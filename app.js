// Background particles logic 
const bgContainer = document.getElementById('floatingDots');
for (let i = 0; i < 30; i++) {
    const dot = document.createElement('div');
    dot.className = 'dot';
    const size = Math.random() * 4 + 2;
    dot.style.width = `${size}px`; dot.style.height = `${size}px`;
    dot.style.left = `${Math.random() * 100}%`; dot.style.top = `${Math.random() * 100}%`;
    dot.style.animationDelay = `${Math.random() * 5}s`; dot.style.animationDuration = `${Math.random() * 10 + 5}s`;
    bgContainer.appendChild(dot);
}

// Global Registries
let activeSession = null;
let appsConfig = {};
let allUsersDirectory = [];
let allStoresRegistry = []; 

const roleDefinitions = {
    'labeller': ['admin', 'merchant', 'app_user'],
    'cash_collection': ['admin', 'report', 'agent'],
    'procurement': ['admin', 'user', 'store/brand'],
    'it_procurement': ['admin', 'user', 'store/brand']
    // 'store' is handled automatically by the assigned_store binding
};

document.addEventListener('DOMContentLoaded', () => {
    loadModuleConfig();
    loadStoresRegistry(); // Fetch stores on load

    document.getElementById('loginForm').addEventListener('submit', handleLogin);
    document.getElementById('createUserForm').addEventListener('submit', createNewUser);
    document.getElementById('logoutBtn').addEventListener('click', logout);
    document.getElementById('backToPortalBtn').addEventListener('click', showDashboard);
    document.getElementById('addModuleBtn').addEventListener('click', addNewModule);
    
    document.getElementById('userSearch').addEventListener('input', (e) => filterUserList(e.target.value));

    document.querySelectorAll('[data-tab]').forEach(btn => {
        btn.addEventListener('click', (e) => switchAdminTab(e.target.dataset.tab));
    });
});

async function loadModuleConfig() {
    try {
        const res = await fetch('api_get_modules.php');
        const data = await res.json();
        if (data.status === 'success') {
            appsConfig = data.data;
        }
    } catch (error) {
        console.warn("Using cached/mock modules map.");
    }
}

async function loadStoresRegistry() {
    try {
        const res = await fetch('api_get_stores.php');
        const data = await res.json();
        if (data.status === 'success') {
            allStoresRegistry = data.stores;
        }
    } catch (error) {
        console.warn("Failed to load stores registry.");
    }
}

async function handleLogin(e) {
    e.preventDefault();
    const btn = document.getElementById('loginBtn');
    btn.innerHTML = 'Authenticating...'; btn.disabled = true;

    try {
        const res = await fetch('api_login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: document.getElementById('username').value, password: document.getElementById('password').value })
        });
        const data = await res.json();

        if (data.status === 'success') {
            // DIRECT STORE LOGIN INTERCEPT
            if (data.type === 'store_direct') {
                window.location.href = data.redirect;
                return; 
            }

            // CORPORATE IAM LOGIN
            activeSession = data.user;
            showDashboard();
        } else {
            alert(data.message);
        }
    } catch (err) {
        alert("System error connecting to the authentication server.");
        console.error(err);
    } finally {
        btn.innerHTML = 'Sign In'; btn.disabled = false;
    }
}

