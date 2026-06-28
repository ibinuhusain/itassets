<?php
// 1. SECURE IAM PHP BRIDGE
session_set_cookie_params([
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']), 
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. KICK OUT UNREGISTERED USERS
if (!isset($_SESSION['iam_user'])) {
        header("Location: ../index.html");
    exit;
}

// 3. EXTRACT SPECIFIC LABELLER ROLES
$labeller_roles = $_SESSION['iam_user']['roles']['labeller'] ?? [];
if (empty($labeller_roles)) {
    die("Unauthorized module access.");
}

// Grab the highest authority role for the UI
$primary_role = $labeller_roles[0]; 
$username = $_SESSION['iam_user']['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retail Master Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
    
    <style>
        /* (Keep all your existing CSS styles here) */
        :root { --bg-card: #13151a; --border-color: rgba(255, 255, 255, 0.08); --text-muted: #8a8f98; --accent-blue: #3b82f6; --accent-danger: #ef4444; --bg-input: #1c1f26; --success: #10b981; --warning: #f59e0b; }
        body { background-color: #0b0c10; display: flex; height: 100vh; overflow: hidden; font-family: 'Segoe UI', Inter, sans-serif; color: #f3f4f6; margin: 0; }
        .sidebar { width: 280px; height: 100vh; background-color: rgba(23, 25, 49, 0.95); color: #fff; display: flex; flex-direction: column; z-index: 100; border-right: 1px solid var(--border-color); }
        .sidebar .nav-link { color: var(--text-muted); padding: 14px 24px; cursor: pointer; transition: all 0.2s; font-size: 0.95rem; display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .sidebar .nav-link:hover { background-color: rgba(255, 255, 255, 0.05); color: #fff; }
        .sidebar .nav-link.active { background-color: rgba(99, 102, 241, 0.1); color: var(--accent-blue); font-weight: 600; }
        .main-content { flex-grow: 1; height: 100vh; display: flex; flex-direction: column; overflow: hidden; width: calc(100% - 280px); }
        .top-header { display: flex; justify-content: space-between; align-items: center; padding: 0 24px; min-height: 70px; background: var(--bg-card); border-bottom: 1px solid var(--border-color); }
        .content { flex: 1; padding: 24px; background: #0f0f1a; overflow-y: auto; width: 100%; box-sizing: border-box; }
        #pageTitle { font-weight: 600; color: #ffffff; font-size: 1.5rem; letter-spacing: -0.5px; }
        .mapping-workspace-grid { display: grid; grid-template-columns: 380px 1fr; gap: 24px; align-items: start; width: 100%; }
        @media (max-width: 1200px) { .mapping-workspace-grid { grid-template-columns: 1fr; } }
        .panel-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); margin-bottom: 24px; }
        .panel-card h3 { font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; color: #fff; margin-top: 0; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .table-scroll-container { background: #16181f; border: 1px solid var(--border-color); border-radius: 8px; overflow-x: auto; max-height: 500px; overflow-y: auto; }
        .table { margin-bottom: 0; width: 100%; border-collapse: collapse; }
        .table thead th { position: sticky; top: 0; z-index: 10; border-bottom: 1px solid var(--border-color) !important; background: #1c1f26 !important; color: var(--text-muted) !important; font-weight: 500; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 14px 16px; }
        .table tbody tr { background-color: rgba(28, 31, 38, 0.4) !important; }
        .table tbody td { vertical-align: middle; border-bottom: 1px solid var(--border-color) !important; color: #e2e8f0 !important; background: transparent !important; font-size: 0.85rem; padding: 14px 16px; }
        .table tbody tr:hover td { background-color: rgba(255, 255, 255, 0.04) !important; }
        .form-control, .form-select { background-color: var(--bg-input) !important; border: 1px solid var(--border-color) !important; color: #fff !important; border-radius: 8px; padding: 10px 14px; font-size: 0.9rem; box-shadow: none; }
        .form-control:focus, .form-select:focus { background-color: var(--bg-input) !important; border-color: var(--accent-blue) !important; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important; outline: none; }
        .form-label { font-weight: 500; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; margin-bottom: 6px; }
        .table-header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 16px; flex-wrap: wrap; }
        .table-header-bar h2 { margin: 0; font-size: 16px; color: #fff; font-weight: 600; }
        .search-wrapper { position: relative; width: 100%; max-width: 320px; }
        .search-wrapper .material-symbols-outlined { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 20px; }
        .search-input { width: 100%; background: var(--bg-input); border: 1px solid var(--border-color); color: #fff; padding: 10px 12px 10px 40px; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
        .mapping-tabs { display: flex; gap: 8px; border-bottom: 1px solid var(--border-color); margin-bottom: 20px; padding-bottom: 8px; }
        .map-tab-btn { background: transparent; border: none; color: var(--text-muted); padding: 8px 16px; font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
        .map-tab-btn:hover { background: rgba(255, 255, 255, 0.03); color: #fff; }
        .map-tab-btn.active { background: rgba(59, 130, 246, 0.1); color: var(--accent-blue); }
        .dual-listbox-container { display: grid; grid-template-columns: 1fr 130px 1fr; gap: 16px; align-items: center; width: 100%; }
        @media (max-width: 768px) { .dual-listbox-container { grid-template-columns: 1fr; } .control-action-arrows { flex-direction: row !important; } }
        .dual-listbox select { height: 320px; overflow-y: auto; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 8px; color: #fff; width: 100%; padding: 6px; }
        .dual-listbox option { padding: 10px 14px; border-radius: 6px; margin-bottom: 2px; color: #d1d5db; font-size: 0.85rem; }
        .dual-listbox option:hover { background-color: rgba(255, 255, 255, 0.02); }
        .dual-listbox option:checked { background-color: var(--accent-blue) !important; color: #fff !important; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 16px; border-radius: 8px; font-weight: 500; font-size: 13px; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background-color: var(--accent-blue); color: white; }
        .btn-primary:hover { background-color: #2563eb; }
        .btn-action-delete { background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); border: 1px solid rgba(239, 68, 68, 0.2); padding: 4px 12px; font-size: 0.8rem; border-radius: 6px; transition: all 0.2s; }
        .btn-action-delete:hover { background: var(--accent-danger); color: white; }
        .btn-action-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); border: 1px solid rgba(245, 158, 11, 0.2); padding: 4px 12px; font-size: 0.8rem; border-radius: 6px; transition: all 0.2s; }
        .btn-action-warning:hover { background: var(--warning); color: #000; }
        .btn-action-success { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); padding: 4px 12px; font-size: 0.8rem; border-radius: 6px; }
        .btn-action-success:hover { background: var(--success); color: white; }
        .badge-role { background-color: rgba(99, 102, 241, 0.15); color: #a5b4fc; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
        .badge-status { background-color: rgba(16, 185, 129, 0.15); color: #10b981; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
        .badge-cyan { background-color: rgba(6, 182, 212, 0.15); color: #22d3ee; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
        .text-pinkish { color: #f43f5e; font-family: monospace; font-size: 0.85rem; font-weight: 500; }
        .upload-confirm-box { background-color: rgba(59, 130, 246, 0.02); border: 2px dashed var(--border-color); border-radius: 8px; padding: 24px; text-align: center; }
        .table-scroll-container::-webkit-scrollbar, .dual-listbox select::-webkit-scrollbar { width: 6px; height: 6px; }
        .table-scroll-container::-webkit-scrollbar-track, .dual-listbox select::-webkit-scrollbar-track { background: transparent; }
        .table-scroll-container::-webkit-scrollbar-thumb, .dual-listbox select::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.12); border-radius: 4px; }
    </style>
</head>
<body>

    <script>
        const currentRole = "<?php echo $primary_role; ?>";
        const currentUserName = "<?php echo $username; ?>";
        
        if (currentRole === 'app_user') {
            document.write("<h3 style='color:white; padding:20px;'>Field Auditors do not have dashboard matrix access. Use the terminal application.</h3>");
            setTimeout(() => { window.location.href = '../index.html'; }, 3000);
        }
    </script>

    <div class="sidebar" id="sidebar">
        <div class="p-4 mb-2 border-bottom border-secondary text-center">
            <h4 class="mb-0 text-white fw-bold tracking-wide">Price Label App</h4>
            <div class="mt-2 text-info small fw-bold" id="displayUserName"></div>
            <div class="text-light small" id="displayUserRole"></div>
        </div>
        <ul class="nav flex-column mb-auto mt-2" id="navMenu">
            <ul class="nav flex-column mb-auto mt-2" id="navMenu"></ul>
        </ul>
        <div class="p-4 border-top border-secondary">
            <button class="btn btn-outline-light w-100 btn-sm fw-bold" style="border-radius: 6px;" onclick="window.location.href='../api_logout.php'">Logout Securely</button>
        </div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="d-flex align-items-center gap-3">
                <h3 id="pageTitle" class="mb-0">Dashboard</h3>
            </div>
            <div>
                <button id="globalPrintBtn" class="btn btn-primary d-none bg-white text-dark border" onclick="window.print()">🖨️ Print System Report</button>
            </div>
        </div>
        
        <div class="content" id="appContainer"></div>
    </div>

    <script src="app.js?v=<?php echo time(); ?>"></script>
</body>
</html>