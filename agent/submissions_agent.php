<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';
requireAgent();

$pdo = getConnection();
$agent_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle bank submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_to_bank'])) {
    $total_amount = floatval($_POST['total_amount']);
    $receipt_image_url = $_POST['bank_receipt_url'] ?? ''; // Get from hidden field

    if (empty($receipt_image_url)) {
        $error = 'Bank receipt photo is required.';
    } else {
        try {
            // Insert bank submission
            $stmt = $pdo->prepare("
                INSERT INTO bank_submissions (agent_id, total_amount, receipt_image, status)
                VALUES (?, ?, ?, 'pending')
            ");
            $stmt->execute([$agent_id, $total_amount, $receipt_image_url]);

            // Mark today's collections as submitted
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

// Get today's total collected
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
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Submissions - Apparel Collection</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link rel="stylesheet" href="../css/mobile_agent.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bank Submissions</h1>
            <p>Submit your daily collections to the bank</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Submit to Bank</h2>
            <p style="margin-bottom: 20px; color: var(--text-secondary);">
                Today's Total Collected: <strong>SAR <?php echo number_format($today_total, 2); ?></strong>
            </p>

            <form method="post" id="bankSubmissionForm">
                <input type="hidden" name="submit_to_bank" value="1">
                
                <div class="form-group">
                    <label for="total_amount">Total Amount to Submit (SAR)</label>
                    <input type="number" id="total_amount" name="total_amount" 
                           step="0.01" min="0" value="<?php echo $today_total; ?>" required>
                </div>

                <!-- Camera section for bank receipt -->
                <div class="form-group">
                    <label>Bank Deposit Receipt</label>
                    <button type="button" id="takeBankPhotoBtn" class="btn">Take Photo of Bank Receipt</button>
                    <div id="receipt-preview" style="margin-top:10px;"></div>
                    <input type="hidden" name="bank_receipt_url" id="bank_receipt_url" value="">
                    <small style="display: block; margin-top: 8px; color: var(--text-secondary);">
                        Take a photo of the bank deposit slip/receipt
                    </small>
                </div>

                <button type="submit" class="btn">Submit to Bank</button>
                <a href="dashboard_agent.php" class="btn btn-secondary">Back to Dashboard</a>
            </form>
        </div>

        <div class="card">
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
                                    <td>SAR <?php echo number_format($submission['total_amount'], 2); ?></td>
                                    <td>
                                        <?php if ($submission['receipt_image']): ?>
                                            <a href="<?php echo htmlspecialchars($submission['receipt_image']); ?>" 
                                               target="_blank" class="receipt-link">
                                                View Receipt
                                            </a>
                                        <?php else: ?>
                                            N/A
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
                                        <span class="status-badge <?php echo $status_class; ?>">
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
                <p style="color: var(--text-secondary); text-align: center; padding: 20px 0;">
                    No submission history found.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-navigation">
        <a href="dashboard_agent.php" class="nav-item">
            <span class="material-symbols-outlined">home</span>
            <span>Dashboard</span>
        </a>
        <a href="submissions_agent.php" class="nav-item active">
            <span class="material-symbols-outlined">receipt_long</span>
            <span>Submissions</span>
        </a>
        <a href="store_agent.php" class="nav-item">
            <span class="material-symbols-outlined">storefront</span>
            <span>Store</span>
        </a>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.nativeBridge) {
            console.log('Native bridge ready');

            // Callback for photo uploads
            window.nativeBridge.onPhotoTaken = function(callbackId, imageUrl) {
                console.log('Bank receipt uploaded:', imageUrl);
                // Show preview
                const preview = document.getElementById('receipt-preview');
                preview.innerHTML = ''; // clear previous
                const img = document.createElement('img');
                img.src = imageUrl;
                img.width = 100;
                preview.appendChild(img);
                // Store URL in hidden field
                document.getElementById('bank_receipt_url').value = imageUrl;
            };

            // Take bank photo button
            document.getElementById('takeBankPhotoBtn').addEventListener('click', function() {
                // Use a callback ID indicating bank upload
                const callbackId = 'bank_' + Date.now();
                // Call the native method (assuming bridge supports 'takeBankPhoto')
                // We'll use takePhoto with a special type; the app should recognize 'bank_' prefix.
                window.nativeBridge.takePhoto(callbackId);
            });

        } else {
            console.warn('Not running inside Cordova app – native features disabled');
            document.getElementById('takeBankPhotoBtn').style.display = 'none';
        }
    });
    </script>
</body>
</html>