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
    
    // Fetch all stores (ID, Name, and Brand)
    $stmt = $pdo->query("SELECT id, name, brand FROM stores");
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "stores" => $stores]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Failed to fetch stores."]);
}
?>