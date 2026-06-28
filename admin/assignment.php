<?php
// TEMPORARY DEBUGGING LINES - REMOVE LATER
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/auth.php';
requireAdmin();
// ... rest of your code ...

$pdo = getConnection();
$success_message = $error_message = null;

// 1. Handle Manual Assignment (PERMANENT - Removed Date Restriction)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_shops'])) {
    $agent_id = $_POST['agent_id'];
    $selected_stores = $_POST['stores'] ?? [];
    
    $added_count = 0;
    $skipped_count = 0;

    // OPTIMIZATION: Fetch all current assignments for this agent into a flat array once
    $stmt = $pdo->prepare("SELECT store_id FROM daily_assignments WHERE agent_id = ?");
    $stmt->execute([$agent_id]);
    $existing_stores = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $existing_stores_map = array_flip($existing_stores); // Faster lookup

    // OPTIMIZATION: Use a transaction for bulk inserts
    try {
        $pdo->beginTransaction();
        $ins_stmt = $pdo->prepare("INSERT INTO daily_assignments (agent_id, store_id, date_assigned, status) VALUES (?, ?, NOW(), 'pending')");
        
        foreach ($selected_stores as $store_id) {
            if (!isset($existing_stores_map[$store_id])) {
                $ins_stmt->execute([$agent_id, $store_id]);
                $existing_stores_map[$store_id] = true; // Prevent duplicates if submitted twice in array
                $added_count++;
            } else {
                $skipped_count++;
            }
        }
        $pdo->commit();
        $success_message = "Assigned $added_count shops." . ($skipped_count > 0 ? " ($skipped_count skipped as they are already assigned to this agent)." : "");
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Database error during assignment: " . $e->getMessage();
    }
}

// 1.5 Handle Reset to Pending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_assignments'])) {
    $reset_pairs = $_POST['reset_pairs'] ?? [];
    $reset_count = 0;
    
    // OPTIMIZATION: Use transaction for bulk updates
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE daily_assignments SET status = 'pending' WHERE agent_id = ? AND store_id = ?");
        
        foreach ($reset_pairs as $pair) {
            list($a_id, $s_id) = explode('_', $pair);
            $stmt->execute([$a_id, $s_id]);
            $reset_count++;
        }
        $pdo->commit();
        if($reset_count > 0) {
            $success_message = "Successfully reset $reset_count shops back to Pending.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Database error during reset: " . $e->getMessage();
    }
}

