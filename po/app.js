// ==========================================
// BILL MATE RETAIL SUITE - PORTAL CORE INTERCEPT
// ==========================================

let currentUser = null;
let currentRole = 'Admin';
let currentRoute = 'N/A';

// Robust local storage handling
try {
    currentUser = localStorage.getItem('procurement_user');
    currentRole = localStorage.getItem('procurement_role') || 'Admin';
    currentRoute = localStorage.getItem('procurement_route') || 'N/A';
} catch(e) {
    console.warn("LocalStorage access restricted by browser security.");
}

if (currentUser === 'null' || currentUser === 'undefined' || currentUser === '') currentUser = null;
if (currentRole === 'null' || currentRole === 'undefined' || currentRole === '') currentRole = 'Admin';

// Core Application Caches
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

// Pagination Config - Set to 20 per request
const tableStates = {};
const ROWS_PER_PAGE = 20;

// Preflight & Batch Caches for Imports
let preflightPendingRows = [];
let preflightMissingVendors = [];

if (typeof pdfjsLib !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
}

// Intercept application load to handle store role visibility constraints safely
const originalPortalInit = window.onload || function() {};
window.onload = () => {
    originalPortalInit();
    
    // Bulletproof Routing Engine
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

async function handleLogin(e) {
    e.preventDefault();
    const user = document.getElementById('loginUsername').value.trim();
    const pass = document.getElementById('loginPassword').value.trim();
    
    try {
        const res = await fetch(`procurement_api.php?action=login&user=${user}&pass=${pass}`);
        const data = await res.json();
        if (data.status === 'success') {
            currentUser = data.user.username || data.user.name || user;
            currentRole = data.user.role || 'Admin';
            localStorage.setItem('procurement_user', currentUser);
            localStorage.setItem('procurement_role', currentRole);
            if (data.user.route_code) localStorage.setItem('procurement_route', data.user.route_code);
            location.reload(); 
        } else { showToast('Access Denied: ' + (data.message || 'Verification failed.')); }
    } catch(err) { showToast('Connection Error: Database endpoint unreachable.'); }
}

function handleLogout() { localStorage.clear(); location.reload(); }

async function initApp() {
    if (document.getElementById('orderDate')) document.getElementById('orderDate').value = new Date().toISOString().slice(0,10);
    
    await Promise.all([ 
        fetchStores(), fetchVendors(), fetchPersonnel(), fetchAllPOs(), fetchInventory(), fetchDispatchLogs(), fetchAuditLogs(), fetchReverseLogistics() 
    ]);

    if (currentRole === 'Store') {
        renderStorePortalInventoryOnly();
        renderStorePortalRequestSession();
    } else {
        patchAdminStoresGridDisplay();
        fetchAdminStoreRequests();
        renderDispatchLog();
        renderStoreStockView();
    }
}

// ==========================================
// DATE FILTERED CSV EXPORT ENGINE
// ==========================================
function openExportModal(tableId, dateColIdx, fileName) {
    document.getElementById('exportTableId').value = tableId;
    document.getElementById('exportDateColIdx').value = dateColIdx;
    document.getElementById('exportFileName').value = fileName;
    document.getElementById('exportStartDate').value = '';
    document.getElementById('exportEndDate').value = '';
    document.getElementById('exportModal').classList.add('active');
}

function executeDateExport(e) {
    e.preventDefault();
    const tableId = document.getElementById('exportTableId').value;
    const dateColIdx = parseInt(document.getElementById('exportDateColIdx').value);
    const fileName = document.getElementById('exportFileName').value;
    const startDateStr = document.getElementById('exportStartDate').value;
    const endDateStr = document.getElementById('exportEndDate').value;

    const table = document.getElementById(tableId);
    if(!table) {
        closeModal('exportModal');
        return showToast("Table not found.");
    }

    const headers = Array.from(table.querySelectorAll('thead th')).map(th => {
        let text = "";
        for (let node of th.childNodes) {
            if (node.nodeType === Node.TEXT_NODE) text += node.textContent;
        }
        return text.trim();
    });
    
    const actionColIndex = headers.findIndex(h => h.toLowerCase().includes('action'));
    if(actionColIndex > -1) headers.splice(actionColIndex, 1);

    // Only get rows that are NOT filtered out by the user's top column search bars
    let rows = Array.from(table.querySelectorAll('tbody tr')).filter(r => !r.classList.contains('filtered-out') && r.cells.length > 1);

    if(rows.length === 0) {
        closeModal('exportModal');
        return showToast("No data matches current search filters.");
    }

    // Apply Date Filter if provided and valid date column exists
    if ((startDateStr || endDateStr) && !isNaN(dateColIdx) && dateColIdx !== -1) {
        const start = startDateStr ? new Date(startDateStr).setHours(0,0,0,0) : null;
        const end = endDateStr ? new Date(endDateStr).setHours(23,59,59,999) : null;

        rows = rows.filter(row => {
            const cell = row.cells[dateColIdx];
            if (!cell) return false;
            
            // Parse date from cell text. If text is invalid date (e.g. "Recent"), keep it safely.
            const cellDate = new Date(cell.innerText.trim());
            if (isNaN(cellDate.getTime())) return true; 

            const cellTime = cellDate.getTime();
            if (start && cellTime < start) return false;
            if (end && cellTime > end) return false;
            return true;
        });
    }

    if(rows.length === 0) {
        closeModal('exportModal');
        return showToast("No data falls within the selected date range.");
    }

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
    showToast("Export successful.");
}

// ==========================================
// BULLETPROOF TAB NAVIGATION
// ==========================================
document.querySelectorAll('.admin-tab').forEach(nav => {
    nav.addEventListener('click', () => {
        document.querySelectorAll('.admin-panel').forEach(p => {
            p.style.display = 'none';
            p.classList.remove('active-panel');
        });
        const tab = nav.getAttribute('data-tab');
        const targetPanel = document.getElementById(tab + 'Panel');
        if (targetPanel) { targetPanel.style.display = 'block'; targetPanel.classList.add('active-panel'); }
        document.querySelectorAll('.admin-tab').forEach(n => n.classList.remove('active'));
        nav.classList.add('active');
        
        if (tab === 'dispatch') renderDispatchLog();
        if (tab === 'storeRequestsAdmin') { fetchAdminStoreRequests(); renderAdminProcessedRequests(); }
        if (tab === 'storeStock') renderStoreStockView();
        if (tab === 'disposed') { fetchReverseLogistics(); renderDisposedList(); }
    });
});

document.querySelectorAll('#storeView .nav-item').forEach(nav => {
    nav.addEventListener('click', () => {
        document.querySelectorAll('#storeView .card-panel').forEach(p => p.style.display = 'none');
        const targetPanel = document.getElementById(nav.getAttribute('data-tab') + 'Panel');
        if(targetPanel) targetPanel.style.display = 'block';
        document.querySelectorAll('#storeView .nav-item').forEach(n => n.classList.remove('active'));
        nav.classList.add('active');
    });
});

// ==========================================
// PAGINATION & FILTER ENGINE
// ==========================================
function initPagination(tableId) {
    if (!tableStates[tableId]) tableStates[tableId] = { currentPage: 1 };
    applyPagination(tableId);
}

function changePage(tableId, delta) {
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
    
    const totalPages = Math.ceil(rows.length / ROWS_PER_PAGE) || 1;
    let state = tableStates[tableId];
    if (state.currentPage > totalPages) state.currentPage = totalPages;
    if (state.currentPage < 1) state.currentPage = 1;

    const startIdx = (state.currentPage - 1) * ROWS_PER_PAGE;
    const endIdx = startIdx + ROWS_PER_PAGE;

    Array.from(tbody.querySelectorAll('tr')).forEach(r => r.style.display = 'none');
    rows.slice(startIdx, endIdx).forEach(r => r.style.display = '');

    const container = document.getElementById('page-' + tableId);
    if(container) {
        container.innerHTML = `
            <span>Showing ${rows.length === 0 ? 0 : startIdx + 1} to ${Math.min(endIdx, rows.length)} of ${rows.length} entries</span>
            <div class="pagination-controls">
                <button onclick="changePage('${tableId}', -1)" ${state.currentPage === 1 ? 'disabled' : ''}>Prev</button>
                <button onclick="changePage('${tableId}', 1)" ${state.currentPage === totalPages ? 'disabled' : ''}>Next</button>
            </div>
        `;
    }
}

function filterTable(inputElement) {
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
// DATA ACQUISITION
// ==========================================
async function fetchStores() { try { const res = await fetch('procurement_api.php?action=list_stores'); const data = await res.json(); if(Array.isArray(data)) { retailStores = data; patchAdminStoresGridDisplay(); populateStoreSelects(); } } catch(e) {} }
async function fetchPersonnel() { try { const res = await fetch('procurement_api.php?action=list_personnel'); const data = await res.json(); if(Array.isArray(data)) { personnelList = data; renderPersonnelList(); populatePersonSelects(); } } catch(e) {} }
async function fetchVendors() { try { const res = await fetch('procurement_api.php?action=list_vendors'); const data = await res.json(); if(Array.isArray(data)) { approvedVendors = data; renderVendorList(); } } catch(e) {} }
async function fetchAllPOs() { try { const res = await fetch('procurement_api.php?action=list_pos'); const data = await res.json(); if(Array.isArray(data)) { purchaseOrders = data; renderPOList(); } } catch(e) {} }
async function fetchInventory() { try { const res = await fetch('procurement_api.php?action=list_inventory'); const data = await res.json(); if(Array.isArray(data)) { masterInventory = data; renderInventoryList(); } } catch(e) {} }
async function fetchDispatchLogs() { try { const res = await fetch('procurement_api.php?action=list_dispatch'); const data = await res.json(); if(Array.isArray(data)) { dispatchLogs = data; } } catch(e) {} }

async function fetchAuditLogs() { 
    try { 
        const res = await fetch('procurement_api.php?action=list_audit_logs'); 
        if(res.ok) { 
            const logs = await res.json(); globalAuditLogs = logs; 
            const tbody = document.getElementById('auditTableBody'); 
            if(tbody) {
                tbody.innerHTML = logs.map(log => `<tr><td style="color: var(--text-muted); font-size: 0.85rem;">${new Date(log.timestamp).toLocaleString()}</td><td><strong>${log.username}</strong></td><td><span style="font-size: 0.8rem; font-weight:bold; background:var(--accent-soft); padding:4px 8px; color:white;">${log.action_type}</span></td><td style="white-space: normal;">${log.details}</td></tr>`).join(''); 
                initPagination('tbl-audit');
            }
        } 
    } catch(e) {} 
}

async function fetchReverseLogistics() { 
    try { 
        const res = await fetch('procurement_api.php?action=list_reverse_logistics'); 
        if(res.ok) { const data = await res.json(); if(Array.isArray(data)) { reverseLogisticsLogs = data; renderDisposedList(); } }
    } catch(e) {} 
}

// ==========================================
// RENDERERS 
// ==========================================
function patchAdminStoresGridDisplay() {
    const tbody = document.getElementById('storeTableBody');
    if(!tbody || !Array.isArray(retailStores)) return;
    tbody.innerHTML = retailStores.map(s => {
        const isMisaligned = s.brand_code === 'Active' || s.brand_code === 'New Store';
        let cityGeo = s.city || '-';
        if(isMisaligned && (cityGeo === 'Footwear' || cityGeo === 'Fashion' || cityGeo === 'Sports')) cityGeo = 'Mapped Node'; 
        return `<tr><td><strong>${s.id}</strong></td><td>${s.name || 'Unnamed Node'}</td><td><strong>${isMisaligned ? s.name : (s.brand || '-')}</strong> <span style="color:var(--text-muted); font-size:0.8rem;">(${isMisaligned ? (s.brand || '-') : (s.brand_code || '-')})${isMisaligned ? ` [${s.brand_code}]` : ''}</span></td><td>${s.mall || '-'} / ${s.entity || '-'}</td><td>${cityGeo} <br><span style="font-size:0.8rem; font-weight:bold; color:var(--accent-primary);">Route Code: ${s.route_code && s.route_code !== 'NULL' ? s.route_code : 'Unassigned'}</span></td></tr>`;
    }).join('');
    initPagination('tbl-stores');
}

function renderStorePortalInventoryOnly() {
    const inventoryTbody = document.getElementById('storeInventoryTableBody');
    if(!inventoryTbody) return;
    const myAllocatedDispatches = dispatchLogs.filter(log => String(log.store_code) === String(currentUser));
    if(myAllocatedDispatches.length === 0) {
        inventoryTbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted); font-style:italic;">No products assigned to this location.</td></tr>`;
        return;
    }
    inventoryTbody.innerHTML = myAllocatedDispatches.map(log => {
        const invRef = masterInventory.find(i => i.sku === log.sku) || {};
        return `<tr><td><strong>${log.dispatch_id || log.id}</strong></td><td><span style="font-family:monospace; font-weight:600; color:var(--accent-primary);">${log.sku}</span></td><td><strong>${log.item_name || invRef.item_name || 'Classified Stock Item'}</strong></td><td><span class="status-badge status-dispatched">${log.category || invRef.category || 'General'}</span></td><td><strong style="color:var(--success); font-size:1rem;">${log.dispatch_qty || log.qty} Units</strong></td><td><button onclick="viewDispatchDetails('${log.dispatch_id || log.id}')" class="btn-create" style="padding:6px 12px; font-size:0.8rem;"><i class="fas fa-eye"></i> View Manifest</button></td></tr>`;
    }).join('');
    initPagination('tbl-store-inv');
}

function renderStorePortalRequestSession() {
    const catalogTbody = document.getElementById('storeCatalogTableBody');
    if(!catalogTbody || !Array.isArray(masterInventory)) return;
    catalogTbody.innerHTML = masterInventory.map(item => {
        const isConsumable = (item.item_name || '').toLowerCase().match(/label|ribbon|paper|roll|tape/);
        const typeBadge = isConsumable ? `<span class="status-badge status-pending" style="background:#451a03; color:#fbbf24;"><i class="fas fa-vial"></i> Consumable</span>` : `<span class="status-badge status-approved" style="background:#064e3b; color:#34d399;"><i class="fas fa-box"></i> Non-Consumable</span>`;
        return `<tr><td><span style="font-family:monospace; font-weight:600;">${item.sku}</span></td><td><strong>${item.item_name}</strong><br><span style="font-size:0.8rem; color:var(--text-muted);">Cat: ${item.category || '-'}</span></td><td>${typeBadge}</td><td><strong>${item.quantity_on_hand || 0} Units</strong> in Warehouse</td><td><input type="number" min="0" max="${item.quantity_on_hand || 0}" class="store-portal-req-input" data-sku="${item.sku}" data-name="${item.item_name}" placeholder="0" style="width:90px; text-align:center;"></td></tr>`;
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
            statusPanelWrapper.innerHTML = `<p style="color:var(--text-muted); font-style:italic;">No historical requests processed.</p>`;
            return;
        }
        statusPanelWrapper.innerHTML = myRequests.map(req => {
            let itemLines = []; try { itemLines = JSON.parse(req.details || '[]'); } catch(e) {}
            const summaryString = itemLines.map(i => `• ${i.qty}x [${i.sku}] ${i.name}`).join('<br>');
            
            const currentStatus = req.status || 'Pending';
            let badgeHtml = `<span class="status-badge status-pending" style="border:1px solid #f59e0b;"><i class="fas fa-hourglass-start"></i> Awaiting Approval</span>`;
            if (currentStatus === 'Approved') badgeHtml = `<span class="status-badge status-approved"><i class="fas fa-check"></i> Approved</span>`;
            if (currentStatus === 'Rejected') badgeHtml = `<span class="status-badge status-pending"><i class="fas fa-times"></i> Rejected</span>`;

            return `<div style="background:var(--bg-surface); border:1px solid var(--border-sharp); padding:16px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;"><div><span style="font-size:0.75rem; color:var(--text-muted); font-weight:600;"><i class="far fa-clock"></i> ${new Date(req.timestamp).toLocaleString()}</span><p style="margin-top:6px; font-size:0.9rem; font-weight:500;">${summaryString}</p></div><div>${badgeHtml}</div></div>`;
        }).join('');
    } catch(err) {}
}

async function submitStoreRequest() {
    const inputNodes = document.querySelectorAll('.store-portal-req-input');
    const cargoPayload = [];
    inputNodes.forEach(input => {
        const demandQuantity = parseInt(input.value || 0);
        if(demandQuantity > 0) cargoPayload.push({ sku: input.getAttribute('data-sku'), name: input.getAttribute('data-name'), qty: demandQuantity });
    });
    if(cargoPayload.length === 0) return showToast("Specify item quantities before submitting.");

    try {
        const res = await fetch('procurement_api.php?action=request_inventory', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ storeId: currentUser, items: cargoPayload }) });
        const data = await res.json();
        if(data.status === 'success') { showToast('Procurement request sent.'); inputNodes.forEach(i => i.value = ''); renderStorePortalRequestHistory(); }
    } catch(err) { showToast("Gateway transmission timed out."); }
}

