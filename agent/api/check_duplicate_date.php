<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
ini_set('display_errors', 0); error_reporting(0);

require_once 'db.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "DB Connection Failed"]));
}

$shop_id   = $_GET['shop_id'] ?? '';
$sale_date = $_GET['sale_date'] ?? '';

if(empty($shop_id) || empty($sale_date)) {
    echo json_encode(["status" => "error", "message" => "Missing shop_id or sale_date"]);
    exit();
}

// Check if a record already exists for this shop on this specific sale date
$stmt = $conn->prepare("SELECT id FROM shop_visits WHERE shop_id = ? AND sale_date = ? LIMIT 1");
if($stmt) {
    // 'i' for integer (shop_id), 's' for string (sale_date)
    $stmt->bind_param("is", $shop_id, $sale_date);
    $stmt->execute();
    $stmt->store_result();
    
    if($stmt->num_rows > 0) {
        // Duplicate found!
        echo json_encode(["status" => "success", "is_duplicate" => true]);
    } else {
        // Clear to proceed
        echo json_encode(["status" => "success", "is_duplicate" => false]);
    }
    
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "SQL Error"]);
}

$conn->close();
?>