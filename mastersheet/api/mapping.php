<?php
// Turn on error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");

require_once '/home/anomakio/apparelgroup.anomak.co.in/config.php';

// --- CONNECTION 1: Main App Database ---
try {
    $conn = getConnection(); 
} catch(PDOException $exception) {
    echo json_encode(["success" => false, "message" => "Main DB Connection failed: " . $exception->getMessage()]);
    exit;
}

// --- CONNECTION 2: Retail Audit Database (Crucial for merchant_items.php) ---
try {
    $audit_conn = new PDO(
        "mysql:host=localhost;dbname=anomakio_retail_audit_db;charset=utf8", 
        "anomakio_retail_audit", 
        "Q_HR}6z=(jYR8%mi"
    );
    $audit_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $exception) {
    echo json_encode(["success" => false, "message" => "Audit DB Connection failed: " . $exception->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $merchants = $conn->query("SELECT id, username, full_name FROM master_users WHERE app_permissions LIKE '%\"merchant\"%' ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
        $app_users = $conn->query("SELECT id, username, full_name FROM master_users WHERE app_permissions LIKE '%\"app_user\"%' ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

        $brands = $audit_conn->query("SELECT id, name FROM brands ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $app_user_brands = $audit_conn->query("SELECT id as mapping_id, user_id, brand_id FROM app_user_brands")->fetchAll(PDO::FETCH_ASSOC);

        // Map safely in PHP
        $raw_mappings = $audit_conn->query("
            SELECT mb.id, mb.merchant_id, b.name as brand 
            FROM merchant_brands mb 
            JOIN brands b ON mb.brand_id = b.id
        ")->fetchAll(PDO::FETCH_ASSOC);

        $merchant_lookup = [];
        foreach ($merchants as $m) { $merchant_lookup[$m['id']] = $m['username']; }
        
        $mappings = [];
        foreach ($raw_mappings as $row) {
            if (isset($merchant_lookup[$row['merchant_id']])) {
                $mappings[] = [
                    'id'       => $row['id'],
                    'merchant' => $merchant_lookup[$row['merchant_id']],
                    'brand'    => $row['brand']
                ];
            }
        }
        usort($mappings, function($a, $b) { return strcmp($a['merchant'], $b['merchant']); });

        echo json_encode([
            "success" => true, "merchants" => $merchants, "brands" => $brands, 
            "mappings" => $mappings, "app_users" => $app_users, "app_user_brands" => $app_user_brands
        ]);
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "SQL Error: " . $e->getMessage()]);
    }
} 
elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (isset($data->action)) {
        
        // ALL mapping inserts go back to $audit_conn so merchant_items.php can see them!
        if ($data->action === 'remove_mapping' && !empty($data->mapping_id)) {
            $stmt = $audit_conn->prepare("DELETE FROM merchant_brands WHERE id = :id");
            if($stmt->execute([':id' => $data->mapping_id])) echo json_encode(["success" => true, "message" => "Mapping removed."]);
            else echo json_encode(["success" => false, "message" => "Failed to remove mapping."]);
            exit;
        }
        elseif ($data->action === 'map_brand' && !empty($data->merchant_id) && !empty($data->brand_id)) {
            $stmt = $audit_conn->prepare("INSERT INTO merchant_brands (merchant_id, brand_id) VALUES (:m_id, :b_id)");
            try {
                $stmt->execute([':m_id' => $data->merchant_id, ':b_id' => $data->brand_id]);
                echo json_encode(["success" => true, "message" => "Successfully mapped brand."]);
            } catch (PDOException $e) {
                // If this fails, we will now see EXACTLY why in the Javascript alert!
                echo json_encode(["success" => false, "message" => "SQL Error: " . $e->getMessage()]);
            }
            exit;
        }

        if ($data->action === 'assign_app_user_brand' && !empty($data->user_id) && !empty($data->brand_id)) {
            $stmt = $audit_conn->prepare("INSERT INTO app_user_brands (user_id, brand_id) VALUES (:u_id, :b_id)");
            try {
                $stmt->execute([':u_id' => $data->user_id, ':b_id' => $data->brand_id]);
                echo json_encode(["success" => true, "message" => "Brand assigned to App User!"]);
            } catch (PDOException $e) {
                echo json_encode(["success" => false, "message" => "SQL Error: " . $e->getMessage()]);
            }
            exit;
        }
        elseif ($data->action === 'remove_app_user_brand' && !empty($data->mapping_id)) {
            $stmt = $audit_conn->prepare("DELETE FROM app_user_brands WHERE id = :id");
            if($stmt->execute([':id' => $data->mapping_id])) echo json_encode(["success" => true, "message" => "Brand access revoked."]);
            else echo json_encode(["success" => false, "message" => "Failed to remove access."]);
            exit;
        }

        if ($data->action === 'add_brand' && !empty($data->brand_name)) {
            try {
                $stmt = $audit_conn->prepare("INSERT INTO brands (name) VALUES (:name)");
                $stmt->execute([':name' => $data->brand_name]);
                
                $clean_username = !empty($data->brand_code) ? strtolower(trim($data->brand_code)) : strtolower(str_replace([' ', '-'], '_', trim($data->brand_name)));
                $auto_password = 'App@' . $clean_username;
                $hashed = password_hash($auto_password, PASSWORD_DEFAULT);
                $default_permissions = json_encode(["labeller" => ["app_user"]]);
                
                $user_stmt = $conn->prepare("INSERT IGNORE INTO master_users (username, password_hash, app_permissions, full_name) VALUES (:user, :pass, :perms, :fname)");
                $user_stmt->execute([':user' => $clean_username, ':pass' => $hashed, ':perms' => $default_permissions, ':fname' => $data->brand_name . " Auditor"]);
                
                echo json_encode(["success" => true, "message" => "Brand created!"]);
            } catch (PDOException $e) {
                echo json_encode(["success" => false, "message" => "SQL Error: " . $e->getMessage()]);
            }
            exit;
        } 
    }
}
?>