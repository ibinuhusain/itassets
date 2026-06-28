// Recovery JavaScript
let assetRecoveryLog = [];
let retailStores = [];
let tableStates = {};
const ROWS_PER_PAGE = 15;

document.addEventListener('DOMContentLoaded', function() {
    initRecovery();
});

async function initRecovery() {
    await fetchStores();
    await fetchRecoveryLog();
    populateStoreDatalist();
    renderRecentRecoveries();
}

async function fetchStores() {
    try {
        const res = await fetch('procurement_api.php?action=list_stores');
        retailStores = await res.json();
    } catch(e) { console.error('Error fetching stores:', e); }
}

async function fetchRecoveryLog() {
    try {
        const res = await fetch('procurement_api.php?action=list_it_asset_recovery');
        assetRecoveryLog = await res.json();
    } catch(e) { console.error('Error fetching recovery log:', e); }
}

function populateStoreDatalist() {
    const dl = document.getElementById('storeDatalist');
    if (dl) {
        dl.innerHTML = retailStores.map(s => `<option value="${s.name} [${s.id}]">`).join('');
    }
}

function renderStoreRecoveryDetails() {
    const searchVal = document.getElementById('recoveryStoreSearch').value;
    const match = searchVal.match(/\[(\d+)\]/);
    const storeId = match ? match[1] : null;
    
    const tbody = document.getElementById('recoveryLedgerTableBody');
    if (!storeId) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:40px;">Select a store to view recovered assets</td></tr>';
        return;
    }
    
    const records = assetRecoveryLog.filter(r => 
        String(r.storeId || r.origin_store || r.store_id) === String(storeId)
    );
    
    let html = '';
    let reuse = 0, writeoff = 0;
    
    records.forEach(r => {
        const action = String(r.action_type || r.action || 'reuse').toLowerCase();
        if (action === 'reuse') reuse += parseInt(r.qty || 1);
        if (action === 'write off' || action === 'writeoff') writeoff += parseInt(r.qty || 1);
        
        const badge = action === 'reuse' ? 'status-approved' : 'status-pending';
        html += `<tr>
            <td><strong>${r.hwType || r.hardware_type || '-'}</strong></td>
            <td><strong>${r.itemName || r.device_issued || '-'}</strong><br><span style="font-size:10px; color:#666;">Model: ${r.modelNo || r.model_number || '-'}</span></td>
            <td style="font-family:monospace;">${r.serialNo || r.serial_number || 'N/A'}</td>
            <td><strong>${r.qty || 1}</strong></td>
            <td><span class="status-badge ${badge}">${action.toUpperCase()}</span></td>
            <td>${r.remarks || '-'}</td>
        </tr>`;
    });
    
    document.getElementById('stat-reuse').textContent = reuse;
    document.getElementById('stat-writeoff').textContent = writeoff;
    tbody.innerHTML = html || '<tr><td colspan="6" style="text-align:center; padding:40px;">No recovery records found</td></tr>';
    initPagination('tbl-recovery');
}

