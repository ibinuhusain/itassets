<?php
// TEMPORARY ERROR REPORTING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Lock timezones to KSA to match the physical store locations
date_default_timezone_set('Asia/Riyadh'); 

require_once '../includes/auth.php';
requireAdminOrReport(); 

$pdo = getConnection();
// Force MySQL timezone
$pdo->exec("SET time_zone = '+03:00'");

$today = date('Y-m-d');

try {
    // Fetch all raw rows for today without aggregating
    $stmt = $pdo->prepare("
        SELECT 
            sv.id as visit_id, 
            sv.visit_date, 
            sv.physical_cash, 
            sv.z_report,
            sv.currency,
            u.name as agent_name, 
            s.id as store_code, 
            s.name as store_name
        FROM shop_visits sv
        LEFT JOIN users u ON sv.agent_id = u.id
        LEFT JOIN stores s ON sv.shop_id = s.id
        WHERE DATE(sv.visit_date) = ?
        ORDER BY sv.visit_date DESC
    ");
    $stmt->execute([$today]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}

$running_total = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debug: Today's Cash Rows</title>
    <style>
        body { font-family: sans-serif; background: #111; color: #eee; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: #222; padding: 20px; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #444; }
        th { background: #333; color: #fff; }
        .high-value { color: #f87171; font-weight: bold; } /* Highlights big numbers in red */
        .total-row { font-size: 20px; font-weight: bold; background: #10b981; color: #fff; }
    </style>
</head>
<body>

<div class="container">
    <h2>Raw Data Dump: Cash Collections</h2>
    <p><strong>System 'Today' Date:</strong> <?php echo $today; ?> (Asia/Riyadh Timezone)</p>
    <p><strong>Total Records Found:</strong> <?php echo count($records); ?></p>

    <table>
        <thead>
            <tr>
                <th>Visit ID</th>
                <th>Time Logged</th>
                <th>Agent</th>
                <th>Store</th>
                <th>Z-Report</th>
                <th>Physical Cash</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($records as $row): 
                $cash = (float)$row['physical_cash'];
                $running_total += $cash;
                
                // Flag unusually high numbers (e.g., above 50,000) for easy spotting
                $cash_class = ($cash > 50000) ? 'high-value' : '';
            ?>
                <tr>
                    <td>#<?php echo $row['visit_id']; ?></td>
                    <td><?php echo date('h:i:s A', strtotime($row['visit_date'])); ?></td>
                    <td><?php echo htmlspecialchars($row['agent_name'] ?? 'Unknown'); ?></td>
                    <td><?php echo htmlspecialchars($row['store_name'] ?? 'Unknown'); ?> (#<?php echo $row['store_code']; ?>)</td>
                    <td><?php echo number_format($row['z_report'] ?? 0, 2); ?></td>
                    <td class="<?php echo $cash_class; ?>"><?php echo number_format($cash, 2) . ' ' . ($row['currency'] ?? 'SAR'); ?></td>
                </tr>
            <?php endforeach; ?>
            
            <tr class="total-row">
                <td colspan="5" style="text-align: right; padding-right: 20px;">GRAND TOTAL:</td>
                <td><?php echo number_format($running_total, 2); ?></td>
            </tr>
        </tbody>
    </table>
</div>

</body>
</html>