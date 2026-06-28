<?php
// TEMPORARY ERROR REPORTING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
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
    error_log("Redis connection failed: " . $e->getMessage());
    $redis = null; 
}

$cache_key = "admin_dashboard_stats_" . $today;
$stats = [];

// --- MANUAL CACHE OVERRIDE ---
if ($redis && isset($_GET['refresh']) && $_GET['refresh'] == '1') {
    $redis->del($cache_key);
    echo "<div style='background: #10b981; color: white; padding: 10px; text-align: center; z-index: 9999; position: relative; font-family: sans-serif; font-weight: bold;'>CACHE CLEARED SUCCESSFULLY! FETCHING FRESH DATA.</div>";
}
// -----------------------------

if ($redis && $redis->exists($cache_key)) {
    $stats = json_decode($redis->get($cache_key), true);
} else {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_agents FROM users WHERE role = 'agent'");
        $stmt->execute();
        $stats['total_agents'] = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) as total_stores FROM stores");
        $stmt->execute();
        $stats['total_stores'] = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) as total_regions FROM regions");
        $stmt->execute();
        $stats['total_regions'] = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) as completed_assignments FROM daily_assignments WHERE status = 'completed'");
        $stmt->execute();
        $stats['completed_assignments'] = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) as total_assignments FROM daily_assignments");
        $stmt->execute();
        $stats['total_assignments'] = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) as total_submissions FROM bank_submissions");
        $stmt->execute();
        $stats['total_submissions'] = $stmt->fetchColumn() ?: 0;

        $stats['completion_percentage'] = $stats['total_assignments'] > 0 ? round(($stats['completed_assignments'] / $stats['total_assignments']) * 100, 2) : 0;

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT da.agent_id) as agents_assigned FROM daily_assignments da WHERE DATE(da.date_assigned) = ?");
        $stmt->execute([$today]);
        $stats['agents_assigned'] = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) as total_stores_today FROM daily_assignments da WHERE DATE(da.date_assigned) = ?");
        $stmt->execute([$today]);
        $stats['total_stores_today'] = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) as completed_stores FROM daily_assignments da WHERE DATE(da.date_assigned) = ? AND da.status = 'completed'");
        $stmt->execute([$today]);
        $stats['completed_stores'] = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.mall) as total_malls FROM daily_assignments da JOIN stores s ON da.store_id = s.id WHERE DATE(da.date_assigned) = ?");
        $stmt->execute([$today]);
        $stats['total_malls'] = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.mall) as completed_malls FROM daily_assignments da JOIN stores s ON da.store_id = s.id WHERE DATE(da.date_assigned) = ? AND da.status = 'completed'");
        $stmt->execute([$today]);
        $stats['completed_malls'] = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) as total_submissions_today FROM bank_submissions bs WHERE DATE(bs.created_at) = ?");
        $stmt->execute([$today]);
        $stats['total_submissions_today'] = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT SUM(physical_cash) as total_collected FROM shop_visits WHERE DATE(visit_date) = ?");
        $stmt->execute([$today]);
        $stats['total_collected'] = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) as completed_orders FROM daily_assignments da WHERE DATE(da.date_assigned) = ? AND da.status = 'completed'");
        $stmt->execute([$today]);
        $stats['completed_orders'] = $stmt->fetchColumn() ?: 0;

        $stats['completion_rate_today'] = $stats['total_stores_today'] > 0 ? round(($stats['completed_stores'] / $stats['total_stores_today']) * 100, 2) : 0;

        // FIXED QUERY: Added sv.z_report to ensure math calculation works
        $stmt = $pdo->prepare("
            SELECT 
                sv.id, sv.visit_date, u.name as agent_name, 
                s.id as store_id, s.name as store_name, s.city, s.mall, s.entity, s.brand, r.name as region_name,
                sv.z_report, sv.physical_cash, sv.refund, sv.incentive, sv.petty_cash, sv.discrepancy, sv.currency 
            FROM shop_visits sv 
            JOIN users u ON sv.agent_id = u.id 
            JOIN stores s ON sv.shop_id = s.id 
            LEFT JOIN regions r ON s.region_id = r.id
            WHERE sv.visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY sv.visit_date DESC
        ");
        $stmt->execute();
        $stats['recent_collections'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($redis) {
            $redis->setex($cache_key, 300, json_encode($stats));
        }
    } catch (PDOException $e) {
        die("<div style='padding:20px; background:#ffdddd; color:#aa0000;'><h2>Database Query Error</h2><p>" . $e->getMessage() . "</p></div>");
    }
}

