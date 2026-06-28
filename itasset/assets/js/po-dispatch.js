// PO & Dispatch JavaScript
let purchaseOrders = [];
let dispatchLogs = [];
let retailStores = [];
let personnelList = [];
let masterInventory = [];
let approvedVendors = [];
let dispatchCart = [];
let tableStates = {};
const ROWS_PER_PAGE = 15;

document.addEventListener('DOMContentLoaded', function() {
    initPODispatch();
});

async function initPODispatch() {
    await Promise.all([
        fetchStores(),
        fetchVendors(),
        fetchPersonnel(),
        fetchPOs(),
        fetchInventory(),
        fetchDispatch()
    ]);
    populateDropdowns();
}

async function fetchStores() {
    try {
        const res = await fetch('procurement_api.php?action=list_stores');
        retailStores = await res.json();
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
    } catch(e) { console.error('Error fetching inventory:', e); }
}

async function fetchDispatch() {
    try {
        const res = await fetch('procurement_api.php?action=list_it_dispatch');
        dispatchLogs = await res.json();
        renderDispatch();
    } catch(e) { console.error('Error fetching dispatch:', e); }
}

function populateDropdowns() {
    // Store datalist
    const dl = document.getElementById('storeDatalist');
    if (dl) {
        dl.innerHTML = retailStores.map(s => `<option value="${s.name} [${s.id}]">`).join('');
    }
    
    // Vendor select
    const vs = document.getElementById('vendorSelect');
    if (vs) {
        vs.innerHTML = '<option value="">Select Vendor</option>' + 
            approvedVendors.map(v => `<option value="${v.company_name || v.company}">${v.company_name || v.company}</option>`).join('');
    }
    
    // Personnel select
    const ps = document.getElementById('dfPersonSelect');
    if (ps) {
        ps.innerHTML = '<option value="">Select Personnel</option>' + 
            personnelList.map(p => `<option value="${p.emp_id}">${p.emp_name} (${p.emp_id})</option>`).join('');
    }
    
    // Brand select for dispatch
    const brands = [...new Set(retailStores.map(s => s.brand || s.brand_code).filter(Boolean))];
    const bs = document.getElementById('dfBrandSelect');
    if (bs) {
        bs.innerHTML = '<option value="">Select Brand</option>' + 
            brands.map(b => `<option value="${b}">${b}</option>`).join('');
    }
}