function showDashboard() {
    document.getElementById('loginView').classList.add('hidden');
    document.getElementById('adminView').classList.add('hidden');
    document.getElementById('dashboardView').classList.remove('hidden');
    document.getElementById('userNameDisplay').textContent = activeSession.name || activeSession.username;
    
    const tilesDiv = document.getElementById('appTiles');
    tilesDiv.innerHTML = '';

    // 1. Render standard modules based on permissions
    activeSession.modules.forEach(modId => {
        if (modId === 'store') return; 

        const config = appsConfig[modId];
        if(config) {
            const tile = document.createElement('div');
            tile.className = 'tile';
            tile.innerHTML = `
                <span class="material-symbols-outlined tile-icon">${config.icon}</span>
                <h3>${config.name}</h3>
                <p>${config.description}</p>
            `;
            let primaryRole = activeSession.role_details && activeSession.role_details[modId] ? activeSession.role_details[modId][0] : 'user';
            tile.onclick = () => launchApp(modId, primaryRole);
            tilesDiv.appendChild(tile);
        }
    });

    // 2. AUTOMATIC STORE TILE: Bulletproof check for assigned_store
    let assigned = activeSession.assigned_store;
    if (assigned && String(assigned).trim() !== '' && String(assigned).trim() !== 'null') {
        const storeConfig = appsConfig['store'] || { icon: 'storefront', name: 'Store Operations', description: 'Dedicated Store Level Dashboard' };
        const storeTile = document.createElement('div');
        storeTile.className = 'tile';
        storeTile.innerHTML = `
            <span class="material-symbols-outlined tile-icon">${storeConfig.icon}</span>
            <h3>${storeConfig.name}</h3>
            <p style="color: var(--accent-color); font-weight: bold;">Store ID: ${assigned}</p>
        `;
        // Send them to the router which translates this into store/dashboard.php
        storeTile.onclick = () => launchApp('store', 'store_user');
        tilesDiv.appendChild(storeTile);
    }

    // 3. Admin Tile
    if(activeSession.isAdmin || activeSession.is_iam_admin) {
        const adminTile = document.createElement('div');
        adminTile.className = 'tile';
        adminTile.innerHTML = `
            <span class="material-symbols-outlined tile-icon">admin_panel_settings</span>
            <h3>IAM Console</h3>
            <p>Manage Users & Roles</p>
        `;
        adminTile.onclick = showAdminPanel;
        tilesDiv.appendChild(adminTile);
    }
}

function launchApp(moduleId, primaryRole) {
    if (moduleId === 'labeller') {
        const token = Math.random().toString(36).substring(2) + Date.now().toString(36);
        localStorage.setItem('authToken', token);
        localStorage.setItem('userRole', primaryRole);
        localStorage.setItem('userName', activeSession.username);
    }
    window.location.href = `router.php?module=${moduleId}`;
}

function switchAdminTab(tab) {
    document.getElementById('adminUsersSection').classList.toggle('hidden', tab !== 'users');
    document.getElementById('adminCreateUserSection').classList.toggle('hidden', tab !== 'create');
    document.getElementById('adminModulesSection').classList.toggle('hidden', tab !== 'modules');
    
    document.getElementById('tabUsers').style.background = tab === 'users' ? 'var(--accent-color)' : 'rgba(255,255,255,0.1)';
    document.getElementById('tabCreate').style.background = tab === 'create' ? 'var(--accent-color)' : 'rgba(255,255,255,0.1)';
    document.getElementById('tabModules').style.background = tab === 'modules' ? 'var(--accent-color)' : 'rgba(255,255,255,0.1)';

    if (tab === 'modules') loadAdminModules();
    if (tab === 'users') showAdminPanel();
}

async function showAdminPanel() {
    if(document.getElementById('adminView').classList.contains('hidden')) {
        document.getElementById('dashboardView').classList.add('hidden');
        document.getElementById('adminView').classList.remove('hidden');
        switchAdminTab('users'); 
    }

    const listContainer = document.getElementById('userListContainer');
    listContainer.innerHTML = '<div style="text-align:center; padding: 20px; color: var(--text-secondary);">Syncing directory...</div>';
    
    try {
        const res = await fetch('api_get_users.php');
        const data = await res.json();
        
        if (data.status === 'success') {
            allUsersDirectory = data.users.filter(u => u.username !== 'admin'); 
            renderUserList(allUsersDirectory);
        }
    } catch (err) {
        listContainer.innerHTML = '<div style="text-align:center; padding: 20px; color: var(--danger);">Directory sync failed.</div>';
    }
}

function renderUserList(users) {
    const listContainer = document.getElementById('userListContainer');
    listContainer.innerHTML = '';
    
    if(users.length === 0) {
        listContainer.innerHTML = '<div style="text-align:center; padding: 20px; color: var(--text-secondary);">No users found.</div>';
        return;
    }

    users.forEach(user => {
        const item = document.createElement('div');
        item.className = 'user-item';
        item.dataset.userId = user.id;
        
        const initial = user.full_name ? user.full_name.charAt(0).toUpperCase() : user.username.charAt(0).toUpperCase();
        
        item.innerHTML = `
            <div class="user-item-avatar">${initial}</div>
            <div class="user-item-info">
                <strong>${user.username}</strong>
                <small>${user.full_name || 'No Name'}</small>
            </div>
        `;
        
        item.onclick = () => {
            document.querySelectorAll('.user-item').forEach(el => el.classList.remove('active'));
            item.classList.add('active');
            renderUserDetail(user);
        };
        
        listContainer.appendChild(item);
    });
}

