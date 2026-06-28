<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// DB Connection
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

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->username) && !empty($data->password)) {
    
    $query = "SELECT id, username, password_hash, role, status FROM users WHERE username = :username LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":username", $data->username);
    $stmt->execute();
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if($row) {
        if($row['status'] !== 'active') {
            echo json_encode(["success" => false, "message" => "Account is inactive."]);
            exit;
        }

        if(password_verify($data->password, $row['password_hash'])) {
            // Generate a simple secure token (In production, use JWT)
            $token = bin2hex(random_bytes(32)); 
            
            echo json_encode([
                "success" => true,
                "message" => "Login successful.",
                "token" => $token,
                "role" => $row['role'],
                "username" => $row['username']
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Incorrect password."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "User not found."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Incomplete data."]);
}
?>