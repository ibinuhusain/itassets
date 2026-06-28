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

// 3. TRANSLATE THE MASTER SESSION FOR THE LEGACY API
$po_roles = $_SESSION['iam_user']['roles']['procurement'] ?? ['user'];
$primary_role = !empty($po_roles) ? $po_roles[0] : 'user';

// Flood the legacy variables so the API queries work flawlessly
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
    $stmt = $conn->prepare("INSERT INTO audit_logs (username, action_type, details) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $user, $action, $details);
    $stmt->execute();
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $user = $data['username'] ?? 'System';

    if ($action === 'reject_store_request') {
        $reqId = $data['requestId']; $storeId = $data['storeId'];
        
        // Ensure you added the status column in your DB: ALTER TABLE store_requests ADD COLUMN status VARCHAR(50) DEFAULT 'Pending';
        $stmt = $conn->prepare("UPDATE store_requests SET status = 'Rejected' WHERE id = ?");
        $stmt->bind_param("i", $reqId); 
        $stmt->execute();
        
        logAction($conn, $user, 'REQUEST_REJECTED', "Rejected stock request from Store: " . $storeId);
        echo json_encode(["status" => "success"]); exit;
    }

    if ($action === 'process_reverse_logistics') {
        $storeId = $data['storeId']; $items = $data['items'];
        $stmtLog = $conn->prepare("INSERT INTO reverse_logistics (store_id, item_name, qty, action_type, remarks, processed_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtInv = $conn->prepare("INSERT INTO inventory (sku, category, item_name, model_number, serial_number, quantity_on_hand, unit_price) VALUES (?, ?, ?, ?, 'N/A', ?, 0) ON DUPLICATE KEY UPDATE quantity_on_hand = quantity_on_hand + ?");

        foreach ($items as $item) {
            $name = $item['name']; $qty = $item['qty']; $act = $item['action']; $rem = $item['remark'];
            $sku = $item['sku']; $cat = $item['category']; $model = $item['model'];

            $stmtLog->bind_param("ssisss", $storeId, $name, $qty, $act, $rem, $user); $stmtLog->execute();
            
            if ($act === 'Reuse') {
                $stmtInv->bind_param("ssssii", $sku, $cat, $name, $model, $qty, $qty); 
                $stmtInv->execute();
            }
        }
        logAction($conn, $user, 'REVERSE_LOGISTICS', "Processed " . count($items) . " items returned from Store: " . $storeId);
        echo json_encode(["status" => "success"]); exit;
    }

    if ($action === 'create_po') {
        $id = $data['id']; $poNum = $data['poNumber']; $orderDate = $data['orderDate'];
        $dept = $data['department']; $vendor = $data['vendor']; 
        $subtotal = $data['subtotal']; $tax = $data['taxAmount']; $total = $data['grandTotal'];
        $delNote = $data['deliveryNote'] ?? null; $invNum = $data['invoiceNumber'] ?? null;
        $assignedStore = $data['assignedStore'] ?? null; $poCat = $data['poCategory'] ?? null;
        $dynAttr = json_encode($data['dynamicAttributes'] ?? new stdClass());

        if (!empty($delNote)) {
            $checkDel = $conn->prepare("SELECT id FROM purchase_orders WHERE delivery_note_number = ?");
            $checkDel->bind_param("s", $delNote); $checkDel->execute();
            if ($checkDel->get_result()->num_rows > 0) {
                echo json_encode(["status" => "error", "message" => "Delivery Note Number must be unique. '$delNote' already exists."]); exit;
            }
        }

        $stmt = $conn->prepare("INSERT INTO purchase_orders (id, po_number, order_date, department, vendor, subtotal, tax_amount, grand_total, status, delivery_note_number, invoice_number, assigned_store, po_category, dynamic_attributes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Approved', ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssdddssssss", $id, $poNum, $orderDate, $dept, $vendor, $subtotal, $tax, $total, $delNote, $invNum, $assignedStore, $poCat, $dynAttr, $user);
        
        if ($stmt->execute()) {
            foreach ($data['lineItems'] as $item) {
                $stmtLine = $conn->prepare("INSERT INTO line_items (po_id, sku, description, quantity, unit_price, category, item_name, model_number, serial_number, received_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
                $stmtLine->bind_param("sssidssss", $id, $item['sku'], $item['description'], $item['quantity'], $item['unitPrice'], $item['category'], $item['itemName'], $item['modelNumber'], $item['serialNumber']); $stmtLine->execute();

                $checkInv = $conn->prepare("SELECT id FROM inventory WHERE sku = ?"); $checkInv->bind_param("s", $item['sku']); $checkInv->execute();
                if ($checkInv->get_result()->num_rows === 0) {
                    $insertInv = $conn->prepare("INSERT INTO inventory (sku, category, item_name, model_number, serial_number, quantity_on_hand, unit_price) VALUES (?, ?, ?, ?, ?, 0, ?)");
                    $insertInv->bind_param("sssssd", $item['sku'], $item['category'], $item['itemName'], $item['modelNumber'], $item['serialNumber'], $item['unitPrice']); $insertInv->execute();
                }
            }
            echo json_encode(["status" => "success"]);
        } else { echo json_encode(["status" => "error", "message" => "Database write failed."]); }
        exit;
    }

    if ($action === 'receive_po') {
        $poId = $data['poId'];
        $items = $data['items'];
        $allCompleted = true;
        $anyReceived = false;

        foreach ($items as $item) {
            if ($item['recv_qty'] > 0) {
                $stmt = $conn->prepare("UPDATE line_items SET received_quantity = received_quantity + ? WHERE po_id = ? AND sku = ?");
                $stmt->bind_param("iss", $item['recv_qty'], $poId, $item['sku']); $stmt->execute();

                $anyReceived = true;
                $stmtInv = $conn->prepare("UPDATE inventory SET quantity_on_hand = quantity_on_hand + ? WHERE sku = ?");
                $stmtInv->bind_param("is", $item['recv_qty'], $item['sku']); $stmtInv->execute();
            }

            $check = $conn->query("SELECT quantity, received_quantity FROM line_items WHERE po_id = '$poId' AND sku = '{$item['sku']}'")->fetch_assoc();
            if ($check['received_quantity'] < $check['quantity']) { $allCompleted = false; }
        }

        $newStatus = $allCompleted ? 'Completed' : ($anyReceived ? 'Partial' : 'Approved');
        $conn->query("UPDATE purchase_orders SET status = '$newStatus' WHERE id = '$poId'");
        logAction($conn, $user, 'RECEIVE_PO', "Received items for PO: $poId. Status updated to: $newStatus");
        echo json_encode(["status" => "success"]); exit;
    }

    if ($action === 'batch_import_stores') {
        $rows = $data['stores']; $imported = 0;
        $stmt = $conn->prepare("INSERT INTO stores (id, name, brand, brand_code, mall, entity, city, country, route_code) VALUES (?, ?, ?, ?, ?, ?, ?, 'KSA', ?) ON DUPLICATE KEY UPDATE name=VALUES(name), brand=VALUES(brand), brand_code=VALUES(brand_code), mall=VALUES(mall), entity=VALUES(entity), city=VALUES(city), route_code=VALUES(route_code)");
        foreach ($rows as $row) {
            $stmt->bind_param("ssssssss", $row['Retail Code'], $row['Store Name'], $row['Brand Name'], $row['Brand Code'], $row['Mall Name'], $row['Entity'], $row['City'], $row['ROUTE CODE']);
            if($stmt->execute()) $imported++;
        }
        logAction($conn, $user, 'BULK_IMPORT', "Imported/Updated $imported stores via CSV.");
        echo json_encode(["status" => "success", "imported" => $imported]); exit;
    }
    
    if ($action === 'update_po') {
        $id = $data['id'];
        $poNum = $data['poNumber'];
        $delNote = $data['deliveryNote'];
        $invNum = $data['invoiceNumber'];

        $stmt = $conn->prepare("UPDATE purchase_orders SET po_number = ?, delivery_note_number = ?, invoice_number = ? WHERE id = ?");
        $stmt->bind_param("ssss", $poNum, $delNote, $invNum, $id);
        
        if ($stmt->execute()) {
            logAction($conn, $user, 'UPDATE_PO', "Updated PO references for ID: " . $id);
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database update failed."]);
        }
        exit;
    }

    if ($action === 'create_dispatch') {
        try {
            $dId = $data['dispatchId']; $store = $data['storeCode']; $pName = $data['pName']; $pId = $data['pId']; $pDept = $data['pDept']; $items = $data['items'];
            $stmt = $conn->prepare("INSERT INTO dispatch_logs (dispatch_id, sku, dispatch_qty, store_code, person_name, person_id, person_dept) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                $sku = $item['sku']; $qty = $item['qty'];
                $stmt->bind_param("ssissss", $dId, $sku, $qty, $store, $pName, $pId, $pDept); $stmt->execute();
                $stmtUpdate = $conn->prepare("UPDATE inventory SET quantity_on_hand = quantity_on_hand - ? WHERE sku = ?");
                $stmtUpdate->bind_param("is", $qty, $sku); $stmtUpdate->execute();
            }
            echo json_encode(["status" => "success"]);
        } catch (Exception $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
        exit;
    }

    if ($action === 'create_store') {
        $country = "KSA"; $route = $data['sRoute'] ?? 'N/A';
        $stmt = $conn->prepare("INSERT INTO stores (name, brand_code, brand, mall, entity, city, country, route_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $data['sName'], $data['sBrandCode'], $data['sBrand'], $data['sMall'], $data['sEntity'], $data['sCity'], $country, $route); $stmt->execute();
        echo json_encode(["status" => "success"]); exit;
    }

    if ($action === 'approve_store_request') {
        $dId = $data['dispatchId']; $store = $data['storeCode']; $items = $data['items']; $reqId = $data['requestId'];
        $pName = "N/A"; $pId = "N/A"; $pDept = "N/A";
        
        $stmt = $conn->prepare("INSERT INTO dispatch_logs (dispatch_id, sku, dispatch_qty, store_code, person_name, person_id, person_dept) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $sku = $item['sku']; $qty = $item['qty'];
            $stmt->bind_param("ssissss", $dId, $sku, $qty, $store, $pName, $pId, $pDept); $stmt->execute();
            $stmtUpdate = $conn->prepare("UPDATE inventory SET quantity_on_hand = quantity_on_hand - ? WHERE sku = ?");
            $stmtUpdate->bind_param("is", $qty, $sku); $stmtUpdate->execute();
        }
        
        $stmtStatus = $conn->prepare("UPDATE store_requests SET status = 'Approved' WHERE id = ?"); 
        $stmtStatus->bind_param("i", $reqId); 
        $stmtStatus->execute();
        
        logAction($conn, $user, 'STORE_REQUEST_APPROVED', "Approved and dispatched items for Store: " . $store);
        echo json_encode(["status" => "success"]); exit;
    }

    if ($action === 'request_inventory') {
        $storeId = $data['storeId'];
        $items = json_encode($data['items']);
        $stmt = $conn->prepare("INSERT INTO store_requests (username, details, status) VALUES (?, ?, 'Pending')");
        $stmt->bind_param("ss", $storeId, $items);
        $stmt->execute();
        echo json_encode(["status" => "success"]); exit;
    }

    if ($action === 'create_vendor') {
        $stmt = $conn->prepare("INSERT INTO vendors (company_name, tax_id, payment_terms, lead_time, contact_email, status, created_by) VALUES (?, ?, ?, ?, ?, 'Approved', ?)");
        $stmt->bind_param("ssssss", $data['company'], $data['taxId'], $data['terms'], $data['lead'], $data['email'], $user); $stmt->execute();
        echo json_encode(["status" => "success"]); exit;
    }
    if ($action === 'create_personnel') {
        $stmt = $conn->prepare("INSERT INTO users_po (emp_id, emp_name, department, username, password_hash, role) VALUES (?, ?, ?, ?, '1234', 'Agent')");
        $stmt->bind_param("ssss", $data['uId'], $data['uName'], $data['uDept'], $data['uId']); $stmt->execute();
        echo json_encode(["status" => "success"]); exit;
    }
    if ($action === 'delete_po') {
        $id = $data['id'];
        $conn->query("DELETE FROM purchase_orders WHERE id = '$id'");
        $conn->query("DELETE FROM line_items WHERE po_id = '$id'");
        echo json_encode(["status" => "success"]); exit;
    }
    if ($action === 'delete_vendor') {
        $id = $_GET['id']; $conn->query("DELETE FROM vendors WHERE id = '$id'"); echo json_encode(["status" => "success"]); exit;
    }
    if ($action === 'delete_personnel') {
        $empId = $_GET['emp_id']; $conn->query("DELETE FROM users_po WHERE emp_id = '$empId'"); echo json_encode(["status" => "success"]); exit;
    }
}

if ($method === 'GET') {
    if ($action === 'login') {
        $user = $_GET['user'] ?? ''; $pass = $_GET['pass'] ?? '';
        $stmt = $conn->prepare("SELECT username, role FROM users_po WHERE username = ? AND password_hash = ?");
        $stmt->bind_param("ss", $user, $pass); $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) { echo json_encode(["status" => "success", "user" => $res->fetch_assoc()]); exit; }

        $stmt2 = $conn->prepare("SELECT id, name, route_code FROM stores WHERE id = ?");
        $stmt2->bind_param("s", $user); $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($res2->num_rows > 0) {
            $storeData = $res2->fetch_assoc();
            if ($pass === ($storeData['id'] . "@aprlgrp")) {
                echo json_encode(["status" => "success", "user" => ["username" => $storeData['id'], "name" => $storeData['name'], "route_code" => $storeData['route_code'], "role" => "Store"]]); exit;
            }
        }
        echo json_encode(["status" => "error", "message" => "Invalid credentials."]); exit;
    }

    if ($action === 'list_stores') { $res = $conn->query("SELECT * FROM stores ORDER BY name ASC"); $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } echo json_encode($data); exit; }
    if ($action === 'list_store_requests') { 
        $res = $conn->query("SELECT id, username, details, timestamp, status FROM store_requests ORDER BY timestamp DESC"); 
        $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } echo json_encode($data); exit; 
    }
    if ($action === 'list_pos') { 
        $res = $conn->query("SELECT * FROM purchase_orders ORDER BY created_at DESC"); $data = []; 
        while($row = $res->fetch_assoc()) { 
            $poId = $row['id']; $itemsRes = $conn->query("SELECT * FROM line_items WHERE po_id = '$poId'"); 
            $items = []; while($itemRow = $itemsRes->fetch_assoc()) { $items[] = $itemRow; } 
            $row['lineItems'] = $items; $data[] = $row; 
        } 
        echo json_encode($data); exit; 
    }
    if ($action === 'list_inventory') { $res = $conn->query("SELECT * FROM inventory ORDER BY created_at DESC"); $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } echo json_encode($data); exit; }
    if ($action === 'list_dispatch') { $res = $conn->query("SELECT * FROM dispatch_logs ORDER BY dispatched_at DESC"); $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } echo json_encode($data); exit; }
    if ($action === 'list_vendors') { $res = $conn->query("SELECT * FROM vendors ORDER BY company_name ASC"); $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } echo json_encode($data); exit; }
    if ($action === 'list_personnel') { $res = $conn->query("SELECT emp_id, emp_name, department FROM users_po WHERE emp_id IS NOT NULL AND emp_id != '' ORDER BY emp_name ASC"); $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } echo json_encode($data); exit; }
    if ($action === 'list_audit_logs') { $res = $conn->query("SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 200"); $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } echo json_encode($data); exit; }
    if ($action === 'list_reverse_logistics') { $res = $conn->query("SELECT * FROM reverse_logistics ORDER BY id DESC"); $data = []; while($row = $res->fetch_assoc()) { $data[] = $row; } echo json_encode($data); exit; }
}
?>