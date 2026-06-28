<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['iam_user'])) {
    header("Location: login.php");
    exit;
}

$it_roles = $_SESSION['iam_user']['roles']['it_procurement'] ?? [];

if (empty($it_roles)) {
    die("<div style='background:#f1f5f9; height:100vh; color:#ef4444; text-align:center; padding-top:100px; font-family:sans-serif;'>
        <h2>Access Denied</h2>
        <p>You do not have clearance for IT Procurement.</p>
    </div>");
}

$username = $_SESSION['iam_user']['username'] ?? 'Unknown';
$fullname = $_SESSION['iam_user']['name'] ?? 'User';
$role = $it_roles[0] ?? 'Admin';
$is_store = ($role === 'store' || $role === 'Store');
?>