<?php
require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();
$success_message = $error_message = null;

// 1. Handle Manual Assignment (PERMANENT - Removed Date Restriction)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_shops'])) {
    $agent_id = $_POST['agent_id'];
    $selected_stores = $_POST['stores'] ?? [];
    
    $added_count = 0;
    $skipped_count = 0;

    foreach ($selected_stores as $store_id) {
        // Check for duplicates globally, not just for today
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM daily_assignments WHERE agent_id = ? AND store_id = ?");
        $checkStmt->execute([$agent_id, $store_id]);
        
        if ($checkStmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO daily_assignments (agent_id, store_id, date_assigned, status) VALUES (?, ?, NOW(), 'pending')");
            $stmt->execute([$agent_id, $store_id]);
            $added_count++;
        } else {
            $skipped_count++;
        }
    }
    $success_message = "Assigned $added_count shops." . ($skipped_count > 0 ? " ($skipped_count skipped as they are already assigned to this agent)." : "");
}

// 1.5 Handle Reset to Pending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_assignments'])) {
    $reset_pairs = $_POST['reset_pairs'] ?? [];
    $reset_count = 0;
    
    foreach ($reset_pairs as $pair) {
        list($a_id, $s_id) = explode('_', $pair);
        $stmt = $pdo->prepare("UPDATE daily_assignments SET status = 'pending' WHERE agent_id = ? AND store_id = ?");
        $stmt->execute([$a_id, $s_id]);
        $reset_count++;
    }
    if($reset_count > 0) {
        $success_message = "Successfully reset $reset_count shops back to Pending.";
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
            foreach ($rows as $row) {
                if (empty($row[0]) || empty($row[5])) continue;

                $agent_name = trim($row[0]);
                $store_name = trim($row[5]);

                $agentStmt = $pdo->prepare("SELECT id FROM users WHERE name = ? AND role = 'agent'");
                $agentStmt->execute([$agent_name]);
                $agent = $agentStmt->fetch(PDO::FETCH_ASSOC);

                $storeStmt = $pdo->prepare("SELECT id FROM stores WHERE name = ?");
                $storeStmt->execute([$store_name]);
                $store = $storeStmt->fetch(PDO::FETCH_ASSOC);

                if ($agent && $store) {
                    // Global duplicate check
                    $check = $pdo->prepare("SELECT COUNT(*) FROM daily_assignments WHERE agent_id = ? AND store_id = ?");
                    $check->execute([$agent['id'], $store['id']]);

                    if ($check->fetchColumn() == 0) {
                        $ins = $pdo->prepare("INSERT INTO daily_assignments (agent_id, store_id, date_assigned, status) VALUES (?, ?, NOW(), 'pending')");
                        $ins->execute([$agent['id'], $store['id']]);
                        $importedCount++;
                    } else {
                        $skippedCount++;
                    }
                }
            }
            $success_message = "Successfully imported $importedCount assignments. $skippedCount duplicates were skipped.";
        }
    }
}

// 3. Fetch Data for Display