// ==========================================
// ADMIN STORE REQUESTS 
// ==========================================
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
                    tbodyPending.innerHTML = `<tr><td colspan="4" style="padding:24px; text-align:center; color:var(--text-muted); font-style:italic;">No store requests awaiting processing.</td></tr>`;
                } else {
                    tbodyPending.innerHTML = pendingRequests.map(req => {
                        let parsedItems = []; try { parsedItems = JSON.parse(req.details || '[]'); } catch(e) {}
                        const itemsSummaryList = parsedItems.map(i => `<span style="display:inline-block; background:var(--bg-elevated); padding:4px 8px; font-size:0.85rem; margin:2px; border:1px solid var(--accent-primary); color: white;"><b>${i.qty}x</b> ${i.sku} (${i.name})</span>`).join(' ');
                        return `<tr><td style="color:var(--text-muted); font-size:0.85rem;">${new Date(req.timestamp).toLocaleString()}</td><td><span class="status-badge status-dispatched">Store ID: ${req.username}</span></td><td style="white-space: normal; min-width: 300px;">${itemsSummaryList}</td><td style="display:flex; gap:8px;"><button onclick="approveStoreDemand(${req.id}, '${req.username}', \`${req.details.replace(/"/g, '&quot;')}\`)" class="btn-create" style="padding:6px 12px; font-size:0.8rem;"><i class="fas fa-check"></i> Approve</button><button onclick="rejectStoreDemand(${req.id}, '${req.username}')" class="btn-cancel" style="padding:6px 12px; font-size:0.8rem; border-color:var(--danger); color:var(--danger);"><i class="fas fa-times"></i> Reject</button></td></tr>`;
                    }).join('');
                }
                initPagination('tbl-req-pending');
            }

            const tbodyHistory = document.getElementById('adminProcessedRequestsTableBody');
            if (tbodyHistory) {
                if (processedRequests.length === 0) {
                    tbodyHistory.innerHTML = `<tr><td colspan="4" style="padding:24px; text-align:center; color:var(--text-muted); font-style:italic;">No historical processed requests found.</td></tr>`;
                } else {
                    tbodyHistory.innerHTML = processedRequests.map(req => {
                        let parsedItems = []; try { parsedItems = JSON.parse(req.details || '[]'); } catch(e) {}
                        const itemsSummaryList = parsedItems.map(i => `<span style="display:inline-block; background:var(--bg-elevated); padding:4px 8px; font-size:0.8rem; margin:2px; color: var(--text-secondary);"><b>${i.qty}x</b> ${i.sku}</span>`).join(' ');
                        const badge = req.status === 'Approved' ? `<span class="status-badge status-approved"><i class="fas fa-check"></i> Approved</span>` : `<span class="status-badge status-pending"><i class="fas fa-times"></i> Rejected</span>`;
                        return `<tr><td style="color:var(--text-muted); font-size:0.85rem;">${new Date(req.timestamp).toLocaleString()}</td><td><span class="status-badge status-dispatched">Store ID: ${req.username}</span></td><td style="white-space: normal; min-width: 300px;">${itemsSummaryList}</td><td>${badge}</td></tr>`;
                    }).join('');
                }
                initPagination('tbl-req-history');
            }
        }
    } catch(e) {}
}

