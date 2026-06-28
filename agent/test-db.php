<?php
// reset-agent-passwords.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/config.php';

$pdo = getConnection();

echo "<h1>Reset Agent Passwords</h1>";

// New password for all agents
$newPassword = 'agent123'; // You can change this
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

echo "<p>New password for all agents: <strong>$newPassword</strong></p>";
echo "<p>Hashed: " . substr($hashedPassword, 0, 30) . "...</p>";

// Reset all agent passwords
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE role = 'agent'");
$stmt->execute([$hashedPassword]);
$affectedRows = $stmt->rowCount();

echo "<p style='color: green;'>✓ Updated $affectedRows agent accounts</p>";

// Verify the update
$stmt = $pdo->query("SELECT id, username, name FROM users WHERE role = 'agent'");
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Updated Agents:</h3>";
echo "<ul>";
foreach ($agents as $agent) {
    echo "<li>" . htmlspecialchars($agent['username']) . " - " . htmlspecialchars($agent['name']) . "</li>";
}
echo "</ul>";

echo "<p><a href='agent-login.php'>Go to Login Page</a></p>";
?>