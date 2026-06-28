<?php
// TEMPORARY ERROR REPORTING
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../includes/auth.php';
requireAdminOrReport(); 

$pdo = getConnection();
$today = date('Y-m-d');

// =========================================================================
// 1. REDIS CACHING SYSTEM SETUP
// =========================================================================
$redis = null;
try {
    if (class_exists('Redis')) {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 56617); 
        $redis->auth('YOUR_PASSWORD'); 
    }
} catch (Exception $e) {
    $redis = null; 
}

$cache_key = "admin_dashboard_stats_v2_" . $today;
$stats = [];

if ($redis && isset($_GET['refresh']) && $_GET['refresh'] == '1') {
    $redis->del($cache_key);
}

if ($redis && $redis->exists($cache_key)) {
    $stats = json_decode($redis->get($cache_key), true);
} else {
    try {
        // Basic Counts
        $stats['total_agents'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'agent'")->fetchColumn() ?: 0;
        $stats['total_stores'] = $pdo->query("SELECT COUNT(*) FROM stores")->fetchColumn() ?: 0;
        
        // Today's Stats
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT agent_id) FROM daily_assignments WHERE DATE(date_assigned) = ?");
        $stmt->execute([$today]);
        $stats['agents_assigned'] = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM daily_assignments WHERE DATE(date_assigned) = ?");
        $stmt->execute([$today]);
        $stats['total_stores_today'] = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM daily_assignments WHERE DATE(date_assigned) = ? AND status = 'completed'");
        $stmt->execute([$today]);
        $stats['completed_stores'] = $stmt->fetchColumn() ?: 0;

        $stats['completion_rate_today'] = $stats['total_stores_today'] > 0 ? round(($stats['completed_stores'] / $stats['total_stores_today']) * 100, 1) : 0;

        $stmt = $pdo->prepare("SELECT SUM(physical_cash) FROM shop_visits WHERE DATE(visit_date) = ?");
        $stmt->execute([$today]);
        $stats['total_collected'] = $stmt->fetchColumn() ?: 0;

        // =========================================================================
        // DYNAMIC CHART DATA GENERATION (7-DAY ROLLING WINDOWS)
        // =========================================================================

        // 1. Sparkline: 7-Day Collection Trend
        $col_trend = $pdo->query("SELECT DATE(visit_date) as date, SUM(physical_cash) as total FROM shop_visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(visit_date) ORDER BY date ASC")->fetchAll(PDO::FETCH_ASSOC);
        $stats['spark_col_dates'] = array_column($col_trend, 'date');
        $stats['spark_col_totals'] = array_column($col_trend, 'total');

        // 2. Sparkline: 7-Day Completion Rate Trend
        $comp_trend = $pdo->query("SELECT DATE(date_assigned) as date, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END)/COUNT(*)*100 as rate FROM daily_assignments WHERE date_assigned >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(date_assigned) ORDER BY date ASC")->fetchAll(PDO::FETCH_ASSOC);
        $stats['spark_comp_dates'] = array_column($comp_trend, 'date');
        $stats['spark_comp_rates'] = array_column($comp_trend, 'rate');

        // 3. Cash Flow Matrix: Collections vs Bank Deposits
        $bank_trend = $pdo->query("SELECT DATE(created_at) as date, SUM(deposited_cash) as total FROM bank_submissions WHERE status != 'rejected' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY date ASC")->fetchAll(PDO::FETCH_ASSOC);
        $stats['flow_bank_dates'] = array_column($bank_trend, 'date');
        $stats['flow_bank_totals'] = array_column($bank_trend, 'total');

        // 4. Region Coverage (Polar Area)
        $region_coverage = $pdo->query("SELECT r.name, COUNT(sv.id) as visits FROM shop_visits sv JOIN stores s ON sv.shop_id = s.id JOIN regions r ON s.region_id = r.id WHERE sv.visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY r.name ORDER BY visits DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
        $stats['region_labels'] = array_column($region_coverage, 'name');
        $stats['region_data'] = array_column($region_coverage, 'visits');

        // Existing Charts Data
        $stats['reason_data'] = $pdo->query("SELECT reason, COUNT(*) as count FROM shop_visits WHERE reason IS NOT NULL AND reason != '' AND reason != 'Match / No Discrepancy' GROUP BY reason ORDER BY count DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
        $stats['agent_perf_data'] = $pdo->query("SELECT u.name, SUM(sv.physical_cash) as total_cash FROM shop_visits sv JOIN users u ON sv.agent_id = u.id WHERE sv.visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY sv.agent_id ORDER BY total_cash DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        $stats['trend_data'] = $pdo->query("SELECT DATE(visit_date) as v_date, SUM(z_report - (physical_cash + refund + incentive + petty_cash)) as net_discrepancy FROM shop_visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(visit_date) ORDER BY v_date ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Recent Collections Table
        $stats['recent_collections'] = $pdo->query("
            SELECT sv.id, sv.visit_date, u.name as agent_name, s.id as store_id, s.name as store_name, s.city, s.mall, s.entity, s.brand, r.name as region_name, sv.z_report, sv.physical_cash, sv.refund, sv.incentive, sv.petty_cash, sv.discrepancy, sv.currency 
            FROM shop_visits sv JOIN users u ON sv.agent_id = u.id JOIN stores s ON sv.shop_id = s.id LEFT JOIN regions r ON s.region_id = r.id
            WHERE sv.visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY sv.visit_date DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        if ($redis) {
            $redis->setex($cache_key, 300, json_encode($stats));
        }
    } catch (PDOException $e) {
        die("<div style='padding:20px; background:#ffdddd; color:#aa0000;'><h2>Database Query Error</h2><p>" . $e->getMessage() . "</p></div>");
    }
}

extract($stats);

// Reference Data for Selectors
$active_agents = $pdo->query("SELECT id, name FROM users WHERE role = 'agent' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$mall_summaries = $pdo->query("SELECT u.name as agent_name, s.mall, sv.currency, COUNT(sv.id) as stores_visited, SUM(sv.physical_cash) as total_collected FROM shop_visits sv JOIN users u ON sv.agent_id = u.id JOIN stores s ON sv.shop_id = s.id WHERE DATE(sv.visit_date) = CURDATE() GROUP BY u.name, s.mall, sv.currency ORDER BY u.name, s.mall")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Apparels Collection</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200');

        :root {
            --primary-bg: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            --sidebar-bg: rgba(23, 25, 49, 0.95);
            --background-color: #0f0f1a;
            --card-bg: rgba(255, 255, 255, 0.05);
            --card-border: rgba(255, 255, 255, 0.1);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --accent-color: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --elevation: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--primary-bg); color: var(--text-primary); line-height: 1.6; min-height: 100vh; overflow-x: hidden; }
        .container { display: flex; min-height: 100vh; position: relative; width: 100%; }

        /* Sidebar */
        .sidebar { width: 280px; height: 100vh; position: fixed; left: 0; top: 0; background: var(--sidebar-bg); backdrop-filter: blur(16px); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); z-index: 100; overflow-y: auto; }
        .sidebar-header { padding: 24px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-logo > img { height: 40px !important; object-fit: contain !important; border-radius: 8px !important; display: block !important; }
        .sidebar-nav { padding: 15px; }
        .nav-item { display: flex; align-items: center; padding: 12px 15px; margin-bottom: 5px; border-radius: 8px; color: var(--text-secondary); text-decoration: none; transition: 0.3s ease; }
        .nav-item:hover, .nav-item.active { background: rgba(255, 255, 255, 0.05); color: var(--text-primary); }
        .nav-item.active { background: rgba(99, 102, 241, 0.1); color: var(--accent-color); }
        .nav-item .material-symbols-outlined { margin-right: 12px; font-size: 24px; }

        .main-content { flex: 1; margin-left: 280px; min-height: 100vh; display: flex; flex-direction: column; width: calc(100% - 280px); }
        .top-header { display: flex; justify-content: space-between; align-items: center; padding: 0 24px; height: 70px; background: rgba(15, 15, 26, 0.8); border-bottom: 1px solid var(--card-border); backdrop-filter: blur(12px); position: sticky; top: 0; z-index: 99; }
        .header-title { font-size: 20px; font-weight: 600; color: var(--text-primary); }
        .content { flex: 1; padding: 24px; background: var(--background-color); overflow-y: auto; }

        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; border-radius: 8px; font-weight: 500; font-size: 14px; cursor: pointer; border: none; text-decoration: none; margin-right: 10px; transition: 0.3s ease;}
        .btn-success { background: var(--success); color: white; }
        .btn-primary { background: var(--accent-color); color: white; }

        /* Sparkline KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; overflow: hidden; position: relative; box-shadow: var(--elevation); display: flex; flex-direction: column; }
        .kpi-info { padding: 20px; z-index: 2; }
        .kpi-title { font-size: 13px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .kpi-value { font-size: 32px; font-weight: 700; color: #fff; margin: 0; }
        .kpi-chart-wrapper { height: 70px; width: 100%; margin-top: -10px; position: relative; z-index: 1; }

        /* Modern Charts Grid Matrix */
        .charts-main-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-panel { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 20px; box-shadow: var(--elevation); display: flex; flex-direction: column; min-height: 350px; }
        .chart-panel.full-width { grid-column: 1 / -1; min-height: 400px; }
        .chart-panel h4 { font-size: 16px; font-weight: 600; margin: 0 0 20px 0; color: var(--text-primary); display: flex; align-items: center; gap: 8px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px; }
        .chart-container { flex: 1; position: relative; width: 100%; }

        @media (max-width: 1100px) { .charts-main-grid { grid-template-columns: 1fr; } }

        /* Tables & Lists */
        .card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 24px; box-shadow: var(--elevation); margin-bottom: 30px; }
        .table-scroll-container { max-height: 500px; overflow-x: auto; overflow-y: auto; border-radius: 8px; background: rgba(0,0,0,0.1); border: 1px solid var(--card-border); }
        .modern-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 1400px; }
        .modern-table thead th { position: sticky; top: 0; z-index: 10; background: rgba(20, 20, 35, 0.95); backdrop-filter: blur(12px); padding: 16px 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; border-bottom: 2px solid var(--accent-color); }
        .modern-table td { padding: 16px 15px; border-bottom: 1px solid var(--card-border); font-size: 13px; color: var(--text-primary); white-space: nowrap; }
        .modern-table tbody tr:hover { background: rgba(255, 255, 255, 0.03); }
        
        .filter-input { width: 100%; padding: 8px 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid transparent; border-radius: 8px; color: white; font-size: 11px; margin-top: 8px; box-sizing: border-box; }
        .filter-input:focus { background: rgba(0, 0, 0, 0.4); border-color: var(--accent-color); outline: none; }
        .badge { display: inline-block; padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 4px; background: rgba(99, 102, 241, 0.2); color: #A5B4FC; }

        .report-form { display: flex; flex-direction: column; gap: 15px; text-align: left; margin-top: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 13px; color: var(--text-secondary); }
        .form-control { width: 100%; padding: 10px; background: rgba(0,0,0,0.2); border: 1px solid var(--card-border); border-radius: 6px; color: white; font-family: inherit; }
        select.form-control option { background-color: #1e1e2d; color: #ffffff; }

    </style>
    <!-- Open-Source Chart.js Engine -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo"><img src="../images/logo.png" alt="Apparel Collection Logo" width="80px" height="80px" loading="lazy"></div>
            </div>
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active"><span class="material-symbols-outlined">home</span> <span>Dashboard</span></a>
                <a href="assignments.php" class="nav-item"><span class="material-symbols-outlined">assignment</span> <span>Assignments</span></a>
                <a href="agents.php" class="nav-item"><span class="material-symbols-outlined">people</span> <span>Agents</span></a>
                <a href="agent_ledger.php" class="nav-item"><span class="material-symbols-outlined">account_balance_wallet</span> <span>Agent Ledger</span></a>
                <a href="management.php" class="nav-item"><span class="material-symbols-outlined">settings</span> <span>Management</span></a>
                <a href="store_data.php" class="nav-item"><span class="material-symbols-outlined">storefront</span> <span>Store Data</span></a>
                <a href="bank_approvals.php" class="nav-item"><span class="material-symbols-outlined">receipt_long</span> <span>Bank Approvals</span></a>
            </div>
        </div>
        
        <div class="main-content">
            <div class="top-header">
                <div class="header-title">Executive Dashboard Matrix</div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <a href="dashboard_report.php" class="btn btn-primary" style="margin:0; background: rgba(99, 102, 241, 0.15); border: 1px solid rgba(99, 102, 241, 0.3); color: #A5B4FC;"><span class="material-symbols-outlined">analytics</span> Report View</a>
                    <a href="../logout.php" style="color: var(--danger); text-decoration: none; display: flex; align-items: center; gap: 5px; font-size: 14px; font-weight: 500;"><span class="material-symbols-outlined">logout</span> Logout</a>
                </div>
            </div>
            
            <div class="content">
                <?php if ($redis): ?>
                    <div style="font-size: 12px; color: var(--success); display: flex; align-items: center; gap: 5px; margin-bottom: 20px;"><span class="material-symbols-outlined" style="font-size: 16px;">bolt</span> Cached via Redis Engine</div>
                <?php endif; ?>

                <!-- DYNAMIC SPARKLINE KPI CARDS -->
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-info">
                            <div class="kpi-title">Total Cash Collected Today</div>
                            <div class="kpi-value" style="color: var(--success);">SAR <?php echo number_format($total_collected, 2); ?></div>
                        </div>
                        <div class="kpi-chart-wrapper"><canvas id="sparkCollection"></canvas></div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-info">
                            <div class="kpi-title">Today's Completion Rate</div>
                            <div class="kpi-value" style="color: var(--accent-color);"><?php echo $completion_rate_today; ?>%</div>
                        </div>
                        <div class="kpi-chart-wrapper"><canvas id="sparkCompletion"></canvas></div>
                    </div>

                    <div class="kpi-card" style="justify-content: center;">
                        <div class="kpi-info">
                            <div class="kpi-title">Active Fleet Operations</div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                                <div><span style="color: var(--text-secondary); font-size: 12px; display:block;">Agents Working</span><strong style="font-size: 24px; color: #fff;"><?php echo $agents_assigned; ?> <span style="font-size: 14px; color: var(--text-secondary); font-weight: normal;">/ <?php echo $total_agents; ?></span></strong></div>
                                <div><span style="color: var(--text-secondary); font-size: 12px; display:block;">Shops Assigned</span><strong style="font-size: 24px; color: #fff;"><?php echo $total_stores_today; ?></strong></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MAIN CHARTS MATRIX -->
                <div class="charts-main-grid">
                    
                    <!-- Full Width: Cash Flow Matrix -->
                    <div class="chart-panel full-width">
                        <h4><span class="material-symbols-outlined" style="color: var(--success);">account_balance</span> Cash Flow Matrix: Collections vs. Bank Deposits (7 Days)</h4>
                        <div class="chart-container"><canvas id="cashFlowChart"></canvas></div>
                    </div>

                    <!-- Half Width: Discrepancy Breakdown -->
                    <div class="chart-panel">
                        <h4><span class="material-symbols-outlined" style="color: var(--danger);">pie_chart</span> Discrepancy Root Causes</h4>
                        <div class="chart-container"><canvas id="reasonChart"></canvas></div>
                    </div>

                    <!-- Half Width: Region Coverage -->
                    <div class="chart-panel">
                        <h4><span class="material-symbols-outlined" style="color: var(--warning);">radar</span> Geographic Store Audits</h4>
                        <div class="chart-container"><canvas id="regionChart"></canvas></div>
                    </div>

                    <!-- Half Width: Agent Performance -->
                    <div class="chart-panel">
                        <h4><span class="material-symbols-outlined" style="color: var(--accent-color);">leaderboard</span> Top Performing Agents (Volume)</h4>
                        <div class="chart-container"><canvas id="agentBarChart"></canvas></div>
                    </div>

                    <!-- Half Width: Discrepancy Trend -->
                    <div class="chart-panel">
                        <h4><span class="material-symbols-outlined" style="color: #ec4899;">monitoring</span> Net Variance Volume (Shortfalls)</h4>
                        <div class="chart-container"><canvas id="varianceTrendChart"></canvas></div>
                    </div>
                </div>

                <!-- TODAY'S MALL TOTALS -->
                <div class="card">
                    <h3 style="margin: 0 0 20px 0; font-size: 18px; border: none; display: flex; align-items: center; gap: 8px;"><span class="material-symbols-outlined">storefront</span> Today's Mall-Wise Collections</h3>
                    <div class="table-scroll-container" style="max-height: 300px; min-width: 100%;">
                        <table class="modern-table" style="min-width: 100%;">
                            <thead><tr><th>Agent</th><th>Mall</th><th>Stores Visited</th><th>Total Collected</th></tr></thead>
                            <tbody>
                                <?php foreach ($mall_summaries as $summary): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($summary['agent_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($summary['mall']); ?></td>
                                        <td><span class="badge"><?php echo $summary['stores_visited']; ?> Store(s)</span></td>
                                        <td><span style="color: var(--success); font-weight: 600;"><?php echo number_format($summary['total_collected'], 2) . ' ' . htmlspecialchars($summary['currency'] ?? 'SAR'); ?></span></td>
                                    </tr>
                                <?php endforeach; if(empty($mall_summaries)): ?>
                                    <tr><td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 20px;">No store collections recorded today yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- RECENT COLLECTIONS -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 40px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                    <h3 style="margin: 0;">Live Audit Stream</h3>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <form action="api/export_daily.php" method="GET" target="_blank" style="display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.05); padding: 5px 10px; border-radius: 8px; border: 1px solid var(--card-border);">
                            <span class="material-symbols-outlined" style="font-size: 18px;">calendar_month</span>
                            <input type="date" name="start_date" style="background: rgba(0,0,0,0.3); border: 1px solid var(--card-border); color: white; padding: 6px 10px; border-radius: 4px;" value="<?php echo $today; ?>" required>
                            <span style="color: var(--text-secondary); font-size: 12px;">to</span>
                            <input type="date" name="end_date" style="background: rgba(0,0,0,0.3); border: 1px solid var(--card-border); color: white; padding: 6px 10px; border-radius: 4px;" value="<?php echo $today; ?>" required>
                            <button type="submit" class="btn btn-primary" style="padding: 6px 12px; margin: 0; font-size: 12px;"><span class="material-symbols-outlined" style="font-size: 16px;">file_download</span> DL</button>
                        </form>
                        <button id="exportRecentBtn" class="btn btn-success" style="margin:0;"><span class="material-symbols-outlined">download</span> Export Visible</button>
                    </div>
                </div>

                <div class="table-scroll-container" style="margin-bottom: 50px;">
                    <table class="modern-table" id="recentCollectionsTable">
                        <thead>
                            <tr>
                                <th>Date & Time <br><input type="date" class="filter-input" data-col="0" style="padding: 4px; width:100%;"></th>
                                <th>Store ID <br><input type="text" class="filter-input" data-col="1" placeholder="Filter..."></th>
                                <th>Store Name <br><input type="text" class="filter-input" data-col="2" placeholder="Filter..."></th>
                                <th>City <br><input type="text" class="filter-input" data-col="3" placeholder="Filter..."></th>
                                <th>Region <br><input type="text" class="filter-input" data-col="4" placeholder="Filter..."></th>
                                <th>Agent <br><input type="text" class="filter-input" data-col="5" placeholder="Filter..."></th>
                                <th>Physical Cash <br><input type="text" class="filter-input" data-col="6" placeholder="Filter..."></th>
                                <th>Refund <br><input type="text" class="filter-input" data-col="7" placeholder="Filter..."></th>
                                <th>Incentive <br><input type="text" class="filter-input" data-col="8" placeholder="Filter..."></th>
                                <th>Petty Cash <br><input type="text" class="filter-input" data-col="9" placeholder="Filter..."></th>
                                <th>Discrepancy <br><input type="text" class="filter-input" data-col="10" placeholder="Filter..."></th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recent_collections) > 0): foreach ($recent_collections as $col): $colCurrency = htmlspecialchars($col['currency'] ?? 'SAR'); ?>
                                    <tr>
                                        <td><?php echo date('M d, Y h:i A', strtotime($col['visit_date'])); ?></td>
                                        <td style="color: var(--accent-color); font-weight: 500;"><?php echo htmlspecialchars($col['store_id']); ?></td>
                                        <td><?php echo htmlspecialchars($col['store_name']); ?></td>
                                        <td><?php echo htmlspecialchars($col['city'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($col['region_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($col['agent_name']); ?></td>
                                        <td style="font-weight: 600; color: var(--success);"><?php echo number_format($col['physical_cash'], 2) . ' ' . $colCurrency; ?></td>
                                        <td style="color: var(--text-secondary);"><?php echo number_format($col['refund'] ?? 0, 2); ?></td>
                                        <td style="color: var(--text-secondary);"><?php echo number_format($col['incentive'] ?? 0, 2); ?></td>
                                        <td style="color: var(--text-secondary);"><?php echo number_format($col['petty_cash'] ?? 0, 2); ?></td>
                                        <td style="font-weight: 600;">
                                            <?php 
                                            $z_report   = (float)($col['z_report'] ?? 0);
                                            $collected  = (float)($col['physical_cash'] ?? 0);
                                            $refund     = (float)($col['refund'] ?? 0);
                                            $incentive  = (float)($col['incentive'] ?? 0);
                                            $petty_cash = (float)($col['petty_cash'] ?? 0);

                                            $basic_disc = $z_report - $collected;
                                            $final_disc = $basic_disc - ($incentive + $refund + $petty_cash);

                                            if ($final_disc > 0) {
                                                echo '<span style="color: var(--danger);">+' . number_format($final_disc, 2) . ' ' . $colCurrency . '</span>';
                                            } elseif ($final_disc < 0) {
                                                echo '<span style="color: var(--warning);">' . number_format($final_disc, 2) . ' ' . $colCurrency . '</span>';
                                            } else {
                                                echo '<span style="color: var(--text-secondary);")>0.00 ' . $colCurrency . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><a href="view_receipt.php?id=<?php echo $col['id']; ?>" target="_blank" class="btn" style="padding: 6px 12px; font-size: 12px; background: rgba(99, 102, 241, 0.1); color: var(--accent-color); border: 1px solid var(--accent-color); margin: 0;">View</a></td>
                                    </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="12" style="text-align: center; padding: 20px; color: var(--text-secondary);">No collections recorded yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- EXPORT REPORTS MODULES -->
                <h3 style="margin-top: 50px;"><span class="material-symbols-outlined" style="vertical-align: middle; color: var(--accent-color);">query_stats</span> Advanced Report Generators</h3>
                <div class="kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); align-items: start;">
                    <div class="card" style="margin-bottom: 0;">
                        <h4 style="font-size: 16px; margin-bottom: 5px; color: var(--success);">Cash Collection</h4>
                        <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 15px;">Export physical cash collected within a range.</p>
                        <form action="api/export_daily.php" method="GET" class="report-form" target="_blank">
                            <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-01'); ?>" required></div>
                            <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control" value="<?php echo $today; ?>" required></div>
                            <button type="submit" class="btn btn-success" style="width: 100%;"><span class="material-symbols-outlined">download</span> Generate</button>
                        </form>
                    </div>

                    <div class="card" style="margin-bottom: 0;">
                        <h4 style="font-size: 16px; margin-bottom: 5px; color: var(--accent-color);">Agent Performance</h4>
                        <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 15px;">Analyze shop visits and collections per user.</p>
                        <form action="api/export_weekly.php" method="GET" class="report-form" target="_blank">
                            <div class="form-group"><label>Select Agent</label>
                                <select name="agent_id" class="form-control" required><option value="all">-- All Agents --</option>
                                    <?php foreach($active_agents as $agent): ?><option value="<?php echo $agent['id']; ?>"><?php echo htmlspecialchars($agent['name']); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <div class="form-group" style="flex: 1;"><label>Start</label><input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>" required></div>
                                <div class="form-group" style="flex: 1;"><label>End</label><input type="date" name="end_date" class="form-control" value="<?php echo $today; ?>" required></div>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;"><span class="material-symbols-outlined">analytics</span> Generate</button>
                        </form>
                    </div>

                    <div class="card" style="margin-bottom: 0;">
                        <h4 style="font-size: 16px; margin-bottom: 5px; color: #10b981;">Bank Deposits</h4>
                        <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 15px;">Export bank submissions and ATM shortages.</p>
                        <form action="api/export_bank_submissions.php" method="GET" class="report-form" target="_blank">
                            <div style="display: flex; gap: 10px;">
                                <div class="form-group" style="flex: 1;"><label>Start Date</label><input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-01'); ?>" required></div>
                                <div class="form-group" style="flex: 1;"><label>End Date</label><input type="date" name="end_date" class="form-control" value="<?php echo $today; ?>" required></div>
                            </div>
                            <div class="form-group"><label>Submission Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="all">All Submissions</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success" style="width: 100%;"><span class="material-symbols-outlined">download</span> Generate</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- GRAPHICS VISUALIZATION ENGINE SCRIPTS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            // --- Live Table Filtering ---
            const table = document.getElementById('recentCollectionsTable');
            const filterInputs = table.querySelectorAll('.filter-input');
            const tableRows = table.querySelectorAll('tbody tr');

            filterInputs.forEach(input => {
                input.addEventListener('input', function() {
                    const filters = Array.from(filterInputs).map(inp => ({
                        index: parseInt(inp.getAttribute('data-col')),
                        value: inp.value.toLowerCase().trim(),
                        isDate: inp.type === 'date' 
                    }));

                    tableRows.forEach(row => {
                        if(row.cells.length === 1) return;
                        let isMatch = true;
                        filters.forEach(filter => {
                            if (filter.value !== '') {
                                if (filter.isDate) {
                                    const cellDate = new Date(row.cells[filter.index].textContent).toISOString().split('T')[0];
                                    if (cellDate !== filter.value) isMatch = false;
                                } else {
                                    if (!row.cells[filter.index].textContent.toLowerCase().includes(filter.value)) isMatch = false;
                                }
                            }
                        });
                        row.style.display = isMatch ? '' : 'none';
                    });
                });
            });

            // --- Export Visible Routine ---
            document.getElementById('exportRecentBtn').addEventListener('click', function() {
                let csv = [];
                let headers = [];
                let headerCells = table.querySelectorAll('thead th');
                for (let i = 0; i < headerCells.length - 1; i++) {
                    headers.push('"' + headerCells[i].childNodes[0].nodeValue.trim().replace(/"/g, '""') + '"');
                }
                csv.push(headers.join(','));

                tableRows.forEach(row => {
                    if (row.style.display !== 'none' && row.cells.length > 1) {
                        let rowData = [];
                        for (let i = 0; i < row.cells.length - 1; i++) {
                            rowData.push('"' + row.cells[i].innerText.trim().replace(/"/g, '""') + '"');
                        }
                        csv.push(rowData.join(','));
                    }
                });

                let csvContent = "data:text/csv;charset=utf-8," + "\uFEFF" + csv.join("\n");
                let link = document.createElement("a");
                link.setAttribute("href", encodeURI(csvContent));
                link.setAttribute("download", "Recent_Collections_Filtered_" + new Date().toISOString().slice(0,10) + ".csv");
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });

            // ==========================================
            // CHART.JS INITIALIZATION
            // ==========================================
            
            // Common Options for Sparklines
            const sparklineOptions = {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                scales: { x: { display: false }, y: { display: false, min: 0 } },
                layout: { padding: 0 }
            };

            // 1. Sparkline: Cash Collected
            new Chart(document.getElementById('sparkCollection').getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($spark_col_dates); ?>,
                    datasets: [{
                        data: <?php echo json_encode($spark_col_totals); ?>,
                        borderColor: '#10b981', borderWidth: 2, tension: 0.4,
                        backgroundColor: 'rgba(16, 185, 129, 0.1)', fill: true, pointRadius: 0
                    }]
                },
                options: sparklineOptions
            });

            // 2. Sparkline: Completion Rates
            new Chart(document.getElementById('sparkCompletion').getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($spark_comp_dates); ?>,
                    datasets: [{
                        data: <?php echo json_encode($spark_comp_rates); ?>,
                        borderColor: '#6366f1', borderWidth: 2, tension: 0.4,
                        backgroundColor: 'rgba(99, 102, 241, 0.1)', fill: true, pointRadius: 0
                    }]
                },
                options: sparklineOptions
            });

            // 3. Cash Flow Matrix (Dual Line Chart)
            new Chart(document.getElementById('cashFlowChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($spark_col_dates); ?>,
                    datasets: [
                        {
                            label: 'Collected (In)',
                            data: <?php echo json_encode($spark_col_totals); ?>,
                            borderColor: '#10b981', backgroundColor: 'transparent',
                            borderWidth: 3, tension: 0.3, pointBackgroundColor: '#10b981'
                        },
                        {
                            label: 'Deposited (Out)',
                            data: <?php echo json_encode($flow_bank_totals); ?>,
                            borderColor: '#3b82f6', backgroundColor: 'transparent',
                            borderWidth: 3, tension: 0.3, pointBackgroundColor: '#3b82f6', borderDash: [5, 5]
                        }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { labels: { color: '#8a8f98', font: { family: 'Inter' } } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#8a8f98' } },
                        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8a8f98' } }
                    }
                }
            });

            // 4. Doughnut: Discrepancy Reasons
            new Chart(document.getElementById('reasonChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($reason_data, 'reason')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($reason_data, 'count')); ?>,
                        backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#ec4899', '#8b5cf6', '#10b981'],
                        borderWidth: 0, hoverOffset: 4
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { color: '#8a8f98', font: { size: 10 } } } } }
            });

            // 5. Polar Area: Regional Coverage
            new Chart(document.getElementById('regionChart').getContext('2d'), {
                type: 'polarArea',
                data: {
                    labels: <?php echo json_encode(array_column($region_data, 'name') ?? ['Region A', 'Region B']); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($region_data, 'visits') ?? [1, 1]); ?>,
                        backgroundColor: ['rgba(99, 102, 241, 0.6)', 'rgba(16, 185, 129, 0.6)', 'rgba(245, 158, 11, 0.6)', 'rgba(239, 68, 68, 0.6)', 'rgba(139, 92, 246, 0.6)'],
                        borderColor: 'transparent'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { color: '#8a8f98', font: { size: 10 } } } }, scales: { r: { ticks: { display: false }, grid: { color: 'rgba(255,255,255,0.05)' } } } }
            });

            // 6. Bar Chart: Agent Volume
            new Chart(document.getElementById('agentBarChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($agent_perf_data, 'name')); ?>,
                    datasets: [{
                        label: 'Total Collected (7 Days)',
                        data: <?php echo json_encode(array_column($agent_perf_data, 'total_cash')); ?>,
                        backgroundColor: 'rgba(99, 102, 241, 0.3)', borderColor: '#6366f1', borderWidth: 1, borderRadius: 4
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { color: '#8a8f98', font: { size: 10 } } }, y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8a8f98' } } } }
            });

            // 7. Line Chart: Variance Trend
            new Chart(document.getElementById('varianceTrendChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($trend_data, 'v_date')); ?>,
                    datasets: [{
                        label: 'Net Variance',
                        data: <?php echo json_encode(array_column($trend_data, 'net_discrepancy')); ?>,
                        borderColor: '#ec4899', backgroundColor: 'rgba(236, 72, 153, 0.1)',
                        fill: true, tension: 0.3, borderWidth: 2, pointBackgroundColor: '#ec4899'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { color: '#8a8f98' } }, y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8a8f98' } } } }
            });

        });
    </script>
</body>
</html>