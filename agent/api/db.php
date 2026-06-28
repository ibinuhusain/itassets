<?php
// 1. Set Universal PHP Time
date_default_timezone_set('Asia/Riyadh'); // or 'Asia/Kolkata'

// 2. Database Credentials
$host = "localhost";
$db   = "anomakio_collection";
$user = "anomakio_collection_adm";
$pass = "@GE3cn093tkl@";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "DB Connection Failed"]));
}

// 3. Set Universal Database Time
$conn->query("SET time_zone = '+03:00'"); // or '+05:30'
?>