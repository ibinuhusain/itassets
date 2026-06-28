<?php
// Force session cookies to clear folder barriers across the entire domain
session_set_cookie_params([
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', 
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// 1. If the session drops, don't redirect blindly. Tell us!
if (!isset($_SESSION['iam_user'])) {
    die("<h3>IAM Routing Error</h3>The server lost your login session during the handoff. Ensure you are accessing the site consistently with or without 'www.'");
}

$module = $_GET['module'] ?? '';

// =================================================================
// Inject the Store Session before doing the DB redirect
// =================================================================
if ($module === 'store') {
    if (!empty($_SESSION['iam_user']['assigned_store'])) {
        // Emulate the local store session variable that store/dashboard.php looks for
        $_SESSION['store_id'] = $_SESSION['iam_user']['assigned_store'];
    } else {
        die("Security Error: No physical store location is bound to your corporate IAM profile. Please contact an Administrator to update your Access Policies.");
    }
}
// =================================================================

// Allow dynamic DB router logic to handle all other conditions
$user_roles = $_SESSION['iam_user']['roles'];

// Bypass explicit role check for 'store' since we rely on 'assigned_store' instead
if ($module !== 'store') {
    if (!isset($user_roles[$module]) || empty($user_roles[$module])) {
        die("Error: You do not have permission to access the module: " . htmlspecialchars($module));
    }
    $primary_role = $user_roles[$module][0];
} else {
    $primary_role = 'store_user'; // Provide a default role key to query the DB map for the store module
}

// Database Credentials
$host = "localhost";
$db   = "anomakio_collection"; 
$user = "anomakio_collection_adm";
$pass = "@GE3cn093tkl@";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT route_map FROM iam_modules WHERE module_id = ? AND is_active = 1");
    $stmt->execute([$module]);
    $module_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module_data || empty($module_data['route_map'])) {
        die("Configuration Error: No routes defined in the database for module: " . htmlspecialchars($module));
    }

    $route_map = json_decode($module_data['route_map'], true);

    if (isset($route_map[$primary_role])) {
        // Append a random timestamp parameter (?t=...) to force the browser to bypass its redirect cache
        $target_url = $route_map[$primary_role] . "?t=" . time();
        header("Location: " . $target_url);
        exit;
    } else {
        die("Error: No destination path mapped for the role: " . htmlspecialchars($primary_role));
    }

} catch (PDOException $e) {
    die("Database routing connection exception error: " . $e->getMessage());
}
?>