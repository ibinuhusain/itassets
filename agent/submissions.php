<?php
require_once '../includes/auth.php';
//requireLogin();

if (hasRole('admin')) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$pdo = getConnection();
$agent_id = $_SESSION['user_id'];

// Handle form submission for bank submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_to_bank'])) {
    $total_amount = floatval($_POST['total_amount']);
    
    // Handle receipt image upload
    $receipt_image = null;
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        $tmp_name = $_FILES['receipt_image']['tmp_name'];
        $name = $_FILES['receipt_image']['name'];
        
        // Sanitize filename
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) {
            $new_filename = uniqid() . '_' . $name;
            $destination = $upload_dir . $new_filename;
            
            if (move_uploaded_file($tmp_name, $destination)) {
                $receipt_image = $new_filename;
            } else {
                $error = 'Failed to upload receipt image.';
            }
        } else {
            $error = 'Only JPG, JPEG, PNG, GIF, and PDF files are allowed.';
        }
    } else {
        $error = 'Receipt image is required.';
    }
    
    if (empty($error)) {
        try {
            // Insert bank submission
            $stmt = $pdo->prepare("
                INSERT INTO bank_submissions (agent_id, total_amount, receipt_image, status)
                VALUES (?, ?, ?, 'pending')
            ");
            $stmt->execute([$agent_id, $total_amount, $receipt_image]);
            
            // Mark all collections for today as submitted to bank
            $today = date('Y-m-d');
            $update_stmt = $pdo->prepare("
                UPDATE collections c
                JOIN daily_assignments da ON c.assignment_id = da.id
                SET c.submitted_to_bank = TRUE, c.submitted_at = NOW()
                WHERE da.agent_id = ? AND DATE(da.date_assigned) = ?
            ");
            $update_stmt->execute([$agent_id, $today]);
            
            $message = 'Bank submission sent for approval successfully!';
        } catch (PDOException $e) {
            $error = 'Error submitting to bank: ' . $e->getMessage();
        }
    }
}

// Get today's collections to calculate total amount
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT SUM(c.amount_collected) as total_collected
    FROM collections c
    JOIN daily_assignments da ON c.assignment_id = da.id
    WHERE da.agent_id = ? AND DATE(da.date_assigned) = ?
");
$stmt->execute([$agent_id, $today]);
$today_total = $stmt->fetchColumn() ?: 0;

// Get submission history
$history_stmt = $pdo->prepare("
    SELECT bs.*, u2.name as approved_by_name, 
           DATE_FORMAT(bs.approved_at, '%M %d, %Y at %h:%i %p') as formatted_approved_at,
           DATE_FORMAT(bs.created_at, '%M %d, %Y at %h:%i %p') as formatted_created_at
    FROM bank_submissions bs
    LEFT JOIN users u2 ON bs.approved_by = u2.id
    WHERE bs.agent_id = ?
    ORDER BY bs.created_at DESC
    LIMIT 10
");
$history_stmt->execute([$agent_id]);
$submission_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
    
<style>

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

.submission-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-width: 1000px;
}

.submission-table thead {
    position: sticky;
    top: 0;
    z-index: 2;
}

.submission-table th {
    padding: 18px 16px;
    text-align: left;
    background: rgba(23, 25, 49, 0.8);
    color: var(--text-primary);
    font-weight: 600;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--card-border);
}

.submission-table td {
    padding: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    color: var(--text-secondary);
    font-size: 14px;
    transition: var(--transition);
}

.submission-table tbody tr:last-child td {
    border-bottom: none;
}

.submission-table tbody tr:hover td {
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

.status-pending {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border-color: rgba(245, 158, 11, 0.2);
}

.status-approved {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border-color: rgba(16, 185, 129, 0.2);
}

.status-rejected {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.2);
}

/* Receipt Link */
.receipt-link {
    color: #a5b4fc;
    text-decoration: none;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
}

.receipt-link .material-symbols-outlined {
    font-size: 18px;
}

.receipt-link:hover {
    color: var(--accent-color);
    text-decoration: underline;
}

.no-receipt {
    color: rgba(255, 255, 255, 0.3);
    font-style: italic;
}

