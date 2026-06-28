<?php
// 1. FORCE THE BROWSER TO USE THE MASTER DOMAIN COOKIE
ini_set('session.cookie_path', '/');
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
session_name('PHPSESSID');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. KICK OUT UNREGISTERED USERS
if (!isset($_SESSION['iam_user'])) {
    // If they aren't logged into the IAM, send them to the master login portal
    header("Location: ../api_logout.php");
    exit;
}

// 3. EXTRACT SPECIFIC PROCUREMENT ROLES
// Replace 'procurement' with whatever ID you used in your iam_modules table
$po_roles = $_SESSION['iam_user']['roles']['procurement'] ?? [];

// If they don't have a role for this module, kick them out
if (empty($po_roles)) {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>Error: You do not have clearance for the Procurement module.</h2>");
}

// Grab their highest authority role for this specific app
$primary_role = $po_roles[0]; 
$username = $_SESSION['iam_user']['username'];
$fullname = $_SESSION['iam_user']['name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Apparel - Procurement & Logistics Suite</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; border-radius: 0px !important; }
        :root {
            --bg-main: #1e222a; --bg-surface: #252a34; --bg-elevated: #2c323e;
            --border-sharp: #3e4555; --text-primary: #e2e8f0; --text-secondary: #cbd5e1;
            --text-muted: #94a3b8; --accent-soft: #2c3a58; --accent-primary: #4f83cc;
            --accent-hover: #5b94e3; --success: #34d399; --warning: #fbbf24;
            --danger: #f87171; --transition: all 0.15s ease;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg-main); color: var(--text-primary); }
        p, div { white-space: normal; word-wrap: break-word; overflow-wrap: break-word; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-main); }
        ::-webkit-scrollbar-thumb { background: var(--border-sharp); }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-primary); }

        /* LOGIN PANEL */
        #loginView { height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--bg-main); border-top: 4px solid var(--accent-primary); }
        .login-card { background: var(--bg-surface); padding: 40px 36px; width: 100%; max-width: 420px; text-align: center; border: 1px solid var(--border-sharp); box-shadow: 8px 8px 0 rgba(0,0,0,0.5); }
        .login-card h2 { font-weight: 700; font-size: 1.9rem; letter-spacing: -0.5px; color: var(--text-primary); margin-bottom: 24px; }
        .login-btn { background: var(--accent-primary); border: 1px solid var(--border-sharp); padding: 12px; font-weight: 600; color: white; width: 100%; font-size: 1rem; cursor: pointer; transition: 0.1s linear; }
        .login-btn:hover { background: var(--accent-hover); }

        /* APP WRAPPER */
        .app-wrapper { display: flex; min-height: 100vh; width: 100vw; overflow: hidden; }

        /* SIDEBAR */
        .sidebar { width: 280px; background: var(--bg-surface); border-right: 2px solid var(--border-sharp); padding: 28px 16px; position: sticky; top: 0; height: 100vh; overflow-y: auto; flex-shrink: 0; }
        .logo-area { display: flex; align-items: center; gap: 12px; font-size: 1.5rem; font-weight: 700; margin-bottom: 40px; padding-bottom: 16px; border-bottom: 2px solid var(--border-sharp); color: var(--text-primary); }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; margin: 4px 0; font-weight: 500; color: var(--text-secondary); cursor: pointer; transition: var(--transition); border-left: 3px solid transparent; }
        .nav-item i { width: 24px; font-size: 1.2rem; }
        .nav-item.active, .nav-item:hover { background: var(--accent-soft); color: var(--accent-primary); border-left: 3px solid var(--accent-primary); }

        /* MAIN CONTENT */
        .main-content { flex: 1; padding: 28px 36px; overflow-x: hidden; overflow-y: auto; background: var(--bg-main); width: calc(100vw - 280px); }

        /* TOP BAR */
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; border-bottom: 1px solid var(--border-sharp); padding-bottom: 16px; }
        .page-title h1 { font-size: 1.8rem; font-weight: 700; letter-spacing: -0.3px; color: var(--text-primary); }

        /* BUTTONS */
        .btn-create, .btn-cancel, .btn-logout, .btn-export { border: 1px solid var(--border-sharp); padding: 10px 22px; font-weight: 600; display: inline-flex; gap: 8px; align-items: center; cursor: pointer; transition: 0.1s linear; font-size: 0.85rem; }
        .btn-create { background: var(--accent-primary); color: white; }
        .btn-create:hover { background: var(--accent-hover); }
        .btn-cancel { background: var(--bg-surface); color: var(--text-primary); }
        .btn-cancel:hover { background: var(--border-sharp); }
        .btn-export { background: transparent; border-color: var(--success); color: var(--success); padding: 6px 12px; font-size: 0.8rem; }
        .btn-export:hover { background: #064e3b; color: white; border-color: #064e3b; }
        .btn-logout { background: transparent; color: var(--danger); border: none; padding: 4px 8px; }
        .btn-logout:hover { color: #ff8f8f; }

        /* CARDS */
        .card-panel { background: var(--bg-surface); border: 1px solid var(--border-sharp); margin-bottom: 32px; width: 100%; }
        .card-header { padding: 18px 24px; border-bottom: 1px solid var(--border-sharp); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; background: var(--bg-elevated); gap: 12px; }
        .card-header h2 { font-size: 1.2rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; }

        /* TABLES */
        .table-scroll-wrapper { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .po-table { width: 100%; min-width: 900px; border-collapse: collapse; font-size: 0.85rem; }
        .po-table th, .po-table td { white-space: nowrap; padding: 14px 16px; }
        .po-table th { text-align: left; background: var(--bg-elevated); font-weight: 600; color: var(--text-secondary); border-bottom: 2px solid var(--border-sharp); text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.5px; }
        .po-table td { border-bottom: 1px solid var(--border-sharp); color: var(--text-primary); background: var(--bg-surface); vertical-align: middle; }
        .po-table tbody tr:hover td { background: var(--bg-elevated); }

        /* PAGINATION STYLES */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding: 12px 24px; background: var(--bg-surface); border-top: 1px solid var(--border-sharp); font-size: 0.85rem; color: var(--text-secondary); }
        .pagination-controls button { background: var(--bg-main); border: 1px solid var(--border-sharp); color: var(--text-primary); padding: 6px 12px; cursor: pointer; margin-left: 8px; transition: 0.1s; }
        .pagination-controls button:disabled { opacity: 0.5; cursor: not-allowed; }
        .pagination-controls button:hover:not(:disabled) { background: var(--accent-primary); border-color: var(--accent-primary); color: white; }

        .status-badge { display: inline-block; padding: 4px 12px; font-size: 0.7rem; font-weight: 700; border: 1px solid transparent; }
        .status-approved { background: #064e3b; color: #34d399; border-color: #065f46; }
        .status-partial { background: #78350f; color: #fbbf24; border-color: #92400e; }
        .status-pending { background: #7f1d1d; color: #f87171; border-color: #991b1b; }
        .status-dispatched { background: #0c4a6e; color: #7dd3fc; border-color: #075985; }

        .action-icons i, .action-icons button { font-size: 1rem; margin: 0 6px; cursor: pointer; color: var(--text-muted); background: none; border: none; }
        .action-icons i:hover { color: var(--accent-primary); }

        /* MODALS */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); display: flex; align-items: center; justify-content: center; z-index: 1000; visibility: hidden; opacity: 0; transition: 0.1s; }
        .modal-overlay.active { visibility: visible; opacity: 1; }
        .modal-container { background: var(--bg-surface); width: 780px; max-width: 90vw; padding: 28px; max-height: 90vh; overflow-y: auto; border: 2px solid var(--border-sharp); box-shadow: 10px 10px 0 rgba(0,0,0,0.5); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-weight: 600; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); }
        input, select { padding: 10px 12px; border: 1px solid var(--border-sharp); font-family: 'Inter', sans-serif; font-size: 0.85rem; background: var(--bg-main); color: var(--text-primary); width: 100%; }
        input:focus, select:focus { outline: none; border-color: var(--accent-primary); }

        .toast-msg { position: fixed; bottom: 20px; right: 20px; background: var(--bg-surface); color: var(--text-primary); padding: 12px 20px; font-weight: 500; z-index: 1100; opacity: 0; transition: 0.1s; pointer-events: none; border-left: 4px solid var(--accent-primary); border-right: 1px solid var(--border-sharp); border-top: 1px solid var(--border-sharp); border-bottom: 1px solid var(--border-sharp); font-size: 0.8rem; }
        .toast-msg.show { opacity: 1; }
        .column-filter { display: block; width: 100%; padding: 6px 8px; font-size: 0.7rem; margin-top: 8px; background: var(--bg-main); border: 1px solid var(--border-sharp); color: var(--text-primary); }
        .receipt-view { background: #ffffff; color: #000000; padding: 20px; border: 2px solid var(--border-sharp); font-family: monospace; }
        .receipt-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .receipt-table th, .receipt-table td { border-bottom: 1px dashed #ccc; padding: 8px 0; text-align: left; }
        .cart-row, .dynamic-field-row { display: grid; grid-template-columns: 1fr 1fr auto; gap: 12px; align-items: center; background: var(--bg-elevated); padding: 12px; margin-bottom: 8px; border: 1px solid var(--border-sharp); }
        .form-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-sharp); }
    </style>
</head>
<body>

<datalist id="storeDatalist"></datalist>

<div id="loginView">
    <div class="login-card">
        <i class="fas fa-file-invoice-dollar" style="font-size: 3rem; color: var(--accent-primary); margin-bottom: 16px;"></i>
        <h2>Procurement Ledger</h2>
        <form class="login-form" onsubmit="handleLogin(event)">
            <div class="form-group"><label>Account ID</label><input type="text" id="loginUsername" required></div>
            <div class="form-group"><label>Password</label><input type="password" id="loginPassword" required></div>
            <button type="submit" class="login-btn" style="margin-top: 20px;">Secure Login <i class="fas fa-arrow-right" style="margin-left: 8px;"></i></button>
        </form>
    </div>
</div>

<div id="storeView" class="app-wrapper" style="display: none;">
    <div class="sidebar">
        <div class="logo-area"><i class="fas fa-store"></i><span>Store Portal</span></div>
        <div class="nav-item store-tab active" data-tab="storeInventory"><i class="fas fa-box-open"></i> <span>Assigned Inventory</span></div>
        <div class="nav-item store-tab" data-tab="storeRequest"><i class="fas fa-hand-holding-box"></i> <span>Request Stock</span></div>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title"><h1><i class="fas fa-store-alt" style="color: var(--accent-primary); margin-right: 8px;"></i> Location: <span id="storeNameDisplay"></span></h1></div>
            <div style="display: flex; align-items: center; gap: 20px;">
                <div style="display: flex; align-items: center; gap: 12px; background: var(--bg-surface); padding: 8px 16px; border: 1px solid var(--border-sharp);">
                    <span style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem;">Route: <span id="storeRouteDisplay"></span></span>
                    <div style="width: 1px; height: 20px; background: var(--border-sharp); margin: 0 4px;"></div>
                    <button onclick="handleLogout()" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</button>
                </div>
            </div>
        </div>

        <div id="storeInventoryPanel" class="card-panel store-panel active-panel">
            <div class="card-header">
                <h2><i class="fas fa-list-check" style="margin-right: 8px; color: var(--accent-primary);"></i> Products Dispatched to You</h2>
                <button onclick="openExportModal('tbl-store-inv', -1, 'My_Assigned_Inventory')" class="btn-export"><i class="fas fa-file-csv"></i> Export Data</button>
            </div>
            <div class="table-scroll-wrapper">
                <table class="po-table" id="tbl-store-inv">
                    <thead><tr><th>Manifest ID</th><th>SKU Code</th><th>Item Details</th><th>Category</th><th>Qty Received</th><th>Actions</th></tr></thead>
                    <tbody id="storeInventoryTableBody"></tbody>
                </table>
            </div>
            <div id="page-tbl-store-inv" class="pagination-container"></div>
        </div>

        <div id="storeRequestPanel" class="card-panel store-panel" style="display:none;">
            <div class="card-header">
                <h2><i class="fas fa-cart-plus" style="margin-right: 8px; color: var(--accent-primary);"></i> Request Material Handover</h2>
                <button onclick="submitStoreRequest()" class="btn-create"><i class="fas fa-paper-plane"></i> Send Request</button>
            </div>
            <div class="table-scroll-wrapper" style="border-bottom:1px solid var(--border-sharp);">
                <table class="po-table" id="tbl-store-req">
                    <thead><tr><th>SKU</th><th>Item Name & Details</th><th>Type Context</th><th>Warehouse Balance</th><th>Target Qty</th></tr></thead>
                    <tbody id="storeCatalogTableBody"></tbody>
                </table>
            </div>
            <div id="page-tbl-store-req" class="pagination-container"></div>
            
            <div style="padding: 28px; background: var(--bg-surface);">
                <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin-bottom: 16px; border-left: 4px solid var(--accent-primary); padding-left: 10px;">
                    <i class="fas fa-history"></i> My Request Status Ledger
                </h3>
                <div id="storePortalRequestStatusContainer" style="max-height:220px; overflow-y:auto; padding-right:5px;"></div>
            </div>
        </div>
    </div>
</div>

<div id="appView" class="app-wrapper" style="display: none;">
    <div class="sidebar">
        <div class="logo-area"><i class="fas fa-file-invoice-dollar"></i><span>Procurement</span></div>
        <div class="nav-item admin-tab" data-tab="po"><i class="fas fa-list-check"></i> <span>Purchase Orders</span></div>
        <div class="nav-item admin-tab" data-tab="inventory"><i class="fas fa-boxes"></i> <span>Master Inventory</span></div>
        <div class="nav-item admin-tab active" data-tab="dispatch"><i class="fas fa-truck-fast"></i> <span>Dispatch Matrix</span></div>
        <div class="nav-item admin-tab" data-tab="storeStock"><i class="fas fa-store-alt"></i> <span>Store Stock Status</span></div>
        <div class="nav-item admin-tab" data-tab="storeRequestsAdmin"><i class="fas fa-bell"></i> <span>Store Requests</span></div>
        <div class="nav-item admin-tab" data-tab="reverseLogistics"><i class="fas fa-store-slash"></i> <span>Store Closure</span></div>
        <div class="nav-item admin-tab" data-tab="disposed"><i class="fas fa-trash-alt"></i> <span>Disposed Assets</span></div>
        <div class="nav-item admin-tab" data-tab="accounts"><i class="fas fa-users-gear"></i> <span>Directory & Accounts</span></div>
        <div class="nav-item admin-tab" data-tab="audit"><i class="fas fa-history"></i> <span>Accountability Log</span></div>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title"><h1><i class="fas fa-warehouse" style="color: var(--accent-primary); margin-right: 8px;"></i> Central Management</h1></div>
            <div style="display: flex; align-items: center; gap: 20px;">
                <div style="display: flex; align-items: center; gap: 12px; background: var(--bg-surface); padding: 8px 16px; border: 1px solid var(--border-sharp);">
                    <i class="fas fa-user-circle" style="color: var(--accent-primary); font-size: 1.2rem;"></i>
                    <span style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem;" id="displayUser">Admin</span>
                    <button onclick="handleLogout()" class="btn-logout"><i class="fas fa-sign-out-alt"></i></button>
                </div>
            </div>
        </div>

        <div id="poPanel" class="card-panel admin-panel" style="display:none;">
            <div class="card-header">
                <h2><i class="fas fa-list-check" style="margin-right: 8px; color: var(--accent-primary);"></i> Purchase Orders</h2>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <button onclick="openExportModal('tbl-po', 8, 'Purchase_Orders')" class="btn-export"><i class="fas fa-file-csv"></i> Export CSV</button>
                    <input type="file" id="excelUpload" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" style="display: none;" onchange="handleExcelImport(event)">
                    <input type="file" id="pdfUpload" accept="application/pdf" style="display: none;" onchange="handlePDFImport(event)">
                    <button onclick="document.getElementById('excelUpload').click()" class="btn-cancel" style="border: 1px solid var(--success); color: var(--success);"><i class="fas fa-file-excel"></i> Bulk PO Import</button>
                    <button onclick="document.getElementById('pdfUpload').click()" class="btn-cancel" style="border: 1px solid var(--danger); color: var(--danger);"><i class="fas fa-file-pdf"></i> Scan PDF</button>
                    <button onclick="document.getElementById('poModal').classList.add('active'); addNewLineItem();" class="btn-create"><i class="fas fa-plus"></i> New PO</button>
                </div>
            </div>
            <div class="table-scroll-wrapper">
                <table class="po-table" id="tbl-po">
                    <thead><tr>
                        <th>PO # <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter PO..."></th>
                        <th>Vendor <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Vendor..."></th>
                        <th>Del. Note <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter DN..."></th>
                        <th>Category <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Cat..."></th>
                        <th>Model No. <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Model..."></th>
                        <th>Item Description <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Desc..."></th>
                        <th>Total <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Total..."></th>
                        <th>Status <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Status..."></th>
                        <th>Date <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Date..."></th>
                        <th style="vertical-align: middle;">Actions</th>
                    </tr></thead>
                    <tbody id="poTableBody"></tbody>
                </table>
            </div>
            <div id="page-tbl-po" class="pagination-container"></div>
        </div>

        <div id="inventoryPanel" class="card-panel admin-panel" style="display:none;">
            <div class="card-header">
                <h2><i class="fas fa-boxes" style="margin-right: 8px; color: var(--accent-primary);"></i> Central Master Inventory</h2>
                <button onclick="openExportModal('tbl-inv', -1, 'Master_Inventory')" class="btn-export"><i class="fas fa-file-csv"></i> Export CSV</button>
            </div>
            <div class="table-scroll-wrapper">
                <table class="po-table" id="tbl-inv">
                    <thead><tr><th>SKU <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter SKU..."></th><th>Category <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Category..."></th><th>Item Details <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Item..."></th><th>Qty on Hand <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Qty..."></th><th>Type Context <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Type..."></th></tr></thead>
                    <tbody id="inventoryTableBody"></tbody>
                </table>
            </div>
            <div id="page-tbl-inv" class="pagination-container"></div>
        </div>

        <div id="dispatchPanel" class="card-panel admin-panel active-panel">
            <div class="card-header">
                <h2><i class="fas fa-truck-fast" style="margin-right: 8px; color: var(--accent-primary);"></i> Dispatch Allocations</h2>
                <div style="display: flex; gap: 12px;">
                    <button onclick="openExportModal('tbl-dispatch', 3, 'Dispatch_Log')" class="btn-export"><i class="fas fa-file-csv"></i> Export CSV</button>
                    <button onclick="openDispatchFlow()" class="btn-create"><i class="fas fa-plus"></i> New Dispatch</button>
                </div>
            </div>
            <div class="table-scroll-wrapper">
                <table class="po-table" id="tbl-dispatch">
                    <thead><tr><th>Dispatch ID <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter ID..."></th><th>Target Scope <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Scope..."></th><th>Manifest Content</th><th>Execution Date <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Date..."></th><th>Actions</th></tr></thead>
                    <tbody id="dispatchTableBody"></tbody>
                </table>
            </div>
            <div id="page-tbl-dispatch" class="pagination-container"></div>
        </div>

        <div id="storeStockPanel" class="card-panel admin-panel" style="display:none;">
            <div class="card-header">
                <h2><i class="fas fa-store-alt" style="margin-right: 8px; color: var(--accent-primary);"></i> Store Inventory Status</h2>
                <button onclick="openExportModal('tbl-store-stock', 3, 'Store_Stock_Matrix')" class="btn-export"><i class="fas fa-file-csv"></i> Export CSV</button>
            </div>
            <div style="padding: 20px;">
                <div class="form-group" style="max-width: 400px; margin-bottom: 20px;">
                    <label>Search Store to View Stock</label>
                    <input type="text" id="stockStoreInput" list="storeDatalist" onchange="renderStoreStockView()" placeholder="Type to search store...">
                </div>
                <div class="table-scroll-wrapper">
                    <table class="po-table" id="tbl-store-stock">
                        <thead><tr><th>SKU <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter SKU..."></th><th>Item Name <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Item..."></th><th>Total Dispatched to Store <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Total..."></th><th>Last Dispatch Date <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Date..."></th></tr></thead>
                        <tbody id="storeStockTableBody"><tr><td colspan="4" style="text-align:center;">Select a store to view stock.</td></tr></tbody>
                    </table>
                </div>
                <div id="page-tbl-store-stock" class="pagination-container"></div>
            </div>
        </div>

        <div id="storeRequestsAdminPanel" class="card-panel admin-panel" style="display:none;">
            <div class="card-header">
                <h2><i class="fas fa-bell" style="margin-right: 8px; color: var(--accent-primary);"></i> Pending Store Requests</h2>
                <button onclick="openExportModal('tbl-req-pending', 0, 'Pending_Store_Requests')" class="btn-export"><i class="fas fa-file-csv"></i> Export CSV</button>
            </div>
            <div class="table-scroll-wrapper">
                <table class="po-table" id="tbl-req-pending">
                    <thead><tr><th>Date</th><th>Origin Store</th><th>Requested Items</th><th>Actions</th></tr></thead>
                    <tbody id="adminStoreRequestsTableBody"></tbody>
                </table>
            </div>
            <div id="page-tbl-req-pending" class="pagination-container"></div>

            <div class="card-header" style="border-top: 8px solid var(--bg-main);">
                <h2><i class="fas fa-check-circle" style="margin-right: 8px; color: var(--success);"></i> Processed Requests History</h2>
                <button onclick="openExportModal('tbl-req-history', 0, 'Processed_Store_Requests')" class="btn-export"><i class="fas fa-file-csv"></i> Export CSV</button>
            </div>
            <div class="table-scroll-wrapper">
                <table class="po-table" id="tbl-req-history">
                    <thead><tr><th>Date Processed</th><th>Origin Store</th><th>Request Ledger Details</th><th>Status</th></tr></thead>
                    <tbody id="adminProcessedRequestsTableBody"></tbody>
                </table>
            </div>
            <div id="page-tbl-req-history" class="pagination-container"></div>
        </div>

        <div id="reverseLogisticsPanel" class="card-panel admin-panel" style="display:none;">
            <div class="card-header">
                <h2><i class="fas fa-store-slash" style="margin-right: 8px; color: var(--danger);"></i> Store Closure / Reverse Logistics</h2>
                <button onclick="addReverseLogisticsRow()" class="btn-create"><i class="fas fa-plus"></i> Add Item Line</button>
            </div>
            <div style="padding: 30px;">
                <form id="reverseLogisticsForm" onsubmit="submitReverseLogistics(event)">
                    <div class="form-group" style="margin-bottom: 24px; max-width: 400px;">
                        <label>Search Target Store Node *</label>
                        <input type="text" id="rlStoreInput" list="storeDatalist" placeholder="Type to search store..." required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1.5fr 1fr 1.5fr 1fr 1fr 1fr auto; gap: 12px; margin-bottom: 8px; padding: 0 12px; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: bold;">
                        <div>Item Name *</div>
                        <div>Category *</div>
                        <div>Description</div>
                        <div>Model No.</div>
                        <div>Qty *</div>
                        <div>Action *</div>
                        <div></div>
                    </div>
                    <div id="rlItemsContainer"></div>
                    <div class="form-actions"><button type="submit" class="btn-create" style="background: var(--danger);"><i class="fas fa-bolt"></i> Execute Reverse Handshake & Close Store</button></div>
                </form>
            </div>
        </div>

        <div id="disposedPanel" class="card-panel admin-panel" style="display:none;">
            <div class="card-header">
                <h2><i class="fas fa-trash-alt" style="margin-right: 8px; color: var(--danger);"></i> Disposed Inventory Ledger</h2>
                <button onclick="openExportModal('tbl-disposed', 0, 'Disposed_Inventory')" class="btn-export"><i class="fas fa-file-csv"></i> Export CSV</button>
            </div>
            <div class="table-scroll-wrapper">
                <table class="po-table" id="tbl-disposed">
                    <thead><tr>
                        <th>Date Processed <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Date..."></th>
                        <th>Origin Store <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Store..."></th>
                        <th>SKU / Item Details <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Item..."></th>
                        <th>Quantity <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Qty..."></th>
                        <th>Remarks <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter Remarks..."></th>
                        <th>Processed By <input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter User..."></th>
                    </tr></thead>
                    <tbody id="disposedTableBody"></tbody>
                </table>
            </div>
            <div id="page-tbl-disposed" class="pagination-container"></div>
        </div>

        <div id="accountsPanel" class="card-panel admin-panel" style="display:none;">
            <div class="card-header">
                <h2><i class="fas fa-store" style="margin-right: 8px; color: var(--accent-primary);"></i> Retail Store Nodes Directory</h2>
                <div style="display: flex; gap: 12px;">
                    <button onclick="openExportModal('tbl-stores', -1, 'Store_Directory')" class="btn-export"><i class="fas fa-file-csv"></i> Export</button>
                    <input type="file" id="storeBulkCsv" accept=".csv" style="display:none;" onchange="handleBulkStoreImport(event)">
                    <button onclick="document.getElementById('storeBulkCsv').click()" class="btn-cancel"><i class="fas fa-file-csv"></i> Bulk Import</button>
                    <button onclick="document.getElementById('storeModal').classList.add('active')" class="btn-create"><i class="fas fa-plus"></i> Add Store</button>
                </div>
            </div>
            <div class="table-scroll-wrapper">
                <table class="po-table" id="tbl-stores">
                    <thead><tr><th>Store ID</th><th>Store Name</th><th>Brand (Code)</th><th>Mall / Entity</th><th>City & Route</th></tr></thead>
                    <tbody id="storeTableBody"></tbody>
                </table>
            </div>
            <div id="page-tbl-stores" class="pagination-container"></div>

            <div class="card-header" style="border-top: 8px solid var(--bg-main);">
                <h2><i class="fas fa-building" style="margin-right: 8px; color: var(--accent-primary);"></i> Approved Vendors Registry</h2>
                <div style="display: flex; gap: 12px;">
                    <button onclick="openExportModal('tbl-vendors', -1, 'Vendor_Registry')" class="btn-export"><i class="fas fa-file-csv"></i> Export</button>
                    <button onclick="document.getElementById('vendorModal').classList.add('active')" class="btn-cancel"><i class="fas fa-plus"></i> Add Vendor</button>
                </div>
            </div>
            <div class="table-scroll-wrapper">
                <table class="po-table" id="tbl-vendors">
                    <thead><tr><th>Company Name</th><th>Tax ID</th><th>Payment Terms</th><th>Lead Time</th><th>Contact</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody id="vendorTableBody"></tbody>
                </table>
            </div>
            <div id="page-tbl-vendors" class="pagination-container"></div>

            <div class="card-header" style="border-top: 8px solid var(--bg-main);">
                <h2><i class="fas fa-users" style="margin-right: 8px; color: var(--accent-primary);"></i> Internal Ops Personnel</h2>
                <div style="display: flex; gap: 12px;">
                    <button onclick="openExportModal('tbl-users', -1, 'Personnel_Directory')" class="btn-export"><i class="fas fa-file-csv"></i> Export</button>
                    <button onclick="document.getElementById('userModal').classList.add('active')" class="btn-cancel"><i class="fas fa-plus"></i> Add Personnel</button>
                </div>
            </div>
            <div class="table-scroll-wrapper">
                <table class="po-table" id="tbl-users">
                    <thead><tr><th>Emp ID</th><th>Employee Name</th><th>Dept / Brand</th><th>Actions</th></tr></thead>
                    <tbody id="userTableBody"></tbody>
                </table>
            </div>
            <div id="page-tbl-users" class="pagination-container"></div>
        </div>

        <div id="auditPanel" class="card-panel admin-panel" style="display:none;">
            <div class="card-header">
                <h2><i class="fas fa-history"></i> Accountability Audit Logs</h2>
                <button onclick="openExportModal('tbl-audit', 0, 'Audit_Logs')" class="btn-export"><i class="fas fa-file-csv"></i> Export CSV</button>
            </div>
            <div class="table-scroll-wrapper">
                <table class="po-table" id="tbl-audit">
                    <thead><tr><th>Timestamp</th><th>Operator</th><th>Action</th><th>Details</th></tr></thead>
                    <tbody id="auditTableBody"></tbody>
                </table>
            </div>
            <div id="page-tbl-audit" class="pagination-container"></div>
        </div>
    </div>
</div>

<div id="exportModal" class="modal-overlay">
    <div class="modal-container small">
        <h2><i class="fas fa-file-csv" style="color: var(--success); margin-right: 8px;"></i> Export Data</h2>
        <form id="exportForm" onsubmit="executeDateExport(event)">
            <input type="hidden" id="exportTableId">
            <input type="hidden" id="exportDateColIdx">
            <input type="hidden" id="exportFileName">

            <p style="margin-bottom: 20px; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.5;">
                Select a date range to filter your export. <br>
                <i style="color: var(--text-muted);">Leave dates blank to instantly export all current records.</i>
            </p>
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" id="exportStartDate">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" id="exportEndDate">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('exportModal')">Cancel</button>
                <button type="submit" class="btn-create" style="background: var(--success); border-color: var(--success);"><i class="fas fa-download"></i> Download CSV</button>
            </div>
        </form>
    </div>
</div>

<div id="receivePoModal" class="modal-overlay">
    <div class="modal-container medium">
        <h2><i class="fas fa-box-open"></i> Receive Purchase Order Items</h2>
        <h4 id="recvPoTitle" style="color:var(--accent-primary); margin-bottom: 20px;">PO-XXXX</h4>
        <form id="receivePoForm" onsubmit="submitPOReceipt(event)">
            <input type="hidden" id="recvPoId">
            <table class="po-table" style="margin-bottom: 20px;">
                <thead><tr><th>SKU</th><th>Item Name</th><th>Ordered</th><th>Previously Received</th><th>Receiving Now</th></tr></thead>
                <tbody id="receivePoTableBody"></tbody>
            </table>
            <div class="form-actions"><button type="button" class="btn-cancel" onclick="closeModal('receivePoModal')">Cancel</button><button type="submit" class="btn-create">Submit Receipt</button></div>
        </form>
    </div>
</div>

<div id="poModal" class="modal-overlay">
    <div class="modal-container">
        <h2>📑 Create Purchase Order</h2>
        <form id="poForm" onsubmit="savePO(event)">
            <div class="form-grid">
                <div class="form-group"><label>PO Number</label><input type="text" id="poNumber" placeholder="Leave blank to auto-generate" style="background: var(--bg-main); font-weight: bold;"></div>
                <div class="form-group"><label>Vendor *</label><select id="vendorSelect"><option value="Generic">Generic Vendor</option></select></div>
                <div class="form-group"><label>Delivery Note Number *</label><input type="text" id="poDeliveryNote" required style="border: 1px solid var(--accent-primary);"></div>
                <div class="form-group"><label>Invoice Number</label><input type="text" id="poInvoice"></div>
                <div class="form-group"><label>PO Category *</label><select id="poCategory" required><option value="ICT">ICT</option><option value="WHS PO">WHS PO</option></select></div>
                <div class="form-group">
                    <label>Assigned Store / Target Node</label>
                    <input type="text" id="poAssignedStore" list="storeDatalist" placeholder="Leave blank for Central Warehouse">
                </div>
                <div class="form-group"><label>Order Date *</label><input type="date" id="orderDate" required></div>
                <div class="form-group"><label>Requesting Department</label><input type="text" id="department" placeholder="e.g. Warehouse"></div>
            </div>
            <div style="margin-top: 24px; border-top: 1px solid var(--border-sharp); padding-top: 24px;">
                <h3 style="color: var(--accent-primary); margin-bottom: 16px;"><i class="fas fa-boxes"></i> Line Items</h3>
                <div id="lineItemsContainer"></div>
                <button type="button" onclick="addNewLineItem()" class="btn-cancel"><i class="fas fa-plus"></i> Add Line Item</button>
            </div>
            <div class="form-grid" style="margin-top: 32px; align-items: end;">
                <div class="form-group"><label>Subtotal</label><input type="text" id="subtotal" readonly style="background: var(--bg-main); font-weight: 600;"></div>
                <div class="form-group"><label>Tax Amount</label><input type="number" id="taxAmount" value="0" step="0.01" oninput="recalcTotals()"></div>
                <div class="form-group" style="grid-column: span 2;"><label>GRAND TOTAL (SAR)</label><input type="text" id="grandTotal" readonly style="font-weight:800; color: var(--success); font-size: 1.2rem;"></div>
            </div>
            <div class="form-actions"><button type="button" class="btn-cancel" onclick="closeModal('poModal')">Cancel</button><button type="submit" class="btn-create">Save PO</button></div>
        </form>
    </div>
</div>

<div id="editPoModal" class="modal-overlay">
    <div class="modal-container small">
        <h2><i class="fas fa-edit" style="color: var(--accent-primary); margin-right: 8px;"></i> Edit Ledger Details</h2>
        <form id="editPoForm" onsubmit="submitEditPO(event)">
            <input type="hidden" id="editPoId">
            <div class="form-group" style="margin-bottom: 16px;">
                <label>Official PO Number</label>
                <input type="text" id="editPoNumber" style="border: 1px solid var(--accent-primary);">
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <label>Delivery Note Number</label>
                <input type="text" id="editPoDeliveryNote">
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <label>Invoice Number</label>
                <input type="text" id="editPoInvoice">
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('editPoModal')">Cancel</button>
                <button type="submit" class="btn-create">Update Ledger</button>
            </div>
        </form>
    </div>
</div>

<div id="dispatchFlowModal" class="modal-overlay">
    <div class="modal-container medium">
        <h2><i class="fas fa-truck-ramp-box" style="color: var(--accent-primary);"></i> Dispatch Operations</h2>
        <div style="margin-bottom: 16px; display: flex; gap: 8px; align-items: center; border-bottom: 1px solid var(--border-sharp); padding-bottom: 12px;">
            <div id="dotStep1" style="font-weight: bold; color: var(--text-primary);">Step 1</div>
            <i class="fas fa-chevron-right" style="color: var(--text-muted); font-size: 0.8rem;"></i>
            <div id="dotStep2" style="color: var(--text-muted);">Step 2</div>
        </div>
        
        <div id="dispatchStep1">
            <h4 style="margin-bottom: 16px; color: var(--text-secondary);">Select Dispatch Assignee</h4>
            <div style="display: flex; gap: 20px; margin-bottom: 24px;">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                    <input type="radio" name="assigneeType" value="store" checked onchange="toggleAssigneeType()"> Target Store
                </label>
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                    <input type="radio" name="assigneeType" value="person" onchange="toggleAssigneeType()"> Personnel
                </label>
            </div>

            <div id="storeFields">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Store ID *</label>
                        <input type="text" id="dfStoreId" placeholder="Enter ID..." oninput="autofillFromId()">
                    </div>
                    <div class="form-group">
                        <label>Search Store Name</label>
                        <input type="text" id="dfStoreInput" list="storeDatalist" onchange="autofillFromDatalist('dfStoreInput', 'dfStoreId')" placeholder="Type to search...">
                    </div>
                    <div class="form-group"><label>Brand Name</label><input type="text" id="dfStoreBrand" readonly style="background: var(--bg-surface);"></div>
                    <div class="form-group"><label>Brand Code</label><input type="text" id="dfStoreCode" readonly style="background: var(--bg-surface);"></div>
                    <div class="form-group"><label>Mall / Entity Context</label><input type="text" id="dfStoreEntity" readonly style="background: var(--bg-surface);"></div>
                    <div class="form-group"><label>Route Code</label><input type="text" id="dfStoreRoute" readonly style="background: var(--bg-elevated); font-weight: bold; color: var(--text-primary);"></div>
                </div>
            </div>

            <div id="personFields" style="display: none;">
                <div class="form-grid">
                    <div class="form-group"><label>Select Personnel</label><select id="dfPersonSelect" onchange="autofillPersonDetails()"><option value="">-- Choose Personnel --</option></select></div>
                    <div class="form-group"><label>Emp ID</label><input type="text" id="dfPersonId" readonly style="background: var(--bg-surface);"></div>
                    <div class="form-group"><label>Emp Name</label><input type="text" id="dfPersonName" readonly style="background: var(--bg-surface);"></div>
                    <div class="form-group"><label>Dept / Brand</label><input type="text" id="dfPersonDept" readonly style="background: var(--bg-surface);"></div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('dispatchFlowModal')">Cancel</button>
                <button type="button" class="btn-create" onclick="goToDispatchStep2()">Next Step <i class="fas fa-arrow-right"></i></button>
            </div>
        </div>

        <div id="dispatchStep2" style="display: none;">
            <h4 style="margin-bottom: 16px; color: var(--text-secondary);">Apportion Stock Matrix</h4>
            <div style="display: flex; gap: 8px; margin-bottom: 16px; align-items: center;">
                <i class="fas fa-search" style="color: var(--text-muted);"></i>
                <input type="text" id="dispatchSearch" placeholder="Search SKU, Details..." oninput="searchDispatchItems()" style="flex: 1;">
            </div>
            <div style="max-height: 200px; overflow-y: auto; margin-bottom: 24px; border: 1px solid var(--border-sharp);" id="dispatchSearchResults"></div>
            
            <h4 style="margin-bottom: 12px; border-top: 1px solid var(--border-sharp); padding-top: 16px;">Manifest Handover Staging</h4>
            <div id="dispatchCartContainer"></div>
            
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="goToDispatchStep1()"><i class="fas fa-arrow-left"></i> Back</button>
                <button type="button" class="btn-create" onclick="submitDispatch()">Confirm Handover</button>
            </div>
        </div>
    </div>
</div>

<div id="dispatchDetailsModal" class="modal-overlay">
    <div class="modal-container small" style="background: #ffffff; color: #000000; max-width: 600px;">
        <div class="receipt-view" id="receiptContent" style="border: none;">
            <h2 style="border-bottom: 2px dashed #000; padding-bottom: 10px; margin-bottom: 16px;">DISPATCH RECEIPT</h2>
            <div style="margin-bottom: 15px; font-size: 13px;">
                <strong>Manifest ID:</strong> <span id="detailDispatchId"></span><br>
                <strong>Date:</strong> <span id="detailDate"></span><br>
                <strong>Recipient:</strong> <span id="detailRecipient"></span>
            </div>
            <table class="receipt-table">
                <thead><tr><th>SKU</th><th>Item</th><th>Vendor</th><th>Qty</th></tr></thead>
                <tbody id="dispatchDetailsTableBody"></tbody>
            </table>
            <div style="text-align:center; font-size:12px; margin-top:30px; border-top:1px dashed #ccc; padding-top:10px;">
                Authorized by: Admin Ops<br>Apparel Group Procurement
            </div>
        </div>
        <div class="form-actions" style="border-top: none; justify-content: center; margin-top: 20px;">
            <button type="button" class="btn-cancel" style="border: 1px solid #ccc;" onclick="closeModal('dispatchDetailsModal')">Close</button>
            <button type="button" class="btn-create" id="printReceiptBtn" style="background: #000; color: #fff; border: none;"><i class="fas fa-print"></i> Download / Print PDF</button>
        </div>
    </div>
</div>

<div id="storeModal" class="modal-overlay">
    <div class="modal-container small">
        <h2>🏪 Register Retail Node Asset</h2>
        <form id="storeForm" onsubmit="saveStore(event)">
            <div class="form-group" style="margin-bottom: 20px;"><label>Store Corporate Name *</label><input type="text" id="sName" placeholder="e.g. ADIDAS SOLITAIRE" required></div>
            <div class="form-grid">
                <div class="form-group"><label>Brand Umbrella Name *</label><input type="text" id="sBrand" required></div>
                <div class="form-group"><label>Brand Code Identifier</label><input type="text" id="sBrandCode"></div>
                <div class="form-group"><label>Mall Complex Context</label><input type="text" id="sMall"></div>
                <div class="form-group"><label>Entity Subsidiary Scope</label><input type="text" id="sEntity" required></div>
                <div class="form-group"><label>City Location Geography</label><input type="text" id="sCity"></div>
                <div class="form-group"><label>Route Code Mapping</label><input type="text" id="sRoute"></div>
            </div>
            <div class="form-actions"><button type="button" class="btn-cancel" onclick="closeModal('storeModal')">Cancel</button><button type="submit" class="btn-create">Save Store</button></div>
        </form>
    </div>
</div>

<div id="preflightVendorModal" class="modal-overlay">
    <div class="modal-container">
        <h2><i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> Unregistered Vendors Detected</h2>
        <p style="margin-bottom: 16px; font-size: 0.9rem; color: var(--text-secondary);">The imported POs contain vendors that are not currently mapped in the approved registry. Please confirm their details before ingestion.</p>
        <div id="preflightContainer" style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;"></div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('preflightVendorModal')">Abort Import</button>
            <button type="button" class="btn-create" onclick="commitPreflightVendors()">Register & Proceed</button>
        </div>
    </div>
</div>

<div id="vendorModal" class="modal-overlay">
    <div class="modal-container small">
        <h2><i class="fas fa-building" style="color: var(--accent-primary); margin-right: 8px;"></i> Register Vendor</h2>
        <form id="vendorForm" onsubmit="saveVendor(event)">
            <div class="form-group" style="margin-bottom: 12px;"><label>Company Name *</label><input type="text" id="vCompany" required></div>
            <div class="form-group" style="margin-bottom: 12px;"><label>Tax ID / VAT Registration</label><input type="text" id="vTax"></div>
            <div class="form-group" style="margin-bottom: 12px;"><label>Payment Terms</label><input type="text" id="vTerms" placeholder="e.g. Net 30"></div>
            <div class="form-group" style="margin-bottom: 12px;"><label>Lead Time</label><input type="text" id="vLead" placeholder="e.g. 5 Days"></div>
            <div class="form-group" style="margin-bottom: 12px;"><label>Contact Email</label><input type="email" id="vEmail"></div>
            <div class="form-actions"><button type="button" class="btn-cancel" onclick="closeModal('vendorModal')">Cancel</button><button type="submit" class="btn-create">Save Vendor</button></div>
        </form>
    </div>
</div>

<div id="userModal" class="modal-overlay">
    <div class="modal-container small">
        <h2><i class="fas fa-user-plus" style="color: var(--accent-primary); margin-right: 8px;"></i> Add Personnel</h2>
        <form id="userForm" onsubmit="saveUser(event)">
            <div class="form-group" style="margin-bottom: 12px;"><label>Employee ID *</label><input type="text" id="uId" required></div>
            <div class="form-group" style="margin-bottom: 12px;"><label>Employee Name *</label><input type="text" id="uName" required></div>
            <div class="form-group" style="margin-bottom: 12px;"><label>Department / Brand</label><input type="text" id="uDept"></div>
            <div class="form-actions"><button type="button" class="btn-cancel" onclick="closeModal('userModal')">Cancel</button><button type="submit" class="btn-create">Save User</button></div>
        </form>
    </div>
</div>

<div id="toastMessage" class="toast-msg"></div>

<style>
    #loginView { display: none !important; }
</style>

<script>
    // 2. Ingest the secure session data from PHP
    const iamRole = "<?php echo $primary_role; ?>";
    const iamUser = "<?php echo $username; ?>";
    const iamName = "<?php echo $fullname; ?>";

    // 3. Trick app.js into thinking it handled the login natively
    // CRITICAL FIX: We are now using the EXACT keys app.js is looking for!
    localStorage.setItem('procurement_user', iamUser);
    localStorage.setItem('procurement_role', iamRole);
    localStorage.setItem('procurement_route', 'Central'); 

    // 4. Force the correct view to open
    function forceAppOpen() {
        const displayUserEl = document.getElementById('displayUser');
        if(displayUserEl) displayUserEl.innerText = iamName;

        if (iamRole === 'admin') {
            document.getElementById('appView').style.display = 'flex';
            document.getElementById('storeView').style.display = 'none';
        } else {
            document.getElementById('storeView').style.display = 'flex';
            document.getElementById('appView').style.display = 'none';
            
            const storeNameEl = document.getElementById('storeNameDisplay');
            if(storeNameEl) storeNameEl.innerText = iamUser;
        }
    }

    // Run it immediately, on DOM load, AND half a second later just to ensure app.js is totally overridden
    forceAppOpen();
    window.addEventListener('DOMContentLoaded', forceAppOpen);
    setTimeout(forceAppOpen, 500);

    // 5. Override the local handleLogout function
    window.handleLogout = function() {
        localStorage.clear();
        window.location.href = '../api_logout.php';
    };
</script>

<script src="app.js"></script>
</body>
</html>