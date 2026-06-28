<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$host = "localhost";
$db_name = "anomakio_retail_audit_db";
$username = "anomakio_retail_audit";
$password = "Q_HR}6z=(jYR8%mi";

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $exception) {
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit;
}

// --- NEW: FULL ADMIN EXPORT ---
if (isset($_GET['action']) && $_GET['action'] === 'export_all') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Global_Active_Master_Sheet_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for Arabic support in Excel
    fputs($output, "\xEF\xBB\xBF");
    
    // Headers (Added 'Merchant Account' so Admin knows whose data it is)
    fputcsv($output, ['Merchant Account', 'Brand Name', 'Location Code', 'Location', 'Barcode', 'Item Description', 'Article Size', 'Style', 'QTY', 'Unit Price', 'Was Price', 'Now Price', 'Print Barcode', 'PromotionItem', 'PromotionType', 'DiscountPercentage', 'Was Price Arabic', 'Now Price Arabic', 'Expiration Date']);
    
    // Fetch all active data joined with the merchant's username
    $query = "SELECT u.username, i.brand_name, i.location_code, i.location_name, i.barcode, i.item_description, i.article_size, i.style, i.qty, i.unit_price, i.was_price, i.now_price, i.print_barcode, i.promotion_item, i.promotion_type, i.discount_percentage, i.was_price_arabic, i.now_price_arabic, i.expires_at 
              FROM item_master i 
              JOIN users u ON i.merchant_id = u.id 
              ORDER BY u.username ASC, i.id ASC";
              
    $stmt = $conn->query($query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// --- EXISTING LOGS FETCH ---
try {
    $query = "SELECT ul.id, ul.filename, ul.total_items, ul.uploaded_at, u.username as merchant_name 
              FROM upload_logs ul 
              LEFT JOIN users u ON ul.merchant_id = u.id 
              ORDER BY ul.uploaded_at DESC";
              
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch(PDOException $exception) {
    echo json_encode(["success" => false, "message" => "Database error."]);
}
?>