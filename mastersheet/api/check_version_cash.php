<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    "latest_version" => 1.0, 
    "mandatory" => true, 
    "download_url" => "https://apparelgroup.anomak.co.in/mastersheet/api/dummy.apk",
    "release_notes" => "Initial Release with OTA updates, dual signatures, and multi-currency support."
]);
?>