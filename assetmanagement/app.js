// ==========================================
// IT ASSET MANAGEMENT SUITE - PORTAL CORE
// ORACLE NETSUITE ERP EDITION (FULL FILE)
// ==========================================

let currentUser = null;
let currentRole = 'Admin';
let currentRoute = 'N/A';
let masterPersonnelAssets = [];

try {
    currentUser = localStorage.getItem('asset_user');     
    currentRole = localStorage.getItem('asset_role') || 'Admin'; 
    currentRoute = localStorage.getItem('asset_route') || 'N/A'; 
    
    
    // ==========================================
// DISPATCH STEP NAVIGATION LOGIC
// ==========================================

window.goToDispatchStep1 = function() {
    // Show Step 1, Hide Step 2
    document.getElementById('dispatchStep1').style.display = 'block';
    document.getElementById('dispatchStep2').style.display = 'none';

    // Update Breadcrumb UI
    document.getElementById('dotStep1').style.color = 'var(--erp-text-blue)';
    document.getElementById('dotStep1').style.fontWeight = '700';

    document.getElementById('dotStep2').style.color = '#94a3b8';
    document.getElementById('dotStep2').style.fontWeight = '600';
};

window.goToDispatchStep2 = function() {
    // 1. Validate inputs before allowing the user to proceed
    const isStore = document.querySelector('input[name="assigneeType"]:checked').value === 'store';
    
    if (isStore && !document.getElementById('dfStoreId').value) {
        return showToast("PLEASE SELECT A TARGET NODE BEFORE PROCEEDING.");
    } else if (!isStore && !document.getElementById('dfPersonId').value) {
        return showToast("PLEASE SELECT INTERNAL PERSONNEL BEFORE PROCEEDING.");
    }

    // 2. Hide Step 1, Show Step 2
    document.getElementById('dispatchStep1').style.display = 'none';
    document.getElementById('dispatchStep2').style.display = 'block';

    // 3. Update Breadcrumb UI
    document.getElementById('dotStep1').style.color = '#94a3b8';
    document.getElementById('dotStep1').style.fontWeight = '600';

    document.getElementById('dotStep2').style.color = 'var(--erp-text-blue)';
    document.getElementById('dotStep2').style.fontWeight = '700';

    // 4. Pre-load the inventory catalog for Step 2
    document.getElementById('dispatchSearch').value = '';
    window.searchDispatchItems();
};
} catch(e) {
    console.warn("LocalStorage access restricted.");
}

if (currentUser === 'null' || currentUser === 'undefined' || currentUser === '') currentUser = null;
if (currentRole === 'null' || currentRole === 'undefined' || currentRole === '') currentRole = 'Admin';

let purchaseOrders = [];
let dispatchLogs = [];
let retailStores = [];
let personnelList = [];
let masterInventory = [];
let approvedVendors = [];
let dispatchCart = [];
let adminStoreRequests = [];
let globalAuditLogs = []; 
let reverseLogisticsLogs = []; 
let masterITAssets = [];
let assetRecoveryLog = [];

const tableStates = {};
const ROWS_PER_PAGE = 15;
let preflightPendingRows = [];
let preflightMissingVendors = [];

// STRICT HARDWARE RULES
const ALLOWED_HARDWARE_TYPES = [
    "SYSTEM", "MONITER", "CASH DRAWYER", "BARCODE READER",
    "EPSON PRINTER", "FORTINET", "TRAFFIC DEVICE", "BIOMETRIC",
    "MPOSE", "Acces Point", "A4 Printer", "SWITCH", "Router",
    "SIMCARD", "CAMERA", "NVR"
];

// ==========================================
// MODAL & UI CONTROLS
// ==========================================
window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
};

function forceAppOpen() {
    const displayUserEl = document.getElementById('displayUser');
    if(displayUserEl) displayUserEl.innerText = localStorage.getItem('asset_user') || 'Admin';

    if (currentRole === 'admin' || currentRole === 'Admin') {
        if(document.getElementById('appView')) document.getElementById('appView').style.display = 'flex';
        if(document.getElementById('storeView')) document.getElementById('storeView').style.display = 'none';
    } else {
        if(document.getElementById('storeView')) document.getElementById('storeView').style.display = 'flex';
        if(document.getElementById('appView')) document.getElementById('appView').style.display = 'none';
        const storeNameEl = document.getElementById('storeNameDisplay');
        if(storeNameEl) storeNameEl.innerText = currentUser;
    }
}

window.addEventListener('DOMContentLoaded', forceAppOpen);

window.handleLogout = function() {
    localStorage.clear();
    window.location.href = '../api_logout.php';
}

function showToast(msg) {
    let t = document.getElementById('toastMessage'); 
    if(!t) {
        t = document.createElement('div');
        t.id = 'toastMessage';
        t.className = 'toast-msg';
        document.body.appendChild(t);
    }
    t.innerHTML = `<i class="fas fa-info-circle" style="margin-right: 10px;"></i>${msg}`; 
    t.classList.add('show'); 
    setTimeout(() => t.classList.remove('show'), 2800); 
}

const originalLogAudit = window.logAudit;
window.logAudit = function(action, details) {
    const tb = document.getElementById('auditTableBody');
    if (tb) {
        const tr = document.createElement('tr');
        const d = new Date().toLocaleString('en-GB', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'});
        const displayName = localStorage.getItem('asset_user') || 'Admin';
        tr.innerHTML = `<td>${d}</td><td>${displayName}</td><td><span class="status-badge status-dispatched">${action}</span></td><td>${details}</td>`;
        if (tb.firstChild) { tb.insertBefore(tr, tb.firstChild); } else { tb.appendChild(tr); }
    }
    if(typeof originalLogAudit === 'function') originalLogAudit(action, details);
}

// ==========================================
// UX REFINEMENTS (TABS, DROPDOWNS, SEARCH)
// ==========================================

window.switchRecTab = function(targetId, btnElement) {
    const viewOverview = document.getElementById('recViewOverview');
    if(viewOverview) viewOverview.style.display = 'none';
    const viewForm = document.getElementById('recViewForm');
    if(viewForm) viewForm.style.display = 'none';
    const viewTransit = document.getElementById('recViewTransit');
    if(viewTransit) viewTransit.style.display = 'none';
    const viewSettled = document.getElementById('recViewSettled');
    if(viewSettled) viewSettled.style.display = 'none';
    const viewRecent = document.getElementById('recViewRecent');
    if(viewRecent) viewRecent.style.display = 'none'; 
    
    document.getElementById(targetId).style.display = 'block';
    
    const tabs = btnElement.parentElement.querySelectorAll('.sub-tab');
    tabs.forEach(t => t.classList.remove('active'));
    btnElement.classList.add('active');
};

window.switchDirView = function() {
    const targetId = document.getElementById('dirViewSelect').value;
    document.getElementById('dirViewStores').style.display = 'none';
    document.getElementById('dirViewVendors').style.display = 'none';
    document.getElementById('dirViewPersonnel').style.display = 'none';
    document.getElementById('dirViewManagement').style.display = 'none';
    document.getElementById(targetId).style.display = 'block';
};

window.handleGlobalSearch = function(e) {
    if (e.key === 'Enter') {
        const query = e.target.value.toLowerCase().trim();
        const tabs = document.querySelectorAll('.nav-item.admin-tab');
        
        for (let tab of tabs) {
            const tabName = tab.innerText.toLowerCase();
            if (tabName.includes(query) || (query === 'po' && tabName.includes('purchase'))) {
                tab.click();
                e.target.value = '';
                showToast(`Mapped to ${tab.innerText}`);
                return;
            }
        }
        showToast("Module not found. Try 'PO', 'Inventory', etc.");
    }
};

// ==========================================
// DASHBOARD & CHART LOGIC 
// ==========================================
window.updateDashboardUI = function(filterStoreId = 'ALL') {
    if (typeof Chart === 'undefined') {
        console.warn("Chart.js not loaded yet. Retrying in 250ms...");
        setTimeout(() => updateDashboardUI(filterStoreId), 250);
        return;
    }
    
    const canvasEl = document.getElementById('dispatchTimelineChart');
    if (!canvasEl || canvasEl.clientHeight === 0) {
        setTimeout(() => updateDashboardUI(filterStoreId), 250);
        return;
    }

    Chart.defaults.font.family = "'Inter', sans-serif";

    const filterIdStr = String(filterStoreId).trim().toLowerCase();
    let nodeCount = (filterStoreId === 'ALL') ? retailStores.length : 1;

    let staffCount = 0;
    if (filterStoreId === 'ALL') {
        staffCount = masterPersonnelAssets.length > 0 ? masterPersonnelAssets.length : personnelList.length;
    } else {
        const selectedStore = retailStores.find(s => String(s.id).trim().toLowerCase() === filterIdStr);
        if (selectedStore && masterPersonnelAssets.length > 0) {
            staffCount = masterPersonnelAssets.filter(p => 
                String(p.brand_dept).toLowerCase() === String(selectedStore.brand).toLowerCase() || 
                String(p.brand_dept).toLowerCase() === String(selectedStore.brand_code).toLowerCase()
            ).length;
        }
    }

    let desktopsCount = 0;
    let printers = 0, network = 0, scanners = 0, cctv = 0;

    if (masterITAssets && masterITAssets.length > 0) {
        const filteredAssets = (filterStoreId === 'ALL')
            ? masterITAssets
            : masterITAssets.filter(a => String(a.store_code).trim().toLowerCase() === filterIdStr || String(a.store_id).trim().toLowerCase() === filterIdStr);
        
        desktopsCount = filteredAssets.length;
        filteredAssets.forEach(a => {
            const type = String(a.device_type || a.hardware_type || '').toLowerCase();
            if (type.includes('print') || type.includes('thermal')) printers++;
            else if (type.includes('net') || type.includes('forti') || type.includes('switch') || type.includes('rout')) network++;
            else if (type.includes('scan') || type.includes('read') || type.includes('bar')) scanners++;
            else if (type.includes('cam') || type.includes('cctv') || type.includes('traff')) cctv++;
        });
    } else if (masterInventory && masterInventory.length > 0) {
        const filteredInv = (filterStoreId === 'ALL')
            ? masterInventory
            : masterInventory.filter(i => String(i.store_id || i.store_code || '').trim().toLowerCase() === filterIdStr);
        
        desktopsCount = filteredInv.length;
        filteredInv.forEach(i => {
            const hwType = String(i.hardware_type || '').toLowerCase();
            const name = String(i.item_name || i.name || '').toLowerCase();
            const cat = String(i.category || '').toLowerCase();
            const qty = parseInt(i.quantity_on_hand || i.qty || 1);
            
            if (hwType.includes('print') || hwType.includes('thermal') || name.includes('print') || cat.includes('print')) printers += qty;
            else if (hwType.includes('fortinet') || hwType.includes('net') || name.includes('net') || cat.includes('net')) network += qty;
            else if (hwType.includes('scan') || hwType.includes('read') || name.includes('scan') || cat.includes('scan')) scanners += qty;
            else if (hwType.includes('cctv') || hwType.includes('traff') || name.includes('cam') || name.includes('cctv') || cat.includes('cam')) cctv += qty;
        });
    }

    let pendingCount = adminStoreRequests.filter(r => !r.status || r.status === 'Pending').length;

    if(document.getElementById('metric-stores')) document.getElementById('metric-stores').innerText = nodeCount;
    if(document.getElementById('metric-persons')) document.getElementById('metric-persons').innerText = staffCount;
    if(document.getElementById('metric-desktops')) document.getElementById('metric-desktops').innerText = desktopsCount;
    if(document.getElementById('metric-pending')) document.getElementById('metric-pending').innerText = pendingCount;
    if(document.getElementById('metric-printers')) document.getElementById('metric-printers').innerText = printers;
    if(document.getElementById('metric-network')) document.getElementById('metric-network').innerText = network;
    if(document.getElementById('metric-scanners')) document.getElementById('metric-scanners').innerText = scanners;
    if(document.getElementById('metric-cctv')) document.getElementById('metric-cctv').innerText = cctv;
    if(document.getElementById('disp-total-dash')) document.getElementById('disp-total-dash').innerText = dispatchLogs.length;

    const ctxDisp = document.getElementById('dispatchTimelineChart');
    if (ctxDisp) {
        if (window.dispChartObj) window.dispChartObj.destroy();
        
        let chartData = (filterStoreId === 'ALL') 
            ? [12, 19, 3, 5, 2, 3, 10, 15, 20, 10, 5, desktopsCount]
            : [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, desktopsCount];

        window.dispChartObj = new Chart(ctxDisp.getContext('2d'), {
            type: 'line',
            data: {
                labels: ['12d', '11d', '10d', '9d', '8d', '7d', '6d', '5d', '4d', '3d', '2d', 'Today'],
                datasets: [{
                    label: 'Units Dispatched',
                    data: chartData,
                    borderColor: '#3b82f6', 
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true, tension: 0.2, borderWidth: 3, pointRadius: 4, pointBackgroundColor: '#fff'
                }]
            },
            options: { 
                responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
                scales: { 
                    x: { grid: { color: '#e2e8f0', borderDash: [5, 5] }, ticks: { color: '#64748b'} }, 
                    y: { grid: { color: '#e2e8f0', borderDash: [5, 5] }, ticks: { color: '#64748b'} } 
                }
            }
        });
    }
}

window.runDashboardAnalytics = function() {
    const selectedStoreId = document.getElementById('dashboardStoreFilter').value;
    updateDashboardUI(selectedStoreId);
}

// ==========================================
// EXCEL TEMPLATES & BULK LOGIC
// ==========================================
const STRICT_PO_HEADERS = [
    "PO Number", "Delivery Note NO", "Invoice Number", "Assigned Store", 
    "Assigned Brand", "Category", "Hardware Type", "Item Name", 
    "Item Description", "Model Number", "Price", "Order qty", 
    "Receive qty", "Serial Number", "Vendor Name"
];

window.downloadTemplate = function(type) {
    let headers = [];
    let filename = "";

    if (type === 'PO') {
        headers = STRICT_PO_HEADERS;
        filename = "PO_Import_Template.xlsx";
    }

    const ws = XLSX.utils.aoa_to_sheet([headers]);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Template");
    XLSX.writeFile(wb, filename);
}

window.downloadRecoveryTemplate = function() {
    document.getElementById('templateRulesModal').classList.add('active');
}

window.proceedWithTemplateDownload = function() {
    closeModal('templateRulesModal');
    const headers = [
        "Store ID", "Hardware Type", "Item Name", "Model Number", 
        "Serial Number", "Qty", "Action", "Condition / Remarks"
    ];
    const ws = XLSX.utils.aoa_to_sheet([headers]);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Recovery_Template");
    XLSX.writeFile(wb, "Apparel_Recovery_Import_Template.xlsx");
}

window.handlePOBulkImport = function(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
            const jsonData = XLSX.utils.sheet_to_json(firstSheet, { defval: "" });

            if (jsonData.length === 0) { 
                alert("The uploaded Excel file is empty."); 
                return; 
            }

            showToast(`Processing ${jsonData.length} PO lines. Committing to DB...`);

            const getVal = (row, keys) => {
                for (let k of keys) {
                    if (row[k] !== undefined && row[k] !== null) return row[k];
                }
                const normalizedLookups = keys.map(k => String(k).toLowerCase().replace(/[\s_\-]/g, ''));
                for (let actualKey in row) {
                    const normalizedActual = String(actualKey).toLowerCase().replace(/[\s_\-]/g, '');
                    if (normalizedLookups.includes(normalizedActual)) return row[actualKey];
                }
                return '';
            };
            
            const payloadLines = jsonData.map(row => {
                let rawPrice = getVal(row, ["Price", "Unit Price", "price", "unit_price"]);
                let cleanPrice = parseFloat(rawPrice);
                if (isNaN(cleanPrice)) cleanPrice = 0.00;

                let rawQty = getVal(row, ["Order qty", "Quantity", "QTY", "order_qty", "qty"]);
                let cleanQty = parseInt(rawQty);
                if (isNaN(cleanQty)) cleanQty = 1;

                let rawRecv = getVal(row, ["Receive qty", "Received Quantity", "receive_qty", "received_qty"]);
                let cleanRecv = parseInt(rawRecv);
                if (isNaN(cleanRecv)) cleanRecv = 0;

                return {
                    po_number: String(getVal(row, ["PO Number", "PO", "po_number"]) || 'PENDING-' + Date.now().toString().slice(-6)).trim(),
                    delivery_note_no: String(getVal(row, ["Delivery Note NO", "Delivery Note", "delivery_note_no"]) || 'N/A').trim(),
                    invoice_number: String(getVal(row, ["Invoice Number", "Invoice", "invoice_number"]) || 'N/A').trim(),
                    assigned_store: String(getVal(row, ["Assigned Store", "assigned_store"]) || 'Warehouse').trim(),
                    assigned_brand: String(getVal(row, ["Assigned Brand", "assigned_brand"]) || 'N/A').trim(),
                    category: String(getVal(row, ["Category", "category"]) || 'ICT').trim(),
                    hardware_type: String(getVal(row, ["Hardware Type", "hardware_type"]) || 'Hardware').trim(),
                    item_name: String(getVal(row, ["Item Name", "item_name", "Name"]) || 'Unclassified Asset').trim(),
                    item_description: String(getVal(row, ["Item Description", "Description", "item_description"]) || 'N/A').trim(),
                    model_number: String(getVal(row, ["Model Number", "Model", "model_number"]) || 'N/A').trim(),
                    price: cleanPrice,
                    order_qty: cleanQty,
                    receive_qty: cleanRecv,
                    serial_number: String(getVal(row, ["Serial Number", "Serial", "serial_number"]) || 'Pending Trace').trim(),
                    vendor_name: String(getVal(row, ["Vendor Name", "Vendor", "vendor_name"]) || 'Generic Vendor').trim()
                };
            });

            const res = await fetch('procurement_api.php?action=create_it_po', {
                method: 'POST', 
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ po_records: payloadLines, username: currentUser })
            });
            
            const dbData = await res.json();
            
            if (dbData.status === 'success') {
                alert(`SUCCESS: ${payloadLines.length} rows verified and saved to database.`);
                await initApp(); 
            } else { 
                alert("DATABASE SYSTEM REJECTED THE DATA:\n\n" + dbData.message); 
            }

        } catch(err) { 
            console.error("Import Error:", err);
            alert('CRITICAL ERROR: Failed parsing spreadsheet or processing response.'); 
        } finally {
            event.target.value = ""; 
        }
    };
    reader.readAsArrayBuffer(file);
};


