<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'db.php'; 

if (isset($conn) && $conn instanceof mysqli) {
    echo "✅ Success! mydb.php is loaded, and \$conn is ready to use.";
} else {
    echo "❌ Fail! mydb.php loaded, but \$conn variable is not working.";
}
?>