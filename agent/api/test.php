<?php
// === DIAGNOSTIC TOOL FOR AGENT APP ===
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>API System Check</h1><hr>";

// 1. CONFIGURATION (ENTER YOUR DB PASS HERE)
require_once 'db.php';

// TEST CREDENTIALS
$test_user = "agent";
$test_pass = "agent123";

// 2. CHECK CONNECTION
echo "<h3>1. Checking Database Connection...</h3>";
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<b style='color:green'>[PASS] Connection Successful.</b><br>";
} catch (PDOException $e) {
    die("<b style='color:red'>[FAIL] Connection Error: " . $e->getMessage() . "</b>");
}

// 3. CHECK TABLE
echo "<h3>2. Checking 'users' Table...</h3>";
try {
    $stmt = $pdo->query("SELECT count(*) FROM users");
    echo "<b style='color:green'>[PASS] Table 'users' exists.</b><br>";
} catch (Exception $e) {
    die("<b style='color:red'>[FAIL] Table 'users' NOT FOUND. (Did you mean 'agents'?)</b>");
}

// 4. CHECK USER
echo "<h3>3. Searching for User '$test_user'...</h3>";
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$test_user]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    die("<b style='color:red'>[FAIL] User '$test_user' does not exist. Please register this user first.</b>");
}
echo "<b style='color:green'>[PASS] User Found (ID: " . $userData['id'] . ")</b><br>";
echo "Role: " . $userData['role'] . "<br>";

// 5. CHECK PASSWORD
echo "<h3>4. Verifying Password...</h3>";
if (password_verify($test_pass, $userData['password'])) {
    echo "<b style='color:green'>[PASS] Password '$test_pass' matches! Login should work.</b><br>";
} else {
    echo "<b style='color:red'>[FAIL] Password '$test_pass' is INCORRECT.</b><br>";
    echo "Stored Hash: " . substr($userData['password'], 0, 15) . "...<br>";
    echo "<i>Tip: The password in the DB might be plain text or a different hash.</i>";
}

// 6. CHECK PERMISSIONS
echo "<h3>5. Checking Upload Permissions...</h3>";
$dir = "../../uploads/" . date("Y") . "/";
if (!file_exists($dir)) @mkdir($dir, 0755, true);

if (is_writable($dir) || is_writable("../../uploads/")) {
    echo "<b style='color:green'>[PASS] Upload folder is writable.</b>";
} else {
    echo "<b style='color:red'>[FAIL] Upload folder is NOT writable. Run: chmod -R 755 uploads</b>";
}
?>