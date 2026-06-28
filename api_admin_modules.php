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
    
    // Fetch ALL modules (active and inactive)
    $stmt = $pdo->query("SELECT * FROM iam_modules ORDER BY id DESC");
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "modules" => $modules]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
}
?>