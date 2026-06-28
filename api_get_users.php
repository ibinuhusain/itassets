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
    
    // UPDATED: Now fetching the new profile columns
  // Change line 15 to include 'assigned_store':
$stmt = $pdo->query("SELECT id, emp_id, username, full_name, email, department, assigned_store, manager, phone, is_iam_admin, app_permissions FROM master_users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse the JSON string into an actual PHP array for the frontend
    foreach ($users as &$u) {
        $u['app_permissions'] = json_decode($u['app_permissions'], true) ?: [];
        $u['is_iam_admin'] = (bool)$u['is_iam_admin'];
    }

    echo json_encode(["status" => "success", "users" => $users]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
}
?>