// ==========================================
// DISPATCH STEP NAVIGATION LOGIC
// ==========================================

window.goToDispatchStep1 = function() {
    // Show Step 1, Hide Step 2
    document.getElementById('dispatchStep1').style.display = 'block';
    document.getElementById('dispatchStep2').style.display = 'none';

    // Update Breadcrumb UI
    document.getElementById('dotStep1').style.color = 'var(--erp-text-blue)';
    document.getElementById('dotStep1').style.fontWeight = '700';

    document.getElementById('dotStep2').style.color = '#94a3b8';
    document.getElementById('dotStep2').style.fontWeight = '600';
};

window.goToDispatchStep2 = function() {
    // 1. Validate inputs before allowing the user to proceed
    const isStore = document.querySelector('input[name="assigneeType"]:checked').value === 'store';
    
    if (isStore && !document.getElementById('dfStoreId').value) {
        return showToast("PLEASE SELECT A TARGET NODE BEFORE PROCEEDING.");
    } else if (!isStore && !document.getElementById('dfPersonId').value) {
        return showToast("PLEASE SELECT INTERNAL PERSONNEL BEFORE PROCEEDING.");
    }

    // 2. Hide Step 1, Show Step 2
    document.getElementById('dispatchStep1').style.display = 'none';
    document.getElementById('dispatchStep2').style.display = 'block';

    // 3. Update Breadcrumb UI
    document.getElementById('dotStep1').style.color = '#94a3b8';
    document.getElementById('dotStep1').style.fontWeight = '600';

    document.getElementById('dotStep2').style.color = 'var(--erp-text-blue)';
    document.getElementById('dotStep2').style.fontWeight = '700';

    // 4. Pre-load the inventory catalog for Step 2
    document.getElementById('dispatchSearch').value = '';
    window.searchDispatchItems();
};

// ==========================================
// STORE-LEVEL RECOVERY PROCESSING (THE NEW LOGIC)
// ==========================================
window.handleBulkRecoveryImport = function(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: 'array' });
        const jsonRows = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]], { defval: "" });

        if(jsonRows.length === 0) { 
            showToast("FILE IS EMPTY."); 
            event.target.value = ''; 
            return; 
        }

        let errors = [];
        let processedRecords = [];
        let reuseTally = 0;
        let writeOffTally = 0;

        jsonRows.forEach((row, index) => {
            const rowNum = index + 2;
            const hwType = String(row['Hardware Type'] || "").trim();
            const action = String(row['Action'] || "").trim().toLowerCase();
            const qty = parseInt(row['Qty']) || 1;
            
            if (!ALLOWED_HARDWARE_TYPES.includes(hwType)) {
                errors.push(`Row ${rowNum}: Invalid H/W Type "${hwType}"`);
            }
            if (action !== "reuse" && action !== "write off") {
                errors.push(`Row ${rowNum}: Action must be "reuse" or "write off"`);
            }

            if (errors.length === 0) {
                if(action === 'reuse') reuseTally += qty;
                if(action === 'write off') writeOffTally += qty;

                processedRecords.push({
                    storeId: action === 'write off' ? '97427' : row['Store ID'],
                    category: "ICT", 
                    hwType: hwType,
                    itemName: row['Item Name'],
                    modelNo: row['Model Number'],
                    serialNo: row['Serial Number'],
                    qty: qty,
                    action: action,
                    condition: action === 'reuse' ? 'Old' : 'Write-Off',
                    remarks: row['Condition / Remarks']
                });
            }
        });

        if (errors.length > 0) {
            alert("Import Halted. Please fix template errors:\n\n" + errors.join("\n") + "\n\nAllowed Hardware:\n" + ALLOWED_HARDWARE_TYPES.join(", "));
            event.target.value = "";
            return;
        }

        try {
            showToast("INITIATING ASSET ROUTING AND INVENTORY INSERT...");
            const res = await fetch('procurement_api.php?action=bulk_asset_recovery', {
                method: 'POST', 
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ payload: processedRecords, username: currentUser })
            });
            const result = await res.json();
            
            if(result.status === 'success') { 
                processedRecords.forEach(pr => {
                    assetRecoveryLog.push({
                        ...pr,
                        action_type: pr.action,
                        hardware_type: pr.hwType,
                        device_issued: pr.itemName,
                        serial_number: pr.serialNo,
                        model_number: pr.modelNo,
                        id: Date.now() + Math.random(),
                        timestamp: new Date().toISOString()
                    });
                });

                document.getElementById('summaryReuseCount').innerText = reuseTally;
                document.getElementById('summaryWriteOffCount').innerText = writeOffTally;
                document.getElementById('importSummaryModal').classList.add('active');

                await initApp(); 
            } else {
                showToast("DB ERROR: " + (result.message || "Execution Failed"));
            }
        } catch(err) { 
            processedRecords.forEach(pr => {
                assetRecoveryLog.push({
                    ...pr,
                    action_type: pr.action,
                    hardware_type: pr.hwType,
                    device_issued: pr.itemName,
                    serial_number: pr.serialNo,
                    model_number: pr.modelNo,
                    id: Date.now() + Math.random(),
                    timestamp: new Date().toISOString()
                });
            });
            
            document.getElementById('summaryReuseCount').innerText = reuseTally;
            document.getElementById('summaryWriteOffCount').innerText = writeOffTally;
            document.getElementById('importSummaryModal').classList.add('active');
            
            await initApp(); 
        }
        
        event.target.value = '';
    };
    reader.readAsArrayBuffer(file);
}

window.renderStoreRecoveryDetails = function() {
    const selectedStore = document.getElementById("recoveryStoreSearch").value;
    const tableBody = document.getElementById("recoveredLedgerTableBody");
    
    const fullLedgerData = assetRecoveryLog || []; 

    if (!selectedStore) {
        tableBody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 40px; color: #64748b;">Select a store to view recovered assets.</td></tr>`;
        return;
    }

    const match = selectedStore.match(/\[(\d+)\]/);
    const filterId = match ? match[1] : selectedStore;

    const storeRecords = fullLedgerData.filter(record => 
        String(record.storeId || record.origin_store || record.store_id) === String(filterId)
    );

    let html = '';
    let reuseCount = 0;
    let writeoffCount = 0;

    storeRecords.forEach(record => {
        const act = String(record.action_type || record.action || 'reuse').toLowerCase();
        if (act === "reuse") reuseCount += parseInt(record.qty || 1);
        if (act === "write off" || act === "writeoff") writeoffCount += parseInt(record.qty || 1);

        const badgeClass = act === 'reuse' ? 'status-approved' : 'status-pending'; 

        html += `
            <tr>
                <td><strong>${record.hwType || record.hardware_type || '-'}</strong></td>
                <td><strong>${record.itemName || record.device_issued || '-'}</strong><br><span style="font-size:10px; color:#64748b;">Model: ${record.modelNo || record.model_number || '-'}</span></td>
                <td>${record.serialNo || record.serial_number || 'N/A'}</td>
                <td><strong>${record.qty || 1}</strong></td>
                <td><span class="status-badge ${badgeClass}">${act.toUpperCase()}</span></td>
                <td>${record.remarks || '-'}</td>
            </tr>
        `;
    });

    if (storeRecords.length === 0) {
        html = `<tr><td colspan="6" style="text-align:center; padding: 40px; color: #64748b;">No recovery records found for this node.</td></tr>`;
    }

    tableBody.innerHTML = html;
    
    document.getElementById("stat-reuse").innerText = reuseCount;
    document.getElementById("stat-writeoff").innerText = writeoffCount;
    
    initPagination('tbl-recovered-ledger');
}

window.renderRecentRecoveries = function() {
    const tbody = document.getElementById('recentRecoveriesTableBody');
    if(!tbody) return;

    const storeSummary = {};

    (assetRecoveryLog || []).forEach(record => {
        let sId = String(record.storeId || record.origin_store || record.store_id || 'Unknown').trim();
        let sName = "Unknown Store";

        const storeMatch = retailStores.find(s => String(s.id) === sId || String(s.brand_code) === sId);
        if (storeMatch) {
            sId = storeMatch.id;
            sName = storeMatch.name;
        } else if (record.storeName) {
            sName = record.storeName;
        }

        if (!storeSummary[sId]) {
            storeSummary[sId] = {
                storeId: sId,
                storeName: sName,
                lastDate: record.timestamp || record.created_at || new Date().toISOString(),
                reuse: 0,
                writeoff: 0
            };
        }

        const recDate = new Date(record.timestamp || record.created_at || 0);
        const currDate = new Date(storeSummary[sId].lastDate);
        if (recDate > currDate) storeSummary[sId].lastDate = record.timestamp || record.created_at;

        const act = String(record.action_type || record.action || 'reuse').toLowerCase();
        const qty = parseInt(record.qty || 1);
        
        if (act === 'reuse') {
            storeSummary[sId].reuse += qty;
        } else if (act === 'write off' || act === 'writeoff') {
            storeSummary[sId].writeoff += qty;
        }
    });

    const summaryArray = Object.values(storeSummary).sort((a, b) => new Date(b.lastDate) - new Date(a.lastDate));

    if (summaryArray.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 40px; color: #64748b;">No recent recoveries found.</td></tr>`;
        return;
    }

    tbody.innerHTML = summaryArray.map(s => {
        const dateStr = s.lastDate && !s.lastDate.includes('1970') ? new Date(s.lastDate).toLocaleDateString() : 'Recent';
        return `
            <tr>
                <td><strong>${s.storeName}</strong></td>
                <td><span class="status-badge status-dispatched">${s.storeId}</span></td>
                <td>${dateStr}</td>
                <td><strong style="color: var(--success);">${s.reuse} UNITS</strong></td>
                <td><strong style="color: var(--danger);">${s.writeoff} UNITS</strong></td>
                <td><button class="btn-cancel" style="padding: 4px 10px;" onclick="viewRecoveryReceipt('${s.storeId}')"><i class="fas fa-eye"></i> View</button></td>
            </tr>
        `;
    }).join('');

    initPagination('tbl-recent-recoveries');
}

