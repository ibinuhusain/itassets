// ==========================================
// BILL MATE RETAIL SUITE - PORTAL CORE INTERCEPT
// ==========================================

// Intercept application load to handle store role visibility constraints safely
const originalPortalInit = window.onload || function() {};
window.onload = () => {
    originalPortalInit();
    
    if(currentUser && currentRole === 'Store') {
        // Enforce tight role constraints for store accounts
        if(document.getElementById('appView')) document.getElementById('appView').style.display = 'none';
        if(document.getElementById('storeView')) {
            document.getElementById('storeView').style.display = 'flex';
            document.getElementById('storeNameDisplay').innerText = currentUser;
        }
        if(document.getElementById('storeRouteDisplay')) {
            document.getElementById('storeRouteDisplay').innerText = localStorage.getItem('procurement_route') || 'N/A';
        }
        renderStorePortalInventoryOnly();
        renderStorePortalRequestSession();
    } else if(currentUser && currentRole === 'Admin') {
        // Override Admin layout execution loop to correct column mapping offsets seen in image_e15ee6.png
        patchAdminStoresGridDisplay();
        fetchAdminStoreRequests();
    }
};

// ==========================================
// ADMIN SCREEN: MAP COLUMN GRID OFFSETS
// ==========================================
function patchAdminStoresGridDisplay() {
    const tbody = document.getElementById('storeTableBody');
    if(!tbody || !Array.isArray(retailStores)) return;
    
    // Process structural overrides matching your true database record structure
    tbody.innerHTML = retailStores.map(s => {
        // Detect row alignment signatures safely
        const isMisaligned = s.brand_code === 'Active' || s.brand_code === 'New Store';
        
        let storeId = s.id;
        let storeName = s.name || 'Unnamed Node';
        let brandLabel = isMisaligned ? s.name : (s.brand || '-');
        let brandCode = isMisaligned ? (s.brand || '-') : (s.brand_code || '-');
        let statusExtension = isMisaligned ? ` [${s.brand_code}]` : '';
        let mallContext = s.mall || '-';
        let entityContext = s.entity || '-';
        
        // Isolate values: Don't show raw category classifications as City titles
        let cityGeo = s.city || '-';
        if(isMisaligned && (cityGeo === 'Footwear' || cityGeo === 'Fashion' || cityGeo === 'Sports')) {
            cityGeo = 'Mapped Node'; 
        }
        
        let targetRoute = s.route_code && s.route_code !== 'NULL' ? s.route_code : 'Unassigned';

        return `
            <tr>
                <td><strong>${storeId}</strong></td>
                <td>${storeName}</td>
                <td><strong>${brandLabel}</strong> <span style="color:var(--text-muted); font-size:0.8rem;">(${brandCode})${statusExtension}</span></td>
                <td>${mallContext} / ${entityContext}</td>
                <td>${cityGeo} <br><span style="font-size:0.8rem; font-weight:bold; color:var(--accent-primary);">Route Code: ${targetRoute}</span></td>
            </tr>
        `;
    }).join('');
}

