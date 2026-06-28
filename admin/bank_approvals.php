<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config.php';

//requireAdmin();
$pdo = getConnection();

// Handle approval/rejection
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_reject_submission'])) {
    $submission_id = $_POST['submission_id'];
    $status = $_POST['status'];
    $approved_by = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("UPDATE bank_submissions SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $approved_by, $submission_id]);
        
        $message = 'Submission status updated successfully!';
    } catch (PDOException $e) {
        $error = 'Error updating submission: ' . $e->getMessage();
    }
}

// Get pending bank submissions for approval with agent details
$pending_submissions_stmt = $pdo->query("
    SELECT bs.*, u.name as agent_name, u.username as agent_username,
           DATE_FORMAT(bs.created_at, '%M %d, %Y at %h:%i %p') as formatted_created_at
    FROM bank_submissions bs
    JOIN users u ON bs.agent_id = u.id
    WHERE bs.status = 'pending'
    ORDER BY bs.created_at DESC
");
$pending_submissions = $pending_submissions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get agent statistics for the dashboard
$agents_stmt = $pdo->prepare("
    SELECT u.id, u.name, u.username,
           (SELECT COUNT(*) FROM daily_assignments da WHERE da.agent_id = u.id AND da.status = 'completed') as completed_stores,
           (SELECT COUNT(DISTINCT s.mall) FROM daily_assignments da 
            JOIN stores s ON da.store_id = s.id 
            WHERE da.agent_id = u.id AND da.status = 'completed') as completed_malls,
           (SELECT COUNT(DISTINCT s.region_id) FROM daily_assignments da 
            JOIN stores s ON da.store_id = s.id 
            WHERE da.agent_id = u.id AND da.status = 'completed') as completed_regions
    FROM users u
    WHERE u.role = 'agent'
    ORDER BY u.name
");
$agents_stmt->execute();
$agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Submissions Approval - Apparels Collection</title>
    <link rel="stylesheet" href="../css/modern-dashboard.css">
    <link rel="stylesheet" href="../css/bank-approvals.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" href="../images/icon-192x192.png" type="image/png">
    <script src="../js/app.js"></script>
    
    <style>
        :root {
            --bg-card: #13151a;
            --border-color: rgba(255, 255, 255, 0.08);
            --text-muted: #8a8f98;
            --accent-blue: #3b82f6;
            --accent-danger: #ef4444;
            --bg-input: #1c1f26;
            --success: #10b981;
            --warning: #f59e0b;
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

        /* Full Screen Fluid Two-Column Grid Setup */
        .approvals-grid {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 24px;
            align-items: start;
            width: 100%;
        }

        @media (max-width: 1250px) {
            .approvals-grid {
                grid-template-columns: 1fr;
            }
        }

        .panel-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            margin-bottom: 24px;
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

        /* Interactive Navigation Search elements */
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
            max-width: 320px;
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

        /* High-Density View Bound Scrollable Layout Engine */
        .table-scroll-container {
            background: #16181f;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow-x: auto;
            max-height: 480px;
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

        /* Unified Microbadge Token Utilities */
        .badge {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-blue { background: rgba(59, 130, 246, 0.12); color: #93c5fd; }
        .badge-success { background: rgba(16, 185, 129, 0.12); color: #10b981; }

        /* Status Microbadges */
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        .status-approved { background: rgba(16, 185, 129, 0.12); color: #10b981; }
        .status-rejected { background: rgba(239, 68, 68, 0.12); color: #ef4444; }
        .status-pending { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }

        /* Action Buttons Grid Layout */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 12px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); border: 1px solid rgba(239, 68, 68, 0.2); }
        .btn-danger:hover { background: var(--accent-danger); color: white; }
        
        .receipt-link {
            color: var(--accent-blue);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .receipt-link:hover { text-decoration: underline; }

        .collection-details-box p {
            margin: 0 0 4px 0;
            font-size: 12px;
            color: var(--text-muted);
        }
        .collection-details-box p strong { color: #fff; }
        .collection-details-box p:last-child { margin-bottom: 0; }

        /* Webkit Interface Engine Scrollbars */
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
                    <span class="material-symbols-outlined">home</span><span>Dashboard</span>
                </a>
                <a href="assignments.php" class="nav-item">
                    <span class="material-symbols-outlined">assignment</span><span>Assignments</span>
                </a>
                <a href="agents.php" class="nav-item">
                    <span class="material-symbols-outlined">people</span><span>Agents</span>
                </a>
                <a href="management.php" class="nav-item">
                    <span class="material-symbols-outlined">settings</span><span>Management</span>
                </a>
                <a href="store_data.php" class="nav-item">
                    <span class="material-symbols-outlined">storefront</span><span>Store Data</span>
                </a>
                <a href="bank_approvals.php" class="nav-item active">
                    <span class="material-symbols-outlined">receipt_long</span><span>Bank Approvals</span>
                </a>
            </div>
        </div>
        
        <div class="overlay"></div>
        
        <div class="main-content">
            <div class="top-header">
                <div class="header-left">
                    <div class="hamburger"><span></span><span></span><span></span></div>
                    <div class="header-title">Bank Approvals System</div>
                </div>
                <div class="header-right">
                    <a href="../logout.php" class="logout-btn">
                        <span class="material-symbols-outlined">logout</span> Logout
                    </a>
                </div>
            </div>

            <div class="content">
                <?php if ($message): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px; padding: 12px; border-radius: 8px; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); font-size: 14px;"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom: 20px; padding: 12px; border-radius: 8px; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); font-size: 14px;"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="approvals-grid">
                    
                    <div class="panel-card" style="overflow: hidden;">
                        <h2><span class="material-symbols-outlined" style="color: var(--accent-blue);">trending_up</span> Fleet Performance</h2>
                        <div class="table-scroll-container" style="max-height: calc(100vh - 210px);">
                            <table id="agentPerformanceTable">
                                <thead>
                                    <tr>
                                        <th>Agent Profile</th>
                                        <th style="text-align: center;">Metrics</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($agents as $agent): ?>
                                        <tr class="agent-row">
                                            <td>
                                                <div style="font-weight: 600; color: #fff;" class="search-agent-name"><?php echo htmlspecialchars($agent['name']); ?></div>
                                                <div style="color: var(--text-muted); font-size: 12px; margin-top: 1px;">@<?php echo htmlspecialchars($agent['username']); ?></div>
                                            </td>
                                            <td style="text-align: right;">
                                                <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-end;">
                                                    <span class="badge badge-blue" style="font-size: 10px;"><?php echo $agent['completed_stores']; ?> Stores</span>
                                                    <span class="badge badge-blue" style="font-size: 10px;"><?php echo $agent['completed_malls']; ?> Malls</span>
                                                    <span class="badge badge-success" style="font-size: 10px;"><?php echo $agent['completed_regions']; ?> Regions</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="right-data-wrapper" style="overflow: hidden; width: 100%;">
                        
                        <div class="panel-card">
                            <div class="table-header-bar">
                                <h2><span class="material-symbols-outlined" style="color: var(--warning);">hourglass_empty</span> Pending Bank Submissions</h2>
                                <div class="search-wrapper">
                                    <span class="material-symbols-outlined">search</span>
                                    <input type="text" id="pendingSearch" class="search-input" placeholder="Filter pending entries...">
                                </div>
                            </div>
                            
                            <div class="table-scroll-container">
                                <?php if (count($pending_submissions) > 0): ?>
                                    <table id="pendingTable">
                                        <thead>
                                            <tr>
                                                <th>Agent Details</th>
                                                <th>Collected</th>
                                                <th>Deposited</th>
                                                <th>Discrepancy / Reason</th>
                                                <th>Submitted Date</th>
                                                <th>Receipt Verification</th>
                                                <th>Collection Details</th>
                                                <th style="text-align: center;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pending_submissions as $submission): ?>
                                                <tr class="pending-row">
                                                    <td>
                                                        <strong style="color: #fff;" class="search-p-agent"><?php echo htmlspecialchars($submission['agent_name']); ?></strong><br>
                                                        <small style="color: var(--text-muted);">@<?php echo htmlspecialchars($submission['agent_username']); ?></small>
                                                    </td>
                                                    <td style="font-weight: 500;">
                                                        <?php echo number_format($submission['collected_cash'], 2) . ' <span style="color: var(--text-muted); font-size:11px;">' . htmlspecialchars($submission['currency'] ?? 'SAR') . '</span>'; ?>
                                                    </td>
                                                    <td style="font-weight: 600; color: #fff;">
                                                        <?php echo number_format($submission['deposited_cash'], 2) . ' <span style="color: var(--text-muted); font-size:11px;">' . htmlspecialchars($submission['currency'] ?? 'SAR') . '</span>'; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
$diff = (float)$submission['discrepancy_amount'] * -1;  // FLIPPED
$currency = htmlspecialchars($submission['currency'] ?? 'SAR');
if ($diff > 0) {
    echo '<span style="color: var(--warning); font-weight: 600;">+' . number_format($diff, 2) . ' <small>' . $currency . '</small></span><br>';
    echo '<span style="color: var(--warning); font-size:12px;">' . htmlspecialchars($submission['discrepancy_reason']) . '</span>';
} elseif ($diff < 0) {
    echo '<span style="color: var(--accent-danger); font-weight: 600;">' . number_format($diff, 2) . ' <small>' . $currency . '</small></span><br>';
    echo '<span style="color: var(--accent-danger); font-size:12px;">' . htmlspecialchars($submission['discrepancy_reason']) . '</span>';
} else {
    echo '<span class="status-badge status-approved">Match</span>';
}
?>
                                                    </td>
                                                    <td style="color: var(--text-muted); font-size: 13px;"><?php echo $submission['formatted_created_at']; ?></td>
                                                    <td>
                                                        <?php if ($submission['receipt_image']): ?>
                                                            <a href="../<?php echo htmlspecialchars($submission['receipt_image']); ?>" target="_blank" class="receipt-link">
                                                                <span class="material-symbols-outlined" style="font-size: 18px;">image</span> View Receipt
                                                            </a>
                                                        <?php else: ?>
                                                            <span style="color: var(--text-muted); font-style: italic; font-size: 13px;">No attachment</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $collection_details_stmt = $pdo->prepare("
                                                            SELECT SUM(c.amount_collected) as total_collected, 
                                                                   SUM(c.pending_amount) as total_pending
                                                            FROM collections c
                                                            JOIN daily_assignments da ON c.assignment_id = da.id
                                                            WHERE da.agent_id = ? AND DATE(da.date_assigned) = DATE(?)
                                                        ");
                                                        $collection_details_stmt->execute([$submission['agent_id'], $submission['created_at']]);
                                                        $collection_details = $collection_details_stmt->fetch(PDO::FETCH_ASSOC);
                                                        
                                                        $cash_collected = $collection_details['total_collected'] ?: 0;
                                                        $pending_amount = $collection_details['total_pending'] ?: 0;
                                                        ?>
                                                        <div class="collection-details-box">
                                                            <p>App: <strong><?php echo number_format($cash_collected, 2); ?></strong></p>
                                                            <p>Pend: <strong><?php echo number_format($pending_amount, 2); ?></strong></p>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div style="display: flex; gap: 6px; justify-content: center;">
                                                            <form method="post" style="margin:0;" onsubmit="return confirm('Are you sure you want to approve this submission?');">
                                                                <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                                                <input type="hidden" name="status" value="approved">
                                                                <input type="hidden" name="approve_reject_submission" value="1">
                                                                <button type="submit" class="btn btn-success">Approve</button>
                                                            </form>
                                                            <form method="post" style="margin:0;" onsubmit="return confirm('Are you sure you want to reject this submission?');">
                                                                <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                                                <input type="hidden" name="status" value="rejected">
                                                                <input type="hidden" name="approve_reject_submission" value="1">
                                                                <button type="submit" class="btn btn-danger">Reject</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div style="padding: 24px; text-align: center; color: var(--text-muted);">No pending bank submissions found requiring verification.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="panel-card" style="margin-bottom: 0;">
                            <?php
                            $recent_submissions_stmt = $pdo->query("
                                SELECT bs.*, u.name as agent_name, u.username as agent_username,
                                       u2.name as approved_by_name,
                                       DATE_FORMAT(bs.approved_at, '%M %d, %Y at %h:%i %p') as formatted_approved_at,
                                       DATE_FORMAT(bs.created_at, '%M %d, %Y at %h:%i %p') as formatted_created_at
                                FROM bank_submissions bs
                                JOIN users u ON bs.agent_id = u.id
                                LEFT JOIN users u2 ON bs.approved_by = u2.id
                                WHERE bs.status != 'pending'
                                ORDER BY bs.created_at DESC
                                LIMIT 10
                            ");
                            $recent_submissions = $recent_submissions_stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            
                            <div class="table-header-bar">
                                <h2><span class="material-symbols-outlined" style="color: var(--success);">history</span> Recent Verified Audits (Limit 10)</h2>
                                <div class="search-wrapper">
                                    <span class="material-symbols-outlined">search</span>
                                    <input type="text" id="recentSearch" class="search-input" placeholder="Filter history archives...">
                                </div>
                            </div>

                            <div class="table-scroll-container">
                                <?php if (count($recent_submissions) > 0): ?>
                                    <table id="recentTable">
                                        <thead>
                                            <tr>
                                                <th>Agent</th>
                                                <th>Collected</th>
                                                <th>Deposited</th>
                                                <th>Discrepancy</th>
                                                <th style="text-align: center;">Status Class</th>
                                                <th>Submitted Date</th>
                                                <th>Auditor Name</th>
                                                <th>Action Timestamp</th>
                                                <th style="text-align: center;">Attachment</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_submissions as $submission): ?>
                                                <tr class="recent-row">
                                                    <td>
                                                        <strong style="color: #fff;" class="search-r-agent"><?php echo htmlspecialchars($submission['agent_name']); ?></strong><br>
                                                        <small style="color: var(--text-muted);">@<?php echo htmlspecialchars($submission['agent_username']); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php echo number_format($submission['collected_cash'], 2) . ' <small style="color: var(--text-muted);">' . htmlspecialchars($submission['currency'] ?? 'SAR') . '</small>'; ?>
                                                    </td>
                                                    <td style="color: #fff; font-weight: 500;">
                                                        <?php echo number_format($submission['deposited_cash'], 2) . ' <small style="color: var(--text-muted);">' . htmlspecialchars($submission['currency'] ?? 'SAR') . '</small>'; ?>
                                                    </td>
                                                    <td>
<?php 
$diff = (float)$submission['discrepancy_amount'] * -1;  // FLIPPED
$currency = htmlspecialchars($submission['currency'] ?? 'SAR');
if ($diff > 0) {
    echo '<span style="color: var(--warning); font-weight: 600;">+' . number_format($diff, 2) . ' <small>' . $currency . '</small></span>';
} elseif ($diff < 0) {
    echo '<span style="color: var(--accent-danger); font-weight: 600;">' . number_format($diff, 2) . ' <small>' . $currency . '</small></span>';
} else {
    echo '<span style="color: var(--success); font-weight: 600;">Match</span>';
}
?>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <?php 
                                                        $status_class = ($submission['status'] === 'approved') ? 'status-approved' : 'status-rejected';
                                                        ?>
                                                        <span class="status-badge <?php echo $status_class; ?>">
                                                            <?php echo ucfirst($submission['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td style="color: var(--text-muted); font-size: 13px;"><?php echo $submission['formatted_created_at']; ?></td>
                                                    <td style="font-weight: 500; color: #fff;"><?php echo $submission['approved_by_name'] ?? 'System Sync'; ?></td>
                                                    <td style="color: var(--text-muted); font-size: 13px;"><?php echo $submission['formatted_approved_at'] ?? 'N/A'; ?></td>
                                                    <td style="text-align: center;">
                                                        <?php if ($submission['receipt_image']): ?>
                                                            <a href="../<?php echo htmlspecialchars($submission['receipt_image']); ?>" target="_blank" class="receipt-link">
                                                                <span class="material-symbols-outlined" style="font-size: 18px;">open_in_new</span>
                                                            </a>
                                                        <?php else: ?>
                                                            <span style="color: var(--text-muted); font-size: 12px;">None</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div style="padding: 24px; text-align: center; color: var(--text-muted);">No archived bank audit histories found.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                    
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Nav Toggle Mobile Actions Drawer
        const hamburger = document.querySelector('.hamburger');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');
        
        if (hamburger && sidebar && overlay) {
            hamburger.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.classList.toggle('menu-open');
            });
            
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.classList.remove('menu-open');
            });
        }

        // Live Text Filter Matcher Engine: Pending Queue
        const pendingSearch = document.getElementById('pendingSearch');
        const pendingRows = document.querySelectorAll('.pending-row');
        if (pendingSearch) {
            pendingSearch.addEventListener('input', function(e) {
                const val = e.target.value.toLowerCase().trim();
                pendingRows.forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
                });
            });
        }

        // Live Text Filter Matcher Engine: Archive History
        const recentSearch = document.getElementById('recentSearch');
        const recentRows = document.querySelectorAll('.recent-row');
        if (recentSearch) {
            recentSearch.addEventListener('input', function(e) {
                const val = e.target.value.toLowerCase().trim();
                recentRows.forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
                });
            });
        }
    });
    </script>
</body>
</html>