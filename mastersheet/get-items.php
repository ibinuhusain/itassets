<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Database credentials - Update these!
if (file_exists('db_value.php')) {
    require_once 'db_value.php';
}


try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get current page from the URL (default to 1)
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $itemsPerPage = 50;
    
    // Calculate the SQL OFFSET
    $offset = ($page - 1) * $itemsPerPage;

    // 1. Get the total number of items to calculate total pages
    $countStmt = $pdo->query("SELECT COUNT(*) FROM item_master");
    $totalItems = $countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $itemsPerPage);

    // 2. Fetch only the 50 items for the current page
    $stmt = $pdo->prepare("SELECT barcode, item_description, location_code, price, expires_at FROM item_master ORDER BY id DESC LIMIT :limit OFFSET :offset");
    
    // PDO requires bindValue for LIMIT/OFFSET when emulate prepares is on
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $results,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_items' => $totalItems
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>