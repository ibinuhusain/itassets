<?php
// Prevent CORS issues and set JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Database credentials - Update these!
if (file_exists('db_value.php')) {
    require_once 'db_value.php';
}


try {
    // Connect to MySQL
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if file was uploaded without errors
    if (!isset($_FILES['masterFile']) || $_FILES['masterFile']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or file upload error.');
    }

    // Check file extension
    $fileName = $_FILES['masterFile']['name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($fileExt !== 'csv') {
        throw new Exception('Please save your Excel file as a .csv before uploading.');
    }

    // Calculate Expiration Date
    $graceDays = isset($_POST['gracePeriod']) ? (int)$_POST['gracePeriod'] : 30;
    $expiresAt = date('Y-m-d H:i:s', strtotime("+$graceDays days"));

    $fileTmpPath = $_FILES['masterFile']['tmp_name'];

    // Begin Database Transaction for massive speed boost
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO item_master (barcode, item_description, location_code, price, price_arabic, expires_at) VALUES (?, ?, ?, ?, ?, ?)");

    // Open and parse the CSV
    $handle = fopen($fileTmpPath, "r");
    $header = fgetcsv($handle); // Skip the first header row
    $count = 0;

    while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
        // Map data based on your exact column order
        // 1=Location Code, 3=Barcode, 4=Description, 10=Now Price, 16=Now Price Arabic
        $locCode  = isset($data[1]) ? $data[1] : '';
        $barcode  = isset($data[3]) ? $data[3] : '';
        $desc     = isset($data[4]) ? $data[4] : '';
        $price    = isset($data[10]) ? $data[10] : 0;
        $price_ar = isset($data[16]) ? $data[16] : '';

        // Only insert if there is a barcode and location code
        if (!empty($barcode) && !empty($locCode)) {
            $stmt->execute([$barcode, $desc, $locCode, $price, $price_ar, $expiresAt]);
            $count++;
        }
    }
    fclose($handle);
    $pdo->commit(); // Commit all 20,000 rows at once

    echo json_encode([
        'success' => true, 
        'total_items' => $count,
        'message' => 'CSV processed and inserted successfully.'
    ]);

} catch (Exception $e) {
    // Rollback the database if anything crashes
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>