function renderRecentRecoveries() {
    const tbody = document.getElementById('recentRecoveriesTableBody');
    if (!tbody) return;
    
    const summary = {};
    assetRecoveryLog.forEach(r => {
        const id = String(r.storeId || r.origin_store || r.store_id || 'Unknown');
        if (!summary[id]) {
            summary[id] = {
                storeId: id,
                storeName: 'Unknown Store',
                lastDate: r.timestamp || new Date().toISOString(),
                reuse: 0,
                writeoff: 0
            };
        }
        const store = retailStores.find(s => String(s.id) === id);
        if (store) summary[id].storeName = store.name;
        
        const date = new Date(r.timestamp || 0);
        if (date > new Date(summary[id].lastDate)) summary[id].lastDate = r.timestamp;
        
        const action = String(r.action_type || r.action || 'reuse').toLowerCase();
        if (action === 'reuse') summary[id].reuse += parseInt(r.qty || 1);
        if (action === 'write off' || action === 'writeoff') summary[id].writeoff += parseInt(r.qty || 1);
    });
    
    const sorted = Object.values(summary).sort((a, b) => new Date(b.lastDate) - new Date(a.lastDate));
    
    tbody.innerHTML = sorted.map(s => `
        <tr>
            <td><strong>${s.storeName}</strong></td>
            <td><span class="status-badge status-dispatched">${s.storeId}</span></td>
            <td>${s.lastDate ? new Date(s.lastDate).toLocaleDateString() : 'Recent'}</td>
            <td><strong style="color:var(--success);">${s.reuse}</strong></td>
            <td><strong style="color:var(--danger);">${s.writeoff}</strong></td>
            <td><button onclick="viewRecoveryReceipt('${s.storeId}')" class="btn-cancel" style="padding:4px 10px;"><i class="fas fa-eye"></i> View</button></td>
        </tr>
    `).join('') || '<tr><td colspan="6" style="text-align:center; padding:20px;">No recent recoveries</td></tr>';
    initPagination('tbl-recent-recoveries');
}

function viewRecoveryReceipt(storeId) {
    const records = assetRecoveryLog.filter(r => 
        String(r.storeId || r.origin_store || r.store_id) === String(storeId)
    );
    if (!records.length) return showToast('No records found');
    
    const store = retailStores.find(s => String(s.id) === String(storeId));
    const storeName = store ? store.name : storeId;
    
    let html = `
        <div style="display:flex; justify-content:space-between; margin-bottom:30px;">
            <div>
                <div style="background:#0f172a; color:#fff; padding:10px 18px; font-size:20px; font-weight:bold; display:inline-block; border-radius:2px;">
                    APPAREL GROUP
                </div>
                <div style="font-size:11px; color:#334155; margin-top:10px;">
                    <strong>IT Procurement & Recovery</strong><br>
                    Jebel Ali Free Zone, Dubai, UAE
                </div>
            </div>
            <div style="text-align:right;">
                <h1 style="color:#0f172a; font-size:20px; font-weight:800; text-transform:uppercase;">Recovery Handover</h1>
                <p style="font-size:12px; color:#64748b;"><strong>Origin:</strong> ${storeName} [${storeId}]</p>
                <p style="font-size:12px; color:#64748b;"><strong>Generated:</strong> ${new Date().toLocaleString()}</p>
            </div>
        </div>
    `;
    
    // Reuse items
    const reuseItems = records.filter(r => String(r.action_type || r.action || 'reuse').toLowerCase() === 'reuse');
    if (reuseItems.length) {
        html += `
            <div style="border:2px dashed var(--success); padding:20px; margin-bottom:30px; border-radius:8px;">
                <h3 style="color:var(--success); margin-bottom:15px;">RESTOCK RECEIPT (REUSE)</h3>
                <table style="width:100%; border-collapse:collapse; font-size:12px;">
                    <thead><tr style="background:#f0fdf4; border-bottom:1px solid var(--success);">
                        <th style="padding:8px; text-align:left;">H/W Type</th>
                        <th style="padding:8px; text-align:left;">Item & Model</th>
                        <th style="padding:8px; text-align:left;">Serial No</th>
                        <th style="padding:8px; text-align:center;">Qty</th>
                    </tr></thead>
                    <tbody>
                        ${reuseItems.map(r => `
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:8px;">${r.hwType || r.hardware_type || '-'}</td>
                                <td style="padding:8px;"><strong>${r.itemName || r.device_issued || '-'}</strong><br><small>${r.modelNo || r.model_number || '-'}</small></td>
                                <td style="padding:8px; font-family:monospace;">${r.serialNo || r.serial_number || 'N/A'}</td>
                                <td style="padding:8px; text-align:center;"><strong>${r.qty || 1}</strong></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }
    
    // Write off items
    const writeoffItems = records.filter(r => {
        const action = String(r.action_type || r.action || '').toLowerCase();
        return action === 'write off' || action === 'writeoff';
    });
    if (writeoffItems.length) {
        html += `
            <div style="border:2px dashed var(--danger); padding:20px; margin-bottom:30px; border-radius:8px;">
                <h3 style="color:var(--danger); margin-bottom:15px;">DISPOSAL RECEIPT (WRITE-OFF)</h3>
                <table style="width:100%; border-collapse:collapse; font-size:12px;">
                    <thead><tr style="background:#fef2f2; border-bottom:1px solid var(--danger);">
                        <th style="padding:8px; text-align:left;">H/W Type</th>
                        <th style="padding:8px; text-align:left;">Item & Model</th>
                        <th style="padding:8px; text-align:left;">Serial No</th>
                        <th style="padding:8px; text-align:left;">Remarks</th>
                        <th style="padding:8px; text-align:center;">Qty</th>
                    </tr></thead>
                    <tbody>
                        ${writeoffItems.map(r => `
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:8px;">${r.hwType || r.hardware_type || '-'}</td>
                                <td style="padding:8px;"><strong>${r.itemName || r.device_issued || '-'}</strong><br><small>${r.modelNo || r.model_number || '-'}</small></td>
                                <td style="padding:8px; font-family:monospace;">${r.serialNo || r.serial_number || 'N/A'}</td>
                                <td style="padding:8px;">${r.remarks || '-'}</td>
                                <td style="padding:8px; text-align:center;"><strong>${r.qty || 1}</strong></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }
    
    document.getElementById('receiptContent').innerHTML = html;
    document.getElementById('dispatchDetailsModal').classList.add('active');
}