// Fetch Agents
$agents_stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'agent' ORDER BY name ASC");
$agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Stores (Added City)
$stores_stmt = $pdo->query("SELECT s.id, s.name, s.city, s.mall, s.entity, s.brand, r.name as region_name FROM stores s LEFT JOIN regions r ON s.region_id = r.id ORDER BY s.name ASC");
$stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ALL assignments (Removed DATE limitation, added Last Visit Subquery)
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
        hr { margin: 30px 0; border: none; border-top: 1px solid var(--card-border); }

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
                    <a href="../api_logout.php" class="logout-btn" style="margin: 0;">
                        <span class="material-symbols-outlined">logout</span> Logout
                    </a>
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
                                        <?php foreach ($stores as $store): ?>
                                            <tr>
                                                <td><input type="checkbox" name="stores[]" value="<?php echo $store['id']; ?>"></td>
                                                <td style="font-weight: 600; color: var(--accent-color);"><?php echo htmlspecialchars($store['id']); ?></td>
                                                <td><?php echo htmlspecialchars($store['city'] ?? $store['name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($store['region_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($store['mall'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($store['entity'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($store['brand'] ?? 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <button type="submit" class="btn">Assign Stores</button>
                    </form>
                </div>
                
                <hr>
                
                <div class="card import-section">
                    <h3>Import & Export Assignments</h3>
                    <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
                        <a href="generate_template.php" class="btn btn-success"><span class="material-symbols-outlined">download</span>Download Template</a>
                        <a href="export_collections.php" class="btn btn-warning"><span class="material-symbols-outlined">table_view</span>Export Today's Collections</a>
                        
                        <form method="post" action="" enctype="multipart/form-data" id="uploadForm" style="display: inline-flex; gap: 10px;">
                            <input type="hidden" name="import_excel" value="1">
                            <input type="file" id="excel_file" name="excel_file" accept=".csv,.xlsx,.xls" style="display: none;" required>
                            <button type="button" id="customUploadBtn" class="btn"><span class="material-symbols-outlined">upload_file</span>Select & Import CSV</button>
                        </form>
                    </div>
                </div>
                
                <hr>
                
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3>Active Agent Assignments</h3>
                        <button type="button" class="btn btn-warning" onclick="document.getElementById('resetForm').submit();" style="margin:0;">
                            <span class="material-symbols-outlined">refresh</span> Reset Selected to Pending
                        </button>
                    </div>
                    
                    <form id="resetForm" method="post" action="">
                        <input type="hidden" name="reset_assignments" value="1">
                        <div class="table-scroll-container">
                            <table class="modern-table" id="currentTable">
                                <thead>
                                    <tr>
                                        <th>Select</th>
                                        <th>Agent</th>
                                        <th>Store ID</th>
                                        <th>City</th>
                                        <th>Mall</th>
                                        <th>Brand</th>
                                        <th>Status</th>
                                        <th>Last Visit (Submitted)</th>
                                    </tr>
                                    <tr>
                                        <th><input type="checkbox" class="selectAllCb" title="Select all visible"></th>
                                        <th><input type="text" class="filter-input" data-col="1" placeholder="Agent..."></th>
                                        <th><input type="text" class="filter-input" data-col="2" placeholder="ID..."></th>
                                        <th><input type="text" class="filter-input" data-col="3" placeholder="City..."></th>
                                        <th><input type="text" class="filter-input" data-col="4" placeholder="Mall..."></th>
                                        <th><input type="text" class="filter-input" data-col="5" placeholder="Brand..."></th>
                                        <th><input type="text" class="filter-input" data-col="6" placeholder="Status..."></th>
                                        <th><input type="text" class="filter-input" data-col="7" placeholder="Date..."></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_assignments as $a): 
                                        $visit_time = $a['last_visit'] ? date('M j, Y g:i A', strtotime($a['last_visit'])) : 'Never Visited';
                                    ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="reset_pairs[]" value="<?php echo $a['agent_id'] . '_' . $a['store_id']; ?>">
                                            </td>
                                            <td style="font-weight: 500;"><?php echo htmlspecialchars($a['agent_name']); ?></td>
                                            <td style="color: var(--accent-color);"><?php echo htmlspecialchars($a['s_id']); ?></td>
                                            <td><?php echo htmlspecialchars($a['city'] ?? $a['store_name']); ?></td>
                                            <td><?php echo htmlspecialchars($a['mall'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($a['brand'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo strtolower($a['status']); ?>">
                                                    <?php echo ucfirst($a['status']); ?>
                                                </span>
                                            </td>
                                            <td style="font-size: 13px; color: <?php echo $a['last_visit'] ? 'var(--success)' : 'var(--text-secondary)'; ?>;">
                                                <?php echo $visit_time; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- DYNAMIC TABLE FILTERING ---
        function setupTableFilters(tableId) {
            const table = document.getElementById(tableId);
            if (!table) return;
            
            const filterInputs = table.querySelectorAll('.filter-input');
            const tableRows = table.querySelectorAll('tbody tr');
            const selectAllCb = table.querySelector('.selectAllCb');

            // Live Filter
            filterInputs.forEach(input => {
                input.addEventListener('input', function() {
                    const filters = Array.from(filterInputs).map(inp => ({
                        index: parseInt(inp.getAttribute('data-col')),
                        value: inp.value.toLowerCase().trim()
                    }));

                    tableRows.forEach(row => {
                        let isMatch = true;
                        filters.forEach(filter => {
                            if (filter.value !== '') {
                                const cellText = row.cells[filter.index].textContent.toLowerCase();
                                if (!cellText.includes(filter.value)) {
                                    isMatch = false;
                                }
                            }
                        });
                        row.style.display = isMatch ? '' : 'none';
                    });
                    
                    if(selectAllCb) selectAllCb.checked = false;
                });
            });

            // Select All Visible
            if (selectAllCb) {
                selectAllCb.addEventListener('change', function() {
                    const isChecked = this.checked;
                    tableRows.forEach(row => {
                        if (row.style.display !== 'none') {
                            const cb = row.querySelector('input[type="checkbox"]');
                            if (cb) cb.checked = isChecked;
                        }
                    });
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Setup filters for BOTH tables independently
            setupTableFilters('assignTable');
            setupTableFilters('currentTable');

            // File Upload logic
            const fileInput = document.getElementById('excel_file');
            const customBtn = document.getElementById('customUploadBtn');
            const form = document.getElementById('uploadForm');

            customBtn.addEventListener('click', () => fileInput.click());

            fileInput.addEventListener('change', function() {
                if (fileInput.value) {
                    customBtn.innerHTML = '<span class="material-symbols-outlined">sync</span> Importing...';
                    customBtn.style.opacity = '0.7';
                    form.submit();
                }
            });

            // Hamburger logic
            const hamburger = document.querySelector('.hamburger');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.overlay');
            
            if (hamburger) {
                hamburger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('active');
                    if (overlay) overlay.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>