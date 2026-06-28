<?php
require_once '../includes/auth.php';
requireLogin();

if (hasRole('admin')) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$pdo = getConnection();
$agent_id = $_SESSION['user_id'];

$assignment_id = $_GET['assignment_id'] ?? null;

if (!$assignment_id) {
    header("Location: dashboard.php");
    exit();
}

// Fetch assignment details
$stmt = $pdo->prepare("
    SELECT da.*, s.name as store_name, s.address as store_address, u.name as agent_name
    FROM daily_assignments da
    JOIN stores s ON da.store_id = s.id
    JOIN users u ON da.agent_id = u.id
    WHERE da.id = ? AND da.agent_id = ?
");
$stmt->execute([$assignment_id, $agent_id]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

$message = '';
if (isset($_GET['saved']) && $_GET['saved'] == '1') {
    $message = 'Collection information saved successfully!';
}

if (!$assignment) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

// Fetch existing collection if any
$stmt = $pdo->prepare("SELECT * FROM collections WHERE assignment_id = ?");
$stmt->execute([$assignment_id]);
$collection = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount_collected = floatval($_POST['amount_collected']);
    $pending_amount = floatval($_POST['pending_amount']);
    $comments = trim($_POST['comments']);

    // Get image URLs from hidden field
    $receipt_images = [];
    if (!empty($_POST['receipt_images_json'])) {
        $receipt_images = json_decode($_POST['receipt_images_json'], true);
        if (!is_array($receipt_images)) {
            $receipt_images = [];
        }
    }

    try {
        if ($collection) {
            // Update existing collection
            $update_stmt = $pdo->prepare("
                UPDATE collections
                SET amount_collected = ?, pending_amount = ?, comments = ?, receipt_images = ?, updated_at = NOW()
                WHERE assignment_id = ?
            ");
            $update_stmt->execute([
                $amount_collected,
                $pending_amount,
                $comments,
                json_encode($receipt_images),
                $assignment_id
            ]);
        } else {
            // Insert new collection
            $insert_stmt = $pdo->prepare("
                INSERT INTO collections (assignment_id, amount_collected, pending_amount, comments, receipt_images, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $insert_stmt->execute([
                $assignment_id,
                $amount_collected,
                $pending_amount,
                $comments,
                json_encode($receipt_images)
            ]);
        }

        // Update assignment status to completed
        $update_assignment = $pdo->prepare("UPDATE daily_assignments SET status = 'completed' WHERE id = ?");
        $update_assignment->execute([$assignment_id]);

        header("Location: store.php?assignment_id=" . $assignment_id . "&saved=1");
        exit;

    } catch (PDOException $e) {
        $error = 'Error saving collection: ' . $e->getMessage();
    }
}

// Pre-fill form with existing data
$amount_collected = $collection ? $collection['amount_collected'] : 0;
$pending_amount = $collection ? $collection['pending_amount'] : 0;
$comments = $collection ? $collection['comments'] : '';
$receipt_images = $collection ? json_decode($collection['receipt_images'], true) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Collection</title>
    <link rel="stylesheet" href="../css/agent-styles.css">
    <link rel="icon" href="../images/icon-192x192.png" type="image/png">
</head>
<body>
    <div class="container">
        <div class="main-content">
            <h1>Store Collection</h1>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Print Receipt Button (for signature receipt) -->
            <div class="form-group">
                <button type="button" id="printReceiptBtn" class="btn btn-secondary">Print Receipt for Signature</button>
            </div>

            <form method="post" id="collectionForm">
                <div class="form-group">
                    <label>Amount Collected (SAR)</label>
                    <input type="number" name="amount_collected" value="<?php echo htmlspecialchars($amount_collected); ?>" step="0.01" required>
                </div>

                <div class="form-group">
                    <label>Pending Amount (SAR)</label>
                    <input type="number" name="pending_amount" value="<?php echo htmlspecialchars($pending_amount); ?>" step="0.01" required>
                </div>

                <div class="form-group">
                    <label>Comments</label>
                    <textarea name="comments"><?php echo htmlspecialchars($comments); ?></textarea>
                </div>

                <!-- Camera section -->
                <div class="form-group">
                    <label>Receipt Photos</label>
                    <button type="button" id="takePhotoBtn" class="btn">Take Photo</button>
                    <div id="camera-preview" style="margin-top:10px;">
                        <?php if (!empty($receipt_images)): ?>
                            <?php foreach ($receipt_images as $img): ?>
                                <img src="<?php echo htmlspecialchars($img); ?>" width="100" style="margin:5px;">
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <!-- Hidden field to store image URLs as JSON -->
                    <input type="hidden" name="receipt_images_json" id="receipt_images_json" value='<?php echo htmlspecialchars(json_encode($receipt_images)); ?>'>
                </div>

                <button type="submit" class="btn btn-primary">Save Collection</button>
            </form>

            <?php if (!empty($receipt_images)): ?>
                <h2>Uploaded Receipts</h2>
                <div>
                    <?php foreach ($receipt_images as $img): ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" width="100" style="margin:5px;">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bottom Navigation (optional) -->
    <div class="bottom-navigation">
        <a href="dashboard_agent.php" class="nav-item">Dashboard</a>
        <a href="submissions_agent.php" class="nav-item">Submissions</a>
        <a href="store_agent.php" class="nav-item">Store</a>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we're inside the Cordova app
        if (window.nativeBridge) {
            console.log('Native bridge ready');

            // Callback when a photo is uploaded
            window.nativeBridge.onPhotoTaken = function(callbackId, imageUrl) {
                console.log('Photo uploaded:', imageUrl);
                // Add image to preview
                const preview = document.getElementById('camera-preview');
                const img = document.createElement('img');
                img.src = imageUrl;
                img.width = 100;
                img.style.margin = '5px';
                preview.appendChild(img);

                // Update hidden field with JSON array of image URLs
                const hidden = document.getElementById('receipt_images_json');
                let urls = [];
                try {
                    urls = JSON.parse(hidden.value || '[]');
                } catch(e) {
                    urls = [];
                }
                urls.push(imageUrl);
                hidden.value = JSON.stringify(urls);
            };

            // Take photo button
            document.getElementById('takePhotoBtn').addEventListener('click', function() {
                // Get the collection ID if it exists (for receipt association)
                // In this form, we don't have a receipt_id until after saving.
                // For now, we'll use a placeholder '0' – the upload endpoint should handle this.
                // Ideally, we'd have a receipt_id after saving, but here we are before save.
                // So we'll use a generic callback and rely on the server to associate later.
                // Or we can save the collection first, then take photos.
                // To keep it simple, we'll allow photos before save and store URLs in hidden field.
                const callbackId = 'photo_' + Date.now();
                window.nativeBridge.takePhoto(callbackId);
            });

            // Print button
            document.getElementById('printReceiptBtn').addEventListener('click', function() {
                var shopData = {
                    shopName: "<?php echo addslashes($assignment['store_name']); ?>",
                    address: "<?php echo addslashes($assignment['store_address']); ?>",
                    date: "<?php echo date('Y-m-d'); ?>",
                    agentName: "<?php echo addslashes($assignment['agent_name']); ?>",
                    items: [] // empty for signature receipt
                };
                window.nativeBridge.printReceipt(shopData);
            });

        } else {
            console.warn('Not running inside Cordova app – native features disabled');
            // Optionally hide buttons or show fallback
            document.getElementById('takePhotoBtn').style.display = 'none';
            document.getElementById('printReceiptBtn').style.display = 'none';
        }
    });
    </script>
</body>
</html>