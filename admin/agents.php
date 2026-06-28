<?php
require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();

// Initialize variables
$agents = [];
$error_message = '';

// Fetch all agents from master_users and filter by JSON permissions
try {
    $stmt = $pdo->prepare("SELECT id, username, full_name as name, phone, created_at, app_permissions FROM master_users ORDER BY id ASC");
    $stmt->execute();
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Safely parse the JSON to ensure we only get cash_collection agents
    foreach ($all_users as $user) {
        if (!empty($user['app_permissions'])) {
            $perms = json_decode($user['app_permissions'], true);
            if (isset($perms['cash_collection']) && is_array($perms['cash_collection']) && in_array('agent', $perms['cash_collection'])) {
                $agents[] = $user;
            }
        }
    }
} catch (PDOException $e) {
    $error_message = "Error fetching agents: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agents Directory - Apparels Collection</title>
    <link rel="stylesheet" href="../css/modern-dashboard.css">
    <link rel="stylesheet" href="../css/agents.css">
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" type="image/png" href="/images/icon-192x192.png">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet" />
    <script src="../js/app.js" defer></script>
    
    <style>
        :root {
            --bg-card: #13151a;
            --border-color: rgba(255, 255, 255, 0.08);
            --text-muted: #8a8f98;
            --accent-blue: #3b82f6;
            --bg-input: #1c1f26;
            --success: #10b981;
            --warning-bg: rgba(59, 130, 246, 0.05);
            --warning-border: rgba(59, 130, 246, 0.2);
        }

        body {
            background-color: #0b0c10;
            color: #f3f4f6;
            margin: 0;
        }

        .content {
            padding: 24px;
            width: 100%;
            box-sizing: border-box;
        }

        /* Admin Notice Banner */
        .admin-notice {
            background: var(--warning-bg);
            border: 1px solid var(--warning-border);
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .admin-notice .icon {
            font-size: 32px;
            color: var(--accent-blue);
        }
        
        .admin-notice-content h3 {
            margin: 0 0 4px 0;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
        }
        
        .admin-notice-content p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.5;
        }

        /* Full Screen Single Grid Integration Layout */
        .agents-grid {
            display: block;
            width: 100%;
        }

        .panel-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        h2 {
            font-size: 18px;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 20px;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Search Header Controller Strip Layout */
        .table-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .table-header-bar h2 { margin: 0; }

        .search-wrapper {
            position: relative;
            width: 100%;
            max-width: 340px;
        }

        .search-wrapper .material-symbols-outlined {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 20px;
        }

        .search-input {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 10px 12px 10px 40px;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }

        /* High-Density View Bound Scrollable Datatable Wrap */
        .table-scroll-container {
            background: #16181f;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow-x: auto;
            max-height: calc(100vh - 290px);
            overflow-y: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #1c1f26;
            color: var(--text-muted);
            font-weight: 500;
            padding: 14px 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            white-space: nowrap;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255, 255, 255, 0.01); }

        .agent-badge {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
        }

        /* Webkit Engine Scrollbar Overrides */
        .table-scroll-container::-webkit-scrollbar { width: 6px; height: 6px; }
        .table-scroll-container::-webkit-scrollbar-track { background: transparent; }
        .table-scroll-container::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.12); border-radius: 4px; }
        .table-scroll-container::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.25); }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo" align="center">
                    <img src="../images/logo.png" alt="Apparel Collection Logo" width="auto" height="80px" style="object-fit: contain;" loading="lazy">
                </div>
            </div>
            
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <span class="material-symbols-outlined">home</span>
                    <span>Dashboard</span>
                </a>
                <a href="assignments.php" class="nav-item">
                    <span class="material-symbols-outlined">assignment</span>
                    <span>Assignments</span>
                </a>
                <a href="agents.php" class="nav-item active">
                    <span class="material-symbols-outlined">people</span>
                    <span>Agents Directory</span>
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
        
        <div class="overlay"></div>
        
        <div class="main-content">
            <div class="top-header">
                <div class="header-left">
                    <div class="hamburger">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <div class="header-title">Agents Directory</div>
                </div>
                <div class="header-right">
                    <a href="../api_logout.php" class="logout-btn" style="margin: 0;">
                        <span class="material-symbols-outlined">logout</span> Logout
                    </a>
                </div>
            </div>
            
            <div class="content">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" style="margin-bottom: 20px; padding: 12px; border-radius: 8px; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); font-size: 14px; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-outlined">error</span>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="admin-notice">
                    <span class="material-symbols-outlined icon">admin_panel_settings</span>
                    <div class="admin-notice-content">
                        <h3>User Management Restricted</h3>
                        <p>Please connect with the <strong>IT Admin</strong> for new user creation, role modifications, or profile management.</p>
                    </div>
                </div>
                
                <div class="agents-grid">
                    <div class="panel-card" style="overflow: hidden;">
                        <div class="table-header-bar">
                            <h2>
                                <span class="material-symbols-outlined" style="color: var(--success);">group</span>
                                Active Agents Fleet Structure (<?php echo count($agents); ?>)
                            </h2>
                            
                            <div class="search-wrapper">
                                <span class="material-symbols-outlined">search</span>
                                <input type="text" id="agentSearch" class="search-input" placeholder="Search by name, user ID, or phone...">
                            </div>
                        </div>
                        
                        <div class="table-scroll-container">
                            <?php if (empty($agents)): ?>
                                <div style="padding: 32px; text-align: center; color: var(--text-muted); font-size: 14px;">
                                    <span class="material-symbols-outlined" style="font-size: 36px; display: block; margin-bottom: 8px; color: var(--text-muted);">info</span>
                                    No agents discovered. Please contact the IT Admin to configure new agent profiles.
                                </div>
                            <?php else: ?>
                                <table id="agentTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 70px;">ID</th>
                                            <th>Agent Real Name</th>
                                            <th>System Username</th>
                                            <th>Mobile Context Link</th>
                                            <th>Profile Created Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($agents as $agent): ?>
                                            <tr class="agent-row">
                                                <td style="color: var(--text-muted); font-weight: 600;" class="search-id"><?php echo htmlspecialchars($agent['id']); ?></td>
                                                <td>
                                                    <div style="font-weight: 600; color: #fff;" class="search-name"><?php echo htmlspecialchars($agent['name'] ?: 'Unassigned'); ?></div>
                                                </td>
                                                <td>
                                                    <span class="agent-badge search-badge">@<?php echo htmlspecialchars($agent['username']); ?></span>
                                                </td>
                                                <td style="color: var(--accent-blue); font-weight: 500;" class="search-phone">
                                                    <?php echo htmlspecialchars($agent['phone'] ?: 'N/A'); ?>
                                                </td>
                                                <td style="color: var(--text-muted); font-size: 13px;">
                                                    <?php echo date('Y-m-d', strtotime($agent['created_at'])); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Nav Menu Mobile Animation Controllers Logic
        const hamburger = document.querySelector('.hamburger');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');
        
        if (hamburger) {
            hamburger.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.classList.toggle('menu-open');
            });
        }
        
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.classList.remove('menu-open');
            });
        }

        // Instant Dynamic Real-time Client Filtering Engine
        const searchInput = document.getElementById('agentSearch');
        const rows = document.querySelectorAll('.agent-row');

        if(searchInput) {
            searchInput.addEventListener('input', function(e) {
                const query = e.target.value.toLowerCase().trim();

                rows.forEach(row => {
                    const id = row.querySelector('.search-id').textContent.toLowerCase();
                    const name = row.querySelector('.search-name').textContent.toLowerCase();
                    const username = row.querySelector('.search-badge') ? row.querySelector('.search-badge').textContent.toLowerCase() : row.textContent.toLowerCase();
                    const phone = row.querySelector('.search-phone').textContent.toLowerCase();

                    if (id.includes(query) || name.includes(query) || username.includes(query) || phone.includes(query)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    });
    </script>
</body>
</html>