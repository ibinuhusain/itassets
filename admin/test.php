<?php
ini_set('display_errors', 0); 
error_reporting(E_ALL);

require_once '../includes/auth.php';
requireAdminOrReport(); 

$pdo = getConnection();
$today = date('Y-m-d');
$current_user_name = $_SESSION['name'] ?? 'Operations Manager'; 

$message = '';
$error = '';

// =========================================================================
// 1. HANDLE FORM SUBMISSIONS (CREATE AGENT & ASSIGNMENT)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_agent'])) {
        $username = trim($_POST['username']);
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $password = trim($_POST['password']);
        
        if (!empty($username) && !empty($name) && !empty($password)) {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, name, phone, role) VALUES (?, ?, ?, ?, 'agent')");
                $stmt->execute([$username, $hashed_password, $name, $phone]);
                $message = 'Agent profile created successfully!';
            } catch (PDOException $e) {
                $error = 'Error creating agent. Username might already exist.';
            }
        } else {
            $error = 'Please fill all required agent fields.';
        }
    } 
    elseif (isset($_POST['create_assignment'])) {
        $agent_id = $_POST['agent_id'];
        $store_id = $_POST['store_id']; // This receives the final chosen store
        $date = $_POST['assignment_date'];
        
        if (!empty($agent_id) && !empty($store_id) && !empty($date)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO daily_assignments (agent_id, store_id, date_assigned, status) VALUES (?, ?, ?, 'pending')");
                $stmt->execute([$agent_id, $store_id, $date]);
                $message = 'Store assignment dispatched successfully!';
            } catch (PDOException $e) {
                $error = 'Error dispatching assignment. It may already exist.';
            }
        } else {
            $error = 'Please select an Agent, Store, and Date.';
        }
    }
}

