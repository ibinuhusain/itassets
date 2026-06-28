<?php
// 1. Error Trapper
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        http_response_code(200);
        die("<br><br><b>FATAL ERROR:</b> " . $err['message']);
    }
});
ini_set('display_errors', 1); error_reporting(E_ALL);

// Use your central db.php connection
require_once 'db_value.php';

// 3. Get Parameters
$agent_id = isset($_GET['agent_id']) ? $_GET['agent_id'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// 4. Exact matching query based on schema
$query = "
    SELECT 
        sv.collection_date, 
        u.name AS agent_name, 
        s.name AS store_name, 
        sv.sale_date,
        sv.z_report, 
        sv.physical_cash, 
        sv.discrepancy, 
        sv.reason
    FROM shop_visits sv
    JOIN users u ON sv.agent_id = u.id
    JOIN stores s ON sv.shop_id = s.id
    WHERE DATE(sv.collection_date) BETWEEN ? AND ?
";

if ($agent_id !== 'all') {
    $query .= " AND sv.agent_id = ?";
    $stmt = $conn->prepare($query . " ORDER BY sv.collection_date DESC");
    if (!$stmt) die("SQL PREPARE ERROR: " . $conn->error);
    $stmt->bind_param("ssi", $start_date, $end_date, $agent_id);
    $filename = "Agent_Performance_" . $start_date . "_to_" . $end_date . ".csv";
} else {
    $stmt = $conn->prepare($query . " ORDER BY sv.collection_date DESC");
    if (!$stmt) die("SQL PREPARE ERROR: " . $conn->error);
    $stmt->bind_param("ss", $start_date, $end_date);
    $filename = "All_Agents_Performance_" . $start_date . "_to_" . $end_date . ".csv";
}

if (!$stmt->execute()) die("SQL EXECUTE ERROR: " . $stmt->error);
$result = $stmt->get_result();

// 5. Output File
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($output, ['Collection Date', 'Agent Name', 'Store Name', 'Sale Date', 'Z-Report (Expected)', 'Physical Cash', 'Discrepancy', 'Reason']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}
fclose($output);
$conn->close();
exit();
?>