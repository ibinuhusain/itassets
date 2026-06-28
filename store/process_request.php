<?php
session_start();
require_once 'db.php';

// 1. Security Check: Ensure the user is actually logged in
if (!isset($_SESSION['store_id'])) {
    die("Unauthorized access. Please log in.");
}

// 2. Process the Form Data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Sanitize inputs to prevent SQL Injection
    $store_id = $conn->real_escape_string($_POST['store_id']);
    $category = $conn->real_escape_string($_POST['category']);
    $item_name = $conn->real_escape_string($_POST['item_name']);
    $qty = (int) $_POST['qty']; // Force to integer
    $reason = $conn->real_escape_string($_POST['reason']);
    
    // Future-proofing: Set initial status so managers can filter by this later
    $status = 'pending_approval';

    // 3. Insert into the database
    $insertQuery = "INSERT INTO inventory_requests (store_id, category, item_name, qty, reason, status) 
                    VALUES ('$store_id', '$category', '$item_name', $qty, '$reason', '$status')";

    if ($conn->query($insertQuery) === TRUE) {
        // Success! Redirect back to the dashboard and append a success flag to the URL
        header("Location: dashboard.php?request=success#request-inventory");
        exit();
    } else {
        // Display error if the query fails (useful for debugging)
        die("Fatal Error submitting request: " . $conn->error);
    }
} else {
    // If someone tries to access this file directly without submitting the form
    header("Location: dashboard.php");
    exit();
}
?>