<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
require_once 'db.php'; // Ensure this points to your database connection file

try {
    $agent_id = $_GET['agent_id'] ?? '';
    if(empty($agent_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Agent ID required']);
        exit;
    }

    $balances = [];

    // 1. Get ALL-TIME Total Collected (Grouped by Currency)
    // Removed the DATE() restriction so unbanked cash carries over day-to-day
    $stmt1 = $conn->prepare("SELECT currency, SUM(physical_cash) as total_collected FROM shop_visits WHERE agent_id = ? GROUP BY currency");
    $stmt1->bind_param("i", $agent_id);
    $stmt1->execute();
    $res1 = $stmt1->get_result();
    
    while($row = $res1->fetch_assoc()) {
        $curr = $row['currency'] ?: 'SAR';
        $balances[$curr] = (float)$row['total_collected'];
    }
    $stmt1->close();

    // 2. Subtract ALL-TIME Total Banked (Grouped by Currency)
    // Removed the DATE() restriction here as well
    $stmt2 = $conn->prepare("SELECT currency, SUM(deposited_cash) as total_banked FROM bank_submissions WHERE agent_id = ? GROUP BY currency");
    $stmt2->bind_param("i", $agent_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    
    while($row = $res2->fetch_assoc()) {
        $curr = $row['currency'] ?: 'SAR';
        if(!isset($balances[$curr])) {
            $balances[$curr] = 0;
        }
        $balances[$curr] -= (float)$row['total_banked'];
    }
    $stmt2->close();

    // 3. Format the Output
    $display_text = [];
    foreach($balances as $curr => $amount) {
        // Only display if there is a positive pending balance
        if($amount > 0) {
            $display_text[] = number_format($amount, 2) . ' ' . $curr;
        }
    }

    // Default to 0.00 SAR if everything is banked or nothing is collected
    if(empty($display_text)) {
        $final_text = "0.00 SAR"; 
    } else {
        $final_text = implode(" | ", $display_text);
    }

    echo json_encode(['status' => 'success', 'balance_text' => $final_text]);

} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

if(isset($conn)) $conn->close();
?>