function printRecoveryReceipt() {
    const content = document.getElementById('receiptContent').innerHTML;
    const win = window.open('', '', 'width=800,height=900');
    win.document.write(`<html><head><style>body{font-family:Arial;padding:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:8px;text-align:left}</style></head><body>${content}<script>window.print();<\/script></body></html>`);
    win.document.close();
}

function downloadRecoveryTemplate() {
    document.getElementById('templateRulesModal').classList.add('active');
}

function proceedWithTemplateDownload() {
    closeModal('templateRulesModal');
    const headers = ['Store ID', 'Hardware Type', 'Item Name', 'Model Number', 'Serial Number', 'Qty', 'Action', 'Remarks'];
    const ws = XLSX.utils.aoa_to_sheet([headers]);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Recovery_Template');
    XLSX.writeFile(wb, 'Recovery_Template.xlsx');
}

function handleBulkRecoveryImport(event) {
    const file = event.target.files[0];
    if (!file) return showToast('No file selected');
    
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const rows = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);
            
            if (!rows.length) return showToast('Empty file');
            
            let errors = [];
            let reuse = 0, writeoff = 0;
            
            rows.forEach((row, i) => {
                const action = String(row['Action'] || '').toLowerCase();
                if (!['reuse', 'write off'].includes(action)) {
                    errors.push(`Row ${i+2}: Invalid action "${action}"`);
                }
                if (action === 'reuse') reuse += parseInt(row['Qty'] || 1);
                if (action === 'write off') writeoff += parseInt(row['Qty'] || 1);
            });
            
            if (errors.length) {
                alert('Errors found:\n' + errors.join('\n'));
                return;
            }
            
            // Process the recovery
            document.getElementById('summaryReuseCount').textContent = reuse;
            document.getElementById('summaryWriteOffCount').textContent = writeoff;
            document.getElementById('importSummaryModal').classList.add('active');
            
            // Send to API
            fetch('procurement_api.php?action=bulk_asset_recovery', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ payload: rows })
            });
            
        } catch(err) {
            showToast('Error processing file: ' + err.message);
        }
    };
    reader.readAsArrayBuffer(file);
    event.target.value = '';
}

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

function handleLogout() {
    if (confirm('Logout?')) {
        window.location.href = 'api_logout.php';
    }
}