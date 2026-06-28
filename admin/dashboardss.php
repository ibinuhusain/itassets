<?php
require_once '../includes/auth.php';
isAdminOrHigher();

$pdo = getConnection();
$today = date('Y-m-d'); // Define today's date

// Get total agents
$stmt = $pdo->prepare("SELECT COUNT(*) as total_agents FROM users WHERE role = 'agent'");
$stmt->execute();
$total_agents = $stmt->fetchColumn() ?: 0;

// Get total stores
$stmt = $pdo->prepare("SELECT COUNT(*) as total_stores FROM stores");
$stmt->execute();
$total_stores = $stmt->fetchColumn() ?: 0;

// Get total regions
$stmt = $pdo->prepare("SELECT COUNT(*) as total_regions FROM regions");
$stmt->execute();
$total_regions = $stmt->fetchColumn() ?: 0;

// Get completed assignments
$stmt = $pdo->prepare("SELECT COUNT(*) as completed_assignments FROM daily_assignments WHERE status = 'completed'");
$stmt->execute();
$completed_assignments = $stmt->fetchColumn() ?: 0;

// Get total assignments
$stmt = $pdo->prepare("SELECT COUNT(*) as total_assignments FROM daily_assignments");
$stmt->execute();
$total_assignments = $stmt->fetchColumn() ?: 0;

// Get total bank submissions
$stmt = $pdo->prepare("SELECT COUNT(*) as total_submissions FROM bank_submissions");
$stmt->execute();
$total_submissions = $stmt->fetchColumn() ?: 0;

// Calculate completion percentage
$completion_percentage = $total_assignments > 0 ? round(($completed_assignments / $total_assignments) * 100, 2) : 0;

// Agents assigned today
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT da.agent_id) as agents_assigned 
    FROM daily_assignments da 
    WHERE DATE(da.date_assigned) = ?
");
$stmt->execute([$today]);
$agents_assigned = $stmt->fetchColumn() ?: 0;

// Total stores/assigned entities
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_stores_today 
    FROM daily_assignments da 
    WHERE DATE(da.date_assigned) = ?
");
$stmt->execute([$today]);
$total_stores_today = $stmt->fetchColumn() ?: 0;

