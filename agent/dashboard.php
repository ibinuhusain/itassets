<?php
require_once '../includes/auth.php';

if (hasRole('admin')) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$pdo = getConnection();
$agent_id = $_SESSION['user_id'];

// Get today's date for statistics
$today = date('Y-m-d');

// Get agent's assignments for today with proper joins
$stmt = $pdo->prepare("
    SELECT 
        da.*, 
        s.name as store_name, 
        s.address as store_address,
        s.mall,
        s.entity,
        s.brand,
        r.name as region_name,
        COALESCE(SUM(c.amount_collected), 0) as collected_amount
    FROM daily_assignments da
    JOIN stores s ON da.store_id = s.id
    LEFT JOIN regions r ON s.region_id = r.id
    LEFT JOIN collections c ON da.id = c.assignment_id
    WHERE da.agent_id = ? AND DATE(da.date_assigned) = ?
    GROUP BY da.id
    ORDER BY s.name
");
$stmt->execute([$agent_id, $today]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_target = 0;
$total_collected = 0;
$completed_count = 0;
$total_assignments = count($assignments);

foreach ($assignments as $assignment) {
    $total_target += $assignment['target_amount'];
    $total_collected += $assignment['collected_amount'];
    
    if ($assignment['status'] === 'completed') {
        $completed_count++;
    }
}

$collection_percentage = $total_target > 0 ? round(($total_collected / $total_target) * 100, 2) : 0;
$remaining_assignments = $total_assignments - $completed_count;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Apparel Group KSA</title>
    <link rel="stylesheet" href="../css/agent-styles.css">
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" type="image/png" href="/images/icon-192x192.png">
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    
    <style>
        /* Mobile Table Styles */
        @media (max-width: 768px) {
            .mobile-hide {
                display: none !important;
            }
            .store-details {
                display: block;
                font-size: 0.85em;
                color: var(--text-secondary);
                margin-top: 4px;
            }
        }

        @media (min-width: 769px) {
            .store-details {
                display: none;
            }
        }

        /* Table Container */
        .table-container {
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            margin: 24px 0;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: var(--elevation-1);
            transition: var(--transition);
        }

        .table-container:hover {
            box-shadow: var(--elevation-2);
        }

        .table-container table {
            width: 100%;
            min-width: 1000px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-container thead {
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .table-container th {
            padding: 18px 16px;
            text-align: left;
            background: rgba(23, 25, 49, 0.8);
            color: var(--text-primary);
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            border-bottom: 1px solid var(--card-border);
        }

        .table-container td {
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            font-size: 14px;
            transition: var(--transition);
        }

        .table-container tbody tr:last-child td {
            border-bottom: none;
        }

        .table-container tbody tr:hover td {
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-primary);
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 100px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-secondary);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .status-pending-badge {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            border-color: rgba(245, 158, 11, 0.2);
        }

        .status-completed-badge {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border-color: rgba(16, 185, 129, 0.2);
        }

        /* Scrollbar Styling */
        .table-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 0 16px 16px 0;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            border: 2px solid transparent;
            background-clip: padding-box;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        @media (max-width: 768px) {
            .table-container {
                border-radius: 12px;
                margin: 16px 0;
            }
            
            .table-container th {
                padding: 14px 12px;
                font-size: 12px;
            }
            
            .table-container td {
                padding: 12px;
                font-size: 13px;
            }
            
            .status-badge {
                padding: 4px 12px;
                font-size: 11px;
                min-width: 80px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="../images/logo.png" alt="Apparel Collection Logo">
                    <div class="sidebar-logo-text"></div>
                </div>
            </div>
            
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <span class="material-symbols-outlined">home</span>
                    <span>Dashboard</span>
                </a>
                <a href="submissions.php" class="nav-item">
                    <span class="material-symbols-outlined">receipt_long</span>
                    <span>Submissions</span>
                </a>
            </div>
        </div>
        
        <!-- Overlay for mobile menu -->
        <div class="overlay"></div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Top Header -->
            <div class="top-header">
                <div class="header-left">
                    <div class="hamburger-menu">
                        <span class="material-symbols-outlined">menu</span>
                    </div>
                </div>
                <div class="header-right">
                    <a href="../logout.php" class="logout-btn">
                        <span class="material-symbols-outlined">logout</span>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
            
            <!-- Page Content -->
            <div class="content">
                <h2 class="page-title">Today's Collection Target</h2>
                
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3><?php echo number_format($total_target, 2); ?></h3>
                        <p>Total Target</p>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php echo number_format($total_collected, 2); ?></h3>
                        <p>Collected</p>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php echo $collection_percentage; ?>%</h3>
                        <p>Completion Rate</p>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php echo $remaining_assignments; ?></h3>
                        <p>Remaining Stores</p>
                    </div>
                </div>
            
                <h2>Your Assigned Entities (<?php echo date('M j, Y'); ?>)</h2>
                <?php if (count($assignments) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Store Name</th>
                                    <th class="mobile-hide">Region</th>
                                    <th class="mobile-hide">Mall</th>
                                    <th class="mobile-hide">Entity</th>
                                    <th class="mobile-hide">Brand</th>
                                    <th class="mobile-hide">Address</th>
                                    <th class="mobile-hide">Collected</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $assignment): ?>
                                    <?php
                                    $status = $assignment['status'] ?? 'pending';
                                    
                                    // Create condensed info for mobile
                                    $storeDetails = [];
                                    if (!empty($assignment['brand'])) $storeDetails[] = htmlspecialchars($assignment['brand']);
                                    if (!empty($assignment['region_name'])) $storeDetails[] = htmlspecialchars($assignment['region_name']);
                                    $condensed_info = implode(' • ', $storeDetails);
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($assignment['store_name'] ?? 'N/A'); ?>
                                            <?php if (!empty($condensed_info)): ?>
                                                <div class="store-details"><?php echo $condensed_info; ?></div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td class="mobile-hide"><?php echo htmlspecialchars($assignment['region_name'] ?? 'N/A'); ?></td>
                                        <td class="mobile-hide"><?php echo htmlspecialchars($assignment['mall'] ?? 'N/A'); ?></td>
                                        <td class="mobile-hide"><?php echo htmlspecialchars($assignment['entity'] ?? 'N/A'); ?></td>
                                        <td class="mobile-hide"><?php echo htmlspecialchars($assignment['brand'] ?? 'N/A'); ?></td>
                                        <td class="mobile-hide"><?php echo htmlspecialchars($assignment['store_address'] ?? 'N/A'); ?></td>
                                        
                                        <td class="mobile-hide"><?php echo number_format($assignment['collected_amount'], 2); ?></td>
                                        
                                        <td>
                                            <span class="status-badge <?php echo $status === 'completed' ? 'status-completed-badge' : 'status-pending-badge'; ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        
                                        <td>
                                            <a href="store.php?assignment_id=<?php echo $assignment['id']; ?>" class="btn">
                                                <?php echo $status === 'completed' ? 'View' : 'Manage'; ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <p>You have no assignments for today.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../js/app.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.querySelector('.hamburger-menu');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');
        const body = document.body;
        
        if (hamburger && sidebar) {
            hamburger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                sidebar.classList.toggle('active');
                if (overlay) {
                    overlay.classList.toggle('active');
                }
                body.classList.toggle('menu-open');
            });
            
            if (overlay) {
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    body.classList.remove('menu-open');
                });
            }
            
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('active');
                        if (overlay) {
                            overlay.classList.remove('active');
                        }
                        body.classList.remove('menu-open');
                    }
                });
            });
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                    if (overlay) {
                        overlay.classList.remove('active');
                    }
                    body.classList.remove('menu-open');
                }
            });
        }
    });
    </script>
</body>
</html>