<?php
// Enable error reporting for debugging
require_once '../config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Start session
session_start();

// Log the start of the login process
error_log("Login attempt started at " . date('Y-m-d H:i:s'));

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method");
    header("Location: agent-login.php?error=Invalid request method");
    exit();
}

// Get form data
$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING) ?? '';
$password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING) ?? '';

// Validate input
if (empty($username) || empty($password)) {
    error_log("Empty username or password");
    header("Location: agent-login.php?error=Please enter both username and password");
    exit();
}

// Log the username attempting to login (without password for security)
error_log("Login attempt for username: " . $username);

try {
    // Include your database connection
    $configFile = __DIR__ . '../config.php';
    if (!file_exists($configFile)) {
        throw new Exception("Database configuration file not found");
    }
    
    require_once $configFile;
    
    // Check if getConnection function exists
    if (!function_exists('getConnection')) {
        throw new Exception("Database connection function not found");
    }
    
    // Get database connection
    $pdo = getConnection();
    
    if (!$pdo instanceof PDO) {
        throw new Exception("Failed to establish database connection");
    }
    
    // Prepare and execute query
    $stmt = $pdo->prepare("
        SELECT id, username, password, name, role 
        FROM users 
        WHERE username = :username AND role = 'agent'
    ");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare SQL statement");
    }
    
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verify user exists and password is correct
    if ($user) {
        if (password_verify($password, $user['password'])) {
            // Authentication successful
            $_SESSION['agent_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'] ?? 'Agent';
            $_SESSION['role'] = $user['role'];
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            error_log("Login successful for user: " . $username);
            
            // Redirect to dashboard
            header("Location: dashboard_agent.php");
            exit();
        } else {
            // Password doesn't match
            error_log("Invalid password for user: " . $username);
            header("Location: agent-login.php?error=Invalid username or password");
            exit();
        }
    } else {
        // User not found
        error_log("User not found: " . $username);
        header("Location: agent-login.php?error=Invalid username or password");
        exit();
    }
    
} catch (Exception $e) {
    // Log the detailed error
    $errorMessage = "Login error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    error_log($errorMessage);
    
    // Display a generic error message to the user
    header("Location: agent-login.php?error=An unexpected error occurred. Please try again later.");
    exit();
} catch (PDOException $e) {
    // Log database-specific errors
    $errorMessage = "Database error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    error_log($errorMessage);
    
    // Display a generic error message to the user
    header("Location: agent-login.php?error=Database connection error. Please try again later.");
    exit();
}
?>