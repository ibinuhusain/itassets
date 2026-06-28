<?php
function getCurrentUser() {
    return $_SESSION['iam_user']['username'] ?? 'Unknown';
}

function getFullName() {
    return $_SESSION['iam_user']['name'] ?? 'User';
}

function getUserRole() {
    $it_roles = $_SESSION['iam_user']['roles']['it_procurement'] ?? [];
    return $it_roles[0] ?? 'Admin';
}

function isStoreUser() {
    return (getUserRole() === 'store' || getUserRole() === 'Store');
}

function getStoreName() {
    return $_SESSION['iam_user']['store_name'] ?? 'N/A';
}

function getStoreId() {
    return $_SESSION['iam_user']['store_id'] ?? 'N/A';
}

function api_request($endpoint, $data = null) {
    $url = 'procurement_api.php?action=' . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}
?>