<?php
// 1. HEADERS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// 2. ERROR HANDLING
ini_set('display_errors', 0);
error_reporting(0);

// 3. DATABASE CONNECTION
require_once 'db.php';      

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "DB Connection Failed"]);
    exit();
}

// 4. GET INPUT
$u = $_POST['username'] ?? '';
$p = $_POST['password'] ?? '';

if (empty($u) || empty($p)) {
    echo json_encode(["status" => "error", "message" => "Enter Username & Password"]);
    exit();
}

// 5. LOGIN LOGIC
$isAuthenticated = false;
$userData = [];

// --- ATTEMPT 1: Check the OLD table first ---
$stmtOld = $pdo->prepare("SELECT id, name, password, role FROM users WHERE username = ?");
$stmtOld->execute([$u]);
$oldUser = $stmtOld->fetch(PDO::FETCH_ASSOC);

if ($oldUser && password_verify($p, $oldUser['password'])) {
    // Valid password in old table! Check flat string roles.
    $rolesArray = array_map('trim', explode(',', strtolower($oldUser['role'])));
    
    if (in_array('agent', $rolesArray) || in_array('admin', $rolesArray)) {
        $isAuthenticated = true;
        $userData = [
            "agent_id" => $oldUser['id'], 
            "name" => $oldUser['name'],
            "role" => $oldUser['role'] // Send legacy flat string
        ];
    } else {
        echo json_encode(["status" => "error", "message" => "Access Denied: Agents Only"]);
        exit();
    }
} 
// --- ATTEMPT 2: Fallback to NEW table ---
else {
    $stmtNew = $pdo->prepare("SELECT id, full_name, password_hash, app_permissions FROM master_users WHERE username = ?");
    $stmtNew->execute([$u]);
    $newUser = $stmtNew->fetch(PDO::FETCH_ASSOC);

    if ($newUser && password_verify($p, $newUser['password_hash'])) {
        // Valid password in new table! Decode the JSON roles.
        $permissions = json_decode($newUser['app_permissions'], true);
        $isAuthorized = false;
        $extractedRoles = [];

        if (is_array($permissions)) {
            foreach ($permissions as $module => $rolesArray) {
                if (is_array($rolesArray)) {
                    foreach ($rolesArray as $role) {
                        $cleanRole = strtolower(trim($role));
                        $extractedRoles[] = $cleanRole; 
                        
                        if ($cleanRole === 'agent' || $cleanRole === 'admin') {
                            $isAuthorized = true;
                        }
                    }
                }
            }
        }
        
        if ($isAuthorized) {
            $isAuthenticated = true;
            $legacyRoleString = implode(',', array_unique($extractedRoles));
            $userData = [
                "agent_id" => $newUser['id'], 
                "name" => $newUser['full_name'],
                "role" => $legacyRoleString // Send legacy flat string
            ];
        } else {
            echo json_encode(["status" => "error", "message" => "Access Denied: Agents Only"]);
            exit();
        }
    }
}

// --- FINAL RESPONSE ---
if ($isAuthenticated) {
    echo json_encode(array_merge(["status" => "success"], $userData));
} else {
    // If it gets here, they failed both the old and new table checks
    echo json_encode(["status" => "error", "message" => "Invalid Username or Password"]);
}
?>