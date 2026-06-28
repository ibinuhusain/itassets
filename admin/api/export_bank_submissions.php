<?php
require_once '../../includes/auth.php';
requireAdminOrReport(); // Ensure security
require_once '../../config.php';
$pdo = getConnection();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$status = $_GET['status'] ?? 'all';

// Build Query
$query = "
    SELECT 
        DATE(bs.created_at) as deposit_date,
        u.name AS agent_name, 
        bs.bank_name,
        bs.currency,
        bs.collected_cash,
        bs.deposited_cash,
        bs.discrepancy_amount,
        bs.discrepancy_reason
    FROM bank_submissions bs
    JOIN users u ON bs.agent_id = u.id
    WHERE DATE(bs.created_at) BETWEEN ? AND ?
";

$params = [$start_date, $end_date];

if ($status !== 'all') {
    $query .= " AND bs.status = ?";
    $params[] = $status;
}

$query .= " ORDER BY bs.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Bank_Submissions_' . $start_date . '_to_' . $end_date . '.csv');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel UTF-8

    // Write Headers matching the Excel image layout
    fputcsv($output, [
        'Deposit Date', 
        'Agent Name', 
        'Bank Name', 
        'Currency', 
        'Collected Cash', 
        'Deposited Cash', 
        'Short By ATM', 
        'Excess By Atm', 
        'Discrepancy', 
        'Reason - Others (to type manually)'
    ]);

    // Write Data
    foreach ($results as $row) {
        $collected = (float)$row['collected_cash'];
        $deposited = (float)$row['deposited_cash'];
        
        $short = '';
        $excess = '';
        
        // Dynamically calculate Short vs Excess
        if ($collected > $deposited) {
            $short = $collected - $deposited;
        } elseif ($deposited > $collected) {
            $excess = $deposited - $collected;
        }
        
        fputcsv($output, [
            $row['deposit_date'],
            $row['agent_name'],
            $row['bank_name'],
            $row['currency'],
            $collected,
            $deposited,
            $short,
            $excess,
            $row['discrepancy_amount'], 
            $row['discrepancy_reason']
        ]);
    }
    
    fclose($output);
    exit();

} catch (PDOException $e) {
    die("Export Failed: " . $e->getMessage());
}
?>