<?php
require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();
$error = '';

// --- FETCH DATA ---
try {
    $stores = $pdo->query("SELECT s.*, r.name AS region_name FROM stores s LEFT JOIN regions r ON s.region_id = r.id ORDER BY s.name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching stores: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Directory - Apparels Collection</title>
    <link rel="stylesheet" href="../css/modern-dashboard.css">
    <link rel="stylesheet" href="../css/management.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" type="image/png" href="/images/icon-192x192.png">
    <script src="../js/app.js" defer></script>
    
    <style>
        :root {
            --bg-card: #13151a;
            --border-color: rgba(255, 255, 255, 0.08);
            --text-muted: #8a8f98;
            --accent-blue: #3b82f6;
            --bg-input: #1c1f26;
            --success: #10b981;
        }

        body {
            background-color: #0b0c10;
            color: #f3f4f6;
            margin: 0;
        }

        .content {
            padding: 24px;
            width: 100%;
            box-sizing: border-box;
        }

        /* Full Screen Single Grid Integration Layout */
        .store-grid {
            display: block;
            width: 100%;
        }

        .panel-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            margin-bottom: 24px;
        }

        h2 {
            font-size: 18px;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 20px;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Real-time Header Control Layout */
        .table-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .table-header-bar h2 { margin: 0; }

        .search-wrapper {
            position: relative;
            width: 100%;
            max-width: 340px;
        }

        .search-wrapper .material-symbols-outlined {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 20px;
        }

        .search-input {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 10px 12px 10px 40px;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }

        /* Sticky-bound Max-height Datatable Scroll Engine */
        .table-scroll-container {
            background: #16181f;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow-x: auto;
            max-height: calc(100vh - 210px);
            overflow-y: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #1c1f26;
            color: var(--text-muted);
            font-weight: 500;
            padding: 14px 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            white-space: nowrap;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255, 255, 255, 0.01); }

        .badge {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(99, 102, 241, 0.12);
            color: #a5b4fc;
            display: inline-block;
        }

        /* Webkit Browser Custom Scrollbar Layer */
        .table-scroll-container::-webkit-scrollbar { width: 6px; height: 6px; }
        .table-scroll-container::-webkit-scrollbar-track { background: transparent; }
        .table-scroll-container::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.12); border-radius: 4px; }
        .table-scroll-container::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.25); }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo" align="center">
                    <img src="../images/logo.png" alt="Apparel Collection Logo" width="auto" height="80px" style="object-fit: contain;" loading="lazy">
                </div>
            </div>
            
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <span class="material-symbols-outlined">home</span><span>Dashboard</span>
                </a>
                <a href="assignments.php" class="nav-item">
                    <span class="material-symbols-outlined">assignment</span><span>Assignments</span>
                </a>
                <a href="agents.php" class="nav-item">
                    <span class="material-symbols-outlined">people</span><span>Agents</span>
                </a>
                <a href="store_data.php" class="nav-item active">
                    <span class="material-symbols-outlined">storefront</span><span>Store Data</span>
                </a>
                <a href="bank_approvals.php" class="nav-item">
                    <span class="material-symbols-outlined">receipt_long</span><span>Bank Approvals</span>
                </a>
            </div>
        </div>
        
        <div class="overlay"></div>
        
        <div class="main-content">
            <div class="top-header">
                <div class="header-left">
                    <div class="hamburger"><span></span><span></span><span></span></div>
                    <div class="header-title">Store Data Directory</div>
                </div>
                <div class="header-right">
                    <a href="../api_logout.php" class="logout-btn" style="margin: 0;">
                        <span class="material-symbols-outlined">logout</span> Logout
                    </a>
                </div>
            </div>
            
            <div class="content">
                <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom: 20px; padding: 12px; border-radius: 8px; background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); font-size: 14px;"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="store-grid">
                    <div class="panel-card" style="overflow: hidden;">
                        <div class="table-header-bar">
                            <h2><span class="material-symbols-outlined" style="color: var(--accent-blue)">list_alt</span> Registered System Stores (<?= count($stores) ?>)</h2>
                            <div class="search-wrapper">
                                <span class="material-symbols-outlined">search</span>
                                <input type="text" id="storeSearch" class="search-input" placeholder="Search brands, IDs, locations...">
                            </div>
                        </div>
                        
                        <div class="table-scroll-container">
                            <table id="storesTable">
                                <thead>
                                    <tr>
                                        <th style="width: 120px;">Store ID</th>
                                        <th>Store Profile Details</th>
                                        <th>Brand Log Metrics</th>
                                        <th>Mall Parameters</th>
                                        <th>Location Geographics</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stores as $store): ?>
                                    <tr class="store-row">
                                        <td><code style="color: var(--accent-blue); font-weight: 600; font-size: 13px;"><?= htmlspecialchars($store['id']) ?></code></td>
                                        <td>
                                            <div style="font-weight: 600; color: #fff;"><?= htmlspecialchars($store['name']) ?></div>
                                            <?php if (!empty($store['region_name'])): ?>
                                                <span class="badge" style="margin-top: 4px; font-size: 10px;"><?= htmlspecialchars($store['region_name']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500; color: #fff;"><?= htmlspecialchars($store['brand'] ?? 'N/A') ?></div>
                                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 1px;"><?= htmlspecialchars($store['brand_code'] ?? '') ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500; color: #fff;"><?= htmlspecialchars($store['mall'] ?? 'N/A') ?></div>
                                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 1px;"><?= htmlspecialchars($store['entity'] ?? '') ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500; color: #fff;"><?= htmlspecialchars($store['city'] ?? 'N/A') ?></div>
                                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 1px;"><?= htmlspecialchars($store['country'] ?? '') ?></div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($stores)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                            No store data available. Please contact the IT Admin for provisioning.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Navigation Drawer Toggle Actions Logic
            const hamburger = document.querySelector('.hamburger');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.overlay');
            
            if (hamburger && sidebar && overlay) {
                function toggleMenu() {
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('active');
                    document.body.classList.toggle('menu-open');
                }
                function closeMenu() {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.classList.remove('menu-open');
                }
                hamburger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleMenu();
                });
                overlay.addEventListener('click', closeMenu);
            }

            // Real-Time String Content Match Filter
            const storeSearch = document.getElementById('storeSearch');
            const rows = document.querySelectorAll('.store-row');

            if (storeSearch) {
                storeSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase().trim();
                    
                    rows.forEach(row => {
                        const contentText = row.textContent.toLowerCase();
                        row.style.display = contentText.includes(searchTerm) ? '' : 'none';
                    });
                });
            }
        });
    </script>
</body>
</html>