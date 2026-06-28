<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
ini_set('display_errors', 0); error_reporting(0);

// DB CREDENTIALS
require_once 'db.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die(json_encode(["status" => "error", "message" => "DB Error"]));

// Capture the new fields from the frontend payload
$agent_id       = $_POST['agent_id'];
$bank_name      = $_POST['bank_name'];
$collected_cash = $_POST['collected_cash'];
$deposited_cash = $_POST['deposited_cash'];
$discrepancy    = $_POST['discrepancy'];
$reason         = $_POST['reason'];
$image_url      = $_POST['image_url']; // This is the receipt image
$ho_signature   = $_POST['ho_signature'] ?? null; // Capture the base64 signature
$status         = "pending";

// Basic validation to ensure the core requirements are met
if(empty($deposited_cash) || empty($image_url) || empty($bank_name)) {
    echo json_encode(["status" => "error", "message" => "Missing Bank Name, Amounts, or Receipt"]);
    exit();
}

// --- AUTOMATIC RESET ---
// Reset all 'completed' shops for this agent back to 'pending' for their next cycle
$reset_stmt = $conn->prepare("UPDATE daily_assignments SET status = 'pending' WHERE agent_id = ? AND status = 'completed'");

if($reset_stmt) {
    $reset_stmt->bind_param("i", $agent_id);
    $reset_stmt->execute();
    $reset_stmt->close();
}

// Prepare the new INSERT statement with all the new columns including ho_signature
$stmt = $conn->prepare("INSERT INTO bank_submissions 
    (agent_id, bank_name, collected_cash, deposited_cash, discrepancy_amount, discrepancy_reason, ho_signature, receipt_image, created_at, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");

// Bind parameters: 
// i = integer, s = string, d = double/decimal
// We now have 9 parameters: isdddssss
$stmt->bind_param("isdddssss", $agent_id, $bank_name, $collected_cash, $deposited_cash, $discrepancy, $reason, $ho_signature, $image_url, $status);

if($stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "DB Error: " . $stmt->error]);
}
$conn->close();
?>