<?php
session_start();
if (isset($_SESSION['iam_user'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Apparel ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { 
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-container {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-container h1 {
            color: #0f172a;
            font-size: 24px;
            margin-bottom: 10px;
            text-align: center;
        }
        .login-container .subtitle {
            color: #64748b;
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .login-container .logo {
            text-align: center;
            font-size: 48px;
            color: #2563eb;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-login:hover {
            background: #1d4ed8;
        }
        .error {
            background: #fef2f2;
            color: #dc2626;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo"><i class="fas fa-cubes"></i></div>
        <h1>Apparel ERP</h1>
        <p class="subtitle">IT Asset Management Suite</p>
        
        <div id="errorMsg" class="error"></div>
        
        <form id="loginForm" onsubmit="handleLogin(event)">
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="username" placeholder="Enter your username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" id="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn-login">Sign In</button>
        </form>
    </div>

    <script>
        function handleLogin(e) {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            // Simple demo login - replace with actual API call
            if (username === 'admin' && password === 'admin') {
                // Set session via API
                fetch('api/login_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ username, password })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.href = 'dashboard.php';
                    } else {
                        showError('Invalid credentials');
                    }
                })
                .catch(() => showError('Connection error'));
            } else {
                showError('Invalid username or password');
            }
        }
        
        function showError(msg) {
            const el = document.getElementById('errorMsg');
            el.textContent = msg;
            el.style.display = 'block';
        }
    </script>
</body>
</html>