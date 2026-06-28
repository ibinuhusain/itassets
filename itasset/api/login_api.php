<?php
session_start();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

// DEMO LOGIN - Replace with actual authentication
if ($username === 'admin' && $password === 'admin') {
    $_SESSION['iam_user'] = [
        'username' => 'admin',
        'name' => 'Administrator',
        'roles' => [
            'it_procurement' => ['Admin']
        ]
    ];
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
}
exit;
?>