// 2. Handle Excel/CSV Import (PERMANENT - Removed Date Restriction)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['excel_file']['tmp_name'];
        $file_name = $_FILES['excel_file']['name'];
        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
        
        $importedCount = 0;
        $skippedCount = 0;
        $rows = [];

        // --- STEP A: LOAD THE DATA ---
        if ($ext === 'csv') {
            if (($handle = fopen($file_tmp, "r")) !== FALSE) {
                fgetcsv($handle); 
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $rows[] = $data;
                }
                fclose($handle);
            }
        } else {
            if (class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                try {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_tmp);
                    $rows = $spreadsheet->getActiveSheet()->toArray();
                    array_shift($rows);
                } catch (Exception $e) {
                    $error_message = "Excel Error: " . $e->getMessage();
                }
            } else {
                $error_message = "Excel library not found. Please upload a .csv file instead.";
            }
        }

        // --- STEP B: PROCESS THE ROWS ---
        if (!empty($rows)) {
            // OPTIMIZATION: Pre-fetch maps to avoid N+1 queries
            $agentsMap = [];
            // FIX: Bulletproof LIKE search to catch all multi-role agents
            $agentStmt = $pdo->query("SELECT id, name FROM users WHERE LOWER(role) LIKE '%agent%' ORDER BY name ASC");
            while ($row = $agentStmt->fetch(PDO::FETCH_ASSOC)) {
                $agentsMap[trim($row['name'])] = $row['id'];
            }

            $storesMap = [];
            $storeStmt = $pdo->query("SELECT name, id FROM stores");
            while ($row = $storeStmt->fetch(PDO::FETCH_ASSOC)) {
                $storesMap[trim($row['name'])] = $row['id'];
            }

            $assignmentsMap = [];
            $assignStmt = $pdo->query("SELECT CONCAT(agent_id, '_', store_id) as pair FROM daily_assignments");
            while ($row = $assignStmt->fetch(PDO::FETCH_ASSOC)) {
                $assignmentsMap[$row['pair']] = true;
            }

            try {
                $pdo->beginTransaction();
                $insStmt = $pdo->prepare("INSERT INTO daily_assignments (agent_id, store_id, date_assigned, status) VALUES (?, ?, NOW(), 'pending')");

                foreach ($rows as $row) {
                    if (empty($row[0]) || empty($row[5])) continue;

                    $agent_name = trim($row[0]);
                    $store_name = trim($row[5]);

                    // Look up IDs in PHP memory (instant)
                    if (isset($agentsMap[$agent_name]) && isset($storesMap[$store_name])) {
                        $a_id = $agentsMap[$agent_name];
                        $s_id = $storesMap[$store_name];
                        $pair_key = $a_id . '_' . $s_id;

                        if (!isset($assignmentsMap[$pair_key])) {
                            $insStmt->execute([$a_id, $s_id]);
                            $assignmentsMap[$pair_key] = true; // Mark as added to prevent duplicates within the same file
                            $importedCount++;
                        } else {
                            $skippedCount++;
                        }
                    }
                }
                $pdo->commit();
                $success_message = "Successfully imported $importedCount assignments. $skippedCount duplicates were skipped.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = "Database error during import: " . $e->getMessage();
            }
        }
    }
}

// 3. Fetch Data for Display

// Fetch Agents
// FIX: Bulletproof LIKE search for dropdown population
$agents_stmt = $pdo->query("SELECT id, name FROM users WHERE LOWER(role) LIKE '%agent%' ORDER BY name ASC");
$agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Stores (Added City)
$stores_stmt = $pdo->query("SELECT s.id, s.name, s.city, s.mall, s.entity, s.brand, r.name as region_name FROM stores s LEFT JOIN regions r ON s.region_id = r.id ORDER BY s.name ASC");
$stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);

