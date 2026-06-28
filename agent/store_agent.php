<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';

requireAgent();

$pdo = getConnection();
$agent_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// First, check if agent has any assignments for today
$assignments_stmt = $pdo->prepare("
    SELECT da.id 
    FROM daily_assignments da
    WHERE da.agent_id = ? AND DATE(da.date_assigned) = ?
    LIMIT 1
");
$assignments_stmt->execute([$agent_id, $today]);
$has_assignments = $assignments_stmt->fetch();

if (!$has_assignments) {
    $no_assignments = true;
} else {
    $no_assignments = false;
    $assignment_id = $_GET['assignment_id'] ?? null;

    if (!$assignment_id) {
        // If no assignment_id provided but agent has assignments, redirect to first assignment
        $first_assignment = $pdo->prepare("
            SELECT da.id 
            FROM daily_assignments da
            WHERE da.agent_id = ? AND DATE(da.date_assigned) = ?
            ORDER BY da.id ASC
            LIMIT 1
        ");
        $first_assignment->execute([$agent_id, $today]);
        $assignment = $first_assignment->fetch();
        
        if ($assignment) {
            header("Location: store.php?assignment_id=" . $assignment['id']);
            exit();
        }
    }

    // Verify assignment belongs to current agent
    $stmt = $pdo->prepare("
        SELECT da.*, s.name as store_name, s.address as store_address, 
               s.mall, s.entity, s.brand, r.name as region_name,
               u.name as agent_name
        FROM daily_assignments da
        JOIN stores s ON da.store_id = s.id
        LEFT JOIN regions r ON s.region_id = r.id
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
        $no_assignments = true;
    } else {
        // Handle form submission
        $message = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $amount_collected = floatval($_POST['amount_collected']);
            $pending_amount = floatval($_POST['pending_amount']);
            $comments = trim($_POST['comments']);
            $receipt_images = [];
            
            // Handle file uploads
            if (isset($_FILES['receipt_images']) && $_FILES['receipt_images']['error'][0] !== UPLOAD_ERR_NO_FILE) {
                $upload_dir = '../uploads/';
                
                for ($i = 0; $i < count($_FILES['receipt_images']['name']); $i++) {
                    if ($_FILES['receipt_images']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['receipt_images']['tmp_name'][$i];
                        $name = $_FILES['receipt_images']['name'][$i];
                        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) {
                            $new_filename = uniqid() . '_' . $name;
                            $destination = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($tmp_name, $destination)) {
                                $receipt_images[] = $new_filename;
                            } else {
                                $error = 'Failed to upload one or more images.';
                            }
                        } else {
                            $error = 'Only JPG, JPEG, PNG, GIF, and PDF files are allowed.';
                        }
                    }
                }
            }
            
            if (empty($error)) {
                try {
                    // Check if collection exists
                    $check_stmt = $pdo->prepare("SELECT id FROM collections WHERE assignment_id = ?");
                    $check_stmt->execute([$assignment_id]);
                    $existing_collection = $check_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing_collection) {
                        // Update existing collection
                        $update_stmt = $pdo->prepare("
                            UPDATE collections 
                            SET amount_collected = ?, pending_amount = ?, comments = ?, receipt_images = ?
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
                            INSERT INTO collections (assignment_id, amount_collected, pending_amount, comments, receipt_images)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $insert_stmt->execute([
                            $assignment_id, 
                            $amount_collected, 
                            $pending_amount, 
                            $comments, 
                            json_encode($receipt_images)
                        ]);
                    }
                    
                    // Update assignment status
                    $update_assignment = $pdo->prepare("UPDATE daily_assignments SET status = 'completed' WHERE id = ?");
                    $update_assignment->execute([$assignment_id]);
                    
                    header("Location: store.php?assignment_id=" . $assignment_id . "&saved=1");
exit;

                } catch (PDOException $e) {
                    $error = 'Error saving collection: ' . $e->getMessage();
                }
            }
        }

        // Get existing collection data
        $collection = null;
        $stmt = $pdo->prepare("SELECT * FROM collections WHERE assignment_id = ?");
        $stmt->execute([$assignment_id]);
        $collection = $stmt->fetch(PDO::FETCH_ASSOC);

        // Set default values
        $amount_collected = $collection ? $collection['amount_collected'] : 0;
        $pending_amount = $collection ? $collection['pending_amount'] : 0;
        $comments = $collection ? $collection['comments'] : '';
        $receipt_images = $collection ? json_decode($collection['receipt_images'], true) : [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $no_assignments ? 'No Assignments' : 'Store Collection'; ?> - Apparel Collection</title>
    <link rel="stylesheet" href="../css/mobile_agent.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Apparel Collection</h1>
            <p><?php echo $no_assignments ? 'No Assignments' : 'Store Collection'; ?> - <?php echo date('M j, Y'); ?></p>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Agent'); ?></p>
        </div>

        <?php if ($no_assignments): ?>
            <div class="card no-assignments">
                <span class="material-symbols-outlined">storefront</span>
                <h2>No Stores Assigned</h2>
                <p>You don't have any store assignments for today. Please check back later or contact your supervisor if you believe this is an error.</p>
                <a href="dashboard_agent.php" class="btn">Back to Dashboard</a>
            </div>
        <?php else: ?>
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="content-section">
                <h2>Store Information</h2>
                <div class="card">
                    <p><strong>Store Name:</strong> <?php echo htmlspecialchars($assignment['store_name']); ?></p>
                    <p><strong>Region:</strong> <?php echo htmlspecialchars($assignment['region_name'] ?? 'N/A'); ?></p>
                    <p><strong>Mall:</strong> <?php echo htmlspecialchars($assignment['mall'] ?? 'N/A'); ?></p>
                    <p><strong>Entity:</strong> <?php echo htmlspecialchars($assignment['entity'] ?? 'N/A'); ?></p>
                    <p><strong>Brand:</strong> <?php echo htmlspecialchars($assignment['brand'] ?? 'N/A'); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($assignment['store_address']); ?></p>
                    <p><strong>Target Amount:</strong> SAR <?php echo number_format($assignment['target_amount'], 2); ?></p>
                </div>
            </div>

            <div class="content-section">
                <h2>Collection Details</h2>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="amount_collected">Amount Collected (SAR)</label>
                        <input type="number" id="amount_collected" name="amount_collected" 
                               step="0.01" min="0" value="<?php echo $amount_collected; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="pending_amount">Pending Amount (SAR)</label>
                        <input type="number" id="pending_amount" name="pending_amount" 
                               step="0.01" min="0" value="<?php echo $pending_amount; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="comments">Comments</label>
                        <textarea id="comments" name="comments"><?php echo htmlspecialchars($comments); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="receipt_images">Upload Receipts/Proof of Collection (Multiple files allowed)</label>
                        <input type="file" id="receipt_images" name="receipt_images[]" multiple accept="image/*,.pdf">
                        <small>Upload receipt images or PDFs (JPG, PNG, GIF, PDF)</small>
                    </div>

                    <?php if (!empty($receipt_images)): ?>
                        <div class="form-group">
                            <label>Uploaded Receipts:</label>
                            <div class="image-preview">
                                <?php foreach ($receipt_images as $img): ?>
                                    <a href="../uploads/<?php echo htmlspecialchars($img); ?>" target="_blank">
                                        <img src="../uploads/<?php echo htmlspecialchars($img); ?>" alt="Receipt">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn">Save Collection</button>
                    <a href="dashboard_agent.php" class="btn btn-secondary">Back to Dashboard</a>
                    
                    
                </form>
            </div>
        <?php endif; ?>
        
    <?php if ($collection): ?>
<button type="button" class="btn btn-secondary" onclick="printReceipt(<?php echo $assignment_id; ?>)">
    Print Receipt
</button>

<script>
function printReceipt() {

    const data = {
        assignment_id: "<?php echo $assignment_id; ?>",
        store_name: "<?php echo addslashes($assignment['store_name']); ?>",
        agent_name: "<?php echo addslashes($assignment['agent_name']); ?>",
        target_amount: "<?php echo $assignment['target_amount']; ?>",
        amount_collected: document.getElementById("amount_collected").value,
        pending_amount: document.getElementById("pending_amount").value,
        date: new Date().toLocaleString()
    };

    console.log("Sending print data:", data);

    window.parent.postMessage({
        type: "PRINT_RECEIPT",
        payload: data
    }, "*");
}

</script>

<?php endif; ?>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're inside the Cordova app (nativeBridge exists)
    if (window.nativeBridge) {
        console.log('Native bridge ready');
        
        // Define the callback that will be called when photo is uploaded
        window.nativeBridge.onPhotoTaken = function(callbackId, imageUrl) {
            console.log('Photo uploaded for callback:', callbackId, 'URL:', imageUrl);
            
            // Update your UI – maybe display the image
            const imgElement = document.getElementById('receiptImage');
            if (imgElement) {
                imgElement.src = imageUrl;
                imgElement.style.display = 'block';
            }
            
            // You could also store the image URL in a hidden input for form submission
            const hiddenInput = document.getElementById('receipt_image_url');
            if (hiddenInput) {
                hiddenInput.value = imageUrl;
            }
        };
    } else {
        console.warn('Not running inside Cordova app – native features disabled');
    }
});

// Function to call when user clicks "Take Photo" button
function takeReceiptPhoto(receiptId) {
    if (window.nativeBridge) {
        // Create a unique callback ID so we know which request this is
        var callbackId = 'photo_' + receiptId + '_' + Date.now();
        window.nativeBridge.takePhoto(callbackId);
    } else {
        alert('Camera not available. Please use the mobile app.');
    }
}
</script>

    <!-- Bottom Navigation -->
    <div class="bottom-navigation">
        <a href="dashboard_agent.php" class="nav-item">
            <span class="material-symbols-outlined">home</span>
            <span>Dashboard</span>
        </a>
        <a href="submissions_agent.php" class="nav-item">
            <span class="material-symbols-outlined">receipt_long</span>
            <span>Submissions</span>
        </a>
        <a href="store.php" class="nav-item <?php echo !$no_assignments ? 'active' : ''; ?>">
            <span class="material-symbols-outlined">storefront</span>
            <span>Store</span>
        </a>
    </div>
</body>
</html>