<?php
require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();

// 1. Set headers to force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=assignment_template_' . date('Y-m-d') . '.csv');

// 2. Open the output stream
$output = fopen('php://output', 'w');

// 3. Set the Column Headers
fputcsv($output, ['Agent_Name', 'Region', 'Mall', 'Entity', 'Brand', 'Store_Name']);

// 4. Fetch all stores with their region names
$query = "SELECT s.name as store_name, s.mall, s.entity, s.brand, r.name as region_name 
          FROM stores s 
          LEFT JOIN regions r ON s.region_id = r.id 
          ORDER BY r.name, s.name";
$stmt = $pdo->query($query);

// 5. Fill the rows
// Note: We leave Agent_Name empty so the admin can fill it in the CSV
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        '', // Empty Agent Name for the admin to fill
        $row['region_name'],
        $row['mall'],
        $row['entity'],
        $row['brand'],
        $row['store_name']
    ]);
}

fclose($output);
exit();
?>