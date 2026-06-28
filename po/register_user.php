<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'db.php'; // Ensure this matches your filename exactly

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $conn; // This ensures we are using the $conn from mydb.php
    
    $username = $_POST['username'];
    $password = $_POST['password']; 
    $role = 'admin';

    $stmt = $conn->prepare("INSERT INTO users_po (username, password_hash, role) VALUES (?, ?, ?)");
    
    if (!$stmt) {
        die("SQL Error: " . $conn->error);
    }

    $stmt->bind_param("sss", $username, $password, $role);
    
    if ($stmt->execute()) {
        echo "✅ User '$username' created successfully!";
    } else {
        echo "❌ Error: " . $conn->error;
    }
}
?>
<form method="POST">
    <input type="text" name="username" placeholder="Username" required><br>
    <input type="password" name="password" placeholder="Password" required><br>
    <button type="submit">Create User</button>
</form>