// Completed stores/entities
$stmt = $pdo->prepare("
    SELECT COUNT(*) as completed_stores 
    FROM daily_assignments da 
    WHERE DATE(da.date_assigned) = ? AND da.status = 'completed'
");
$stmt->execute([$today]);
$completed_stores = $stmt->fetchColumn() ?: 0;

// Total malls (from assigned stores)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT s.mall) as total_malls 
    FROM daily_assignments da 
    JOIN stores s ON da.store_id = s.id
    WHERE DATE(da.date_assigned) = ?
");
$stmt->execute([$today]);
$total_malls = $stmt->fetchColumn() ?: 0;

// Completed malls (malls with all stores completed)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT s.mall) as completed_malls 
    FROM daily_assignments da 
    JOIN stores s ON da.store_id = s.id
    WHERE DATE(da.date_assigned) = ? 
    AND da.status = 'completed'
");
$stmt->execute([$today]);
$completed_malls = $stmt->fetchColumn() ?: 0;

// Total bank submissions today
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_submissions_today 
    FROM bank_submissions bs 
    WHERE DATE(bs.created_at) = ?
");
$stmt->execute([$today]);
$total_submissions_today = $stmt->fetchColumn() ?: 0;

// Total collected amount today (Using new physical_cash column)
$stmt = $pdo->prepare("
    SELECT SUM(physical_cash) as total_collected 
    FROM shop_visits 
    WHERE DATE(visit_date) = ?
");
$stmt->execute([$today]);
$total_collected = $stmt->fetchColumn() ?: 0;

// Completed orders today
$stmt = $pdo->prepare("
    SELECT COUNT(*) as completed_orders 
    FROM daily_assignments da 
    WHERE DATE(da.date_assigned) = ? AND da.status = 'completed'
");
$stmt->execute([$today]);
$completed_orders = $stmt->fetchColumn() ?: 0;

// Calculate today's completion rate
$completion_rate_today = $total_stores_today > 0 ? round(($completed_stores / $total_stores_today) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Apparels Collection</title>
    <style>
        /* modern-dashboard.css - Base styles for all pages */
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
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--primary-bg);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ===== CONTAINER & LAYOUT ===== */
        .container {
            display: flex;
            min-height: 100vh;
            position: relative;
            width: 100%;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: var(--sidebar-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            z-index: 100;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        /* Logo Container */
        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
        }

        /* Force the image size */
        .sidebar-logo > img {
            /*width: 40px !important;*/
            height: 40px !important;
            min-width: 100% !important;
            object-fit: contain !important;
            border-radius: 8px !important;
            display: block !important;
            flex-shrink: 0 !important;
        }

        .sidebar-logo-text {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(90deg, #ffffff, #d1d5db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex-shrink: 1;
        }

        /* Navigation */
        .sidebar-nav {
            padding: 15px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 5px;
            border-radius: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition);
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
        }

        .nav-item.active {
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent-color);
        }

        .nav-item .material-symbols-outlined {
            margin-right: 12px;
            font-size: 24px;
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-left: 280px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
            width: calc(100% - 280px);
        }

        /* Top Header */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 24px;
            height: 70px;
            background: var(--card-bg);
            box-shadow: var(--elevation-1);
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Hamburger Menu */
        .hamburger {
            display: none;
            flex-direction: column;
            justify-content: space-between;
            width: 30px;
            height: 21px;
            cursor: pointer;
            z-index: 101;
        }

        .hamburger span {
            display: block;
            height: 3px;
            width: 100%;
            background: var(--text-primary);
            border-radius: 3px;
            transition: var(--transition);
        }

        /* Logout Button */
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-radius: 8px;
            text-decoration: none;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        /* Content Area */
        .content {
            flex: 1;
            padding: 24px;
            background: var(--background-color);
            overflow-y: auto;
            min-height: calc(100vh - 70px);
        }

        /* Overlay */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        /* ===== DASHBOARD STYLES ===== */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: var(--transition);
            box-shadow: var(--elevation-1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--elevation-2);
        }

        .stat-card h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .stat-card p {
            color: var(--text-secondary);
            font-size: 14px;
            margin: 0;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 24px;
            color: var(--text-primary);
            position: relative;
            padding-bottom: 12px;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--accent-color);
            border-radius: 2px;
        }

        .content h3 {
            font-size: 20px;
            margin: 30px 0 20px;
            color: var(--text-primary);
            padding-bottom: 10px;
            border-bottom: 1px solid var(--card-border);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            outline: none;
            text-decoration: none;
            margin-right: 10px;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #0da271;
            transform: translateY(-2px);
        }

        /* PWA Install Button */
        #installPWA {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 24px;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.3);
            z-index: 1000;
            display: none;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: var(--transition);
        }

        #installPWA.show {
            display: flex;
            animation: slideUp 0.3s ease;
        }

        #installPWA:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
        }

        @keyframes slideUp {
            from {
                transform: translateY(100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Scrollbar Styles */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .hamburger {
                display: flex;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .overlay.active {
                display: block;
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .dashboard-stats {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 15px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card h3 {
                font-size: 24px;
            }

            .content h3 {
                font-size: 18px;
            }

            .top-header {
                padding: 0 15px;
            }

            .content {
                padding: 15px;
            }
        }

        @media (max-width: 480px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
                margin-bottom: 10px;
                margin-right: 0;
            }

            .sidebar {
                width: 100%;
                max-width: 300px;
            }
        }

        /* For desktop view */
        @media (min-width: 993px) {
            .sidebar {
                transform: translateX(0) !important;
            }

            .overlay {
                display: none !important;
            }
        }

        /* Today's Stats Highlight */
        .today-highlight {
            background: rgba(99, 102, 241, 0.15) !important;
            border-color: var(--accent-color) !important;
        }

        .today-highlight h3 {
            color: var(--accent-color) !important;
        }

        /* Amount Collected */
        .amount-card {
            background: rgba(16, 185, 129, 0.15) !important;
            border-color: var(--success) !important;
        }

        .amount-card h3 {
            color: var(--success) !important;
        }
    </style>
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" type="image/png" href="/images/icon-192x192.png">
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
           <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="../images/logo.png" alt="Apparel Collection Logo" width="80px" height="80px" loading="lazy">
                </div>
            </div>
            
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <span class="material-symbols-outlined">home</span>
                    <span>Dashboard</span>
                </a>
                <a href="assignments.php" class="nav-item">
                    <span class="material-symbols-outlined">assignment</span>
                    <span>Assignments</span>
                </a>
                <a href="agents.php" class="nav-item">
                    <span class="material-symbols-outlined">people</span>
                    <span>Agents</span>
                </a>
                <a href="management.php" class="nav-item">
                    <span class="material-symbols-outlined">settings</span>
                    <span>Management</span>
                </a>
                <a href="store_data.php" class="nav-item">
                    <span class="material-symbols-outlined">storefront</span>
                    <span>Store Data</span>
                </a>
                <a href="bank_approvals.php" class="nav-item">
                    <span class="material-symbols-outlined">receipt_long</span>
                    <span>Bank Approvals</span>
                </a>
            </div>
        </div>
        
        <!-- Overlay for mobile sidebar -->
        <div class="overlay"></div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Top Header -->
            <div class="top-header">
                <div class="header-left">
                    <div class="hamburger">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <div class="header-title">Dashboard</div>
                </div>
                <div class="header-right">
                    <a href="../logout.php" class="logout-btn">
                        <span class="material-symbols-outlined">logout</span>
                        Logout
                    </a>
                </div>
            </div>
            
            <!-- Page Content -->
            <div class="content">
                <h2 class="page-title">System Overview</h2>
                
                <!-- Today's Stats -->
                <div class="dashboard-stats">
                    <div class="stat-card today-highlight">
                        <h3><?php echo $agents_assigned; ?></h3>
                        <p>Agents Working Today</p>
                    </div>
                    
                    <div class="stat-card today-highlight">
                        <h3><?php echo $total_stores_today; ?></h3>
                        <p>Shops Assigned Today</p>
                    </div>
                    
                    <div class="stat-card today-highlight">
                        <h3><?php echo $completion_rate_today; ?>%</h3>
                        <p>Today's Completion</p>
                    </div>
                    
                    <div class="stat-card amount-card">
                        <h3>SAR <?php echo number_format($total_collected, 2); ?></h3>
                        <p>Amount Collected Today</p>
                    </div>
                </div>

                <!-- Overall Statistics -->
                <h3>Overall Statistics</h3>
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3><?php echo $total_agents; ?></h3>
                        <p>Total Agents</p>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php echo $total_stores; ?></h3>
                        <p>Total Stores</p>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php echo $total_regions; ?></h3>
                        <p>Total Regions</p>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php echo $completion_percentage; ?>%</h3>
                        <p>Overall Completion</p>
                    </div>
                </div>

                <!-- Today's Detailed Stats -->
                <h3>Today's Detailed Statistics</h3>
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3><?php echo $completed_stores; ?>/<?php echo $total_stores_today; ?></h3>
                        <p>Shops Completed</p>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php echo $completed_malls; ?>/<?php echo $total_malls; ?></h3>
                        <p>Malls Completed</p>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php echo $total_submissions_today; ?></h3>
                        <p>Bank Submissions Today</p>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php echo $completed_orders; ?></h3>
                        <p>Completed Orders</p>
                    </div>
                </div>

                <!-- Historical Data -->
                <h3>Historical Data</h3>
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3><?php echo $total_assignments; ?></h3>
                        <p>Total Assignments</p>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php echo $completed_assignments; ?></h3>
                        <p>Completed Assignments</p>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php echo $total_submissions; ?></h3>
                        <p>Total Bank Submissions</p>
                    </div>
                </div>

                <!-- Data Export Section -->
                <div style="margin-top: 40px; padding: 20px; background: var(--card-bg); border-radius: 12px; border: 1px solid var(--card-border);">
                    <h3 style="margin-top: 0; border: none; padding-bottom: 0;">Data Export</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 15px;">Export your data for reporting and analysis</p>
                    <div>
                        <a href="export_daily.php" class="btn btn-success">
                            <span class="material-symbols-outlined" style="margin-right: 8px;">download</span>
                            Export Daily Data
                        </a>
                        <a href="export_weekly.php" class="btn btn-success">
                            <span class="material-symbols-outlined" style="margin-right: 8px;">download</span>
                            Export Weekly Data
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PWA Install Button -->
    <button id="installPWA">
        <span class="material-symbols-outlined">download</span>
        Install App
    </button>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile Menu Functionality
            const hamburger = document.querySelector('.hamburger');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.overlay');
            
            if (hamburger && sidebar && overlay) {
                function toggleMenu() {
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('active');
                    document.body.classList.toggle('menu-open');
                }
                
                function closeMenu() {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.classList.remove('menu-open');
                }
                
                hamburger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleMenu();
                });
                
                overlay.addEventListener('click', closeMenu);
                
                document.addEventListener('click', function(e) {
                    if (window.innerWidth <= 992 && 
                        !sidebar.contains(e.target) && 
                        e.target !== hamburger) {
                        closeMenu();
                    }
                });
                
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 992) {
                        closeMenu();
                    }
                });
            }

            // PWA Installation Handler
            let deferredPrompt;
            let installButton = document.getElementById('installPWA');
            
            if (installButton) {
                window.addEventListener('beforeinstallprompt', (e) => {
                    e.preventDefault();
                    deferredPrompt = e;
                    
                    setTimeout(() => {
                        if (!localStorage.getItem('pwaInstallDismissed') && !localStorage.getItem('pwaInstalled')) {
                            installButton.classList.add('show');
                        }
                    }, 2000);
                });
                
                installButton.addEventListener('click', () => {
                    if (deferredPrompt) {
                        installButton.style.display = 'none';
                        deferredPrompt.prompt();
                        
                        deferredPrompt.userChoice.then((choiceResult) => {
                            if (choiceResult.outcome === 'accepted') {
                                console.log('User accepted PWA install');
                                localStorage.setItem('pwaInstalled', 'true');
                            } else {
                                console.log('User dismissed PWA install');
                                localStorage.setItem('pwaInstallDismissed', 'true');
                            }
                            deferredPrompt = null;
                        });
                    }
                });
                
                window.addEventListener('appinstalled', () => {
                    console.log('PWA was installed');
                    localStorage.setItem('pwaInstalled', 'true');
                    if (installButton) {
                        installButton.classList.remove('show');
                    }
                });
            }

            // Service Worker Registration
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function() {
                    navigator.serviceWorker.register('../sw.js')
                        .then(function(registration) {
                            console.log('ServiceWorker registration successful');
                        })
                        .catch(function(err) {
                            console.log('ServiceWorker registration failed: ', err);
                        });
                });
            }
        });
    </script>
</body>
</html>