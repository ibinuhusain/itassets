<?php
// 1. Grab the exact same domain-wide cookie settings we used to create the session
session_set_cookie_params([
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']), 
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Empty the session array completely
$_SESSION = array();

// 3. Destroy the actual cookie in the user's browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Annihilate the session on the server
session_destroy();

// 5. Send them right back to the master login screen
header("Location: index.html");
exit;
?>