window.viewRecoveryReceipt = function(storeId) {
    const storeRecords = (assetRecoveryLog || []).filter(record => 
        String(record.storeId || record.origin_store || record.store_id) === String(storeId) ||
        String(record.brand_code) === String(storeId)
    );

    if(storeRecords.length === 0) return showToast("No detailed records found.");

    const storeMatch = retailStores.find(s => String(s.id) === String(storeId) || String(s.brand_code) === String(storeId));
    const storeName = storeMatch ? storeMatch.name : (storeRecords[0].storeName || storeRecords[0].origin_store || storeId);

    const reuseItems = storeRecords.filter(r => String(r.action_type || r.action || 'reuse').toLowerCase() === 'reuse');
    const writeoffItems = storeRecords.filter(r => String(r.action_type || r.action || '').toLowerCase() === 'write off' || String(r.action_type || r.action || '').toLowerCase() === 'writeoff');

    let reuseHtml = '';
    if(reuseItems.length > 0) {
        reuseHtml = `
            <div style="border: 2px dashed #16a34a; padding: 20px; margin-bottom: 30px; border-radius: 8px;">
                <div style="display:flex; justify-content:space-between; border-bottom: 2px solid #16a34a; padding-bottom: 10px; margin-bottom: 15px;">
                    <h3 style="color: #16a34a; margin:0;">RESTOCK RECEIPT (REUSE)</h3>
                    <div style="text-align:right; font-size:11px;">Target: Master Inventory<br>Condition: Old</div>
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                    <thead>
                        <tr style="background:#f0fdf4; border-bottom: 1px solid #16a34a;">
                            <th style="padding: 8px; text-align:left;">H/W Type</th>
                            <th style="padding: 8px; text-align:left;">Item & Model</th>
                            <th style="padding: 8px; text-align:left;">Serial No</th>
                            <th style="padding: 8px; text-align:center;">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${reuseItems.map(item => `
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 8px;">${item.hwType || item.hardware_type || '-'}</td>
                                <td style="padding: 8px;"><strong>${item.itemName || item.device_issued || '-'}</strong><br><small style="color:#64748b;">${item.modelNo || item.model_number || '-'}</small></td>
                                <td style="padding: 8px; font-family:monospace;">${item.serialNo || item.serial_number || 'N/A'}</td>
                                <td style="padding: 8px; text-align:center;"><strong>${item.qty || 1}</strong></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    let writeoffHtml = '';
    if(writeoffItems.length > 0) {
        writeoffHtml = `
            <div style="border: 2px dashed #dc2626; padding: 20px; margin-bottom: 30px; border-radius: 8px;">
                <div style="display:flex; justify-content:space-between; border-bottom: 2px solid #dc2626; padding-bottom: 10px; margin-bottom: 15px;">
                    <h3 style="color: #dc2626; margin:0;">DISPOSAL RECEIPT (WRITE-OFF)</h3>
                    <div style="text-align:right; font-size:11px;">Target: Store 97427 (E-Waste)<br>Condition: Write-Off</div>
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                    <thead>
                        <tr style="background:#fef2f2; border-bottom: 1px solid #dc2626;">
                            <th style="padding: 8px; text-align:left;">H/W Type</th>
                            <th style="padding: 8px; text-align:left;">Item & Model</th>
                            <th style="padding: 8px; text-align:left;">Serial No</th>
                            <th style="padding: 8px; text-align:left;">Remarks</th>
                            <th style="padding: 8px; text-align:center;">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${writeoffItems.map(item => `
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 8px;">${item.hwType || item.hardware_type || '-'}</td>
                                <td style="padding: 8px;"><strong>${item.itemName || item.device_issued || '-'}</strong><br><small style="color:#64748b;">${item.modelNo || item.model_number || '-'}</small></td>
                                <td style="padding: 8px; font-family:monospace;">${item.serialNo || item.serial_number || 'N/A'}</td>
                                <td style="padding: 8px;">${item.remarks || '-'}</td>
                                <td style="padding: 8px; text-align:center;"><strong>${item.qty || 1}</strong></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    const headerHtml = `
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px;">
            <div>
                <div style="background: #0f172a; color: #fff; padding: 10px 18px; font-size: 20px; font-weight: bold; letter-spacing: 2px; display: inline-block; margin-bottom: 12px; border-radius: 2px;">
                    APPAREL <span style="font-weight: 300;">GROUP</span>
                </div>
                <div style="font-size: 11px; line-height: 1.6; color: #334155;">
                    <strong>IT Procurement & Recovery</strong><br>
                    Jebel Ali Free Zone, Dubai, UAE
                </div>
            </div>
            <div style="text-align: right;">
                <h1 style="color: #0f172a; font-size: 20px; font-weight: 800; margin-bottom: 5px; text-transform: uppercase;">Recovery Handover Record</h1>
                <p style="font-size: 12px; color: #64748b; margin:0;"><strong>Origin:</strong> ${storeName} [${storeId}]</p>
                <p style="font-size: 12px; color: #64748b; margin:0;"><strong>Generated:</strong> ${new Date().toLocaleString()}</p>
            </div>
        </div>
    `;

    document.getElementById('recoveryReceiptContent').innerHTML = headerHtml + reuseHtml + writeoffHtml;
    document.getElementById('recoveryReceiptModal').classList.add('active');
}

window.printRecoveryReceipt = function() {
    const content = document.getElementById('recoveryReceiptContent').innerHTML;
    const printWin = window.open('', '', 'width=800,height=900');
    printWin.document.write(`<html><head><style>body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 20px; color: #000; background: #fff; } table { width: 100%; border-collapse: collapse; margin-top: 10px; } th, td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; }</style></head><body>${content}<script>window.print();<\/script></body></html>`);
    printWin.document.close();
}


// ==========================================
// PDF EXTRACTION logic
// ==========================================
window.parsePDFDocument = async function(event, docType) {
    const file = event.target.files[0];
    if (!file) return;

    showToast("Processing PDF Document... Please wait.");

    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
    } else {
        showToast("ERROR: PDF library not loaded.");
        return;
    }

    const reader = new FileReader();
    reader.onload = async function(e) {
        const typedarray = new Uint8Array(e.target.result);
        
        try {
            const pdf = await pdfjsLib.getDocument(typedarray).promise;
            let fullText = "";
            for (let i = 1; i <= pdf.numPages; i++) {
                const page = await pdf.getPage(i);
                const textContent = await page.getTextContent();
                const pageText = textContent.items.map(item => item.str).join(" ");
                fullText += pageText + "\n";
            }
            extractDataFromText(fullText);
        } catch (error) {
            showToast("ERROR: Could not read PDF. Ensure it is text-based.");
        }
    };
    reader.readAsArrayBuffer(file);
    event.target.value = ""; 
}

function extractDataFromText(text) {
    const poRegex = /(?:Purchase Order|Order)\s*(\d{8,15})/i;
    const vendorRegex = /Supplier\s+([A-Za-z0-9\s\.\&\-\,]+?)(?=\n|SAUDI|TRN|P\.O\.|Riyadh|Dubai)/i;
    const storeRegex = /Ship To\s+([A-Za-z0-9\s\.\&\-\,]+?)(?=\n|Riyadh|Dubai|SAUDI)/i;
    const brandRegex = /Sold To\s+([A-Za-z0-9\s\.\&\-\,]+?)(?=\n|Riyadh|Dubai|SAUDI)/i; 
    
    const dnMatch = text.match(/(?:Delivery Note|DN|DO)\s*(?:Number|No|#)?\s*[:\-]?\s*([A-Za-z0-9\-\/]+)/i);
    const invMatch = text.match(/(?:Invoice|Tax Invoice|INV)\s*(?:Number|No|#)?\s*[:\-]?\s*([A-Za-z0-9\-\/]+)/i);

    document.getElementById('pdfPoNumber').value = text.match(poRegex) ? text.match(poRegex)[1].trim() : "TBD";
    document.getElementById('pdfVendor').value = text.match(vendorRegex) ? text.match(vendorRegex)[1].trim() : "Unknown Vendor";
    document.getElementById('pdfTarget').value = text.match(storeRegex) ? text.match(storeRegex)[1].trim() : "Warehouse";
    document.getElementById('pdfBrand').value = text.match(brandRegex) ? text.match(brandRegex)[1].trim() : "N/A";
    document.getElementById('pdfDelNote').value = dnMatch ? dnMatch[1].trim() : "N/A";
    document.getElementById('pdfInvoice').value = invMatch ? invMatch[1].trim() : "N/A";

    const lineItemRegex = /\b\d+\s+([A-Za-z0-9\s\.\-\/\&\+]+?)\s+(\d+(?:\.\d+)?)\s+(?:EA|PCS|SET|NOS)\s+\d{2}-[A-Za-z]{3}-\d{4}\s+([\d,]+(?:\.\d+)?)/gi;
    const fullDescScanner = text.match(/(HP LaserJet.*?|Zebra.*?|MFP[A-Z0-9\-\s]+|Fortinet.*?|Zebra ZD[0-9]+)/ig) || [];

    let itemsHtml = "";
    let match;
    let foundItems = 0;

    while ((match = lineItemRegex.exec(text)) !== null) {
        foundItems++;
        
        let qty = match[1];
        let rawDesc = match[2].trim();
        let price = match[3];
        
        let enrichedDesc = rawDesc;
        let modelNo = "N/A";
        let category = "ICT";
        let hardType = "General Asset";

        if (fullDescScanner.length > 0) {
            const detailedSpec = fullDescScanner.sort((a,b) => b.length - a.length)[0].trim();
            if (
                (detailedSpec.toLowerCase().includes('printer') && rawDesc.toLowerCase().includes('printer')) ||
                (detailedSpec.toLowerCase().includes('switch') && rawDesc.toLowerCase().includes('network'))
            ) {
                enrichedDesc = detailedSpec;
                const modelMatch = enrichedDesc.match(/\b([A-Z]+[0-9]+[A-Z0-9]*)\b/g);
                if (modelMatch) modelNo = modelMatch[modelMatch.length - 1]; 
            }
        }

        let descToAnalyze = (enrichedDesc + " " + rawDesc).toLowerCase();
        if (descToAnalyze.includes('printer') || descToAnalyze.includes('label')) hardType = "Thermal Printers";
        else if (descToAnalyze.includes('network') || descToAnalyze.includes('switch') || descToAnalyze.includes('forti')) { category = "Network"; hardType = "Fortinet / Network"; }
        else if (descToAnalyze.includes('scan') || descToAnalyze.includes('reader')) hardType = "Scanners / Readers";
        else if (descToAnalyze.includes('cam') || descToAnalyze.includes('cctv')) { category = "Security"; hardType = "CCTV / Traffic"; }

        let shortNameArr = enrichedDesc.split(' ');
        let shortName = shortNameArr.length > 4 ? shortNameArr.slice(0, 4).join(' ') : enrichedDesc;

        itemsHtml += `
            <tr>
                <td><input type="text" value="${category}" class="pdf-category"></td>
                <td><input type="text" value="${hardType}" class="pdf-hwtype"></td>
                <td><input type="text" value="${shortName}" class="pdf-name"></td>
                <td><input type="text" value="${rawDesc}" class="pdf-desc"></td>
                <td><input type="text" value="${modelNo}" class="pdf-model"></td>
                <td><input type="text" value="Pending Trace" class="pdf-serial"></td>
                <td><input type="number" value="${qty}" class="pdf-qty"></td>
                <td><input type="number" step="0.01" value="${price}" class="pdf-price"></td>
            </tr>`;
    }

    if (foundItems === 0) {
        itemsHtml = `<tr><td colspan="8" style="text-align:center; color: var(--danger); font-weight: 600; padding: 20px;">Could not auto-detect tabular data. Ensure the PDF matches standard layout.</td></tr>`;
    }

    document.getElementById('pdfParsedItemsBody').innerHTML = itemsHtml;
    document.getElementById('pdfReviewModal').classList.add('active');
}

window.commitParsedPDF = function(e) {
    e.preventDefault();
    const parsedData = {
        po_number: document.getElementById('pdfPoNumber').value,
        vendor: document.getElementById('pdfVendor').value,
        delivery_note: document.getElementById('pdfDelNote').value,
        invoice: document.getElementById('pdfInvoice').value,
        target: document.getElementById('pdfTarget').value,
        brand: document.getElementById('pdfBrand').value,
        items: []
    };

    const rows = document.querySelectorAll('#pdfParsedItemsBody tr');
    rows.forEach(row => {
        if(!row.querySelector('.pdf-qty')) return; 
        parsedData.items.push({
            category: row.querySelector('.pdf-category').value,
            hwType: row.querySelector('.pdf-hwtype').value,
            name: row.querySelector('.pdf-name').value,
            description: row.querySelector('.pdf-desc').value,
            model: row.querySelector('.pdf-model').value,
            serial: row.querySelector('.pdf-serial').value,
            qty: row.querySelector('.pdf-qty').value,
            price: row.querySelector('.pdf-price').value
        });
    });

    closeModal('pdfReviewModal');
    
    document.getElementById('poNumber').value = parsedData.po_number;
    document.getElementById('poDeliveryNote').value = parsedData.delivery_note;
    document.getElementById('poInvoice').value = parsedData.invoice;
    document.getElementById('poAssignedStore').value = parsedData.target;
    document.getElementById('poAssignedBrand').value = parsedData.brand;
    
    let vendorField = document.getElementById('vendorSelect');
    if(vendorField) {
        let matched = false;
        for(let i=0; i<vendorField.options.length; i++) {
            if(vendorField.options[i].value.toLowerCase().includes(parsedData.vendor.toLowerCase().split(' ')[0])) {
                vendorField.selectedIndex = i;
                matched = true;
                break;
            }
        }
    }
    
    document.getElementById('poModal').classList.add('active');
    
    document.getElementById('lineItemsContainer').innerHTML = '';
    parsedData.items.forEach(item => {
        addNewLineItem(item.category, item.hwType, item.name, item.description, item.model, item.serial, item.qty, item.price);
    });
    
    showToast("PDF Data injected into mapped PO Draft.");
}

// ==========================================
// CORE INITIALIZATION & ROUTING
// ==========================================
const originalPortalInit = window.onload || function() {};
window.onload = () => {
    originalPortalInit();
    
    if (currentUser) {
        if (document.getElementById('loginView')) document.getElementById('loginView').style.display = 'none';
        
        if (currentRole === 'Store') {
            if (document.getElementById('appView')) document.getElementById('appView').style.display = 'none';
            if (document.getElementById('storeView')) {
                document.getElementById('storeView').style.display = 'flex';
                if(document.getElementById('storeNameDisplay')) document.getElementById('storeNameDisplay').innerText = currentUser;
                if(document.getElementById('storeRouteDisplay')) document.getElementById('storeRouteDisplay').innerText = currentRoute;
            }
            initApp();
        } else {
            if (document.getElementById('storeView')) document.getElementById('storeView').style.display = 'none';
            if (document.getElementById('appView')) document.getElementById('appView').style.display = 'flex';
            if (document.getElementById('displayUser')) document.getElementById('displayUser').innerText = currentUser;
            initApp();
        }
    } else {
        if (document.getElementById('loginView')) document.getElementById('loginView').style.display = 'flex';
        if (document.getElementById('storeView')) document.getElementById('storeView').style.display = 'none';
        if (document.getElementById('appView')) document.getElementById('appView').style.display = 'none';
    }
};

async function initApp() {
    if (document.getElementById('orderDate')) document.getElementById('orderDate').value = new Date().toISOString().slice(0,10);
    
    await Promise.all([ 
        fetchStores(), fetchVendors(), fetchPersonnel(), fetchAllPOs(), fetchInventory(), 
        fetchDispatchLogs(), fetchAuditLogs(), fetchReverseLogistics(),
        fetchITAssets(), fetchPersonnelAssets(), fetchAssetRecovery()
    ]);

    if (currentRole === 'Store') {
        renderStorePortalInventoryOnly();
        renderStorePortalRequestSession();
    } else {
        patchAdminStoresGridDisplay();
        fetchAdminStoreRequests();
        renderDispatchLog();
        renderStoreStockView();
        
        updateDashboardUI('ALL'); 
    }
}

document.querySelectorAll('.admin-tab, .nav-item').forEach(nav => {
    nav.addEventListener('click', () => {
        document.querySelectorAll('.admin-panel, .card-panel').forEach(p => {
            p.style.display = 'none';
            p.classList.remove('active-panel');
        });
        const tab = nav.getAttribute('data-tab') || nav.getAttribute('data-target');
        const targetPanel = document.getElementById(tab.includes('Panel') ? tab : tab + 'Panel');
        if (targetPanel) { targetPanel.style.display = 'flex'; targetPanel.classList.add('active-panel'); }
        document.querySelectorAll('.admin-tab, .nav-item').forEach(n => n.classList.remove('active'));
        nav.classList.add('active');
        
        if (tab === 'dashboardPanel' || tab === 'dashboard') updateDashboardUI();
        if (tab === 'dispatch') renderDispatchLog();
        if (tab === 'storeRequestsAdmin') fetchAdminStoreRequests();
        if (tab === 'storeStock') renderStoreStockView();
        if (tab === 'audit' || tab === 'auditPanel') fetchAuditLogs();
        if (tab === 'assetRecovery') { 
            renderStoreRecoveryDetails(); 
            renderRecentRecoveries();
        }
    });
});

window.openExportModal = function(tableId, dateColIdx, fileName) {
    document.getElementById('exportTableId').value = tableId;
    document.getElementById('exportDateColIdx').value = dateColIdx;
    document.getElementById('exportFileName').value = fileName;
    document.getElementById('exportStartDate').value = '';
    document.getElementById('exportEndDate').value = '';
    document.getElementById('exportModal').classList.add('active');
}

window.executeDateExport = function(e) {
    e.preventDefault();
    const tableId = document.getElementById('exportTableId').value;
    const dateColIdx = parseInt(document.getElementById('exportDateColIdx').value);
    const fileName = document.getElementById('exportFileName').value;
    const startDateStr = document.getElementById('exportStartDate').value;
    const endDateStr = document.getElementById('exportEndDate').value;

    const table = document.getElementById(tableId);
    if(!table) { closeModal('exportModal'); return showToast("TABLE NOT FOUND."); }

    const headers = Array.from(table.querySelectorAll('thead th')).map(th => {
        let text = "";
        for (let node of th.childNodes) { if (node.nodeType === Node.TEXT_NODE) text += node.textContent; }
        return text.trim();
    });
    
    const actionColIndex = headers.findIndex(h => h.toLowerCase().includes('action'));
    if(actionColIndex > -1) headers.splice(actionColIndex, 1);

    let rows = Array.from(table.querySelectorAll('tbody tr')).filter(r => !r.classList.contains('filtered-out') && r.cells.length > 1);
    if(rows.length === 0) { closeModal('exportModal'); return showToast("NO DATA MATCHES SEARCH."); }

    if ((startDateStr || endDateStr) && !isNaN(dateColIdx) && dateColIdx !== -1) {
        const start = startDateStr ? new Date(startDateStr).setHours(0,0,0,0) : null;
        const end = endDateStr ? new Date(endDateStr).setHours(23,59,59,999) : null;
        rows = rows.filter(row => {
            const cell = row.cells[dateColIdx];
            if (!cell) return false;
            const cellDate = new Date(cell.innerText.trim());
            if (isNaN(cellDate.getTime())) return true; 
            const cellTime = cellDate.getTime();
            if (start && cellTime < start) return false;
            if (end && cellTime > end) return false;
            return true;
        });
    }

    if(rows.length === 0) { closeModal('exportModal'); return showToast("NO DATA IN SELECTED RANGE."); }

    let csvContent = headers.join(',') + '\n';
    rows.forEach(row => {
        const cells = Array.from(row.querySelectorAll('td'));
        let rowData = [];
        cells.forEach((cell, index) => {
            if (index !== actionColIndex) {
                let text = cell.innerText.trim().replace(/"/g, '""');
                rowData.push(`"${text}"`);
            }
        });
        csvContent += rowData.join(',') + '\n';
    });

    const link = document.createElement("a");
    link.setAttribute("href", "data:text/csv;charset=utf-8," + encodeURIComponent(csvContent));
    link.setAttribute("download", `${fileName}_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    closeModal('exportModal');
    showToast("EXPORT SUCCESSFUL.");
}

// ==========================================
// PAGINATION & FILTERS
// ==========================================
function initPagination(tableId) {
    if (!tableStates[tableId]) tableStates[tableId] = { currentPage: 1 };
    applyPagination(tableId);
}

window.changePage = function(tableId, delta) {
    const state = tableStates[tableId];
    if(!state) return;
    state.currentPage += delta;
    applyPagination(tableId);
}

function applyPagination(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.classList.contains('filtered-out'));
    const container = document.getElementById('page-' + tableId);

    const totalPages = Math.ceil(rows.length / ROWS_PER_PAGE) || 1;
    let state = tableStates[tableId];
    if (state.currentPage > totalPages) state.currentPage = totalPages;
    if (state.currentPage < 1) state.currentPage = 1;

    const startIdx = (state.currentPage - 1) * ROWS_PER_PAGE;
    const endIdx = startIdx + ROWS_PER_PAGE;

    Array.from(tbody.querySelectorAll('tr')).forEach(r => r.style.display = 'none');
    rows.slice(startIdx, endIdx).forEach(r => r.style.display = '');

    if(container) {
        container.innerHTML = `
            <span>Showing ${rows.length === 0 ? 0 : startIdx + 1} to ${Math.min(endIdx, rows.length)} of ${rows.length} entries</span>
            <div class="pagination-controls">
                <button type="button" onclick="changePage('${tableId}', -1)" ${state.currentPage === 1 ? 'disabled' : ''}>Prev</button>
                <button type="button" onclick="changePage('${tableId}', 1)" ${state.currentPage === totalPages ? 'disabled' : ''}>Next</button>
            </div>
        `;
    }
}

window.filterTable = function(inputElement) {
    const table = inputElement.closest('table');
    const tbody = table.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    const headers = table.querySelectorAll('th');
    
    const filterParams = Array.from(headers).map(th => {
        const filterInput = th.querySelector('.column-filter');
        return filterInput ? filterInput.value.toLowerCase().trim() : '';
    });

    rows.forEach(row => {
        if (row.cells.length <= 1) return; 
        let isRowVisible = true;
        filterParams.forEach((filterText, colIndex) => {
            if (filterText && row.cells[colIndex]) {
                const cellText = row.cells[colIndex].textContent.toLowerCase();
                if (!cellText.includes(filterText)) isRowVisible = false;
            }
        });
        
        if (isRowVisible) row.classList.remove('filtered-out');
        else row.classList.add('filtered-out');
    });

    tableStates[table.id] = { currentPage: 1 };
    applyPagination(table.id);
}

// ==========================================
// DATA FETCHERS
// ==========================================
async function fetchStores() { try { const res = await fetch('procurement_api.php?action=list_stores'); const data = await res.json(); if(Array.isArray(data)) { retailStores = data; populateStoreSelects(); } } catch(e) {} }
async function fetchPersonnel() { try { const res = await fetch('procurement_api.php?action=list_personnel'); const data = await res.json(); if(Array.isArray(data)) { personnelList = data; renderPersonnelList(); populatePersonSelects(); } } catch(e) {} }
async function fetchVendors() { try { const res = await fetch('procurement_api.php?action=list_vendors'); const data = await res.json(); if(Array.isArray(data)) { approvedVendors = data; renderVendorList(); } } catch(e) {} }
async function fetchITAssets() { try { const res = await fetch('procurement_api.php?action=list_it_assets'); const data = await res.json(); if(Array.isArray(data)) { masterITAssets = data; renderITAssetsTable(); } } catch(e) {} }
async function fetchAllPOs() { try { const res = await fetch('procurement_api.php?action=list_it_pos'); const data = await res.json(); if(Array.isArray(data)) { purchaseOrders = data; renderPOList(); } } catch(e) {} }
async function fetchInventory() { try { const res = await fetch('procurement_api.php?action=list_it_inventory'); const data = await res.json(); if(Array.isArray(data)) { masterInventory = data; renderInventoryList(); } } catch(e) {} }
async function fetchDispatchLogs() { try { const res = await fetch('procurement_api.php?action=list_it_dispatch'); const data = await res.json(); if(Array.isArray(data)) { dispatchLogs = data; } } catch(e) {} }
async function fetchAssetRecovery() { try { const res = await fetch('procurement_api.php?action=list_it_asset_recovery'); const data = await res.json(); if(Array.isArray(data)) { assetRecoveryLog = data; renderStoreRecoveryDetails(); renderRecentRecoveries(); } } catch(e) {} }

async function fetchAuditLogs() { 
    try { 
        let combinedLogs = [];
        try {
            const resStandard = await fetch('procurement_api.php?action=list_audit_logs');
            const textStandard = await resStandard.text();
            if (textStandard && textStandard.trim() !== '') {
                try {
                    const parsed = JSON.parse(textStandard);
                    if (Array.isArray(parsed)) combinedLogs.push(...parsed);
                } catch (e) {}
            }
        } catch(e) {}

        try {
            const resIT = await fetch('procurement_api.php?action=list_it_audit_logs'); 
            const textIT = await resIT.text();
            if (textIT && textIT.trim() !== '') {
                try {
                    const parsed = JSON.parse(textIT);
                    if (Array.isArray(parsed)) combinedLogs.push(...parsed);
                } catch (e) {}
            }
        } catch(e) {}

        combinedLogs.sort((a, b) => new Date(b.timestamp || 0) - new Date(a.timestamp || 0));
        globalAuditLogs = combinedLogs; 
        
        const tbody = document.getElementById('auditTableBody'); 
        if(tbody) {
            if (combinedLogs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">NO AUDIT RECORDS FOUND.</td></tr>';
            } else {
                tbody.innerHTML = combinedLogs.map(log => {
                    const rawDate = log.timestamp || Date.now();
                    const action = (log.action_type || 'UNKNOWN').toUpperCase();
                    return `<tr>
                        <td>${new Date(rawDate).toLocaleString('en-GB', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'})}</td>
                        <td><strong>${log.username || 'System'}</strong></td>
                        <td><span class="status-badge status-dispatched">${action}</span></td>
                        <td>${log.details || '-'}</td>
                    </tr>`;
                }).join(''); 
            }
            initPagination('tbl-audit');
        }
    } catch(e) { } 
}

async function fetchReverseLogistics() { 
    try { 
        const res = await fetch('procurement_api.php?action=list_reverse_logistics'); 
        if(res.ok) { const data = await res.json(); if(Array.isArray(data)) { reverseLogisticsLogs = data; } }
    } catch(e) {} 
}

async function fetchPersonnelAssets() { 
    try { 
        const res = await fetch('procurement_api.php?action=list_personnel_assets'); 
        if (!res.ok) return;
        const data = await res.json(); 
        if(Array.isArray(data)) { masterPersonnelAssets = data; }
    } catch(e) {} 
}

// ==========================================
// RENDERERS
// ==========================================

function renderITAssetsTable() {
    const tbody = document.getElementById('itStoreAssetsTableBody');
    if(!tbody) return;

    if (!masterITAssets || masterITAssets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">NO ASSETS LOADED.</td></tr>';
        return;
    }

    tbody.innerHTML = masterITAssets.map(asset => `
        <tr>
            <td><strong>${asset.hostname || 'N/A'}</strong></td>
            <td>${asset.brand_name || '-'}<br><span style="font-size:10px; color:#666;">${asset.store_code || '-'}</span></td>
            <td>${asset.location || '-'}<br><span style="font-size:10px; color:#666;">${asset.route_code || '-'}</span></td>
            <td><strong>${asset.device_type || '-'}</strong></td>
            <td style="font-family: monospace;">${asset.serial_number || '-'}</td>
            <td>${asset.os || ''} ${asset.os_version || ''}<br><span style="font-size:10px; color:#666;">${asset.memory || ''}</span></td>
        </tr>
    `).join('');
    
    initPagination('tbl-it-assets-store');
}

function patchAdminStoresGridDisplay() {
    const tbody = document.getElementById('storeTableBody');
    if(!tbody || !Array.isArray(retailStores)) return;
    tbody.innerHTML = retailStores.map(s => `<tr><td><strong>${s.id}</strong></td><td>${s.name}</td><td>${s.brand}</td><td>${s.mall}</td><td>${s.city} / Route: ${s.route_code}</td></tr>`).join('');
    initPagination('tbl-stores');
}

window.renderInventoryList = function() {
    const tbody = document.getElementById('inventoryTableBody');
    if(!tbody) return;
    
    let unifiedInventory = [];

    // 1. WAREHOUSE STOCK (masterInventory)
    masterInventory.forEach(item => {
        unifiedInventory.push({
            sku: item.sku || 'N/A',
            category: item.category || 'General',
            hardware: item.hardware_type || '-',
            name: item.item_name || item.name || 'Unknown',
            qty: parseInt(item.quantity_on_hand || item.qty || 0),
            condition: item.condition || 'New',
            context: 'WAREHOUSE',
            badge: 'status-approved',
            qtyColor: parseInt(item.quantity_on_hand || 0) > 0 ? 'var(--success)' : 'var(--danger)'
        });
    });

    // 2. PERSONNEL ASSETS (Mapped to your `person` table schema)
    masterPersonnelAssets.forEach(person => {
        unifiedInventory.push({
            sku: person.serial_number || 'N/A',
            category: 'Deployed (Staff)',
            hardware: person.device_type || '-',
            name: person.model_name || person.hostname || 'Personnel Device',
            qty: 1, 
            condition: 'Deployed',
            context: `STAFF: ${person.emp_name} (${person.emp_id})`,
            badge: 'status-dispatched',
            qtyColor: '#333'
        });
    });

    // 3. STORE ASSETS (Mapped to your `it_assets` table schema)
    masterITAssets.forEach(asset => {
        unifiedInventory.push({
            sku: asset.serial_number || 'N/A',
            category: 'Deployed (Node)',
            hardware: asset.device_type || '-',
            name: asset.model_name || asset.hostname || 'Store Asset',
            qty: 1, 
            condition: 'Deployed',
            context: `NODE: ${asset.store_code} ${asset.store_name ? '('+asset.store_name+')' : ''}`,
            badge: 'status-dispatched',
            qtyColor: '#333'
        });
    });

    // 4. THE MAGIC BULLET - RECOVERED ASSETS INJECTION
    assetRecoveryLog.forEach(log => {
        const actionStr = String(log.action_type || log.action || '').toLowerCase();
        
        if (actionStr === 'reuse') {
            unifiedInventory.push({
                sku: log.serialNo || log.serial_number || log.sku || 'N/A',
                category: log.category || 'ICT',
                hardware: log.hwType || log.hardware_type || '-',
                name: log.itemName || log.device_issued || 'Recovered Item',
                qty: parseInt(log.qty || 1),
                condition: 'Old',
                context: 'WAREHOUSE (Recovered)',
                badge: 'status-approved',
                qtyColor: 'var(--success)'
            });
        } else if (actionStr === 'write off' || actionStr === 'writeoff') {
            unifiedInventory.push({
                sku: log.serialNo || log.serial_number || log.sku || 'N/A',
                category: log.category || 'E-WASTE',
                hardware: log.hwType || log.hardware_type || '-',
                name: log.itemName || log.device_issued || 'Written Off Item',
                qty: parseInt(log.qty || 1),
                condition: 'Write-Off',
                context: 'NODE: 97427 (Disposal)',
                badge: 'status-pending',
                qtyColor: 'var(--danger)'
            });
        } else if (log.status === 'Partial') {
            // For in-transit elements matching the older table structure
            unifiedInventory.push({
                sku: log.serial_number || log.sku || 'N/A',
                category: log.category || 'Recovery',
                hardware: log.hardware_type || '-',
                name: log.device_issued || 'Recovered Item',
                qty: parseInt(log.qty || 1),
                condition: 'In-Transit',
                context: `IN TRANSIT (FROM: ${log.origin_store})`,
                badge: 'status-pending',
                qtyColor: 'var(--warning)'
            });
        }
    });

    // Render the Unified Table
    tbody.innerHTML = unifiedInventory.map(item => {
        let conditionBadge = item.condition === 'Old' ? 'status-partial' : 'status-complete';
        if(item.condition === 'Deployed' || item.condition === 'In-Transit') conditionBadge = 'status-pending';
        if(item.condition === 'Write-Off') conditionBadge = 'status-pending';

        return `
            <tr>
                <td style="font-family:monospace; font-weight:600;">${item.sku}</td>
                <td>${item.category}</td>
                <td><strong>${item.hardware}</strong></td>
                <td>${item.name}</td>
                <td><strong style="color: ${item.qtyColor};">${item.qty} UNITS</strong></td>
                <td><span class="status-badge ${conditionBadge}">${item.condition}</span></td>
                <td><span class="status-badge ${item.badge}">${item.context}</span></td>
            </tr>
        `
    }).join('');
    
    initPagination('tbl-inv');
    
    // Update the Dashboard Top Counters
    if(document.getElementById('inv-total-skus')) document.getElementById('inv-total-skus').innerText = unifiedInventory.length;
    if(document.getElementById('inv-total-units')) document.getElementById('inv-total-units').innerText = unifiedInventory.reduce((acc, curr) => acc + curr.qty, 0);
    if(document.getElementById('inv-low-stock')) document.getElementById('inv-low-stock').innerText = masterInventory.filter(i => parseInt(i.quantity_on_hand || 0) < 5).length;
};

function renderStorePortalInventoryOnly() {
    const inventoryTbody = document.getElementById('storeInventoryTableBody');
    if(!inventoryTbody) return;
    const myAllocatedDispatches = dispatchLogs.filter(log => String(log.store_code) === String(currentUser));
    if(myAllocatedDispatches.length === 0) {
        inventoryTbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:40px;">NO PRODUCTS ASSIGNED.</td></tr>`;
        return;
    }
    inventoryTbody.innerHTML = myAllocatedDispatches.map(log => {
        const invRef = masterInventory.find(i => i.sku === log.sku) || {};
        return `<tr><td><strong>${log.dispatch_id || log.id}</strong></td><td style="font-family:monospace;">${log.sku}</td><td>${log.item_name || invRef.item_name || 'Classified Stock Item'}</td><td><span class="status-badge status-dispatched">${log.category || invRef.category || 'General'}</span></td><td><strong style="color:var(--success);">${log.dispatch_qty || log.qty} UNITS</strong></td><td><button onclick="viewDispatchDetails('${log.dispatch_id || log.id}')" class="btn-cancel"><i class="fas fa-eye"></i> VIEW</button></td></tr>`;
    }).join('');
    initPagination('tbl-store-inv');
}

function renderStorePortalRequestSession() {
    const catalogTbody = document.getElementById('storeCatalogTableBody');
    if(!catalogTbody || !Array.isArray(masterInventory)) return;
    catalogTbody.innerHTML = masterInventory.map(item => {
        const isConsumable = (item.item_name || '').toLowerCase().match(/label|ribbon|paper|roll|tape/);
        const typeBadge = isConsumable ? `<span class="status-badge status-partial">CONSUMABLE</span>` : `<span class="status-badge status-complete">ASSET</span>`;
        return `<tr><td style="font-family:monospace;">${item.sku}</td><td><strong>${item.item_name}</strong></td><td>${typeBadge}</td><td><strong>${item.quantity_on_hand || 0} UNITS</strong></td><td><input type="number" min="0" max="${item.quantity_on_hand || 0}" class="store-portal-req-input" data-sku="${item.sku}" data-name="${item.item_name}" placeholder="0" style="width:70px;"></td></tr>`;
    }).join('');
    initPagination('tbl-store-req');
    renderStorePortalRequestHistory();
}

async function renderStorePortalRequestHistory() {
    const statusPanelWrapper = document.getElementById('storePortalRequestStatusContainer');
    if(!statusPanelWrapper) return;
    try {
        const res = await fetch(`procurement_api.php?action=list_store_requests`);
        const allRequests = await res.json();
        const myRequests = allRequests.filter(req => String(req.username) === String(currentUser));
        if(myRequests.length === 0) {
            statusPanelWrapper.innerHTML = `<p style="padding: 10px;">NO HISTORICAL REQUESTS.</p>`;
            return;
        }
        statusPanelWrapper.innerHTML = myRequests.map(req => {
            let itemLines = []; try { itemLines = JSON.parse(req.details || '[]'); } catch(e) {}
            const summaryString = itemLines.map(i => `• ${i.qty}x [${i.sku}] ${i.name}`).join('<br>');
            const currentStatus = req.status || 'Pending';
            let badgeHtml = `<span class="status-badge status-pending">AWAITING APPROVAL</span>`;
            if (currentStatus === 'Approved') badgeHtml = `<span class="status-badge status-complete">APPROVED</span>`;
            if (currentStatus === 'Rejected') badgeHtml = `<span class="status-badge status-pending">REJECTED</span>`;
            return `<div style="background:#fff; border:1px solid #cbd5e1; padding:15px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; border-radius: 4px;"><div><span style="font-size:11px; color:#64748b; font-weight: 600;"><i class="far fa-clock"></i> ${new Date(req.timestamp).toLocaleString()}</span><p style="margin-top:6px; font-weight: 500;">${summaryString}</p></div><div>${badgeHtml}</div></div>`;
        }).join('');
    } catch(err) {}
}

window.submitStoreRequest = async function() {
    const inputNodes = document.querySelectorAll('.store-portal-req-input');
    const cargoPayload = [];
    inputNodes.forEach(input => {
        const demandQuantity = parseInt(input.value || 0);
        if(demandQuantity > 0) cargoPayload.push({ sku: input.getAttribute('data-sku'), name: input.getAttribute('data-name'), qty: demandQuantity });
    });
    if(cargoPayload.length === 0) return showToast("SPECIFY QTY BEFORE SUBMITTING.");

    try {
        const res = await fetch('procurement_api.php?action=request_inventory', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ storeId: currentUser, items: cargoPayload }) });
        const data = await res.json();
        if(data.status === 'success') { showToast('REQUEST SENT.'); inputNodes.forEach(i => i.value = ''); renderStorePortalRequestHistory(); }
    } catch(err) { showToast("GATEWAY TIMEOUT."); }
}

async function fetchAdminStoreRequests() {
    const adminPanel = document.getElementById('storeRequestsAdminPanel');
    if (!adminPanel) return;

    try {
        const res = await fetch('procurement_api.php?action=list_store_requests');
        const data = await res.json();
        if (Array.isArray(data)) {
            adminStoreRequests = data;
            
            const pendingRequests = data.filter(r => !r.status || r.status === 'Pending');
            const processedRequests = data.filter(r => r.status === 'Approved' || r.status === 'Rejected');

            const tbodyPending = document.getElementById('adminStoreRequestsTableBody');
            if (tbodyPending) {
                if (pendingRequests.length === 0) {
                    tbodyPending.innerHTML = `<tr><td colspan="4" style="padding:40px; text-align:center; color: #64748b;">NO PENDING REQUESTS.</td></tr>`;
                } else {
                    tbodyPending.innerHTML = pendingRequests.map(req => {
                        let parsedItems = []; try { parsedItems = JSON.parse(req.details || '[]'); } catch(e) {}
                        const itemsSummaryList = parsedItems.map(i => `<span style="display:inline-block; background:#f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; padding:4px 8px; font-size:11px; margin:2px;"><b>${i.qty}x</b> ${i.sku}</span>`).join(' ');
                        return `<tr><td>${new Date(req.timestamp).toLocaleString()}</td><td><strong>${req.username}</strong></td><td style="white-space: normal; min-width: 250px;">${itemsSummaryList}</td><td style="display:flex; gap:8px;"><button onclick="approveStoreDemand(${req.id}, '${req.username}', \`${req.details.replace(/"/g, '&quot;')}\`)" class="btn-create" style="padding:4px 10px; font-size: 11px;"><i class="fas fa-check"></i> APPROVE</button><button onclick="rejectStoreDemand(${req.id}, '${req.username}')" class="btn-cancel" style="padding:4px 10px; font-size: 11px; border-color:var(--danger); color:var(--danger);"><i class="fas fa-times"></i> REJECT</button></td></tr>`;
                    }).join('');
                }
                initPagination('tbl-req-pending');
            }

            const tbodyHistory = document.getElementById('adminProcessedRequestsTableBody');
            if (tbodyHistory) {
                if (processedRequests.length === 0) {
                    tbodyHistory.innerHTML = `<tr><td colspan="4" style="padding:40px; text-align:center; color: #64748b;">NO HISTORICAL REQUESTS.</td></tr>`;
                } else {
                    tbodyHistory.innerHTML = processedRequests.map(req => {
                        let parsedItems = []; try { parsedItems = JSON.parse(req.details || '[]'); } catch(e) {}
                        const itemsSummaryList = parsedItems.map(i => `<span style="display:inline-block; background:#f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; padding:4px 8px; font-size:11px; margin:2px;"><b>${i.qty}x</b> ${i.sku}</span>`).join(' ');
                        const badge = req.status === 'Approved' ? `<span class="status-badge status-complete">APPROVED</span>` : `<span class="status-badge status-pending">REJECTED</span>`;
                        return `<tr><td>${new Date(req.timestamp).toLocaleString()}</td><td><strong>${req.username}</strong></td><td style="white-space: normal; min-width: 250px;">${itemsSummaryList}</td><td>${badge}</td></tr>`;
                    }).join('');
                }
                initPagination('tbl-req-history');
            }
        }
    } catch(e) {}
}

window.rejectStoreDemand = async function(requestId, storeId) {
    if(!confirm(`REJECT REQUEST FROM STORE [${storeId}]?`)) return;
    try {
        const res = await fetch('procurement_api.php?action=reject_store_request', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ requestId, storeId, username: currentUser }) });
        const data = await res.json();
        if(data.status === 'success') { showToast('REQUEST REJECTED.'); fetchAdminStoreRequests(); }
    } catch(err) { showToast('GATEWAY ERROR.'); }
}

window.approveStoreDemand = async function(requestId, storeId, itemsArray) {
    if (typeof itemsArray === 'string') { try { itemsArray = JSON.parse(itemsArray); } catch(e) { itemsArray = []; } }
    if (!confirm(`CONSTRUCT DISPATCH FOR STORE: [${storeId}]?`)) return;

    const payload = { dispatchId: generateDispatchID(), storeCode: storeId, pName: "N/A", pId: "N/A", pDept: "N/A", username: currentUser, requestId: requestId, items: itemsArray.map(i => ({ sku: i.sku, qty: i.qty })) };

    try {
        const res = await fetch('procurement_api.php?action=approve_store_request', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('STORE REQUEST APPROVED.');
            await initApp(); 
        } else { showToast("DB ERROR: " + data.message); }
    } catch(err) { showToast('GATEWAY ERROR.'); }
}

function renderVendorList() {
    const tbody = document.getElementById('vendorTableBody');
    if(tbody) {
        tbody.innerHTML = approvedVendors.map(v => `<tr><td><strong>${v.company_name || v.company}</strong></td><td>${v.tax_id || v.taxId || '-'}</td><td>${v.payment_terms || v.terms || '-'}</td><td>${v.lead_time || v.lead || '-'}</td><td>${v.contact_email || v.email || '-'}</td><td><span class="status-badge status-complete">APPROVED</span></td><td class="action-icons"><i class="fas fa-trash-alt" onclick="removeVendor('${v.id}', '${v.company_name || v.company}')"></i></td></tr>`).join('');
        initPagination('tbl-vendors');
    }
    const vSelect = document.getElementById('vendorSelect');
    if(vSelect) vSelect.innerHTML = '<option value="">-- SELECT VENDOR --</option>' + approvedVendors.map(v => `<option value="${v.company_name || v.company}">${v.company_name || v.company}</option>`).join('');
}

function renderPersonnelList() {
    const tbody = document.getElementById('userTableBody');
    if(tbody) { 
        tbody.innerHTML = personnelList.map(u => `<tr><td><strong>${u.emp_id}</strong></td><td><strong>${u.emp_name}</strong></td><td>${u.department || u.brand_dept || '-'}</td><td class="action-icons"><i class="fas fa-trash-alt" onclick="removePersonnel('${u.emp_id}')"></i></td></tr>`).join(''); 
        initPagination('tbl-users');
    }
}

function renderPOList() {
    const tbody = document.getElementById('poTableBody');
    if(!tbody) return;
    tbody.innerHTML = purchaseOrders.map(po => {
        let statusClass = po.status === 'Completed' ? 'status-complete' : (po.status === 'Partial' ? 'status-partial' : 'status-approved');
        const firstItem = po.lineItems && po.lineItems.length > 0 ? po.lineItems[0] : {};
        return `<tr>
            <td><strong>${po.po_number}</strong></td>
            <td>${po.vendor}</td>
            <td>${po.delivery_note_number || '-'}</td>
            <td>${firstItem.category || '-'}</td>
            <td>${firstItem.modelNumber || '-'}</td>
            <td>${firstItem.itemName || '-'}</td>
            <td><strong>SAR ${Number(po.grand_total || 0).toFixed(2)}</strong></td>
            <td><span class="status-badge ${statusClass}">${po.status}</span></td>
            <td>${po.order_date}</td>
            <td style="display:flex; gap:8px;">
                <button onclick="openPOTracker('${po.po_number}')" class="btn-create" style="padding:4px 10px; font-size:11px;">TRACK</button>
                <button onclick="openEditPOModal('${po.id}')" class="btn-cancel" style="padding:4px 10px; font-size:11px;"><i class="fas fa-edit"></i> EDIT</button>
            </td>
        </tr>`;
    }).join('');
    initPagination('tbl-po');
}

window.openPOTracker = function(poNumber) {
    const poRecords = purchaseOrders.filter(p => p.po_number === poNumber || p.poNumber === poNumber);
    if(poRecords.length === 0) return showToast("PO Not Found");
    
    const trackerTbody = document.getElementById('poTrackerTableBody');
    let html = '';
    
    const mainPo = poRecords[0];
    mainPo.lineItems.forEach(item => {
        const dispatchMatch = dispatchLogs.filter(d => d.sku === item.sku);
        let assignedTo = '<span class="status-badge status-partial">IN WAREHOUSE</span>';
        if (dispatchMatch.length > 0) {
            assignedTo = dispatchMatch.map(d => `<span class="status-badge status-dispatched"><i class="fas fa-truck"></i> ${d.store_code}</span> <small style="font-weight: 600;">${new Date(d.dispatched_at).toLocaleDateString()}</small>`).join('<br>');
        }
        
        html += `<tr>
            <td><strong>${item.hardware_type || 'Asset'}</strong><br><small style="color: #64748b;">${item.category}</small></td>
            <td><strong>${item.itemName}</strong><br><small style="color: #64748b;">${item.description}</small></td>
            <td style="font-family: monospace;">${item.sku}</td>
            <td><strong style="color: var(--success);">${item.quantity} UNITS</strong></td>
            <td>${assignedTo}</td>
        </tr>`;
    });
    
    document.getElementById('trackerPoTitle').innerText = 'TRACKING: ' + poNumber;
    document.getElementById('btnExportTracker').onclick = () => window.exportPOTracker(poNumber);
    trackerTbody.innerHTML = html;
    document.getElementById('poTrackerModal').classList.add('active');
};

window.exportPOTracker = function(poNumber) {
    openExportModal('tbl-po-tracker', -1, `Lifecycle_Tracker_${poNumber}`);
};

function renderStoreStockView() {
    const storeInput = document.getElementById('stockStoreInput');
    if (!storeInput) return;
    const match = storeInput.value.match(/\[(\d+)\]/);
    const storeId = match ? match[1] : storeInput.value;

    const tbody = document.getElementById('storeStockTableBody');
    if(!tbody) return;
    if(!storeId) { tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:40px; color: #64748b;">Select a target node to compile stock matrix.</td></tr>`; return; }

    const storeLogs = dispatchLogs.filter(log => String(log.store_code) === String(storeId));
    const stockMap = {};
    storeLogs.forEach(log => {
        if(!stockMap[log.sku]) stockMap[log.sku] = { name: log.item_name || 'Stock Cargo', qty: 0, lastDate: log.dispatched_at };
        stockMap[log.sku].qty += parseInt(log.dispatch_qty || log.qty || 0);
        if(new Date(log.dispatched_at) > new Date(stockMap[log.sku].lastDate)) stockMap[log.sku].lastDate = log.dispatched_at;
    });

    if(Object.keys(stockMap).length === 0) { tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:40px; color: #64748b;">NO RECORDED STOCK FOR THIS NODE.</td></tr>`; return; }

    tbody.innerHTML = Object.entries(stockMap).map(([sku, data]) => `<tr><td style="font-family:monospace; font-weight: 600;">${sku}</td><td>${data.name}</td><td><strong style="color:var(--success);">${data.qty} UNITS</strong></td><td>${data.lastDate ? new Date(data.lastDate).toLocaleDateString() : 'N/A'}</td></tr>`).join('');
    initPagination('tbl-store-stock');
}

function renderDispatchLog() {
    const tbody = document.getElementById('dispatchTableBody');
    if (!tbody) return;
    const uniqueDispatches = []; const seenIds = new Set(); let dispatchTotals = {}; let dispatchItemCounts = {};

    dispatchLogs.forEach(log => {
        const id = log.dispatch_id || log.id;
        if (!dispatchTotals[id]) { dispatchTotals[id] = 0; dispatchItemCounts[id] = new Set(); }
        dispatchTotals[id] += parseInt(log.dispatch_qty || log.qty || 0);
        dispatchItemCounts[id].add(log.sku);
        if (!seenIds.has(id)) { seenIds.add(id); uniqueDispatches.push(log); }
    });

    if (uniqueDispatches.length === 0) { tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px; color: #64748b;">NO DISPATCH MANIFESTS LOGGED.</td></tr>`; return; }

    tbody.innerHTML = uniqueDispatches.map(log => `<tr>
        <td><strong>${log.dispatch_id || log.id}</strong></td>
        <td><span class="status-badge status-dispatched">${log.store_code}</span></td>
        <td><strong>${dispatchItemCounts[log.dispatch_id || log.id].size} ITEMS</strong> <span style="color: #64748b;">(${dispatchTotals[log.dispatch_id || log.id]} UNITS)</span></td>
        <td>${new Date(log.dispatched_at).toLocaleDateString()}</td>
        <td><button class="btn-cancel" style="padding: 4px 10px;" onclick="viewDispatchDetails('${log.dispatch_id || log.id}')"><i class="fas fa-eye"></i> View</button></td>
    </tr>`).join('');
    initPagination('tbl-dispatch');
    
    if(document.getElementById('disp-total')) document.getElementById('disp-total').innerText = uniqueDispatches.length;
}

window.viewDispatchDetails = function(dispatchId) { 
    document.getElementById('detailDispatchId').innerText = dispatchId; 
    const tbody = document.getElementById('dispatchDetailsTableBody'); 
    const items = dispatchLogs.filter(log => (log.dispatch_id || log.id) === dispatchId); 

    if(items.length > 0) {
        const first = items[0];

        if (first.store_code && first.store_code !== "N/A") {
            let storeSearchCode = String(first.store_code).replace(/store id:?/i, '').trim().toLowerCase();
            let storeMatch = retailStores.find(s => {
                if (!s) return false;
                const sId = String(s.id || s.store_id || '').trim().toLowerCase();
                const sCode = String(s.brand_code || s.code || '').trim().toLowerCase();
                const sName = String(s.name || s.store_name || '').trim().toLowerCase();
                return (sId === storeSearchCode && sId !== '') || 
                       (sCode === storeSearchCode && sCode !== '') || 
                       (sName === storeSearchCode && sName !== '');
            });

            if (!storeMatch) {
                storeMatch = retailStores.find(s => 
                    String(s.name).trim().toLowerCase().includes(storeSearchCode) || 
                    storeSearchCode.includes(String(s.id).trim().toLowerCase())
                );
            }

            if (storeMatch) {
                document.getElementById('detailRecipient').innerHTML = `<strong>${storeMatch.name} [ID: ${storeMatch.id}]</strong><br><strong>BRAND:</strong> ${storeMatch.brand || '-'} (${storeMatch.brand_code || '-'})<br><strong>ENTITY:</strong> ${storeMatch.entity || '-'} / ${storeMatch.mall || '-'}<br><strong>LOC:</strong> ${storeMatch.city || '-'} | <strong>ROUTE:</strong> ${storeMatch.route_code || 'UNASSIGNED'}`;
            } else { 
                const fallbackDisplay = first.store_name ? first.store_name : (first.store_code.includes('ID') ? first.store_code : `STORE ID: ${first.store_code}`);
                document.getElementById('detailRecipient').innerHTML = `<strong>${fallbackDisplay}</strong><br><em style="font-size: 11px; color: #666;">*Store details pending sync*</em>`; 
            }
        } else if (first.person_name && first.person_name !== "N/A") {
            document.getElementById('detailRecipient').innerHTML = `<strong>PERSONNEL: ${first.person_name}</strong><br><strong>EMP ID:</strong> ${first.p_id || 'N/A'}<br><strong>DEPT:</strong> ${first.p_dept || 'N/A'}`;
        } else {
            document.getElementById('detailRecipient').innerHTML = `<strong>UNASSIGNED / INTERNAL WAREHOUSE</strong>`;
        }

        document.getElementById('detailDate').innerText = first.dispatched_at ? new Date(first.dispatched_at).toLocaleString() : 'RECENT';
    }

    tbody.innerHTML = items.map((item, index) => {
        const masterItem = masterInventory.find(mi => mi.sku === item.sku);
        const vendorDisplay = masterItem && masterItem.vendor ? masterItem.vendor : '-';
        return `<tr>
            <td style="vertical-align: top;">${index + 1}</td>
            <td style="vertical-align: top; word-break: break-all;"><span style="font-family:monospace; font-weight:800;">${item.sku}</span></td>
            <td style="vertical-align: top;"><strong>${item.item_name || (masterItem ? masterItem.item_name : 'Unknown Item')}</strong><br><span style="font-size: 11px; color: #666;">Vendor: ${vendorDisplay}</span></td>
            <td style="vertical-align: top; text-align: center;"><strong>${item.dispatch_qty || item.qty}</strong></td>
        </tr>`;
    }).join(''); 

    document.getElementById('printReceiptBtn').onclick = () => printDispatchManifest(dispatchId); 
    document.getElementById('dispatchDetailsModal').classList.add('active'); 
}

window.printDispatchManifest = function(dispatchId) {
    const content = document.getElementById('receiptContent').innerHTML;
    const printWin = window.open('', '', 'width=600,height=800');
    printWin.document.write(`<html><head><style>body { font-family: monospace; padding: 20px; color: #000; background: #fff; } table { width: 100%; border-collapse: collapse; margin-top: 20px; } th, td { border: 1px solid #000; padding: 10px; text-align: left; } h2 { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; }</style></head><body>${content}<script>window.print();<\/script></body></html>`);
    printWin.document.close();
}

window.generateDispatchID = function() {
    if (!dispatchLogs || dispatchLogs.length === 0) return 'DSP-0002';
    let maxVal = 1; 
    dispatchLogs.forEach(log => {
        const idStr = log.dispatch_id || log.id;
        if (idStr && idStr.startsWith('DSP-')) {
            const num = parseInt(idStr.replace('DSP-', ''), 10);
            if (!isNaN(num) && num > maxVal) maxVal = num;
        }
    });
    return 'DSP-' + String(maxVal + 1).padStart(4, '0');
}

window.openDispatchFlow = function() { dispatchCart = []; document.getElementById('dispatchFlowModal').classList.add('active'); goToDispatchStep1(); }
window.toggleAssigneeType = function() { const isStore = document.querySelector('input[name="assigneeType"]:checked').value === 'store'; document.getElementById('storeFields').style.display = isStore ? 'block' : 'none'; document.getElementById('personFields').style.display = !isStore ? 'block' : 'none'; }

function updateFields(store) { document.getElementById('dfStoreEntity').value = (store.mall || '') + " / " + (store.entity || ''); document.getElementById('dfStoreRoute').value = store.route_code || 'UNASSIGNED'; }
function clearStoreFields() { document.getElementById('dfStoreEntity').value = ''; document.getElementById('dfStoreRoute').value = ''; }
window.autofillFromId = function() { const id = document.getElementById('dfStoreId').value.trim(); const store = retailStores.find(s => String(s.id) === String(id)); if(store) { updateFields(store); } else { clearStoreFields(); } }
window.autofillFromDatalist = function(inputId, targetIdField) { const val = document.getElementById(inputId).value; const match = val.match(/\[(\d+)\]/); if(match) { document.getElementById(targetIdField).value = match[1]; autofillFromId(); } else { document.getElementById(targetIdField).value = ''; clearStoreFields(); } }

function populateStoreSelects() { 
    const filterSelect = document.getElementById('dashboardStoreFilter');
    if (filterSelect) {
        let optionsHtml = '<option value="ALL">All Retail Nodes (Global)</option>';
        optionsHtml += retailStores.map(s => `<option value="${s.id}">${s.name} [${s.id}]</option>`).join('');
        filterSelect.innerHTML = optionsHtml;
    }

    const storesHtml = '<option value="">-- CHOOSE TARGET --</option>' + retailStores.map(s => `<option value="${s.id}">${s.name} [${s.id}]</option>`).join('');
    if(document.getElementById('storeDatalist')) {
        document.getElementById('storeDatalist').innerHTML = retailStores.map(s => `<option value="${s.name} [${s.id}]">`).join('');
    }
    if(document.getElementById('rlStoreSelect')) document.getElementById('rlStoreSelect').innerHTML = storesHtml;
    if(document.getElementById('poAssignedStore')) document.getElementById('poAssignedStore').innerHTML = '<option value="Warehouse">-- KEEP IN WH --</option>' + storesHtml; 
    
    const brands = [...new Set(retailStores.map(s => s.brand || s.brand_code).filter(Boolean))];
    const brandSel = document.getElementById('dfBrandSelect');
    if(brandSel) {
        brandSel.innerHTML = '<option value="">-- Choose Brand --</option>' + brands.map(b => `<option value="${b}">${b}</option>`).join('');
    }
}

window.filterStoresByBrand = function() {
    const selectedBrand = document.getElementById('dfBrandSelect').value;
    const storeSelect = document.getElementById('dfStoreSelect');
    
    storeSelect.innerHTML = '<option value="">-- Select Target Store --</option>';
    if (!selectedBrand) { 
        storeSelect.disabled = true; 
        document.getElementById('dfStoreId').value = '';
        document.getElementById('dfStoreEntity').value = '';
        document.getElementById('dfStoreRoute').value = '';
        return; 
    }

    const filtered = retailStores.filter(s => s.brand === selectedBrand || s.brand_code === selectedBrand);
    filtered.forEach(store => {
        const opt = document.createElement('option');
        opt.value = store.id; 
        opt.textContent = `${store.name} [${store.id}]`;
        opt.dataset.entity = store.entity || ''; 
        opt.dataset.mall = store.mall || ''; 
        opt.dataset.route = store.route_code || '';
        storeSelect.appendChild(opt);
    });
    storeSelect.disabled = false;
};

window.autofillStoreMeta = function() {
    const sel = document.getElementById('dfStoreSelect');
    const opt = sel.options[sel.selectedIndex];
    
    if (opt && opt.value) {
        document.getElementById('dfStoreId').value = opt.value;
        document.getElementById('dfStoreEntity').value = (opt.dataset.mall ? opt.dataset.mall + " / " : "") + opt.dataset.entity;
        document.getElementById('dfStoreRoute').value = opt.dataset.route || 'UNASSIGNED';
    }
};

window.populatePersonSelects = function() { const sel = document.getElementById('dfPersonSelect'); if(sel) sel.innerHTML = '<option value="">-- CHOOSE PERSONNEL --</option>' + personnelList.map(p => `<option value="${p.emp_id}">${p.emp_name} (${p.emp_id})</option>`).join(''); }
window.autofillPersonDetails = function() { const val = document.getElementById('dfPersonSelect').value; const person = personnelList.find(p => p.emp_id == val); if(person) { document.getElementById('dfPersonId').value = person.emp_id; document.getElementById('dfPersonName').value = person.emp_name; document.getElementById('dfPersonDept').value = person.department || ''; } else { document.getElementById('dfPersonId').value = ''; document.getElementById('dfPersonName').value = ''; document.getElementById('dfPersonDept').value = ''; } }

window.searchDispatchItems = function() { const query = document.getElementById('dispatchSearch').value.toLowerCase(); let results = masterInventory; if(query) results = masterInventory.filter(i => i.sku.toLowerCase().includes(query) || (i.category && i.category.toLowerCase().includes(query)) || (i.item_name && i.item_name.toLowerCase().includes(query))); window.renderDispatchSearchResults(results.slice(0, 10)); }
window.renderDispatchSearchResults = function(items) { 
    const container = document.getElementById('dispatchSearchResults'); 
    if(!container) return; 
    
    if(items.length === 0) { 
        container.innerHTML = '<p style="padding: 15px; font-weight: 600; color: #64748b; text-transform: uppercase;">ZERO MATCHES.</p>'; 
        return; 
    } 
    
    const isStore = document.querySelector('input[name="assigneeType"]:checked') && document.querySelector('input[name="assigneeType"]:checked').value === 'store'; 
    const targetStoreId = document.getElementById('dfStoreId') ? document.getElementById('dfStoreId').value : null; 
    
    container.innerHTML = items.map(item => { 
        let warningBadge = ''; 
        if (isStore && targetStoreId) { 
            const alreadyAssigned = dispatchLogs.some(log => String(log.store_code) === String(targetStoreId) && String(log.sku) === String(item.sku)); 
            if (alreadyAssigned) warningBadge = `<span style="background: var(--warning); color: #fff; padding: 3px 8px; font-size: 10px; margin-left: 10px; border-radius: 4px; font-weight: 600;">PREVIOUSLY ASSIGNED</span>`; 
        } 
        
        // Bulletproof fallback for names across different API endpoints
        const displayName = item.item_name || item.name || item.device_issued || 'Unknown Asset';
        const displayCat = item.category || '-';
        const displayQty = item.quantity_on_hand || item.qty || 0;

        return `<div style="padding: 15px; border-bottom: 1px solid #e2e8f0; background: #fff; display: flex; justify-content: space-between; align-items: center;"><div><strong style="font-size: 13px;">${displayName}</strong> <span style="font-family:monospace; margin-left: 5px; color: #64748b;">(${item.sku})</span> ${warningBadge}<br><span style="font-size:11px; color:#64748b; font-weight: 500; margin-top: 4px; display: inline-block;">BALANCE: ${displayQty} UNITS | CAT: ${displayCat}</span></div><button class="btn-cancel" onclick="addToDispatchCart('${item.sku}')"><i class="fas fa-plus"></i> ADD</button></div>`; 
    }).join(''); 
}

window.renderDispatchCart = function() { 
    const container = document.getElementById('dispatchCartContainer'); 
    
    if(dispatchCart.length === 0) { 
        container.innerHTML = '<p style="padding: 15px; font-weight: 500; color: #64748b;">NO ITEMS STAGED.</p>'; 
        return; 
    } 
    
    container.innerHTML = dispatchCart.map(item => {
        const displayName = item.item_name || item.name || item.device_issued || 'Unknown Asset';
        const displayQty = item.quantity_on_hand || item.qty || 1;
        
        return `<div style="display:flex; justify-content:space-between; align-items:center; padding: 12px 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 8px;"><div><strong style="font-size: 13px;">${displayName}</strong> <span style="font-family:monospace; font-size:11px; color: #64748b; margin-left: 6px;">(${item.sku})</span></div><div style="display:flex; gap:15px; align-items:center;"><input type="number" min="1" max="${displayQty}" value="${item.dispatchQty}" onchange="updateCartQty('${item.sku}', this.value)" style="width: 80px;"><i class="fas fa-trash-alt" style="color: var(--danger); cursor:pointer; font-size: 14px;" onclick="removeFromCart('${item.sku}')"></i></div></div>`
    }).join(''); 
}

window.submitDispatch = async function() {
    if(dispatchCart.length === 0) return showToast("APPEND PRODUCTS.");
    
    const isStore = document.querySelector('input[name="assigneeType"]:checked').value === 'store';
    
    const payload = { 
        dispatchId: generateDispatchID(), 
        storeCode: isStore ? document.getElementById('dfStoreId').value : "N/A", 
        pName: !isStore ? document.getElementById('dfPersonName').value : "N/A", 
        pId: !isStore ? document.getElementById('dfPersonId').value : "N/A", 
        pDept: !isStore ? document.getElementById('dfPersonDept').value : "N/A", 
        username: currentUser, 
        items: dispatchCart.map(i => ({ 
            sku: i.sku, 
            qty: i.dispatchQty,
            item_name: i.item_name || i.name || i.device_issued || 'Dispatched IT Asset'
        })) 
    };
    
    try {
        const res = await fetch('procurement_api.php?action=create_it_dispatch', { 
            method: 'POST', 
            headers: {'Content-Type': 'application/json'}, 
            body: JSON.stringify(payload) 
        });
        
        // INTERCEPT RAW TEXT TO CATCH PHP FATAL ERRORS
        const text = await res.text();
        
        try {
            const data = JSON.parse(text);
            if(data.status === 'success') { 
                showToast('IT DISPATCH COMPLETE'); 
                await fetchInventory(); 
                await fetchDispatchLogs(); 
                await fetchAuditLogs(); 
                closeModal('dispatchFlowModal'); 
            } else { 
                showToast("DB ERROR: " + data.message); 
            }
        } catch (jsonErr) {
            console.error("CRITICAL PHP ERROR. The server responded with:", text);
            showToast("PHP FATAL ERROR. Check F12 Console.");
        }

    } catch(err) { 
        console.error("Network/Fetch Error:", err);
        showToast('NETWORK FAILED.'); 
    }
}
window.renderDispatchCart = function() { 
    const container = document.getElementById('dispatchCartContainer'); 
    if(dispatchCart.length === 0) { container.innerHTML = '<p style="padding: 15px; font-weight: 500; color: #64748b;">NO ITEMS STAGED.</p>'; return; } 
    container.innerHTML = dispatchCart.map(item => `<div style="display:flex; justify-content:space-between; align-items:center; padding: 12px 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 8px;"><div><strong style="font-size: 13px;">${item.item_name || item.name}</strong> <span style="font-family:monospace; font-size:11px; color: #64748b; margin-left: 6px;">(${item.sku})</span></div><div style="display:flex; gap:15px; align-items:center;"><input type="number" min="1" max="${item.quantity_on_hand || item.qty}" value="${item.dispatchQty}" onchange="updateCartQty('${item.sku}', this.value)" style="width: 80px;"><i class="fas fa-trash-alt" style="color: var(--danger); cursor:pointer; font-size: 14px;" onclick="removeFromCart('${item.sku}')"></i></div></div>`).join(''); 
}
window.addToDispatchCart = function(sku) { const item = masterInventory.find(i => i.sku === sku); if(!item) return; const existing = dispatchCart.find(i => i.sku === sku); if(existing) { if(existing.dispatchQty < (item.quantity_on_hand || item.qty)) existing.dispatchQty += 1; else showToast("WAREHOUSE EXHAUSTED."); } else { if((item.quantity_on_hand || item.qty) > 0) dispatchCart.push({...item, dispatchQty: 1}); else showToast("ITEM DEPLETED."); } window.renderDispatchCart(); }
window.updateCartQty = function(sku, value) { const item = dispatchCart.find(i => i.sku === sku); const masterItem = masterInventory.find(i => i.sku === sku); if(item && masterItem) { let val = parseInt(value) || 1; const max = masterItem.quantity_on_hand || masterItem.qty; if(val > max) val = max; if(val < 1) val = 1; item.dispatchQty = val; window.renderDispatchCart(); } }
window.removeFromCart = function(sku) { dispatchCart = dispatchCart.filter(i => i.sku !== sku); window.renderDispatchCart(); }

window.submitDispatch = async function() {
    if(dispatchCart.length === 0) return showToast("APPEND PRODUCTS.");
    const isStore = document.querySelector('input[name="assigneeType"]:checked').value === 'store';
    const payload = { 
        dispatchId: generateDispatchID(), 
        storeCode: isStore ? document.getElementById('dfStoreId').value : "N/A", 
        pName: !isStore ? document.getElementById('dfPersonName').value : "N/A", 
        pId: !isStore ? document.getElementById('dfPersonId').value : "N/A", 
        pDept: !isStore ? document.getElementById('dfPersonDept').value : "N/A", 
        username: currentUser, 
        items: dispatchCart.map(i => ({ 
            sku: i.sku, 
            qty: i.dispatchQty,
            item_name: i.item_name || i.name || 'Dispatched IT Asset'
        })) 
    };
    try {
        const res = await fetch('procurement_api.php?action=create_it_dispatch', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json();
        if(data.status === 'success') { showToast('IT DISPATCH COMPLETE'); await fetchInventory(); await fetchDispatchLogs(); await fetchAuditLogs(); closeModal('dispatchFlowModal'); } else { showToast("DB ERROR: " + data.message); }
    } catch(err) { showToast('PAYLOAD FAILED.'); }
}

window.handleITAssetsImport = async function(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = async function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, {type: 'array'});
        const jsonRows = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]], { defval: "" });
        
        const assetsPayload = jsonRows.map(row => ({
            hostname: row['Hostname'] || 'N/A',
            brandName: row['Brand Name'] || '',
            storeCode: row['Store Code'] || '',
            storeName: row['Store Name'] || '',
            deviceType: row['Device Type'] || '',
            modelName: row['Model Name'] || '',
            serialNumber: row['Serial Number'] || '',
            osInfo: `${row['OS'] || ''} ${row['Version'] || ''}`.trim(),
            specs: `${row['Memory'] || ''} / ${row['CPU Type'] || ''}`.trim()
        })).filter(a => a.hostname !== 'N/A');

        try {
            showToast("SYNCING DATABASE...");
            const res = await fetch('procurement_api.php?action=bulk_import_it_assets', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/json'}, 
                body: JSON.stringify({ assets: assetsPayload, username: currentUser }) 
            });
            const result = await res.json();
            if(result.status === 'success') { 
                showToast(`IMPORTED ${result.imported} ASSETS.`); 
                fetchITAssets(); 
            } else { showToast("MAPPING ERROR."); }
        } catch(err) { showToast("API FAILURE."); }
        
        if (document.getElementById('itAssetsExcelUpload')) document.getElementById('itAssetsExcelUpload').value = "";
    };
    reader.readAsArrayBuffer(file);
}

