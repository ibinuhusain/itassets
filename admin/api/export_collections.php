<?php
// Start output buffering to catch stray text
ob_start();

// Disable on-screen errors to protect the CSV download
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Went up TWO directories to reach the root includes folder
require_once '../../includes/auth.php';
requireAdmin();

// Assuming db_value.php is in the same api/ directory. If it's in root, make this '../../db_value.php'
if (file_exists('db_value.php')) {
    require_once 'db_value.php';
}

$pdo = getConnection();

// Get the focus filter
$focus = $_GET['focus'] ?? 'all';
$format = $_GET['format'] ?? 'csv';
$today = date('Y-m-d'); // Capture exactly today for same-day filtering

$filename = "store_coverage_" . $focus . "_" . $today . ".csv";

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
    'Date Assigned', 'Record Created At', 'Agent Name', 'Store ID', 'Store Name', 'City', 'Region',
    'Mall', 'Brand', 'Assignment Status', 'Last Collection Recorded'
]);

// EXPLICIT SAME-DAY FILTER: Added WHERE DATE(da.date_assigned) = ?
// PATCH: Added master_users JOIN, full_name, explicit agent_id, and COALESCE
$query = "
    SELECT 
        da.agent_id,
        da.date_assigned,
        da.created_at,
        COALESCE(u.name, mu.full_name) as agent_name, 
        s.id as store_id,
        s.name as store_name, 
        s.city,
        r.name as region_name,
        s.mall, 
        s.brand, 
        da.status,
        (SELECT MAX(collection_date) FROM shop_visits sv WHERE sv.shop_id = da.store_id AND sv.agent_id = da.agent_id) as last_visit
    FROM daily_assignments da
    LEFT JOIN users u ON da.agent_id = u.id
    LEFT JOIN master_users mu ON da.agent_id = mu.id
    LEFT JOIN stores s ON da.store_id = s.id
    LEFT JOIN regions r ON s.region_id = r.id
    WHERE DATE(da.date_assigned) = ?
"; // <-- THIS WAS MISSING, CAUSING THE CORRUPTION

$params = [$today];

// Append status filters safely using AND
if ($focus === 'completed') {
    $query .= " AND da.status = 'completed'";
} elseif ($focus === 'pending') {
    $query .= " AND da.status = 'pending'";
}

// Updated ORDER BY to use the aliased agent_name 
$query .= " ORDER BY da.status DESC, agent_name, s.name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Evaluate final agent name with ID fallback
    $finalAgentName = $row['agent_name'] ?: 'Missing Agent (ID: ' . ($row['agent_id'] ?? 'NULL') . ')';

    fputcsv($output, [
        $row['date_assigned'] ? date('Y-m-d', strtotime($row['date_assigned'])) : 'N/A',
        $row['created_at'] ?? 'N/A',
        $finalAgentName, 
        $row['store_id'] ?: 'N/A',
        $row['store_name'] ?: 'Deleted Store',
        $row['city'] ?? 'N/A',
        $row['region_name'] ?? 'N/A',
        $row['mall'] ?? 'N/A',
        $row['brand'] ?? 'N/A',
        ucfirst($row['status']),
        $row['last_visit'] ? date('Y-m-d', strtotime($row['last_visit'])) : 'No Collection Recorded'
    ]);
}

fclose($output);
ob_end_flush();
exit();
?>