extract($stats);

// Fetch active agents
try {
    $agent_stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'agent' ORDER BY name ASC");
    $active_agents = $agent_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Failed to fetch agents list: " . $e->getMessage());
}

// Fetch Mall-Wise Totals for Today
try {
    $mall_summary_stmt = $pdo->query("
        SELECT 
            u.name as agent_name,
            s.mall,
            sv.currency,
            COUNT(sv.id) as stores_visited,
            SUM(sv.physical_cash) as total_collected
        FROM shop_visits sv
        JOIN users u ON sv.agent_id = u.id
        JOIN stores s ON sv.shop_id = s.id
        WHERE DATE(sv.visit_date) = CURDATE()
        GROUP BY u.name, s.mall, sv.currency
        ORDER BY u.name, s.mall
    ");
    $mall_summaries = $mall_summary_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mall_summaries = [];
}
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
            --card-bg: rgba(255, 255, 255, 0.08);
            --card-border: rgba(255, 255, 255, 0.1);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --accent-color: #6366f1;
            --accent-hover: #4f46e5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --elevation-1: 0 4px 6px rgba(0, 0, 0, 0.1);
            --elevation-2: 0 10px 15px rgba(0, 0, 0, 0.2);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--primary-bg); color: var(--text-primary); line-height: 1.6; min-height: 100vh; overflow-x: hidden; }
        .container { display: flex; min-height: 100vh; position: relative; width: 100%; }

        .sidebar { width: 280px; height: 100vh; position: fixed; left: 0; top: 0; background: var(--sidebar-bg); backdrop-filter: blur(16px); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); z-index: 100; overflow-y: auto; }
        .sidebar-header { padding: 24px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-logo > img { height: 40px !important; object-fit: contain !important; border-radius: 8px !important; display: block !important; }
        .sidebar-nav { padding: 15px; }
        .nav-item { display: flex; align-items: center; padding: 12px 15px; margin-bottom: 5px; border-radius: 8px; color: var(--text-secondary); text-decoration: none; transition: background 0.3s ease; }
        .nav-item:hover, .nav-item.active { background: rgba(255, 255, 255, 0.05); color: var(--text-primary); }
        .nav-item.active { background: rgba(99, 102, 241, 0.1); color: var(--accent-color); }
        .nav-item .material-symbols-outlined { margin-right: 12px; font-size: 24px; }

        .main-content { flex: 1; margin-left: 280px; min-height: 100vh; display: flex; flex-direction: column; width: calc(100% - 280px); }
        .top-header { display: flex; justify-content: space-between; align-items: center; padding: 0 24px; height: 70px; background: var(--card-bg); box-shadow: var(--elevation-1); position: sticky; top: 0; z-index: 99; }
        .header-title { font-size: 20px; font-weight: 600; color: var(--text-primary); }
        .hamburger { display: none; flex-direction: column; justify-content: space-between; width: 30px; height: 21px; cursor: pointer; }
        .hamburger span { display: block; height: 3px; width: 100%; background: var(--text-primary); border-radius: 3px; }
        .logout-btn { display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: rgba(239, 68, 68, 0.1); color: var(--danger); border-radius: 8px; text-decoration: none; }
        .content { flex: 1; padding: 24px; background: var(--background-color); overflow-y: auto; }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 99; opacity: 0; transition: opacity 0.3s ease; }

        .dashboard-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; }
        .stat-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 20px; text-align: center; box-shadow: var(--elevation-1); }
        .stat-card h3 { font-size: 28px; font-weight: 700; margin-bottom: 8px; color: var(--text-primary); }
        .stat-card p { color: var(--text-secondary); font-size: 14px; margin: 0; }
        .page-title { font-size: 24px; font-weight: 600; margin-bottom: 24px; position: relative; padding-bottom: 12px; }
        .content h3 { font-size: 20px; margin: 30px 0 20px; padding-bottom: 10px; border-bottom: 1px solid var(--card-border); }

        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; border-radius: 8px; font-weight: 500; font-size: 14px; cursor: pointer; border: none; text-decoration: none; margin-right: 10px; transition: 0.3s ease;}
        .btn-success { background: var(--success); color: white; }
        .btn-primary { background: var(--accent-color); color: white; }

        .report-form { display: flex; flex-direction: column; gap: 15px; text-align: left; margin-top: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 13px; color: var(--text-secondary); }
        .form-control { width: 100%; padding: 10px; background: rgba(0,0,0,0.2); border: 1px solid var(--card-border); border-radius: 6px; color: white; font-family: inherit; }
        select.form-control { background-color: rgba(0,0,0,0.2); color: #ffffff; }
        select.form-control option { background-color: #1e1e2d; color: #ffffff; }
        
        .today-highlight { background: rgba(99, 102, 241, 0.15) !important; border-color: var(--accent-color) !important; }
        .today-highlight h3 { color: var(--accent-color) !important; }
        .amount-card { background: rgba(16, 185, 129, 0.15) !important; border-color: var(--success) !important; }
        .amount-card h3 { color: var(--success) !important; }
        .badge { display: inline-block; padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 4px; background: rgba(99, 102, 241, 0.2); color: #A5B4FC; }

        .table-scroll-container { 
            max-height: 500px; 
            overflow-x: auto; 
            overflow-y: auto; 
            border: 1px solid var(--card-border); 
            border-radius: 12px; 
            background: var(--card-bg); 
            box-shadow: var(--elevation-2); 
        }
        
        .table-scroll-container::-webkit-scrollbar { height: 10px; width: 10px; }
        .table-scroll-container::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.2); border-radius: 0 0 12px 12px; }
        .table-scroll-container::-webkit-scrollbar-thumb { background: var(--accent-color); border-radius: 10px; border: 2px solid rgba(0, 0, 0, 0.2); }
        .table-scroll-container::-webkit-scrollbar-thumb:hover { background: var(--accent-hover); }

        .modern-table { 
            width: 100%; 
            border-collapse: separate; 
            border-spacing: 0;
            min-width: 1600px; 
        }
        
        .modern-table thead th { 
            position: sticky; 
            top: 0; 
            z-index: 10; 
            background: rgba(20, 20, 35, 0.85); 
            backdrop-filter: blur(12px); 
            -webkit-backdrop-filter: blur(12px);
            padding: 16px 15px; 
            text-align: left; 
            font-size: 12px; 
            font-weight: 600; 
            color: var(--text-secondary); 
            text-transform: uppercase; 
            border-bottom: 2px solid var(--accent-color); 
            vertical-align: top;
        }

        .modern-table td { 
            padding: 16px 15px; 
            border-bottom: 1px solid var(--card-border); 
            font-size: 13px; 
            color: var(--text-primary); 
            white-space: nowrap;
        }
        
        .modern-table tbody tr { transition: background-color 0.2s ease; }
        .modern-table tbody tr:hover { background: rgba(255, 255, 255, 0.05); }

        .filter-input { 
            width: 100%; 
            padding: 8px 14px; 
            background: rgba(255, 255, 255, 0.05); 
            border: 1px solid transparent; 
            border-radius: 20px; 
            color: white; 
            font-size: 11px; 
            margin-top: 10px; 
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        .filter-input::placeholder { color: rgba(255, 255, 255, 0.3); }
        .filter-input:focus {
            background: rgba(0, 0, 0, 0.4);
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); 
            outline: none;
        }

        @media (max-width: 992px) {
            .hamburger { display: flex; }
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
            .overlay.active { display: block; opacity: 1; }
        }

    </style>
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" type="image/png" href="/images/icon-192x192.png">
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="../images/logo.png" alt="Apparel Collection Logo" width="80px" height="80px" loading="lazy">
                </div>
            </div>
            
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <span class="material-symbols-outlined">home</span> <span>Dashboard</span>
                </a>
                <a href="assignments.php" class="nav-item">
                    <span class="material-symbols-outlined">assignment</span> <span>Assignments</span>
                </a>
                <a href="agents.php" class="nav-item">
                    <span class="material-symbols-outlined">people</span> <span>Agents</span>
                </a>
                <a href="store_data.php" class="nav-item">
                    <span class="material-symbols-outlined">storefront</span> <span>Store Data</span>
                </a>
                <a href="bank_approvals.php" class="nav-item">
                    <span class="material-symbols-outlined">receipt_long</span> <span>Bank Approvals</span>
                </a>
            </div>
        </div>
        
        <div class="overlay"></div>
        
        <div class="main-content">
            <div class="top-header">
                <div class="header-left">
                    <div class="hamburger"><span></span><span></span><span></span></div>
                    <div class="header-title">Dashboard</div>
                </div>
                <div class="header-right" style="display: flex; gap: 12px; align-items: center;">
                    <a href="dashboard_report.php" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; margin: 0; border-radius: 8px; font-size: 14px; background: rgba(99, 102, 241, 0.15); color: #A5B4FC; border: 1px solid rgba(99, 102, 241, 0.3);">
                        <span class="material-symbols-outlined" style="font-size: 20px;">analytics</span> Report View
                    </a>
                    <a href="../api_logout.php" class="logout-btn" style="margin: 0;">
                        <span class="material-symbols-outlined">logout</span> Logout
                    </a>
                </div>
            </div>
            
            <div class="content">
                <h2 class="page-title">System Overview</h2>
                
                <?php if ($redis): ?>
                    <div style="font-size: 12px; color: var(--success); display: flex; align-items: center; gap: 5px; margin-top: -15px; margin-bottom: 20px;">
                        <span class="material-symbols-outlined" style="font-size: 16px;">bolt</span> Cached via Redis (Updates every 5 mins)
                    </div>
                <?php endif; ?>

                <div class="dashboard-stats">
                    <div class="stat-card today-highlight">
                        <h3><?php echo $agents_assigned; ?></h3><p>Agents Working Today</p>
                    </div>
                    <div class="stat-card today-highlight">
                        <h3><?php echo $total_stores_today; ?></h3><p>Shops Assigned Today</p>
                    </div>
                    <div class="stat-card today-highlight">
                        <h3><?php echo $completion_rate_today; ?>%</h3><p>Today's Completion</p>
                    </div>
                    <div class="stat-card amount-card">
                        <h3>SAR <?php echo number_format($total_collected, 2); ?></h3><p>Amount Collected Today</p>
                    </div>
                </div>

                <h3>Overall Statistics</h3>
                <div class="dashboard-stats">
                    <div class="stat-card"><h3><?php echo $total_agents; ?></h3><p>Total Agents</p></div>
                    <div class="stat-card"><h3><?php echo $total_stores; ?></h3><p>Total Stores</p></div>
                    <div class="stat-card"><h3><?php echo $total_regions; ?></h3><p>Total Regions</p></div>
                    <div class="stat-card"><h3><?php echo $completion_percentage; ?>%</h3><p>Overall Completion</p></div>
                </div>

                <h3>Today's Detailed Statistics</h3>
                <div class="dashboard-stats">
                    <div class="stat-card"><h3><?php echo $completed_stores; ?>/<?php echo $total_stores_today; ?></h3><p>Shops Completed</p></div>
                    <div class="stat-card"><h3><?php echo $completed_malls; ?>/<?php echo $total_malls; ?></h3><p>Malls Completed</p></div>
                    <div class="stat-card"><h3><?php echo $total_submissions_today; ?></h3><p>Bank Submissions Today</p></div>
                    <div class="stat-card"><h3><?php echo $completed_orders; ?></h3><p>Completed Orders</p></div>
                </div>

                <div class="card" style="margin-top: 30px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 20px; box-shadow: var(--elevation-1);">
                    <h3 style="margin-bottom: 16px; margin-top: 0; border: none; color: var(--text-primary);">
                        <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">storefront</span>
                        Today's Mall-Wise Collections
                    </h3>
                    <div class="table-scroll-container" style="min-width: 100%; border: none; box-shadow: none;">
                        <table class="modern-table" style="min-width: 100%;">
                            <thead>
                                <tr>
                                    <th>Agent</th>
                                    <th>Mall</th>
                                    <th>Stores Visited</th>
                                    <th>Total Collected</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mall_summaries as $summary): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($summary['agent_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($summary['mall']); ?></td>
                                        <td><span class="badge"><?php echo $summary['stores_visited']; ?> Store(s)</span></td>
                                        <td>
                                            <span style="color: var(--success); font-weight: 600; font-size: 14px;">
                                                <?php echo number_format($summary['total_collected'], 2) . ' ' . htmlspecialchars($summary['currency'] ?? 'SAR'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if(empty($mall_summaries)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                            No store collections recorded today yet.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 40px; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                    <h3 style="margin: 0; border: none; padding: 0;">Recent Collections</h3>
                    
                            
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <form action="api/export_daily.php" method="GET" target="_blank" style="display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.05); padding: 5px 10px; border-radius: 8px; border: 1px solid var(--card-border);">
                            <span class="material-symbols-outlined" style="color: var(--text-secondary); font-size: 18px;">calendar_month</span>
                            <input type="date" name="start_date" style="background: rgba(0,0,0,0.3); border: 1px solid var(--card-border); color: white; padding: 6px 10px; border-radius: 4px; font-size: 12px; width: 120px;" value="<?php echo $today; ?>" required>
                            <span style="color: var(--text-secondary); font-size: 12px;">to</span>
                            <input type="date" name="end_date" style="background: rgba(0,0,0,0.3); border: 1px solid var(--card-border); color: white; padding: 6px 10px; border-radius: 4px; font-size: 12px; width: 120px;" value="<?php echo $today; ?>" required>
                            <button type="submit" class="btn btn-primary" style="padding: 6px 12px; margin: 0; font-size: 12px;">
                                <span class="material-symbols-outlined" style="font-size: 16px; margin-right: 4px;">file_download</span> DL Report
                            </button>
                        </form>
                        
                        <button id="exportRecentBtn" class="btn btn-success" style="margin:0; padding: 8px 16px; font-size: 13px;">
                            <span class="material-symbols-outlined" style="margin-right: 5px; font-size: 18px;">download</span> Export Visible
                        </button>
                    </div>
                </div>

                <div class="table-scroll-container" style="margin-bottom: 40px;">
                    <table class="modern-table" id="recentCollectionsTable">
                        <thead>
                            <tr>
                                <th>Date & Time <br><input type="date" class="filter-input" data-col="0" style="padding: 4px; width: 100%;"></th>
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
                            <?php if (count($recent_collections) > 0): ?>
                                <?php foreach ($recent_collections as $col): 
                                    $colCurrency = htmlspecialchars($col['currency'] ?? 'SAR');
                                ?>
                                    <tr>
                                        <td><?php echo date('M d, Y h:i A', strtotime($col['visit_date'])); ?></td>
                                        <td style="color: var(--accent-color); font-weight: 500;"><?php echo htmlspecialchars($col['store_id']); ?></td>
                                        <td><?php echo htmlspecialchars($col['store_name']); ?></td>
                                        <td><?php echo htmlspecialchars($col['city'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($col['region_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($col['agent_name']); ?></td>
                                        <td style="font-weight: 600; color: var(--success);">
                                            <?php echo number_format($col['physical_cash'], 2) . ' ' . $colCurrency; ?>
                                        </td>
                                        <td style="color: var(--text-secondary);">
                                            <?php echo number_format($col['refund'] ?? 0, 2); ?>
                                        </td>
                                        <td style="color: var(--text-secondary);">
                                            <?php echo number_format($col['incentive'] ?? 0, 2); ?>
                                        </td>
                                        <td style="color: var(--text-secondary);">
                                            <?php echo number_format($col['petty_cash'] ?? 0, 2); ?>
                                        </td>
                                        <td style="font-weight: 600;">
                                            <?php 
                                            // EXPLICIT LOGIC MATH
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
                                                echo '<span style="color: var(--text-secondary);">0.00 ' . $colCurrency . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="view_receipt.php?id=<?php echo $col['id']; ?>" target="_blank" class="btn" style="padding: 6px 12px; font-size: 12px; background: rgba(99, 102, 241, 0.1); color: var(--accent-color); border: 1px solid var(--accent-color); margin: 0;">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="12" style="text-align: center; padding: 20px; color: var(--text-secondary);">No collections recorded yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        </table>
                </div>

                <h3 style="margin-top: 50px;">
                    <span class="material-symbols-outlined" style="vertical-align: middle; color: var(--accent-color);">query_stats</span> 
                    Advanced Report Generator
                </h3>
                
                <div class="dashboard-stats" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); align-items: start;">
                    
                    <div class="stat-card" style="padding: 25px;">
                        <div style="display: flex; justify-content: center; margin-bottom: 15px; color: var(--success);"><span class="material-symbols-outlined" style="font-size: 36px;">payments</span></div>
                        <h4 style="font-size: 18px; margin-bottom: 5px;">Cash Collection Report</h4>
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 15px;">Export all physical cash collected within a date range.</p>
                        <form action="api/export_daily.php" method="GET" class="report-form" target="_blank">
                            <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-01'); ?>" required></div>
                            <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control" value="<?php echo $today; ?>" required></div>
                            <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 10px;"><span class="material-symbols-outlined" style="margin-right: 8px;">download</span> Generate</button>
                        </form>
                    </div>

                    <div class="stat-card" style="padding: 25px;">
                        <div style="display: flex; justify-content: center; margin-bottom: 15px; color: var(--accent-color);"><span class="material-symbols-outlined" style="font-size: 36px;">badge</span></div>
                        <h4 style="font-size: 18px; margin-bottom: 5px;">Agent Performance Data</h4>
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 15px;">Analyze shop visits and collection for specific users.</p>
                        <form action="api/export_weekly.php" method="GET" class="report-form" target="_blank">
                            <div class="form-group"><label>Select Agent</label>
                                <select name="agent_id" class="form-control" required>
                                    <option value="all">-- All Agents --</option>
                                    <?php foreach($active_agents as $agent): ?><option value="<?php echo $agent['id']; ?>"><?php echo htmlspecialchars($agent['name']); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <div class="form-group" style="flex: 1;"><label>Start</label><input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>" required></div>
                                <div class="form-group" style="flex: 1;"><label>End</label><input type="date" name="end_date" class="form-control" value="<?php echo $today; ?>" required></div>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;"><span class="material-symbols-outlined" style="margin-right: 8px;">analytics</span> Generate</button>
                        </form>
                    </div>

                    <div class="stat-card" style="padding: 25px;">
                        <div style="display: flex; justify-content: center; margin-bottom: 15px; color: var(--warning);"><span class="material-symbols-outlined" style="font-size: 36px;">store</span></div>
                        <h4 style="font-size: 18px; margin-bottom: 5px;">Store Coverage Report</h4>
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 15px;">Export data filtered by regions, malls, or entity status.</p>
                        <form action="api/export_collections.php" method="GET" class="report-form" target="_blank">
                            <div class="form-group"><label>Report Focus</label>
                                <select name="focus" class="form-control" required>
                                    <option value="completed">Completed Shops</option>
                                    <option value="pending">Pending Shops</option>
                                    <option value="all">All Data</option>
                                </select>
                            </div>
                            <div class="form-group"><label>Format</label>
                                <select name="format" class="form-control"><option value="csv">CSV File</option><option value="excel">Excel</option></select>
                            </div>
                            <button type="submit" class="btn" style="width: 100%; margin-top: 10px; background: var(--warning); color: #000;"><span class="material-symbols-outlined" style="margin-right: 8px;">table_view</span> Export Status</button>
                        </form>
                    </div>

                    <div class="stat-card" style="padding: 25px;">
                        <div style="display: flex; justify-content: center; margin-bottom: 15px; color: #10b981;">
                            <span class="material-symbols-outlined" style="font-size: 36px;">account_balance</span>
                        </div>
                        <h4 style="font-size: 18px; margin-bottom: 5px;">Bank Deposits Report</h4>
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 15px;">Export bank submissions, discrepancies, and ATM shortfall reasons.</p>
                        
                        <form action="api/export_bank_submissions.php" method="GET" class="report-form" target="_blank">
                            <div style="display: flex; gap: 10px;">
                                <div class="form-group" style="flex: 1;">
                                    <label>Start Date</label>
                                    <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-01'); ?>" required>
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label>End Date</label>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo $today; ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Submission Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="all">All Submissions</option>
                                    <option value="pending">Pending Approval</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px; background: #10b981; color: white;">
                                <span class="material-symbols-outlined" style="margin-right: 8px;">download</span> Generate
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector('.hamburger');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.overlay');
            if (hamburger && sidebar && overlay) {
                function toggleMenu() { sidebar.classList.toggle('active'); overlay.classList.toggle('active'); }
                function closeMenu() { sidebar.classList.remove('active'); overlay.classList.remove('active'); }
                hamburger.addEventListener('click', (e) => { e.stopPropagation(); toggleMenu(); });
                overlay.addEventListener('click', closeMenu);
            }

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
                    const cellText = row.cells[filter.index].textContent.toLowerCase();
                    
                    if (filter.isDate) {
                        const cellDate = new Date(row.cells[filter.index].textContent).toISOString().split('T')[0];
                        if (cellDate !== filter.value) isMatch = false;
                    } else {
                        if (!cellText.includes(filter.value)) isMatch = false;
                    }
                }
            });
            row.style.display = isMatch ? '' : 'none';
        });
    });
});

            document.getElementById('exportRecentBtn').addEventListener('click', function() {
                let csv = [];
                let headers = [];
                let headerCells = table.querySelectorAll('thead th');
                for (let i = 0; i < headerCells.length - 1; i++) {
                    let headerText = headerCells[i].childNodes[0].nodeValue.trim();
                    headers.push('"' + headerText.replace(/"/g, '""') + '"');
                }
                csv.push(headers.join(','));

                tableRows.forEach(row => {
                    if (row.style.display !== 'none' && row.cells.length > 1) {
                        let rowData = [];
                        for (let i = 0; i < row.cells.length - 1; i++) {
                            let cellText = row.cells[i].innerText.trim();
                            rowData.push('"' + cellText.replace(/"/g, '""') + '"');
                        }
                        csv.push(rowData.join(','));
                    }
                });

                let csvContent = "data:text/csv;charset=utf-8," + "\uFEFF" + csv.join("\n");
                let encodedUri = encodeURI(csvContent);
                let link = document.createElement("a");
                let todayFormatted = new Date().toISOString().slice(0,10);
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", "Recent_Collections_Filtered_" + todayFormatted + ".csv");
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        });
    </script>
</body>
</html>