async function rejectStoreDemand(requestId, storeId) {
    if(!confirm(`Are you sure you want to REJECT the request from Store [${storeId}]?`)) return;
    try {
        const res = await fetch('procurement_api.php?action=reject_store_request', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ requestId, storeId, username: currentUser }) });
        const data = await res.json();
        if(data.status === 'success') { showToast('Request Rejected.'); fetchAdminStoreRequests(); }
    } catch(err) { showToast('Gateway communication error.'); }
}

async function approveStoreDemand(requestId, storeId, itemsArray) {
    if (typeof itemsArray === 'string') { try { itemsArray = JSON.parse(itemsArray); } catch(e) { itemsArray = []; } }
    if (!confirm(`Construct automated dispatch and release items from warehouse for Store: [${storeId}]?`)) return;

    const payload = { dispatchId: generateDispatchID(), storeCode: storeId, pName: "N/A", pId: "N/A", pDept: "N/A", username: currentUser, requestId: requestId, items: itemsArray.map(i => ({ sku: i.sku, qty: i.qty })) };

    try {
        const res = await fetch('procurement_api.php?action=approve_store_request', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('Store request approved. Transfer manifest created.');
            await initApp(); 
        } else { showToast("Database Refusal: " + data.message); }
    } catch(err) { showToast('Gateway communication error.'); }
}

function renderVendorList() {
    const tbody = document.getElementById('vendorTableBody');
    if(tbody) {
        tbody.innerHTML = approvedVendors.map(v => `<tr><td><strong>${v.company_name || v.company}</strong></td><td>${v.tax_id || v.taxId || '-'}</td><td>${v.payment_terms || v.terms || '-'}</td><td>${v.lead_time || v.lead || '-'}</td><td>${v.contact_email || v.email || '-'}</td><td><span class="status-badge status-approved">Approved</span></td><td class="action-icons"><i class="fas fa-trash-alt" onclick="removeVendor('${v.id}', '${v.company_name || v.company}')"></i></td></tr>`).join('');
        initPagination('tbl-vendors');
    }
    const vSelect = document.getElementById('vendorSelect');
    if(vSelect) vSelect.innerHTML = '<option value="">-- Select Vendor --</option>' + approvedVendors.map(v => `<option value="${v.company_name || v.company}">${v.company_name || v.company}</option>`).join('');
}

function renderPersonnelList() {
    const tbody = document.getElementById('userTableBody');
    if(tbody) { 
        tbody.innerHTML = personnelList.map(u => `<tr><td><strong>${u.emp_id}</strong></td><td>${u.emp_name}</td><td>${u.department || '-'}</td><td class="action-icons"><i class="fas fa-trash-alt" onclick="removePersonnel('${u.emp_id}')"></i></td></tr>`).join(''); 
        initPagination('tbl-users');
    }
}

function renderInventoryList() {
    const tbody = document.getElementById('inventoryTableBody');
    if(!tbody) return;
    tbody.innerHTML = masterInventory.map(item => {
        const descLower = (item.item_name || '').toLowerCase();
        const isConsumable = descLower.match(/label|ribbon|paper|roll|tape/);
        const typeBadge = isConsumable ? `<span class="status-badge status-partial">Consumable</span>` : `<span class="status-badge status-dispatched">Asset</span>`;
        return `<tr><td><strong>${item.sku}</strong></td><td>${item.category || '-'}</td><td><strong>${item.item_name || item.name}</strong><br><span style="font-size:0.8rem; color:var(--text-muted);">Model: ${item.model_number || 'N/A'}</span></td><td><strong style="color: ${item.quantity_on_hand > 0 ? 'var(--success)' : 'var(--danger)'};">${item.quantity_on_hand || 0} Units</strong></td><td>${typeBadge}</td></tr>`;
    }).join('');
    initPagination('tbl-inv');
}

function renderPOList() {
    const tbody = document.getElementById('poTableBody');
    if(!tbody) return;
    tbody.innerHTML = purchaseOrders.map(po => {
        let statusClass = 'status-pending'; 
        if (po.status === 'Completed') statusClass = 'status-approved';
        else if (po.status === 'Partial') statusClass = 'status-partial';
        else if (po.status === 'Approved') statusClass = 'status-dispatched';

        const firstItem = (po.lineItems && po.lineItems.length > 0) ? po.lineItems[0] : {};
        let displayDesc = firstItem.description || firstItem.itemName || '-';
        if (po.lineItems && po.lineItems.length > 1) displayDesc += `<br><span style="color:var(--text-muted); font-size: 0.75rem;">(+${po.lineItems.length - 1} more items)</span>`;

        return `<tr><td><strong>${po.po_number || po.poNumber}</strong></td><td>${po.vendor || '-'}</td><td>${po.delivery_note_number || '-'}</td><td>${firstItem.category || '-'}</td><td>${firstItem.model_number || firstItem.modelNumber || '-'}</td><td style="white-space: normal; min-width: 200px;">${displayDesc}</td><td><strong>SAR ${Number(po.grand_total || po.grandTotal).toFixed(2)}</strong><br><span style="font-size:0.75rem; color:var(--text-muted);">${po.lineItems ? po.lineItems.length : 0} lines</span></td><td><span class="status-badge ${statusClass}">${po.status || 'Approved'}</span></td><td>${po.order_date ? new Date(po.order_date).toISOString().slice(0,10) : po.orderDate}</td><td class="action-icons" style="white-space: nowrap; display: flex; gap: 16px; align-items: center; border-bottom: none;"><i class="fas fa-box-open" title="Receive Goods" style="color:var(--success); font-size:1.1rem; cursor:pointer;" onclick="openReceivePOModal('${po.id}')"></i><i class="fas fa-edit" title="Edit References" style="color:var(--accent-primary); font-size:1.1rem; cursor:pointer;" onclick="openEditPOModal('${po.id}')"></i><i class="fas fa-trash-alt" title="Delete" style="color:var(--danger); font-size:1.1rem; cursor:pointer;" onclick="deletePO('${po.id}', '${po.po_number || po.poNumber}')"></i></td></tr>`;
    }).join('');
    initPagination('tbl-po');
}

