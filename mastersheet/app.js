// --- SYSTEM GLOBALS & CONFIG ---
document.getElementById('displayUserName').innerText = `@${currentUserName}`;
document.getElementById('displayUserRole').innerText = `Role: ${currentRole.toUpperCase()}`;
if(currentRole === 'admin') document.getElementById('globalPrintBtn').classList.remove('d-none');

const menus = {
    admin: [
        { id: 'admin-users', title: 'User Management', icon: '👥' },
        { id: 'admin-logs', title: 'Upload Logs & Brands', icon: '📁' },
        { id: 'admin-upload', title: 'Master Data Upload', icon: '📤' },
        { id: 'admin-unified-mapping', title: 'Global Assignments Hub', icon: '🔗' },
        { id: 'admin-settings', title: 'System Settings', icon: '⚙️' }
    ],
    merchant: [
        { id: 'merch-upload', title: 'Master Data Upload', icon: '📤' }
    ]
};

let allBrands = [], currentBrandsPage = 1, brandsSearchTerm = '';
const itemsPerPage = 10;

let rawMerchants = [], rawMappings = [];
let rawAppUsers = [], rawAppUserBrands = []; 

let pendingCSVData = null;
let pendingFileName = null;
let parsedTotalRows = 0;
let parsedTotalBrands = 0;

// --- UTILITIES ---
function showTempAlert(elementId, message, isSuccess) {
    const alertBox = document.getElementById(elementId);
    if(!alertBox) return;
    alertBox.className = `alert alert-${isSuccess ? 'success' : 'danger'} mb-3 py-2`;
    alertBox.style.fontSize = "13px";
    alertBox.innerText = message;
    alertBox.classList.remove('d-none');
    if(isSuccess) setTimeout(() => alertBox.classList.add('d-none'), 3500);
}

// --- INITIALIZATION ---
function renderMenu() {
    const nav = document.getElementById('navMenu');
    if(!menus[currentRole]) return window.location.href='../api_logout.php'; 
    
    menus[currentRole].forEach((item, index) => {
        const li = document.createElement('li');
        li.style.listStyle = "none";
        li.innerHTML = `<a class="nav-link ${index === 0 ? 'active' : ''}" onclick="navigate('${item.id}', this)"><span class="me-2">${item.icon}</span> ${item.title}</a>`;
        nav.appendChild(li);
    });
    navigate(menus[currentRole][0].id, nav.querySelector('.nav-link')); 
}