function filterUserList(searchTerm) {
    const term = searchTerm.toLowerCase();
    const filtered = allUsersDirectory.filter(u => 
        u.username.toLowerCase().includes(term) || 
        (u.full_name && u.full_name.toLowerCase().includes(term))
    );
    renderUserList(filtered);
}

function renderUserDetail(user) {
    const detailContainer = document.getElementById('userDetailContainer');
    
    let modulesHtml = '<div class="modules-grid">';
    Object.keys(appsConfig).forEach(modId => {
        if (modId === 'store') return; 

        const mod = appsConfig[modId];
        const availableRoles = roleDefinitions[modId] || ['admin', 'user']; 
        const userHasRoles = user.app_permissions[modId] || [];

        modulesHtml += `
            <div class="module-card">
                <div class="module-card-header">
                    <span class="material-symbols-outlined">${mod.icon}</span>
                    <h4>${mod.name}</h4>
                </div>
                <div class="role-pills-container">
        `;
        
        availableRoles.forEach(role => {
            const isActive = userHasRoles.includes(role);
            const activeClass = isActive ? 'active' : '';
            const checkedAttr = isActive ? 'checked' : '';
            
            modulesHtml += `
                <label class="role-pill ${activeClass}" onclick="togglePill(this)">
                    <input type="checkbox" class="role-cb" data-mod="${modId}" value="${role}" ${checkedAttr}>
                    <span class="material-symbols-outlined" style="font-size: 14px;">${isActive ? 'check_circle' : 'radio_button_unchecked'}</span>
                    ${role}
                </label>
            `;
        });
        modulesHtml += `</div></div>`;
    });
    modulesHtml += '</div>';

    // FIX: Put the ID and the Name in the 'value' so the browser displays both during search
    let storeOptions = allStoresRegistry.map(s => `<option value="${s.id} - ${s.name} (${s.brand})"></option>`).join('');

    detailContainer.innerHTML = `
        <div class="detail-header" style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: none;">
            <div>
                <h2 style="font-size: 22px; margin-bottom: 5px;">${user.full_name || user.username}</h2>
                <p style="color: var(--text-secondary); font-size: 14px;">Network ID: ${user.username}</p>
            </div>
        </div>

        <div class="inner-tabs">
            <div class="inner-tab active" onclick="switchInnerTab('policies')">Access Policies</div>
            <div class="inner-tab" onclick="switchInnerTab('management')">Profile Management</div>
        </div>

        <div id="pane-policies" class="inner-pane active">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="font-size: 14px; color: var(--text-secondary);">Application Access & Roles</h3>
                <button class="btn-primary" style="width: auto; padding: 8px 16px; font-size: 13px;" onclick="saveActiveUser(${user.id})">
                    <span class="material-symbols-outlined" style="font-size: 18px;">save</span> Save Policies
                </button>
            </div>
            ${modulesHtml}

            <div style="margin-top: 20px; padding: 15px; background: rgba(0,0,0,0.2); border: 1px solid var(--card-border); border-radius: 8px;">
                <h4 style="font-size: 14px; margin-bottom: 8px; color: var(--accent-color);">Store Association Binding</h4>
                <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 12px;">Search and select a Store to grant this user direct access.</p>
                
                <input type="text" id="assignStoreId" list="storeDatalist" placeholder="Search Store ID or Name..." value="${user.assigned_store || ''}" autocomplete="off" style="width: 100%; max-width: 400px; padding: 12px; font-size: 14px; background: var(--input-bg); color: #fff; border: 1px solid var(--card-border); border-radius: 6px;">
                <datalist id="storeDatalist">
                    ${storeOptions}
                </datalist>

            </div>
        </div>

        <div id="pane-management" class="inner-pane">
            <form id="editUserForm" onsubmit="updateUserProfile(event, ${user.id})">
                <div class="form-grid">
                    <div>
                        <label>Employee ID</label>
                        <input type="text" id="editEmpId" value="${user.emp_id || ''}">
                    </div>
                    <div>
                        <label>Username</label>
                        <input type="text" id="editUsername" value="${user.username}" required>
                    </div>
                    <div>
                        <label>Full Name</label>
                        <input type="text" id="editFullName" value="${user.full_name || ''}" required>
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" id="editEmail" value="${user.email || ''}">
                    </div>
                    <div>
                        <label>Department</label>
                        <select id="editDept" style="color: #fff; background: var(--input-bg);">
                            <option value="IT" ${user.department === 'IT' ? 'selected' : ''}>IT & Procurement</option>
                            <option value="Operations" ${user.department === 'Operations' ? 'selected' : ''}>Store Operations</option>
                            <option value="Finance" ${user.department === 'Finance' ? 'selected' : ''}>Cash Collection & Finance</option>
                        </select>
                    </div>
                    <div>
                        <label>Phone</label>
                        <input type="text" id="editPhone" value="${user.phone || ''}">
                    </div>
                </div>
                <div style="margin-top: 15px; text-align: right;">
                    <button type="submit" class="btn-primary" style="width: auto; display: inline-flex; padding: 8px 16px; font-size: 13px;">Update Profile</button>
                </div>
            </form>

            <div class="danger-zone">
                <h4>Security & Authentication</h4>
                <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 10px;">Force a password reset. The user will be required to change this upon their next login.</p>
                <div class="input-group">
                    <input type="text" id="newTempPassword" placeholder="Enter new temporary password">
                    <button class="btn-primary" style="width: auto; background: var(--danger); padding: 12px 20px;" onclick="resetUserPassword(${user.id})">Force Reset</button>
                </div>
            </div>
        </div>
    `;
}