function renderStoreStockView() {
    const storeInput = document.getElementById('stockStoreInput');
    if (!storeInput) return;
    const match = storeInput.value.match(/\[(\d+)\]/);
    const storeId = match ? match[1] : storeInput.value;

    const tbody = document.getElementById('storeStockTableBody');
    if(!tbody) return;
    if(!storeId) { tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:16px; color:var(--text-muted);">Select a retail node target location to view active stock mapping.</td></tr>`; return; }

    const storeLogs = dispatchLogs.filter(log => String(log.store_code) === String(storeId));
    const stockMap = {};
    storeLogs.forEach(log => {
        if(!stockMap[log.sku]) stockMap[log.sku] = { name: log.item_name || 'Stock Cargo', qty: 0, lastDate: log.dispatched_at };
        stockMap[log.sku].qty += parseInt(log.dispatch_qty || log.qty || 0);
        if(new Date(log.dispatched_at) > new Date(stockMap[log.sku].lastDate)) stockMap[log.sku].lastDate = log.dispatched_at;
    });

    if(Object.keys(stockMap).length === 0) { tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:16px; color:var(--text-muted);">No recorded active stock allocated to this unit target node.</td></tr>`; return; }

    tbody.innerHTML = Object.entries(stockMap).map(([sku, data]) => `<tr><td><strong style="color:var(--accent-primary); font-family:monospace;">${sku}</strong></td><td><strong>${data.name}</strong></td><td><strong style="color:var(--success);">${data.qty} Units</strong></td><td>${data.lastDate ? new Date(data.lastDate).toLocaleDateString() : 'N/A'}</td></tr>`).join('');
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

    if (uniqueDispatches.length === 0) { tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:20px; color:var(--text-muted);">No dispatch manifests logged.</td></tr>`; return; }

    tbody.innerHTML = uniqueDispatches.map(log => {
        const id = log.dispatch_id || log.id;
        let displayScope = '-'; 
        if (log.store_code && log.store_code !== "N/A") {
            const matchedStore = retailStores.find(s => String(s.id) === String(log.store_code));
            displayScope = `<span class="status-badge status-dispatched">${matchedStore ? matchedStore.name : log.store_code}</span>`;
        } else if (log.person_name && log.person_name !== "N/A") {
            displayScope = `<span class="status-badge status-pending"><i class="fas fa-user"></i> ${log.person_name}</span>`;
        }
        return `<tr><td><strong>${id}</strong></td><td>${displayScope}</td><td><strong>${dispatchItemCounts[id].size} Items</strong> <span class="status-badge status-approved" style="margin-left:8px;">${dispatchTotals[id]} Units</span></td><td>${log.dispatched_at ? new Date(log.dispatched_at).toLocaleDateString() : 'Recent'}</td><td class="action-icons" style="white-space: nowrap;"><button onclick="viewDispatchDetails('${id}')" title="View & Print" style="color:var(--accent-primary);"><i class="fas fa-eye"></i></button></td></tr>`;
    }).join('');
    initPagination('tbl-dispatch');
}

function renderDisposedList() {
    const tbody = document.getElementById('disposedTableBody');
    if (!tbody) return;
    const disposed = reverseLogisticsLogs.filter(log => log.action_type === 'Dispose');
    if (disposed.length === 0) { tbody.innerHTML = `<tr><td colspan="6" style="padding:24px; text-align:center; color:var(--text-muted); font-style:italic;">No disposed items recorded.</td></tr>`; return; }
    tbody.innerHTML = disposed.map(log => `<tr><td style="color:var(--text-muted); font-size:0.85rem;">${new Date(log.timestamp || log.created_at || Date.now()).toLocaleDateString()}</td><td><span class="status-badge status-dispatched">Store ID: ${log.store_id}</span></td><td><strong>${log.item_name}</strong><br><span style="font-size:0.8rem; color:var(--text-muted);">${log.sku || 'N/A'}</span></td><td><strong style="color:var(--danger);">${log.qty} Units</strong></td><td style="white-space: normal; min-width: 200px;">${log.remarks || '-'}</td><td>${log.processed_by || 'Admin'}</td></tr>`).join('');
    initPagination('tbl-disposed');
}

// ==========================================
// PO LOGIC & MODALS
// ==========================================
function openEditPOModal(poId) {
    const po = purchaseOrders.find(p => String(p.id) === String(poId));
    if(!po) return;
    document.getElementById('editPoId').value = po.id;
    document.getElementById('editPoNumber').value = (po.po_number || po.poNumber).includes('PENDING') ? '' : (po.po_number || po.poNumber);
    document.getElementById('editPoDeliveryNote').value = po.delivery_note_number || '';
    document.getElementById('editPoInvoice').value = po.invoice_number || '';
    document.getElementById('editPoModal').classList.add('active');
}

async function submitEditPO(e) {
    e.preventDefault();
    const id = document.getElementById('editPoId').value;
    const poNumber = document.getElementById('editPoNumber').value.trim() || 'PENDING-' + Date.now().toString().slice(-6);
    const deliveryNote = document.getElementById('editPoDeliveryNote').value.trim();
    const invoiceNumber = document.getElementById('editPoInvoice').value.trim();
    try {
        const res = await fetch('procurement_api.php?action=update_po', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ id, poNumber, deliveryNote, invoiceNumber, username: currentUser }) });
        const data = await res.json();
        if(data.status === 'success') { showToast('Ledger references updated.'); closeModal('editPoModal'); initApp(); } else { showToast("Update Error: " + data.message); }
    } catch(err) { showToast('Database communication failure.'); }
}

function openReceivePOModal(poId) {
    const po = purchaseOrders.find(p => String(p.id) === String(poId));
    if(!po) return;
    document.getElementById('recvPoId').value = po.id;
    document.getElementById('recvPoTitle').innerText = `Receiving: ${po.po_number || po.poNumber}`;
    const tbody = document.getElementById('receivePoTableBody');
    tbody.innerHTML = (po.lineItems || []).map(item => {
        const remaining = parseInt(item.quantity) - parseInt(item.received_quantity || 0);
        return `<tr class="recv-row"><td class="recv-sku">${item.sku}</td><td>${item.item_name || item.itemName}</td><td>${item.quantity}</td><td>${item.received_quantity || 0}</td><td><input type="number" class="recv-input" min="0" max="${remaining}" value="${remaining > 0 ? remaining : 0}" ${remaining === 0 ? 'disabled' : ''} style="width:70px; text-align:center; padding: 6px; border: 1px solid var(--border-sharp); background: var(--bg-surface); color: white;"></td></tr>`;
    }).join('');
    document.getElementById('receivePoModal').classList.add('active');
}

async function submitPOReceipt(e) {
    e.preventDefault();
    const poId = document.getElementById('recvPoId').value;
    const rows = document.querySelectorAll('.recv-row');
    const items = [];
    rows.forEach(row => { const input = row.querySelector('.recv-input'); if(input && !input.disabled) items.push({ sku: row.querySelector('.recv-sku').innerText, recv_qty: parseInt(input.value || 0) }); });
    try {
        const res = await fetch('procurement_api.php?action=receive_po', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ poId, items, username: currentUser }) });
        const data = await res.json();
        if(data.status === 'success') { showToast('PO Received Successfully'); closeModal('receivePoModal'); initApp(); }
    } catch(err) { showToast('DB communication failure.'); }
}

