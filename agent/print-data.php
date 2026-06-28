<?php
require_once '../includes/auth.php';
requireLogin();

$pdo = getConnection();

$assignment_id = $_GET['assignment_id'] ?? 0;

$stmt = $pdo->prepare("
SELECT da.*, s.name as store_name, s.address as store_address, u.name as agent_name
FROM daily_assignments da
JOIN stores s ON da.store_id = s.id
JOIN users u ON da.agent_id = u.id
WHERE da.id = ?
");
$stmt->execute([$assignment_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($data);