window.handleBulkProvisionImport = function(event) {
    const file = event.target.files[0]; if(!file) return;
    const reader = new FileReader();
    reader.onload = async function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, {type: 'array'});
        const jsonRows = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);
        
        try {
            showToast("PROCESSING BULK ASSIGNATION...");
            const res = await fetch('procurement_api.php?action=bulk_store_provision', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ payload: jsonRows, username: currentUser })
            });
            const result = await res.json();
            if(result.status === 'success') { showToast('BULK PROVISION SUCCESSFUL.'); initApp(); }
        } catch(e) { showToast("API ERROR"); }
        event.target.value = '';
    };
    reader.readAsArrayBuffer(file);
}

window.addReverseLogisticsRow = function() {
    const container = document.getElementById('rlItemsContainer');
    if(!container) return;
    const div = document.createElement('div'); div.className = 'dynamic-field-row';
    div.style.display = 'grid'; 
    div.style.gridTemplateColumns = '1.2fr 1fr 1fr 1.5fr 1fr 1fr 0.8fr 1fr auto'; 
    div.style.gap = '15px'; div.style.alignItems = 'center'; 
    div.style.background = '#f8fafc'; div.style.padding = '15px'; 
    div.style.border = '1px solid #cbd5e1'; div.style.marginBottom = '12px'; div.style.borderRadius = '6px';
    
    div.innerHTML = `
        <input type="text" list="categoryList" class="rl-category" placeholder="CATEGORY" required>
        <input type="text" list="hardwareTypesList" class="rl-hwtype" placeholder="H/W TYPE" required>
        <input type="text" list="itDevicesList" class="rl-name" placeholder="ITEM NAME" required>
        <input type="text" class="rl-desc" placeholder="DESC">
        <input type="text" class="rl-model" placeholder="MODEL NO.">
        <input type="text" class="rl-serial" placeholder="SERIAL NO.">
        <input type="number" class="rl-qty" placeholder="QTY" min="1" required>
        <select class="rl-action" required>
            <option value="Reuse">REUSE</option>
            <option value="Dispose">DISPOSE</option>
        </select>
        <button type="button" class="btn-cancel" onclick="this.parentElement.remove()" style="border: none; color: var(--danger); background:transparent; padding: 0; box-shadow: none;"><i class="fas fa-trash" style="font-size: 16px;"></i></button>
    `;
    container.appendChild(div);
}