function viewDispatchDetails(dispatchId) { 
    document.getElementById('detailDispatchId').innerText = dispatchId; 
    const tbody = document.getElementById('dispatchDetailsTableBody'); 
    const items = dispatchLogs.filter(log => (log.dispatch_id || log.id) === dispatchId); 
    if(items.length > 0) {
        const first = items[0];
        const storeMatch = retailStores.find(s => String(s.id) === String(first.store_code));
        if (storeMatch) {
            document.getElementById('detailRecipient').innerHTML = `${storeMatch.name} [ID: ${storeMatch.id}]<br><strong>Brand:</strong> ${storeMatch.brand || '-'} (${storeMatch.brand_code || '-'})<br><strong>Entity/Mall:</strong> ${storeMatch.entity || '-'} / ${storeMatch.mall || '-'}<br><strong>Location:</strong> ${storeMatch.city || '-'} | <strong>Route:</strong> ${storeMatch.route_code || 'Unassigned'}`;
        } else { document.getElementById('detailRecipient').innerText = first.store_code !== "N/A" ? first.store_code : first.person_name; }
        document.getElementById('detailDate').innerText = first.dispatched_at ? new Date(first.dispatched_at).toLocaleString() : 'Recent';
    }
    tbody.innerHTML = items.map(item => {
        const masterItem = masterInventory.find(mi => mi.sku === item.sku);
        const vendorDisplay = masterItem && masterItem.vendor ? masterItem.vendor : '-';
        return `<tr><td><span style="font-family:monospace; font-weight:600; color:var(--accent-primary);">${item.sku}</span></td><td><strong>${item.item_name || (masterItem ? masterItem.item_name : 'Unknown Item')}</strong></td><td>${vendorDisplay}</td><td><span style="font-weight:800;">${item.dispatch_qty || item.qty}</span></td></tr>`;
    }).join(''); 
    document.getElementById('printReceiptBtn').onclick = () => printDispatchManifest(dispatchId); document.getElementById('dispatchDetailsModal').classList.add('active'); 
}

function printDispatchManifest(dispatchId) {
    const content = document.getElementById('receiptContent').innerHTML;
    const printWin = window.open('', '', 'width=600,height=800');
    printWin.document.write(`<html><head><style>body { font-family: monospace; padding: 20px; color: #000; background: #fff; } table { width: 100%; border-collapse: collapse; margin-top: 20px; } th, td { border-bottom: 1px dashed #ccc; padding: 8px 0; text-align: left; } h2 { text-align: center; border-bottom: 2px dashed #000; padding-bottom: 10px; }</style></head><body>${content}<script>window.print();<\/script></body></html>`);
    printWin.document.close();
}

