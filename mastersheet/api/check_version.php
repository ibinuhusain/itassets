<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// When you have a new APK, simply change the "1.0" to "1.1", "1.2", etc.
// Upload the new APK to your server and change the download_url to match.
echo json_encode([
    "latest_version" => 1.0, 
    "mandatory" => false, 
    "download_url" => "https://apparelgroupksa.com/mastersheet/api/apparel_labeler_v2.0.apk",
    "release_notes" => "Added universal bold printing, dynamic paper size auto-calibration, and fine-tune UI sliders."
]);
?>