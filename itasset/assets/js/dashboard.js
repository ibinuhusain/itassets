// Dashboard JavaScript
let purchaseOrders = [];
let dispatchLogs = [];
let retailStores = [];
let personnelList = [];
let masterInventory = [];
let approvedVendors = [];
let adminStoreRequests = [];
let masterITAssets = [];
let assetRecoveryLog = [];

const ROWS_PER_PAGE = 15;
let tableStates = {};

// ==========================================
// INITIALIZATION
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    initApp();
    setupTabs();
});

function setupTabs() {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', function() {
            const tab = this.dataset.tab;
            switchTab(tab);
        });
    });
}

function switchTab(tab) {
    document.querySelectorAll('.card-panel').forEach(p => p.classList.remove('active-panel'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    
    const target = document.getElementById(tab + 'Tab') || document.getElementById(tab);
    if (target) target.classList.add('active-panel');
    document.querySelector(`.nav-item[data-tab="${tab}"]`)?.classList.add('active');
    
    // Refresh data for specific tabs
    if (tab === 'inventory') renderInventory();
    if (tab === 'directory') renderDirectory();
    if (tab === 'store-requests') fetchStoreRequests();
    if (tab === 'it-assets') renderITAssets();
}

// ==========================================
// API CALLS
// ==========================================
async function initApp() {
    await Promise.all([
        fetchStores(),
        fetchVendors(),
        fetchPersonnel(),
        fetchPOs(),
        fetchInventory(),
        fetchDispatch(),
        fetchITAssets(),
        fetchStoreRequests()
    ]);
    updateDashboard();
}

async function fetchStores() {
    try {
        const res = await fetch('procurement_api.php?action=list_stores');
        retailStores = await res.json();
        populateStoreDatalist();
    } catch(e) { console.error('Error fetching stores:', e); }
}

async function fetchVendors() {
    try {
        const res = await fetch('procurement_api.php?action=list_vendors');
        approvedVendors = await res.json();
    } catch(e) { console.error('Error fetching vendors:', e); }
}

async function fetchPersonnel() {
    try {
        const res = await fetch('procurement_api.php?action=list_personnel');
        personnelList = await res.json();
    } catch(e) { console.error('Error fetching personnel:', e); }
}

async function fetchPOs() {
    try {
        const res = await fetch('procurement_api.php?action=list_it_pos');
        purchaseOrders = await res.json();
        renderPOs();
    } catch(e) { console.error('Error fetching POs:', e); }
}

async function fetchInventory() {
    try {
        const res = await fetch('procurement_api.php?action=list_it_inventory');
        masterInventory = await res.json();
        renderInventory();
    } catch(e) { console.error('Error fetching inventory:', e); }
}

async function fetchDispatch() {
    try {
        const res = await fetch('procurement_api.php?action=list_it_dispatch');
        dispatchLogs = await res.json();
    } catch(e) { console.error('Error fetching dispatch:', e); }
}

async function fetchITAssets() {
    try {
        const res = await fetch('procurement_api.php?action=list_it_assets');
        masterITAssets = await res.json();
        renderITAssets();
    } catch(e) { console.error('Error fetching IT assets:', e); }
}

async function fetchStoreRequests() {
    try {
        const res = await fetch('procurement_api.php?action=list_store_requests');
        adminStoreRequests = await res.json();
        renderStoreRequests();
    } catch(e) { console.error('Error fetching store requests:', e); }
}

// ==========================================
// RENDER FUNCTIONS
// ==========================================
function updateDashboard() {
    const pending = adminStoreRequests.filter(r => !r.status || r.status === 'Pending').length;
    const lowStock = masterInventory.filter(i => parseInt(i.quantity_on_hand || 0) < 5).length;
    
    document.getElementById('metric-pending').textContent = pending;
    document.getElementById('inv-low-stock-dash').textContent = lowStock;
    document.getElementById('metric-stores').textContent = retailStores.length;
    document.getElementById('metric-persons').textContent = personnelList.length;
    document.getElementById('metric-desktops').textContent = masterITAssets.length;
    document.getElementById('disp-total-dash').textContent = dispatchLogs.length;
    
    // Fleet breakdown
    let printers = 0, network = 0, scanners = 0, cctv = 0;
    masterITAssets.forEach(a => {
        const type = String(a.device_type || '').toLowerCase();
        if (type.includes('print')) printers++;
        else if (type.includes('net') || type.includes('forti') || type.includes('switch')) network++;
        else if (type.includes('scan') || type.includes('read')) scanners++;
        else if (type.includes('cam') || type.includes('cctv')) cctv++;
    });
    document.getElementById('metric-printers').textContent = printers;
    document.getElementById('metric-network').textContent = network;
    document.getElementById('metric-scanners').textContent = scanners;
    document.getElementById('metric-cctv').textContent = cctv;
}

function renderInventory() {
    const tbody = document.getElementById('inventoryTableBody');
    if (!tbody) return;
    
    let html = '';
    let totalUnits = 0;
    
    masterInventory.forEach(item => {
        const qty = parseInt(item.quantity_on_hand || item.qty || 0);
        totalUnits += qty;
        html += `<tr>
            <td style="font-family:monospace; font-weight:600;">${item.sku || 'N/A'}</td>
            <td>${item.category || '-'}</td>
            <td><strong>${item.hardware_type || '-'}</strong></td>
            <td>${item.item_name || item.name || 'Unknown'}</td>
            <td><strong style="color:${qty > 0 ? 'var(--success)' : 'var(--danger)'};">${qty}</strong></td>
            <td><span class="status-badge ${qty > 0 ? 'status-approved' : 'status-pending'}">${qty > 0 ? 'In Stock' : 'Out of Stock'}</span></td>
        </tr>`;
    });
    
    tbody.innerHTML = html || '<tr><td colspan="6" style="text-align:center; padding:40px;">No inventory found</td></tr>';
    document.getElementById('inv-total-skus').textContent = masterInventory.length;
    document.getElementById('inv-total-units').textContent = totalUnits;
    initPagination('tbl-inv');
}

function renderDirectory() {
    renderStores();
    renderVendors();
    renderUsers();
}

function renderStores() {
    const tbody = document.getElementById('storeTableBody');
    if (!tbody) return;
    tbody.innerHTML = retailStores.map(s => `
        <tr>
            <td><strong>${s.id}</strong></td>
            <td>${s.name}</td>
            <td>${s.brand || '-'}</td>
            <td>${s.mall || '-'}</td>
            <td>${s.city || '-'}</td>
        </tr>
    `).join('') || '<tr><td colspan="5" style="text-align:center; padding:20px;">No stores found</td></tr>';
    initPagination('tbl-stores');
}

function renderVendors() {
    const tbody = document.getElementById('vendorTableBody');
    if (!tbody) return;
    tbody.innerHTML = approvedVendors.map(v => `
        <tr>
            <td><strong>${v.company_name || v.company}</strong></td>
            <td>${v.tax_id || '-'}</td>
            <td>${v.payment_terms || '-'}</td>
            <td>${v.lead_time || '-'}</td>
            <td><button onclick="deleteVendor(${v.id})" class="btn-cancel" style="padding:4px 10px;"><i class="fas fa-trash"></i></button></td>
        </tr>
    `).join('') || '<tr><td colspan="5" style="text-align:center; padding:20px;">No vendors found</td></tr>';
    initPagination('tbl-vendors');
}

function renderUsers() {
    const tbody = document.getElementById('userTableBody');
    if (!tbody) return;
    tbody.innerHTML = personnelList.map(u => `
        <tr>
            <td><strong>${u.emp_id}</strong></td>
            <td>${u.emp_name}</td>
            <td>${u.department || '-'}</td>
            <td><button onclick="deleteUser('${u.emp_id}')" class="btn-cancel" style="padding:4px 10px;"><i class="fas fa-trash"></i></button></td>
        </tr>
    `).join('') || '<tr><td colspan="4" style="text-align:center; padding:20px;">No users found</td></tr>';
    initPagination('tbl-users');
}

function renderStoreRequests() {
    const pending = adminStoreRequests.filter(r => !r.status || r.status === 'Pending');
    const processed = adminStoreRequests.filter(r => r.status === 'Approved' || r.status === 'Rejected');
    
    // Pending requests
    const tbodyPending = document.getElementById('adminStoreRequestsTableBody');
    if (tbodyPending) {
        tbodyPending.innerHTML = pending.map(req => {
            let items = [];
            try { items = JSON.parse(req.details || '[]'); } catch(e) {}
            const itemsHtml = items.map(i => `<span style="background:#f1f5f9; padding:4px 8px; border-radius:4px; font-size:11px; margin:2px;">${i.qty}x ${i.sku}</span>`).join(' ');
            return `<tr>
                <td>${new Date(req.timestamp).toLocaleString()}</td>
                <td><strong>${req.username}</strong></td>
                <td>${itemsHtml}</td>
                <td>
                    <button onclick="approveRequest(${req.id})" class="btn-create" style="padding:4px 10px; font-size:11px;">Approve</button>
                    <button onclick="rejectRequest(${req.id})" class="btn-cancel" style="padding:4px 10px; font-size:11px; color:var(--danger);">Reject</button>
                </td>
            </tr>`;
        }).join('') || '<tr><td colspan="4" style="text-align:center; padding:20px;">No pending requests</td></tr>';
        initPagination('tbl-req-pending');
    }
    
    // Processed requests
    const tbodyProcessed = document.getElementById('adminProcessedRequestsTableBody');
    if (tbodyProcessed) {
        tbodyProcessed.innerHTML = processed.map(req => {
            const badge = req.status === 'Approved' ? 
                '<span class="status-badge status-complete">APPROVED</span>' : 
                '<span class="status-badge status-pending">REJECTED</span>';
            return `<tr>
                <td>${new Date(req.timestamp).toLocaleString()}</td>
                <td><strong>${req.username}</strong></td>
                <td>${req.details || '-'}</td>
                <td>${badge}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="4" style="text-align:center; padding:20px;">No processed requests</td></tr>';
        initPagination('tbl-req-history');
    }
}

function renderITAssets() {
    const tbody = document.getElementById('itAssetsTableBody');
    if (!tbody) return;
    tbody.innerHTML = masterITAssets.map(a => `
        <tr>
            <td><strong>${a.hostname || 'N/A'}</strong></td>
            <td>${a.brand_name || '-'}<br><span style="font-size:10px; color:#666;">${a.store_code || '-'}</span></td>
            <td>${a.location || '-'}</td>
            <td><strong>${a.device_type || '-'}</strong></td>
            <td style="font-family:monospace;">${a.serial_number || '-'}</td>
            <td>${a.os || ''} ${a.os_version || ''}</td>
        </tr>
    `).join('') || '<tr><td colspan="6" style="text-align:center; padding:20px;">No IT assets found</td></tr>';
    initPagination('tbl-it-assets');
}

// ==========================================
// ACTIONS
// ==========================================
async function approveRequest(id) {
    if (!confirm('Approve this request?')) return;
    try {
        const res = await fetch('procurement_api.php?action=approve_store_request', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ requestId: id })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('Request approved');
            fetchStoreRequests();
        }
    } catch(e) { showToast('Error approving request'); }
}

async function rejectRequest(id) {
    if (!confirm('Reject this request?')) return;
    try {
        const res = await fetch('procurement_api.php?action=reject_store_request', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ requestId: id })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('Request rejected');
            fetchStoreRequests();
        }
    } catch(e) { showToast('Error rejecting request'); }
}

async function deleteVendor(id) {
    if (!confirm('Delete this vendor?')) return;
    try {
        const res = await fetch(`procurement_api.php?action=delete_vendor&id=${id}`, { method: 'POST' });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('Vendor deleted');
            fetchVendors();
        }
    } catch(e) { showToast('Error deleting vendor'); }
}

async function deleteUser(empId) {
    if (!confirm('Delete this user?')) return;
    try {
        const res = await fetch(`procurement_api.php?action=delete_personnel&emp_id=${empId}`, { method: 'POST' });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('User deleted');
            fetchPersonnel();
        }
    } catch(e) { showToast('Error deleting user'); }
}

// ==========================================
// MODALS
// ==========================================
function openStoreModal() { document.getElementById('storeModal').classList.add('active'); }
function openVendorModal() { document.getElementById('vendorModal').classList.add('active'); }
function openUserModal() { document.getElementById('userModal').classList.add('active'); }

async function saveStore(e) {
    e.preventDefault();
    const payload = {
        sName: document.getElementById('sName').value,
        sBrand: document.getElementById('sBrand').value,
        sBrandCode: document.getElementById('sBrandCode').value,
        sMall: document.getElementById('sMall').value,
        sEntity: document.getElementById('sEntity').value,
        sCity: document.getElementById('sCity').value,
        sRoute: document.getElementById('sRoute').value
    };
    try {
        const res = await fetch('procurement_api.php?action=create_store', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('Store added');
            closeModal('storeModal');
            fetchStores();
        }
    } catch(e) { showToast('Error adding store'); }
}

async function saveVendor(e) {
    e.preventDefault();
    const payload = {
        company: document.getElementById('vCompany').value,
        taxId: document.getElementById('vTax').value,
        terms: document.getElementById('vTerms').value,
        lead: document.getElementById('vLead').value
    };
    try {
        const res = await fetch('procurement_api.php?action=create_vendor', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('Vendor added');
            closeModal('vendorModal');
            fetchVendors();
        }
    } catch(e) { showToast('Error adding vendor'); }
}

async function saveUser(e) {
    e.preventDefault();
    const payload = {
        uId: document.getElementById('uId').value,
        uName: document.getElementById('uName').value,
        uDept: document.getElementById('uDept').value
    };
    try {
        const res = await fetch('procurement_api.php?action=create_personnel', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('User added');
            closeModal('userModal');
            fetchPersonnel();
        }
    } catch(e) { showToast('Error adding user'); }
}

// ==========================================
// EXPORT
// ==========================================
function openExportModal(tableId, dateCol, fileName) {
    document.getElementById('exportTableId').value = tableId;
    document.getElementById('exportFileName').value = fileName;
    document.getElementById('exportModal').classList.add('active');
}

function executeExport(e) {
    e.preventDefault();
    const tableId = document.getElementById('exportTableId').value;
    const fileName = document.getElementById('exportFileName').value;
    
    const table = document.getElementById(tableId);
    if (!table) { closeModal('exportModal'); return; }
    
    let csv = '';
    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
    csv += headers.join(',') + '\n';
    
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        csv += Array.from(cells).map(cell => `"${cell.textContent.trim()}"`).join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `${fileName}_${new Date().toISOString().slice(0,10)}.csv`;
    link.click();
    closeModal('exportModal');
    showToast('Export completed');
}

// ==========================================
// UTILITY FUNCTIONS
// ==========================================
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function showToast(msg) {
    const t = document.getElementById('toastMessage');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function initPagination(tableId) {
    if (!tableStates[tableId]) tableStates[tableId] = { currentPage: 1 };
    applyPagination(tableId);
}

function applyPagination(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const container = document.getElementById('page-' + tableId);
    
    const totalPages = Math.ceil(rows.length / ROWS_PER_PAGE) || 1;
    const state = tableStates[tableId];
    if (state.currentPage > totalPages) state.currentPage = totalPages;
    
    const start = (state.currentPage - 1) * ROWS_PER_PAGE;
    const end = start + ROWS_PER_PAGE;
    
    rows.forEach((r, i) => r.style.display = (i >= start && i < end) ? '' : 'none');
    
    if (container) {
        container.innerHTML = `
            <span>${rows.length} entries</span>
            <div class="pagination-controls">
                <button onclick="changePage('${tableId}', -1)" ${state.currentPage === 1 ? 'disabled' : ''}>Prev</button>
                <button onclick="changePage('${tableId}', 1)" ${state.currentPage === totalPages ? 'disabled' : ''}>Next</button>
            </div>
        `;
    }
}

function changePage(tableId, delta) {
    const state = tableStates[tableId];
    state.currentPage += delta;
    applyPagination(tableId);
}

function populateStoreDatalist() {
    const dl = document.getElementById('storeDatalist');
    if (dl) {
        dl.innerHTML = retailStores.map(s => `<option value="${s.name} [${s.id}]">`).join('');
    }
}

function handleLogout() {
    if (confirm('Logout?')) {
        window.location.href = 'api_logout.php';
    }
}

function handleITAssetsImport(event) {
    const file = event.target.files[0];
    if (!file) return;
    showToast('Processing import...');
    // XLSX parsing would go here
    event.target.value = '';
}