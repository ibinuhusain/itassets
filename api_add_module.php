<?php
// Ensure cookies work across all directories on the domain
session_set_cookie_params(['path' => '/']);
session_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Database configuration variables
$host = "localhost";
$db   = "anomakio_collection";
$user = "anomakio_collection_adm";
$pass = "@GE3cn093tkl@";

// Read incoming raw payload
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input);

// 1. Validate mandatory fields
if (!$data || !isset($data->module_id) || !isset($data->name) || !isset($data->route_map)) {
    echo json_encode(["status" => "error", "message" => "Missing mandatory fields (Module ID, Name, and Routing Map)."]);
    exit;
}

$module_id   = trim(strtolower($data->module_id));
$name        = trim($data->name);
$icon        = !empty(trim($data->icon)) ? trim($data->icon) : 'extension';
$description = isset($data->description) ? trim($data->description) : '';
$route_map   = trim($data->route_map);

// 2. Format verification for module ID string
if (preg_match('/[^a-z0-9_]/', $module_id)) {
    echo json_encode(["status" => "error", "message" => "Module ID contains invalid characters. Use alphanumeric characters and underscores only."]);
    exit;
}

// 3. Strictly validate route map string as valid JSON syntax
json_decode($route_map);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["status" => "error", "message" => "Route Map validation failed: Invalid JSON format syntax."]);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 4. Secure prepared SQL insert statement execution
    $stmt = $pdo->prepare("INSERT INTO iam_modules (module_id, name, icon, description, route_map, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([
        $module_id, 
        $name, 
        $icon, 
        $description,
        $route_map
    ]);

    echo json_encode(["status" => "success", "message" => "Dynamic module and routing definitions registered successfully."]);

} catch (PDOException $e) {
    // Check for explicit SQL duplicate entry status constraint flag
    if ($e->getCode() == 23000 || $e->errorInfo[1] == 1062) {
        echo json_encode(["status" => "error", "message" => "A module configuration with the ID '{$module_id}' already exists."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Internal Database Exception Error: " . $e->getMessage()]);
    }
}
?>