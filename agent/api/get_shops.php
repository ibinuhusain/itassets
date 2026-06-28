<?php
// 1. HEADERS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// 2. ERROR HANDLING
ini_set('display_errors', 0);
error_reporting(0);

// 3. DATABASE CONNECTION (Your Credentials)
require_once 'db.php';   

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["error" => "DB Connection Failed"]);
    exit();
}

// 4. GET AGENT ID FROM APP
$agent_id = $_GET['agent_id'] ?? '';
$today = date('Y-m-d'); // Current Date

if(empty($agent_id)) {
    echo json_encode([]); 
    exit();
}

try {
    // 5. YOUR EXACT SQL QUERY
    $sql = "SELECT 
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
            ORDER BY s.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$agent_id, $today]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 6. FORMAT DATA FOR THE APP
    $app_data = [];
    foreach($assignments as $row) {
        
        // Determine Status based on Collection
        $status = "pending";
        if($row['status'] === 'completed') {
            $status = "completed"; // Honor the new completed status from save_visit
        } elseif($row['collected_amount'] > 0) {
            $status = "visited";
        } elseif (isset($row['status'])) {
            $status = $row['status'];
        }

        // Map database columns to App Keys
        $app_data[] = [
            "id" => $row['store_id'],        
            "assignment_id" => $row['id'],   
            "name" => $row['store_name'],    
            "address" => $row['store_address'],
            "status" => $status,
            "collected" => $row['collected_amount'],
            // FIX: Added brand and region_name to the JSON output
            "brand" => $row['brand'] ?? '',
            "region_name" => $row['region_name'] ?? ''
        ];
    }

    echo json_encode($app_data);

} catch (PDOException $e) {
    echo json_encode(["error" => "Query Failed", "details" => $e->getMessage()]);
}
?>