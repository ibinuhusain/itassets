<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
ini_set('display_errors', 0); error_reporting(0);

require_once 'db.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "DB Connection Failed"]));
}

$agent_id  = $_POST['agent_id'];
$shop_id   = $_POST['shop_id']; 
$image_url = $_POST['image_url'] ?? ''; 

// Capture the new base64 signatures sent from processDigitalVisit()
$agent_sig = $_POST['agent_signature'] ?? null;
$store_sig = $_POST['store_signature'] ?? null;

$z_rep     = $_POST['z_report'] ?? 0;
$phys      = $_POST['physical_cash'] ?? 0;
$refund    = $_POST['refund'] ?? 0;
$incentive = $_POST['incentive'] ?? 0;
$petty_cash= $_POST['petty_cash'] ?? 0;
$disc      = $_POST['discrepancy'] ?? 0;

$emp_id    = $_POST['handover_id'] ?? '';
$reason    = $_POST['reason'] ?? '';
$sale_dt   = $_POST['sale_date'];
$col_dt    = $_POST['col_date'];

if(empty($shop_id)) {
    echo json_encode(["status" => "error", "message" => "Missing Shop ID"]); exit();
}

$query = "INSERT INTO shop_visits 
          (shop_id, agent_id, proof_image, agent_signature, store_signature, z_report, physical_cash, refund, incentive, petty_cash, discrepancy, handover_id, reason, sale_date, collection_date, visit_date) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt1 = $conn->prepare($query);
if($stmt1) {
    // 15 Parameters: shop(i), agent(i), img(s), ag_sig(s), st_sig(s), z(d), phys(d), ref(d), inc(d), petty(d), disc(d), emp(s), reason(s), sale(s), col(s)
    // -> "iisssddddddssss"
    $stmt1->bind_param("iisssddddddssss", $shop_id, $agent_id, $image_url, $agent_sig, $store_sig, $z_rep, $phys, $refund, $incentive, $petty_cash, $disc, $emp_id, $reason, $sale_dt, $col_dt);
    if(!$stmt1->execute()) {
        echo json_encode(["status" => "error", "message" => "Failed to save visit record: " . $stmt1->error]); exit();
    }
    $stmt1->close();
} else {
    echo json_encode(["status" => "error", "message" => "SQL Error: Verify database columns exist."]); exit();
}

$today = date('Y-m-d');
$stmt2 = $conn->prepare("UPDATE daily_assignments SET status = 'completed' WHERE agent_id = ? AND store_id = ? AND date_assigned = ?");
if($stmt2) {
    $stmt2->bind_param("iis", $agent_id, $shop_id, $today);
    $stmt2->execute();
    echo json_encode(["status" => "success", "message" => "Visit Verified & Amounts Saved"]);
    $stmt2->close();
} else {
    echo json_encode(["status" => "error", "message" => "SQL Error on Update"]);
}
$conn->close();
?>