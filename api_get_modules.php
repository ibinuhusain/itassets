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
    
    // Fetch only active modules
    $stmt = $pdo->query("SELECT module_id, name, icon, description FROM iam_modules WHERE is_active = 1");
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Reformat into an easy key-value object for JavaScript
    $config = [];
    foreach ($modules as $mod) {
        $config[$mod['module_id']] = $mod;
    }

    echo json_encode(["status" => "success", "data" => $config]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
}
?>