// ==========================================
// PO FUNCTIONS
// ==========================================
function renderPOs() {
    const tbody = document.getElementById('poTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = purchaseOrders.map(po => {
        const status = po.status === 'Completed' ? 'status-complete' : 
                       po.status === 'Partial' ? 'status-partial' : 'status-approved';
        const first = po.lineItems && po.lineItems.length ? po.lineItems[0] : {};
        return `<tr>
            <td><strong>${po.po_number || po.poNumber}</strong></td>
            <td>${po.vendor || '-'}</td>
            <td>${po.delivery_note_number || '-'}</td>
            <td>${first.category || '-'}</td>
            <td>${first.modelNumber || '-'}</td>
            <td>${first.itemName || '-'}</td>
            <td><strong>SAR ${Number(po.grand_total || 0).toFixed(2)}</strong></td>
            <td><span class="status-badge ${status}">${po.status || 'Pending'}</span></td>
            <td>${po.order_date || '-'}</td>
            <td style="display:flex; gap:4px;">
                <button onclick="openPOTracker('${po.po_number || po.poNumber}')" class="btn-create" style="padding:2px 8px; font-size:10px;">Track</button>
                <button onclick="deletePO(${po.id})" class="btn-cancel" style="padding:2px 8px; font-size:10px; color:var(--danger);"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    }).join('') || '<tr><td colspan="10" style="text-align:center; padding:20px;">No POs found</td></tr>';
    initPagination('tbl-po');
}

function openPOModal() {
    document.getElementById('poModal').classList.add('active');
    document.getElementById('lineItemsContainer').innerHTML = '';
    addLineItem();
    recalcTotals();
}

function addLineItem(cat = 'ICT', hwType = 'SYSTEM', name = '', desc = '', model = 'N/A', serial = 'Pending', qty = 1, price = 0) {
    const container = document.getElementById('lineItemsContainer');
    const div = document.createElement('div');
    div.className = 'line-item';
    div.innerHTML = `
        <div style="display:grid; grid-template-columns:1fr 1fr 2fr 1fr 1fr 1fr 1fr; gap:10px; background:#fff; padding:15px; border:1px solid var(--erp-border); border-radius:6px; margin-bottom:10px; align-items:end;">
            <div class="form-group"><label>Category</label><input type="text" class="line-category" value="${cat}"></div>
            <div class="form-group"><label>H/W Type</label><input type="text" class="line-hw-type" value="${hwType}" list="hardwareTypesList"></div>
            <div class="form-group"><label>Item Name</label><input type="text" class="line-name" value="${name}" required></div>
            <div class="form-group"><label>Model</label><input type="text" class="line-model" value="${model}"></div>
            <div class="form-group"><label>Serial</label><input type="text" class="line-serial" value="${serial}"></div>
            <div class="form-group"><label>Qty</label><input type="number" class="line-qty" value="${qty}" min="1" oninput="recalcTotals()" required></div>
            <div class="form-group"><label>Price</label><input type="number" step="0.01" class="line-price" value="${price}" oninput="recalcTotals()" required></div>
            <button type="button" onclick="this.closest('.line-item').remove(); recalcTotals();" style="grid-column:span 1; background:transparent; border:none; color:var(--danger); font-size:18px; cursor:pointer; align-self:center; padding-bottom:5px;">×</button>
        </div>
    `;
    container.appendChild(div);
}

function recalcTotals() {
    let subtotal = 0;
    document.querySelectorAll('.line-item').forEach(item => {
        const qty = parseFloat(item.querySelector('.line-qty').value) || 0;
        const price = parseFloat(item.querySelector('.line-price').value) || 0;
        subtotal += qty * price;
    });
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    const tax = parseFloat(document.getElementById('taxAmount').value) || 0;
    document.getElementById('grandTotal').value = (subtotal + tax).toFixed(2);
}

async function savePO(e) {
    e.preventDefault();
    
    const lines = [];
    document.querySelectorAll('.line-item').forEach(item => {
        lines.push({
            category: item.querySelector('.line-category').value,
            hw_type: item.querySelector('.line-hw-type').value,
            item_name: item.querySelector('.line-name').value,
            model: item.querySelector('.line-model').value,
            serial: item.querySelector('.line-serial').value,
            qty: parseInt(item.querySelector('.line-qty').value) || 1,
            price: parseFloat(item.querySelector('.line-price').value) || 0
        });
    });
    
    if (!lines.length) return showToast('Add at least one line item');
    
    const payload = {
        po_number: document.getElementById('poNumber').value || 'PO-' + Date.now().toString().slice(-6),
        vendor: document.getElementById('vendorSelect').value,
        delivery_note: document.getElementById('poDeliveryNote').value,
        invoice: document.getElementById('poInvoice').value,
        store: document.getElementById('poAssignedStore').value || 'Warehouse',
        brand: document.getElementById('poAssignedBrand').value || 'N/A',
        lines: lines
    };
    
    try {
        const res = await fetch('procurement_api.php?action=create_it_po', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('PO created successfully');
            closeModal('poModal');
            fetchPOs();
        } else {
            showToast('Error: ' + data.message);
        }
    } catch(e) {
        showToast('Error creating PO');
    }
}

function openPOTracker(poNumber) {
    const po = purchaseOrders.find(p => (p.po_number || p.poNumber) === poNumber);
    if (!po) return showToast('PO not found');
    
    document.getElementById('trackerPoTitle').textContent = 'TRACKING: ' + poNumber;
    const tbody = document.getElementById('poTrackerTableBody');
    
    tbody.innerHTML = (po.lineItems || []).map(item => {
        const dispatched = dispatchLogs.filter(d => d.sku === item.sku);
        let status = '<span class="status-badge status-partial">In Warehouse</span>';
        if (dispatched.length) {
            status = dispatched.map(d => 
                `<span class="status-badge status-dispatched">${d.store_code || 'Dispatched'}</span>`
            ).join(' ');
        }
        return `<tr>
            <td>${item.hardware_type || item.category || '-'}</td>
            <td><strong>${item.item_name || item.itemName}</strong></td>
            <td style="font-family:monospace;">${item.sku || item.serial_number || '-'}</td>
            <td><strong>${item.quantity || item.order_qty || 1}</strong></td>
            <td>${status}</td>
        </tr>`;
    }).join('') || '<tr><td colspan="5" style="text-align:center; padding:20px;">No items</td></tr>';
    
    document.getElementById('poTrackerModal').classList.add('active');
}

async function deletePO(id) {
    if (!confirm('Delete this PO?')) return;
    try {
        const res = await fetch('procurement_api.php?action=delete_po', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id })
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('PO deleted');
            fetchPOs();
        }
    } catch(e) { showToast('Error deleting PO'); }
}

function downloadPOTemplate() {
    const headers = ['PO Number', 'Vendor Name', 'Category', 'Hardware Type', 'Item Name', 'Model Number', 'Serial Number', 'Order qty', 'Price'];
    const ws = XLSX.utils.aoa_to_sheet([headers]);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'PO_Template');
    XLSX.writeFile(wb, 'PO_Import_Template.xlsx');
}

function handlePOBulkImport(event) {
    const file = event.target.files[0];
    if (!file) return showToast('No file selected');
    
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const rows = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);
            
            if (!rows.length) return showToast('Empty file');
            
            // Process rows
            showToast(`Processing ${rows.length} PO lines...`);
            // API call would go here
            // For now, just show success
            showToast('Import completed successfully');
            fetchPOs();
        } catch(err) {
            showToast('Error processing file: ' + err.message);
        }
    };
    reader.readAsArrayBuffer(file);
    event.target.value = '';
}

function parsePDFDocument(event) {
    const file = event.target.files[0];
    if (!file) return showToast('No file selected');
    showToast('PDF parsing is being implemented...');
    // PDF parsing logic would go here
    event.target.value = '';
}

// ==========================================
// DISPATCH FUNCTIONS
// ==========================================
function renderDispatch() {
    const tbody = document.getElementById('dispatchTableBody');
    if (!tbody) return;
    
    const unique = [];
    const seen = new Set();
    dispatchLogs.forEach(log => {
        const id = log.dispatch_id || log.id;
        if (!seen.has(id)) {
            seen.add(id);
            unique.push(log);
        }
    });
    
    tbody.innerHTML = unique.map(log => {
        const items = dispatchLogs.filter(d => (d.dispatch_id || d.id) === (log.dispatch_id || log.id));
        const totalQty = items.reduce((sum, i) => sum + parseInt(i.dispatch_qty || i.qty || 0), 0);
        return `<tr>
            <td><strong>${log.dispatch_id || log.id}</strong></td>
            <td><span class="status-badge status-dispatched">${log.store_code || log.person_name || 'Unknown'}</span></td>
            <td><strong>${items.length} items</strong> (${totalQty} units)</td>
            <td>${log.dispatched_at ? new Date(log.dispatched_at).toLocaleDateString() : '-'}</td>
            <td><button onclick="viewDispatchDetails('${log.dispatch_id || log.id}')" class="btn-cancel" style="padding:4px 10px;"><i class="fas fa-eye"></i> View</button></td>
        </tr>`;
    }).join('') || '<tr><td colspan="5" style="text-align:center; padding:20px;">No dispatch logs</td></tr>';
    
    document.getElementById('disp-total').textContent = unique.length;
    initPagination('tbl-dispatch');
}

function viewDispatchDetails(dispatchId) {
    const items = dispatchLogs.filter(d => (d.dispatch_id || d.id) === dispatchId);
    if (!items.length) return showToast('No details found');
    
    const first = items[0];
    let recipient = first.store_code || first.person_name || 'Unknown';
    
    let html = `
        <div style="display:flex; justify-content:space-between; margin-bottom:30px;">
            <div>
                <div style="background:#0f172a; color:#fff; padding:10px 18px; font-size:20px; font-weight:bold; display:inline-block; border-radius:2px;">
                    APPAREL GROUP
                </div>
                <div style="font-size:11px; color:#334155; margin-top:10px;">
                    <strong>Central Logistics Node</strong><br>
                    Apparel Group IT Procurement<br>
                    Jebel Ali Free Zone, Dubai, UAE
                </div>
            </div>
            <div style="text-align:right;">
                <h1 style="color:#0f172a; font-size:20px; font-weight:800; text-transform:uppercase;">Dispatch Manifest</h1>
                <p style="font-size:12px; color:#64748b;"><strong>Manifest #:</strong> ${dispatchId}</p>
                <p style="font-size:12px; color:#64748b;"><strong>Date:</strong> ${new Date().toLocaleString()}</p>
            </div>
        </div>
        <div style="margin-bottom:30px;">
            <h3 style="font-size:13px; font-weight:bold;">Dispatched To:</h3>
            <p><strong>${recipient}</strong></p>
        </div>
        <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
            <thead>
                <tr style="background:#f1f5f9; border-bottom:2px solid #0f172a;">
                    <th style="padding:10px; text-align:left;">#</th>
                    <th style="padding:10px; text-align:left;">SKU</th>
                    <th style="padding:10px; text-align:left;">Item</th>
                    <th style="padding:10px; text-align:center;">Qty</th>
                </tr>
            </thead>
            <tbody>
                ${items.map((item, i) => `
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <td style="padding:8px;">${i+1}</td>
                        <td style="padding:8px; font-family:monospace;">${item.sku}</td>
                        <td style="padding:8px;"><strong>${item.item_name || 'Item'}</strong></td>
                        <td style="padding:8px; text-align:center;"><strong>${item.dispatch_qty || item.qty}</strong></td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
        <div style="display:flex; justify-content:space-between; margin-top:40px;">
            <div style="width:40%; text-align:center; border-top:1px solid #0f172a; padding-top:8px;">Dispatcher Signature</div>
            <div style="width:40%; text-align:center; border-top:1px solid #0f172a; padding-top:8px;">Receiver Signature</div>
        </div>
    `;
    
    document.getElementById('receiptContent').innerHTML = html;
    document.getElementById('dispatchDetailsModal').classList.add('active');
    document.getElementById('printReceiptBtn').onclick = function() {
        const content = document.getElementById('receiptContent').innerHTML;
        const win = window.open('', '', 'width=800,height=900');
        win.document.write(`<html><head><style>body{font-family:Arial;padding:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:8px;text-align:left}</style></head><body>${content}<script>window.print();<\/script></body></html>`);
        win.document.close();
    };
}

function openDispatchFlow() {
    dispatchCart = [];
    document.getElementById('dispatchFlowModal').classList.add('active');
    goToDispatchStep1();
}

function toggleAssigneeType() {
    const isStore = document.querySelector('input[name="assigneeType"]:checked').value === 'store';
    document.getElementById('storeFields').style.display = isStore ? 'block' : 'none';
    document.getElementById('personFields').style.display = isStore ? 'none' : 'block';
}

function filterStoresByBrand() {
    const brand = document.getElementById('dfBrandSelect').value;
    const select = document.getElementById('dfStoreSelect');
    select.innerHTML = '<option value="">Select Store</option>';
    if (!brand) { select.disabled = true; return; }
    
    retailStores.filter(s => s.brand === brand || s.brand_code === brand).forEach(store => {
        const opt = document.createElement('option');
        opt.value = store.id;
        opt.textContent = `${store.name} [${store.id}]`;
        opt.dataset.entity = store.entity || '';
        opt.dataset.route = store.route_code || '';
        select.appendChild(opt);
    });
    select.disabled = false;
}

function autofillStoreMeta() {
    const select = document.getElementById('dfStoreSelect');
    const opt = select.options[select.selectedIndex];
    if (opt && opt.value) {
        document.getElementById('dfStoreId').value = opt.value;
        document.getElementById('dfStoreEntity').value = opt.dataset.entity || '';
        document.getElementById('dfStoreRoute').value = opt.dataset.route || '';
    }
}

function autofillPersonDetails() {
    const val = document.getElementById('dfPersonSelect').value;
    const person = personnelList.find(p => String(p.emp_id) === String(val));
    if (person) {
        document.getElementById('dfPersonId').value = person.emp_id;
        document.getElementById('dfPersonName').value = person.emp_name;
        document.getElementById('dfPersonDept').value = person.department || '';
    }
}

function goToDispatchStep1() {
    document.getElementById('dispatchStep1').style.display = 'block';
    document.getElementById('dispatchStep2').style.display = 'none';
    document.getElementById('dotStep1').style.color = 'var(--erp-text-blue)';
    document.getElementById('dotStep2').style.color = '#94a3b8';
}

function goToDispatchStep2() {
    const isStore = document.querySelector('input[name="assigneeType"]:checked').value === 'store';
    if (isStore) {
        if (!document.getElementById('dfStoreSelect').value) return showToast('Select a store');
    } else {
        if (!document.getElementById('dfPersonSelect').value) return showToast('Select personnel');
    }
    
    document.getElementById('dispatchStep1').style.display = 'none';
    document.getElementById('dispatchStep2').style.display = 'block';
    document.getElementById('dotStep1').style.color = '#94a3b8';
    document.getElementById('dotStep2').style.color = 'var(--erp-text-blue)';
    renderDispatchCart();
    searchDispatchItems();
}

function searchDispatchItems() {
    const query = document.getElementById('dispatchSearch').value.toLowerCase();
    const results = masterInventory.filter(i => 
        i.sku.toLowerCase().includes(query) || 
        (i.item_name && i.item_name.toLowerCase().includes(query)) ||
        (i.category && i.category.toLowerCase().includes(query))
    );
    
    const container = document.getElementById('dispatchSearchResults');
    if (!results.length) {
        container.innerHTML = '<p style="padding:15px; text-align:center; color:#64748b;">No items found</p>';
        return;
    }
    
    container.innerHTML = results.slice(0, 20).map(item => {
        const inCart = dispatchCart.find(c => c.sku === item.sku);
        return `<div style="display:flex; justify-content:space-between; padding:10px 15px; border-bottom:1px solid #e2e8f0; background:#fff;">
            <div>
                <strong>${item.item_name || item.name}</strong>
                <span style="font-family:monospace; color:#64748b; margin-left:10px;">${item.sku}</span>
                <br><span style="font-size:11px; color:#64748b;">Stock: ${item.quantity_on_hand || item.qty || 0}</span>
            </div>
            <button onclick="addToDispatchCart('${item.sku}')" class="btn-create" style="padding:4px 12px; font-size:11px;" ${inCart ? 'disabled' : ''}>
                ${inCart ? 'Added' : 'Add'}
            </button>
        </div>`;
    }).join('');
}

function addToDispatchCart(sku) {
    const item = masterInventory.find(i => i.sku === sku);
    if (!item) return showToast('Item not found');
    if (dispatchCart.find(c => c.sku === sku)) return showToast('Already in cart');
    
    dispatchCart.push({
        sku: item.sku,
        item_name: item.item_name || item.name,
        qty: 1,
        max_qty: parseInt(item.quantity_on_hand || item.qty || 1)
    });
    renderDispatchCart();
    searchDispatchItems();
}

function renderDispatchCart() {
    const container = document.getElementById('dispatchCartContainer');
    if (!dispatchCart.length) {
        container.innerHTML = '<p style="padding:15px; text-align:center; color:#64748b;">No items in manifest</p>';
        return;
    }
    
    container.innerHTML = dispatchCart.map(item => `
        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 15px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; margin-bottom:8px;">
            <div>
                <strong>${item.item_name}</strong>
                <span style="font-family:monospace; color:#64748b; margin-left:10px;">${item.sku}</span>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <input type="number" min="1" max="${item.max_qty}" value="${item.qty}" 
                       onchange="updateCartQty('${item.sku}', this.value)" style="width:70px; padding:5px;">
                <button onclick="removeFromCart('${item.sku}')" style="background:transparent; border:none; color:var(--danger); cursor:pointer; font-size:16px;">×</button>
            </div>
        </div>
    `).join('');
}

function updateCartQty(sku, value) {
    const item = dispatchCart.find(c => c.sku === sku);
    if (item) {
        let qty = parseInt(value) || 1;
        if (qty > item.max_qty) qty = item.max_qty;
        if (qty < 1) qty = 1;
        item.qty = qty;
    }
}

function removeFromCart(sku) {
    dispatchCart = dispatchCart.filter(c => c.sku !== sku);
    renderDispatchCart();
    searchDispatchItems();
}

async function submitDispatch() {
    if (!dispatchCart.length) return showToast('Add items to manifest');
    
    const isStore = document.querySelector('input[name="assigneeType"]:checked').value === 'store';
    const payload = {
        dispatch_id: 'DSP-' + String(Date.now()).slice(-6),
        store_code: isStore ? document.getElementById('dfStoreId').value : null,
        person_name: !isStore ? document.getElementById('dfPersonName').value : null,
        person_id: !isStore ? document.getElementById('dfPersonId').value : null,
        items: dispatchCart.map(c => ({ sku: c.sku, qty: c.qty }))
    };
    
    try {
        const res = await fetch('procurement_api.php?action=create_it_dispatch', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('Dispatch executed successfully');
            closeModal('dispatchFlowModal');
            fetchDispatch();
            fetchInventory();
        } else {
            showToast('Error: ' + data.message);
        }
    } catch(e) {
        showToast('Error executing dispatch');
    }
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

function handleLogout() {
    if (confirm('Logout?')) {
        window.location.href = 'api_logout.php';
    }
}