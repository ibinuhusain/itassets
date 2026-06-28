<?php
// 1. FORCE DOMAIN-WIDE SESSION COOKIE
ini_set('session.cookie_path', '/');
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
session_name('PHPSESSID');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. KICK OUT UNREGISTERED USERS
if (!isset($_SESSION['iam_user'])) {
    header("Location: /iam/index.html");
    exit;
}

// 3. VERIFY PROCUREMENT CLEARANCE
$po_roles = $_SESSION['iam_user']['roles']['procurement'] ?? [];

if (empty($po_roles)) {
    die("<div style='background:#0b1329; height:100vh; color:white; text-align:center; padding-top:100px; font-family:sans-serif;'><h2>Access Denied</h2><p>You do not have clearance for the Procurement module.</p></div>");
}

$primary_role = $po_roles[0]; 
$username = $_SESSION['iam_user']['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Operations Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --bg-main: #0b1329; --bg-card: #1c2541; --text-primary: #f8fafc; --text-secondary: #cbd5e1;
            --border-color: #334155; --accent-primary: #3a86ff; --accent-hover: #00b4d8;
            --success-text: #34d399; --danger-text: #f87171; --info-text: #38bdf8;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-main); color: var(--text-primary); display: flex; min-height: 100vh; }
        
        .sidebar { width: 280px; background: var(--bg-card); border-right: 1px solid var(--border-color); padding: 32px 24px; position: sticky; top: 0; height: 100vh; }
        .logo-area { font-size: 1.5rem; font-weight: 800; margin-bottom: 48px; color: var(--text-primary); display: flex; align-items: center; gap: 10px; }
        .logo-area i { color: var(--accent-primary); }
        .nav-item { padding: 14px 16px; margin: 8px 0; border-radius: 12px; font-weight: 600; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; gap: 12px; transition: 0.2s; }
        .nav-item.active { background: #1e3a8a; color: white; border-left: 4px solid var(--accent-primary); }
        .nav-item:hover:not(.active) { background: #243056; color: white; }
        
        .main-content { flex: 1; padding: 32px 40px; overflow-y: auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; border-bottom: 1px solid var(--border-color); padding-bottom: 20px; }
        .btn-logout { background: none; border: 1px solid var(--danger-text); color: var(--danger-text); padding: 8px 16px; border-radius: 20px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-logout:hover { background: #7f1d1d; color: white; }
        .btn-create { background: var(--accent-primary); border: none; padding: 10px 20px; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-create:hover { background: var(--accent-hover); }
        
        .card-panel { background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border-color); overflow: hidden; margin-bottom: 32px; display: none; box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
        .card-panel.active { display: block; }
        .card-header { padding: 20px 24px; background: #222c4e; border-bottom: 1px solid var(--border-color); font-weight: 700; font-size: 1.2rem; display: flex; justify-content: space-between; align-items: center; }
        
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 16px 20px; background: #0f172a; color: var(--text-secondary); font-size: 0.85rem; text-transform: uppercase; border-bottom: 1px solid var(--border-color); }
        td { padding: 16px 20px; border-bottom: 1px solid var(--border-color); font-size: 0.95rem; }
        tr:hover td { background: #243056; }
        
        .status { padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: bold; }
        .status.consumable { background: #fef3c7; color: #d97706; }
        .status.asset { background: #e0e7ff; color: #4f46e5; }
        
        input[type="number"] { width: 90px; padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); background: #111827; color: white; text-align: center; font-weight: bold; }
        input[type="number"]:focus { border-color: var(--accent-primary); outline: none; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo-area"><i class="fas fa-store"></i> Store Portal</div>
        <div class="nav-item active" onclick="switchTab('inventory')"><i class="fas fa-box-open"></i> Assigned Inventory</div>
        <div class="nav-item" onclick="switchTab('request')"><i class="fas fa-hand-holding-box"></i> Request Stock</div>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <h2>Location: <span id="storeName" style="color: var(--accent-primary);"></span></h2>
            <div style="display: flex; gap: 20px; align-items: center;">
                <span style="color: var(--text-primary); font-weight: 600; background: var(--info-bg); padding: 8px 16px; border-radius: 20px; border: 1px solid var(--info-text);">
                    Route: <span id="storeRoute"></span>
                </span>
                <button class="btn-logout" onclick="logout()"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </div>
        </div>

        <div id="inventoryPanel" class="card-panel active">
            <div class="card-header"><i class="fas fa-list-check"></i> Stock Assigned to this Location</div>
            <div style="max-height: 600px; overflow-y: auto;">
                <table>
                    <thead><tr><th>Manifest ID</th><th>SKU</th><th>Item Name</th><th>Category</th><th>Qty Received</th><th>Date</th></tr></thead>
                    <tbody id="inventoryBody"></tbody>
                </table>
            </div>
        </div>

        <div id="requestPanel" class="card-panel">
            <div class="card-header">
                <div><i class="fas fa-cart-plus"></i> Central Warehouse Catalog</div>
                <button class="btn-create" onclick="submitRequest()"><i class="fas fa-paper-plane"></i> Submit Request</button>
            </div>
            <div style="max-height: 400px; overflow-y: auto;">
                <table>
                    <thead><tr><th>SKU</th><th>Item Details</th><th>Type</th><th>WH Balance</th><th>Request Qty</th></tr></thead>
                    <tbody id="catalogBody"></tbody>
                </table>
            </div>

            <div style="padding: 24px; border-top: 4px solid #0b1329;">
                <h3 style="margin-bottom: 16px; color: var(--text-primary); border-left: 4px solid var(--accent-primary); padding-left: 10px;">
                    <i class="fas fa-history"></i> My Request Status Ledger
                </h3>
                <div id="statusHistory" style="max-height: 250px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>

    <script>
        // Directly inherit context from the secure PHP Session
        const user = "<?php echo $username; ?>";
        const role = "<?php echo $primary_role; ?>";
        const route = "N/A"; // You can inject this from DB later if needed

        document.getElementById('storeName').innerText = user;
        document.getElementById('storeRoute').innerText = route;

        let masterInventory = [];

        async function initStore() {
            try {
                // 1. Fetch Master Inventory for Catalog
                let invRes = await fetch('procurement_api.php?action=list_inventory');
                masterInventory = await invRes.json();
                renderCatalog();

                // 2. Fetch My Dispatches
                let dispRes = await fetch('procurement_api.php?action=list_dispatch');
                let allDispatches = await dispRes.json();
                renderInventory(allDispatches.filter(d => String(d.store_code) === String(user)));

                // 3. Fetch My Requests
                let reqRes = await fetch('procurement_api.php?action=list_store_requests');
                let allReqs = await reqRes.json();
                renderStatus(allReqs.filter(r => String(r.username) === String(user)));
            } catch (err) { console.error("Error connecting to database."); }
        }

        function renderInventory(dispatches) {
            const tbody = document.getElementById('inventoryBody');
            if (dispatches.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:var(--text-muted); font-style:italic; padding:40px;">No physical items or assets currently assigned to this location.</td></tr>`;
                return;
            }
            tbody.innerHTML = dispatches.map(d => `
                <tr>
                    <td><strong>${d.dispatch_id}</strong></td>
                    <td style="color:var(--accent-primary); font-family:monospace; font-weight:bold;">${d.sku}</td>
                    <td><strong>${d.item_name || 'Classified Asset'}</strong></td>
                    <td><span style="background:var(--bg-main); padding:4px 8px; border-radius:6px; font-size:0.8rem; border:1px solid var(--border-color);">${d.category || 'General'}</span></td>
                    <td style="color:var(--success-text); font-weight:800; font-size:1.1rem;">${d.dispatch_qty || d.qty} Units</td>
                    <td style="color:var(--text-secondary); font-size:0.85rem;">${new Date(d.dispatched_at).toLocaleDateString()}</td>
                </tr>
            `).join('');
        }

        function renderCatalog() {
            document.getElementById('catalogBody').innerHTML = masterInventory.map(item => {
                const descLower = (item.item_name || '').toLowerCase();
                const isConsumable = descLower.includes('label') || descLower.includes('ribbon') || descLower.includes('paper') || descLower.includes('roll') || descLower.includes('tape');
                const typeClass = isConsumable ? 'consumable' : 'asset';
                const typeText = isConsumable ? '<i class="fas fa-vial"></i> Consumable' : '<i class="fas fa-box"></i> Non-Consumable';
                
                return `
                    <tr>
                        <td style="font-family:monospace; font-weight:bold; color:var(--text-primary);">${item.sku}</td>
                        <td><strong>${item.item_name}</strong><br><small style="color:var(--text-muted);">Category: ${item.category}</small></td>
                        <td><span class="status ${typeClass}">${typeText}</span></td>
                        <td><strong>${item.quantity_on_hand}</strong> Units</td>
                        <td><input type="number" min="0" max="${item.quantity_on_hand}" data-sku="${item.sku}" data-name="${item.item_name}" class="req-input" placeholder="0"></td>
                    </tr>
                `;
            }).join('');
        }

        function renderStatus(requests) {
            const container = document.getElementById('statusHistory');
            if (requests.length === 0) {
                container.innerHTML = `<p style="color:var(--text-muted); font-style:italic;">No historical requests processed from this node.</p>`;
                return;
            }
            container.innerHTML = requests.map(r => {
                let items = [];
                try { items = JSON.parse(r.details); } catch(e){}
                const summary = items.map(i => `• ${i.qty}x [${i.sku}] ${i.name}`).join('<br>');
                return `
                    <div style="background:#111827; padding:16px; border-radius:12px; margin-bottom:12px; border:1px solid #334155; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:8px;"><i class="far fa-clock"></i> ${new Date(r.timestamp).toLocaleString()}</div>
                            <div style="font-weight:500; line-height:1.4;">${summary}</div>
                        </div>
                        <span style="color:#f59e0b; border:1px solid #f59e0b; padding:6px 12px; border-radius:8px; font-size:0.85rem; font-weight:bold;"><i class="fas fa-hourglass-start"></i> Pending Approval</span>
                    </div>
                `;
            }).join('');
        }

        async function submitRequest() {
            const inputs = document.querySelectorAll('.req-input');
            const items = [];
            inputs.forEach(i => {
                let qty = parseInt(i.value || 0);
                if (qty > 0) items.push({ sku: i.getAttribute('data-sku'), name: i.getAttribute('data-name'), qty });
            });

            if (items.length === 0) return alert("Please enter quantities to request.");

            try {
                const res = await fetch('procurement_api.php?action=request_inventory', {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ storeId: user, items })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    alert("Handshake complete: Procurement allocation sent.");
                    inputs.forEach(i => i.value = '');
                    initStore(); // Refresh tables live
                }
            } catch (e) { alert("Failed to send request."); }
        }

        function switchTab(tab) {
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            document.querySelectorAll('.card-panel').forEach(p => p.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.getElementById(tab + 'Panel').classList.add('active');
        }

        function logout() {
            window.location.replace('../api_logout.php');
        }

        // Boot up
        initStore();
    </script>
</body>
</html>