<?php
// Start output buffering to catch stray text
ob_start();

// Disable on-screen errors to protect the CSV download
ini_set('display_errors', 0);
error_reporting(E_ALL);

// FIXED: Went up TWO directories to reach the root includes folder
require_once '../../includes/auth.php';
requireAdmin();

$pdo = getConnection();

// Get parameters from the GET request
$agent_id = $_GET['agent_id'] ?? 'all';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Build the query using LEFT JOIN and actual schema columns
// FIXED: Changed filter from DATE(sv.sale_date) to DATE(sv.visit_date) to match same-day submissions
// PATCH: Added master_users JOIN, explicit agent_id, and COALESCE logic
$query = "
    SELECT 
        sv.id, 
        sv.agent_id, /* Fetching explicit ID for fallback */
        sv.sale_date, 
        sv.collection_date,
        sv.visit_date as submission_time,
        COALESCE(u.name, mu.full_name) as agent_name, /* Checking both tables */
        s.id as store_id,
        s.name as store_name, 
        s.mall,
        sv.z_report, 
        sv.physical_cash,
        sv.refund,
        sv.incentive,
        sv.petty_cash,
        sv.reason,
        CONCAT('https://apparelgroupksa.com/admin/view_receipt.php?id=', sv.id) AS receipt_link
    FROM shop_visits sv
    LEFT JOIN users u ON sv.agent_id = u.id
    LEFT JOIN master_users mu ON sv.agent_id = mu.id /* Joined master_users */
    LEFT JOIN stores s ON sv.shop_id = s.id
    WHERE DATE(sv.visit_date) >= ? AND DATE(sv.visit_date) <= ?
";

$params = [$start_date, $end_date];

if ($agent_id !== 'all') {
    $query .= " AND sv.agent_id = ?";
    $params[] = $agent_id;
    
    // PATCH: Check both tables to name the downloaded file correctly
    $nameStmt = $pdo->prepare("
        SELECT COALESCE(
            (SELECT name FROM users WHERE id = ?), 
            (SELECT full_name FROM master_users WHERE id = ?)
        )
    ");
    $nameStmt->execute([$agent_id, $agent_id]);
    $fetchedName = $nameStmt->fetchColumn();
    $agentName = str_replace(' ', '_', $fetchedName ?: 'Agent_' . $agent_id);
    
    $filename = "performance_" . $agentName . "_" . $start_date . "_to_" . $end_date . ".csv";
} else {
    $filename = "performance_all_agents_" . $start_date . "_to_" . $end_date . ".csv";
}

// Updated sort to use the alias
$query .= " ORDER BY sv.visit_date DESC, agent_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);

// Clean buffer and set headers
if (ob_get_length()) {
    ob_clean();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel

fputcsv($output, [
    'Sale Date', 
    'Collection Date',
    'Submission Time',
    'Agent Name', 
    'Store ID',
    'Store Name', 
    'Mall',
    'Z-Report', 
    'Physical Cash', 
    'Refund',
    'Incentive',
    'Petty Cash',
    'Discrepancy', 
    'Reason',
    'Digital Receipt Link' 
]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    
    // EXPLICIT FIXED LOGIC MATHEMATICAL FORMULAS
    $z_report   = (float)($row['z_report'] ?? 0);
    $collected  = (float)($row['physical_cash'] ?? 0);
    $refund     = (float)($row['refund'] ?? 0);
    $incentive  = (float)($row['incentive'] ?? 0);
    $petty_cash = (float)($row['petty_cash'] ?? 0);
    
    $basic_discrepancy = $z_report - $collected;
    $final_discrepancy = $basic_discrepancy - ($incentive + $refund + $petty_cash);
    
    // Evaluate final agent name
    $finalAgentName = $row['agent_name'] ?: 'Missing Agent (ID: ' . ($row['agent_id'] ?? 'NULL') . ')';
    
    fputcsv($output, [
        $row['sale_date'],
        $row['collection_date'],
        $row['submission_time'] ?? 'N/A',
        $finalAgentName, /* PATCH: Output the evaluated name with ID fallback */
        $row['store_id'] ?: 'N/A',
        $row['store_name'] ?: 'Deleted Store',
        $row['mall'] ?: 'N/A',
        number_format($z_report, 2, '.', ''),
        number_format($collected, 2, '.', ''),
        number_format($refund, 2, '.', ''),
        number_format($incentive, 2, '.', ''),
        number_format($petty_cash, 2, '.', ''),
        number_format($final_discrepancy, 2, '.', ''), // Precise logic output
        $row['reason'],
        $row['receipt_link'] 
    ]);
}

fclose($output);
ob_end_flush();
exit;
?>