window.submitReverseLogistics = async function(e) {
    e.preventDefault();
    
    const storeInputVal = document.getElementById('rlStoreInput').value;
    const match = storeInputVal.match(/\[(\d+)\]/);
    if (!match) return showToast("SELECT VALID STORE.");
    const storeId = match[1];

    const rows = document.querySelectorAll('#rlItemsContainer .dynamic-field-row');
    const items = [];
    
    rows.forEach(row => {
        const generatedSKU = 'REV-' + Math.floor(100000 + Math.random() * 900000); 
        items.push({
            name: row.querySelector('.rl-name').value.trim(),
            category: row.querySelector('.rl-category').value.trim() || 'General',
            hardware_type: row.querySelector('.rl-hwtype').value.trim() || 'Asset',
            desc: row.querySelector('.rl-desc').value.trim() || 'N/A',
            model: row.querySelector('.rl-model').value.trim() || 'N/A',
            serial: row.querySelector('.rl-serial').value.trim() || 'N/A',
            qty: parseInt(row.querySelector('.rl-qty').value),
            action: row.querySelector('.rl-action').value,
            sku: generatedSKU,
            remark: 'Manual Entry (Generated SKU: ' + generatedSKU + ')'
        });
    });

    if(items.length === 0) return showToast("ADD ITEMS TO PROCESS.");

    // 1. UPDATE THE WARNING MESSAGE
    const warningMsg = `WARNING: CLOSING STORE ID [${storeId}].\n\nThis will process the return items and disable the store's login access. The store record will remain in the database.\n\nPROCEED?`;
    if(!confirm(warningMsg)) { return showToast("ABORTED."); }

    try {
        const res = await fetch('procurement_api.php?action=process_reverse_logistics', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ storeId, items, username: currentUser })
        });
        const data = await res.json();
        
        if(data.status === 'success') {
            try {
                // 2. CHANGE ENDPOINT FROM delete_store TO disable_store
                await fetch(`procurement_api.php?action=disable_store&id=${storeId}`, {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ username: currentUser })
                });
            } catch(e) { console.warn("Disable request failed."); }

            for(let item of items) {
                await fetch('procurement_api.php?action=initiate_asset_recovery', {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ originStore: storeId, deviceIssued: item.name, qty: item.qty, sku: item.sku, username: currentUser })
                });
            }

            showToast('STORE DISABLED & RETURNS PROCESSED.');
            document.getElementById('rlItemsContainer').innerHTML = ''; 
            document.getElementById('rlStoreInput').value = '';
            
            await fetchInventory(); 
            await fetchAuditLogs(); 
            await fetchReverseLogistics();
            await fetchStores();
            await fetchAssetRecovery(); 
        } else { showToast("DB ERROR: " + data.message); }
    } catch(err) { showToast('CONNECTION FAILED.'); }
}

