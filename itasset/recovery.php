<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Recovery - Apparel ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
</head>
<body>

<div class="app-wrapper">
    <!-- Top Bar -->
    <div class="ns-top-bar">
        <div class="ns-logo"><i class="fas fa-sync"></i> ASSET RECOVERY</div>
        <div class="ns-user-menu">
            <span><i class="fas fa-user-circle"></i> <?php echo $fullname; ?></span>
            <button onclick="handleLogout()" class="btn-logout"><i class="fas fa-sign-out-alt"></i></button>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="ns-main-nav">
        <div class="nav-item" onclick="window.location.href='dashboard.php'"><i class="fas fa-home"></i> Dashboard</div>
        <div class="nav-item" onclick="window.location.href='po-dispatch.php'"><i class="fas fa-file-invoice-dollar"></i> PO & Dispatch</div>
        <div class="nav-item active"><i class="fas fa-sync"></i> Recovery</div>
    </nav>

    <div class="ns-workspace">
        <main class="ns-content">
            <!-- Recovery Stats -->
            <div class="card-header-wrapper">
                <div class="card-header">
                    <h2><i class="fas fa-sync"></i> Recovery Operations</h2>
                    <div style="display:flex; gap:10px;">
                        <button onclick="downloadRecoveryTemplate()" class="btn-cancel">
                            <i class="fas fa-file-csv"></i> Template
                        </button>
                        <input type="file" id="bulkRecoveryImport" accept=".csv,.xlsx" style="display:none;" onchange="handleBulkRecoveryImport(event)">
                        <button onclick="document.getElementById('bulkRecoveryImport').click()" class="btn-create" style="background:var(--danger); border-color:var(--danger);">
                            <i class="fas fa-file-import"></i> Process Recovery
                        </button>
                    </div>
                </div>
            </div>

            <!-- Store Selection -->
            <div style="padding:20px; background:#f8fafc; border:1px solid var(--erp-border); margin-bottom:20px; border-radius:6px;">
                <div class="form-group" style="max-width:400px;">
                    <label>Select Store Node</label>
                    <input type="text" id="recoveryStoreSearch" list="storeDatalist" onchange="renderStoreRecoveryDetails()" placeholder="Search store...">
                    <datalist id="storeDatalist"></datalist>
                </div>
                <div style="display:flex; gap:15px; margin-top:15px;">
                    <div style="background:#fff; padding:10px 20px; border:1px solid var(--erp-border); border-radius:6px; text-align:center;">
                        <div style="font-size:10px; color:var(--erp-text-muted); font-weight:700; text-transform:uppercase;">Reuse (Restock)</div>
                        <div style="font-size:20px; font-weight:700; color:var(--success);" id="stat-reuse">0</div>
                    </div>
                    <div style="background:#fff; padding:10px 20px; border:1px solid var(--erp-border); border-radius:6px; text-align:center;">
                        <div style="font-size:10px; color:var(--erp-text-muted); font-weight:700; text-transform:uppercase;">Write Off (E-Waste)</div>
                        <div style="font-size:20px; font-weight:700; color:var(--danger);" id="stat-writeoff">0</div>
                    </div>
                </div>
            </div>

            <!-- Recovery Ledger -->
            <div class="card-header-wrapper">
                <div class="card-header">
                    <h2><i class="fas fa-clipboard-list"></i> Recovery Ledger</h2>
                    <button onclick="openExportModal('tbl-recovery', 0, 'Recovery_Ledger')" class="btn-export">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="po-table" id="tbl-recovery">
                        <thead><tr>
                            <th>Hardware Type</th>
                            <th>Item & Model</th>
                            <th>Serial Number</th>
                            <th>Qty</th>
                            <th>Action</th>
                            <th>Remarks</th>
                        </tr></thead>
                        <tbody id="recoveryLedgerTableBody">
                            <tr><td colspan="6" style="text-align:center; padding:40px; color:#64748b;">Select a store to view recovered assets.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="page-tbl-recovery" class="pagination-container"></div>
            </div>

            <!-- Recent Recovery Summaries -->
            <div class="card-header-wrapper" style="margin-top:20px;">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Recent Recovery Summaries</h2>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="po-table" id="tbl-recent-recoveries">
                        <thead><tr>
                            <th>Store Name</th>
                            <th>Store Code</th>
                            <th>Last Recovery</th>
                            <th>Reused</th>
                            <th>Written Off</th>
                            <th>Actions</th>
                        </tr></thead>
                        <tbody id="recentRecoveriesTableBody"></tbody>
                    </table>
                </div>
                <div id="page-tbl-recent-recoveries" class="pagination-container"></div>
            </div>
        </main>
    </div>