window.switchInnerTab = function(tabName) {
    document.querySelectorAll('.inner-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.inner-pane').forEach(p => p.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById(`pane-${tabName}`).classList.add('active');
};

window.updateUserProfile = async function(e, userId) {
    e.preventDefault();
    const payload = {
        id: userId,
        emp_id: document.getElementById('editEmpId').value,
        username: document.getElementById('editUsername').value,
        full_name: document.getElementById('editFullName').value,
        email: document.getElementById('editEmail').value,
        department: document.getElementById('editDept').value,
        phone: document.getElementById('editPhone').value
    };

    try {
        const res = await fetch('api_update_profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            alert('User profile updated successfully.');
            showAdminPanel(); 
        } else {
            alert('Update failed: ' + data.message);
        }
    } catch (err) {
        alert('Communication error updating profile.');
    }
};

window.resetUserPassword = async function(userId) {
    const newPass = document.getElementById('newTempPassword').value;
    if(!newPass || newPass.length < 6) {
        return alert("Please enter a valid temporary password (min 6 characters).");
    }

    if(confirm("Are you sure you want to force reset this user's password?")) {
        try {
            const res = await fetch('api_reset_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: userId, new_password: newPass })
            });
            const data = await res.json();
            if (data.status === 'success') {
                alert('Password reset successfully.');
                document.getElementById('newTempPassword').value = '';
            } else {
                alert('Reset failed: ' + data.message);
            }
        } catch (err) {
            alert('Communication error resetting password.');
        }
    }
};

window.togglePill = function(labelElement) {
    const checkbox = labelElement.querySelector('input[type="checkbox"]');
    const icon = labelElement.querySelector('.material-symbols-outlined');
    
    setTimeout(() => {
        if (checkbox.checked) {
            labelElement.classList.add('active');
            icon.textContent = 'check_circle';
        } else {
            labelElement.classList.remove('active');
            icon.textContent = 'radio_button_unchecked';
        }
    }, 10);
};

window.saveActiveUser = async function(userId) {
    const checkboxes = document.getElementById('userDetailContainer').querySelectorAll(`.role-cb:checked`);
    const newPerms = {};
    
    checkboxes.forEach(cb => {
        const mod = cb.dataset.mod;
        const role = cb.value;
        if (!newPerms[mod]) newPerms[mod] = [];
        newPerms[mod].push(role);
    });

    // FIX: Parse out just the ID before the hyphen to send to the database
    let rawStoreStr = document.getElementById('assignStoreId').value;
    let finalAssignedStore = rawStoreStr ? rawStoreStr.split('-')[0].trim() : null;

    try {
        const res = await fetch('api_update_permissions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                user_id: userId, 
                app_permissions: JSON.stringify(newPerms),
                assigned_store: finalAssignedStore // Clean ID goes to DB
            })
        });
        const data = await res.json();
        if (data.status === 'success') {
            alert('Access policies successfully updated for this user.');
            
            const cachedUser = allUsersDirectory.find(u => u.id === userId);
            if(cachedUser) {
                cachedUser.app_permissions = newPerms;
                cachedUser.assigned_store = finalAssignedStore;
            }
        } else {
            alert('Error updating policies: ' + data.message);
        }
    } catch (err) {
        alert('Server connection timeout. Changes not saved.');
    }
};