function generateDispatchID() {
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

function openDispatchFlow() { dispatchCart = []; document.getElementById('dispatchFlowModal').classList.add('active'); goToDispatchStep1(); }
function toggleAssigneeType() { const isStore = document.querySelector('input[name="assigneeType"]:checked').value === 'store'; document.getElementById('storeFields').style.display = isStore ? 'block' : 'none'; document.getElementById('personFields').style.display = !isStore ? 'block' : 'none'; }
function updateFields(store) { document.getElementById('dfStoreBrand').value = store.brand || ''; document.getElementById('dfStoreCode').value = store.brand_code || ''; document.getElementById('dfStoreEntity').value = (store.mall || '') + " / " + (store.entity || ''); document.getElementById('dfStoreRoute').value = store.route_code || 'Unassigned'; }
function clearStoreFields() { document.getElementById('dfStoreBrand').value = ''; document.getElementById('dfStoreCode').value = ''; document.getElementById('dfStoreEntity').value = ''; document.getElementById('dfStoreRoute').value = ''; }
function autofillFromId() { const id = document.getElementById('dfStoreId').value.trim(); const store = retailStores.find(s => String(s.id) === String(id)); if(store) { document.getElementById('dfStoreInput').value = `${store.name} [${store.id}]`; updateFields(store); } else { clearStoreFields(); } }
function autofillFromDatalist(inputId, targetIdField) { const val = document.getElementById(inputId).value; const match = val.match(/\[(\d+)\]/); if(match) { document.getElementById(targetIdField).value = match[1]; autofillFromId(); } else { document.getElementById(targetIdField).value = ''; clearStoreFields(); } }
function populateStoreSelects() { const storesHtml = '<option value="">-- Choose Target --</option>' + retailStores.map(s => `<option value="${s.id}">${s.name || 'Unnamed'} [${s.id}]</option>`).join(''); if(document.getElementById('storeDatalist')) document.getElementById('storeDatalist').innerHTML = retailStores.map(s => `<option value="${s.name} [${s.id}]">`).join(''); if(document.getElementById('rlStoreSelect')) document.getElementById('rlStoreSelect').innerHTML = storesHtml; if(document.getElementById('poAssignedStore')) document.getElementById('poAssignedStore').innerHTML = '<option value="Warehouse">-- Keep in Central Warehouse --</option>' + storesHtml; }
function populatePersonSelects() { const sel = document.getElementById('dfPersonSelect'); if(sel) sel.innerHTML = '<option value="">-- Choose Personnel --</option>' + personnelList.map(p => `<option value="${p.emp_id}">${p.emp_name} (${p.emp_id})</option>`).join(''); }
function autofillPersonDetails() { const val = document.getElementById('dfPersonSelect').value; const person = personnelList.find(p => p.emp_id == val); if(person) { document.getElementById('dfPersonId').value = person.emp_id; document.getElementById('dfPersonName').value = person.emp_name; document.getElementById('dfPersonDept').value = person.department || ''; } else { document.getElementById('dfPersonId').value = ''; document.getElementById('dfPersonName').value = ''; document.getElementById('dfPersonDept').value = ''; } }

function goToDispatchStep1() { document.getElementById('dispatchStep2').style.display = 'none'; document.getElementById('dispatchStep1').style.display = 'block'; document.getElementById('dotStep2').style.color = 'var(--text-muted)'; document.getElementById('dotStep2').style.fontWeight = 'normal'; document.getElementById('dotStep1').style.color = 'var(--text-primary)'; document.getElementById('dotStep1').style.fontWeight = 'bold'; }
function goToDispatchStep2() { const isStore = document.querySelector('input[name="assigneeType"]:checked').value === 'store'; if(isStore && !document.getElementById('dfStoreId').value) return showToast("Please map target store before continuing."); document.getElementById('dispatchStep1').style.display = 'none'; document.getElementById('dispatchStep2').style.display = 'block'; document.getElementById('dotStep1').style.color = 'var(--text-muted)'; document.getElementById('dotStep1').style.fontWeight = 'normal'; document.getElementById('dotStep2').style.color = 'var(--text-primary)'; document.getElementById('dotStep2').style.fontWeight = 'bold'; searchDispatchItems(); renderDispatchCart(); }

function searchDispatchItems() { const query = document.getElementById('dispatchSearch').value.toLowerCase(); let results = masterInventory; if(query) results = masterInventory.filter(i => i.sku.toLowerCase().includes(query) || (i.category && i.category.toLowerCase().includes(query)) || (i.item_name && i.item_name.toLowerCase().includes(query))); renderDispatchSearchResults(results.slice(0, 10)); }
function renderDispatchSearchResults(items) { const container = document.getElementById('dispatchSearchResults'); if(!container) return; if(items.length === 0) { container.innerHTML = '<p style="padding: 10px; color:var(--text-muted); font-size:0.9rem;">Zero active inventory matches.</p>'; return; } const isStore = document.querySelector('input[name="assigneeType"]:checked') && document.querySelector('input[name="assigneeType"]:checked').value === 'store'; const targetStoreId = document.getElementById('dfStoreId') ? document.getElementById('dfStoreId').value : null; container.innerHTML = items.map(item => { let warningBadge = ''; if (isStore && targetStoreId) { const alreadyAssigned = dispatchLogs.some(log => String(log.store_code) === String(targetStoreId) && String(log.sku) === String(item.sku)); if (alreadyAssigned) warningBadge = `<span style="background: rgba(245, 158, 11, 0.15); color: var(--warning); border: 1px solid #b45309; padding: 2px 8px; font-size: 0.7rem; font-weight: 700; margin-left: 10px; letter-spacing: 0.5px;"><i class="fas fa-exclamation-triangle"></i> PREVIOUSLY ASSIGNED</span>`; } return `<div class="item-row" style="padding: 10px; border-bottom: 1px solid var(--border-sharp); background: var(--bg-surface); margin-bottom: 8px;"><div style="display: flex; justify-content: space-between; align-items: center; width: 100%;"><div><strong>${item.item_name || item.name}</strong> <span style="color:var(--text-muted); font-size:0.85rem;">(${item.sku})</span> ${warningBadge}<br><span style="font-size:0.8rem; color:var(--text-secondary); margin-top:4px; display:inline-block;">Available Balance: ${item.quantity_on_hand || item.qty} Units | Category: ${item.category || '-'}</span></div><button class="btn-create" style="padding: 6px 16px; font-size: 0.85rem;" onclick="addToDispatchCart('${item.sku}')"><i class="fas fa-plus"></i> Add</button></div></div>`; }).join(''); }
function renderDispatchCart() { const container = document.getElementById('dispatchCartContainer'); if(dispatchCart.length === 0) { container.innerHTML = '<p style="color: var(--text-muted); font-size: 0.9rem; font-style: italic;">No items staged.</p>'; return; } container.innerHTML = dispatchCart.map(item => `<div class="cart-row"><div><strong>${item.item_name || item.name}</strong> <span style="font-size:0.85rem; color:var(--text-muted);">(${item.sku})</span></div><div><input type="number" min="1" max="${item.quantity_on_hand || item.qty}" value="${item.dispatchQty}" onchange="updateCartQty('${item.sku}', this.value)" style="width: 100%; padding: 8px; border: 1px solid var(--border-sharp); background: var(--bg-main); color: white;"></div><i class="fas fa-trash-alt" style="color: var(--danger); cursor:pointer;" onclick="removeFromCart('${item.sku}')"></i></div>`).join(''); }
function addToDispatchCart(sku) { const item = masterInventory.find(i => i.sku === sku); if(!item) return; const existing = dispatchCart.find(i => i.sku === sku); if(existing) { if(existing.dispatchQty < (item.quantity_on_hand || item.qty)) existing.dispatchQty += 1; else showToast("Warehouse balance exhausted."); } else { if((item.quantity_on_hand || item.qty) > 0) dispatchCart.push({...item, dispatchQty: 1}); else showToast("Item marked depletion."); } renderDispatchCart(); }
function updateCartQty(sku, value) { const item = dispatchCart.find(i => i.sku === sku); const masterItem = masterInventory.find(i => i.sku === sku); if(item && masterItem) { let val = parseInt(value) || 1; const max = masterItem.quantity_on_hand || masterItem.qty; if(val > max) val = max; if(val < 1) val = 1; item.dispatchQty = val; renderDispatchCart(); } }
function removeFromCart(sku) { dispatchCart = dispatchCart.filter(i => i.sku !== sku); renderDispatchCart(); }

async function submitDispatch() {
    if(dispatchCart.length === 0) return showToast("Append products to transfer payload.");
    const isStore = document.querySelector('input[name="assigneeType"]:checked').value === 'store';
    const payload = { dispatchId: generateDispatchID(), storeCode: isStore ? document.getElementById('dfStoreId').value : "N/A", pName: !isStore ? "N/A" : "N/A", pId: !isStore ? "N/A" : "N/A", pDept: !isStore ? "N/A" : "N/A", username: currentUser, items: dispatchCart.map(i => ({ sku: i.sku, qty: i.dispatchQty })) };
    try {
        const res = await fetch('procurement_api.php?action=create_dispatch', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json();
        if(data.status === 'success') { showToast('Dispatch Handshake Complete'); await fetchInventory(); await fetchDispatchLogs(); await fetchAuditLogs(); closeModal('dispatchFlowModal'); } else { showToast("Database Refusal: " + data.message); }
    } catch(err) { showToast('Connection Error. Payload failed.'); }
}

// ==========================================
// REVERSE LOGISTICS ENGINE (STORE CLOSURE)
// ==========================================
let rvSequenceCounter = 1;

function generateReverseLogisticsSKU() {
    const today = new Date();
    const d = String(today.getDate()).padStart(2, '0');
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const y = String(today.getFullYear()).slice(-2);
    const suffix = String(rvSequenceCounter++).padStart(3, '0');
    return `SKURV${d}${m}${y}-${suffix}`;
}

function addReverseLogisticsRow() {
    const container = document.getElementById('rlItemsContainer');
    if(!container) return;
    const div = document.createElement('div'); div.className = 'dynamic-field-row';
    div.style.display = 'grid'; div.style.gridTemplateColumns = '1.5fr 1fr 1.5fr 1fr 1fr 1fr auto'; div.style.gap = '12px'; div.style.alignItems = 'center'; div.style.background = 'var(--bg-elevated)'; div.style.padding = '12px'; div.style.border = '1px solid var(--border-sharp)'; div.style.marginBottom = '8px';
    div.innerHTML = `
        <input type="text" class="rl-name" placeholder="Item Name" required style="width:100%; padding:10px; border:1px solid var(--border-sharp); background:var(--bg-main); color:white;">
        <input type="text" class="rl-category" placeholder="Category" required style="width:100%; padding:10px; border:1px solid var(--border-sharp); background:var(--bg-main); color:white;">
        <input type="text" class="rl-desc" placeholder="Description" style="width:100%; padding:10px; border:1px solid var(--border-sharp); background:var(--bg-main); color:white;">
        <input type="text" class="rl-model" placeholder="Model No." style="width:100%; padding:10px; border:1px solid var(--border-sharp); background:var(--bg-main); color:white;">
        <input type="number" class="rl-qty" placeholder="Qty" min="1" required style="width:100%; padding:10px; border:1px solid var(--border-sharp); background:var(--bg-main); color:white;">
        <select class="rl-action" required style="width:100%; padding:10px; border:1px solid var(--border-sharp); background:var(--bg-main); color:white;">
            <option value="Reuse">Reuse (To WH)</option>
            <option value="Dispose">Dispose</option>
        </select>
        <button type="button" class="btn-cancel" onclick="this.parentElement.remove()" style="border: none; color: var(--danger); background:transparent;"><i class="fas fa-trash"></i></button>
    `;
    container.appendChild(div);
}

async function submitReverseLogistics(e) {
    e.preventDefault();
    
    const storeInputVal = document.getElementById('rlStoreInput').value;
    const match = storeInputVal.match(/\[(\d+)\]/);
    if (!match) return showToast("Please select a valid store from the dropdown.");
    const storeId = match[1];

    const rows = document.querySelectorAll('#rlItemsContainer .dynamic-field-row');
    const items = [];
    
    rows.forEach(row => {
        const generatedSKU = generateReverseLogisticsSKU();
        items.push({
            name: row.querySelector('.rl-name').value.trim(),
            category: row.querySelector('.rl-category').value.trim(),
            desc: row.querySelector('.rl-desc').value.trim() || 'N/A',
            model: row.querySelector('.rl-model').value.trim() || 'N/A',
            qty: parseInt(row.querySelector('.rl-qty').value),
            action: row.querySelector('.rl-action').value,
            sku: generatedSKU,
            remark: 'Generated SKU: ' + generatedSKU
        });
    });

    if(items.length === 0) return showToast("Please add items to process.");

    // STRICT STORE CLOSURE WARNING POPUP
    const warningMsg = `WARNING: You are about to close Store ID [${storeId}].\n\nExecuting this reverse logistics handshake will process the listed items, AND THIS STORE WILL BE PERMANENTLY DELETED FROM THE SYSTEM.\n\nAre you absolutely sure you want to proceed?`;
    
    if(!confirm(warningMsg)) {
        return showToast("Store closure aborted.");
    }

    try {
        // Process Reverse Logistics payload
        const res = await fetch('procurement_api.php?action=process_reverse_logistics', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ storeId, items, username: currentUser })
        });
        const data = await res.json();
        
        if(data.status === 'success') {
            
            // Delete the store from the database
            try {
                await fetch(`procurement_api.php?action=delete_store&id=${storeId}`, {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ username: currentUser })
                });
            } catch(e) {
                console.warn("Store deletion request failed, but logistics were processed.");
            }

            showToast('Store Closed, Items Processed & Store Deleted.');
            document.getElementById('rlItemsContainer').innerHTML = ''; 
            document.getElementById('rlStoreInput').value = '';
            
            // Refresh tables to remove the store and update inventory
            await fetchInventory(); 
            await fetchAuditLogs(); 
            await fetchReverseLogistics();
            await fetchStores(); 
        } else { 
            showToast("DB Error: " + data.message); 
        }
    } catch(err) { 
        showToast('Connection failed.'); 
    }
}

// ==========================================
// PURCHASE ORDERS (MANUAL CREATION & AUTOMATED SKU)
// ==========================================
function generateSKU(category) {
    const catPrefix = (category && category.trim().length >= 2) ? category.trim().substring(0, 2).toUpperCase() : 'GN';
    const today = new Date();
    const d = String(today.getDate()).padStart(2, '0');
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const y = String(today.getFullYear()).slice(-2);
    const uniqueSuffix = Math.floor(100 + Math.random() * 900);
    return `SKU${catPrefix}${d}${m}${y}-${uniqueSuffix}`;
}

