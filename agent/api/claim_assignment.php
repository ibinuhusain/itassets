<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', 0); error_reporting(0);

require_once 'db.php';
$idnt = $_POST['identifier'];
$agnt = $_POST['agent_id'];
$today = date('Y-m-d');

if(empty($idnt) || empty($agnt)) {
    echo json_encode(["status" => "error", "message" => "Missing Store Code or Agent ID"]);
    exit();
}

// 1. Find the Store and today's Assignment
$sql = "SELECT da.id as assign_id, s.id as shop_id, s.name, s.brand, r.name as region 
        FROM stores s 
        LEFT JOIN regions r ON s.region_id = r.id
        LEFT JOIN daily_assignments da ON s.id = da.store_id AND da.date_assigned = ?
        WHERE s.id = ? OR s.name LIKE ? LIMIT 1";

$stmt = $conn->prepare($sql);
$search = "%$idnt%";
$stmt->bind_param("sss", $today, $idnt, $search);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if($res) {
    // If assignment exists, update it to the scanner agent
    if($res['assign_id']) {
        $upd = $conn->prepare("UPDATE daily_assignments SET agent_id = ? WHERE id = ?");
        $upd->bind_param("ii", $agnt, $res['assign_id']);
        $upd->execute();
    } else {
        // If no assignment today, create one on the fly for this agent
        $ins = $conn->prepare("INSERT INTO daily_assignments (agent_id, store_id, date_assigned, status) VALUES (?, ?, ?, 'pending')");
        $ins->bind_param("iis", $agnt, $res['shop_id'], $today);
        $ins->execute();
        $res['assign_id'] = $ins->insert_id;
    }
    
    echo json_encode([
        "status" => "success",
        "assignment_id" => $res['assign_id'],
        "shop_id" => $res['shop_id'],
        "shop_name" => $res['name'],
        "brand" => $res['brand'],
        "region" => $res['region']
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Store Code not found in database"]);
}
?>