<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");

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

$method = $_SERVER['REQUEST_METHOD'];

function getMerchantId($conn, $username) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = :usr AND role = 'merchant'");
    $stmt->execute([':usr' => $username]);
    return $stmt->fetchColumn();
}

if ($method === 'GET') {
    $merchant_user = $_GET['username'] ?? '';
    $action = $_GET['action'] ?? '';
    
    $m_id = getMerchantId($conn, $merchant_user);
    if(!$m_id) { 
        echo json_encode(["success" => false, "message" => "Invalid Merchant"]); 
        exit; 
    }

    // --- CSV EXPORT LOGIC ---
    if ($action === 'export') {
        // Change headers to force a file download instead of returning JSON
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Active_Master_Sheet_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        
        // Add a UTF-8 BOM so Excel reads Arabic characters correctly
        fputs($output, "\xEF\xBB\xBF");
        
        // Write headers
        fputcsv($output, ['Brand Name', 'Location Code', 'Location', 'Barcode', 'Item Description', 'Article Size', 'Style', 'QTY', 'Unit Price', 'Was Price', 'Now Price', 'Print Barcode', 'PromotionItem', 'PromotionType', 'DiscountPercentage', 'Was Price Arabic', 'Now Price Arabic', 'Expiration Date']);
        
        // Fetch all data for this merchant
        $stmt = $conn->prepare("SELECT brand_name, location_code, location_name, barcode, item_description, article_size, style, qty, unit_price, was_price, now_price, print_barcode, promotion_item, promotion_type, discount_percentage, was_price_arabic, now_price_arabic, expires_at FROM item_master WHERE merchant_id = ? ORDER BY id ASC");
        $stmt->execute([$m_id]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit; // Stop executing so we don't accidentally output JSON at the end
    }

    // --- NORMAL TABLE PAGINATION & SEARCH LOGIC ---
    $search = $_GET['search'] ?? '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $searchQuery = "";
    $params = [$m_id];
    
    if (!empty($search)) {
        $searchQuery = " AND (barcode LIKE ? OR item_description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM item_master WHERE merchant_id = ?" . $searchQuery);
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $limit) ?: 1;

    $stmt = $conn->prepare("SELECT barcode, item_description, was_price, now_price, location_code, expires_at FROM item_master WHERE merchant_id = ?" . $searchQuery . " ORDER BY id DESC LIMIT ? OFFSET ?");
    
    // Bind parameters manually 
    $paramIndex = 1;
    $stmt->bindValue($paramIndex++, $m_id, PDO::PARAM_INT);
    if (!empty($search)) {
        $stmt->bindValue($paramIndex++, "%$search%", PDO::PARAM_STR);
        $stmt->bindValue($paramIndex++, "%$search%", PDO::PARAM_STR);
    }
    $stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode([
        "success" => true, 
        "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
        "total_items" => $total_items,
        "total_pages" => $total_pages,
        "current_page" => $page
    ]);
} 
elseif ($method === 'POST') {
    $merchant_user = $_POST['merchant_username'] ?? '';
    $grace_period = $_POST['gracePeriod'] ?? 30;
    $m_id = getMerchantId($conn, $merchant_user);

    if (!$m_id) { echo json_encode(["success" => false, "message" => "Invalid Merchant."]); exit; }
    
    if (isset($_FILES['masterFile']) && $_FILES['masterFile']['error'] == 0) {
        $fileName = $_FILES['masterFile']['name'];
        $tmpName  = $_FILES['masterFile']['tmp_name'];
        
        if (($handle = fopen($tmpName, "r")) !== FALSE) {
            $conn->beginTransaction();
            try {
                $conn->prepare("DELETE FROM item_master WHERE merchant_id = ?")->execute([$m_id]);

                $query = "INSERT INTO item_master 
                          (merchant_id, brand_name, location_code, location_name, barcode, item_description, article_size, style, qty, unit_price, was_price, now_price, print_barcode, promotion_item, promotion_type, discount_percentage, was_price_arabic, now_price_arabic, expires_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                
                $row = 0;
                $items_processed = 0;
                $expires_at = date('Y-m-d', strtotime("+$grace_period days"));

                while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
                    $row++;
                    if ($row == 1) continue; 
                    
                    if (count($data) >= 17) { 
                        $stmt->execute([
                            $m_id, 
                            trim($data[0]), trim($data[1]), trim($data[2]), trim($data[3]), 
                            trim($data[4]), trim($data[5]), trim($data[6]), (int)$data[7], 
                            (float)$data[8], (float)$data[9], (float)$data[10], trim($data[11]), 
                            trim($data[12]), trim($data[13]), trim($data[14]), trim($data[15]), 
                            trim($data[16]), $expires_at
                        ]);
                        $items_processed++;
                    }
                }
                fclose($handle);

                $log = $conn->prepare("INSERT INTO upload_logs (merchant_id, filename, total_items) VALUES (?, ?, ?)");
                $log->execute([$m_id, $fileName, $items_processed]);

                $conn->commit();
                echo json_encode(["success" => true, "total_items" => $items_processed, "message" => "File processed successfully! $items_processed items updated."]);
            } catch (Exception $e) {
                $conn->rollBack();
                echo json_encode(["success" => false, "message" => "Failed to process file. Ensure your CSV exactly matches the 17 column format."]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Cannot read file."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "No file uploaded."]);
    }
}
?>