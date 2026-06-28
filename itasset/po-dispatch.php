<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PO & Dispatch - Apparel ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
</head>
<body>

<div class="app-wrapper">
    <!-- Top Bar -->
    <div class="ns-top-bar">
        <div class="ns-logo"><i class="fas fa-file-invoice-dollar"></i> PO & DISPATCH</div>
        <div class="ns-user-menu">
            <span><i class="fas fa-user-circle"></i> <?php echo $fullname; ?></span>
            <button onclick="handleLogout()" class="btn-logout"><i class="fas fa-sign-out-alt"></i></button>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="ns-main-nav">
        <div class="nav-item" onclick="window.location.href='dashboard.php'"><i class="fas fa-home"></i> Dashboard</div>
        <div class="nav-item active"><i class="fas fa-file-invoice-dollar"></i> PO & Dispatch</div>
        <div class="nav-item" onclick="window.location.href='recovery.php'"><i class="fas fa-sync"></i> Recovery</div>
    </nav>

    <div class="ns-workspace">
        <main class="ns-content">
            <!-- PO Section -->
            <div class="card-header-wrapper">
                <div class="card-header">
                    <h2><i class="fas fa-file-invoice-dollar"></i> Purchase Orders</h2>
                    <div style="display:flex; gap:10px;">
                        <button onclick="downloadPOTemplate()" class="btn-cancel"><i class="fas fa-file-csv"></i> Template</button>
                        <input type="file" id="poBulkImport" accept=".csv,.xlsx" style="display:none;" onchange="handlePOBulkImport(event)">
                        <button onclick="document.getElementById('poBulkImport').click()" class="btn-cancel"><i class="fas fa-file-import"></i> Import</button>
                        <input type="file" id="poPdfUpload" accept=".pdf" style="display:none;" onchange="parsePDFDocument(event)">
                        <button onclick="document.getElementById('poPdfUpload').click()" class="btn-create" style="background:#475569; border-color:#475569;">
                            <i class="fas fa-file-pdf"></i> Parse PDF
                        </button>
                        <button onclick="openPOModal()" class="btn-create"><i class="fas fa-plus"></i> New PO</button>
                    </div>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="po-table" id="tbl-po">
                        <thead><tr>
                            <th>PO Number</th><th>Vendor</th><th>Delivery Note</th>
                            <th>Category</th><th>Model</th><th>Item</th>
                            <th>Total</th><th>Status</th><th>Date</th><th>Actions</th>
                        </tr></thead>
                        <tbody id="poTableBody"></tbody>
                    </table>
                </div>
                <div id="page-tbl-po" class="pagination-container"></div>
            </div>

            <!-- Dispatch Section -->
            <div class="card-header-wrapper" style="margin-top:20px;">
                <div class="card-header">
                    <h2><i class="fas fa-truck"></i> Dispatch Allocations</h2>
                    <div style="display:flex; gap:10px;">
                        <button onclick="openExportModal('tbl-dispatch', 3, 'Dispatch_Log')" class="btn-export">
                            <i class="fas fa-file-export"></i> Export Log
                        </button>
                        <button onclick="openDispatchFlow()" class="btn-create">
                            <i class="fas fa-paper-plane"></i> New Dispatch
                        </button>
                    </div>
                </div>
                <div style="padding:24px; background:#f8fafc; border-bottom:1px solid var(--erp-border);">
                    <div style="flex:1; background:#fff; padding:20px; border:1px solid var(--erp-border); border-radius:6px; text-align:center; max-width:300px;">
                        <div style="font-size:11px; color:var(--erp-text-muted); text-transform:uppercase; font-weight:700;">Total Manifests</div>
                        <div style="font-size:32px; font-weight:700; color:#0f172a;" id="disp-total">0</div>
                    </div>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="po-table" id="tbl-dispatch">
                        <thead><tr>
                            <th>Dispatch ID</th><th>Target</th><th>Content</th>
                            <th>Date</th><th>Actions</th>
                        </tr></thead>
                        <tbody id="dispatchTableBody"></tbody>
                    </table>
                </div>
                <div id="page-tbl-dispatch" class="pagination-container"></div>
            </div>
        </main>
    </div>
</div>