window.openEditPOModal = function(poId) {
    const po = purchaseOrders.find(p => String(p.id) === String(poId));
    if(!po) return;
    document.getElementById('editPoId').value = po.id;
    document.getElementById('editPoNumber').value = (po.po_number || po.poNumber || '').includes('PENDING') ? '' : (po.po_number || po.poNumber);
    document.getElementById('editPoDeliveryNote').value = po.delivery_note_no || po.delivery_note_number || '';
    document.getElementById('editPoInvoice').value = po.invoice_number || '';

    // Populate the line items for quantity editing
    const linesBody = document.getElementById('editPoLinesBody');
    linesBody.innerHTML = (po.lineItems || []).map(item => `
        <tr class="edit-po-line-row" data-sku="${item.sku}">
            <td style="font-family: monospace; font-weight: 600;">${item.sku}</td>
            <td>${item.item_name || item.itemName}</td>
            <td><input type="number" class="edit-po-qty" value="${item.order_qty || item.quantity}" min="1" style="width: 80px; padding: 6px;"></td>
            <td><input type="number" class="edit-po-price" value="${item.price}" step="0.01" style="width: 100px; padding: 6px;"></td>
        </tr>
    `).join('');

    document.getElementById('editPoModal').classList.add('active');
}

window.submitEditPO = async function(e) {
    e.preventDefault();
    const id = document.getElementById('editPoId').value;
    const poNumber = document.getElementById('editPoNumber').value.trim() || 'PENDING-' + Date.now().toString().slice(-6);
    const deliveryNote = document.getElementById('editPoDeliveryNote').value.trim();
    const invoiceNumber = document.getElementById('editPoInvoice').value.trim();

    // Gather the edited quantities
    const updatedLines = [];
    document.querySelectorAll('.edit-po-line-row').forEach(row => {
        updatedLines.push({
            sku: row.getAttribute('data-sku'),
            qty: parseInt(row.querySelector('.edit-po-qty').value || 1),
            price: parseFloat(row.querySelector('.edit-po-price').value || 0)
        });
    });

    try {
        const res = await fetch('procurement_api.php?action=update_po', { 
            method: 'POST', 
            headers: {'Content-Type': 'application/json'}, 
            body: JSON.stringify({ id, poNumber, deliveryNote, invoiceNumber, updatedLines, username: currentUser }) 
        });
        const data = await res.json();
        
        if(data.status === 'success') { 
            showToast('PO RECORD & QUANTITIES UPDATED.'); 
            closeModal('editPoModal'); 
            initApp(); 
        } else { 
            showToast("UPDATE ERROR: " + data.message); 
        }
    } catch(err) { 
        showToast('DB FAILURE.'); 
    }
}

