<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 

$host = "localhost";
$db   = "anomakio_collection";
$user = "anomakio_collection_adm";
$pass = "@GE3cn093tkl@";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (\PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data payload.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE master_users 
        SET emp_id = ?, full_name = ?, username = ?, email = ?, department = ?, phone = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $data['emp_id'],
        $data['full_name'],
        $data['username'],
        $data['email'],
        $data['department'],
        $data['phone'],
        $data['id']
    ]);

    echo json_encode(['status' => 'success']);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Update Error.']);
}
?>