<!-- PO Modal -->
<div id="poModal" class="modal-overlay">
    <div class="modal-container" style="max-width:1000px;">
        <h2><i class="fas fa-file-invoice"></i> CREATE PURCHASE ORDER</h2>
        <form id="poForm" onsubmit="savePO(event)">
            <div class="form-grid" style="grid-template-columns: repeat(3, 1fr);">
                <div class="form-group"><label>PO Number</label><input type="text" id="poNumber" placeholder="Auto-generate" style="background:#f1f5f9;"></div>
                <div class="form-group"><label>Vendor *</label><select id="vendorSelect"><option value="">Select Vendor</option></select></div>
                <div class="form-group"><label>Delivery Note *</label><input type="text" id="poDeliveryNote" required></div>
                <div class="form-group"><label>Invoice Number</label><input type="text" id="poInvoice"></div>
                <div class="form-group"><label>Store</label><input type="text" id="poAssignedStore" list="storeDatalist" placeholder="Warehouse"></div>
                <div class="form-group"><label>Brand</label><input type="text" id="poAssignedBrand"></div>
            </div>
            <div style="margin-top:25px; border-top:1px solid var(--erp-border); padding-top:20px;">
                <h3 style="font-size:13px; margin-bottom:15px; font-weight:700; text-transform:uppercase;">Line Items</h3>
                <div id="lineItemsContainer"></div>
                <button type="button" onclick="addLineItem()" class="btn-cancel" style="width:100%; justify-content:center; padding:12px; border:1px dashed var(--erp-text-blue); color:var(--erp-text-blue); background:#eff6ff;">
                    <i class="fas fa-plus"></i> ADD LINE ITEM
                </button>
            </div>
            <div class="form-grid" style="margin-top:25px; background:#f8fafc; padding:20px; border:1px solid var(--erp-border); border-radius:6px;">
                <div class="form-group"><label>Subtotal (SAR)</label><input type="text" id="subtotal" readonly style="font-weight:700; font-size:16px; border:none; background:transparent; padding:0;"></div>
                <div class="form-group"><label>Tax</label><input type="number" id="taxAmount" value="0" step="0.01" oninput="recalcTotals()"></div>
                <div class="form-group" style="grid-column:span 2;"><label style="color:var(--success);">Grand Total (SAR)</label>
                    <input type="text" id="grandTotal" readonly style="font-weight:800; color:var(--success); font-size:22px; border:none; background:transparent; padding:0;">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('poModal')">CANCEL</button>
                <button type="submit" class="btn-create">COMMIT PO</button>
            </div>
        </form>
    </div>
</div>

