<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");


// Start session
require_once '../includes/auth.php';

requireAgent();

$pdo = getConnection();
$agent_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Get agent's assignments for today
$stmt = $pdo->prepare("
    SELECT 
        da.*, 
        s.name as store_name, 
        s.address as store_address,
        s.mall,
        s.entity,
        s.brand,
        r.name as region_name,
        COALESCE(SUM(c.amount_collected), 0) as collected_amount
    FROM daily_assignments da
    JOIN stores s ON da.store_id = s.id
    LEFT JOIN regions r ON s.region_id = r.id
    LEFT JOIN collections c ON da.id = c.assignment_id
    WHERE da.agent_id = ? AND DATE(da.date_assigned) = ?
    GROUP BY da.id
    ORDER BY s.name
");
$stmt->execute([$agent_id, $today]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_target = 0;
$total_collected = 0;
$completed_count = 0;
$total_assignments = count($assignments);

foreach ($assignments as $assignment) {
    $total_target += $assignment['target_amount'];
    $total_collected += $assignment['collected_amount'];
    if ($assignment['status'] === 'completed') {
        $completed_count++;
    }
}

$collection_percentage = $total_target > 0 ? round(($total_collected / $total_target) * 100, 2) : 0;
$remaining_assignments = $total_assignments - $completed_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apparel Collection - Dashboard</title>
    <link rel="stylesheet" href="../css/mobile_agent.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />

</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Apparel Collection</h1>
            <p>Dashboard - <?php echo date('M j, Y'); ?></p>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Agent'); ?></p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>SAR <?php echo number_format($total_collected, 2); ?></h3>
                <p>Total Collected</p>
            </div>
            <div class="stat-card">
                <h3>SAR <?php echo number_format($total_target, 2); ?></h3>
                <p>Target Amount</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $collection_percentage; ?>%</h3>
                <p>Completion Rate</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $remaining_assignments; ?></h3>
                <p>Stores Remaining</p>
            </div>
        </div>

        <div class="content-section">
            <h2>Your Assigned Stores (<?php echo date('M j, Y'); ?>)</h2>
            <?php if (count($assignments) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Store Name</th>
                                <th>Region</th>
                                <th>Mall</th>
                                <th>Target</th>
                                <th>Collected</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                                <?php
                                $status = $assignment['status'] ?? 'pending';
                                $status_class = $status === 'completed' ? 'status-completed' : 'status-pending';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assignment['store_name']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['region_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['mall'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($assignment['target_amount'], 2); ?></td>
                                    <td><?php echo number_format($assignment['collected_amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="store.php?assignment_id=<?php echo $assignment['id']; ?>" class="btn">
                                            <?php echo $status === 'completed' ? 'View' : 'Manage'; ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No assignments found for today.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-navigation">
        <a href="dashboard_agent.php" class="nav-item active">
            <span class="material-symbols-outlined">home</span>
            <span>Dashboard</span>
        </a>
        <a href="submissions_agent.php" class="nav-item">
            <span class="material-symbols-outlined">receipt_long</span>
            <span>Submissions</span>
        </a>
        <a href="store_agent.php" class="nav-item">
            <span class="material-symbols-outlined">storefront</span>
            <span>Store</span>
        </a>
    </div>
</body>
</html>