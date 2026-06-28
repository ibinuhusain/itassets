<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");

$host = "localhost";
$db_name = "anomakio_retail_audit_db";
$username = "anomakio_retail_audit";
$password = "Q_HR}6z=(jYR8%mi";

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $exception) {
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'grace_period_days'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "grace_period" => $result ? $result['setting_value'] : 30]);
} 
elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    if (!empty($data->grace_period)) {
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = :val WHERE setting_key = 'grace_period_days'");
        if ($stmt->execute([':val' => $data->grace_period])) {
            echo json_encode(["success" => true, "message" => "Settings updated."]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to update."]);
        }
    }
}
?>