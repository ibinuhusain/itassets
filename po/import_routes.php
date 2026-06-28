```php
<?php

// Database Credentials
$host = "localhost";
$db   = "anomakio_collection";
$user = "anomakio_collection_adm";
$pass = "@GE3cn093tkl@";

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_assets'])) {

    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        die("Database Connection Failed: " . $conn->connect_error);
    }

    if (!empty($_FILES['asset_file']['tmp_name'])) {

        $file = $_FILES['asset_file']['tmp_name'];

        if (($handle = fopen($file, "r")) !== false) {

            // Read Header Row
            $header = fgetcsv($handle, 0, ",");

            $header = array_map(function ($value) {
                return strtolower(trim($value));
            }, $header);

            // Map CSV Headers
            $hostIdx      = array_search('hostname', $header);
            $brandIdx     = array_search('brand name', $header);
            $storeIdx     = array_search('store code', $header);
            $storeNameIdx = array_search('store name', $header);
            $mallIdx      = array_search('mall name', $header);
            $locationIdx  = array_search('location', $header);
            $mobileIdx    = array_search('mobile number', $header);
            $emailIdx     = array_search('email', $header);
            $routeIdx     = array_search('route code', $header);
            $managerIdx   = array_search('line manager', $header);
            $deviceIdx    = array_search('device type', $header);
            $modelIdx     = array_search('model name', $header);
            $serialIdx    = array_search('serial number', $header);
            $osIdx        = array_search('os', $header);
            $versionIdx   = array_search('version', $header);
            $memoryIdx    = array_search('memory', $header);
            $cpuIdx       = array_search('cpu type', $header);

            if ($hostIdx === false) {

                $message = "
                <div class='alert error'>
                    Hostname column not found in CSV.
                </div>";

            } else {

                $sql = "
                INSERT INTO it_assets
                (
                    hostname,
                    brand_name,
                    store_code,
                    store_name,
                    mall_name,
                    location,
                    mobile_number,
                    email,
                    route_code,
                    line_manager,
                    device_type,
                    model_name,
                    serial_number,
                    os,
                    os_version,
                    memory,
                    cpu_type
                )
                VALUES
                (
                    ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
                )
                ON DUPLICATE KEY UPDATE
                    brand_name = VALUES(brand_name),
                    store_code = VALUES(store_code),
                    store_name = VALUES(store_name),
                    mall_name = VALUES(mall_name),
                    location = VALUES(location),
                    mobile_number = VALUES(mobile_number),
                    email = VALUES(email),
                    route_code = VALUES(route_code),
                    line_manager = VALUES(line_manager),
                    device_type = VALUES(device_type),
                    model_name = VALUES(model_name),
                    serial_number = VALUES(serial_number),
                    os = VALUES(os),
                    os_version = VALUES(os_version),
                    memory = VALUES(memory),
                    cpu_type = VALUES(cpu_type)
                ";

                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    die("Prepare failed: " . $conn->error);
                }

                $processed = 0;
                $errors = 0;

                while (($row = fgetcsv($handle, 0, ",")) !== false) {

                    $hostname    = trim($row[$hostIdx] ?? '');
                    $brandName   = trim($row[$brandIdx] ?? '');
                    $storeCode   = trim($row[$storeIdx] ?? '');
                    $storeName   = trim($row[$storeNameIdx] ?? '');
                    $mallName    = trim($row[$mallIdx] ?? '');
                    $location    = trim($row[$locationIdx] ?? '');
                    $mobile      = trim($row[$mobileIdx] ?? '');
                    $email       = trim($row[$emailIdx] ?? '');
                    $routeCode   = trim($row[$routeIdx] ?? '');
                    $lineManager = trim($row[$managerIdx] ?? '');
                    $deviceType  = trim($row[$deviceIdx] ?? '');
                    $modelName   = trim($row[$modelIdx] ?? '');
                    $serialNum   = trim($row[$serialIdx] ?? '');
                    $os          = trim($row[$osIdx] ?? '');
                    $version     = trim($row[$versionIdx] ?? '');
                    $memory      = trim($row[$memoryIdx] ?? '');
                    $cpuType     = trim($row[$cpuIdx] ?? '');

                    if (empty($hostname)) {
                        continue;
                    }

                    $stmt->bind_param(
                        "sssssssssssssssss",
                        $hostname,
                        $brandName,
                        $storeCode,
                        $storeName,
                        $mallName,
                        $location,
                        $mobile,
                        $email,
                        $routeCode,
                        $lineManager,
                        $deviceType,
                        $modelName,
                        $serialNum,
                        $os,
                        $version,
                        $memory,
                        $cpuType
                    );

                    if ($stmt->execute()) {
                        $processed++;
                    } else {
                        $errors++;
                    }
                }

                fclose($handle);
                $stmt->close();

                $message = "
                <div class='alert success'>
                    <strong>Import Complete!</strong><br>
                    Imported / Updated: <b>{$processed}</b><br>
                    Errors: <b>{$errors}</b>
                </div>";
            }
        }
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>IT Assets Import</title>

<style>
body{
    font-family:Arial, sans-serif;
    background:#f4f6f9;
    padding:40px;
}

.container{
    max-width:700px;
    margin:auto;
    background:#fff;
    padding:30px;
    border-radius:10px;
    box-shadow:0 3px 10px rgba(0,0,0,.1);
}

h1{
    margin-top:0;
    color:#2563eb;
}

input[type=file]{
    width:100%;
    padding:12px;
    border:1px dashed #ccc;
    border-radius:6px;
    margin-top:15px;
}

button{
    width:100%;
    margin-top:20px;
    padding:12px;
    background:#059669;
    border:none;
    color:#fff;
    font-size:16px;
    border-radius:6px;
    cursor:pointer;
}

button:hover{
    background:#047857;
}

.alert{
    padding:15px;
    margin-bottom:20px;
    border-radius:6px;
}

.success{
    background:#dcfce7;
    color:#166534;
}

.error{
    background:#fee2e2;
    color:#991b1b;
}
</style>
</head>
<body>

<div class="container">

    <?php echo $message; ?>

    <h1>IT Assets Import</h1>

    <p>
        Upload CSV exported from Excel containing:
        Hostname, Brand Name, Store Code, Store Name,
        Mall Name, Location, Mobile Number, Email,
        Route Code, Line Manager, Device Type,
        Model Name, Serial Number, OS, Version,
        Memory and CPU Type.
    </p>

    <form method="POST" enctype="multipart/form-data">

        <input
            type="file"
            name="asset_file"
            accept=".csv"
            required>

        <button
            type="submit"
            name="import_assets">
            Import IT Assets
        </button>

    </form>

</div>

</body>
</html>