// ==========================================
// STORE PORTAL REQUIREMENT 1 & 3: SEE ASSIGNED ONLY
// ==========================================
function renderStorePortalInventoryOnly() {
    const storeId = currentUser;
    const inventoryTbody = document.getElementById('storeInventoryTableBody');
    if(!inventoryTbody) return;

    // Filter allocations belonging explicitly to this Store ID Node
    const myAllocatedDispatches = dispatchLogs.filter(log => String(log.store_code) === String(storeId));

    if(myAllocatedDispatches.length === 0) {
        // Render a clean, blank slate structure if nothing has been sent to them yet
        inventoryTbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted); font-style:italic;">No products or manifest cargo items currently assigned to this location.</td></tr>`;
        return;
    }

    inventoryTbody.innerHTML = myAllocatedDispatches.map(log => {
        const invRef = masterInventory.find(i => i.sku === log.sku) || {};
        const category = log.category || invRef.category || 'General';
        
        return `
            <tr>
                <td><strong>${log.dispatch_id || log.id}</strong></td>
                <td><span style="font-family:monospace; font-weight:600; color:var(--accent-primary);">${log.sku}</span></td>
                <td><strong>${log.item_name || invRef.item_name || 'Classified Stock Item'}</strong></td>
                <td><span class="status-badge status-dispatched" style="padding:4px 10px;">${category}</span></td>
                <td><strong style="color:var(--success-text); font-size:1rem;">${log.dispatch_qty || log.qty} Units</strong></td>
                <td>
                    <button onclick="viewDispatchDetails('${log.dispatch_id || log.id}')" class="btn-create" style="padding:6px 12px; font-size:0.8rem; box-shadow:none; border-radius:6px;">
                        <i class="fas fa-eye"></i> View Manifest Details
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// ==========================================
// STORE PORTAL REQUIREMENT 2: THE REQUEST SESSION
// ==========================================
function renderStorePortalRequestSession() {
    const catalogTbody = document.getElementById('storeCatalogTableBody');
    if(!catalogTbody || !Array.isArray(masterInventory)) return;

    catalogTbody.innerHTML = masterInventory.map(item => {
        const descLower = (item.item_name || '').toLowerCase();
        const isConsumable = descLower.includes('label') || descLower.includes('ribbon') || descLower.includes('paper') || descLower.includes('roll') || descLower.includes('tape');
        const typeBadge = isConsumable ? 
            `<span class="status-badge status-pending" style="background:#fef3c7; color:#d97706; padding:4px 10px;"><i class="fas fa-vial"></i> Consumable</span>` : 
            `<span class="status-badge status-approved" style="background:#e0e7ff; color:#4f46e5; padding:4px 10px;"><i class="fas fa-box"></i> Non-Consumable</span>`;

        return `
            <tr>
                <td><span style="font-family:monospace; font-weight:600;">${item.sku}</span></td>
                <td><strong>${item.item_name}</strong><br><span style="font-size:0.8rem; color:var(--text-muted);">Cat: ${item.category || '-'}</span></td>
                <td>${typeBadge}</td>
                <td><strong>${item.quantity_on_hand || 0} Units</strong> in Warehouse</td>
                <td>
                    <input type="number" min="0" max="${item.quantity_on_hand || 0}" class="store-portal-req-input" data-sku="${item.sku}" data-name="${item.item_name}" placeholder="0" style="width:90px; padding:6px; border-radius:8px; border:1px solid var(--border-color); font-weight:600; text-align:center; color:white; background:#111827;">
                </td>
            </tr>
        `;
    }).join('');

    renderStorePortalRequestHistory();
}

async function renderStorePortalRequestHistory() {
    const statusPanelWrapper = document.getElementById('storePortalRequestStatusContainer');
    if(!statusPanelWrapper) return;

    try {
        const res = await fetch(`procurement_api.php?action=list_store_requests`);
        const allRequests = await res.json();
        if(!Array.isArray(allRequests)) return;
        
        const myRequests = allRequests.filter(req => String(req.username) === String(currentUser));

        if(myRequests.length === 0) {
            statusPanelWrapper.innerHTML = `<p style="color:var(--text-muted); font-style:italic; font-size:0.9rem;">No historical requests processed from this node location.</p>`;
            return;
        }

        statusPanelWrapper.innerHTML = myRequests.map(req => {
            let itemLines = [];
            try { itemLines = JSON.parse(req.details || '[]'); } catch(e) { itemLines = []; }
            const summaryString = itemLines.map(i => `• ${i.qty}x [${i.sku}] ${i.name}`).join('<br>');
            
            return `
                <div style="background:var(--bg-main); border:1px solid var(--border-color); padding:16px; border-radius:12px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <span style="font-size:0.75rem; color:var(--text-muted); font-weight:600;"><i class="far fa-clock"></i> ${new Date(req.timestamp).toLocaleString()}</span>
                        <p style="margin-top:6px; font-size:0.9rem; line-height:1.4; color:var(--text-primary); font-weight:500;">${summaryString}</p>
                    </div>
                    <div>
                        <span class="status-badge status-pending" style="border:1px solid #f59e0b;"><i class="fas fa-hourglass-start"></i> Awaiting Approval Matrix</span>
                    </div>
                </div>
            `;
        }).join('');
    } catch(err) { console.warn("Failed retrieving request states."); }
}

async function submitStoreRequest() {
    const inputNodes = document.querySelectorAll('.store-portal-req-input');
    const cargoPayload = [];

    inputNodes.forEach(input => {
        const demandQuantity = parseInt(input.value || 0);
        if(demandQuantity > 0) {
            cargoPayload.push({
                sku: input.getAttribute('data-sku'),
                name: input.getAttribute('data-name'),
                qty: demandQuantity
            });
        }
    });

    if(cargoPayload.length === 0) return alert("Please specify item quantities before submitting.");

    try {
        const res = await fetch('procurement_api.php?action=request_inventory', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ storeId: currentUser, items: cargoPayload })
        });
        const data = await res.json();
        if(data.status === 'success') {
            if(typeof showToast === 'function') showToast('Procurement allocation request sent.');
            inputNodes.forEach(i => i.value = '');
            renderStorePortalRequestHistory();
        }
    } catch(err) { alert("Gateway pipeline processing transmission timed out."); }
}

// Handle tab clicks inside store sidebar cleanly
document.querySelectorAll('#storeView .nav-item').forEach(nav => {
    nav.addEventListener('click', () => {
        document.querySelectorAll('#storeView .card-panel').forEach(p => p.style.display = 'none');
        const targetPanel = document.getElementById(nav.getAttribute('data-tab') + 'Panel');
        if(targetPanel) targetPanel.style.display = 'block';
        document.querySelectorAll('#storeView .nav-item').forEach(n => n.classList.remove('active'));
        nav.classList.add('active');
    });
});