function addNewLineItem(cat='', name='', desc='', model='', serial='', qty=1, price=0) {
    const container = document.getElementById('lineItemsContainer');
    const div = document.createElement('div'); div.className = 'line-item';
    div.innerHTML = `
        <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
            <div class="form-group"><label>Category</label><input type="text" class="line-category" value="${cat}" placeholder="e.g. Hardware"></div>
            <div class="form-group"><label>Item Name *</label><input type="text" class="line-name" value="${name}" required></div>
            <div class="form-group"><label>Model Number</label><input type="text" class="line-model" value="${model}" placeholder="Optional"></div>
            <div class="form-group" style="grid-column: span 3;"><label>Description</label><input type="text" class="line-desc" value="${desc}" placeholder="Optional Details"></div>
            <div class="form-group"><label>Serial Number</label><input type="text" class="line-serial" value="${serial}" placeholder="Optional"></div>
            <div class="form-group"><label>Qty *</label><input type="number" class="line-qty" value="${qty}" min="1" required oninput="recalcTotals()"></div>
            <div class="form-group"><label>Unit Price (SAR) *</label><input type="number" step="0.01" class="line-price" value="${price}" required oninput="recalcTotals()"></div>
            <div class="form-group"><label>Line Total</label><input type="text" class="line-total" style="background: var(--bg-main); font-weight: 600;" readonly></div>
            <div class="form-group" style="grid-column: span 2; display: flex; align-items: flex-end;">
                <button type="button" onclick="this.parentElement.parentElement.parentElement.remove(); recalcTotals();" class="btn-cancel" style="width: 100%; border-color: var(--danger); color: var(--danger);"><i class="fas fa-trash"></i> Remove</button>
            </div>
        </div>`;
    container.appendChild(div);
    recalcTotals();
}

function recalcTotals() {
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
}

async function savePO(e) {
    e.preventDefault();
    
    const storeInputVal = document.getElementById('poAssignedStore').value;
    let assignedStoreVal = 'Warehouse';
    if (storeInputVal && storeInputVal !== 'Warehouse') {
        const match = storeInputVal.match(/\[(\d+)\]/);
        assignedStoreVal = match ? match[1] : storeInputVal;
    }

    const poData = {
        id: 'po_' + Date.now(),
        poNumber: document.getElementById('poNumber').value.trim() || 'PENDING-' + Date.now().toString().slice(-6),
        vendor: document.getElementById('vendorSelect') ? document.getElementById('vendorSelect').value : 'Generic',
        deliveryNote: document.getElementById('poDeliveryNote').value,
        invoiceNumber: document.getElementById('poInvoice').value,
        poCategory: document.getElementById('poCategory').value,
        assignedStore: assignedStoreVal,
        orderDate: document.getElementById('orderDate').value,
        department: document.getElementById('department') ? document.getElementById('department').value : '',
        subtotal: parseFloat(document.getElementById('subtotal').value) || 0,
        taxAmount: parseFloat(document.getElementById('taxAmount').value) || 0,
        grandTotal: parseFloat(document.getElementById('grandTotal').value) || 0,
        username: currentUser,
        dynamicAttributes: {},
        lineItems: []
    };

    document.querySelectorAll('.line-item').forEach(item => {
        const itemCategory = item.querySelector('.line-category').value;
        poData.lineItems.push({
            sku: generateSKU(itemCategory),
            category: itemCategory || 'N/A',
            description: item.querySelector('.line-desc').value || 'N/A',
            itemName: item.querySelector('.line-name').value,
            modelNumber: item.querySelector('.line-model').value || 'N/A',
            serialNumber: item.querySelector('.line-serial').value || 'N/A',
            quantity: parseFloat(item.querySelector('.line-qty').value) || 0,
            unitPrice: parseFloat(item.querySelector('.line-price').value) || 0
        });
    });

    try {
        const res = await fetch('procurement_api.php?action=create_po', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(poData)
        });
        const data = await res.json();
        if(data.status === 'success') {
            showToast('PO Logged to Ledger');
            closeModal('poModal');
            initApp();
        } else { showToast("Database Error: " + data.message); }
    } catch(err) { showToast('Connection Error. PO failed to save.'); }
}

async function deletePO(id, poNum) {
    if(confirm('Delete PO permanently from database?')) {
        try {
            await fetch('procurement_api.php?action=delete_po', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id, poNumber: poNum, username: currentUser})
            });
            showToast('PO Purged');
            initApp();
        } catch(e) { showToast('DB Connection Error'); }
    }
}

// ==========================================
// BULK STORE IMPORT & PDF INGESTION ENGINES
// ==========================================
async function handleBulkStoreImport(event) {
    const file = event.target.files[0]; if(!file) return; const reader = new FileReader();
    reader.onload = async function(e) {
        const rows = e.target.result.split('\n').map(row => row.split(','));
        if(rows.length < 2) return showToast("Formatting failure.");
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
            if(result.status === 'success') { showToast(`Imported ${result.imported} stores.`); fetchStores(); }
        } catch(err) { showToast("API stream failure."); }
        document.getElementById('storeBulkCsv').value = "";
    }; reader.readAsText(file);
}

function handleExcelImport(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: 'array' });
        const jsonRows = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);
        
        if(jsonRows.length === 0) return showToast("Template contains no readable record rows.");
        preflightPendingRows = jsonRows;
        
        const templateVendors = [...new Set(jsonRows.map(row => (row['Vendor Name'] || row['vendor'] || '').trim()).filter(Boolean))];
        const registeredNames = approvedVendors.map(v => (v.company_name || v.company || '').toLowerCase().trim());
        preflightMissingVendors = templateVendors.filter(v => !registeredNames.includes(v.toLowerCase().trim()));
        
        if (preflightMissingVendors.length > 0) { triggerPreflightResolutionWizard(); } 
        else { executeSplitPOIngestion(); }
        document.getElementById('excelUpload').value = ""; 
    };
    reader.readAsArrayBuffer(file);
}

function triggerPreflightResolutionWizard() {
    const container = document.getElementById('preflightContainer');
    container.innerHTML = preflightMissingVendors.map((vendor, index) => `
        <div style="background: var(--bg-surface); padding: 20px; border: 1px solid var(--border-sharp); display:grid; grid-template-columns: 2fr 1fr 1fr; gap: 12px; align-items:end;">
            <div class="form-group"><label>Company Name</label><input type="text" class="pf-company" value="${vendor}" readonly style="font-weight:700; background:var(--bg-main); color:white;"></div>
            <div class="form-group"><label>Tax ID / VAT Registration</label><input type="text" class="pf-tax" placeholder="e.g. TRN-93812"></div>
            <div class="form-group"><label>Payment Terms</label><input type="text" class="pf-terms" value="Net 30"></div>
        </div>
    `).join('');
    document.getElementById('preflightVendorModal').classList.add('active');
}

async function commitPreflightVendors() {
    const blocks = document.querySelectorAll('#preflightContainer > div');
    for (let div of blocks) {
        const company = div.querySelector('.pf-company').value.trim();
        const taxId = div.querySelector('.pf-tax').value.trim() || 'N/A';
        const terms = div.querySelector('.pf-terms').value.trim() || 'Net 30';
        const payload = { company, taxId, terms, lead: '5 Days', email: 'operations@vendor.sa', username: currentUser };
        
        try {
            const res = await fetch('procurement_api.php?action=create_vendor', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
            const data = await res.json();
            if(data.status !== 'success') { showToast("Preflight structural registration broken."); return; }
        } catch(err) { return showToast("API transmission broke."); }
    }
    await fetchVendors();
    closeModal('preflightVendorModal');
    executeSplitPOIngestion();
}

async function executeSplitPOIngestion() {
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
        let subtotal = 0;
        const poData = {
            id: 'po_' + Math.floor(1000000 + Math.random() * 9000000), poNumber: generatedPoNo, vendor: vendorName,
            orderDate: new Date().toISOString().slice(0,10), department: rows[0]['Department'] || 'Logistics Warehouse',
            deliveryNote: '', invoiceNumber: '', assignedStore: 'Warehouse', poCategory: 'WHS PO',
            subtotal: 0, taxAmount: 0, grandTotal: 0, username: currentUser,
            dynamicAttributes: { "Import Mode": "Bulk Automated Split Ingestion" }, lineItems: []
        };

        rows.forEach(row => {
            const qty = parseInt(row['QTY'] || row['Quantity'] || 1);
            const price = parseFloat(row['Price'] || row['Unit Price'] || 0);
            subtotal += (qty * price);
            poData.lineItems.push({
                sku: generateSKU(row['Category'] || 'GN'),
                category: row['Category'] || 'General Hardware',
                description: row['Item Description'] || row['Description'] || 'Imported Line Item Context',
                itemName: row['Item Name'] || row['Name'] || 'Unclassified Core Asset',
                modelNumber: row['Model Number'] || 'N/A', serialNumber: row['Serial Number'] || 'Pending Trace',
                quantity: qty, unitPrice: price
            });
        });

        poData.subtotal = subtotal;
        poData.taxAmount = parseFloat((subtotal * 0.15).toFixed(2)); 
        poData.grandTotal = subtotal + poData.taxAmount;

        try {
            const res = await fetch('procurement_api.php?action=create_po', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(poData) });
            const result = await res.json();
            if(result.status === 'success') successCount++;
        } catch(e) { console.warn("Network crash during splits.", e); }
    }
    showToast(`Successfully created ${successCount} distinct Vendor PO records.`);
    initApp();
}

