<?php
// Force CORS headers FIRST
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// --- SILENT DEBUG TRACKER (Does not affect JSON output) ---
$req_method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$req_origin = $_SERVER['HTTP_ORIGIN'] ?? 'NO_ORIGIN (Legacy App)';
$log_entry = date('Y-m-d H:i:s') . " | Method: $req_method | Origin: $req_origin | IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
file_put_contents(__DIR__ . '/network_debug_log.txt', $log_entry, FILE_APPEND);
// ----------------------------------------------------------




// Catch absolutely everything
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $host = "localhost";
    $db_name = "anomakio_retail_audit_db";
    $username = "anomakio_retail_audit";
    $password = "Q_HR}6z=(jYR8%mi";

    $conn = new mysqli($host, $username, $password, $db_name);
    $conn->set_charset("utf8mb4");

    $location_code = isset($_GET['location']) ? $_GET['location'] : '';

    if(empty($location_code)) {
        echo json_encode(["error" => "No location provided"]);
        exit();
    }

    // FIX: Replaced 'promotion_pct' with 'discount_percentage' based on your schema
    $sql = "SELECT 
                barcode, 
                brand_name, 
                item_description, 
                was_price, 
                now_price,
                unit_price, 
                promotion_type, 
                discount_percentage 
            FROM item_master 
            WHERE location_code = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $location_code);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while($row = $result->fetch_assoc()) {
        // Map the database columns to exactly what the mobile app expects
        $products[] = [
            "barcode" => $row["barcode"],
            "brand" => $row["brand_name"],
            "desc" => $row["item_description"],
            "was" => $row["was_price"],
            // Use now_price, but if it's null/empty, fallback to unit_price
            "now" => !empty($row["now_price"]) ? $row["now_price"] : $row["unit_price"],
            "p_type" => $row["promotion_type"],
            "p_pct" => $row["discount_percentage"] // The fix!
        ];
    }

    echo json_encode($products);
    
    $stmt->close();
    $conn->close();

} catch (Throwable $e) {
    http_response_code(200); 
    echo json_encode([
        "error" => "Fatal Crash Detected", 
        "details" => $e->getMessage(),
        "line" => $e->getLine()
    ]);
}
?>