<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
ini_set('display_errors', 1); error_reporting(E_ALL); 

require_once 'db.php'; 

try {
    $agent_id = $_POST['agent_id'] ?? '';
    $shop_id  = $_POST['shop_id'] ?? ''; 
    $z_report = $_POST['z_report'] ?? 0;
    $cash     = $_POST['physical_cash'] ?? 0;
    
    // NEW FIELDS
    $refund     = $_POST['refund'] ?? 0;
    $incentive  = $_POST['incentive'] ?? 0;
    $petty_cash = $_POST['petty_cash'] ?? 0;
    
    $disc     = $_POST['discrepancy'] ?? 0;
    $emp_id   = $_POST['handover_id'] ?? '';
    $reason   = $_POST['reason'] ?? '';
    $s_date   = $_POST['sale_date'] ?? null;
    $c_date   = $_POST['col_date'] ?? null;
    $currency = $_POST['currency'] ?? 'SAR';
    $remarks  = $_POST['remarks'] ?? '';
    $sig_b64  = $_POST['signature_b64'] ?? '';
    $store_sig_b64 = $_POST['store_signature_b64'] ?? '';

    if(empty($shop_id) || empty($sig_b64) || empty($store_sig_b64)) {
        echo json_encode(["status" => "error", "message" => "Missing Shop ID or Signatures"]);
        exit();
    }

    if (!empty($s_date)) {
        $check_stmt = $conn->prepare("SELECT id FROM shop_visits WHERE shop_id = ? AND sale_date = ?");
        $check_stmt->bind_param("is", $shop_id, $s_date);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            echo json_encode([
                "status" => "error", 
                "message" => "Duplicate Entry: A collection for this store with the Sale Date ($s_date) has already been submitted!"
            ]);
            exit();
        }
        $check_stmt->close();
    }

    $target_dir = __DIR__ . '/../../uploads/signatures/';
    if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }

    $agent_parts = explode(";base64,", $sig_b64);
    $agent_base64 = base64_decode($agent_parts[1]);
    $agent_file_name = 'sig_agent_' . $shop_id . '_' . time() . '.png';
    $agent_file_path = $target_dir . $agent_file_name;
    $agent_db_path = 'uploads/signatures/' . $agent_file_name;

    if(!file_put_contents($agent_file_path, $agent_base64)) {
        echo json_encode(["status" => "error", "message" => "Failed to save Agent signature image."]); exit();
    }

    $store_parts = explode(";base64,", $store_sig_b64);
    $store_base64 = base64_decode($store_parts[1]);
    $store_file_name = 'sig_store_' . $shop_id . '_' . time() . '.png';
    $store_file_path = $target_dir . $store_file_name;
    $store_db_path = 'uploads/signatures/' . $store_file_name;

    if(!file_put_contents($store_file_path, $store_base64)) {
        echo json_encode(["status" => "error", "message" => "Failed to save Store signature image."]); exit();
    }

    // UPDATE QUERY - 16 Parameters Total
    $query = "INSERT INTO shop_visits 
        (shop_id, agent_id, currency, proof_image, store_employee_signature, z_report, physical_cash, refund, incentive, petty_cash, discrepancy, handover_id, reason, remarks, sale_date, collection_date, visit_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
    $stmt = $conn->prepare($query);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    // Types: i i s s s d d d d d d s s s s s -> "iisssddddddsssss"
    $stmt->bind_param("iisssddddddsssss", 
        $shop_id, $agent_id, $currency, $agent_db_path, $store_db_path,
        $z_report, $cash, $refund, $incentive, $petty_cash, $disc, 
        $emp_id, $reason, $remarks, $s_date, $c_date
    );

    if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
    $stmt->close();

    $today = date('Y-m-d');
    $query2 = "UPDATE daily_assignments SET status = 'completed' WHERE agent_id = ? AND store_id = ? AND date_assigned = ?";
    $stmt2 = $conn->prepare($query2);
    if ($stmt2) {
        $stmt2->bind_param("iis", $agent_id, $shop_id, $today);
        $stmt2->execute();
        $stmt2->close();
    }

    echo json_encode(["status" => "success", "message" => "Digital Receipt Saved Successfully"]);

} catch(Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
}

if (isset($conn)) $conn->close();
?>