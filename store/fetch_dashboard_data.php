<?php
require_once 'db.php';

$store_code = "system_migration"; // Replace with dynamic store selection later

// 1. Fetch Total Cash Metrics
$cashQuery = "SELECT SUM(total_amount) as total_sales, SUM(physical_cash) as total_cash FROM shop_visits";
$cashResult = $conn->query($cashQuery);
$cashMetrics = $cashResult->fetch_assoc();

// 2. Fetch IT Assets Inventory
$itAssetsQuery = "SELECT sku, item_name, dispatch_qty, dispatched_at FROM ict_dispatch_logs WHERE store_code = '$store_code' ORDER BY dispatched_at DESC LIMIT 10";
$itAssets = $conn->query($itAssetsQuery);

// 3. Fetch Normal Inventory
$normalInvQuery = "SELECT sku, dispatch_qty, dispatched_at FROM dispatch_logs WHERE store_code = '$store_code' ORDER BY dispatched_at DESC LIMIT 10";
$normalInv = $conn->query($normalInvQuery);
?>