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

if ($method === 'GET') {
    // Standard Merchant & Brand Data
    $merchants = $conn->query("SELECT id, username FROM users WHERE role = 'merchant' AND status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
    $brands = $conn->query("SELECT id, name FROM brands ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Merchant to Brand Mappings
    $query = "SELECT mb.id, u.username as merchant, b.name as brand 
              FROM merchant_brands mb
              JOIN users u ON mb.merchant_id = u.id
              JOIN brands b ON mb.brand_id = b.id
              ORDER BY u.username ASC";
    $mappings = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);

    // App User to Brand Mappings (NEW)
    $app_users = $conn->query("SELECT id, username FROM users WHERE role = 'app_user' AND status = 'active' ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Auto-create table if missing to prevent crashes
    $conn->exec("CREATE TABLE IF NOT EXISTS app_user_brands (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        user_id INT NOT NULL, 
        brand_id INT NOT NULL, 
        UNIQUE KEY unique_mapping (user_id, brand_id)
    )");
    $app_user_brands = $conn->query("SELECT id as mapping_id, user_id, brand_id FROM app_user_brands")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true, 
        "merchants" => $merchants, 
        "brands" => $brands, 
        "mappings" => $mappings,
        "app_users" => $app_users,
        "app_user_brands" => $app_user_brands
    ]);
} 
elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (isset($data->action)) {
        
        // --- MERCHANT BRAND MAPPING ---
        if ($data->action === 'remove_mapping' && !empty($data->mapping_id)) {
            $stmt = $conn->prepare("DELETE FROM merchant_brands WHERE id = :id");
            if($stmt->execute([':id' => $data->mapping_id])) echo json_encode(["success" => true, "message" => "Mapping removed."]);
            else echo json_encode(["success" => false, "message" => "Failed to remove mapping."]);
            exit;
        }
        elseif ($data->action === 'map_brand' && !empty($data->merchant_id) && !empty($data->brand_id)) {
            $stmt = $conn->prepare("INSERT INTO merchant_brands (merchant_id, brand_id) VALUES (:m_id, :b_id)");
            try {
                $stmt->execute([':m_id' => $data->merchant_id, ':b_id' => $data->brand_id]);
                echo json_encode(["success" => true, "message" => "Successfully mapped brand."]);
            } catch (PDOException $e) {
                echo json_encode(["success" => false, "message" => "Mapping already exists."]);
            }
            exit;
        }

        // --- APP USER BRAND MAPPING (NEW) ---
        if ($data->action === 'assign_app_user_brand' && !empty($data->user_id) && !empty($data->brand_id)) {
            $stmt = $conn->prepare("INSERT INTO app_user_brands (user_id, brand_id) VALUES (:u_id, :b_id)");
            try {
                $stmt->execute([':u_id' => $data->user_id, ':b_id' => $data->brand_id]);
                echo json_encode(["success" => true, "message" => "Brand assigned to App User!"]);
            } catch (PDOException $e) {
                echo json_encode(["success" => false, "message" => "App User already has this brand."]);
            }
            exit;
        }
        elseif ($data->action === 'remove_app_user_brand' && !empty($data->mapping_id)) {
            $stmt = $conn->prepare("DELETE FROM app_user_brands WHERE id = :id");
            if($stmt->execute([':id' => $data->mapping_id])) echo json_encode(["success" => true, "message" => "Brand access revoked."]);
            else echo json_encode(["success" => false, "message" => "Failed to remove access."]);
            exit;
        }

        // --- ADD BRAND ---
        if ($data->action === 'add_brand' && !empty($data->brand_name)) {
            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare("INSERT INTO brands (name) VALUES (:name)");
                $stmt->execute([':name' => $data->brand_name]);
                
                if (!empty($data->brand_code)) {
                    $clean_username = strtolower(trim($data->brand_code));
                } else {
                    $clean_username = strtolower(str_replace([' ', '-'], '_', trim($data->brand_name)));
                }

                $auto_password = 'App@' . $clean_username;
                $hashed = password_hash($auto_password, PASSWORD_DEFAULT);
                
                $user_stmt = $conn->prepare("INSERT IGNORE INTO users (username, password_hash, role) VALUES (:user, :pass, 'app_user')");
                $user_stmt->execute([':user' => $clean_username, ':pass' => $hashed]);
                
                $conn->commit();
                echo json_encode(["success" => true, "message" => "Brand created! App User generated: [$clean_username / $auto_password]"]);
            } catch (PDOException $e) {
                $conn->rollBack();
                echo json_encode(["success" => false, "message" => "Brand already exists or error occurred."]);
            }
            exit;
        } 
    }
}
?>