async function handlePDFImport(event) {
    const file = event.target.files[0];
    if (!file) return;
    showToast("Parsing Document Engine Matrix...");
    const fileReader = new FileReader();
    
    fileReader.onload = async function() {
        try {
            const pdf = await pdfjsLib.getDocument(new Uint8Array(this.result)).promise;
            let rawText = "";
            for(let i = 1; i <= Math.min(2, pdf.numPages); i++) {
                const page = await pdf.getPage(i);
                const textContent = await page.getTextContent();
                rawText += textContent.items.map(item => item.str).join(' ') + " ";
            }
            
            const poMatch = rawText.match(/Purchase Order\s+(\d+)/i) || rawText.match(/Order\s*"?,"?\s*(\d{10,})/i);
            const poNumber = poMatch ? poMatch[1] : 'PO-' + Math.floor(100000 + Math.random() * 900000);
            
            const dateMatch = rawText.match(/Order Date\s*"?,"?\s*(\d{1,2}-[a-zA-Z]{3}-\d{4})/i);
            let orderDate = new Date().toISOString().split('T')[0];
            if (dateMatch) orderDate = new Date(dateMatch[1]).toISOString().split('T')[0];

            let vendorName = 'Approved Vendor';
            const vendorMatch = rawText.match(/Supplier\s+([A-Za-z\s\.,]+?)(?=\s+\d|,|TRN)/i);
            if(vendorMatch) vendorName = vendorMatch[1].trim();

            const extractedItems = [];
            const itemRegex = /([A-Za-z0-9\s\(\)&,\-\/]+?)\s*"?,"?\s*(\d+)\s*"?,"?\s*EA\s*"?,"?\s*[\s\S]*?([+|-]?[\d,]+(?:\.\d+)?)/gi;
            let match;
            
            while ((match = itemRegex.exec(rawText)) !== null) {
                let description = match[1].replace(/[\"\']/g, '').trim();
                let quantity = parseInt(match[2]);
                let price = parseFloat(match[3].replace(/,/g, ''));
                
                let category = 'Hardware';
                let shortName = description;
                
                if (description.toLowerCase().includes('support') || description.toLowerCase().includes('sla')) {
                    category = 'Software'; shortName = 'Vemcount SLA Option';
                } else if (description.toLowerCase().includes('validation') || description.toLowerCase().includes('service')) {
                    category = 'Service'; shortName = 'Calibration & Validation Service';
                } else if (description.toLowerCase().includes('counter') || description.toLowerCase().includes('pc2')) {
                    shortName = 'Traffic Counter PC2S';
                }

                if (quantity > 0 && price > 0 && description.length > 3) {
                    extractedItems.push({ category, name: shortName, desc: description, qty: quantity, price: price });
                }
            }

            if (extractedItems.length === 0) {
                let detectedQty = 1;
                const qtyScanner = rawText.match(/(\d+)\s*EA/i);
                if (qtyScanner) detectedQty = parseInt(qtyScanner[1]);

                if (rawText.includes('Vemcount') || rawText.includes('Xovis')) {
                    extractedItems.push({ category: 'Hardware', name: 'Traffic Counter PC2S', desc: 'Dynamic Detection Fallback Asset Entry', qty: detectedQty, price: 2650.00 });
                } else {
                    extractedItems.push({ category: 'Hardware', name: 'General Asset Item', desc: 'Standard Asset Ingestion Inflow Entry', qty: detectedQty, price: 0.00 });
                }
            }

            document.getElementById('poModal').classList.add('active');
            document.getElementById('lineItemsContainer').innerHTML = '';
            
            document.getElementById('poNumber').value = poNumber;
            document.getElementById('orderDate').value = orderDate;
            
            const vendorSelect = document.getElementById('vendorSelect');
            if (vendorSelect) {
                let matchedVendorIdx = 0;
                for(let i=0; i < vendorSelect.options.length; i++) {
                    if(vendorSelect.options[i].value.toLowerCase().includes(vendorName.toLowerCase().split(' ')[0])) { 
                        matchedVendorIdx = i; break; 
                    }
                }
                vendorSelect.selectedIndex = matchedVendorIdx;
            }
            
            extractedItems.forEach(item => addNewLineItem(item.category, item.name, item.desc, 'N/A', 'Pending Trace', item.qty, item.price));
            showToast(`PDF Compiled. Localized ${extractedItems.length} contextual row matrices dynamically.`);
            
        } catch (err) { showToast("Error executing automated data map translation steps."); }
    };
    fileReader.readAsArrayBuffer(file);
}

async function saveStore(e) {
    e.preventDefault();
    const payload = { 
        sName: document.getElementById('sName').value, 
        sBrandCode: document.getElementById('sBrandCode').value || 'N/A',
        sBrand: document.getElementById('sBrand').value || 'N/A',
        sMall: document.getElementById('sMall').value || 'N/A',
        sEntity: document.getElementById('sEntity').value || 'N/A',
        sCity: document.getElementById('sCity').value || 'N/A',
        sRoute: document.getElementById('sRoute').value || 'N/A', 
        username: currentUser
    };
    try {
        const res = await fetch('procurement_api.php?action=create_store', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json();
        if(data.status === 'success') { showToast('Store Registered'); await fetchStores(); await fetchAuditLogs(); closeModal('storeModal'); } 
        else showToast("Error writing store: " + data.message);
    } catch(err) { showToast('Connection to schema writing failed'); }
}

async function saveVendor(e) {
    e.preventDefault();
    const payload = { company: document.getElementById('vCompany').value, taxId: document.getElementById('vTax').value || 'N/A', terms: document.getElementById('vTerms').value || 'N/A', lead: document.getElementById('vLead').value || 'N/A', email: document.getElementById('vEmail').value || 'N/A', username: currentUser };
    try {
        const res = await fetch('procurement_api.php?action=create_vendor', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json();
        if(data.status === 'success') { showToast('Vendor Appended'); await fetchVendors(); await fetchAuditLogs(); closeModal('vendorModal'); } else showToast("Error: " + data.message);
    } catch(err) { showToast('Connection failed'); }
}

async function removeVendor(id, company) {
    if(confirm('Revoke approval and remove vendor?')) {
        try {
            await fetch(`procurement_api.php?action=delete_vendor&id=${id}`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({username: currentUser, company}) });
            showToast('Vendor Removed'); await fetchVendors(); await fetchAuditLogs();
        } catch(e) { showToast('Database Connection Error'); }
    }
}

async function saveUser(e) {
    e.preventDefault();
    const payload = { uId: document.getElementById('uId').value, uName: document.getElementById('uName').value, uDept: document.getElementById('uDept').value || 'N/A', username: currentUser };
    try {
        const res = await fetch('procurement_api.php?action=create_personnel', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json();
        if(data.status === 'success') { showToast('Personnel Logged'); await fetchPersonnel(); await fetchAuditLogs(); closeModal('userModal'); } else showToast("Error: " + data.message);
    } catch(err) { showToast('Connection failed'); }
}

async function removePersonnel(empId) {
    if(confirm('Remove this user access record from users_po?')) {
        try {
            const res = await fetch(`procurement_api.php?action=delete_personnel&emp_id=${empId}`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({username: currentUser}) });
            const data = await res.json();
            if(data.status === 'success') { showToast('Personnel Access Revoked'); await fetchPersonnel(); await fetchAuditLogs(); } else showToast(data.message);
        } catch(e) { showToast('Database Communication Error'); }
    }
}

function showToast(msg) { 
    const t = document.getElementById('toastMessage'); 
    if(!t) return;
    t.innerHTML = `<i class="fas fa-info-circle" style="margin-right: 8px;"></i>${msg}`; 
    t.classList.add('show'); 
    setTimeout(() => t.classList.remove('show'), 2800); 
}

function closeModal(id) { 
    const modal = document.getElementById(id);
    if(modal) modal.classList.remove('active'); 
    
    if(id === 'poModal' && document.getElementById('poForm')) { document.getElementById('poForm').reset(); document.getElementById('lineItemsContainer').innerHTML = ''; }
    if(id === 'vendorModal' && document.getElementById('vendorForm')) document.getElementById('vendorForm').reset();
    if(id === 'storeModal' && document.getElementById('storeForm')) document.getElementById('storeForm').reset();
    if(id === 'userModal' && document.getElementById('userForm')) document.getElementById('userForm').reset();
    if(id === 'preflightVendorModal' && document.getElementById('preflightContainer')) document.getElementById('preflightContainer').innerHTML = '';
    if(id === 'receivePoModal' && document.getElementById('receivePoForm')) document.getElementById('receivePoForm').reset();
}