// REVERTED: Back to your original, proven query to fix the data crash!
$assignments_stmt = $pdo->query("
    SELECT 
        da.agent_id,
        da.store_id,
        da.status, 
        u.name as agent_name, 
        s.id as s_id,
        s.name as store_name, 
        s.city,
        s.mall, 
        s.entity, 
        s.brand, 
        r.name as region_name,
        (SELECT MAX(visit_date) FROM shop_visits sv WHERE sv.shop_id = da.store_id AND sv.agent_id = da.agent_id) as last_visit
    FROM daily_assignments da
    JOIN users u ON da.agent_id = u.id
    JOIN stores s ON da.store_id = s.id
    LEFT JOIN regions r ON s.region_id = r.id
    ORDER BY da.status DESC, u.name, s.name
");
$all_assignments = $assignments_stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - Apparels Collection</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
    <style>
        :root {
            --primary-bg: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            --sidebar-bg: rgba(23, 25, 49, 0.95);
            --background-color: #0f0f1a;
            --card-bg: rgba(255, 255, 255, 0.08);
            --card-border: rgba(255, 255, 255, 0.1);
            --input-bg: rgba(255, 255, 255, 0.05);
            --input-border: rgba(255, 255, 255, 0.15);
            --table-header-bg: rgba(255, 255, 255, 0.1);
            --table-row-hover: rgba(255, 255, 255, 0.05);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --accent-color: #6366f1;
            --accent-hover: #4f46e5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --elevation-1: 0 4px 6px rgba(0, 0, 0, 0.1);
            --elevation-2: 0 10px 15px rgba(0, 0, 0, 0.2);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--primary-bg);
            background-attachment: fixed;
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container { display: flex; min-height: 100vh; position: relative; width: 100%; }

        /* Sidebar */
        .sidebar { width: 280px; height: 100vh; position: fixed; left: 0; top: 0; background: var(--sidebar-bg); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); z-index: 100; transition: transform 0.3s ease; overflow-y: auto; }
        .sidebar-header { padding: 24px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-logo img { height: 40px; min-width: 100% !important; object-fit: contain !important; border-radius: 8px !important; display: block !important; }
        .sidebar-nav { padding: 15px; }
        .nav-item { display: flex; align-items: center; padding: 12px 15px; margin-bottom: 5px; border-radius: 8px; color: var(--text-secondary); text-decoration: none; transition: var(--transition); }
        .nav-item:hover, .nav-item.active { background: rgba(255, 255, 255, 0.05); color: var(--text-primary); }
        .nav-item.active { background: rgba(99, 102, 241, 0.1); color: var(--accent-color); }
        .nav-item .material-symbols-outlined { margin-right: 12px; font-size: 24px; }

        /* Main Content */
        .main-content { flex: 1; margin-left: 280px; min-height: 100vh; display: flex; flex-direction: column; transition: margin-left 0.3s ease; width: calc(100% - 280px); }

        /* Top Header */
        .top-header { display: flex; justify-content: space-between; align-items: center; padding: 0 24px; height: 70px; background: var(--card-bg); box-shadow: var(--elevation-1); position: sticky; top: 0; z-index: 99; border-bottom: 1px solid var(--card-border); }
        .header-left { display: flex; align-items: center; gap: 15px; }
        .hamburger { display: none; flex-direction: column; justify-content: space-between; width: 30px; height: 21px; cursor: pointer; z-index: 101; }
        .hamburger span { display: block; height: 3px; width: 100%; background: var(--text-primary); border-radius: 3px; transition: var(--transition); }
        .header-title { font-size: 20px; font-weight: 600; color: var(--text-primary); }
        .logout-btn { display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: rgba(239, 68, 68, 0.1); color: var(--danger); border-radius: 8px; text-decoration: none; transition: var(--transition); }
        .logout-btn:hover { background: rgba(239, 68, 68, 0.2); }

        /* Content Area */
        .content { flex: 1; padding: 24px; background: var(--background-color); overflow-y: auto; min-height: calc(100vh - 70px); }
        .page-title { font-size: 24px; font-weight: 600; margin-bottom: 24px; color: var(--text-primary); position: relative; padding-bottom: 12px; }
        .page-title::after { content: ''; position: absolute; bottom: 0; left: 0; width: 60px; height: 3px; background: var(--accent-color); border-radius: 2px; }

        /* Cards & Forms */
        .card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: var(--elevation-1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: var(--text-secondary); }
        input[type="text"], select { width: 100%; padding: 10px 15px; border: 1px solid var(--input-border); border-radius: 8px; font-size: 14px; color: var(--text-primary); background: rgb(33, 33, 43); transition: var(--transition); }
        input:focus, select:focus { outline: none; border-color: var(--accent-color); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.3); }

        /* Table Filters */
        .filter-input { width: 100%; padding: 8px 10px; background: rgba(0, 0, 0, 0.2); border: 1px solid var(--card-border); border-radius: 6px; color: var(--text-primary); font-size: 12px; transition: var(--transition); }
        .filter-input:focus { outline: none; border-color: var(--accent-color); background: rgba(0, 0, 0, 0.4); }
        .modern-table thead tr:nth-child(2) th { padding-top: 0; }

        /* Buttons */
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; border-radius: 8px; font-weight: 500; font-size: 14px; cursor: pointer; transition: var(--transition); border: none; text-decoration: none; margin-right: 10px; background: var(--accent-color); color: white; }
        .btn:hover { background: var(--accent-hover); transform: translateY(-2px); }
        .btn-success { background: var(--success); }
        .btn-success:hover { background: #0da271; }
        .btn-warning { background: var(--warning); color: #1f2937; }

        /* Alerts */
        .alert { padding: 12px 20px; margin-bottom: 20px; border-radius: 8px; font-size: 14px; display: flex; align-items: center; gap: 10px; background: var(--card-bg); border-left: 4px solid transparent; }
        .alert-success { background: rgba(16, 185, 129, 0.15); color: #6EE7B7; border-left-color: var(--success); }
        .alert-danger { background: rgba(239, 68, 68, 0.15); color: #FCA5A5; border-left-color: var(--danger); }

        /* Tables */
        .table-scroll-container { max-height: 400px; overflow-y: auto; border: 1px solid var(--card-border); border-radius: 8px; background: var(--card-bg); margin-top: 10px; box-shadow: var(--elevation-1); }
        .modern-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .modern-table thead { position: sticky; top: 0; z-index: 10; }
        .modern-table th { background: var(--table-header-bg); padding: 12px 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--card-border); }
        .modern-table td { padding: 12px 15px; border-bottom: 1px solid var(--card-border); font-size: 14px; color: var(--text-primary); }
        .modern-table tbody tr:hover { background: var(--table-row-hover); }

        /* Badges */
        .badge { display: inline-block; padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 4px; }
        .badge-pending { background: rgba(245, 158, 11, 0.2); color: #F59E0B; }
        .badge-completed { background: rgba(16, 185, 129, 0.2); color: #10B981; }

        .import-section { background: var(--card-bg); padding: 20px; border-radius: 8px; border: 1px solid var(--card-border); margin-bottom: 25px; }
        <hr> { margin: 30px 0; border: none; border-top: 1px solid var(--card-border); }

        @media (max-width: 992px) {
            .hamburger { display: flex; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="../images/logo.png" alt="Apparel Collection Logo" height="40">
                </div>
            </div>
            
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <span class="material-symbols-outlined">home</span>
                    <span>Dashboard</span>
                </a>
                <a href="assignments.php" class="nav-item active">
                    <span class="material-symbols-outlined">assignment</span>
                    <span>Assignments</span>
                </a>
                <a href="agents.php" class="nav-item">
                    <span class="material-symbols-outlined">people</span>
                    <span>Agents</span>
                </a>
                <a href="management.php" class="nav-item">
                    <span class="material-symbols-outlined">settings</span>
                    <span>Management</span>
                </a>
                <a href="store_data.php" class="nav-item">
                    <span class="material-symbols-outlined">storefront</span>
                    <span>Store Data</span>
                </a>
                <a href="bank_approvals.php" class="nav-item">
                    <span class="material-symbols-outlined">receipt_long</span>
                    <span>Bank Approvals</span>
                </a>
            </div>
        </div>
        
        <div class="main-content">
            <div class="top-header">
                <div class="header-left">
                    <div class="hamburger"><span></span><span></span><span></span></div>
                    <div class="header-title">Assignments</div>
                </div>
                <div class="header-right">
                    <a href="../logout.php" class="logout-btn"><span class="material-symbols-outlined">logout</span>Logout</a>
                </div>
            </div>
            
            <div class="content">
                <h2 class="page-title">Assignment Management</h2>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <h3>Assign Stores to Agents (Permanent)</h3>
                    <form method="post" action="">
                        <input type="hidden" name="assign_shops" value="1">
                        
                        <div class="form-group">
                            <label for="agent_id">Agent Name:</label>
                            <select id="agent_id" name="agent_id" required>
                                <option value="">Choose an agent</option>
                                <?php foreach ($agents as $agent): ?>
                                    <option value="<?php echo $agent['id']; ?>"><?php echo htmlspecialchars($agent['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Select Stores to Assign:</label>
                            <div class="table-scroll-container">
                                <table class="modern-table" id="assignTable">
                                    <thead>
                                        <tr>
                                            <th>Select</th>
                                            <th>Store ID</th>
                                            <th>City</th>
                                            <th>Region</th>
                                            <th>Mall</th>
                                            <th>Entity</th>
                                            <th>Brand</th>
                                        </tr>
                                        <tr>
                                            <th><input type="checkbox" class="selectAllCb" title="Select all visible"></th>
                                            <th><input type="text" class="filter-input" data-col="1" placeholder="ID..."></th>
                                            <th><input type="text" class="filter-input" data-col="2" placeholder="City..."></th>
                                            <th><input type="text" class="filter-input" data-col="3" placeholder="Region..."></th>
                                            <th><input type="text" class="filter-input" data-col="4" placeholder="Mall..."></th>
                                            <th><input type="text" class="filter-input" data-col="5" placeholder="Entity..."></th>
                                            <th><input type="text" class="filter-input" data-col="6" placeholder="Brand..."></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ```

---

### 2. The Full `agents.php` File
*(Includes the two fixes for fetching the master agents list)*

```php
<?php
require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();

// Initialize variables
$agents = [];
$success_message = '';
$error_message = '';

// Fetch all agents from database
try {
    // FIX 1: Bulletproof LIKE search to catch all multi-role agents
    $stmt = $pdo->prepare("SELECT id, username, name, phone, role, created_at FROM users WHERE LOWER(role) LIKE '%agent%' ORDER BY id ASC");
    $stmt->execute();
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching agents: " . $e->getMessage();
}

// Handle Excel import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_agents'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        
        try {
            // Simple CSV parsing (no PhpSpreadsheet dependency)
            $file = fopen($_FILES['excel_file']['tmp_name'], 'r');
            if (!$file) {
                throw new Exception('Cannot open uploaded file.');
            }
            
            $rows = [];
            while (($row = fgetcsv($file)) !== false) {
                $rows[] = $row;
            }
            fclose($file);
            
            // Check if we have any data
            if (empty($rows)) {
                throw new Exception('The uploaded file is empty.');
            }
            
            // Import counters
            $importedCount = 0;
            $skippedCount = 0;
            $importErrors = [];
            
            // Start transaction
            $pdo->beginTransaction();
            
            foreach ($rows as $rowIndex => $row) {
                // Skip empty rows
                if (empty(array_filter($row, function($value) { 
                    return $value !== null && trim($value) !== ''; 
                }))) {
                    continue;
                }
                
                // Check if first row is header (skip if contains header keywords)
                if ($rowIndex === 0) {
                    $firstCell = strtolower(trim($row[0] ?? ''));
                    if (strpos($firstCell, 'agent') !== false || 
                        strpos($firstCell, 'name') !== false ||
                        strpos($firstCell, 'username') !== false ||
                        strpos($firstCell, 'userid') !== false) {
                        continue;
                    }
                }
                
                // Extract data - assuming columns: Agent_Name, Username, Userid, Phone, Password
                $agent_name = isset($row[0]) ? trim($row[0]) : '';
                $username = isset($row[1]) ? trim($row[1]) : '';
                $manual_id = isset($row[2]) ? intval(trim($row[2])) : 0;
                $phone = isset($row[3]) ? trim($row[3]) : '';
                $raw_password = isset($row[4]) ? trim($row[4]) : '';
                
                // Validate required fields
                if (empty($agent_name)) {
                    $importErrors[] = "Row " . ($rowIndex + 1) . ": Agent Name is required";
                    $skippedCount++;
                    continue;
                }
                
                if (empty($username)) {
                    $importErrors[] = "Row " . ($rowIndex + 1) . ": Username is required";
                    $skippedCount++;
                    continue;
                }
                
                // Check if username already exists
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $checkStmt->execute([$username]);
                
                if ($checkStmt->rowCount() > 0) {
                    $importErrors[] = "Row " . ($rowIndex + 1) . ": Username '$username' already exists";
                    $skippedCount++;
                    continue;
                }
                
                // Determine which ID to use
                $agent_id = null;
                $useManualId = false;
                
                if ($manual_id > 0) {
                    // Check if manual ID already exists
                    $checkIdStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                    $checkIdStmt->execute([$manual_id]);
                    
                    if ($checkIdStmt->rowCount() === 0) {
                        $agent_id = $manual_id;
                        $useManualId = true;
                    }
                }
                
                // Set password
                if (!empty($raw_password)) {
                    $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
                } else {
                    $hashed_password = password_hash('agent123', PASSWORD_DEFAULT);
                }
                
                // Insert the agent
                try {
                    if ($useManualId && $agent_id > 0) {
                        $stmt = $pdo->prepare("INSERT INTO users (id, username, password, name, phone, role, created_at) VALUES (?, ?, ?, ?, ?, 'agent', NOW())");
                        $stmt->execute([$agent_id, $username, $hashed_password, $agent_name, $phone]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, name, phone, role, created_at) VALUES (?, ?, ?, ?, 'agent', NOW())");
                        $stmt->execute([$username, $hashed_password, $agent_name, $phone]);
                        $agent_id = $pdo->lastInsertId();
                    }
                    
                    if ($stmt->rowCount() > 0) {
                        $importedCount++;
                    } else {
                        $importErrors[] = "Row " . ($rowIndex + 1) . ": Failed to insert agent '$agent_name'";
                        $skippedCount++;
                    }
                    
                } catch (PDOException $e) {
                    $importErrors[] = "Row " . ($rowIndex + 1) . ": " . $e->getMessage();
                    $skippedCount++;
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Refresh agents list
            // FIX 2: Apply the same bulletproof LIKE query here to refresh correctly
            $stmt = $pdo->prepare("SELECT id, username, name, phone, role, created_at FROM users WHERE LOWER(role) LIKE '%agent%' ORDER BY id ASC");
            $stmt->execute();
            $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Prepare messages
            if ($importedCount > 0) {
                $success_message = "✅ Successfully imported $importedCount agents.";
                if ($skippedCount > 0) {
                    $success_message .= " $skippedCount rows were skipped.";
                    if (!empty($importErrors)) {
                        $error_message = "Some rows had issues:<br>" . implode("<br>", array_slice($importErrors, 0, 3));
                        if (count($importErrors) > 3) {
                            $error_message .= "<br>... and " . (count($importErrors) - 3) . " more";
                        }
                    }
                }
            } else {
                $error_message = "❌ No agents were imported. $skippedCount rows were skipped.";
                if (!empty($importErrors)) {
                    $error_message .= "<br>Errors:<br>" . implode("<br>", array_slice($importErrors, 0, 5));
                }
            }
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = "Error importing file: " . $e->getMessage();
        }
    } else {
        $error_message = "Please select a valid file.";
        if (isset($_FILES['excel_file']['error'])) {
            switch ($_FILES['excel_file']['error']) {
                case 1:
                case 2:
                    $error_message .= " File is too large.";
                    break;
                case 3:
                    $error_message .= " File was only partially uploaded.";
                    break;
                case 4:
                    $error_message .= " No file was uploaded.";
                    break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agents - Apparels Collection</title>
    <link rel="stylesheet" href="../css/modern-dashboard.css">
    <link rel="stylesheet" href="../css/agents.css">
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" type="image/png" href="/images/icon-192x192.png">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet" />
    <script src="../js/app.js" defer></script>
    
    <style>
        :root {
            --bg-card: #13151a;
            --border-color: rgba(255, 255, 255, 0.08);
            --text-muted: #8a8f98;
            --accent-blue: #3b82f6;
            --bg-input: #1c1f26;
            --success: #10b981;
        }

        body {
            background-color: #0b0c10;
            color: #f3f4f6;
            margin: 0;
        }

        .content {
            padding: 24px;
            width: 100%;
            box-sizing: border-box;
        }

        /* Full Screen Column Grid Integration Layout */
        .agents-grid {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 24px;
            align-items: start;
            width: 100%;
        }

        @media (max-width: 1200px) {
            .agents-grid {
                grid-template-columns: 1fr;
            }
        }

        .panel-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        h2 {
            font-size: 18px;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 20px;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        input[type="file"] {
            width: 100%;
            background: var(--bg-input);
            border: 1px dashed var(--border-color);
            color: #fff;
            padding: 14px;
            border-radius: 8px;
            font-size: 13px;
            box-sizing: border-box;
            cursor: pointer;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 13px;
            cursor: pointer;
            border: none;
            background: var(--accent-blue);
            color: white;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn:hover { background: #2563eb; }

        /* Search Header Controller Strip Layout */
        .table-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .table-header-bar h2 { margin: 0; }

        .search-wrapper {
            position: relative;
            width: 100%;
            max-width: 340px;
        }

        .search-wrapper .material-symbols-outlined {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 20px;
        }

        .search-input {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 10px 12px 10px 40px;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }

        /* High-Density View Bound Scrollable Datatable Wrap */
        .table-scroll-container {
            background: #16181f;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow-x: auto;
            max-height: calc(100vh - 210px);
            overflow-y: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #1c1f26;
            color: var(--text-muted);
            font-weight: 500;
            padding: 14px 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            white-space: nowrap;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255, 255, 255, 0.01); }

        .agent-badge {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
        }

        /* Webkit Engine Scrollbar Overrides */
        .table-scroll-container::-webkit-scrollbar { width: 6px; height: 6px; }
        .table-scroll-container::-webkit-scrollbar-track { background: transparent; }
        .table-scroll-container::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.12); border-radius: 4px; }
        .table-scroll-container::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.25); }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo" align="center">
                    <img src="../images/logo.png" alt="Apparel Collection Logo" width="auto" height="80px" style="object-fit: contain;" loading="lazy">
                </div>
            </div>
            
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <span class="material-symbols-outlined">home</span>
                    <span>Dashboard</span>
                </a>
                <a href="assignments.php" class="nav-item">
                    <span class="material-symbols-outlined">assignment</span>
                    <span>Assignments</span>
                </a>
                <a href="agents.php" class="nav-item active">
                    <span class="material-symbols-outlined">people</span>
                    <span>Agents</span>
                </a>
                <a href="management.php" class="nav-item">
                    <span class="material-symbols-outlined">settings</span>
                    <span>Management</span>
                </a>
                <a href="store_data.php" class="nav-item">
                    <span class="material-symbols-outlined">storefront</span>
                    <span>Store Data</span>
                </a>
                <a href="bank_approvals.php" class="nav-item">
                    <span class="material-symbols-outlined">receipt_long</span>
                    <span>Bank Approvals</span>
                </a>
            </div>
        </div>
        
        <div class="overlay"></div>
        
        <div class="main-content">
            <div class="top-header">
                <div class="header-left">
                    <div class="hamburger">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <div class="header-title">Agents</div>
                </div>
                <div class="header-right">
                    <a href="../logout.php" class="logout-btn">
                        <span class="material-symbols-outlined">logout</span> Logout
                    </a>
                </div>
            </div>
            
            <div class="content">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px; padding: 12px; border-radius: 8px; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); font-size: 14px; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-outlined">check_circle</span>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" style="margin-bottom: 20px; padding: 12px; border-radius: 8px; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); font-size: 14px; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-outlined">error</span>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="agents-grid">
                    
                    <div class="panel-card">
                        <h2>
                            <span class="material-symbols-outlined" style="color: var(--accent-blue);">upload</span>
                            Import Agents Profiles
                        </h2>
                        
                        <form method="post" action="" enctype="multipart/form-data" style="margin: 0;">
                            <input type="hidden" name="import_agents" value="1">
                            
                            <div class="form-group">
                                <label for="excel_file">Upload CSV / Excel File Target Source:</label>
                                <input type="file" id="excel_file" name="excel_file" accept=".csv,.xlsx,.xls" required>
                                <small style="display: block; margin-top: 10px; color: var(--text-muted); font-size: 11px; line-height: 1.4;">
                                    Matrix Schema structure sequence metrics required:<br>
                                    <strong>Agent_Name, Username, Userid, Phone, Password</strong>
                                </small>
                            </div>
                            
                            <button type="submit" class="btn" style="width: 100%; margin-top: 4px;">
                                <span class="material-symbols-outlined" style="font-size: 18px;">file_upload</span>
                                Run Engine Import
                            </button>
                        </form>
                    </div>
                    
                    <div class="panel-card" style="overflow: hidden;">
                        <div class="table-header-bar">
                            <h2>
                                <span class="material-symbols-outlined" style="color: var(--success);">group</span>
                                Active Agents Fleet Structure (<?php echo count($agents); ?>)
                            </h2>
                            
                            <div class="search-wrapper">
                                <span class="material-symbols-outlined">search</span>
                                <input type="text" id="agentSearch" class="search-input" placeholder="Search by name, user ID, or phone...">
                            </div>
                        </div>
                        
                        <div class="table-scroll-container">
                            <?php if (empty($agents)): ?>
                                <div style="padding: 32px; text-align: center; color: var(--text-muted); font-size: 14px;">
                                    <span class="material-symbols-outlined" style="font-size: 36px; display: block; margin-bottom: 8px; color: var(--text-muted);">info</span>
                                    No agents discovered. Utilize the configuration loader file map on the left matrix to create profiles.
                                </div>
                            <?php else: ?>
                                <table id="agentTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 70px;">ID</th>
                                            <th>Agent Real Name</th>
                                            <th>System Username</th>
                                            <th>Mobile Context Link</th>
                                            <th>Profile Created Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($agents as $agent): ?>
                                            <tr class="agent-row">
                                                <td style="color: var(--text-muted); font-weight: 600;" class="search-id"><?php echo htmlspecialchars($agent['id']); ?></td>
                                                <td>
                                                    <div style="font-weight: 600; color: #fff;" class="search-name"><?php echo htmlspecialchars($agent['name']); ?></div>
                                                </td>
                                                <td>
                                                    <span class="agent-badge" class="search-username">@<?php echo htmlspecialchars($agent['username']); ?></span>
                                                </td>
                                                <td style="color: var(--accent-blue); font-weight: 500;" class="search-phone">
                                                    <?php echo htmlspecialchars($agent['phone']); ?>
                                                </td>
                                                <td style="color: var(--text-muted); font-size: 13px;">
                                                    <?php echo date('Y-m-d', strtotime($agent['created_at'])); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Nav Menu Mobile Animation Controllers Logic
        const hamburger = document.querySelector('.hamburger');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.overlay');
        
        if (hamburger) {
            hamburger.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.classList.toggle('menu-open');
            });
        }
        
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.classList.remove('menu-open');
            });
        }

        // Instant Dynamic Real-time Client Filtering Engine
        const searchInput = document.getElementById('agentSearch');
        const rows = document.querySelectorAll('.agent-row');

        if(searchInput) {
            searchInput.addEventListener('input', function(e) {
                const query = e.target.value.toLowerCase().trim();

                rows.forEach(row => {
                    const id = row.querySelector('.search-id').textContent.toLowerCase();
                    const name = row.querySelector('.search-name').textContent.toLowerCase();
                    const username = row.querySelector('.search-badge') ? row.querySelector('.search-badge').textContent.toLowerCase() : row.textContent.toLowerCase();
                    const phone = row.querySelector('.search-phone').textContent.toLowerCase();

                    if (id.includes(query) || name.includes(query) || username.includes(query) || phone.includes(query)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    });
    </script>
</body>
</html>