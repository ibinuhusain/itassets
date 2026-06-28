<?php
require_once 'db_value.php';

$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Updated Query to pull the new cash/discrepancy fields
$query = "
    SELECT 
        DATE(bs.created_at) as deposit_date,
        u.name AS agent_name, 
        bs.collected_cash,
        bs.deposited_cash,
        bs.discrepancy_amount,
        bs.discrepancy_reason,
        bs.status, 
        bs.approved_at,
        CONCAT('https://apparelgroup.anomak.co.in/', bs.receipt_image) AS receipt_link
    FROM bank_submissions bs
    JOIN users u ON bs.agent_id = u.id
    WHERE DATE(bs.created_at) BETWEEN ? AND ?
";

if ($status !== 'all') {
    $query .= " AND bs.status = ?";
    $stmt = $conn->prepare($query . " ORDER BY bs.created_at DESC");
    $stmt->bind_param("sss", $start_date, $end_date, $status);
} else {
    $stmt = $conn->prepare($query . " ORDER BY bs.created_at DESC");
    $stmt->bind_param("ss", $start_date, $end_date);
}

$stmt->execute();
$result = $stmt->get_result();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Bank_Deposits_' . $start_date . '_to_' . $end_date . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Updated CSV Headers
fputcsv($output, ['Deposit Date', 'Agent Name', 'Collected Cash', 'Deposited Cash', 'Discrepancy', 'Reason', 'Status', 'Approved At', 'Receipt Link']);

while ($row = $result->fetch_assoc()) {
    $row['status'] = ucfirst($row['status']);
    fputcsv($output, $row);
}
fclose($output);
$conn->close();
?>