</div>

<!-- Template Rules Modal -->
<div id="templateRulesModal" class="modal-overlay">
    <div class="modal-container" style="max-width:500px;">
        <h2><i class="fas fa-exclamation-circle" style="color:#f59e0b;"></i> IMPORT RULES</h2>
        <div style="padding:20px;">
            <p style="margin-bottom:15px; font-weight:500;">Allowed Hardware Types:</p>
            <div style="background:#f8fafc; padding:15px; border:1px solid var(--erp-border); border-radius:6px; font-family:monospace; font-size:11px; display:grid; grid-template-columns:1fr 1fr; gap:5px;">
                <span>• SYSTEM</span><span>• MPOSE</span>
                <span>• MONITER</span><span>• Access Point</span>
                <span>• CASH DRAWYER</span><span>• A4 Printer</span>
                <span>• BARCODE READER</span><span>• SWITCH</span>
                <span>• EPSON PRINTER</span><span>• Router</span>
                <span>• FORTINET</span><span>• SIMCARD</span>
                <span>• TRAFFIC DEVICE</span><span>• CAMERA</span>
                <span>• BIOMETRIC</span><span>• NVR</span>
            </div>
            <p style="margin-top:15px; font-weight:700; color:var(--danger);">Allowed Actions: Reuse or Write Off</p>
        </div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('templateRulesModal')">CANCEL</button>
            <button type="button" class="btn-create" onclick="proceedWithTemplateDownload()">
                <i class="fas fa-download"></i> DOWNLOAD TEMPLATE
            </button>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div id="exportModal" class="modal-overlay">
    <div class="modal-container" style="max-width:500px;">
        <h2><i class="fas fa-file-export"></i> EXPORT DATA</h2>
        <form id="exportForm" onsubmit="executeExport(event)">
            <input type="hidden" id="exportTableId">
            <input type="hidden" id="exportFileName">
            <div class="form-grid">
                <div class="form-group"><label>Start Date</label><input type="date" id="exportStartDate"></div>
                <div class="form-group"><label>End Date</label><input type="date" id="exportEndDate"></div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('exportModal')">CANCEL</button>
                <button type="submit" class="btn-export">DOWNLOAD</button>
            </div>
        </form>
    </div>
</div>

<!-- Import Summary Modal -->
<div id="importSummaryModal" class="modal-overlay">
    <div class="modal-container" style="max-width:450px; text-align:center;">
        <div style="padding:40px 20px 20px;">
            <i class="fas fa-check-circle" style="font-size:48px; color:var(--success); margin-bottom:15px;"></i>
            <h2 style="font-size:18px; color:#0f172a; margin-bottom:10px;">IMPORT SUCCESSFUL</h2>
            <div style="display:flex; gap:15px; justify-content:center; margin-top:20px;">
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:15px; border-radius:6px; flex:1;">
                    <div style="font-size:10px; color:#166534; font-weight:700; text-transform:uppercase;">Reused</div>
                    <div style="font-size:24px; font-weight:800; color:#15803d;" id="summaryReuseCount">0</div>
                </div>
                <div style="background:#fef2f2; border:1px solid #fecaca; padding:15px; border-radius:6px; flex:1;">
                    <div style="font-size:10px; color:#991b1b; font-weight:700; text-transform:uppercase;">Written Off</div>
                    <div style="font-size:24px; font-weight:800; color:#b91c1c;" id="summaryWriteOffCount">0</div>
                </div>
            </div>
        </div>
        <div class="form-actions" style="justify-content:center; background:transparent; border:none; padding-bottom:30px;">
            <button type="button" class="btn-create" onclick="closeModal('importSummaryModal')" style="width:100%; justify-content:center; padding:12px;">CONTINUE</button>
        </div>
    </div>
</div>

<div id="toastMessage" class="toast-msg"></div>

<script src="assets/js/recovery.js"></script>
</body>
</html>