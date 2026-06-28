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

if (!$data || !isset($data['id']) || !isset($data['new_password'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data payload.']);
    exit;
}

try {
    $hashedPassword = password_hash($data['new_password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE master_users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$hashedPassword, $data['id']]);

    echo json_encode(['status' => 'success']);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error resetting password.']);
}
?>