<?php
// TEMPORARY ERROR REPORTING (Remove or comment out for production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CORRECTED PATH: Look up one level to find the includes folder
require_once '../includes/auth.php'; 

// =========================================================================
// AUTHORIZATION CHECK (Admin or Report user only) - FIXED LOGIC
// =========================================================================

// Check using the same pattern as your dashboard
$hasAccess = false;

// Method 1: If your auth.php provides a hasRole() function
if (function_exists('hasRole')) {
    $hasAccess = (hasRole('admin') || hasRole('report'));
} 
// Method 2: Fallback to session check
else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $user_role = $_SESSION['role'] ?? '';
    $hasAccess = ($user_role === 'admin' || $user_role === 'report');
}

// If no access, show denied page and exit
if (!$hasAccess) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - Authentication Required</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
        <style>
            body { 
                background: #0f0f1a; 
                font-family: 'Inter', sans-serif; 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                min-height: 100vh; 
                margin: 0; 
                padding: 20px; 
                box-sizing: border-box; 
            }
            .error-modal { 
                background: rgba(255, 255, 255, 0.05); 
                border: 1px solid rgba(255, 255, 255, 0.1); 
                border-radius: 16px; 
                padding: 40px; 
                text-align: center; 
                max-width: 420px; 
                width: 100%; 
                box-shadow: 0 20px 40px rgba(0,0,0,0.4); 
                backdrop-filter: blur(16px); 
                -webkit-backdrop-filter: blur(16px);
            }
            .icon-container { color: #ef4444; margin-bottom: 20px; }
            .icon-container .material-symbols-outlined { font-size: 64px; }
            h2 { color: #ffffff; margin: 0 0 12px 0; font-size: 22px; font-weight: 600; letter-spacing: -0.5px; }
            p { color: rgba(255, 255, 255, 0.7); font-size: 14px; line-height: 1.6; margin: 0 0 32px 0; }
            .btn-login { 
                display: inline-flex; align-items: center; justify-content: center; gap: 8px; 
                background: #6366f1; color: #ffffff; padding: 14px 24px; border-radius: 8px; 
                font-size: 14px; font-weight: 600; text-decoration: none; transition: background 0.3s ease; 
                border: none; width: 100%; box-sizing: border-box; cursor: pointer;
                box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
            }
            .btn-login:hover { background: #4f46e5; }
        </style>
    </head>
    <body>
        <div class="error-modal">
            <div class="icon-container">
                <span class="material-symbols-outlined">gpp_bad</span>
            </div>
            <h2>Access Denied</h2>
            <p>You need Report User or Admin privileges to view this receipt.</p>
            <a href="https://apparelgroupksa.com/" class="btn-login">
                <span class="material-symbols-outlined" style="font-size: 18px;">login</span> Go to Login Page
            </a>
        </div>
    </body>
    </html>
    <?php
    exit; // Stop executing the rest of the script
}

// If we get here, user has access - continue with the receipt display
$pdo = getConnection();

// Get ID from URL safely
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'><h2>Invalid Receipt ID.</h2></div>");
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            sv.*, 
            u.name as agent_name, 
            u.username as agent_username,
            s.name as store_name, 
            s.id as store_code, 
            s.mall, 
            s.brand,
            s.entity
        FROM shop_visits sv
        JOIN users u ON sv.agent_id = u.id
        JOIN stores s ON sv.shop_id = s.id
        WHERE sv.id = ?
    ");
    
    $stmt->execute([$id]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receipt) {
        die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'><h2>Receipt Not Found (#$id)</h2><p>Check if the ID exists in the shop_visits table.</p></div>");
    }

} catch (PDOException $e) {
    die("<div style='color:red; padding:20px;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}

$currency = htmlspecialchars($receipt['currency'] ?? 'SAR');
$discrepancy = (float)($receipt['discrepancy'] ?? 0) * -1;

// --- SMART SIGNATURE RESOLVER ---
function getSignatureSrc($dbValue) {
    if (empty($dbValue)) {
        return '../images/no-sig.png';
    }
    if (strpos($dbValue, 'data:image') === 0) {
        return $dbValue;
    }
    if (strpos($dbValue, '.') === false && strlen($dbValue) > 100) {
        return 'data:image/png;base64,' . $dbValue;
    }
    if (strpos($dbValue, 'http://') === 0 || strpos($dbValue, 'https://') === 0) {
        return $dbValue;
    }
    if (strpos($dbValue, '../') !== 0) {
        return '../' . ltrim($dbValue, '/');
    }
    return $dbValue;
}

$collector_sig = getSignatureSrc($receipt['proof_image']);
$manager_sig   = getSignatureSrc($receipt['store_employee_signature']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Receipt - #<?php echo htmlspecialchars($receipt['id']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <style>
        body { background: #f3f4f6; font-family: 'Inter', sans-serif; display: flex; justify-content: center; padding: 40px 20px; color: #1f2937; margin: 0; }
        .receipt-container { background: #ffffff; width: 100%; max-width: 500px; padding: 40px; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.08); position: relative; }
        
        .receipt-container::before, .receipt-container::after { content: ''; position: absolute; left: 0; width: 100%; height: 12px; background-size: 24px 100%; }
        .receipt-container::before { top: -6px; background-image: radial-gradient(circle at 12px 0, transparent 13px, #ffffff 14px); }
        .receipt-container::after { bottom: -6px; background-image: radial-gradient(circle at 12px 12px, transparent 13px, #ffffff 14px); }

        .header { text-align: center; border-bottom: 2px dashed #e5e7eb; padding-bottom: 24px; margin-bottom: 24px; }
        .header h1 { margin: 0 0 5px 0; font-size: 22px; font-weight: 700; color: #111827; letter-spacing: -0.5px; }
        .header p { margin: 0; color: #6b7280; font-size: 14px; }
        .header .badge { display: inline-block; background: #e0e7ff; color: #4f46e5; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 12px; }

        .row { display: flex; justify-content: space-between; margin-bottom: 12px; font-family: 'JetBrains Mono', monospace; font-size: 13px; }
        .row .label { color: #6b7280; }
        .row .value { font-weight: 500; color: #111827; text-align: right; }
        
        .amount-highlight { background: #f9fafb; border-radius: 8px; padding: 16px; margin: 20px 0; }
        .amount-highlight .row { margin-bottom: 8px; font-size: 14px; }
        .amount-highlight .row:last-child { margin-bottom: 0; }
        .amount-highlight .total { font-size: 18px; font-weight: 700; color: #10b981; border-top: 1px solid #e5e7eb; padding-top: 8px; margin-top: 8px; }

        .discrepancy-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
        .discrepancy-box .row { color: #dc2626; font-weight: 600; margin-bottom: 5px; }
        .discrepancy-box .reason { font-family: 'Inter', sans-serif; font-size: 12px; color: #b91c1c; background: #fff5f5; padding: 8px; border-radius: 4px; display: inline-block; width: 100%; box-sizing: border-box; }

        .remarks-box { background: #fbfbfe; border: 1px dashed #c7d2fe; padding: 12px; border-radius: 8px; margin-bottom: 24px; font-size: 13px; color: #4b5563; }
        .remarks-box strong { color: #374151; display: block; margin-bottom: 4px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }

        .signatures { display: flex; gap: 20px; margin-top: 30px; border-top: 2px dashed #e5e7eb; padding-top: 24px; }
        .sig-box { flex: 1; text-align: center; }
        .sig-box img { width: 100%; max-width: 150px; height: 80px; object-fit: contain; border-bottom: 1px solid #111827; margin-bottom: 8px; mix-blend-mode: multiply; }
        .sig-box p { margin: 0; font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;}
        .sig-box .name { font-size: 12px; color: #111827; font-weight: 500; margin-top: 4px; text-transform: none; letter-spacing: 0; }

        .actions { display: flex; gap: 10px; margin-top: 40px; }
        .btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; border: none; transition: 0.2s; font-family: 'Inter', sans-serif;}
        .btn-print { background: #111827; color: white; }
        .btn-print:hover { background: #374151; }
        .btn-close { background: #e5e7eb; color: #374151; }
        .btn-close:hover { background: #d1d5db; }

        @media print {
            body { background: white; padding: 0; }
            .receipt-container { box-shadow: none; max-width: 100%; border: none; padding: 0; }
            .receipt-container::before, .receipt-container::after, .actions { display: none; }
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <div class="header">
        <h1>APPAREL GROUP</h1>
        <p><?php echo htmlspecialchars($receipt['store_name']); ?></p>
        <p><?php echo htmlspecialchars($receipt['mall']); ?> | Store #<?php echo htmlspecialchars($receipt['store_code']); ?></p>
        <div class="badge">Cash Collection Record</div>
    </div>

    <div class="row">
        <span class="label">Receipt ID:</span>
        <span class="value">#<?php echo str_pad($receipt['id'], 6, '0', STR_PAD_LEFT); ?></span>
    </div>
    <div class="row">
        <span class="label">Date Logged:</span>
        <span class="value"><?php echo date('M d, Y h:i A', strtotime($receipt['visit_date'])); ?></span>
    </div>
    <div class="row">
        <span class="label">Sales Date:</span>
        <span class="value"><?php echo date('M d, Y', strtotime($receipt['sale_date'])); ?></span>
    </div>
    <div class="row">
        <span class="label">Collection Date:</span>
        <span class="value"><?php echo date('M d, Y', strtotime($receipt['collection_date'])); ?></span>
    </div>

    <div class="amount-highlight">
        <div class="row">
            <span class="label">Z-Report Amount:</span>
            <span class="value"><?php echo number_format($receipt['z_report'], 2) . ' ' . $currency; ?></span>
        </div>
        
        <?php if ($receipt['refund'] > 0): ?>
        <div class="row">
            <span class="label" style="color: #6b7280;">Refund:</span>
            <span class="value"><?php echo number_format($receipt['refund'], 2) . ' ' . $currency; ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($receipt['incentive'] > 0): ?>
        <div class="row">
            <span class="label" style="color: #6b7280;">Incentive:</span>
            <span class="value"><?php echo number_format($receipt['incentive'], 2) . ' ' . $currency; ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($receipt['petty_cash'] > 0): ?>
        <div class="row">
            <span class="label" style="color: #6b7280;">Petty Cash:</span>
            <span class="value"><?php echo number_format($receipt['petty_cash'], 2) . ' ' . $currency; ?></span>
        </div>
        <?php endif; ?>

        <div class="row total">
            <span class="label" style="color:#10b981;">Physical Cash:</span>
            <span class="value"><?php echo number_format($receipt['physical_cash'], 2) . ' ' . $currency; ?></span>
        </div>
    </div>

<?php if ($discrepancy != 0): ?>
    <div class="discrepancy-box">
        <div class="row">
            <span>Discrepancy:</span>
            <span>
                <?php 
                echo ($discrepancy > 0 ? '+' : '-'); 
                echo number_format(abs($discrepancy), 2) . ' ' . $currency; 
                ?>
            </span>
        </div>
        <div class="reason">
            <strong>Reason:</strong> <?php echo htmlspecialchars($receipt['reason']); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($receipt['remarks'])): ?>
    <div class="remarks-box">
        <strong>Collector Remarks:</strong>
        <?php echo nl2br(htmlspecialchars($receipt['remarks'])); ?>
    </div>
    <?php endif; ?>

    <div class="signatures">
        <div class="sig-box">
            <img src="<?php echo htmlspecialchars($collector_sig); ?>" alt="Collector Signature" onerror="this.src='../images/no-sig.png'">
            <p>Collector Signature</p>
            <div class="name"><?php echo htmlspecialchars($receipt['agent_name']); ?></div>
        </div>
        <div class="sig-box">
            <img src="<?php echo htmlspecialchars($manager_sig); ?>" alt="Manager Signature" onerror="this.src='../images/no-sig.png'">
            <p>Manager Signature</p>
            <div class="name">Emp ID: <?php echo htmlspecialchars($receipt['handover_id'] ?: 'N/A'); ?></div>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-close" onclick="window.close()">
            <span class="material-symbols-outlined">close</span> Close
        </button>
        <button class="btn btn-print" onclick="window.print()">
            <span class="material-symbols-outlined">print</span> Print Receipt
        </button>
    </div>
</div>

</body>
</html>