function downloadCsvTemplate() {
    const headers = ["barcode", "brand", "item_description", "location_code", "was_price", "now_price", "expires_at"].join(",");
    const csvContent = `data:text/csv;charset=utf-8,${headers}\n`;
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "merchant_import_template.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// --- PRIMARY ROUTER ---
function navigate(viewId, element) {
    document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
    if(element) element.classList.add('active');
    
    const container = document.getElementById('appContainer');
    const title = document.getElementById('pageTitle');

    if (viewId === 'admin-users') {
        title.innerText = "System User Management";
        container.innerHTML = `
            <div class="panel-card" id="userManagementPanel">
                <div class="table-header-bar">
                    <h3 class="mb-0"><span class="material-symbols-outlined" style="color:var(--accent-blue)">group</span> Global Directory</h3>
                    <div class="search-wrapper">
                        <span class="material-symbols-outlined">search</span>
                        <input type="text" id="userSearchInput" class="search-input" placeholder="Search usernames, names, or roles...">
                    </div>
                </div>
                <div class="table-scroll-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Username / ID</th>
                                <th>Full Name</th>
                                <th>System Role</th>
                                <th>Access Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="userTableBody">
                            <tr><td colspan="5" class="text-center py-4 text-muted">Loading system profile matrices...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        fetchSystemUsers(); 
    }
    else if (viewId === 'admin-logs') {
        title.innerText = "Merchant Upload Logs & Brands";
        container.innerHTML = `
            <div class="mapping-workspace-grid">
                <div class="panel-card">
                    <h3><span class="material-symbols-outlined" style="color:var(--accent-blue)">add_circle</span> Create Brand</h3>
                    <div class="form-group">
                        <label class="form-label">Single Brand Entry Name</label>
                        <input type="text" id="newBrandName" class="form-control mb-3" placeholder="e.g. Skechers">
                        <button class="btn btn-primary w-100" onclick="addBrand()">Save Brand Identifier</button>
                    </div>
                    <small class="d-block mt-3" style="font-size:12px; font-weight:500; color:var(--warning); line-height:1.4;">App User security signatures will auto-resolve access structures down via authorization routines.</small>
                </div>
                
                <div class="panel-card">
                    <div class="table-header-bar">
                        <h2>Global Brands Directory</h2>
                        <div class="search-wrapper">
                            <span class="material-symbols-outlined">search</span>
                            <input type="text" id="adminBrandSearch" class="search-input" placeholder="Filter global directory..." onkeyup="handleBrandSearch(this.value)">
                        </div>
                    </div>
                    <div class="table-scroll-container">
                        <table class="table">
                            <thead><tr><th style="width: 80px;">ID</th><th>Brand Profile Name</th></tr></thead>
                            <tbody id="brandTableBody"><tr><td colspan="2" class="text-center py-4">Loading system definitions...</td></tr></tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <small id="brandPageInfo" class="text-muted font-monospace" style="font-size:12px;"></small>
                        <div class="d-flex gap-1">
                            <button class="btn btn-outline-secondary bg-white btn-sm" style="padding:2px 8px;" onclick="changeBrandPage(-1)">&lt;</button>
                            <button class="btn btn-outline-secondary bg-white btn-sm" style="padding:2px 8px;" onclick="changeBrandPage(1)">&gt;</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="panel-card mt-2">
                <div class="table-header-bar">
                    <h2>System Upload History Log Suite</h2>
                    <button class="btn btn-primary btn-sm" onclick="window.location.href='api/logs.php?action=export_all'">⬇️ Export Combined Master Data</button>
                </div>
                <div class="table-scroll-container">
                    <table class="table">
                        <thead><tr><th>Timestamp</th><th>Merchant Owner</th><th>Items Processed</th><th>System File Name</th><th>Action Link</th></tr></thead>
                        <tbody id="logsTableBody"><tr><td colspan="5" class="text-center py-4">Loading transaction buffers...</td></tr></tbody>
                    </table>
                </div>
            </div>`;
        fetchLogsAndBrands();
    }
    else if (viewId === 'admin-upload') {
        title.innerText = "Admin Master Data Upload";
        container.innerHTML = `
            <div class="panel-card" style="max-width: 900px; margin: 0 auto;">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h3 class="mb-0"><span class="material-symbols-outlined" style="color:var(--accent-blue)">upload_file</span> Data Injection Port</h3>
                    <button class="btn btn-outline-info btn-sm border text-white" onclick="downloadCsvTemplate()">⬇️ Download CSV Template</button>
                </div>
                <div id="uploadAlert" class="alert d-none"></div>
                
                <form id="uploadForm" onsubmit="processFileLocally(event)">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Target Merchant Profile</label>
                            <select id="adminUploadMerchant" class="form-select" required>
                                <option value="" disabled selected>Loading merchant entries...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Target Brand (Override)</label>
                            <select id="adminUploadBrand" class="form-select">
                                <option value="" selected>Read from CSV column...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Select Master Spreadsheet</label>
                            <input type="file" id="masterCsvFile" class="form-control" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>
                        </div>
                        <div class="col-12 text-end mt-3">
                            <button type="submit" id="readBtn" class="btn btn-primary px-4">Analyze Target Sheet</button>
                        </div>
                    </div>
                </form>

                <div id="uploadConfirmBox" class="upload-confirm-box d-none flex-column align-items-center mt-4">
                    <h4 class="text-white mb-2" style="font-size:16px;">Array Bounds Verification Complete</h4>
                    <p id="uploadStatsText" class="text-muted mb-3" style="font-size:13px;"></p>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success" id="confirmUploadBtn" onclick="executeUpload()">Commit to Database</button>
                        <button class="btn btn-danger" onclick="cancelUpload()">Drop Buffer</button>
                    </div>
                </div>
            </div>`;
        fetchMerchantsForAdminUpload();
    }
    else if (viewId === 'admin-unified-mapping') {
        title.innerText = "Global Assignments Hub";
        container.innerHTML = `
            <div class="mapping-tabs">
                <button class="map-tab-btn active" id="tabMerchantBtn" onclick="switchMappingMode('merchant')"><span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">storefront</span> Merchant Mapping Matrix</button>
                <button class="map-tab-btn" id="tabAgentBtn" onclick="switchMappingMode('agent')"><span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">badge</span> Field Agent Clearance Scope</button>
            </div>

            <div class="mapping-workspace-grid">
                <div class="panel-card">
                    <div id="merchantSelectContainer">
                        <h3><span class="material-symbols-outlined" style="color:var(--accent-blue)">storefront</span> Target Merchant Account</h3>
                        <div class="form-group mb-0">
                            <label class="form-label">Active Merchant Profiles</label>
                            <select id="mappingMerchantSelect" class="form-select" onchange="renderDualListBox()"><option>Loading secure mapping channels...</option></select>
                        </div>
                    </div>
                    
                    <div id="agentSelectContainer" class="d-none">
                        <h3><span class="material-symbols-outlined" style="color:var(--accent-blue)">badge</span> Target Field Agent Account</h3>
                        <div class="form-group mb-0">
                            <label class="form-label">Active Agent Profiles</label>
                            <select id="agentMappingUserSelect" class="form-select" onchange="renderAgentDualListBox()"><option>Loading terminal field links...</option></select>
                        </div>
                    </div>
                </div>

                <div class="panel-card">
                    <div id="merchantListBoxWrapper" class="dual-listbox-container dual-listbox">
                        <div>
                            <div class="form-label">Global Unassigned Brand Catalog</div>
                            <select multiple id="availableBrandsBox" class="form-select"></select>
                        </div>
                        <div class="d-flex flex-column gap-2 justify-content-center align-items-center control-action-arrows">
                            <button class="btn btn-primary w-100 btn-sm" onclick="moveBrands('right')">Assign <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">chevron_right</span></button>
                            <button class="btn btn-danger w-100 btn-sm" onclick="moveBrands('left')"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">chevron_left</span> Remove</button>
                        </div>
                        <div>
                            <div class="form-label">Active Assigned Brand Scopes</div>
                            <select multiple id="assignedBrandsBox" class="form-select"></select>
                        </div>
                    </div>

                    <div id="agentListBoxWrapper" class="dual-listbox-container dual-listbox d-none">
                        <div>
                            <div class="form-label">Global Catalog Library</div>
                            <select multiple id="availableAgentBrandsBox" class="form-select"></select>
                        </div>
                        <div class="d-flex flex-column gap-2 justify-content-center align-items-center control-action-arrows">
                            <button class="btn btn-primary w-100 btn-sm" onclick="moveAgentBrands('right')">Authorize <span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">chevron_right</span></button>
                            <button class="btn btn-danger w-100 btn-sm" onclick="moveAgentBrands('left')"><span class="material-symbols-outlined" style="font-size:14px; vertical-align:middle;">chevron_left</span> Revoke</button>
                        </div>
                        <div>
                            <div class="form-label">Live Device Active Scope Clearances</div>
                            <select multiple id="assignedAgentBrandsBox" class="form-select"></select>
                        </div>
                    </div>
                </div>
            </div>`;
        fetchMappings();
    }
    else if (viewId === 'admin-settings') {
        title.innerText = "System Global Settings Configuration";
        container.innerHTML = `
            <div class="panel-card" style="max-width: 500px;">
                <h3><span class="material-symbols-outlined" style="color:var(--accent-blue)">shield</span> Global Lifecycles</h3>
                <div id="settingsAlert" class="alert d-none"></div>
                <div class="form-group mb-4">
                    <label class="form-label">Master Records Frame Persistence Duration (Days)</label>
                    <input type="number" id="gracePeriodInput" class="form-control" min="1">
                </div>
                <button class="btn btn-primary w-100" onclick="saveSettings(this)">Write Configuration State</button>
            </div>`;
        fetchSettings();
    }
    else if (viewId === 'merch-upload') {
        title.innerText = "Master Sheet Workspace Control";
        container.innerHTML = `
            <div class="panel-card">
                <h3><span class="material-symbols-outlined" style="color:var(--success)">verified</span> Account Clearances Matrix</h3>
                <div id="merchantBrandsList" class="d-flex flex-wrap gap-2"><span class="text-muted small">Mapping authorization structures...</span></div>
            </div>
            
            <div class="panel-card">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h3 class="mb-0"><span class="material-symbols-outlined" style="color:var(--accent-blue)">cloud_upload</span> Stage Updated Log Asset</h3>
                    <button class="btn btn-outline-info btn-sm border text-white" onclick="downloadCsvTemplate()">⬇️ Download CSV Template</button>
                </div>
                <div id="uploadAlert" class="alert d-none"></div>
                <form id="uploadForm" onsubmit="processFileLocally(event)">
                    <div class="d-flex gap-3 align-items-center flex-wrap">
                        <input type="file" id="masterCsvFile" class="form-control" style="max-width:400px;" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>
                        <button type="submit" id="readBtn" class="btn btn-primary">Process Matrix Parse</button>
                    </div>
                </form>

                <div id="uploadConfirmBox" class="upload-confirm-box d-none flex-column align-items-center mt-3">
                    <h4 class="text-white mb-2" style="font-size:15px;">Local Parsing Complete</h4>
                    <p id="uploadStatsText" class="text-muted mb-3" style="font-size:13px;"></p>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success btn-sm" id="confirmUploadBtn" onclick="executeUpload()">Overwrite Engine Stack</button>
                        <button class="btn btn-outline-secondary btn-sm bg-white text-dark" onclick="cancelUpload()">Abort</button>
                    </div>
                </div>
            </div>
            
            <div class="panel-card">
                <div class="table-header-bar">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold" style="font-size:15px;">Active Master Grid Workspace</span>
                        <span class="badge bg-dark text-white border" id="activeDataStats">Records: 0</span>
                    </div>
                    <div class="d-flex gap-2">
                        <input type="text" id="searchBox" class="form-control form-control-sm" placeholder="Search item barcode..." onchange="searchItems()">
                        <button class="btn btn-success btn-sm text-nowrap" onclick="downloadActiveSheet()">⬇️ Download CSV</button>
                    </div>
                </div>
                <div class="table-scroll-container">
                    <table class="table">
                        <thead><tr><th>Description Field Specification</th><th>Location Space</th><th>Expiration Metric</th><th>File Name</th></tr></thead>
                        <tbody id="itemsTableBody"><tr><td colspan="4" class="text-center py-4">Waiting on stack execution loop...</td></tr></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <small id="paginationInfo" class="text-muted font-monospace" style="font-size:12px;">Frame index: 1</small>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary bg-white btn-sm" id="prevPageBtn" onclick="changeItemPage(-1)">Prev</button>
                        <button class="btn btn-outline-secondary bg-white btn-sm" id="nextPageBtn" onclick="changeItemPage(1)">Next</button>
                    </div>
                </div>
            </div>`;
        fetchMerchantAssignments(); 
        fetchMasterItems(1);
    }
}

// --- SUBTAB ROUTER SWITCH ENGINE ---
function switchMappingMode(mode) {
    document.querySelectorAll('.map-tab-btn').forEach(btn => btn.classList.remove('active'));
    
    const tabMerchantBtn = document.getElementById('tabMerchantBtn');
    const tabAgentBtn = document.getElementById('tabAgentBtn');
    const merchantSelectContainer = document.getElementById('merchantSelectContainer');
    const agentSelectContainer = document.getElementById('agentSelectContainer');
    const merchantListBoxWrapper = document.getElementById('merchantListBoxWrapper');
    const agentListBoxWrapper = document.getElementById('agentListBoxWrapper');

    if (mode === 'merchant') {
        tabMerchantBtn.classList.add('active');
        merchantSelectContainer.classList.remove('d-none');
        merchantListBoxWrapper.classList.remove('d-none');
        agentSelectContainer.classList.add('d-none');
        agentListBoxWrapper.classList.add('d-none');
        if (document.getElementById('mappingMerchantSelect').value) renderDualListBox();
    } else {
        tabAgentBtn.classList.add('active');
        agentSelectContainer.classList.remove('d-none');
        agentListBoxWrapper.classList.remove('d-none');
        merchantSelectContainer.classList.add('d-none');
        merchantListBoxWrapper.classList.add('d-none');
        if (document.getElementById('agentMappingUserSelect').value) renderAgentDualListBox();
    }
}

// --- PIPELINE CONTROLLERS LOGIC ---

async function fetchSystemUsers() {
    try {
        const response = await fetch('api/users.php'); 
        const result = await response.json();
        const tbody = document.getElementById('userTableBody');
        
        if (result.success && result.users) {
            let html = '';
            
            const labellerUsers = result.users.filter(user => {
                if (!user.app_permissions) return false;
                try {
                    const perms = typeof user.app_permissions === 'string' ? JSON.parse(user.app_permissions) : user.app_permissions;
                    return perms.labeller && Array.isArray(perms.labeller) && perms.labeller.length > 0;
                } catch(e) { 
                    return false; 
                }
            });

            if (labellerUsers.length === 0) {
                html = '<tr><td colspan="5" class="text-center py-4 text-muted">No labeller system profiles found.</td></tr>';
            } else {
                labellerUsers.forEach(u => {
                    const perms = typeof u.app_permissions === 'string' ? JSON.parse(u.app_permissions) : u.app_permissions;
                    const primaryRole = perms.labeller[0]; 
                    
                    let roleBadge = '';
                    if (primaryRole === 'admin') {
                        roleBadge = '<span class="badge-role">System Admin</span>';
                    } else if (primaryRole === 'merchant') {
                        roleBadge = '<span class="badge-cyan">Merchant</span>';
                    } else {
                        roleBadge = '<span class="badge-status" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">Field Auditor</span>';
                    }

                    html += `<tr>
                        <td>
                            <strong class="text-white">@${u.username}</strong>
                            <br><small class="text-muted" style="font-size: 11px;">EMP ID: ${u.emp_id || u.id}</small>
                        </td>
                        <td class="fw-bold">${u.full_name || 'Unassigned'}</td>
                        <td>${roleBadge}</td>
                        <td><span class="badge-status">Active</span></td>
                        <td>
                            <button class="btn btn-action-warning">Edit</button>
                            <button class="btn btn-action-delete">Revoke</button>
                        </td>
                    </tr>`;
                });
            }
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Directory empty or unreadable.</td></tr>';
        }
    } catch (e) {
        if(document.getElementById('userTableBody')) {
            document.getElementById('userTableBody').innerHTML = '<tr><td colspan="5" class="text-danger text-center py-4">Error reading from user matrix API.</td></tr>';
        }
    }
}

async function fetchLogsAndBrands() {
    try {
        const bRes = await fetch('api/mapping.php');
        const bData = await bRes.json();
        if(bData.success) {
            allBrands = bData.brands;
            if(document.getElementById('brandTableBody')) renderBrands();
        }

        const response = await fetch('api/logs.php');
        const result = await response.json();
        const tbody = document.getElementById('logsTableBody');
        if(!tbody) return;
        
        let logsHtml = '';
        if (result.success && result.data.length > 0) {
            result.data.forEach(log => {
                logsHtml += `<tr>
                    <td class="text-muted">${log.uploaded_at}</td>
                    <td><strong class="text-white">${log.merchant_name || 'Unknown'}</strong></td>
                    <td><span class="badge-cyan">${log.total_items} items</span></td>
                    <td><span class="text-pinkish">${log.filename}</span></td>
                    <td><button class="btn-action-success btn-sm" onclick="downloadMerchantData('${log.merchant_name}')">Download CSV</button></td>
                </tr>`;
            });
        } else logsHtml = '<tr><td colspan="5" class="text-center py-4 text-muted">No uploads recorded in logs.</td></tr>';
        tbody.innerHTML = logsHtml;
    } catch (e) { if(document.getElementById('logsTableBody')) document.getElementById('logsTableBody').innerHTML = '<tr><td colspan="5" class="text-danger text-center">Error reading data.</td></tr>'; }
}

function handleBrandSearch(term) { brandsSearchTerm = term.toLowerCase(); currentBrandsPage = 1; renderBrands(); }
function changeBrandPage(dir) { currentBrandsPage += dir; renderBrands(); }

function renderBrands() {
    const filtered = allBrands.filter(b => b.name.toLowerCase().includes(brandsSearchTerm));
    const totalPages = Math.ceil(filtered.length / itemsPerPage) || 1;
    if(currentBrandsPage < 1) currentBrandsPage = 1;
    if(currentBrandsPage > totalPages) currentBrandsPage = totalPages;

    const start = (currentBrandsPage - 1) * itemsPerPage;
    const pageData = filtered.slice(start, start + itemsPerPage);

    let html = '';
    if(pageData.length === 0) html = '<tr><td colspan="2" class="text-center text-muted py-4">No matching brands.</td></tr>';
    else pageData.forEach(b => { html += `<tr><td class="text-muted">${b.id}</td><td><strong class="text-white">${b.name}</strong></td></tr>`; });
    
    document.getElementById('brandTableBody').innerHTML = html;
    document.getElementById('brandPageInfo').innerText = `Page ${currentBrandsPage} of ${totalPages} (Total: ${filtered.length})`;
}

async function fetchMappings() {
    const merchSelect = document.getElementById('mappingMerchantSelect');
    const agentSelect = document.getElementById('agentMappingUserSelect');
    
    // ANTI-HIJACK: Cache the current selections before rewriting the DOM
    const savedMerchId = merchSelect ? merchSelect.value : null;
    const savedAgentId = agentSelect ? agentSelect.value : null;

    try {
        const response = await fetch('api/mapping.php?t=' + Date.now());
        const rawText = await response.text(); 

        try {
            const result = JSON.parse(rawText);
            if(result.success) {
                rawMerchants = result.merchants || [];
                allBrands = result.brands || [];
                rawMappings = result.mappings || [];
                rawAppUsers = result.app_users || [];
                rawAppUserBrands = result.app_user_brands || [];

                if(merchSelect) {
                    merchSelect.innerHTML = '<option value="" disabled selected>Choose target profile links...</option>' + 
                        rawMerchants.map(m => {
                            const fullName = m.name || m.full_name || m.fullname || m.first_name || '';
                            const resolvedName = fullName ? `${fullName} (@${m.username})` : `@${m.username}`;
                            return `<option value="${m.id}">${resolvedName}</option>`;
                        }).join('');
                    
                    // ANTI-HIJACK: Restore the selection so the UI doesn't break
                    if (savedMerchId) merchSelect.value = savedMerchId;
                }

                if(agentSelect) {
                    agentSelect.innerHTML = '<option value="" disabled selected>Choose target agent links...</option>' + 
                        rawAppUsers.map(u => {
                            const fullName = u.name || u.full_name || u.fullname || u.first_name || '';
                            const resolvedName = fullName ? `${fullName} (@${u.username})` : `@${u.username}`;
                            return `<option value="${u.id}">${resolvedName}</option>`;
                        }).join('');
                    
                    // ANTI-HIJACK: Restore the selection so the UI doesn't break
                    if (savedAgentId) agentSelect.value = savedAgentId;
                }
            } else {
                if(merchSelect) merchSelect.innerHTML = `<option disabled style="color:var(--accent-danger)">SQL Error: ${result.message}</option>`;
                if(agentSelect) agentSelect.innerHTML = `<option disabled style="color:var(--accent-danger)">SQL Error: ${result.message}</option>`;
            }
        } catch (e) {
            console.error("PHP Crash Output:", rawText);
            if(merchSelect) merchSelect.innerHTML = `<option disabled style="color:var(--accent-danger)">PHP Crash! Press F12 -> Console to read error.</option>`;
            if(agentSelect) agentSelect.innerHTML = `<option disabled style="color:var(--accent-danger)">PHP Crash! Press F12 -> Console to read error.</option>`;
        }
    } catch (e) {
        if(merchSelect) merchSelect.innerHTML = `<option disabled>Network Connection Failed</option>`;
    }
}
function renderDualListBox() {
    const selectedMerchantId = document.getElementById('mappingMerchantSelect').value;
    if(!selectedMerchantId) return;

    const merchantName = getMerchantNameById(selectedMerchantId);
    const merchantMappings = rawMappings.filter(m => m.merchant === merchantName);
    const assignedBrandNames = merchantMappings.map(m => m.brand);

    const availableBox = document.getElementById('availableBrandsBox');
    const assignedBox = document.getElementById('assignedBrandsBox');
    let availHtml = '', assignHtml = '';

    allBrands.forEach(brand => {
        if(assignedBrandNames.includes(brand.name)) {
            const mappingEntry = merchantMappings.find(m => m.brand === brand.name);
            assignHtml += `<option value="${mappingEntry.id}">${brand.name}</option>`;
        } else {
            availHtml += `<option value="${brand.id}">${brand.name}</option>`;
        }
    });
    availableBox.innerHTML = availHtml;
    assignedBox.innerHTML = assignHtml;
}

function getMerchantNameById(id) {
    const m = rawMerchants.find(m => m.id == id);
    return m ? m.username : ''; 
}

async function moveBrands(direction) {
    const merchantId = document.getElementById('mappingMerchantSelect').value;
    if(!merchantId) return alert("Select baseline target directory asset.");

    if(direction === 'right') {
        const availableBox = document.getElementById('availableBrandsBox');
        const selectedOptions = Array.from(availableBox.selectedOptions);
        if(selectedOptions.length === 0) return alert("Select lines to apply.");
        let successCount = 0;
        for (let opt of selectedOptions) {
            try {
                const res = await fetch('api/mapping.php', { method: 'POST', body: JSON.stringify({ action: 'map_brand', merchant_id: merchantId, brand_id: opt.value }) });
                const result = await res.json();
                if(result.success) {
                    successCount++;
                } else {
                    // This will tell us EXACTLY why the database is rejecting the insert
                    alert("Database rejected mapping: " + result.message); 
                }
            } catch(e) {}
        }
        if(successCount > 0) { await fetchMappings(); setTimeout(renderDualListBox, 100); }
    } else if (direction === 'left') {
        const assignedBox = document.getElementById('assignedBrandsBox');
        const selectedOptions = Array.from(assignedBox.selectedOptions);
        if(selectedOptions.length === 0) return alert("Select lines to discard.");
        let successCount = 0;
        for (let opt of selectedOptions) {
            try {
                const res = await fetch('api/mapping.php', { method: 'POST', body: JSON.stringify({ action: 'remove_mapping', mapping_id: opt.value }) });
                if((await res.json()).success) successCount++;
            } catch(e) {}
        }
        if(successCount > 0) { await fetchMappings(); setTimeout(renderDualListBox, 100); }
    }
}

async function moveAgentBrands(direction) {
    const userId = document.getElementById('agentMappingUserSelect').value;
    if(!userId) return alert("Select agent context tracker entry.");

    if(direction === 'right') {
        const availableBox = document.getElementById('availableAgentBrandsBox');
        const selectedOptions = Array.from(availableBox.selectedOptions);
        if(selectedOptions.length === 0) return alert("Select profiles to link.");
        let successCount = 0;
        for (let opt of selectedOptions) {
            try {
                const res = await fetch('api/mapping.php', { method: 'POST', body: JSON.stringify({ action: 'assign_app_user_brand', user_id: userId, brand_id: opt.value }) });
                const result = await res.json();
                if(result.success) {
                    successCount++;
                } else {
                    alert("Database rejected mapping: " + result.message);
                }
            } catch(e) {}
        }
        if(successCount > 0) { await fetchMappings(); setTimeout(renderAgentDualListBox, 100); }
    } else if (direction === 'left') {
        const assignedBox = document.getElementById('assignedAgentBrandsBox');
        const selectedOptions = Array.from(assignedBox.selectedOptions);
        if(selectedOptions.length === 0) return alert("Select lines to drop.");
        let successCount = 0;
        for (let opt of selectedOptions) {
            try {
                const res = await fetch('api/mapping.php', { method: 'POST', body: JSON.stringify({ action: 'remove_app_user_brand', mapping_id: opt.value }) });
                if((await res.json()).success) successCount++;
            } catch(e) {}
        }
        if(successCount > 0) { await fetchMappings(); setTimeout(renderAgentDualListBox, 100); }
    }
}

function renderAgentDualListBox() {
    const selectedUserId = document.getElementById('agentMappingUserSelect').value;
    if(!selectedUserId) return;
    
    const agentMappings = rawAppUserBrands.filter(m => m.user_id == selectedUserId);
    const assignedBrandIds = agentMappings.map(m => m.brand_id);

    const availableBox = document.getElementById('availableAgentBrandsBox');
    const assignedBox = document.getElementById('assignedAgentBrandsBox');
    let availHtml = '', assignHtml = '';

    allBrands.forEach(brand => {
        // FIX: Use .some() with loose equality (==) instead of .includes() (===)
        // This prevents silent failures if PDO returns a mix of numeric strings and integers
        if(assignedBrandIds.some(id => id == brand.id)) {
            const mappingEntry = agentMappings.find(m => m.brand_id == brand.id);
            assignHtml += `<option value="${mappingEntry.mapping_id}">${brand.name}</option>`;
        } else {
            availHtml += `<option value="${brand.id}">${brand.name}</option>`;
        }
    });
    
    availableBox.innerHTML = availHtml;
    assignedBox.innerHTML = assignHtml;
}

async function moveAgentBrands(direction) {
    const userId = document.getElementById('agentMappingUserSelect').value;
    if(!userId) return alert("Select agent context tracker entry.");

    if(direction === 'right') {
        const availableBox = document.getElementById('availableAgentBrandsBox');
        const selectedOptions = Array.from(availableBox.selectedOptions);
        if(selectedOptions.length === 0) return alert("Select profiles to link.");
        let successCount = 0;
        for (let opt of selectedOptions) {
            try {
                const res = await fetch('api/mapping.php', { method: 'POST', body: JSON.stringify({ action: 'assign_app_user_brand', user_id: userId, brand_id: opt.value }) });
                if((await res.json()).success) successCount++;
            } catch(e) {}
        }
        if(successCount > 0) { await fetchMappings(); setTimeout(renderAgentDualListBox, 100); }
    } else if (direction === 'left') {
        const assignedBox = document.getElementById('assignedAgentBrandsBox');
        const selectedOptions = Array.from(assignedBox.selectedOptions);
        if(selectedOptions.length === 0) return alert("Select lines to drop.");
        let successCount = 0;
        for (let opt of selectedOptions) {
            try {
                const res = await fetch('api/mapping.php', { method: 'POST', body: JSON.stringify({ action: 'remove_app_user_brand', mapping_id: opt.value }) });
                if((await res.json()).success) successCount++;
            } catch(e) {}
        }
        if(successCount > 0) { await fetchMappings(); setTimeout(renderAgentDualListBox, 100); }
    }
}

async function fetchSettings() {
    try {
        const response = await fetch('api/settings.php');
        const result = await response.json();
        if (result.success) document.getElementById('gracePeriodInput').value = result.grace_period;
    } catch (e) {}
}

async function saveSettings(btn) {
    btn.disabled = true;
    const originalText = btn.innerText;
    btn.innerText = "Writing system values...";
    try {
        const response = await fetch('api/settings.php', { 
            method: 'POST', 
            body: JSON.stringify({ grace_period: document.getElementById('gracePeriodInput').value }) 
        });
        const result = await response.json();
        showTempAlert('settingsAlert', result.message, result.success);
    } catch (e) { showTempAlert('settingsAlert', "API state block fault.", false); }
    btn.disabled = false; btn.innerText = originalText;
}

async function fetchMerchantsForAdminUpload() {
    try {
        const response = await fetch('api/mapping.php');
        const rawText = await response.text();
        try {
            const result = JSON.parse(rawText);
            if(result.success) {
                if (result.merchants) {
                    const select = document.getElementById('adminUploadMerchant');
                    if(select) {
                        select.innerHTML = '<option value="" disabled selected>Select merchant endpoint...</option>' + 
                            result.merchants.map(m => {
                                const fullName = m.name || m.full_name || m.fullname || '';
                                return `<option value="${m.username}">${fullName ? fullName + ' (@' + m.username + ')' : '@' + m.username}</option>`;
                            }).join('');
                    }
                }
                if (result.brands) {
                    const bSelect = document.getElementById('adminUploadBrand');
                    if(bSelect) {
                        bSelect.innerHTML = '<option value="" selected>Read from CSV column...</option>' + 
                            result.brands.map(b => `<option value="${b.name}">${b.name}</option>`).join('');
                    }
                }
            }
        } catch (e) { console.error("Error parsing fetchMerchantsForAdminUpload"); }
    } catch (e) {}
}

let currentItemPage = 1; let totalItemPages = 1; let currentSearchQuery = '';
function searchItems() { currentSearchQuery = document.getElementById('searchBox').value; fetchMasterItems(1); }
function downloadActiveSheet() { window.location.href = `api/merchant_items.php?username=${currentUserName}&action=export`; }
function downloadMerchantData(merchantUsername) {
    if(!merchantUsername || merchantUsername === 'Unknown') return alert("Target matrix unresolvable.");
    window.location.href = `api/merchant_items.php?username=${merchantUsername}&action=export`;
}

async function fetchMasterItems(page) {
    currentItemPage = page;
    try {
        const response = await fetch(`api/merchant_items.php?username=${currentUserName}&page=${page}&search=${encodeURIComponent(currentSearchQuery)}`);
        const result = await response.json();
        const tbody = document.getElementById('itemsTableBody');
        let itemsHtml = '';
        if (result.success) {
            totalItemPages = result.total_pages;
            document.getElementById('activeDataStats').innerText = `Records: ${result.total_items}`;
            if(result.data.length === 0) {
                itemsHtml = '<tr><td colspan="4" class="text-center py-4" style="color:var(--warning); font-weight:500;">No active brand catalogs assigned to this account asset line.</td></tr>';
            } else {
                result.data.forEach(item => {
                    itemsHtml += `<tr>
                        <td><strong class="text-white">${item.item_description || 'N/A'}</strong></td>
                        <td><span class="badge-role">${item.location_code || 'N/A'}</span></td>
                        <td class="text-muted">${item.expires_at || 'N/A'}</td>
                        <td class="text-pinkish">${item.filename || item.file_name || 'N/A'}</td>
                    </tr>`;
                });
            }
            tbody.innerHTML = itemsHtml;
            document.getElementById('paginationInfo').innerText = `Showing matrix line segment ${page} of ${totalItemPages || 1} (Total Rows: ${result.total_items})`;
            document.getElementById('prevPageBtn').disabled = page <= 1;
            document.getElementById('nextPageBtn').disabled = page >= totalItemPages;
        }
    } catch (e) {}
}

function changeItemPage(direction) {
    let newPage = currentItemPage + direction;
    if (newPage >= 1 && newPage <= totalItemPages) fetchMasterItems(newPage);
}

function processFileLocally(event) {
    event.preventDefault();
    const fileInput = document.getElementById('masterCsvFile');
    const file = fileInput.files[0];
    const readBtn = document.getElementById('readBtn');
    
    let targetMerchant = currentUserName;
    if (currentRole === 'admin') {
        const merchantSelect = document.getElementById('adminUploadMerchant');
        if (!merchantSelect.value) return alert("Select execution targeting bounds parameter.");
        targetMerchant = merchantSelect.value;
    }
    
    if (!file) return;
    readBtn.disabled = true; readBtn.innerText = "Analyzing file arrays...";
    const reader = new FileReader();
    
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const firstSheet = workbook.SheetNames[0];
            const sheet = workbook.Sheets[firstSheet];
            pendingCSVData = XLSX.utils.sheet_to_csv(sheet);
            const today = new Date().toISOString().split('T')[0]; 
            if (currentRole === 'admin') { pendingFileName = `${currentUserName}_for_${targetMerchant}_${today}.csv`; } 
            else { pendingFileName = `${currentUserName}_${today}.csv`; }
            
            const jsonData = XLSX.utils.sheet_to_json(sheet); parsedTotalRows = jsonData.length; parsedTotalBrands = 0;
            if(parsedTotalRows > 0) {
                const keys = Object.keys(jsonData[0]);
                const brandKey = keys.find(k => k.toLowerCase().includes('brand'));
                if(brandKey) { parsedTotalBrands = new Set(jsonData.map(row => row[brandKey]).filter(b => b)).size; }
            }
            
            document.getElementById('uploadForm').classList.add('d-none');
            const confirmBox = document.getElementById('uploadConfirmBox');
            confirmBox.classList.remove('d-none'); confirmBox.classList.add('d-flex');
            
            let brandText = parsedTotalBrands > 0 ? ` including <strong>${parsedTotalBrands} unique verified brands</strong>` : '';
            let adminContextText = currentRole === 'admin' ? `<br><span class="text-info mt-2 d-block">Operator authority line context: <b>@${currentUserName}</b> assigning targeting scope execution to <b>@${targetMerchant}</b></span>` : '';
            document.getElementById('uploadStatsText').innerHTML = `Staging sequence array successfully resolved: parsed <strong>${parsedTotalRows} unique asset records</strong>${brandText}.${adminContextText}`;
        } catch (err) { alert("Failure executing file analysis matrices."); } finally { readBtn.disabled = false; readBtn.innerText = "Process Matrix Parse"; }
    };
    reader.readAsArrayBuffer(file);
}

function cancelUpload() {
    pendingCSVData = null; pendingFileName = null;
    document.getElementById('masterCsvFile').value = '';
    document.getElementById('uploadConfirmBox').classList.add('d-none');
    document.getElementById('uploadConfirmBox').classList.remove('d-flex');
    document.getElementById('uploadForm').classList.remove('d-none');
    if (currentRole === 'admin') {
        document.getElementById('adminUploadMerchant').value = '';
        document.getElementById('adminUploadBrand').value = '';
    }
}

async function executeUpload() {
    if(!pendingCSVData) return cancelUpload();
    const confirmBox = document.getElementById('uploadConfirmBox');
    const confBtn = document.getElementById('confirmUploadBtn');
    confBtn.disabled = true; confBtn.innerText = "Syncing logs...";
    
    let targetMerchant = currentUserName;
    let targetBrand = ''; 
    
    if (currentRole === 'admin') {
        targetMerchant = document.getElementById('adminUploadMerchant').value;
        const brandSelect = document.getElementById('adminUploadBrand');
        if (brandSelect && brandSelect.value) targetBrand = brandSelect.value;
    }
    
    try {
        const csvBlob = new Blob([pendingCSVData], { type: 'text/csv' });
        const formData = new FormData();
        formData.append('masterFile', csvBlob, pendingFileName);
        formData.append('merchant_username', targetMerchant); 
        formData.append('uploaded_by', currentUserName); 
        
        if (targetBrand) formData.append('target_brand', targetBrand);
        
        const response = await fetch('api/merchant_items.php', { method: 'POST', body: formData });
        const result = await response.json();
        
        confirmBox.classList.add('d-none'); confirmBox.classList.remove('d-flex');
        document.getElementById('uploadForm').classList.remove('d-none');
        showTempAlert('uploadAlert', result.message || (result.success ? 'Records committed' : 'Drop fault'), result.success);
        
        if (result.success) {
            document.getElementById('masterCsvFile').value = '';
            if (currentRole === 'merchant') { currentSearchQuery = ''; fetchMasterItems(1); } 
            else { 
                document.getElementById('adminUploadMerchant').value = ''; 
                document.getElementById('adminUploadBrand').value = '';
            }
        }
    } catch (err) { 
        showTempAlert('uploadAlert', `Error: ${err.message}`, false); 
    } finally { 
        confBtn.disabled = false; confBtn.innerText = "Overwrite Engine Stack"; pendingCSVData = null; 
    }
}

async function fetchMerchantAssignments() {
    try {
        const response = await fetch(`api/mapping.php?t=${Date.now()}`);
        const result = await response.json();
        if (result.success && result.mappings) {
            const myBrands = result.mappings.filter(m => m.merchant === currentUserName);
            const brandList = document.getElementById('merchantBrandsList');
            if (brandList) {
                if (myBrands.length > 0) { brandList.innerHTML = myBrands.map(b => `<span class="badge-cyan me-2 px-3 py-2 fs-6 shadow-sm">${b.brand}</span>`).join(''); } 
                else { brandList.innerHTML = '<span style="color:var(--warning); font-size:13px; font-weight:500;">No active brand catalogs assigned to this account asset line.</span>'; }
            }
        }
    } catch (e) { console.error(e); }
}

window.onload = renderMenu;