// =========================================================================
// 2. ULTRA-FAST DATA FETCHING (METRICS & CHARTS & DROPDOWNS)
// =========================================================================
$stats = [];
try {
    // Top-Level Master Metrics
    $stmt = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'agent') as total_agents,
        (SELECT COUNT(*) FROM stores) as total_stores,
        (SELECT COUNT(*) FROM daily_assignments) as total_assignments,
        (SELECT SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) FROM daily_assignments) as completed_assignments,
        (SELECT COUNT(DISTINCT agent_id) FROM daily_assignments WHERE DATE(date_assigned) = '$today') as agents_assigned,
        (SELECT COUNT(*) FROM daily_assignments WHERE DATE(date_assigned) = '$today') as today_assignments,
        (SELECT SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) FROM daily_assignments WHERE DATE(date_assigned) = '$today') as today_completed,
        (SELECT SUM(cash_amount) FROM shop_visits WHERE DATE(visit_date) = '$today') as total_collected,
        (SELECT COUNT(DISTINCT s.mall) FROM daily_assignments da JOIN stores s ON da.store_id = s.id WHERE DATE(da.date_assigned) = '$today') as total_malls,
        (SELECT COUNT(DISTINCT CASE WHEN da.status = 'completed' THEN s.mall END) FROM daily_assignments da JOIN stores s ON da.store_id = s.id WHERE DATE(da.date_assigned) = '$today') as completed_malls
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Safety checks for nulls
    foreach ($stats as $key => $val) { $stats[$key] = $val ?: 0; }
    $stats['pending_assignments'] = $stats['total_assignments'] - $stats['completed_assignments'];
    $stats['completion_rate_today'] = $stats['today_assignments'] > 0 ? round(($stats['today_completed'] / $stats['today_assignments']) * 100, 1) : 0;

    // CHART DATA: Top Agents & Regions
    $top_agents = $pdo->query("SELECT u.name, COUNT(da.id) as completed_count FROM daily_assignments da JOIN users u ON da.agent_id = u.id WHERE da.status = 'completed' GROUP BY u.id ORDER BY completed_count DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $top_regions = $pdo->query("SELECT r.name, COUNT(da.id) as assign_count FROM daily_assignments da JOIN stores s ON da.store_id = s.id JOIN regions r ON s.region_id = r.id GROUP BY r.id ORDER BY assign_count DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    // FETCH FORMS DATA: Filters and Dropdowns
    $active_agents = $pdo->query("SELECT id, name FROM users WHERE role = 'agent' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $regions = $pdo->query("SELECT id, name FROM regions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $brands = $pdo->query("SELECT DISTINCT brand FROM stores WHERE brand IS NOT NULL AND brand != '' ORDER BY brand ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // FETCH ALL STORES FOR INSTANT JS FILTERING
    $all_stores = $pdo->query("SELECT id, name, mall, region_id, brand FROM stores ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // RECENT ASSIGNMENTS TABLE DATA
    $recent_assignments = $pdo->query("SELECT da.id, da.date_assigned, u.name as agent, s.name as store, da.status FROM daily_assignments da JOIN users u ON da.agent_id = u.id JOIN stores s ON da.store_id = s.id ORDER BY da.id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("<div style='padding:20px; background:#ffdddd; color:#aa0000;'>Database Error: " . $e->getMessage() . "</div>");
}

extract($stats);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>God Mode - Operations Command</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200');

        :root {
            --primary-bg: #09090e;
            --card-bg: rgba(23, 25, 49, 0.6);
            --card-border: rgba(255, 255, 255, 0.08);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.6);
            --accent-indigo: #6366f1;
            --accent-emerald: #10b981;
            --accent-blue: #3b82f6;
            --accent-amber: #f59e0b;
            --danger: #ef4444;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--primary-bg); background-image: radial-gradient(circle at 50% 0%, #1a1a3a 0%, #09090e 50%); background-attachment: fixed; color: var(--text-primary); line-height: 1.6; min-height: 100vh; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

        /* Navbar */
        .top-navbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background: rgba(9, 9, 14, 0.8); backdrop-filter: blur(20px); border-bottom: 1px solid var(--card-border); position: sticky; top: 0; z-index: 100; }
        .nav-brand { display: flex; align-items: center; gap: 15px; }
        .nav-brand img { width: 40px; }
        .nav-title { font-size: 22px; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; color: #fff;}
        .logout-btn { display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: rgba(239, 68, 68, 0.1); color: var(--danger); border-radius: 8px; text-decoration: none; font-weight: 600; border: 1px solid rgba(239, 68, 68, 0.2); transition: var(--transition);}
        .logout-btn:hover { background: var(--danger); color: white; box-shadow: 0 0 15px rgba(239, 68, 68, 0.4); }

        .dashboard-container { max-width: 1600px; margin: 0 auto; padding: 30px 40px; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 25px; font-weight: 500; }
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid var(--accent-emerald); color: var(--accent-emerald); }
        .alert-danger { background: rgba(239, 68, 68, 0.2); border: 1px solid var(--danger); color: #fca5a5; }

        .section-title { font-size: 22px; font-weight: 800; margin: 40px 0 20px; color: #fff; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 10px; }
        .section-title::before { content: ''; display: block; width: 6px; height: 22px; background: var(--accent-indigo); border-radius: 4px; }

        /* Grids */
        .grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        
        @media (max-width: 1200px) { .grid-2 { grid-template-columns: 1fr; } }

        /* Cards */
        .card { background: var(--card-bg); backdrop-filter: blur(12px); border: 1px solid var(--card-border); border-radius: 16px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); position: relative; overflow: hidden; transition: transform 0.3s; }
        .card:hover { transform: translateY(-3px); border-color: rgba(255,255,255,0.15); }
        .card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 3px; background: rgba(255,255,255,0.1); }
        
        .card.accent-emerald::before { background: var(--accent-emerald); }
        .card.accent-indigo::before { background: var(--accent-indigo); }
        .card.accent-amber::before { background: var(--accent-amber); }
        .card.accent-blue::before { background: var(--accent-blue); }

        /* KPI Card Styles */
        .kpi-value { font-size: 38px; font-weight: 800; margin: 10px 0 5px; line-height: 1; }
        .kpi-label { font-size: 13px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .icon-wrapper { position: absolute; top: 20px; right: 20px; opacity: 0.8; }
        .progress-container { width: 100%; background: rgba(0,0,0,0.3); height: 6px; border-radius: 10px; margin-top: 15px; overflow: hidden; }
        .progress-bar { height: 100%; border-radius: 10px; transition: width 1.5s ease-out; }

        /* Forms */
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 11px; color: rgba(255,255,255,0.7); margin-bottom: 6px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;}
        .form-control { width: 100%; padding: 12px 15px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: white; font-family: inherit; font-size: 14px; transition: var(--transition);}
        .form-control:focus { outline: none; border-color: var(--accent-indigo); background: rgba(0,0,0,0.5); }
        .form-control option { background: #171931; color: white; }
        
        .btn { width: 100%; padding: 14px; border-radius: 8px; font-weight: 700; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; border: none; transition: var(--transition); text-transform: uppercase; letter-spacing: 1px;}
        .btn-indigo { background: var(--accent-indigo); color: #fff; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2); }
        .btn-indigo:hover { background: #4f46e5; transform: translateY(-2px); }
        .btn-emerald { background: var(--accent-emerald); color: #fff; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); }
        .btn-emerald:hover { background: #0ea5e9; transform: translateY(-2px); }
        .btn-white { background: #fff; color: #000; }
        .btn-white:hover { background: #e2e8f0; transform: translateY(-2px); }

        /* Table */
        .table-responsive { overflow-x: auto; background: rgba(0,0,0,0.2); border-radius: 12px; padding: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 14px 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
        th { color: var(--text-secondary); font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; }
        .badge { padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;}
        .badge-completed { background: rgba(16, 185, 129, 0.2); color: var(--accent-emerald); border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-pending { background: rgba(245, 158, 11, 0.2); color: var(--accent-amber); border: 1px solid rgba(245, 158, 11, 0.3); }
        
        .chart-container { position: relative; height: 250px; width: 100%; margin-top: 20px;}
    </style>
</head>
<body>

    <nav class="top-navbar">
        <div class="nav-brand">
            <img src="../images/logo.png" alt="Logo">
            <div class="nav-title">Apparel Ops Command</div>
        </div>
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="text-align: right;">
                <div style="font-size: 12px; color: var(--accent-emerald); font-weight: 700; letter-spacing: 1px;"><span style="display: inline-block; width: 6px; height: 6px; background: var(--accent-emerald); border-radius: 50%; margin-right: 5px; animation: pulse 2s infinite;"></span> LIVE LINK</div>
                <div style="font-size: 14px; font-weight: 600;"><?php echo htmlspecialchars($current_user_name); ?></div>
            </div>
            <a href="../logout.php" class="logout-btn"><span class="material-symbols-outlined">logout</span></a>
        </div>
    </nav>
    
    <div class="dashboard-container">
        
        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="section-title">Today's Live Pulse</div>
        <div class="grid-4">
            <div class="card accent-emerald">
                <div class="icon-wrapper" style="color: var(--accent-emerald);"><span class="material-symbols-outlined" style="font-size: 32px;">payments</span></div>
                <div class="kpi-label">Physical Cash</div>
                <div class="kpi-value" style="color: var(--accent-emerald);">SAR <?php echo number_format($total_collected, 2); ?></div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">Collected Today</div>
            </div>

            <div class="card accent-indigo">
                <div class="icon-wrapper" style="color: var(--accent-indigo);"><span class="material-symbols-outlined" style="font-size: 32px;">storefront</span></div>
                <div class="kpi-label">Store Coverage</div>
                <div class="kpi-value"><?php echo $today_completed; ?> <span style="font-size: 18px; color: var(--text-secondary);">/ <?php echo $today_assignments; ?></span></div>
                <div class="progress-container"><div class="progress-bar" style="width: <?php echo $completion_rate_today; ?>%; background: var(--accent-indigo);"></div></div>
            </div>

            <div class="card accent-amber">
                <div class="icon-wrapper" style="color: var(--accent-amber);"><span class="material-symbols-outlined" style="font-size: 32px;">groups</span></div>
                <div class="kpi-label">Active Agents</div>
                <div class="kpi-value"><?php echo $agents_assigned; ?> <span style="font-size: 18px; color: var(--text-secondary);">/ <?php echo $total_agents; ?></span></div>
                <div class="progress-container"><div class="progress-bar" style="width: <?php echo $total_agents > 0 ? ($agents_assigned/$total_agents)*100 : 0; ?>%; background: var(--accent-amber);"></div></div>
            </div>
            
            <div class="card accent-blue">
                <div class="icon-wrapper" style="color: var(--accent-blue);"><span class="material-symbols-outlined" style="font-size: 32px;">domain</span></div>
                <div class="kpi-label">Malls Covered</div>
                <div class="kpi-value"><?php echo $completed_malls; ?> <span style="font-size: 18px; color: var(--text-secondary);">/ <?php echo $total_malls; ?></span></div>
                <div class="progress-container"><div class="progress-bar" style="width: <?php echo $total_malls > 0 ? ($completed_malls/$total_malls)*100 : 0; ?>%; background: var(--accent-blue);"></div></div>
            </div>
        </div>

        <div class="section-title">Performance Graphics</div>
        <div class="grid-3">
            <div class="card">
                <h3 style="font-size: 14px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary);">Top Agents</h3>
                <div class="chart-container"><canvas id="agentChart"></canvas></div>
            </div>
            <div class="card">
                <h3 style="font-size: 14px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary);">Top Regions</h3>
                <div class="chart-container"><canvas id="regionChart"></canvas></div>
            </div>
            <div class="card">
                <h3 style="font-size: 14px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary);">Assignment Status</h3>
                <div class="chart-container"><canvas id="statusChart"></canvas></div>
            </div>
        </div>

        <div class="section-title">Central Operations Hub</div>
        <div class="grid-2">
            
            <div style="display: flex; flex-direction: column; gap: 25px;">
                
                <div class="card" style="border-top: 4px solid var(--accent-indigo);">
                    <h3 style="font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-outlined" style="color: var(--accent-indigo);">rocket_launch</span> Dispatch Assignment
                    </h3>
                    <form method="POST">
                        <input type="hidden" name="create_assignment" value="1">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Assign To Agent</label>
                                <select name="agent_id" class="form-control" required>
                                    <option value="">-- Choose Agent --</option>
                                    <?php foreach($active_agents as $agent): ?>
                                        <option value="<?php echo $agent['id']; ?>"><?php echo htmlspecialchars($agent['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Target Date</label>
                                <input type="date" name="assignment_date" class="form-control" value="<?php echo $today; ?>" required>
                            </div>
                        </div>

                        <div class="form-row" style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="color: var(--accent-amber);">Filter by Region</label>
                                <select id="filter_region" class="form-control" onchange="filterStores()">
                                    <option value="">-- All Regions --</option>
                                    <?php foreach($regions as $r): ?>
                                        <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="color: var(--accent-amber);">Filter by Brand</label>
                                <select id="filter_brand" class="form-control" onchange="filterStores()">
                                    <option value="">-- All Brands --</option>
                                    <?php foreach($brands as $b): ?>
                                        <option value="<?php echo htmlspecialchars($b['brand']); ?>"><?php echo htmlspecialchars($b['brand']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Target Store</label>
                            <select name="store_id" id="target_store" class="form-control" required style="border: 1px solid var(--accent-indigo); background: rgba(99, 102, 241, 0.1);">
                                <option value="">-- Choose Store --</option>
                                </select>
                        </div>
                        
                        <button type="submit" class="btn btn-indigo"><span class="material-symbols-outlined">send</span> Dispatch To Field</button>
                    </form>
                </div>

                <div class="card" style="border-top: 4px solid var(--accent-emerald);">
                    <h3 style="font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-outlined" style="color: var(--accent-emerald);">person_add</span> Enlist New Agent
                    </h3>
                    <form method="POST">
                        <input type="hidden" name="create_agent" value="1">
                        <div class="form-row">
                            <div class="form-group"><label>Full Name</label><input type="text" name="name" class="form-control" required></div>
                            <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" required></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
                            <div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" required></div>
                        </div>
                        <button type="submit" class="btn btn-emerald"><span class="material-symbols-outlined">how_to_reg</span> Deploy Agent</button>
                    </form>
                </div>

            </div>

            <div class="card" style="display: flex; flex-direction: column;">
                <h3 style="font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined" style="color: #fff;">history</span> Live Dispatch Feed
                </h3>
                <div class="table-responsive" style="flex: 1;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Agent</th>
                                <th>Store Location</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($recent_assignments)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 40px 0;">No recent dispatches found.</td></tr>
                            <?php else: ?>
                                <?php foreach($recent_assignments as $ra): ?>
                                <tr>
                                    <td><?php echo date('M j', strtotime($ra['date_assigned'])); ?></td>
                                    <td style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($ra['agent']); ?></td>
                                    <td><?php echo htmlspecialchars(strlen($ra['store']) > 25 ? substr($ra['store'],0,25).'...' : $ra['store']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $ra['status']; ?>">
                                            <?php echo ucfirst($ra['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="section-title">Report Generators</div>
        <div class="grid-3">
            
            <div class="card" style="border-top: 4px solid var(--accent-emerald);">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                    <span class="material-symbols-outlined" style="font-size: 32px; color: var(--accent-emerald);">account_balance_wallet</span>
                    <h3 style="font-size: 16px;">Cash Ledger</h3>
                </div>
                <form action="api/export_daily.php" method="GET" target="_blank">
                    <div class="form-row">
                        <div class="form-group"><label>Start</label><input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-01'); ?>" required></div>
                        <div class="form-group"><label>End</label><input type="date" name="end_date" class="form-control" value="<?php echo $today; ?>" required></div>
                    </div>
                    <button type="submit" class="btn btn-emerald">Export CSV</button>
                </form>
            </div>

            <div class="card" style="border-top: 4px solid var(--accent-indigo);">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                    <span class="material-symbols-outlined" style="font-size: 32px; color: var(--accent-indigo);">query_stats</span>
                    <h3 style="font-size: 16px;">Agent Analytics</h3>
                </div>
                <form action="api/export_weekly.php" method="GET" target="_blank">
                    <div class="form-group" style="margin-bottom: 5px;">
                        <select name="agent_id" class="form-control" required>
                            <option value="all">-- Entire Team --</option>
                            <?php foreach($active_agents as $agent): ?>
                                <option value="<?php echo $agent['id']; ?>"><?php echo htmlspecialchars($agent['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Start</label><input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>" required></div>
                        <div class="form-group"><label>End</label><input type="date" name="end_date" class="form-control" value="<?php echo $today; ?>" required></div>
                    </div>
                    <button type="submit" class="btn btn-indigo">Run Analytics</button>
                </form>
            </div>

            <div class="card" style="border-top: 4px solid #fff; display: flex; flex-direction: column; justify-content: center;">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                    <span class="material-symbols-outlined" style="font-size: 32px; color: #fff;">database</span>
                    <h3 style="font-size: 16px;">Master Store File</h3>
                </div>
                <form action="api/export_collections.php" method="GET" target="_blank">
                    <div class="form-group">
                        <label>Filter By Status</label>
                        <select name="focus" class="form-control" required>
                            <option value="all">Entire Database</option>
                            <option value="completed">Completed Only</option>
                            <option value="pending">Pending Only</option>
                        </select>
                    </div>
                    <input type="hidden" name="format" value="csv">
                    <button type="submit" class="btn btn-white"><span class="material-symbols-outlined">table_view</span> Extract Master</button>
                </form>
            </div>

        </div>
    </div>

    <script>
        // Store Directory JSON from PHP
        const storeDirectory = <?php echo json_encode($all_stores); ?>;
        
        // Fast JS Filter Logic
        function filterStores() {
            const regionId = document.getElementById('filter_region').value;
            const brandName = document.getElementById('filter_brand').value;
            const storeSelect = document.getElementById('target_store');
            
            // Clear current options
            storeSelect.innerHTML = '<option value="">-- Choose Store --</option>';
            
            // Filter and populate instantly
            let count = 0;
            storeDirectory.forEach(store => {
                const matchRegion = regionId === "" || store.region_id == regionId;
                const matchBrand = brandName === "" || store.brand === brandName;
                
                if(matchRegion && matchBrand) {
                    const opt = document.createElement('option');
                    opt.value = store.id;
                    opt.textContent = store.name + (store.mall ? ' (' + store.mall + ')' : '');
                    storeSelect.appendChild(opt);
                    count++;
                }
            });
            
            // Add a helpful note if no stores match
            if(count === 0) {
                storeSelect.innerHTML = '<option value="">-- No stores found for these filters --</option>';
            }
        }
        
        // Initialize dropdown on page load
        document.addEventListener('DOMContentLoaded', filterStores);

        // Chart Configs
        Chart.defaults.color = 'rgba(255, 255, 255, 0.6)';
        Chart.defaults.font.family = 'Inter';
        
        const commonOptions = {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, beginAtZero: true, border: {display: false} },
                x: { grid: { display: false }, border: {display: false} }
            }
        };

        // 1. Top Agents Chart
        const agentData = <?php echo json_encode($top_agents); ?>;
        new Chart(document.getElementById('agentChart'), {
            type: 'bar',
            data: {
                labels: agentData.map(d => d.name.split(' ')[0]), 
                datasets: [{
                    data: agentData.map(d => d.completed_count),
                    backgroundColor: 'rgba(16, 185, 129, 0.9)',
                    borderRadius: 4
                }]
            },
            options: commonOptions
        });

        // 2. Top Regions Doughnut Chart
        const regionData = <?php echo json_encode($top_regions); ?>;
        new Chart(document.getElementById('regionChart'), {
            type: 'doughnut',
            data: {
                labels: regionData.map(d => d.name),
                datasets: [{
                    data: regionData.map(d => d.assign_count),
                    backgroundColor: ['#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#f59e0b'],
                    borderWidth: 2,
                    borderColor: '#171931'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } } },
                cutout: '75%'
            }
        });

        // 3. Status Pie Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Pending'],
                datasets: [{
                    data: [<?php echo $completed_assignments; ?>, <?php echo $pending_assignments; ?>],
                    backgroundColor: ['rgba(16, 185, 129, 0.9)', 'rgba(245, 158, 11, 0.9)'],
                    borderWidth: 2,
                    borderColor: '#171931'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } } },
                cutout: '75%'
            }
        });
    </script>
</body>
</html>