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

// 2. Database Connection
date_default_timezone_set('Asia/Riyadh');
$host = "localhost";
$db   = "anomakio_collection";
$user = "anomakio_collection_adm";
$pass = "@GE3cn093tkl@";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("DB Connection Failed: " . $conn->connect_error);
$conn->query("SET time_zone = '+03:00'");

$focus = isset($_GET['focus']) ? $_GET['focus'] : 'all';

// 3. Query matching daily_assignments exactly
$query = "
    SELECT 
        da.date_assigned,
        u.name AS agent_name, 
        s.name AS store_name, 
        da.status
    FROM daily_assignments da
    JOIN users u ON da.agent_id = u.id
    JOIN stores s ON da.store_id = s.id
";

if ($focus === 'completed') {
    $query .= " WHERE da.status = 'completed'";
} elseif ($focus === 'pending') {
    $query .= " WHERE da.status = 'pending'";
}
$query .= " ORDER BY da.status DESC, u.name, s.name";

$result = $conn->query($query);
if (!$result) die("SQL ERROR: " . $conn->error);

// 4. Output File
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Store_Coverage_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($output, ['Date Assigned', 'Agent Name', 'Store Name', 'Assignment Status']);

while ($row = $result->fetch_assoc()) {
    $row['status'] = ucfirst($row['status']);
    fputcsv($output, $row);
}

fclose($output);
$conn->close();
exit();
?>