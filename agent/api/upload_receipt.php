<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
ini_set('display_errors', 0); error_reporting(0);

if(isset($_FILES["file"]["name"])){
    // 1. Setup Folders
    $root_path = $_SERVER['DOCUMENT_ROOT']; 
    $folder_path = "/uploads/" . date("Y") . "/" . date("m") . "/";
    $target_dir = $root_path . $folder_path;
    
    if (!file_exists($target_dir)) mkdir($target_dir, 0755, true);
    
    $fileName = time() . "_" . basename($_FILES["file"]["name"]);
    $target_file = $target_dir . $fileName;
    
    // Relative URL to save in DB later
    $db_url = "uploads/" . date("Y") . "/" . date("m") . "/" . $fileName;
    
    // 2. Move File
    if(move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)){
        // SUCCESS: Return the URL to the App
        echo json_encode(["status" => "success", "image_url" => $db_url]);
    } else {
        echo json_encode(["status" => "error", "message" => "File Write Failed"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "No file received"]);
}
?>