/* No Data Message */
.no-data {
    padding: 40px 20px;
    text-align: center;
    color: rgba(255, 255, 255, 0.5);
    font-style: italic;
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

/* Responsive Table */
@media (max-width: 768px) {
    .table-container {
        border-radius: 12px;
        margin: 16px 0;
    }
    
    .submission-table th {
        padding: 14px 12px;
        font-size: 12px;
    }
    
    .submission-table td {
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
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Submissions - Apparels Collection</title>
    <link rel="stylesheet" href="../css/agent-styles.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" href="../images/icon-192x192.png" type="image/png">
    <script src="../js/app.js"></script>
</head>

<body>
    <div class="container">
        <!-- Sidebar Navigation -->
       <div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="../images/logo.png" alt="Apparel Collection Logo" style="align:center">
            <div class="sidebar-logo-text"></div>
        </div>
    </div>
    
    <div class="sidebar-nav">
        <a href="dashboard.php" class="nav-item">
            <span class="material-symbols-outlined">home</span>
            <span>Dashboard</span>
        </a>
         <!--
        <a href="store.php" class="nav-item">
            <span class="material-symbols-outlined">storefront</span>
            <span>Store</span>
        </a>
        -->
        <a href="submissions.php" class="nav-item active">
            <span class="material-symbols-outlined">receipt_long</span>
            <span>Submissions</span>
        </a>
    </div>
</div>
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
        
        <div class="agent-content">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <h2>Submit to Bank</h2>
            <div class="card">
                <p><strong>Today's Total Collected:</strong> <?php echo number_format($today_total, 2); ?></p>
                <p><strong>Date:</strong> <?php echo date('M j, Y'); ?></p>
            </div>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="submit_to_bank" value="1">
                
                <div class="form-group">
                    <label for="total_amount">Total Amount to Submit:</label>
                    <input type="number" id="total_amount" name="total_amount" 
                           value="<?php echo $today_total; ?>" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="receipt_image">Bank Deposit Receipt:</label>
                    <input type="file" id="receipt_image" name="receipt_image" accept="image/*,.pdf" required>
                    <small>Upload the bank deposit slip/receipt</small>
                </div>
                
                <button type="submit" class="btn btn-success">Submit to Bank</button>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <!-- Replace the existing history table section with this: -->
<h2>Submission History</h2>
            <?php if (count($submission_history) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date Submitted</th>
                                <th>Amount</th>
                                <th>Receipt</th>
                                <th>Status</th>
                                <th>Approved By</th>
                                <th>Approved At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submission_history as $submission): ?>
                                <tr>
                                    <td><?php echo $submission['formatted_created_at']; ?></td>
                                    <td><?php echo number_format($submission['total_amount'], 2); ?></td>
                                    <td>
                                        <?php if ($submission['receipt_image']): ?>
                                            <a href="../uploads/<?php echo htmlspecialchars($submission['receipt_image']); ?>" target="_blank" class="receipt-link">
                                                <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">
                                                    receipt_long
                                                </span>
                                                View Receipt
                                            </a>
                                        <?php else: ?>
                                            <span class="no-receipt">No receipt</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = '';
                                        if ($submission['status'] === 'approved') {
                                            $status_class = 'status-approved';
                                        } elseif ($submission['status'] === 'rejected') {
                                            $status_class = 'status-rejected';
                                        } else {
                                            $status_class = 'status-pending';
                                        }
                                        ?>
                                        <span class="<?php echo $status_class; ?>">
                                            <?php echo ucfirst($submission['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $submission['approved_by_name'] ?? 'N/A'; ?></td>
                                    <td><?php echo $submission['formatted_approved_at'] ?? 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="material-symbols-outlined" style="font-size: 48px; color: #ccc; margin-bottom: 15px;">
                        history
                    </span>
                    <p>No submission history found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<!-- JavaScript Files -->
<script src="../js/app.js"></script>
<script>
    // Direct hamburger menu setup - simpler and more reliable
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, setting up hamburger menu...');
        
        const hamburger = document.querySelector('.hamburger-menu');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');
        const body = document.body;
        
        if (hamburger && sidebar) {
            console.log('Elements found, adding event listeners...');
            
            // Hamburger click event
            hamburger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Hamburger clicked');
                
                sidebar.classList.toggle('active');
                if (overlay) {
                    overlay.classList.toggle('active');
                }
                body.classList.toggle('menu-open');
            });
            
            // Overlay click event to close sidebar
            if (overlay) {
                overlay.addEventListener('click', function() {
                    console.log('Overlay clicked, closing sidebar');
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    body.classList.remove('menu-open');
                });
            }
            
            // Close menu when clicking on nav items (mobile)
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        console.log('Nav item clicked on mobile, closing sidebar');
                        sidebar.classList.remove('active');
                        if (overlay) {
                            overlay.classList.remove('active');
                        }
                        body.classList.remove('menu-open');
                    }
                });
            });
            
            // Close menu when window is resized to desktop size
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                    if (overlay) {
                        overlay.classList.remove('active');
                    }
                    body.classList.remove('menu-open');
                }
            });
            
            console.log('Hamburger menu setup complete');
        } else {
            console.error('Could not find hamburger or sidebar elements');
            console.log('Hamburger found:', !!hamburger);
            console.log('Sidebar found:', !!sidebar);
            console.log('Overlay found:', !!overlay);
        }
    });
</script>
</body>
</html>