<!-- Dispatch Flow Modal -->
<div id="dispatchFlowModal" class="modal-overlay">
    <div class="modal-container">
        <h2><i class="fas fa-truck-ramp-box"></i> DISPATCH OPERATIONS</h2>
        <div>
            <div style="display:flex; gap:15px; padding:15px; background:#f8fafc; border-radius:6px; margin-bottom:20px;">
                <div id="dotStep1" style="font-weight:700; color:var(--erp-text-blue);">1. ASSIGNEE</div>
                <i class="fas fa-chevron-right" style="color:#94a3b8;"></i>
                <div id="dotStep2" style="color:#94a3b8; font-weight:600;">2. MATRIX</div>
            </div>
            
            <div id="dispatchStep1">
                <h4 style="margin-bottom:15px;">Select Target</h4>
                <div style="display:flex; gap:20px; margin-bottom:25px; padding:15px; border:1px solid var(--erp-border); border-radius:6px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="radio" name="assigneeType" value="store" checked onchange="toggleAssigneeType()"> STORE
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="radio" name="assigneeType" value="person" onchange="toggleAssigneeType()"> PERSONNEL
                    </label>
                </div>

                <div id="storeFields">
                    <div class="form-grid">
                        <div class="form-group"><label>Brand</label><select id="dfBrandSelect" onchange="filterStoresByBrand()"><option value="">-- Select Brand --</option></select></div>
                        <div class="form-group"><label>Target Store</label><select id="dfStoreSelect" onchange="autofillStoreMeta()" disabled><option value="">-- Select Store --</option></select></div>
                        <input type="hidden" id="dfStoreId">
                        <div class="form-group"><label>Entity</label><input type="text" id="dfStoreEntity" readonly style="background:#f1f5f9;"></div>
                        <div class="form-group"><label>Route</label><input type="text" id="dfStoreRoute" readonly style="background:#f1f5f9;"></div>
                    </div>
                </div>

                <div id="personFields" style="display:none;">
                    <div class="form-grid">
                        <div class="form-group"><label>Personnel</label><select id="dfPersonSelect" onchange="autofillPersonDetails()"><option value="">-- Select --</option></select></div>
                        <div class="form-group"><label>Emp ID</label><input type="text" id="dfPersonId" readonly style="background:#f1f5f9;"></div>
                        <div class="form-group"><label>Name</label><input type="text" id="dfPersonName" readonly style="background:#f1f5f9;"></div>
                        <div class="form-group"><label>Department</label><input type="text" id="dfPersonDept" readonly style="background:#f1f5f9;"></div>
                    </div>
                </div>
                
                <div class="form-actions" style="margin:0; padding-bottom:0; border-top:none; background:transparent;">
                    <button type="button" class="btn-cancel" onclick="closeModal('dispatchFlowModal')">CANCEL</button>
                    <button type="button" class="btn-create" onclick="goToDispatchStep2()">NEXT <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>

            <div id="dispatchStep2" style="display:none;">
                <div style="display:flex; gap:10px; margin-bottom:15px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; background:#fff;">
                    <i class="fas fa-search" style="color:#94a3b8;"></i>
                    <input type="text" id="dispatchSearch" placeholder="Search inventory..." oninput="searchDispatchItems()" style="flex:1; border:none; outline:none;">
                </div>
                <div style="max-height:200px; overflow-y:auto; margin-bottom:20px; border:1px solid var(--erp-border); border-radius:6px;" id="dispatchSearchResults"></div>
                
                <h4 style="margin-bottom:10px;"><i class="fas fa-clipboard-list"></i> Manifest Staging</h4>
                <div id="dispatchCartContainer" style="min-height:100px;"></div>
                
                <div class="form-actions" style="margin:0; padding-bottom:0; border-top:none; background:transparent;">
                    <button type="button" class="btn-cancel" onclick="goToDispatchStep1()"><i class="fas fa-arrow-left"></i> BACK</button>
                    <button type="button" class="btn-create" onclick="submitDispatch()"><i class="fas fa-check"></i> EXECUTE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dispatch Details Modal -->
<div id="dispatchDetailsModal" class="modal-overlay">
    <div class="modal-container" style="max-width:850px; padding:0;">
        <div style="padding:30px;" id="receiptContent">
            <!-- Receipt content rendered by JS -->
        </div>
        <div class="form-actions" style="background:#f8fafc; padding:15px 25px; border-top:1px solid var(--erp-border);">
            <button type="button" class="btn-cancel" onclick="closeModal('dispatchDetailsModal')">CLOSE</button>
            <button type="button" class="btn-create" id="printReceiptBtn"><i class="fas fa-print"></i> PRINT</button>
        </div>
    </div>
</div>

<!-- PO Tracker Modal -->
<div id="poTrackerModal" class="modal-overlay">
    <div class="modal-container" style="max-width:1100px;">
        <div class="modal-header">
            <span><i class="fas fa-route"></i> PO LIFECYCLE TRACKER</span>
        </div>
        <div class="modal-body">
            <h4 id="trackerPoTitle" style="color:var(--erp-text-muted); margin-bottom:20px; font-weight:700; font-size:14px;">TRACKING: PO-XXXX</h4>
            <div class="table-scroll-wrapper" style="border:1px solid var(--erp-border); border-radius:6px;">
                <table class="po-table" id="tbl-po-tracker">
                    <thead><tr>
                        <th>Type</th><th>Item</th><th>SKU</th><th>Qty</th><th>Assignment Status</th>
                    </tr></thead>
                    <tbody id="poTrackerTableBody"></tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModal('poTrackerModal')">CLOSE</button>
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

<div id="toastMessage" class="toast-msg"></div>

<datalist id="storeDatalist"></datalist>
<datalist id="hardwareTypesList">
    <option value="SYSTEM"></option><option value="MONITER"></option><option value="CASH DRAWYER"></option>
    <option value="BARCODE READER"></option><option value="EPSON PRINTER"></option><option value="FORTINET"></option>
    <option value="TRAFFIC DEVICE"></option><option value="BIOMETRIC"></option><option value="MPOSE"></option>
    <option value="Acces Point"></option><option value="A4 Printer"></option><option value="SWITCH"></option>
    <option value="Router"></option><option value="SIMCARD"></option><option value="CAMERA"></option><option value="NVR"></option>
</datalist>

<script src="assets/js/po-dispatch.js"></script>
</body>
</html>