window.checkRecvComplete = function(input, ordered, prev) {
    const row = input.closest('tr');
    const total = parseInt(input.value || 0) + parseInt(prev);
    const statusCell = row.querySelector('.recv-dynamic-status');
    if (total >= ordered) {
        statusCell.innerHTML = '<span class="status-badge status-complete">COMPLETE</span>';
    } else {
        statusCell.innerHTML = '<span class="status-badge status-partial">PARTIAL</span>';
    }
};

window.openReceivePOModal = function(poId) {
    const po = purchaseOrders.find(p => String(p.id) === String(poId));
    if(!po) return;
    document.getElementById('recvPoId').value = po.id;
    document.getElementById('recvPoTitle').innerText = `RECEIVING: ${po.po_number || po.poNumber}`;
    const tbody = document.getElementById('receivePoTableBody');
    tbody.innerHTML = (po.lineItems || []).map(item => {
        const ordered = parseInt(item.order_qty || item.quantity);
        const prev = parseInt(item.receive_qty || item.received_quantity || 0);
        const remaining = ordered - prev;
        
        let initialStatus = remaining <= 0 ? 
            '<span class="status-badge status-complete">COMPLETE</span>' : 
            '<span class="status-badge status-partial">PARTIAL</span>';

        return `<tr class="recv-row">
            <td class="recv-sku" style="font-family:monospace; font-weight: 600;">${item.sku || item.serial_number}</td>
            <td><strong>${item.item_name || item.itemName}</strong></td>
            <td><strong style="color: #334155;">${ordered}</strong></td>
            <td><strong style="color: #64748b;">${prev}</strong></td>
            <td>
                <input type="number" 
                       class="recv-input" 
                       min="0" 
                       max="${remaining}" 
                       value="${remaining > 0 ? remaining : 0}" 
                       ${remaining === 0 ? 'disabled' : ''} 
                       oninput="checkRecvComplete(this, ${ordered}, ${prev})"
                       style="width:90px;">
            </td>
            <td class="recv-dynamic-status">${initialStatus}</td>
        </tr>`;
    }).join('');
    document.getElementById('receivePoModal').classList.add('active');
}

window.submitPOReceipt = async function(e) {
    e.preventDefault();
    const poId = document.getElementById('recvPoId').value;
    const rows = document.querySelectorAll('.recv-row');
    const items = [];
    rows.forEach(row => { const input = row.querySelector('.recv-input'); if(input && !input.disabled) items.push({ sku: row.querySelector('.recv-sku').innerText, recv_qty: parseInt(input.value || 0) }); });
    try {
        const res = await fetch('procurement_api.php?action=receive_po', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ poId, items, username: currentUser }) });
        const data = await res.json();
        if(data.status === 'success') { showToast('PO RECEIVED'); closeModal('receivePoModal'); initApp(); }
    } catch(err) { showToast('DB FAILURE.'); }
}