async function loadAdminModules() {
    const tbody = document.getElementById('modulesTableBody');
    tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--text-secondary);">Reading tracking data registry...</td></tr>';
    try {
        const res = await fetch('api_admin_modules.php');
        const data = await res.json();
        
        if (data.status === 'success') {
            tbody.innerHTML = '';
            data.modules.forEach(mod => {
                tbody.innerHTML += `
                    <tr>
                        <td><span class="material-symbols-outlined" style="font-size: 30px; color: var(--accent-color);">${mod.icon}</span></td>
                        <td>
                            <strong style="color:#fff;">${mod.name}</strong><br>
                            <small style="color: var(--text-secondary);">ID: ${mod.module_id} | ${mod.description}</small>
                        </td>
                        <td><span style="color: ${mod.is_active == 1 ? 'var(--success)' : 'var(--danger)'}; font-weight: bold;">${mod.is_active == 1 ? 'Active' : 'Inactive'}</span></td>
                    </tr>
                `;
            });
        }
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="3" style="color: var(--danger);">Failed listing application models data logs.</td></tr>';
    }
}

async function createNewUser(e) {
    e.preventDefault();
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.innerHTML = 'Provisioning...'; submitBtn.disabled = true;

    const tempPassword = "Temp@" + Math.floor(1000 + Math.random() * 9000); 

    const payload = {
        emp_id: document.getElementById('newEmpId').value,
        full_name: document.getElementById('newFullName').value,
        username: document.getElementById('newUsername').value,
        email: document.getElementById('newEmail').value,
        department: document.getElementById('newDept').value,
        manager: document.getElementById('newManager').value,
        phone: document.getElementById('newPhone').value,
        temp_password: tempPassword,
        mail_subject: "Corporate User ID Created",
        mail_body: `Dear ${document.getElementById('newFullName').value},\n\nYour corporate user ID has been created.\n\nUser ID: ${document.getElementById('newEmail').value}\nTemporary Password: ${tempPassword}\n\nUse the above credentials to log in at portal.apparelgroup.com.\nYou will be asked to change your password on first login.\n\nThank you,\nIT Support`
    };

    try {
        const res = await fetch('api_add_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            alert("Success! User provisioned and email sent to " + payload.email);
            e.target.reset(); 
            showAdminPanel(); 
        } else {
            alert("Error: " + data.message);
        }
    } catch (err) {
        alert("System error sending payload to api_add_user.php");
    } finally {
        submitBtn.innerHTML = 'Create Identity & Send Credentials'; submitBtn.disabled = false;
    }
}

async function addNewModule() {
    const payload = {
        module_id: document.getElementById('newModId').value.toLowerCase().replace(/\s+/g, '_'),
        name: document.getElementById('newModName').value,
        icon: document.getElementById('newModIcon').value,
        description: document.getElementById('newModDesc').value,
        route_map: document.getElementById('newModRoutes').value
    };

    if (!payload.module_id || !payload.name || !payload.route_map) {
        return alert("Module ID, Name, and Routing Configuration Map JSON fields are completely mandatory strings.");
    }

    try {
        const res = await fetch('api_add_module.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            alert("Module successfully verified and loaded dynamically into registry maps.");
            document.getElementById('newModId').value = '';
            document.getElementById('newModName').value = '';
            document.getElementById('newModDesc').value = '';
            document.getElementById('newModRoutes').value = '';
            loadAdminModules();
            loadModuleConfig();
        } else {
            alert(data.message);
        }
    } catch (err) {
        alert("Critical server handshake interruption while creating application record.");
    }
}

function logout() {
    localStorage.clear();
    window.location.href = 'api_logout.php';
}