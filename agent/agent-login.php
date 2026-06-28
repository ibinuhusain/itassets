<?php

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '.hostingersite.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);
// agent-login.php - Agent Login Page

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Force no-cache headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables
$error = '';
$message = '';

// Check for error in URL
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

// Check for message in URL
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Include config.php - adjust path based on your structure
// Since we're in /agent/ folder, config should be in parent
$configPath = '../config.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    // Try alternative path
    $configPath = '../../config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    } else {
        die("Config file not found. Checked: ../config.php and ../../config.php");
    }
}

// Include auth.php
$authPath = '../includes/auth.php';
if (file_exists($authPath)) {
    require_once $authPath;
} else {
    $authPath = '../../includes/auth.php';
    if (file_exists($authPath)) {
        require_once $authPath;
    } else {
        die("Auth file not found.");
    }
}

// Now check if already logged in
if (function_exists('isLoggedIn') && isLoggedIn()) {
    if (function_exists('hasRole')) {
        if (hasRole('admin')) {
            header("Location: ../admin/dashboard.php");
        } else {
            header("Location: dashboard_agent.php");
        }
    } else {
        header("Location: dashboard_agent.php");
    }
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Basic validation
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Check if login function exists
        if (!function_exists('login')) {
            $error = 'System error: Login function not available.';
        } else {
            // Attempt login
            $login_result = login($username, $password);
            
         if ($login_result === true) {
    $url = hasRole('admin')
        ? 'https://aquamarine-mule-238491.hostingersite.com/admin/dashboard.php'
        : 'https://aquamarine-mule-238491.hostingersite.com/agent/dashboard_agent.php';

    echo "<script>
        window.top.location.href = '$url';
    </script>";
    exit();
}
 else {
                // Login failed
                $error = 'Invalid username or password.';
                
                // If login() returns an error message, use it
                if (is_string($login_result) && !empty($login_result)) {
                    $error = $login_result;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Login - Apparel Collection</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Your existing CSS styles here */
        :root {
            --primary-bg: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            --card-bg: rgba(255, 255, 255, 0.1);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --accent-color: #6366f1;
            --accent-hover: #4f46e5;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: var(--primary-bg);
            padding: 20px;
        }

        .login-container {
            background: rgba(23, 25, 49, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: var(--text-primary);
            font-size: 28px;
            margin-bottom: 10px;
            background: linear-gradient(90deg, #ffffff, #d1d5db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo p {
            color: var(--text-secondary);
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }

        .success-message {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }

        .help-text {
            color: var(--text-secondary);
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }

        .help-text a {
            color: var(--accent-color);
            text-decoration: none;
        }

        .help-text a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Apparel Collection</h1>
            <p>Agent Portal</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- FIXED: Form submits to itself -->
        <!-- In agent-login.php, update the form: -->
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">Sign In</button>
        </form>

        <div class="help-text">
            Don't have an account? <a href="mailto:admin@apparelcollection.com">Contact Administrator</a>
        </div>
        
        <!-- Debug info (remove in production) -->
        <div style="margin-top: 20px; padding: 10px; background: rgba(0,0,0,0.3); border-radius: 5px; color: #ccc; font-size: 12px;">
            <strong>Debug Info:</strong><br>
            File: <?php echo __FILE__; ?><br>
            Config included: <?php echo isset($configPath) ? 'Yes' : 'No'; ?><br>
            Auth included: <?php echo isset($authPath) ? 'Yes' : 'No'; ?><br>
            Functions: 
            isLoggedIn: <?php echo function_exists('isLoggedIn') ? 'Exists' : 'NOT FOUND'; ?>, 
            login: <?php echo function_exists('login') ? 'Exists' : 'NOT FOUND'; ?>
        </div>
    </div>
    
    
</body>
</html>