<?php
// 1. FORCE THE API TO READ THE MASTER DOMAIN COOKIE
ini_set('session.cookie_path', '/');
ini_set('session.use_strict_mode', 1);
session_name('PHPSESSID');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. BLOCK UNAUTHORIZED API CALLS
if (!isset($_SESSION['iam_user'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized API Access. Master session missing.']);
    exit;
}

// 3. TRANSLATE THE MASTER SESSION FOR THE LEGACY API (IT SPECIFIC)
$po_roles = $_SESSION['iam_user']['roles']['it_procurement'] ?? ['user'];
$primary_role = !empty($po_roles) ? $po_roles[0] : 'user';

$_SESSION['user_id']   = $_SESSION['iam_user']['id'];
$_SESSION['id']        = $_SESSION['iam_user']['id'];
$_SESSION['username']  = $_SESSION['iam_user']['username'];
$_SESSION['name']      = $_SESSION['iam_user']['name'];
$_SESSION['role']      = $primary_role;
$_SESSION['user_role'] = $primary_role;

error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json');
require_once 'db.php';
global $conn;

if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]); exit;
}
$conn->set_charset("utf8mb4");

function logAction($conn, $user, $action, $details) {
    $stmt = $conn->prepare("INSERT INTO ict_audit_logs (username, action_type, details) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $user, $action, $details);
    $stmt->execute();
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $user = $data['username'] ?? $_GET['username'] ?? 'System';

    try {
        if ($action === 'bulk_import_it_assets') {
            $assets = $data['assets'] ?? [];
            $imported = 0;
            
            $stmt = $conn->prepare("INSERT INTO it_assets (hostname, brand_name, store_code, store_name, device_type, model_name, serial_number, os, memory) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE brand_name=VALUES(brand_name), store_code=VALUES(store_code), store_name=VALUES(store_name), device_type=VALUES(device_type), model_name=VALUES(model_name), serial_number=VALUES(serial_number), os=VALUES(os), memory=VALUES(memory)");
            
            foreach ($assets as $a) {
                $stmt->bind_param("sssssssss", 
                    $a['hostname'], $a['brandName'], $a['storeCode'], $a['storeName'], 
                    $a['deviceType'], $a['modelName'], $a['serialNumber'], $a['osInfo'], $a['specs']
                );
                if($stmt->execute()) $imported++;
            }
            logAction($conn, $user, 'IT_ASSETS_IMPORT', "Imported/Updated $imported IT Assets via Excel.");
            echo json_encode(["status" => "success", "imported" => $imported]); exit;
        }

        if ($action === 'initiate_asset_recovery') {
            $originStore = $data['originStore'];
            $deviceIssued = $data['deviceIssued'];
            $qty = (int)$data['qty'];
            $sku = $data['sku'] ?? 'N/A';
            
            $stmt = $conn->prepare("INSERT INTO ict_asset_recovery (origin_store, device_issued, qty, sku, category, hardware_type, model_number, serial_number, action_type, remarks, status, username, created_at) VALUES (?, ?, ?, ?, 'ICT', 'Asset', 'N/A', 'N/A', 'Reuse', 'Initiated from transit', 'Partial', ?, NOW())");
            $stmt->bind_param("ssiss", $originStore, $deviceIssued, $qty, $sku, $user);
            
            if ($stmt->execute()) {
                logAction($conn, $user, 'ASSET_RECOVERY_INITIATED', "Store closure return initiated for $qty x $deviceIssued from $originStore");
                echo json_encode(["status" => "success", "id" => $conn->insert_id]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to initiate recovery."]);
            }
            exit;
        }

        if ($action === 'bulk_asset_recovery') {
            $rows = $data['payload'];
            
            $stmtLog = $conn->prepare("INSERT INTO ict_asset_recovery 
                (origin_store, device_issued, qty, sku, category, hardware_type, model_number, serial_number, action_type, remarks, status, username, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Complete', ?, NOW())");
            
            // FULLY FIXED: Removed unit_price and 0 to match DB schema exactly
            $stmtInv = $conn->prepare("INSERT INTO ict_inventory (sku, category, item_name, model_number, quantity_on_hand) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity_on_hand = quantity_on_hand + ?");
            
            foreach ($rows as $row) {
                $storeId = $row['storeId'] ?? 'Unknown';
                $category = $row['category'] ?? 'ICT';
                $hwType = $row['hwType'] ?? 'Asset';
                $device = $row['itemName'] ?? 'System';
                $model = $row['modelNo'] ?? 'N/A';
                $serial = $row['serialNo'] ?? 'N/A';
                $qty = (int)($row['qty'] ?? 1);
                $actionType = $row['action'] ?? 'Reuse';
                $remarks = $row['remarks'] ?? '';
                
                $sku = ($serial !== 'N/A' && !empty($serial)) ? $serial : 'REV-' . rand(100000, 999999);
                
                $stmtLog->bind_param("ssissssssss", $storeId, $device, $qty, $sku, $category, $hwType, $model, $serial, $actionType, $remarks, $user);
                $stmtLog->execute();
                
                if (strtolower(trim($actionType)) === 'reuse') {
                    $stmtInv->bind_param("ssssii", $sku, $category, $device, $model, $qty, $qty);
                    $stmtInv->execute();
                }
            }
            logAction($conn, $user, 'BULK_RECOVERY', "Bulk recovery processed for " . count($rows) . " items.");
            echo json_encode(["status" => "success"]); exit;
        }

        if ($action === 'bulk_store_provision') {
            $rows = $data['payload'];
            $dId = 'DSP-BLK-' . time();
            
            $stmtDisp = $conn->prepare("INSERT INTO ict_dispatch_logs (dispatch_id, sku, item_name, dispatch_qty, store_code, person_name, username) VALUES (?, ?, ?, ?, ?, 'N/A', ?)");
            $stmtAsset = $conn->prepare("INSERT INTO it_assets (store_code, device_type, model_name, serial_number) VALUES (?, ?, ?, ?)");
            
            foreach ($rows as $row) {
                $storeId = $row['store id'] ?? $row['Store ID'];
                $hwType = $row['hardware type'] ?? $row['Hardware Type'];
                $poNum = $row['po number'] ?? $row['PO Number'];
                $itemName = $row['item name'] ?? $row['Item Name'];
                $serial = $row['serial number'] ?? $row['Serial Number'] ?? 'N/A';
                $qty = (int)($row['quantity'] ?? $row['Quantity'] ?? 1);
                
                $stmtDisp->bind_param("sssiss", $dId, $serial, $itemName, $qty, $storeId, $user);
                $stmtDisp->execute();
                
                $stmtAsset->bind_param("ssss", $storeId, $hwType, $itemName, $serial);
                $stmtAsset->execute();
            }
            logAction($conn, $user, 'BULK_PROVISION', "Bulk assigned new assets to stores via file.");
            echo json_encode(["status" => "success"]); exit;
        }

        if ($action === 'receive_asset_recovery') {
            $logId = (int)$data['id'];
            $stmt = $conn->prepare("UPDATE ict_asset_recovery SET status = 'Complete', username = ? WHERE id = ?");
            $stmt->bind_param("si", $user, $logId);
            
            if ($stmt->execute()) {
                logAction($conn, $user, 'ASSET_RECOVERY_RECEIVED', "Marked ICT recovery log $logId as Complete.");
                echo json_encode(["status" => "success"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to update recovery status."]);
            }
            exit;
        }

        if ($action === 'create_it_po') {
            $stmt = $conn->prepare("INSERT INTO ict_purchase_orders (
                po_number, delivery_note_no, invoice_number, assigned_store, assigned_brand, 
                category, hardware_type, item_name, item_description, model_number, price, order_qty, 
                receive_qty, serial_number, vendor_name, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'Pending')");
            
            foreach($data['po_records'] as $item) {
                $stmt->bind_param("ssssssssssdiss", 
                    $item['po_number'], $item['delivery_note_no'], $item['invoice_number'], 
                    $item['assigned_store'], $item['assigned_brand'],
                    $item['category'], $item['hardware_type'], $item['item_name'], 
                    $item['item_description'], $item['model_number'], 
                    $item['price'], $item['order_qty'], $item['serial_number'], $item['vendor_name']
                );
                $stmt->execute();
            }
            logAction($conn, $user, 'CREATE_ICT_PO', "Created ICT PO");
            echo json_encode(["status" => "success"]);
            exit;
        }

        if ($action === 'create_it_dispatch') {
            try {
                $dId = $data['dispatchId']; $store = $data['storeCode'] ?? 'N/A'; $pName = $data['pName'] ?? 'N/A'; 
                $stmt = $conn->prepare("INSERT INTO ict_dispatch_logs (dispatch_id, sku, item_name, dispatch_qty, store_code, person_name, username) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtUpdate = $conn->prepare("UPDATE ict_inventory SET quantity_on_hand = quantity_on_hand - ? WHERE sku = ?");

                foreach ($data['items'] as $item) {
                    $sku = $item['sku']; $qty = $item['qty'];
                    $itemName = $item['item_name'] ?? 'Dispatched Asset';

                    $stmt->bind_param("sssiss", $dId, $sku, $itemName, $qty, $store, $pName, $user); 
                    $stmt->execute();
                    
                    $stmtUpdate->bind_param("is", $qty, $sku); 
                    $stmtUpdate->execute();
                }
                logAction($conn, $user, 'CREATE_ICT_DISPATCH', "Dispatched ICT Assets under: " . $dId);
                echo json_encode(["status" => "success"]);
            } catch (Exception $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
            exit;
        }

        if ($action === 'reject_store_request') {
            $reqId = $data['requestId']; $storeId = $data['storeId'];
            $stmt = $conn->prepare("UPDATE store_requests SET status = 'Rejected' WHERE id = ?");
            $stmt->bind_param("i", $reqId); $stmt->execute();
            logAction($conn, $user, 'REQUEST_REJECTED', "Rejected stock request from Store: " . $storeId);
            echo json_encode(["status" => "success"]); exit;
        }

        if ($action === 'process_reverse_logistics') {
            $storeId = $data['storeId']; 
            $items = $data['items'];
            
            $stmtLog = $conn->prepare("INSERT INTO ict_asset_recovery 
                (origin_store, device_issued, qty, sku, category, hardware_type, model_number, serial_number, action_type, remarks, status, username, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Complete', ?, NOW())");
            
            // FULLY FIXED: Removed unit_price and 0 to match DB schema exactly
            $stmtInv = $conn->prepare("INSERT INTO ict_inventory (sku, category, item_name, model_number, quantity_on_hand) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity_on_hand = quantity_on_hand + ?");

            foreach ($items as $item) {
                $name = $item['name']; 
                $qty = $item['qty']; 
                $act = $item['action']; 
                $rem = $item['remark'];
                $sku = $item['sku']; 
                $cat = $item['category']; 
                $hwType = $item['hardware_type'] ?? 'Asset';
                $model = $item['model'] ?? 'N/A';
                $serial = $item['serial'] ?? 'N/A';

                $stmtLog->bind_param("ssissssssss", $storeId, $name, $qty, $sku, $cat, $hwType, $model, $serial, $act, $rem, $user); 
                $stmtLog->execute();
                
                if (strtolower(trim($act)) === 'reuse') {
                    $stmtInv->bind_param("ssssii", $sku, $cat, $name, $model, $qty, $qty); 
                    $stmtInv->execute();
                }
            }
            logAction($conn, $user, 'REVERSE_LOGISTICS', "Processed " . count($items) . " items returned manually from Store: " . $storeId);
            echo json_encode(["status" => "success"]); exit;
        }

        if ($action === 'create_vendor') {
            $stmt = $conn->prepare("INSERT INTO vendors (company_name, tax_id, payment_terms, lead_time, contact_email, status, created_by) VALUES (?, ?, ?, ?, ?, 'Approved', ?)");
            $stmt->bind_param("ssssss", $data['company'], $data['taxId'], $data['terms'], $data['lead'], $data['email'], $user); $stmt->execute();
            logAction($conn, $user, 'CREATE_VENDOR', "Added new supplier: " . $data['company']);
            echo json_encode(["status" => "success"]); exit;
        }

        if ($action === 'create_personnel') {
            $stmt = $conn->prepare("INSERT INTO users_po (emp_id, emp_name, department, username, password_hash, role) VALUES (?, ?, ?, ?, '1234', 'Agent')");
            $stmt->bind_param("ssss", $data['uId'], $data['uName'], $data['uDept'], $data['uId']); $stmt->execute();
            logAction($conn, $user, 'CREATE_PERSONNEL', "Provisioned personnel: " . $data['uId']);
            echo json_encode(["status" => "success"]); exit;
        }

        if ($action === 'delete_po') {
            $poNum = $data['poNumber']; 
            $stmt = $conn->prepare("DELETE FROM ict_purchase_orders WHERE po_number = ?");
            $stmt->bind_param("s", $poNum);
            $stmt->execute();
            
            logAction($conn, $user, 'DELETE_ICT_PO', "Purged IT PO Number: " . $poNum);
            echo json_encode(["status" => "success"]); 
            exit;
        }
        
        if ($action === 'delete_vendor') {
            $id = $_GET['id']; 
            $conn->query("DELETE FROM vendors WHERE id = '$id'"); 
            logAction($conn, $user, 'DELETE_VENDOR', "Revoked and removed Vendor ID: " . $id);
            echo json_encode(["status" => "success"]); exit;
        }

        if ($action === 'delete_personnel') {
            $empId = $_GET['emp_id']; 
            $conn->query("DELETE FROM users_po WHERE emp_id = '$empId'"); 
            logAction($conn, $user, 'DELETE_PERSONNEL', "Revoked personnel access for ID: " . $empId);
            echo json_encode(["status" => "success"]); exit;
        }

    } catch (mysqli_sql_exception $e) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Database Integrity Block: " . $e->getMessage()]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
        exit;
    }
}

if ($method === 'GET') {
    if ($action === 'list_audit_logs') { 
        $res = $conn->query("SELECT * FROM ict_audit_logs ORDER BY timestamp DESC LIMIT 200"); 
        $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } 
        echo json_encode($data); exit; 
    }
    
    if ($action === 'list_it_assets') {
        $sql = "SELECT hostname, brand_name, store_code, store_name, mall_name, location, route_code, device_type, model_name, serial_number, os, os_version, memory, cpu_type FROM it_assets ORDER BY created_at DESC";
        $res = $conn->query($sql);
        $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; }
        echo json_encode($data); exit;
    }

    if ($action === 'list_personnel_assets') {
        $sql = "SELECT id, hostname, emp_id, emp_name, brand_dept, mobile_number, device_type, model_name, serial_number, os, `version`, memory, cpu_type, created_at, updated_at FROM person ORDER BY created_at DESC";
        $res = $conn->query($sql);
        if (!$res) { echo json_encode(["status" => "error", "message" => $conn->error]); exit; }
        $data = []; while ($row = $res->fetch_assoc()) { $data[] = $row; }
        echo json_encode($data); exit;
    }

    if ($action === 'list_it_inventory') { 
        $res = $conn->query("SELECT * FROM ict_inventory ORDER BY item_name ASC"); 
        $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } 
        echo json_encode($data); exit; 
    }

    if ($action === 'list_it_dispatch') { 
        $res = $conn->query("SELECT * FROM ict_dispatch_logs ORDER BY dispatched_at DESC"); 
        $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } 
        echo json_encode($data); exit; 
    }

    if ($action === 'list_it_asset_recovery') {
        $res = $conn->query("SELECT * FROM ict_asset_recovery ORDER BY created_at DESC");
        $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; }
        echo json_encode($data); exit;
    }

    if ($action === 'list_it_pos') {
        $res = $conn->query("SELECT * FROM ict_purchase_orders ORDER BY created_at DESC");
        $pos = [];
        while ($row = $res->fetch_assoc()) {
            $poNum = $row['po_number'];
            if (!isset($pos[$poNum])) {
                $pos[$poNum] = [
                    'id' => $poNum, 'po_number' => $poNum, 'vendor' => $row['vendor_name'],
                    'delivery_note_number' => $row['delivery_note_no'], 'invoice_number' => $row['invoice_number'],
                    'assigned_store' => $row['assigned_store'], 'status' => $row['status'],
                    'order_date' => date('Y-m-d', strtotime($row['created_at'])), 'grand_total' => 0, 'lineItems' => []
                ];
            }
            $pos[$poNum]['grand_total'] += ($row['price'] * $row['order_qty']);
            $pos[$poNum]['lineItems'][] = [
                'sku' => $row['serial_number'] ?: 'PENDING', 'category' => $row['category'],
                'hardware_type' => $row['hardware_type'] ?? 'Hardware',
                'itemName' => $row['item_name'], 'description' => $row['item_description'],
                'modelNumber' => $row['model_number'], 'quantity' => $row['order_qty'], 'received_quantity' => $row['receive_qty'], 'price' => $row['price']
            ];
        }
        echo json_encode(array_values($pos)); exit;
    }

    if ($action === 'login') {
        $user = $_GET['user'] ?? ''; $pass = $_GET['pass'] ?? '';
        $stmt = $conn->prepare("SELECT username, role FROM users_po WHERE username = ? AND password_hash = ?");
        $stmt->bind_param("ss", $user, $pass); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) { echo json_encode(["status" => "success", "user" => $res->fetch_assoc()]); exit; }

        $stmt2 = $conn->prepare("SELECT id, name, route_code FROM stores WHERE id = ? AND is_active = 1");
        $stmt2->bind_param("s", $user); $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($res2->num_rows > 0) {
            $storeData = $res2->fetch_assoc();
            if ($pass === ($storeData['id'] . "@aprlgrp")) {
                echo json_encode(["status" => "success", "user" => ["username" => $storeData['id'], "name" => $storeData['name'], "route_code" => $storeData['route_code'], "role" => "Store"]]); exit;
            }
        }
        echo json_encode(["status" => "error", "message" => "Invalid credentials or Store is inactive."]); exit;
    }

    if ($action === 'list_stores') { $res = $conn->query("SELECT * FROM stores WHERE is_active = 1 ORDER BY name ASC"); $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } echo json_encode($data); exit; }
    if ($action === 'list_store_requests') { $res = $conn->query("SELECT id, username, details, timestamp, status FROM store_requests ORDER BY timestamp DESC"); $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } echo json_encode($data); exit; }
    
    if ($action === 'list_inventory') { $res = $conn->query("SELECT * FROM inventory ORDER BY created_at DESC"); $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } echo json_encode($data); exit; }
    if ($action === 'list_dispatch') { $res = $conn->query("SELECT * FROM dispatch_logs ORDER BY dispatched_at DESC"); $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } echo json_encode($data); exit; }
    if ($action === 'list_vendors') { $res = $conn->query("SELECT * FROM vendors ORDER BY company_name ASC"); $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } echo json_encode($data); exit; }
    if ($action === 'list_personnel') { $res = $conn->query("SELECT emp_id, emp_name, department FROM users_po WHERE emp_id IS NOT NULL AND emp_id != '' ORDER BY emp_name ASC"); $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } echo json_encode($data); exit; }
}
?>