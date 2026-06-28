<?php
// Set to India time for operations
date_default_timezone_set('Asia/Kolkata'); 

// 1. CRITICAL: Force domain-wide session cookies
session_set_cookie_params([
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']), 
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. KEEP CONFIG LOADER INTACT (Dashboards still need database connections!)
if (!function_exists('getConnection')) {
    $configPath = dirname(__DIR__) . '/config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    } else {
        $altPaths = [
            __DIR__ . '/../config.php',
            $_SERVER['DOCUMENT_ROOT'] . '/config.php',
            dirname(dirname(__FILE__)) . '/config.php'
        ];
        
        $found = false;
        foreach ($altPaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            die("Config file not found!");
        }
    }
}

if (!function_exists('getConnection')) {
    die("Database connection function not found in config.php!");
}

// ------------------------------------------------------------------
// CENTRAL IAM INTEGRATION
// ------------------------------------------------------------------

// Check if user is logged into the Master IAM
function isLoggedIn() {
    return isset($_SESSION['iam_user']);
}

// Get current user info directly from the IAM Session
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    // Format it similarly to the old DB array so legacy pages don't break
    return [
        'id' => $_SESSION['iam_user']['id'],
        'username' => $_SESSION['iam_user']['username'],
        'name' => $_SESSION['iam_user']['name']
    ];
}

// The old login() function is permanently decommissioned. 
// We force any straggler login attempts to the central portal.
function login($username, $password) {
    header("Location: /iam/index.html");
    exit();
}

// ------------------------------------------------------------------
// CORE ROLE CHECKER - UPDATED FOR IAM JSON ARRAYS
// ------------------------------------------------------------------
function hasRole($target_role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Extract the roles specifically mapped to the 'cash_collection' module
    // The IAM stores these as a direct array (e.g., ['admin', 'report']) instead of comma-strings
    $user_roles = $_SESSION['iam_user']['roles']['cash_collection'] ?? [];
    
    return in_array($target_role, $user_roles);
}

// ------------------------------------------------------------------
// PERMISSION GATEKEEPERS
// ------------------------------------------------------------------

// The central IAM portal URL for unauthorized kickbacks
define('IAM_LOGIN_URL', '/iam/index.html'); // Adjust this path if your IAM folder is named differently

function requireAdminOrReport() {
    if (!isLoggedIn()) {
        header('Location: ' . IAM_LOGIN_URL);
        exit();
    }
    
    if (!hasRole('admin') && !hasRole('report')) {
        header('Location: ../agent/dashboard.php'); 
        exit();
    }
}

function requireAgent() {
    requireLogin();
    if (!hasRole('agent')) {
        header("Location: " . IAM_LOGIN_URL);
        exit();
    }
}

function isSuperAdmin() {
    return hasRole('admin');
}

function isAdminOrHigher() {
    return hasRole('admin');
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . IAM_LOGIN_URL);
        exit();
    }
}

function requireAdmin() {
    if (!isAdminOrHigher()) {
        $isInAdminFolder = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
        
        if ($isInAdminFolder) {
            header("Location: ../agent/dashboard.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    }
}

function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        header("Location: ../agent/dashboard.php");
        exit();
    }
}

// Global Logout destroys the IAM session and returns to the master portal
function logout() {
    session_destroy();
    header("Location: " . IAM_LOGIN_URL);
    exit();
}
?>