window.addNewLineItem = function(
    cat = 'ICT', hwType = 'SYSTEM', name = '', desc = '', 
    model = 'N/A', serial = 'Pending Trace', qty = 1, price = 0
) {
    const container = document.getElementById('lineItemsContainer');
    const div = document.createElement('div'); div.className = 'line-item';
    div.innerHTML = `
        <div class="form-grid" style="grid-template-columns: repeat(4, 1fr); gap: 15px; background: #fff; padding: 20px; border: 1px solid var(--erp-border); border-radius: var(--radius-sm); margin-bottom: 15px; position: relative; box-shadow: var(--shadow-card);">
            <button type="button" onclick="this.parentElement.parentElement.remove(); window.recalcTotals();" style="position: absolute; top: 15px; right: 15px; background: transparent; border: none; color: var(--danger); cursor: pointer;"><i class="fas fa-times fa-lg"></i></button>
            
            <div class="form-group"><label>Category</label><input type="text" class="line-category" value="${cat}" list="categoryList"></div>
            <div class="form-group"><label>Hardware Type</label><input type="text" class="line-hw-type" value="${hwType}" list="hardwareTypesList"></div>
            <div class="form-group" style="grid-column: span 2;"><label>Item Name *</label><input type="text" class="line-name" value="${name}" required></div>
            
            <div class="form-group" style="grid-column: span 2;"><label>Item Description</label><input type="text" class="line-desc" value="${desc}"></div>
            <div class="form-group"><label>Model Number</label><input type="text" class="line-model" value="${model}"></div>
            <div class="form-group"><label>Serial Number</label><input type="text" class="line-serial" value="${serial}"></div>
            
            <div class="form-group"><label>Order Qty *</label><input type="number" class="line-qty" value="${qty}" min="1" required oninput="window.recalcTotals()"></div>
            <div class="form-group"><label>Unit Price (SAR) *</label><input type="number" step="0.01" class="line-price" value="${price}" required oninput="window.recalcTotals()"></div>
            <div class="form-group" style="grid-column: span 2;"><label>Line Total</label><input type="text" class="line-total" style="background: #f8fafc; font-weight: 700; color: var(--erp-text-blue);" readonly></div>
        </div>`;
    container.appendChild(div);
    window.recalcTotals();
};

window.recalcTotals = function() {
    let subtotal = 0;
    document.querySelectorAll('.line-item').forEach(item => {
        const qty = parseFloat(item.querySelector('.line-qty').value) || 0;
        const price = parseFloat(item.querySelector('.line-price').value) || 0;
        item.querySelector('.line-total').value = (qty * price).toFixed(2);
        subtotal += (qty * price);
    });
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    const tax = parseFloat(document.getElementById('taxAmount').value) || 0;
    document.getElementById('grandTotal').value = (subtotal + tax).toFixed(2);
};

window.deletePO = async function(id, poNum) {
    if(confirm('DELETE PO PERMANENTLY?')) {
        try {
            const res = await fetch('procurement_api.php?action=delete_po', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id, poNumber: poNum, username: currentUser})
            });
            const data = await res.json();
            if(data.status === 'success') {
                showToast('PO PURGED');
                initApp();
            } else {
                showToast(data.message);
            }
        } catch(e) { showToast('SERVER/NETWORK ERROR'); }
    }
};

window.handleBulkStoreImport = async function(event) {
    const file = event.target.files[0]; if(!file) return; const reader = new FileReader();
    reader.onload = async function(e) {
        const rows = e.target.result.split('\n').map(row => row.split(','));
        if(rows.length < 2) return showToast("FORMATTING FAILURE.");
        const headers = rows[0].map(h => h.trim().replace(/[\"\']/g, ''));
        
        const payloadRows = [];
        for(let i=1; i<rows.length; i++) {
            let rowObj = {};
            headers.forEach((head, idx) => { if(rows[i][idx]) rowObj[head] = rows[i][idx].trim().replace(/[\"\']/g, ''); });
            if(rowObj['Retail Code']) payloadRows.push(rowObj);
        }
        
        try {
            const res = await fetch('procurement_api.php?action=batch_import_stores', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ stores: payloadRows, username: currentUser }) });
            const result = await res.json();
            if(result.status === 'success') { showToast(`IMPORTED ${result.imported} STORES.`); fetchStores(); }
        } catch(err) { showToast("API FAILURE."); }
        document.getElementById('storeBulkCsv').value = "";
    }; reader.readAsText(file);
};

window.handleExcelImport = function(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: 'array' });
        const jsonRows = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);
        
        if(jsonRows.length === 0) return showToast("TEMPLATE EMPTY.");
        preflightPendingRows = jsonRows;
        
        const templateVendors = [...new Set(jsonRows.map(row => (row['Vendor Name'] || row['vendor'] || '').trim()).filter(Boolean))];
        const registeredNames = approvedVendors.map(v => (v.company_name || v.company || '').toLowerCase().trim());
        preflightMissingVendors = templateVendors.filter(v => !registeredNames.includes(v.toLowerCase().trim()));
        
        if (preflightMissingVendors.length > 0) { window.triggerPreflightResolutionWizard(); } 
        else { window.executeSplitPOIngestion(); }
        document.getElementById('excelUpload').value = ""; 
    };
    reader.readAsArrayBuffer(file);
};

window.triggerPreflightResolutionWizard = function() {
    const container = document.getElementById('preflightContainer');
    container.innerHTML = preflightMissingVendors.map((vendor, index) => `
        <div style="background: #fff; padding: 15px; border: 1px solid var(--erp-border); border-radius: var(--radius-sm); display:grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; align-items:end;">
            <div class="form-group"><label>Company Name</label><input type="text" class="pf-company" value="${vendor}" readonly style="background:#f8fafc; font-weight: 600;"></div>
            <div class="form-group"><label>Tax ID / VAT Registration</label><input type="text" class="pf-tax" placeholder="e.g. TRN-93812"></div>
            <div class="form-group"><label>Payment Terms</label><input type="text" class="pf-terms" value="Net 30"></div>
        </div>
    `).join('');
    document.getElementById('preflightVendorModal').classList.add('active');
};

window.commitPreflightVendors = async function() {
    const blocks = document.querySelectorAll('#preflightContainer > div');
    for (let div of blocks) {
        const company = div.querySelector('.pf-company').value.trim();
        const taxId = div.querySelector('.pf-tax').value.trim() || 'N/A';
        const terms = div.querySelector('.pf-terms').value.trim() || 'Net 30';
        const payload = { company, taxId, terms, lead: '5 Days', email: 'operations@vendor.sa', username: currentUser };
        
        try {
            const res = await fetch('procurement_api.php?action=create_vendor', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
            const data = await res.json();
            if(data.status !== 'success') { showToast("REGISTRATION FAILED."); return; }
        } catch(err) { return showToast("API ERROR."); }
    }
    await fetchVendors();
    closeModal('preflightVendorModal');
    window.executeSplitPOIngestion();
};

window.executeSplitPOIngestion = async function() {
    const groupedData = {};
    preflightPendingRows.forEach(row => {
        let vName = (row['Vendor Name'] || row['vendor'] || 'Generic Ingest').trim();
        const match = approvedVendors.find(v => (v.company_name || v.company || '').toLowerCase().trim() === vName.toLowerCase());
        if(match) vName = match.company_name || match.company;

        if(!groupedData[vName]) groupedData[vName] = [];
        groupedData[vName].push(row);
    });

    let successCount = 0;
    
    for (const [vendorName, rows] of Object.entries(groupedData)) {
        const generatedPoNo = 'PO-' + Math.floor(100000 + Math.random() * 900000);
        
        const flatRecords = rows.map(row => {
            const qty = parseInt(row['QTY'] || row['Quantity'] || row['Order qty'] || 1);
            const price = parseFloat(row['Price'] || row['Unit Price'] || 0);

            return {
                po_number: generatedPoNo,
                delivery_note_no: 'N/A',
                invoice_number: 'N/A',
                assigned_store: 'Warehouse',
                assigned_brand: 'N/A',
                category: String(row['Category'] || 'ICT').trim(),
                hardware_type: String(row['Hardware Type'] || 'Hardware').trim(),
                item_name: String(row['Item Name'] || row['Name'] || 'Unclassified Core Asset').trim(),
                item_description: String(row['Item Description'] || row['Description'] || 'Imported Line Item Context').trim(),
                model_number: String(row['Model Number'] || 'N/A').trim(),
                price: price,
                order_qty: qty,
                receive_qty: 0,
                serial_number: String(row['Serial Number'] || 'Pending Trace').trim(),
                vendor_name: vendorName
            };
        });

        try {
            const res = await fetch('procurement_api.php?action=create_it_po', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/json'}, 
                body: JSON.stringify({ po_records: flatRecords, username: currentUser }) 
            });
            const result = await res.json();
            if(result.status === 'success') {
                successCount++;
            } else {
                console.error("DB Validation Error:", result.message);
            }
        } catch(e) { 
            console.warn("Network crash during splits.", e); 
        }
    }
    
    alert(`SUCCESS: CREATED ${successCount} VENDOR PO BATCHES.`);
    await initApp();
};

window.saveStore = async function(e) {
    e.preventDefault();
    const payload = { 
        sName: document.getElementById('sName').value, 
        sCode: document.getElementById('sCode').value, // NEW: Grabbing the manual code
        sBrandCode: document.getElementById('sBrandCode').value || 'N/A',
        sBrand: document.getElementById('sBrand').value || 'N/A',
        sMall: document.getElementById('sMall').value || 'N/A',
        sEntity: document.getElementById('sEntity').value || 'N/A',
        sCity: document.getElementById('sCity').value || 'N/A',
        sCountry: document.getElementById('sCountry').value || 'N/A',
        sAddress: document.getElementById('sAddress').value || 'N/A',
        sRegionId: document.getElementById('sRegionId').value || 'N/A',
        sEmail: document.getElementById('sEmail').value || 'N/A',
        sRoute: document.getElementById('sRoute').value || 'N/A', 
        username: currentUser
    };
    
    try {
        const res = await fetch('procurement_api.php?action=create_store', { 
            method: 'POST', 
            headers: {'Content-Type': 'application/json'}, 
            body: JSON.stringify(payload) 
        });
        
        // Let's use the safer parsing method here too to catch PHP errors!
        const text = await res.text();
        try {
            const data = JSON.parse(text);
            if(data.status === 'success') { 
                showToast('STORE REGISTERED'); 
                
                // Clear the form for the next time
                document.getElementById('storeForm').reset();
                
                await fetchStores(); 
                await fetchAuditLogs(); 
                closeModal('storeModal'); 
            } else {
                showToast("DB ERROR: " + data.message);
            }
        } catch (jsonErr) {
            console.error("PHP Error:", text);
            showToast("PHP FATAL ERROR. Check F12 Console.");
        }
    } catch(err) { 
        showToast('CONNECTION FAILED'); 
    }
};

window.saveVendor = async function(e) {
    e.preventDefault();
    const payload = { company: document.getElementById('vCompany').value, taxId: document.getElementById('vTax').value || 'N/A', terms: document.getElementById('vTerms').value || 'N/A', lead: document.getElementById('vLead').value || 'N/A', email: document.getElementById('vEmail').value || 'N/A', username: currentUser };
    try {
        const res = await fetch('procurement_api.php?action=create_vendor', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json();
        if(data.status === 'success') { showToast('VENDOR APPENDED'); await fetchVendors(); await fetchAuditLogs(); closeModal('vendorModal'); } else showToast("ERROR: " + data.message);
    } catch(err) { showToast('CONNECTION FAILED'); }
};

window.removeVendor = async function(id, company) {
    if(confirm('REVOKE AND REMOVE VENDOR?')) {
        try {
            const res = await fetch(`procurement_api.php?action=delete_vendor&id=${id}`, { 
                method: 'POST', headers: {'Content-Type': 'application/json'}, 
                body: JSON.stringify({username: currentUser, company}) 
            });
            const data = await res.json();
            if(data.status === 'success') {
                showToast('VENDOR REMOVED'); 
                await fetchVendors(); 
                await fetchAuditLogs();
            } else {
                showToast(data.message); 
            }
        } catch(e) { showToast('SERVER/NETWORK ERROR'); }
    }
};

window.saveUser = async function(e) {
    e.preventDefault();
    const payload = { uId: document.getElementById('uId').value, uName: document.getElementById('uName').value, uDept: document.getElementById('uDept').value || 'N/A', username: currentUser };
    try {
        const res = await fetch('procurement_api.php?action=create_personnel', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json();
        if(data.status === 'success') { showToast('PERSONNEL LOGGED'); await fetchPersonnel(); await fetchAuditLogs(); closeModal('userModal'); } else showToast("ERROR: " + data.message);
    } catch(err) { showToast('CONNECTION FAILED'); }
};

window.removePersonnel = async function(empId) {
    if(confirm('REVOKE PERSONNEL ACCESS?')) {
        try {
            const res = await fetch(`procurement_api.php?action=delete_personnel&emp_id=${empId}`, { 
                method: 'POST', headers: {'Content-Type': 'application/json'}, 
                body: JSON.stringify({username: currentUser}) 
            });
            const data = await res.json();
            if(data.status === 'success') { 
                showToast('ACCESS REVOKED'); 
                await fetchPersonnel(); 
                await fetchAuditLogs(); 
            } else {
                showToast(data.message); 
            }
        } catch(e) { showToast('SERVER/NETWORK ERROR'); }
    }
};

window.savePO = async function(e) {
    e.preventDefault();
    
    const storeInputVal = document.getElementById('poAssignedStore').value;
    let assignedStoreVal = 'Warehouse';
    if (storeInputVal && storeInputVal !== 'Warehouse') {
        const match = storeInputVal.match(/\[(\d+)\]/);
        assignedStoreVal = match ? match[1] : storeInputVal;
    }

    const payloadLines = [];
    document.querySelectorAll('.line-item').forEach(item => {
        payloadLines.push({
            po_number: document.getElementById('poNumber').value.trim() || 'PENDING-' + Date.now().toString().slice(-6),
            delivery_note_no: document.getElementById('poDeliveryNote').value.trim() || 'N/A',
            invoice_number: document.getElementById('poInvoice').value.trim() || 'N/A',
            assigned_store: assignedStoreVal,
            assigned_brand: document.getElementById('poAssignedBrand').value.trim() || 'N/A',
            category: item.querySelector('.line-category').value.trim() || 'ICT',
            hardware_type: item.querySelector('.line-hw-type').value.trim() || 'Hardware',
            item_name: item.querySelector('.line-name').value.trim(),
            item_description: item.querySelector('.line-desc').value.trim() || 'N/A',
            model_number: item.querySelector('.line-model').value.trim() || 'N/A',
            price: parseFloat(item.querySelector('.line-price').value) || 0,
            order_qty: parseFloat(item.querySelector('.line-qty').value) || 0,
            receive_qty: 0,
            serial_number: item.querySelector('.line-serial').value.trim() || 'Pending Trace',
            vendor_name: document.getElementById('vendorSelect') ? document.getElementById('vendorSelect').value : 'Generic'
        });
    });

    try {
        const res = await fetch('procurement_api.php?action=create_it_po', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ po_records: payloadLines, username: currentUser })
        });
        const data = await res.json();
        if(data.status === 'success') {
            window.showToast('ICT PO LOGGED TO LEDGER');
            closeModal('poModal'); 
            window.initApp();
        } else { window.showToast("DB ERROR: " + data.message); }
    } catch(err) { window.showToast('CONNECTION ERROR.'); }
};