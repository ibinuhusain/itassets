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

// 1. Get parameters from the GET request
$agent_id = $_GET['agent_id'] ?? 'all';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// 2. Build the BASE query using LEFT JOIN
$query = "
    SELECT 
        sv.sale_date,
        sv.collection_date,
        sv.visit_date as submission_time,
        u.name as agent_name,
        sv.shop_id as store_id,
        s.name as store_name,
        s.mall,
        sv.z_report,
        sv.physical_cash,
        sv.refund,
        sv.incentive,
        sv.petty_cash,
        sv.discrepancy,
        sv.reason,
        sv.proof_image
    FROM shop_visits sv
    LEFT JOIN users u ON sv.agent_id = u.id
    LEFT JOIN stores s ON sv.shop_id = s.id
    WHERE DATE(sv.sale_date) BETWEEN ? AND ?
";

$params = [$start_date, $end_date];

// 3. Append Agent filter and determine filename
if ($agent_id !== 'all') {
    $query .= " AND sv.agent_id = ?";
    $params[] = $agent_id;
    
    $nameStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $nameStmt->execute([$agent_id]);
    $agentName = str_replace(' ', '_', $nameStmt->fetchColumn() ?: 'Agent');
    $filename = "performance_" . $agentName . "_" . $start_date . "_to_" . $end_date . ".csv";
} else {
    $filename = "performance_all_agents_" . $start_date . "_to_" . $end_date . ".csv";
}

$query .= " ORDER BY sv.sale_date DESC, u.name";

// 4. Prepare and Execute the Database Query
$stmt = $pdo->prepare($query);
$stmt->execute($params);

// 5. Clean buffer and set Headers for CSV Download
if (ob_get_length()) {
    ob_clean();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel formatting

// 6. Output CSV Headers (Added Refund, Incentive, Petty Cash)
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
    'Proof Image Link'
]);

// 7. Output Data Rows
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    
    // Flip the discrepancy sign here - same as dashboard
    $flipped_discrepancy = (float)$row['discrepancy'] * -1;
    
    // Check to make sure it's not just the bare URL
    $image_link = (!empty($row['proof_image']) && $row['proof_image'] !== 'https://apparelgroup.anomak.co.in/') 
        ? $row['proof_image'] 
        : 'No Image Provided';
    
    fputcsv($output, [
        $row['sale_date'],
        $row['collection_date'],
        $row['submission_time'] ?? 'N/A',
        $row['agent_name'] ?: 'Deleted Agent',
        $row['store_id'] ?: 'N/A',
        $row['store_name'] ?: 'Deleted Store',
        $row['mall'] ?: 'N/A',
        $row['z_report'],
        $row['physical_cash'],
        $row['refund'],
        $row['incentive'],
        $row['petty_cash'],
        $flipped_discrepancy,  
        $row['reason'],
        $image_link            
    ]);
}

fclose($output);
ob_end_flush();
exit;
?>