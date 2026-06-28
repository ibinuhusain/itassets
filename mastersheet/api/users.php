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
    echo json_encode(["success" => false, "message" => "Database connection error."]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $query = "SELECT id, username, role, status, created_at FROM users ORDER BY id DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} 
elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    // ACTION: Delete User
    if (isset($data->action) && $data->action === 'delete_user') {
        if (!empty($data->user_id)) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
            if ($stmt->execute([':id' => $data->user_id])) {
                echo json_encode(["success" => true, "message" => "User deleted successfully."]);
            } else {
                echo json_encode(["success" => false, "message" => "Failed to delete user."]);
            }
        }
        exit;
    }

    // ACTION: Reset Password
    if (isset($data->action) && $data->action === 'reset_password') {
        if (!empty($data->user_id) && !empty($data->new_password)) {
            $hashed = password_hash($data->new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = :password WHERE id = :id");
            if ($stmt->execute([':password' => $hashed, ':id' => $data->user_id])) {
                echo json_encode(["success" => true, "message" => "Password updated successfully."]);
            } else {
                echo json_encode(["success" => false, "message" => "Failed to update password."]);
            }
        }
        exit;
    }

    // ACTION: Create User
    if(!empty($data->username) && !empty($data->password) && !empty($data->role)) {
        $check = $conn->prepare("SELECT id FROM users WHERE username = :username");
        $check->execute([':username' => $data->username]);
        
        if($check->rowCount() > 0) {
            echo json_encode(["success" => false, "message" => "Username already exists!"]);
            exit;
        }

        $hashed = password_hash($data->password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (:username, :password, :role)");
        if($stmt->execute([':username' => $data->username, ':password' => $hashed, ':role' => $data->role])) {
            echo json_encode(["success" => true, "message" => "User created successfully."]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to create user."]);
        }
    }
}
?>