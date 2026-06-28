<?php
// WARNING: Do not put any blank lines or spaces before the <?php tag above!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Pull in your mysqli connection. Change this to 'db.php' if that's the actual filename.
require_once 'db.php'; 

$migrationMessage = "";
$alertClass = "";
$migrationSuccess = false;

try {
    // ==========================================
    // 1. EXECUTE MIGRATION LOGIC FIRST
    // ==========================================
    $conn->begin_transaction();

    // Prepare Inventory Insert
    $insertInventory = $conn->prepare("
        INSERT INTO ict_inventory (sku, category, item_name, model_number, quantity_on_hand, vendor, updated_at) 
        VALUES (?, ?, ?, ?, 0, 'Internal/Staging', NOW())
        ON DUPLICATE KEY UPDATE 
            item_name = VALUES(item_name), 
            updated_at = NOW()
    ");
    if (!$insertInventory) throw new Exception("Failed to prepare inventory statement: " . $conn->error);

    // Prepare Dispatch Insert
    $insertDispatch = $conn->prepare("
        INSERT INTO ict_dispatch_logs (dispatch_id, sku, item_name, dispatch_qty, store_code, person_name, username, dispatched_at) 
        VALUES (?, ?, ?, 1, ?, ?, ?, NOW())
    ");
    if (!$insertDispatch) throw new Exception("Failed to prepare dispatch statement: " . $conn->error);

    // Bind parameters for Inventory (s = string)
    $inv_sku = ""; $inv_cat = ""; $inv_name = ""; $inv_model = "";
    $insertInventory->bind_param("ssss", $inv_sku, $inv_cat, $inv_name, $inv_model);

    // Bind parameters for Dispatch
    $dsp_id = ""; $dsp_sku = ""; $dsp_name = ""; $dsp_store = null; $dsp_person = ""; $dsp_user = "";
    $insertDispatch->bind_param("ssssss", $dsp_id, $dsp_sku, $dsp_name, $dsp_store, $dsp_person, $dsp_user);

    // ------------------------------------------
    // Process Store Assets (from it_assets)
    // ------------------------------------------
    $resultStore = $conn->query("SELECT * FROM it_assets");
    if (!$resultStore) throw new Exception("Failed to query it_assets: " . $conn->error);
    $storeAssets = $resultStore->fetch_all(MYSQLI_ASSOC);

    foreach ($storeAssets as $asset) {
        // Set Inventory Params
        $inv_sku = !empty($asset['serial_number']) ? $asset['serial_number'] : 'AST-' . time() . '-' . rand(100, 999);
        $inv_cat = $asset['device_type'];
        $inv_name = trim($asset['device_type'] . ' - ' . $asset['model_name']);
        $inv_model = $asset['model_name'];
        $insertInventory->execute();

        // Set Dispatch Params
        $dsp_id = 'DSP-ST-' . (!empty($asset['id']) ? $asset['id'] : substr($inv_sku, 0, 8));
        $dsp_sku = $inv_sku;
        $dsp_name = $inv_name;
        $dsp_store = $asset['store_code'];
        $dsp_person = $asset['line_manager'];
        $dsp_user = 'system_migration';
        $insertDispatch->execute();
    }

    // ------------------------------------------
    // Process Employee Assets (from person)
    // ------------------------------------------
    $resultEmp = $conn->query("SELECT * FROM person");
    if (!$resultEmp) throw new Exception("Failed to query person: " . $conn->error);
    $employeeAssets = $resultEmp->fetch_all(MYSQLI_ASSOC);

    foreach ($employeeAssets as $empAsset) {
        // Set Inventory Params
        $inv_sku = !empty($empAsset['serial_number']) ? $empAsset['serial_number'] : 'EMP-' . time() . '-' . rand(100, 999);
        $inv_cat = $empAsset['device_type'];
        $inv_name = trim($empAsset['device_type'] . ' - ' . $empAsset['model_name']);
        $inv_model = $empAsset['model_name'];
        $insertInventory->execute();

        // Set Dispatch Params
        $dsp_id = 'DSP-EMP-' . (!empty($empAsset['id']) ? $empAsset['id'] : substr($inv_sku, 0, 8));
        $dsp_sku = $inv_sku;
        $dsp_name = $inv_name;
        $dsp_store = null; // Employees don't get a store code
        $dsp_person = $empAsset['emp_name'];
        $dsp_user = $empAsset['emp_id'];
        $insertDispatch->execute();
    }

    $conn->commit();
    $migrationSuccess = true;
    $migrationMessage = "Migration Script Executed Successfully! Live data is displayed below.";
    $alertClass = "bg-green-100 border-green-500 text-green-700";

} catch (Exception $e) {
    $conn->rollback();
    $migrationMessage = "Migration Failed: " . $e->getMessage();
    $alertClass = "bg-red-100 border-red-500 text-red-700";
}

// ==========================================
// 2. FETCH DASHBOARD UI METRICS
// ==========================================
$totalAssets = 0; $storeAssetsCount = 0; $employeeAssetsCount = 0;
$categoryLabels = []; $categoryData = [];
$modelLabels = []; $modelData = [];

if ($migrationSuccess) {
    try {
        $totalAssets = $conn->query("SELECT COUNT(*) FROM ict_dispatch_logs")->fetch_row()[0];
        $storeAssetsCount = $conn->query("SELECT COUNT(*) FROM ict_dispatch_logs WHERE store_code IS NOT NULL")->fetch_row()[0];
        $employeeAssetsCount = $conn->query("SELECT COUNT(*) FROM ict_dispatch_logs WHERE store_code IS NULL")->fetch_row()[0];

        $catStmt = $conn->query("SELECT category, COUNT(*) as count FROM ict_inventory GROUP BY category");
        while ($row = $catStmt->fetch_assoc()) {
            $categoryLabels[] = ucfirst($row['category']);
            $categoryData[] = $row['count'];
        }

        $modelStmt = $conn->query("SELECT model_number, COUNT(*) as count FROM ict_inventory GROUP BY model_number ORDER BY count DESC LIMIT 5");
        while ($row = $modelStmt->fetch_assoc()) {
            $modelLabels[] = $row['model_number'];
            $modelData[] = $row['count'];
        }
    } catch (Exception $e) {
        $migrationMessage = "Dashboard Data Error: " . $e->getMessage();
        $alertClass = "bg-yellow-100 border-yellow-500 text-yellow-700";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto-Dispatch Migration & Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal p-8">

    <div class="max-w-7xl mx-auto">
        <div class="border-l-4 p-4 mb-8 shadow-sm rounded-r <?php echo $alertClass; ?>" role="alert">
            <p class="font-bold">System Status</p>
            <p><?php echo htmlspecialchars($migrationMessage); ?></p>
        </div>

        <h1 class="text-3xl font-bold text-gray-800 mb-8">Asset Migration Dashboard</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                <h3 class="text-gray-500 text-sm font-semibold uppercase tracking-wider">Total Dispatched Assets</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $totalAssets; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                <h3 class="text-gray-500 text-sm font-semibold uppercase tracking-wider">Dispatched to Stores</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $storeAssetsCount; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-purple-500">
                <h3 class="text-gray-500 text-sm font-semibold uppercase tracking-wider">Assigned to Employees</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $employeeAssetsCount; ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-800 font-bold mb-4">Top 5 Device Models</h3>
                <canvas id="modelChart" height="200"></canvas>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-800 font-bold mb-4">Inventory by Category</h3>
                <div class="flex justify-center">
                    <canvas id="categoryChart" height="200" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        const catLabels = <?php echo json_encode($categoryLabels); ?>;
        const catData = <?php echo json_encode($categoryData); ?>;
        const modelLabels = <?php echo json_encode($modelLabels); ?>;
        const modelData = <?php echo json_encode($modelData); ?>;

        // Render Bar Chart if data exists
        if (modelLabels.length > 0) {
            new Chart(document.getElementById('modelChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: modelLabels,
                    datasets: [{
                        label: 'Device Count',
                        data: modelData,
                        backgroundColor: ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444']
                    }]
                },
                options: { 
                    responsive: true, 
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } 
                }
            });
        }

        // Render Doughnut Chart if data exists
        if (catLabels.length > 0) {
            new Chart(document.getElementById('categoryChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: catLabels,
                    datasets: [{
                        data: catData,
                        backgroundColor: ['#f59e0b', '#3b82f6', '#ef4444']
                    }]
                }
            });
        }
    </script>
</body>
</html>