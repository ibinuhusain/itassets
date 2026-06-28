<?php
require_once 'config.php';
require_once 'auth.php';

session_start();
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check file upload
if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

// Get receipt_id from POST (may be 0 for bank or pre-save uploads)
$receiptId = isset($_POST['receipt_id']) ? intval($_POST['receipt_id']) : 0;

// Validate file type
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['receipt']['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Only JPEG/PNG images are allowed']);
    exit;
}

// Limit file size (5MB)
if ($_FILES['receipt']['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large (max 5MB)']);
    exit;
}

// If receipt_id > 0, verify ownership and update the record
if ($receiptId > 0) {
    try {
        $pdo = getDbConnection();
        // Check if record exists and belongs to logged-in agent
        $stmt = $pdo->prepare("SELECT agent_id FROM receipt_details WHERE receipt_id = ?");
        $stmt->execute([$receiptId]);
        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Receipt record not found']);
            exit;
        }

        if ($row['agent_id'] != $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'You are not authorized to update this receipt']);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error during verification']);
        exit;
    }
}

// Generate unique filename
$extension = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
if ($receiptId > 0) {
    $filename = 'receipt_' . $receiptId . '_' . time() . '.' . $extension;
} else {
    // For bank uploads or pre-save store photos
    $filename = 'temp_' . time() . '_' . uniqid() . '.' . $extension;
}

// Define upload directory (create if not exists)
$uploadDir = dirname(__DIR__) . '/uploads/receipts/'; // Adjust path as needed
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destination = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

// Construct public URL
$baseUrl = 'https://aquamarine-mule-238491.hostingersite.com/uploads/receipts/';
$imageUrl = $baseUrl . $filename;

// If receipt_id > 0, update the receipt_images column in receipt_details
if ($receiptId > 0) {
    try {
        $pdo = getDbConnection();
        // If you want to append multiple images, modify this to merge JSON arrays.
        // For simplicity, we overwrite with the new URL.
        $stmt = $pdo->prepare("UPDATE receipt_details SET receipt_images = ? WHERE receipt_id = ?");
        $stmt->execute([$imageUrl, $receiptId]);

        if ($stmt->rowCount() === 0) {
            unlink($destination);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update record']);
            exit;
        }
    } catch (Exception $e) {
        unlink($destination);
        http_response_code(500);
        echo json_encode(['error' => 'Database error during update']);
        exit;
    }
}

// Return success response
echo json_encode(['success' => true, 'imageUrl' => $imageUrl]);