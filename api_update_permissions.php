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

if (!$data || !isset($data['user_id']) || !isset($data['app_permissions'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing data']);
    exit;
}

try {
    // UPDATED: Now saves the assigned_store field alongside the permissions
    $assigned_store = !empty($data['assigned_store']) ? trim($data['assigned_store']) : null;

    $stmt = $pdo->prepare("UPDATE master_users SET app_permissions = ?, assigned_store = ? WHERE id = ?");
    $stmt->execute([
        $data['app_permissions'], 
        $assigned_store,
        $data['user_id']
    ]);

    echo json_encode(['status' => 'success']);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error updating permissions.']);
}
?>