<?php
// 1. FORCE MASTER DOMAIN COOKIE
ini_set('session.cookie_path', '/');
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
session_name('PHPSESSID');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['iam_user'])) {
    header("Location: ../api_logout.php");
    exit;
}

// 2. EXTRACT IT PROCUREMENT ROLES
$it_roles = $_SESSION['iam_user']['roles']['it_procurement'] ?? [];

if (empty($it_roles)) {
    die("<div style='background:#f1f5f9; height:100vh; color:#ef4444; text-align:center; padding-top:100px; font-family:sans-serif;'><h2>Access Denied</h2><p>You do not have clearance for IT Procurement.</p></div>");
}

$primary_role = $it_roles[0]; 
$username = $_SESSION['iam_user']['username'];
$fullname = $_SESSION['iam_user']['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Apparel - IT Asset Management Suite Enterprise</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* =========================================================
           GLOBAL THEME: MODERN SAAS ERP (UX OPTIMIZED)
           ========================================================= */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --erp-bg-body: #f1f5f9;      /* Slate 100 - soft workspace bg */
            --erp-bg-panel: #ffffff;
            --erp-header-top: #0f172a;   /* Slate 900 */
            --erp-header-nav: #1e293b;   /* Slate 800 */
            --erp-nav-hover: #334155;
            
            --erp-text-main: #334155;
            --erp-text-muted: #64748b;
            --erp-text-blue: #2563eb;    /* Blue 600 */
            
            --erp-border: #e2e8f0;
            --erp-border-hover: #cbd5e1;
            
            --erp-portlet-header: #f8fafc; 
            
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
            --dispatched: #4f46e5;
            
            --radius-md: 8px;
            --radius-sm: 6px;
            --shadow-card: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
        }
        
        html, body { 
            height: 100vh; 
            width: 100vw;
            overflow: hidden;
            font-family: 'Inter', sans-serif; 
            background: var(--erp-bg-body); 
            color: var(--erp-text-main); 
            font-size: 13px;
            -webkit-font-smoothing: antialiased;
        }
        
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: var(--radius-sm); }

        #loginView { display: none !important; }

        .app-wrapper { display: flex; flex-direction: column; height: 100vh; width: 100vw; overflow: hidden; }

        /* --- TOP UTILITY BAR --- */
        .ns-top-bar {
            height: 55px;
            background: var(--erp-header-top);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 24px;
            flex-shrink: 0;
            z-index: 100;
        }
        .ns-logo { font-size: 1.1rem; font-weight: 700; color: #f8fafc; letter-spacing: 0.5px; display: flex; align-items: center; gap: 10px; text-transform: uppercase; }
        .ns-logo i { color: #38bdf8; font-size: 1.4rem; }
        .ns-search-bar { flex: 1; max-width: 500px; margin: 0 30px; position: relative; }
        .ns-search-bar input { width: 100%; padding: 10px 14px 10px 38px; border: 1px solid transparent; background: #1e293b; color: #fff; border-radius: 20px; font-size: 12px; transition: all 0.2s; }
        .ns-search-bar input:focus { background: #fff; color: #000; outline: none; box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.4); }
        .ns-search-bar i { position: absolute; left: 16px; top: 11px; color: #94a3b8; font-size: 13px; }
        .ns-user-menu { font-size: 13px; font-weight: 500; color: #e2e8f0; display: flex; align-items: center; gap: 20px; }

        /* --- MAIN MODULE NAVIGATION --- */
        .ns-main-nav {
            height: 48px;
            background: var(--erp-header-nav);
            display: flex;
            align-items: center;
            padding: 0 15px;
            flex-shrink: 0;
            z-index: 90;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .ns-main-nav .nav-item {
            color: #cbd5e1;
            padding: 0 18px;
            height: 100%;
            display: flex;
            align-items: center;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 3px solid transparent;
        }
        .ns-main-nav .nav-item:hover { color: #fff; background: var(--erp-nav-hover); }
        .ns-main-nav .nav-item.active { color: #fff; border-bottom: 3px solid #38bdf8; background: transparent; font-weight: 600; }
        .ns-main-nav .nav-item i { margin-right: 8px; font-size: 14px; opacity: 0.8; }

        .ns-workspace { display: flex; flex: 1; overflow: hidden; padding: 24px; gap: 24px; }

        /* --- LEFT SIDEBAR SHORTCUTS --- */
        .ns-sidebar {
            width: 240px;
            background: var(--erp-bg-panel);
            border: 1px solid var(--erp-border);
            border-radius: var(--radius-md);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            flex-shrink: 0;
            box-shadow: var(--shadow-card);
        }
        .ns-sidebar-header {
            background: var(--erp-portlet-header);
            padding: 15px 20px;
            font-weight: 700;
            color: var(--erp-text-main);
            border-bottom: 1px solid var(--erp-border);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ns-sidebar .nav-item {
            padding: 12px 20px;
            color: var(--erp-text-main);
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--erp-border);
            transition: background 0.1s;
        }
        .ns-sidebar .nav-item:hover { background: #f8fafc; color: var(--erp-text-blue); }
        .ns-sidebar .nav-item.active { background: #eff6ff; font-weight: 600; color: var(--erp-text-blue); border-left: 3px solid var(--erp-text-blue); padding-left: 17px; }
        .ns-sidebar .nav-item i { width: 22px; text-align: center; color: var(--erp-text-muted); font-size: 13px; }

        .ns-content { flex: 1; overflow-y: auto; display: flex; flex-direction: column; position: relative; }

        /* --- PORTLETS / PANELS --- */
        .card-panel { display: none; width: 100%; flex-direction: column; gap: 24px; }
        .card-panel.active-panel { display: flex; }
        
        .portlet, .card-header-wrapper {
            background: var(--erp-bg-panel);
            border: 1px solid var(--erp-border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-card);
            overflow: hidden;
            margin-bottom: 10px;
        }
        .card-header {
            background: var(--erp-portlet-header);
            padding: 16px 24px;
            border-bottom: 1px solid var(--erp-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h2 { font-size: 15px; font-weight: 600; color: var(--erp-text-main); margin: 0; display: flex; align-items: center; text-transform: uppercase; letter-spacing: 0.5px; }
        .card-header h2 i { margin-right: 10px; color: var(--erp-text-blue); font-size: 16px; }
        
        /* --- MODERN PREMIUM BUTTONS --- */
        .btn-create, .btn-cancel, .btn-logout, .btn-export { 
            border-radius: var(--radius-sm);
            padding: 8px 16px; 
            font-size: 12px; 
            font-weight: 600; 
            letter-spacing: 0.3px; 
            cursor: pointer; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
            border: 1px solid transparent; 
            text-transform: uppercase;
            font-family: inherit;
        }
        .btn-create { background: var(--erp-text-blue); color: #fff; box-shadow: 0 1px 2px rgba(37, 99, 235, 0.3); }
        .btn-create:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.4); }
        .btn-create:active { transform: translateY(0); box-shadow: none; }
        
        .btn-cancel { background: #fff; color: #475569; border-color: #cbd5e1; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .btn-cancel:hover { background: #f8fafc; color: #0f172a; border-color: #94a3b8; transform: translateY(-1px); }
        
        .btn-export { background: #fef9c3; color: #b45309; border-color: #fde047; box-shadow: 0 1px 2px rgba(253, 224, 71, 0.2); }
        .btn-export:hover { background: #fef08a; border-color: #facc15; transform: translateY(-1px); }
        
        .btn-logout { border: none; background: transparent; color: #f87171; font-size: 16px; padding: 0; box-shadow: none; }
        .btn-logout:hover { color: #dc2626; background: transparent; transform: scale(1.1); }

        /* --- UX REFINEMENTS: SUB-TABS & DROPDOWNS --- */
        .erp-sub-nav { 
            display: flex; 
            gap: 15px; 
            padding: 15px 24px 0 24px; 
            border-bottom: 1px solid var(--erp-border); 
            background: #f8fafc; 
        }
        .erp-sub-nav button { 
            background: transparent; 
            border: none; 
            padding: 10px 15px; 
            font-size: 13px; 
            font-weight: 600; 
            color: var(--erp-text-muted); 
            cursor: pointer; 
            border-bottom: 3px solid transparent; 
            transition: all 0.2s; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
        }
        .erp-sub-nav button:hover { color: var(--erp-text-blue); }
        .erp-sub-nav button.active { color: var(--erp-text-blue); border-bottom: 3px solid var(--erp-text-blue); }

        .dir-select { 
            width: 240px; 
            padding: 8px 12px; 
            border: 1px solid #cbd5e1; 
            border-radius: var(--radius-sm); 
            font-size: 13px; 
            font-weight: 600; 
            color: #0f172a; 
            cursor: pointer; 
            background: #fff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: border-color 0.2s;
        }
        .dir-select:focus { outline: none; border-color: var(--erp-text-blue); }

        /* --- DENSE DATA TABLES (FIXED FILTERS) --- */
        .table-scroll-wrapper { width: 100%; overflow-x: auto; background: var(--erp-bg-panel); }
        .po-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        
        .po-table th { 
            background: var(--erp-portlet-header); 
            padding: 12px 16px; 
            border-top: 1px solid var(--erp-border); 
            border-bottom: 2px solid var(--erp-border); 
            border-right: 1px solid var(--erp-border);
            vertical-align: top;
        }
        .po-table td { 
            padding: 12px 16px; 
            border-bottom: 1px solid var(--erp-border); 
            border-right: 1px solid var(--erp-border); 
            vertical-align: middle; 
            color: var(--erp-text-main); 
        }
        .po-table tbody tr:hover td { background: #f8fafc; }
        
        .th-title {
            display: block;
            font-weight: 600;
            color: var(--erp-text-muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            white-space: nowrap;
        }

        .column-filter { 
            display: block;
            width: 100%; 
            min-width: 120px; 
            padding: 6px 10px; 
            font-size: 11px; 
            border: 1px solid #cbd5e1; 
            border-radius: 4px; 
            background: #ffffff;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);
            font-weight: 400;
            color: #334155;
            box-sizing: border-box;
            transition: all 0.2s;
        }
        .column-filter:focus { border-color: #38bdf8; outline: none; box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2); }
        .column-filter::placeholder { color: #94a3b8; }

        .pagination-container { display: flex; justify-content: space-between; align-items: center; padding: 14px 24px; background: #fff; font-size: 12px; border-top: 1px solid var(--erp-border); color: var(--erp-text-muted); border-radius: 0 0 var(--radius-md) var(--radius-md); }
        .pagination-controls button { background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; color: #334155; padding: 6px 14px; cursor: pointer; margin-left: 8px; font-size: 12px; font-weight: 500; transition: all 0.2s; }
        .pagination-controls button:disabled { opacity: 0.4; cursor: not-allowed; }
        .pagination-controls button:hover:not(:disabled) { background: #f1f5f9; border-color: #cbd5e1; }

        /* --- STATUS BADGES --- */
        .status-badge { display: inline-flex; align-items: center; justify-content: center; padding: 4px 10px; font-size: 10px; font-weight: 700; text-transform: uppercase; border-radius: 12px; letter-spacing: 0.5px; }
        .status-approved, .status-complete { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .status-partial { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
        .status-pending { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .status-dispatched { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }

        /* --- FORMS --- */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-weight: 600; font-size: 11px; color: var(--erp-text-muted); text-transform: uppercase; }
        input[type="text"], input[type="date"], input[type="number"], input[type="email"], select { 
            padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: var(--radius-sm); 
            font-family: inherit; font-size: 13px; width: 100%; box-sizing: border-box; background: #fff; color: #0f172a; transition: border-color 0.2s;
        }
        input:focus, select:focus { border-color: #38bdf8; outline: none; box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2); }

        /* --- DASHBOARD WIDGETS --- */
        .dash-grid-top { display: grid; grid-template-columns: 1fr 3fr; gap: 24px; margin-bottom: 10px; }
        .suite-access-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; padding: 24px; }
        .sa-block { padding: 24px; border-radius: var(--radius-md); border: 1px solid var(--erp-border); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 12px; font-weight: 600; color: var(--erp-text-main); background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .sa-yellow { border-top: 4px solid #f59e0b; }
        .sa-pink { border-top: 4px solid #ef4444; }
        .sa-blue { border-top: 4px solid #3b82f6; }
        .sa-grey { border-top: 4px solid #8b5cf6; }
        .sa-block .val { font-size: 32px; font-weight: 700; color: #0f172a; }

        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; padding: 24px; background: #fff; }
        .kpi-card { border: 1px solid var(--erp-border); padding: 24px; text-align: center; background: #f8fafc; border-radius: var(--radius-md); transition: transform 0.2s; }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-card); }
        .kpi-card .title { font-size: 11px; color: var(--erp-text-muted); text-transform: uppercase; margin-bottom: 12px; font-weight: 700; letter-spacing: 0.5px; }
        .kpi-card .value { font-size: 28px; font-weight: 700; color: #0f172a; }
        .kpi-card .trend-up { color: var(--success); font-size: 12px; font-weight: 600; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 4px; }
        .kpi-card .trend-down { color: var(--danger); font-size: 12px; font-weight: 600; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 4px; }

        /* --- MODALS --- */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px); display: flex; align-items: center; justify-content: center; z-index: 1000; visibility: hidden; opacity: 0; transition: opacity 0.2s; }
        .modal-overlay.active { visibility: visible; opacity: 1; }
        .modal-container { background: #fff; width: 850px; max-width: 95vw; border-radius: var(--radius-md); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); border: 1px solid #e2e8f0; display: flex; flex-direction: column; max-height: 90vh; overflow: hidden; }
        .modal-container > h2, .modal-header { background: #fff; color: #0f172a; padding: 20px 25px; font-size: 15px; font-weight: 700; display: flex; justify-content: space-between; margin: 0; align-items: center; border-bottom: 1px solid var(--erp-border); text-transform: uppercase; letter-spacing: 0.5px; }
        .modal-container > h2 i, .modal-header i { color: var(--erp-text-blue); margin-right: 10px; }
        .modal-container form, .modal-body { padding: 30px 25px; overflow-y: auto; flex: 1; }
        .form-actions, .modal-footer { padding: 15px 25px; background: #f8fafc; border-top: 1px solid var(--erp-border); display: flex; justify-content: flex-end; gap: 12px; margin: 0; }

        .toast-msg { position: fixed; bottom: 30px; right: 30px; background: #1e293b; color: #fff; padding: 14px 24px; font-size: 13px; font-weight: 500; border-radius: var(--radius-md); z-index: 1100; opacity: 0; transition: opacity 0.3s; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border: 1px solid #334155; }
        .toast-msg.show { opacity: 1; transform: translateY(-10px); }
        
        .action-icons i { margin: 0 8px; color: var(--erp-text-muted); cursor: pointer; font-size: 14px; transition: color 0.2s; }
        .action-icons i:hover { color: var(--erp-text-blue); }
        .action-icons i.fa-trash-alt:hover { color: var(--danger); }
    </style>
</head>
<body>

<datalist id="storeDatalist"></datalist>
<!-- Validated Hardware Categories Datalsit -->
<datalist id="hardwareTypesList">
    <option value="SYSTEM"></option><option value="MONITER"></option><option value="CASH DRAWYER"></option>
    <option value="BARCODE READER"></option><option value="EPSON PRINTER"></option><option value="FORTINET"></option>
    <option value="TRAFFIC DEVICE"></option><option value="BIOMETRIC"></option><option value="MPOSE"></option>
    <option value="Acces Point"></option><option value="A4 Printer"></option><option value="SWITCH"></option>
    <option value="Router"></option><option value="SIMCARD"></option><option value="CAMERA"></option>
    <option value="NVR"></option>
</datalist>

<div id="storeView" class="app-wrapper" style="display: none;">
    <div class="ns-top-bar">
        <div class="ns-logo"><i class="fas fa-store"></i> Node Portal</div>
        <div class="ns-user-menu">
            <span style="background: #334155; padding: 6px 12px; border-radius: 20px;">ROUTE: <span id="storeRouteDisplay" style="color: #38bdf8;"></span></span>
            <span><i class="fas fa-map-marker-alt"></i> <span id="storeNameDisplay"></span></span>
            <button onclick="handleLogout()" class="btn-logout"><i class="fas fa-power-off"></i></button>
        </div>
    </div>
    <nav class="ns-main-nav">
        <div class="nav-item active" data-tab="storeInventory"><i class="fas fa-box"></i> Assigned Assets</div>
        <div class="nav-item" data-tab="storeRequest"><i class="fas fa-hand-paper"></i> Request H/W</div>
    </nav>
    
    <div class="ns-workspace">
        <main class="ns-content">
            <div id="storeInventoryPanel" class="card-panel active-panel card-header-wrapper">
                <div class="card-header">
                    <h2><i class="fas fa-list"></i> Dispatched Hardware</h2>
                    <button onclick="openExportModal('tbl-store-inv', -1, 'My_Assigned_Assets')" class="btn-export"><i class="fas fa-file-export"></i> Export</button>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="po-table" id="tbl-store-inv">
                        <thead><tr>
                            <th><span class="th-title">Manifest ID</span></th>
                            <th><span class="th-title">SKU Code</span></th>
                            <th><span class="th-title">Item Details</span></th>
                            <th><span class="th-title">Category</span></th>
                            <th><span class="th-title">Qty Received</span></th>
                            <th><span class="th-title">Actions</span></th>
                        </tr></thead>
                        <tbody id="storeInventoryTableBody"></tbody>
                    </table>
                </div>
                <div id="page-tbl-store-inv" class="pagination-container"></div>
            </div>

            <div id="storeRequestPanel" class="card-panel card-header-wrapper">
                <div class="card-header">
                    <h2><i class="fas fa-plus-square"></i> Requisition Form</h2>
                    <button onclick="submitStoreRequest()" class="btn-create"><i class="fas fa-paper-plane"></i> Submit Request</button>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="po-table" id="tbl-store-req">
                        <thead><tr>
                            <th><span class="th-title">SKU</span></th>
                            <th><span class="th-title">Item Name & Details</span></th>
                            <th><span class="th-title">Type Context</span></th>
                            <th><span class="th-title">Warehouse Balance</span></th>
                            <th><span class="th-title">Target Qty</span></th>
                        </tr></thead>
                        <tbody id="storeCatalogTableBody"></tbody>
                    </table>
                </div>
                <div id="page-tbl-store-req" class="pagination-container"></div>
                
                <div style="padding: 20px; background: #f8fafc; border-top: 1px solid var(--erp-border);">
                    <h3 style="font-size: 12px; font-weight: 700; color: var(--erp-text-main); margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-history" style="color: var(--erp-text-muted);"></i> Request Ledger</h3>
                    <div id="storePortalRequestStatusContainer" style="max-height:250px; overflow-y:auto;"></div>
                </div>
            </div>
        </main>
    </div>
</div>

<div id="appView" class="app-wrapper" style="display: none;">
    
    <div class="ns-top-bar">
        <div class="ns-logo"><i class="fas fa-cubes"></i> APPAREL SUITE ERP</div>
        <div class="ns-search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="globalSearch" placeholder="Jump to module (e.g. 'PO', 'Inventory', 'Recovery')..." onkeyup="handleGlobalSearch(event)">
        </div>
        <div class="ns-user-menu">
            <span><i class="fas fa-question-circle" style="color: #94a3b8; margin-right: 5px;"></i> Help</span>
            <span><i class="fas fa-user-circle" style="color: #94a3b8; margin-right: 5px;"></i> <span id="displayUser">Admin</span> (IT)</span>
            <button onclick="handleLogout()" class="btn-logout"><i class="fas fa-sign-out-alt"></i></button>
        </div>
    </div>

    <nav class="ns-main-nav">
        <div class="nav-item admin-tab active" data-tab="dashboardPanel"><i class="fas fa-home"></i> Home</div>
        <div class="nav-item admin-tab" data-tab="po"><i class="fas fa-file-invoice-dollar"></i> Purchase Orders</div>
        <div class="nav-item admin-tab" data-tab="inventory"><i class="fas fa-boxes"></i> Master Inventory</div>
        <div class="nav-item admin-tab" data-tab="dispatch"><i class="fas fa-truck"></i> Dispatch Log</div>
        <div class="nav-item admin-tab" data-tab="storeStock"><i class="fas fa-store-alt"></i> Node Stock</div>
        <div class="nav-item admin-tab" data-tab="assetRecovery"><i class="fas fa-sync"></i> Asset Recovery</div>
        <div class="nav-item admin-tab" data-tab="accounts"><i class="fas fa-users"></i> Directory</div>
    </nav>

    <div class="ns-workspace">
        
        <aside class="ns-sidebar">
            <div class="ns-sidebar-header">Shortcuts</div>
            <div style="padding: 10px 0;">
                <div style="padding: 5px 20px; font-weight: 700; color: #94a3b8; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Assets & Ledgers</div>
                <div class="nav-item admin-tab" data-tab="itAssets"><i class="fas fa-desktop"></i> Master Fleet</div>
                <div class="nav-item admin-tab" data-tab="storeRequestsAdmin"><i class="fas fa-hand-paper"></i> Node Requests</div>
                
                <div style="padding: 5px 20px; font-weight: 700; color: #94a3b8; font-size: 11px; margin-top: 15px; text-transform: uppercase; letter-spacing: 0.5px;">Security</div>
                <div class="nav-item admin-tab" data-tab="audit"><i class="fas fa-clipboard-list"></i> Audit Trail</div>
            </div>
        </aside>

        <main class="ns-content">
            
            <div id="dashboardPanel" class="card-panel admin-panel active-panel">
                <div class="dash-grid-top">
                    <div class="portlet">
                        <div class="card-header"><h2><i class="fas fa-bell"></i> Reminders</h2></div>
                        <div style="padding: 25px; display: flex; flex-direction: column; gap: 20px;">
                            <div style="display: flex; align-items: center; gap: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--erp-border);">
                                <div style="font-size: 32px; color: var(--erp-text-blue); font-weight: 600;" id="metric-pending">0</div>
                                <div style="font-size: 13px; color: var(--erp-text-main); cursor: pointer; font-weight: 500;" onclick="document.querySelector('[data-tab=\'storeRequestsAdmin\']').click()">Requests to Approve</div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div style="font-size: 32px; color: var(--danger); font-weight: 600;" id="inv-low-stock-dash">0</div>
                                <div style="font-size: 13px; color: var(--erp-text-main); cursor: pointer; font-weight: 500;" onclick="document.querySelector('[data-tab=\'inventory\']').click()">Low Stock Alerts</div>
                            </div>
                        </div>
                    </div>

                    <div class="portlet">
                        <div class="card-header">
                            <h2><i class="fas fa-chart-line"></i> Infrastructure Overview</h2>
                            <select id="dashboardStoreFilter" onchange="runDashboardAnalytics()" style="width: auto; padding: 6px 12px; font-size: 12px; height: auto;">
                                <option value="ALL">All Retail Nodes (Global)</option>
                            </select>
                        </div>
                        <div class="suite-access-grid">
                            <div class="sa-block sa-yellow">
                                <i class="fas fa-store" style="font-size: 24px; color: #f59e0b;"></i>
                                <span class="val" id="metric-stores">0</span>
                                <span style="font-size: 11px; text-transform: uppercase; color: var(--erp-text-muted);">Active Nodes</span>
                            </div>
                            <div class="sa-block sa-pink">
                                <i class="fas fa-users" style="font-size: 24px; color: #ef4444;"></i>
                                <span class="val" id="metric-persons">0</span>
                                <span style="font-size: 11px; text-transform: uppercase; color: var(--erp-text-muted);">Personnel</span>
                            </div>
                            <div class="sa-block sa-blue">
                                <i class="fas fa-desktop" style="font-size: 24px; color: #3b82f6;"></i>
                                <span class="val" id="metric-desktops">0</span>
                                <span style="font-size: 11px; text-transform: uppercase; color: var(--erp-text-muted);">Core Assets</span>
                            </div>
                            <div class="sa-block sa-grey">
                                <i class="fas fa-truck-loading" style="font-size: 24px; color: #8b5cf6;"></i>
                                <span class="val" id="disp-total-dash">0</span>
                                <span style="font-size: 11px; text-transform: uppercase; color: var(--erp-text-muted);">Total Dispatches</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="portlet">
                    <div class="card-header"><h2><i class="fas fa-server"></i> Key Performance Indicators (Fleet Breakdown)</h2></div>
                    <div class="kpi-grid">
                        <div class="kpi-card">
                            <div class="title">Thermal Printers</div>
                            <div class="value" id="metric-printers">0</div>
                            <div class="trend-up"><i class="fas fa-arrow-up"></i> Active Fleet</div>
                        </div>
                        <div class="kpi-card">
                            <div class="title">Network / Fortinet</div>
                            <div class="value" id="metric-network">0</div>
                            <div class="trend-up"><i class="fas fa-arrow-up"></i> Active Fleet</div>
                        </div>
                        <div class="kpi-card">
                            <div class="title">Scanners / Readers</div>
                            <div class="value" id="metric-scanners">0</div>
                            <div class="trend-up"><i class="fas fa-arrow-up"></i> Active Fleet</div>
                        </div>
                        <div class="kpi-card">
                            <div class="title">CCTV / Traffic</div>
                            <div class="value" id="metric-cctv">0</div>
                            <div class="trend-up"><i class="fas fa-arrow-up"></i> Active Fleet</div>
                        </div>
                    </div>
                </div>

                <div class="portlet">
                    <div class="card-header">
                        <h2><i class="fas fa-chart-area"></i> Dispatch Velocity Trend</h2>
                        <span style="font-size: 11px; color: var(--erp-text-main); font-weight: 600; background: #e2e8f0; padding: 6px 12px; border-radius: 20px;"><i class="fas fa-calendar-alt"></i> Trailing 12 Days</span>
                    </div>
                    <div style="height: 300px; position: relative; padding: 20px;">
                        <canvas id="dispatchTimelineChart"></canvas>
                    </div>
                </div>
            </div>

            <div id="inventoryPanel" class="card-panel admin-panel card-header-wrapper">
                <div class="card-header">
                    <h2><i class="fas fa-boxes"></i> Master Inventory Balance</h2>
                    <button onclick="openExportModal('tbl-inv', -1, 'Master_Inventory')" class="btn-export"><i class="fas fa-file-export"></i> Export</button>
                </div>
                <div style="padding: 24px; border-bottom: 1px solid var(--erp-border); background: #f8fafc; display: flex; gap: 24px;">
                    <div style="flex: 1; background: #fff; padding: 24px; border: 1px solid var(--erp-border); border-radius: var(--radius-sm); text-align: center; box-shadow: var(--shadow-card);">
                        <div style="font-size: 11px; color: var(--erp-text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 8px; letter-spacing: 0.5px;">Total SKU Lines</div>
                        <div style="font-size: 32px; font-weight: 700; color: #0f172a;" id="inv-total-skus">0</div>
                    </div>
                    <div style="flex: 1; background: #fff; padding: 24px; border: 1px solid var(--erp-border); border-radius: var(--radius-sm); text-align: center; box-shadow: var(--shadow-card);">
                        <div style="font-size: 11px; color: var(--erp-text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 8px; letter-spacing: 0.5px;">Total Units on Hand</div>
                        <div style="font-size: 32px; font-weight: 700; color: #0f172a;" id="inv-total-units">0</div>
                    </div>
                    <div style="flex: 1; background: #fffbf1; padding: 24px; border: 1px solid #fde047; border-radius: var(--radius-sm); text-align: center; box-shadow: var(--shadow-card);">
                        <div style="font-size: 11px; color: #b45309; text-transform: uppercase; font-weight: 700; margin-bottom: 8px; letter-spacing: 0.5px;">Low Stock Alerts</div>
                        <div style="font-size: 32px; font-weight: 700; color: #dc2626;" id="inv-low-stock">0</div>
                    </div>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="po-table" id="tbl-inv">
                        <thead><tr>
                            <th>
                                <span class="th-title">SKU</span>
                                <input type="text" class="column-filter" placeholder="Filter..." oninput="filterTable(this)">
                            </th>
                            <th>
                                <span class="th-title">Category</span>
                                <input type="text" class="column-filter" placeholder="Filter..." oninput="filterTable(this)">
                            </th>
                            <th>
                                <span class="th-title">Hardware Type</span>
                                <input type="text" class="column-filter" placeholder="Filter..." oninput="filterTable(this)">
                            </th>
                            <th>
                                <span class="th-title">Item Details</span>
                                <input type="text" class="column-filter" placeholder="Filter..." oninput="filterTable(this)">
                            </th>
                            <th>
                                <span class="th-title">Qty on Hand</span>
                                <input type="text" class="column-filter" placeholder="Filter..." oninput="filterTable(this)">
                            </th>
                            <th>
                                <span class="th-title">Condition</span>
                                <input type="text" class="column-filter" placeholder="Old / New" oninput="filterTable(this)">
                            </th>
                            <th>
                                <span class="th-title">Type Context</span>
                            </th>
                        </tr></thead>
                        <tbody id="inventoryTableBody"></tbody>
                    </table>
                </div>
                <div id="page-tbl-inv" class="pagination-container"></div>
            </div>

            <div id="poPanel" class="card-panel admin-panel card-header-wrapper">
                <div class="card-header">
                    <h2><i class="fas fa-file-invoice-dollar"></i> Purchase Orders</h2>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="downloadTemplate('PO')" class="btn-cancel"><i class="fas fa-file-csv"></i> Template</button>
                        <input type="file" id="poBulkImport" accept=".csv, .xlsx" style="display:none;" onchange="handlePOBulkImport(event)">
                        <button onclick="document.getElementById('poBulkImport').click()" class="btn-cancel"><i class="fas fa-file-import"></i> Import</button>
                        <button onclick="openExportModal('tbl-po', -1, 'PO_Export')" class="btn-export"><i class="fas fa-file-export"></i> Export</button>
                        <input type="file" id="poPdfUpload" accept="application/pdf" style="display:none;" onchange="parsePDFDocument(event, 'PO')">
                        <button onclick="document.getElementById('poPdfUpload').click()" class="btn-create" style="background:#475569; border-color:#475569;"><i class="fas fa-file-pdf"></i> Parse PDF</button>
                        <button onclick="document.getElementById('poModal').classList.add('active'); addNewLineItem();" class="btn-create"><i class="fas fa-plus"></i> Draft PO</button>
                    </div>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="po-table" id="tbl-po">
                        <thead><tr>
                            <th><span class="th-title">PO Number</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                            <th><span class="th-title">Vendor</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                            <th><span class="th-title">Del. Note</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                            <th><span class="th-title">Category / Type</span></th>
                            <th><span class="th-title">Model #</span></th>
                            <th><span class="th-title">Item Details</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                            <th><span class="th-title">Total</span></th>
                            <th><span class="th-title">Status</span></th>
                            <th><span class="th-title">Date</span></th>
                            <th><span class="th-title">Actions</span></th>
                        </tr></thead>
                        <tbody id="poTableBody"></tbody>
                    </table>
                </div>
                <div id="page-tbl-po" class="pagination-container"></div>
            </div>

            <div id="dispatchPanel" class="card-panel admin-panel card-header-wrapper">
                <div class="card-header">
                    <h2><i class="fas fa-truck"></i> Dispatch Allocations</h2>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="openExportModal('tbl-dispatch', 3, 'Dispatch_Log')" class="btn-export"><i class="fas fa-file-export"></i> Export Log</button>
                        <button onclick="openDispatchFlow()" class="btn-create"><i class="fas fa-paper-plane"></i> New Dispatch</button>
                    </div>
                </div>
                <div style="padding: 24px; border-bottom: 1px solid var(--erp-border); background: #f8fafc; display: flex; gap: 24px;">
                    <div style="flex: 1; background: #fff; padding: 24px; border: 1px solid var(--erp-border); border-radius: var(--radius-sm); text-align: center; box-shadow: var(--shadow-card);">
                        <div style="font-size: 11px; color: var(--erp-text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 8px;">Total Manifests Executed</div>
                        <div style="font-size: 32px; font-weight: 700; color: #0f172a;" id="disp-total">0</div>
                    </div>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="po-table" id="tbl-dispatch">
                        <thead><tr>
                            <th><span class="th-title">Dispatch ID</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                            <th><span class="th-title">Target Scope</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                            <th><span class="th-title">Manifest Content</span></th>
                            <th><span class="th-title">Execution Date</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                            <th><span class="th-title">Actions</span></th>
                        </tr></thead>
                        <tbody id="dispatchTableBody"></tbody>
                    </table>
                </div>
                <div id="page-tbl-dispatch" class="pagination-container"></div>
            </div>

            <div id="storeStockPanel" class="card-panel admin-panel card-header-wrapper">
                <div class="card-header"><h2><i class="fas fa-store-alt"></i> Node Stock Status</h2>
                    <button onclick="openExportModal('tbl-store-stock', 3, 'Store_Stock_Matrix')" class="btn-export"><i class="fas fa-file-export"></i> Export</button>
                </div>
                <div style="padding: 24px; border-bottom: 1px solid var(--erp-border); background: #fff;">
                    <div class="form-group" style="max-width: 400px;">
                        <label>Query Target Store Node</label>
                        <input type="text" id="stockStoreInput" list="storeDatalist" onchange="renderStoreStockView()" placeholder="Search store name or ID...">
                    </div>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="po-table" id="tbl-store-stock">
                        <thead><tr>
                            <th><span class="th-title">SKU</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                            <th><span class="th-title">Item Name</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                            <th><span class="th-title">Total Dispatched</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                            <th><span class="th-title">Last Dispatch Date</span></th>
                        </tr></thead>
                        <tbody id="storeStockTableBody"><tr><td colspan="4" style="text-align:center; padding: 40px; color: #64748b;">Select a target node to compile stock matrix.</td></tr></tbody>
                    </table>
                </div>
                <div id="page-tbl-store-stock" class="pagination-container"></div>
            </div>

            <div id="assetRecoveryPanel" class="card-panel admin-panel card-header-wrapper">
                <div class="card-header">
                    <h2><i class="fas fa-sync"></i> Recovery Operations</h2>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="downloadRecoveryTemplate()" class="btn-cancel"><i class="fas fa-file-csv"></i> Download Template</button>
                        <input type="file" id="bulkClosureImport" accept=".csv, .xlsx" style="display:none;" onchange="handleBulkRecoveryImport(event)">
                        <button onclick="document.getElementById('bulkClosureImport').click()" class="btn-create" style="background: var(--danger); border-color: var(--danger);"><i class="fas fa-file-import"></i> Process Recovery File</button>
                        <button onclick="openExportModal('tbl-recovered-ledger', 0, 'Asset_Recovery_Log')" class="btn-export"><i class="fas fa-file-export"></i> Export Data</button>
                    </div>
                </div>
                
                <div class="erp-sub-nav">
                    <button class="sub-tab active" onclick="switchRecTab('recViewOverview', this)">Store Recovery Ledger</button>
                    <button class="sub-tab" onclick="switchRecTab('recViewRecent', this)">Recent Recoveries</button>
                </div>
                
                <div id="recViewOverview" style="display: block;">
                    <div style="padding: 20px; background: #f8fafc; border-bottom: 1px solid var(--erp-border); display: flex; gap: 20px; align-items: flex-end;">
                        <div class="form-group" style="flex: 1; max-width: 400px;">
                            <label>Query Store Node</label>
                            <input type="text" id="recoveryStoreSearch" list="storeDatalist" onchange="renderStoreRecoveryDetails()" placeholder="Select a store to view recovery stats...">
                        </div>
                        <div style="display: flex; gap: 15px;">
                            <div style="background: #fff; padding: 10px 20px; border: 1px solid var(--erp-border); border-radius: var(--radius-sm); text-align: center;">
                                <div style="font-size: 10px; color: var(--erp-text-muted); font-weight: 700; text-transform: uppercase;">Sent to Master (Reuse)</div>
                                <div style="font-size: 20px; font-weight: 700; color: var(--success);" id="stat-reuse">0</div>
                            </div>
                            <div style="background: #fff; padding: 10px 20px; border: 1px solid var(--erp-border); border-radius: var(--radius-sm); text-align: center;">
                                <div style="font-size: 10px; color: var(--erp-text-muted); font-weight: 700; text-transform: uppercase;">Sent to 97427 (Write Off)</div>
                                <div style="font-size: 20px; font-weight: 700; color: var(--danger);" id="stat-writeoff">0</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-scroll-wrapper">
                        <table class="po-table" id="tbl-recovered-ledger">
                            <thead>
                                <tr>
                                    <th><span class="th-title">Hardware Type</span></th>
                                    <th><span class="th-title">Item Name & Model</span></th>
                                    <th><span class="th-title">Serial Number</span></th>
                                    <th><span class="th-title">Qty</span></th>
                                    <th><span class="th-title">Action Taken</span></th>
                                    <th><span class="th-title">Remarks</span></th>
                                </tr>
                            </thead>
                            <tbody id="recoveredLedgerTableBody">
                                <tr><td colspan="6" style="text-align:center; padding: 40px; color: #64748b;">Select a store to view recovered assets.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- RECENT RECOVERIES SUB-TAB -->
                <div id="recViewRecent" style="display: none; padding: 25px;">
                    <h3 style="font-size: 12px; font-weight: 700; color: var(--erp-text-main); margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fas fa-history" style="color: var(--erp-text-muted);"></i> Store Recovery Summaries
                    </h3>
                    <div class="table-scroll-wrapper">
                        <table class="po-table" id="tbl-recent-recoveries">
                            <thead>
                                <tr>
                                    <th><span class="th-title">Store Name</span></th>
                                    <th><span class="th-title">Store Code</span></th>
                                    <th><span class="th-title">Last Recovery Date</span></th>
                                    <th><span class="th-title">Total Reused</span></th>
                                    <th><span class="th-title">Total Written Off</span></th>
                                    <th><span class="th-title">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody id="recentRecoveriesTableBody"></tbody>
                        </table>
                    </div>
                    <div id="page-tbl-recent-recoveries" class="pagination-container"></div>
                </div>

            </div>

            <div id="accountsPanel" class="card-panel admin-panel card-header-wrapper">
                <div class="card-header" style="justify-content: space-between;">
                    <h2><i class="fas fa-users"></i> Directory & Provisioning</h2>
                    <select id="dirViewSelect" class="dir-select" onchange="switchDirView()">
                        <option value="dirViewStores">Retail Nodes (Stores)</option>
                        <option value="dirViewVendors">Approved Vendors</option>
                        <option value="dirViewPersonnel">Internal Personnel</option>
                        <option value="dirViewManagement">Management / Admins</option>
                    </select>
                </div>
                
                <div id="dirViewStores" style="display: block;">
                    <div style="padding: 20px; background: #f8fafc; border-bottom: 1px solid var(--erp-border); display: flex; gap: 12px; flex-wrap: wrap;">
                        <button onclick="document.getElementById('storeModal').classList.add('active')" class="btn-create" style="background:#475569; border-color:#475569;"><i class="fas fa-store"></i> Register Retail Node</button>
                        <input type="file" id="storeBulkCsv" accept=".csv" style="display:none;" onchange="handleBulkStoreImport(event)">
                        <button onclick="document.getElementById('storeBulkCsv').click()" class="btn-cancel"><i class="fas fa-file-import"></i> Bulk Import Nodes</button>
                        <input type="file" id="bulkProvisionImport" accept=".csv, .xlsx" style="display:none;" onchange="handleBulkProvisionImport(event)">
                        <button onclick="document.getElementById('bulkProvisionImport').click()" class="btn-cancel"><i class="fas fa-bolt"></i> Bulk Store Assignation</button>
                    </div>
                    <div class="table-scroll-wrapper">
                        <table class="po-table" id="tbl-stores">
                            <thead><tr>
                                <th><span class="th-title">Store ID</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                                <th><span class="th-title">Store Name</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                                <th><span class="th-title">Brand (Code)</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                                <th><span class="th-title">Mall / Entity</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                                <th><span class="th-title">City & Route</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                            </tr></thead>
                            <tbody id="storeTableBody"></tbody>
                        </table>
                    </div>
                    <div id="page-tbl-stores" class="pagination-container"></div>
                </div>

                <div id="dirViewVendors" style="display: none;">
                    <div style="padding: 20px; background: #f8fafc; border-bottom: 1px solid var(--erp-border);">
                        <button onclick="document.getElementById('vendorModal').classList.add('active')" class="btn-create" style="background:#475569; border-color:#475569;"><i class="fas fa-building"></i> Add Approved Vendor</button>
                    </div>
                    <div class="table-scroll-wrapper">
                        <table class="po-table" id="tbl-vendors">
                            <thead><tr>
                                <th><span class="th-title">Company Name</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                                <th><span class="th-title">Tax ID</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                                <th><span class="th-title">Payment Terms</span></th>
                                <th><span class="th-title">Lead Time</span></th>
                                <th><span class="th-title">Contact</span></th>
                                <th><span class="th-title">Status</span></th>
                                <th><span class="th-title">Actions</span></th>
                            </tr></thead>
                            <tbody id="vendorTableBody"></tbody>
                        </table>
                    </div>
                    <div id="page-tbl-vendors" class="pagination-container"></div>
                </div>

                <div id="dirViewPersonnel" style="display: none;">
                    <div style="padding: 20px; background: #f8fafc; border-bottom: 1px solid var(--erp-border);">
                        <button onclick="document.getElementById('userModal').classList.add('active')" class="btn-create" style="background:#475569; border-color:#475569;"><i class="fas fa-user-plus"></i> Provision Personnel</button>
                    </div>
                    <div class="table-scroll-wrapper">
                        <table class="po-table" id="tbl-users">
                            <thead><tr>
                                <th><span class="th-title">Emp ID</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                                <th><span class="th-title">Employee Name</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                                <th><span class="th-title">Dept / Brand</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                                <th><span class="th-title">Actions</span></th>
                            </tr></thead>
                            <tbody id="userTableBody"></tbody>
                        </table>
                    </div>
                    <div id="page-tbl-users" class="pagination-container"></div>
                </div>

                <div id="dirViewManagement" style="display: none; padding: 50px; text-align: center;">
                    <i class="fas fa-tools" style="font-size: 40px; color: #cbd5e1; margin-bottom: 15px;"></i>
                    <h3 style="color: #475569; font-weight: 600;">Management Settings</h3>
                    <p style="color: #94a3b8; margin-top: 10px;">Security and administrative routing functions are handled via the Main IT Active Directory.</p>
                </div>
            </div>

            <div id="auditPanel" class="card-panel admin-panel card-header-wrapper">
                <div class="card-header">
                    <h2><i class="fas fa-clipboard-list"></i> Accountability Audit</h2>
                    <button onclick="openExportModal('tbl-audit', 0, 'Audit_Logs')" class="btn-export"><i class="fas fa-file-export"></i> Export</button>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="po-table" id="tbl-audit">
                        <thead><tr><th><span class="th-title">Timestamp</span></th><th><span class="th-title">Operator</span></th><th><span class="th-title">Action</span></th><th><span class="th-title">Details</span></th></tr></thead>
                        <tbody id="auditTableBody"></tbody>
                    </table>
                </div>
                <div id="page-tbl-audit" class="pagination-container"></div>
            </div>

            <div id="storeRequestsAdminPanel" class="card-panel admin-panel card-header-wrapper">
                <div class="card-header">
                    <h2><i class="fas fa-hand-paper"></i> Pending Node Requests</h2>
                    <button onclick="openExportModal('tbl-req-pending', 0, 'Pending_Store_Requests')" class="btn-export"><i class="fas fa-file-export"></i> Export</button>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="po-table" id="tbl-req-pending">
                        <thead><tr><th><span class="th-title">Date</span></th><th><span class="th-title">Origin Store</span></th><th><span class="th-title">Requested Items</span></th><th><span class="th-title">Actions</span></th></tr></thead>
                        <tbody id="adminStoreRequestsTableBody"></tbody>
                    </table>
                </div>
                <div id="page-tbl-req-pending" class="pagination-container"></div>

                <div class="card-header" style="border-top: 1px solid var(--erp-border);">
                    <h2><i class="fas fa-check"></i> Processed History</h2>
                    <button onclick="openExportModal('tbl-req-history', 0, 'Processed_Store_Requests')" class="btn-export"><i class="fas fa-file-export"></i> Export</button>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="po-table" id="tbl-req-history">
                        <thead><tr><th><span class="th-title">Date Processed</span></th><th><span class="th-title">Origin Store</span></th><th><span class="th-title">Request Ledger Details</span></th><th><span class="th-title">Status</span></th></tr></thead>
                        <tbody id="adminProcessedRequestsTableBody"></tbody>
                    </table>
                </div>
                <div id="page-tbl-req-history" class="pagination-container"></div>
            </div>

            <div id="itAssetsPanel" class="card-panel admin-panel card-header-wrapper">
                <div class="card-header">
                    <h2><i class="fas fa-desktop"></i> Master Fleet Ledger</h2>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="openExportModal('tbl-it-assets-store', -1, 'IT_Assets_Fleet')" class="btn-export"><i class="fas fa-file-export"></i> Export Data</button>
                        <input type="file" id="itAssetsExcelUpload" accept=".csv, .xlsx" style="display: none;" onchange="handleITAssetsImport(event)">
                        <button onclick="document.getElementById('itAssetsExcelUpload').click()" class="btn-cancel"><i class="fas fa-file-import"></i> Sync Excel</button>
                    </div>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="po-table" id="tbl-it-assets-store">
                        <thead>
                            <tr>
                                <th><span class="th-title">Hostname</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                                <th><span class="th-title">Brand & Store</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                                <th><span class="th-title">Location</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                                <th><span class="th-title">Hardware Type</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                                <th><span class="th-title">Serial Number</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                                <th><span class="th-title">OS & Specs</span><input type="text" class="column-filter" oninput="filterTable(this)" placeholder="Filter..."></th>
                            </tr>
                        </thead>
                        <tbody id="itStoreAssetsTableBody"></tbody>
                    </table>
                </div>
                <div id="page-tbl-it-assets-store" class="pagination-container"></div>
            </div>

        </main>
    </div>
</div>

<!-- ==========================================
     MODALS
     ========================================== -->

<!-- RECOVERY TEMPLATE RULES MODAL -->
<div id="templateRulesModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 500px;">
        <h2 style="color: #0f172a;"><i class="fas fa-exclamation-circle" style="color: #f59e0b;"></i> IMPORT RULES</h2>
        <div style="padding: 20px;">
            <p style="margin-bottom: 15px; font-weight: 500; color: #334155;">Before downloading the template, please note the strict formatting required for successful ingestion.</p>
            <p style="margin-bottom: 10px; font-size: 12px; font-weight: 700; color: var(--erp-text-main); text-transform: uppercase;">Allowed "Hardware Type" Values:</p>
            <div style="background: #f8fafc; padding: 15px; border: 1px solid var(--erp-border); border-radius: var(--radius-sm); font-family: monospace; font-size: 11px; color: #475569; display: grid; grid-template-columns: 1fr 1fr; gap: 5px;">
                <span>• SYSTEM</span><span>• MPOSE</span>
                <span>• MONITER</span><span>• Acces Point</span>
                <span>• CASH DRAWYER</span><span>• A4 Printer</span>
                <span>• BARCODE READER</span><span>• SWITCH</span>
                <span>• EPSON PRINTER</span><span>• Router</span>
                <span>• FORTINET</span><span>• SIMCARD</span>
                <span>• TRAFFIC DEVICE</span><span>• CAMERA</span>
                <span>• BIOMETRIC</span><span>• NVR</span>
            </div>
            <p style="margin-top: 15px; font-size: 12px; font-weight: 700; color: var(--danger); text-transform: uppercase;">Allowed "Action" Values:</p>
            <p style="font-size: 12px; color: #475569; margin-top: 5px;">Must be exactly <strong>Reuse</strong> or <strong>Write Off</strong>.</p>
        </div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('templateRulesModal')">CANCEL</button>
            <button type="button" class="btn-create" onclick="proceedWithTemplateDownload()"><i class="fas fa-download"></i> UNDERSTOOD, DOWNLOAD</button>
        </div>
    </div>
</div>

<!-- POST-IMPORT SUMMARY MODAL -->
<div id="importSummaryModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 450px; text-align: center;">
        <div style="padding: 40px 20px 20px;">
            <i class="fas fa-check-circle" style="font-size: 48px; color: var(--success); margin-bottom: 15px;"></i>
            <h2 style="font-size: 18px; color: #0f172a; margin-bottom: 10px; border:none; padding:0; justify-content:center;">IMPORT SUCCESSFUL</h2>
            <p style="color: #64748b; margin-bottom: 25px;">The recovery manifest has been processed and routed.</p>
            
            <div style="display: flex; gap: 15px; justify-content: center;">
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 15px; border-radius: var(--radius-sm); flex: 1;">
                    <div style="font-size: 10px; color: #166534; font-weight: 700; text-transform: uppercase;">Sent to Master (Old)</div>
                    <div style="font-size: 24px; font-weight: 800; color: #15803d; margin-top: 5px;" id="summaryReuseCount">0</div>
                </div>
                <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 15px; border-radius: var(--radius-sm); flex: 1;">
                    <div style="font-size: 10px; color: #991b1b; font-weight: 700; text-transform: uppercase;">Sent to 97427</div>
                    <div style="font-size: 24px; font-weight: 800; color: #b91c1c; margin-top: 5px;" id="summaryWriteOffCount">0</div>
                </div>
            </div>
        </div>
        <div class="form-actions" style="justify-content: center; background: transparent; border: none; padding-bottom: 30px;">
            <button type="button" class="btn-create" onclick="closeModal('importSummaryModal')" style="width: 100%; justify-content: center; padding: 12px;">CONTINUE TO LEDGER</button>
        </div>
    </div>
</div>

<!-- RECOVERY RECEIPT MODAL -->
<div id="recoveryReceiptModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 900px; padding: 0; background: #fff; border: none;">
        <h2 style="display:none;">Recovery Receipt</h2>
        <div class="receipt-view" id="recoveryReceiptContent" style="background: #ffffff; color: #000000; padding: 50px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; max-height: 70vh; overflow-y: auto;">
            <!-- Content injected via JS -->
        </div>
        <div class="form-actions" style="background: #f8fafc; margin: 0; padding: 15px 25px; border-top: 1px solid var(--erp-border); border-radius: 0 0 var(--radius-md) var(--radius-md);">
            <button type="button" class="btn-cancel" onclick="closeModal('recoveryReceiptModal')">CLOSE</button>
            <button type="button" class="btn-create" onclick="printRecoveryReceipt()"><i class="fas fa-print"></i> PRINT RECEIPTS</button>
        </div>
    </div>
</div>

<div id="exportModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 500px;">
        <h2><i class="fas fa-file-export"></i> EXPORT DATA</h2>
        <form id="exportForm" onsubmit="executeDateExport(event)">
            <input type="hidden" id="exportTableId">
            <input type="hidden" id="exportDateColIdx">
            <input type="hidden" id="exportFileName">
            <p style="margin-bottom: 20px; font-size: 13px; color: var(--erp-text-muted); background: #f8fafc; padding: 15px; border: 1px solid var(--erp-border); border-radius: var(--radius-sm);">
                Select a date range to filter your export. <br>
                <i>Leave dates blank to instantly export all current records.</i>
            </p>
            <div class="form-grid">
                <div class="form-group"><label>Start Date</label><input type="date" id="exportStartDate"></div>
                <div class="form-group"><label>End Date</label><input type="date" id="exportEndDate"></div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('exportModal')">CANCEL</button>
                <button type="submit" class="btn-export">DOWNLOAD CSV</button>
            </div>
        </form>
    </div>
</div>

<div id="receivePoModal" class="modal-overlay">
    <div class="modal-container">
        <h2><i class="fas fa-box-open"></i> RECEIVE PO ITEMS</h2>
        <div>
            <h4 id="recvPoTitle" style="color: var(--erp-text-muted); margin-bottom: 20px; font-size: 14px; text-transform: uppercase;">PO-XXXX</h4>
            <form id="receivePoForm" onsubmit="submitPOReceipt(event)">
                <input type="hidden" id="recvPoId">
                <div class="table-scroll-wrapper" style="border: 1px solid var(--erp-border); margin-bottom: 20px; border-radius: var(--radius-sm);">
                    <table class="po-table">
                        <thead><tr><th><span class="th-title">SKU</span></th><th><span class="th-title">Item Name</span></th><th><span class="th-title">Ordered</span></th><th><span class="th-title">Previously Received</span></th><th><span class="th-title">Receiving Now</span></th><th><span class="th-title">Expected Status</span></th></tr></thead>
                        <tbody id="receivePoTableBody"></tbody>
                    </table>
                </div>
                <div class="form-actions" style="margin:0; padding-bottom:0; border-top:none; background:transparent;">
                    <button type="button" class="btn-cancel" onclick="closeModal('receivePoModal')">CANCEL</button>
                    <button type="submit" class="btn-create">SUBMIT RECEIPT</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="editPoModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 700px;">
        <h2><i class="fas fa-pen"></i> EDIT PO & QUANTITIES</h2>
        <form id="editPoForm" onsubmit="submitEditPO(event)">
            <input type="hidden" id="editPoId">
            <div class="form-grid">
                <div class="form-group" style="margin-bottom: 15px;"><label>Official PO Number</label><input type="text" id="editPoNumber"></div>
                <div class="form-group" style="margin-bottom: 15px;"><label>Delivery Note Number</label><input type="text" id="editPoDeliveryNote"></div>
                <div class="form-group" style="margin-bottom: 20px;"><label>Invoice Number</label><input type="text" id="editPoInvoice"></div>
            </div>
            
            <h3 style="font-size: 12px; margin-bottom: 10px; color: var(--erp-text-muted); font-weight: 700;">UPDATE LINE ITEMS</h3>
            <div class="table-scroll-wrapper" style="max-height: 250px; overflow-y: auto; border: 1px solid var(--erp-border); margin-bottom: 20px;">
                <table class="po-table">
                    <thead><tr><th>SKU</th><th>Item Name</th><th>Order Qty</th><th>Unit Price</th></tr></thead>
                    <tbody id="editPoLinesBody"></tbody>
                </table>
            </div>

            <div class="form-actions" style="margin:0; border-top:none;">
                <button type="button" class="btn-cancel" onclick="closeModal('editPoModal')">CANCEL</button>
                <button type="submit" class="btn-create">SAVE CHANGES</button>
            </div>
        </form>
    </div>
</div>

<div id="dispatchFlowModal" class="modal-overlay">
    <div class="modal-container">
        <h2><i class="fas fa-truck-ramp-box"></i> DISPATCH OPERATIONS</h2>
        <div>
            <div style="margin-bottom: 25px; display: flex; gap: 15px; align-items: center; border-bottom: 1px solid var(--erp-border); padding-bottom: 15px; background: #f8fafc; padding: 15px; border-radius: var(--radius-sm);">
                <div id="dotStep1" style="font-weight: 700; color: var(--erp-text-blue); font-size: 13px;">1. ASSIGNEE</div>
                <i class="fas fa-chevron-right" style="color: #94a3b8; font-size: 11px;"></i>
                <div id="dotStep2" style="color: #94a3b8; font-weight: 600; font-size: 13px;">2. MATRIX</div>
            </div>
            
            <div id="dispatchStep1">
                <h4 style="margin-bottom: 15px; font-weight: 700; text-transform: uppercase; color: #334155;">Select Delivery Target</h4>
                <div style="display: flex; gap: 20px; margin-bottom: 25px; padding: 15px; border: 1px solid var(--erp-border); background: #fff; border-radius: var(--radius-sm);">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                        <input type="radio" name="assigneeType" value="store" checked onchange="toggleAssigneeType()" style="width: auto;"> RETAIL NODE
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                        <input type="radio" name="assigneeType" value="person" onchange="toggleAssigneeType()" style="width: auto;"> INTERNAL PERSONNEL
                    </label>
                </div>

                <div id="storeFields">
                    <div class="form-grid">
                        <div class="form-group"><label>1. Select Brand Context *</label><select id="dfBrandSelect" onchange="filterStoresByBrand()"><option value="">-- Choose Brand --</option></select></div>
                        <div class="form-group"><label>2. Select Target Node (Store) *</label><select id="dfStoreSelect" onchange="autofillStoreMeta()" disabled><option value="">-- Awaiting Brand --</option></select></div>
                        <input type="hidden" id="dfStoreId">
                        <div class="form-group"><label>Mall / Entity Context</label><input type="text" id="dfStoreEntity" readonly style="background: #f1f5f9;"></div>
                        <div class="form-group"><label>Route Code</label><input type="text" id="dfStoreRoute" readonly style="background: #f1f5f9;"></div>
                    </div>
                </div>

                <div id="personFields" style="display: none;">
                    <div class="form-grid">
                        <div class="form-group"><label>Select Personnel</label><select id="dfPersonSelect" onchange="autofillPersonDetails()"><option value="">-- Choose Personnel --</option></select></div>
                        <div class="form-group"><label>Emp ID</label><input type="text" id="dfPersonId" readonly style="background: #f1f5f9;"></div>
                        <div class="form-group"><label>Emp Name</label><input type="text" id="dfPersonName" readonly style="background: #f1f5f9;"></div>
                        <div class="form-group"><label>Dept / Brand</label><input type="text" id="dfPersonDept" readonly style="background: #f1f5f9;"></div>
                    </div>
                </div>
                <div class="form-actions" style="margin:0; padding-bottom:0; border-top:none; background:transparent;"><button type="button" class="btn-cancel" onclick="closeModal('dispatchFlowModal')">CANCEL</button><button type="button" class="btn-create" onclick="goToDispatchStep2()">NEXT STEP <i class="fas fa-arrow-right"></i></button></div>
            </div>

            <div id="dispatchStep2" style="display: none;">
                <div style="display: flex; gap: 10px; margin-bottom: 15px; align-items: center; border: 1px solid #cbd5e1; border-radius: var(--radius-sm); padding: 8px 12px; background: #fff; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                    <i class="fas fa-search" style="color: #94a3b8;"></i>
                    <input type="text" id="dispatchSearch" placeholder="Search Master Inventory (SKU, Name)..." oninput="searchDispatchItems()" style="flex: 1; border: none; outline: none; box-shadow: none; padding: 0;">
                </div>
                <div style="max-height: 200px; overflow-y: auto; margin-bottom: 25px; border: 1px solid var(--erp-border); border-radius: var(--radius-sm);" id="dispatchSearchResults"></div>
                
                <h4 style="margin-bottom: 10px; font-weight: 700; text-transform: uppercase; color: var(--erp-text-main);"><i class="fas fa-clipboard-list" style="color: var(--erp-text-muted);"></i> MANIFEST HANDOVER STAGING</h4>
                <div id="dispatchCartContainer" style="min-height: 100px;"></div>
                
                <div class="form-actions" style="margin:0; padding-bottom:0; border-top:none; background:transparent;"><button type="button" class="btn-cancel" onclick="goToDispatchStep1()"><i class="fas fa-arrow-left"></i> BACK</button><button type="button" class="btn-create" onclick="submitDispatch()"><i class="fas fa-check"></i> EXECUTE HANDSHAKE</button></div>
            </div>
        </div>
    </div>
</div>

<div id="dispatchDetailsModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 850px; padding: 0; background: #fff; border: none;">
        <h2 style="display:none;">Receipt</h2>
        <div class="receipt-view" id="receiptContent" style="background: #ffffff; color: #000000; padding: 50px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px;">
                <div>
                    <div style="background: #0f172a; color: #fff; padding: 10px 18px; font-size: 20px; font-weight: bold; letter-spacing: 2px; display: inline-block; margin-bottom: 12px; border-radius: 2px;">
                        APPAREL <span style="font-weight: 300;">GROUP</span>
                    </div>
                    <div style="font-size: 11px; line-height: 1.6; color: #334155;">
                        <strong>Central Logistics Node</strong><br>
                        Apparel Group IT Procurement<br>
                        Jebel Ali Free Zone, Dubai, UAE<br>
                        support@apparelgroup.com<br>
                        Phone +971 (4) 555 0100
                    </div>
                </div>
                <div style="text-align: right;">
                    <h1 style="color: #0f172a; font-size: 24px; font-weight: 800; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">Dispatch Manifest</h1>
                    <table style="margin-left: auto; font-size: 11px; text-align: left;">
                        <tr><td style="padding: 4px 12px 4px 0; font-weight: bold; color:#475569;">Manifest #:</td><td id="detailDispatchId" style="padding: 4px 0; font-weight: 600;"></td></tr>
                        <tr><td style="padding: 4px 12px 4px 0; font-weight: bold; color:#475569;">Execution Date:</td><td id="detailDate" style="padding: 4px 0;"></td></tr>
                    </table>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 40px; font-size: 12px; line-height: 1.6;">
                <div style="width: 45%;">
                    <h3 style="font-size: 13px; font-weight: bold; color: #0f172a; margin-bottom: 6px; border-bottom: 2px solid #0f172a; padding-bottom: 4px;">Dispatched From</h3>
                    Central IT Warehouse<br>Main Storage Facility<br>Internal Logistics Team
                </div>
                <div style="width: 45%;">
                    <h3 style="font-size: 13px; font-weight: bold; color: #0f172a; margin-bottom: 6px; border-bottom: 2px solid #0f172a; padding-bottom: 4px;">Dispatched To / Recipient</h3>
                    <span id="detailRecipient" style="color: #0f172a; font-weight: bold;">[Loading Recipient...]</span>
                </div>
            </div>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 40px; font-size: 12px; table-layout: auto;">
                <thead>
                    <tr style="background: #f1f5f9; border-top: 2px solid #0f172a; border-bottom: 2px solid #0f172a;">
                        <th style="font-weight: bold; text-align: left; padding: 12px 10px; width: 5%; color:#0f172a;">#</th>
                        <th style="font-weight: bold; text-align: left; padding: 12px 10px; width: 40%; color:#0f172a;">SKU Code</th>
                        <th style="font-weight: bold; text-align: left; padding: 12px 10px; width: 45%; color:#0f172a;">Description / Item Name</th>
                        <th style="font-weight: bold; text-align: center; padding: 12px 10px; width: 10%; color:#0f172a;">Qty</th>
                    </tr>
                </thead>
                <tbody id="dispatchDetailsTableBody" style="overflow-wrap: anywhere;"></tbody>
            </table>

            <div style="margin-top: 60px; display: flex; justify-content: space-between; font-size: 12px;">
                <div style="width: 40%; text-align: center; border-top: 1px solid #0f172a; padding-top: 8px; font-weight: bold; color: #334155;">Authorized Dispatcher Signature</div>
                <div style="width: 40%; text-align: center; border-top: 1px solid #0f172a; padding-top: 8px; font-weight: bold; color: #334155;">Receiver Signature / Store Stamp</div>
            </div>
        </div>
        <div class="form-actions" style="background: #f8fafc; margin: 0; padding: 15px 25px; border-top: 1px solid var(--erp-border); border-radius: 0 0 var(--radius-md) var(--radius-md);">
            <button type="button" class="btn-cancel" onclick="closeModal('dispatchDetailsModal')">CLOSE</button>
            <button type="button" class="btn-create" id="printReceiptBtn"><i class="fas fa-print"></i> PRINT MANIFEST</button>
        </div>
    </div>
</div>

<div id="storeModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 600px;">
        <h2><i class="fas fa-store"></i> REGISTER RETAIL NODE</h2>
       <form id="storeForm" onsubmit="saveStore(event)">
    <div class="form-grid">
        <div class="form-group" style="grid-column: span 2;">
            <label>Store Corporate Name *</label>
            <input type="text" id="sName" placeholder="e.g. ADIDAS SOLITAIRE" required>
        </div>
        <div class="form-group">
            <label>Store Code / ID *</label>
            <input type="text" id="sCode" placeholder="e.g. 46858" required>
        </div>
        <div class="form-group"><label>Brand Umbrella *</label><input type="text" id="sBrand" required></div>
        <div class="form-group"><label>Brand Code</label><input type="text" id="sBrandCode"></div>
        <div class="form-group"><label>Mall Complex</label><input type="text" id="sMall"></div>
        <div class="form-group"><label>Entity Scope *</label><input type="text" id="sEntity" required></div>
        <div class="form-group"><label>City Location</label><input type="text" id="sCity"></div>
        <div class="form-group"><label>Country</label><input type="text" id="sCountry" value="Saudi Arabia"></div>
        <div class="form-group" style="grid-column: span 2;"><label>Full Address</label><input type="text" id="sAddress" placeholder="123 Retail St, Unit 4"></div>
        <div class="form-group"><label>Region ID</label><input type="text" id="sRegionId"></div>
        <div class="form-group"><label>Store Email</label><input type="email" id="sEmail"></div>
        <div class="form-group"><label>Route Mapping</label><input type="text" id="sRoute"></div>
    </div>
    <div class="form-actions">
        <button type="button" class="btn-cancel" onclick="closeModal('storeModal')">CANCEL</button>
        <button type="submit" class="btn-create">COMMIT STORE</button>
    </div>
</form>
    </div>
</div>

<div id="preflightVendorModal" class="modal-overlay">
    <div class="modal-container">
        <h2><i class="fas fa-exclamation-triangle"></i> UNREGISTERED VENDORS</h2>
        <div>
            <p style="margin-bottom: 20px; font-size: 13px; font-weight: 500; padding: 15px; border-left: 4px solid var(--danger); background: #fef2f2; color: #991b1b; border-radius: 0 var(--radius-sm) var(--radius-sm) 0;">The imported matrix contains vendor strings that are not mapped in the registry. Establish records for them below before ingestion.</p>
            <div id="preflightContainer" style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 20px; max-height: 350px; overflow-y: auto;"></div>
            <div class="form-actions" style="margin:0; padding-bottom:0; border-top:none; background:transparent;"><button type="button" class="btn-cancel" onclick="closeModal('preflightVendorModal')">ABORT</button><button type="button" class="btn-create" onclick="commitPreflightVendors()" style="background: var(--danger); border-color: var(--danger);"><i class="fas fa-link"></i> MAP & PROCEED</button></div>
        </div>
    </div>
</div>

<div id="vendorModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 500px;">
        <h2><i class="fas fa-building"></i> MAP VENDOR</h2>
        <form id="vendorForm" onsubmit="saveVendor(event)">
            <div class="form-group" style="margin-bottom: 15px;"><label>Company Name *</label><input type="text" id="vCompany" required></div>
            <div class="form-group" style="margin-bottom: 15px;"><label>Tax ID / VAT Registration</label><input type="text" id="vTax"></div>
            <div class="form-group" style="margin-bottom: 15px;"><label>Payment Terms</label><input type="text" id="vTerms" placeholder="e.g. Net 30"></div>
            <div class="form-group" style="margin-bottom: 15px;"><label>Lead Time</label><input type="text" id="vLead" placeholder="e.g. 5 Days"></div>
            <div class="form-group" style="margin-bottom: 25px;"><label>Contact Email</label><input type="email" id="vEmail"></div>
            <div class="form-actions"><button type="button" class="btn-cancel" onclick="closeModal('vendorModal')">CANCEL</button><button type="submit" class="btn-create">SAVE VENDOR</button></div>
        </form>
    </div>
</div>

<div id="userModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 500px;">
        <h2><i class="fas fa-user-plus"></i> PROVISION PERSONNEL</h2>
        <form id="userForm" onsubmit="saveUser(event)">
            <div class="form-group" style="margin-bottom: 15px;"><label>Employee ID *</label><input type="text" id="uId" required></div>
            <div class="form-group" style="margin-bottom: 15px;"><label>Employee Name *</label><input type="text" id="uName" required></div>
            <div class="form-group" style="margin-bottom: 25px;"><label>Department / Brand Context</label><input type="text" id="uDept"></div>
            <div class="form-actions"><button type="button" class="btn-cancel" onclick="closeModal('userModal')">CANCEL</button><button type="submit" class="btn-create">PROVISION</button></div>
        </form>
    </div>
</div>

<div id="poModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 1000px;">
        <h2><i class="fas fa-file-invoice"></i> DRAFT PURCHASE ORDER</h2>
        <form id="poForm" onsubmit="savePO(event)">
            <div class="form-grid" style="grid-template-columns: repeat(3, 1fr);">
                <div class="form-group"><label>PO Number</label><input type="text" id="poNumber" placeholder="Leave blank to auto-generate" style="background: #f1f5f9; font-weight: 600;"></div>
                <div class="form-group"><label>Vendor Name *</label><select id="vendorSelect"><option value="Generic">Generic Vendor</option></select></div>
                <div class="form-group"><label>Delivery Note NO *</label><input type="text" id="poDeliveryNote" required></div>
                <div class="form-group"><label>Invoice Number</label><input type="text" id="poInvoice"></div>
                <div class="form-group"><label>Assigned Store (Node)</label><input type="text" id="poAssignedStore" list="storeDatalist" placeholder="Leave blank for Warehouse"></div>
                <div class="form-group"><label>Assigned Brand</label><input type="text" id="poAssignedBrand"></div>
            </div>
            <div style="margin-top: 25px; border-top: 1px solid var(--erp-border); padding-top: 20px;">
                <h3 style="font-size: 13px; color: var(--erp-text-main); margin-bottom: 15px; font-weight: 700; text-transform: uppercase;"><i class="fas fa-list" style="color: var(--erp-text-muted);"></i> LINE ITEMS</h3>
                <div id="lineItemsContainer"></div>
                <button type="button" onclick="addNewLineItem()" class="btn-cancel" style="border: 1px dashed var(--erp-text-blue); color: var(--erp-text-blue); width: 100%; justify-content: center; padding: 12px; margin-top: 10px; background: #eff6ff;"><i class="fas fa-plus"></i> ADD LINE ITEM</button>
            </div>
            <div class="form-grid" style="margin-top: 25px; align-items: end; background: #f8fafc; padding: 20px; border: 1px solid var(--erp-border); border-radius: var(--radius-sm);">
                <div class="form-group"><label>Subtotal (SAR)</label><input type="text" id="subtotal" readonly style="background: transparent; border: none; font-weight: 700; font-size: 16px; padding: 0; box-shadow: none;"></div>
                <div class="form-group"><label>Tax Amount</label><input type="number" id="taxAmount" value="0" step="0.01" oninput="recalcTotals()" style="background: #fff;"></div>
                <div class="form-group" style="grid-column: span 2;"><label style="color: var(--success);">GRAND TOTAL (SAR)</label><input type="text" id="grandTotal" readonly style="background: transparent; border: none; font-weight:800; color: var(--success); font-size: 22px; padding: 0; box-shadow: none;"></div>
            </div>
            <div class="form-actions"><button type="button" class="btn-cancel" onclick="closeModal('poModal')">CANCEL</button><button type="submit" class="btn-create">COMMIT TO LEDGER</button></div>
        </form>
    </div>
</div>

<div id="pdfReviewModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 95vw; width: 1200px;">
        <h2><i class="fas fa-magic"></i> AI PDF EXTRACTION REVIEW</h2>
        <div>
            <p style="color: var(--erp-text-muted); margin-bottom: 20px; font-size: 13px; font-weight: 500;">Verify the extracted fields before mapping them into the PO Ledger.</p>
            <form id="pdfReviewForm" onsubmit="commitParsedPDF(event)">
                <div class="form-grid" style="background: #f8fafc; padding: 20px; border: 1px solid var(--erp-border); border-radius: var(--radius-sm); margin-bottom: 25px; grid-template-columns: repeat(5, 1fr);">
                    <div class="form-group"><label>PO Number</label><input type="text" id="pdfPoNumber" required></div>
                    <div class="form-group"><label>Vendor Name</label><input type="text" id="pdfVendor"></div>
                    <div class="form-group"><label>Delivery Note NO</label><input type="text" id="pdfDelNote"></div>
                    <div class="form-group"><label>Invoice Number</label><input type="text" id="pdfInvoice"></div>
                    <div class="form-group"><label>Assigned Store</label><input type="text" id="pdfTarget"></div>
                    <div class="form-group"><label>Assigned Brand</label><input type="text" id="pdfBrand"></div>
                    <div class="form-group" style="grid-column: span 4;"><label>Raw PDF Date (Reference)</label><input type="date" id="pdfDate" readonly style="background: #f1f5f9;"></div>
                </div>
                
                <h3 style="font-size: 13px; font-weight: 700; margin-bottom: 12px; color: var(--erp-text-main); text-transform: uppercase;">EXTRACTED LINE ITEMS</h3>
                <div class="table-scroll-wrapper" style="border: 1px solid var(--erp-border); max-height: 300px; overflow-y: auto; border-radius: var(--radius-sm);">
                    <table class="po-table">
                        <thead>
                            <tr>
                                <th><span class="th-title">Category</span></th>
                                <th><span class="th-title">Hardware Type</span></th>
                                <th><span class="th-title">Item Name</span></th>
                                <th><span class="th-title">Item Description</span></th>
                                <th><span class="th-title">Model No.</span></th>
                                <th><span class="th-title">Serial No.</span></th>
                                <th><span class="th-title">Order Qty</span></th>
                                <th><span class="th-title">Price</span></th>
                            </tr>
                        </thead>
                        <tbody id="pdfParsedItemsBody"></tbody>
                    </table>
                </div>
                
                <div class="form-actions" style="margin:0; padding-top:20px; border-top:none; background:transparent;">
                    <button type="button" class="btn-cancel" onclick="closeModal('pdfReviewModal')">DISCARD</button>
                    <button type="submit" class="btn-create" style="background: var(--erp-text-main); border-color: var(--erp-text-main);"><i class="fas fa-check"></i> CONFIRM & MAP TO DRAFT</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="poTrackerModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 1100px;">
        <div class="modal-header">
            <span><i class="fas fa-route"></i> PO LIFECYCLE TRACKER</span>
            <button class="btn-export" id="btnExportTracker" style="padding: 4px 10px; color: #b45309; background: #fdfaf0; font-size: 11px;"><i class="fas fa-file-export"></i> EXPORT LOG</button>
        </div>
        <div class="modal-body">
            <h4 id="trackerPoTitle" style="color: var(--erp-text-muted); margin-bottom: 20px; font-weight: 700; font-size: 14px; text-transform: uppercase;">TRACKING: PO-XXXX</h4>
            <div class="table-scroll-wrapper" style="border: 1px solid var(--erp-border); border-radius: var(--radius-sm);">
                <table class="po-table" id="tbl-po-tracker">
                    <thead><tr><th><span class="th-title">TYPE / CATEGORY</span></th><th><span class="th-title">ITEM NAME & DESC</span></th><th><span class="th-title">SERIAL / SKU</span></th><th><span class="th-title">QTY</span></th><th><span class="th-title">ASSIGNMENT STATUS & DATE</span></th></tr></thead>
                    <tbody id="poTrackerTableBody"></tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModal('poTrackerModal')">CLOSE TRACKER</button>
        </div>
    </div>
</div>

<div id="toastMessage" class="toast-msg"></div>

<script>
    // System Variables Init
    const iamRole = "<?php echo $primary_role; ?>";
    const iamUser = "<?php echo $username; ?>";
    const iamName = "<?php echo $fullname; ?>";

    localStorage.setItem('asset_user', iamUser);
    localStorage.setItem('asset_role', iamRole);
    localStorage.setItem('asset_route', 'Central'); 
</script>
<script src="app.js"></script>
</body>
</html>