<?php
// Enable error reporting for debugging if needed
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();
$selected_agent = $_GET['agent_id'] ?? null;

// 1. Fetch Agents for the Dropdown (Using our bulletproof LIKE query)
$agents_stmt = $pdo->query("SELECT id, name, username FROM users WHERE LOWER(role) LIKE '%agent%' ORDER BY name ASC");
$agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);

$ledger_entries = [];
$total_collected = 0;
$total_deposited = 0;
$current_balance = 0;

if ($selected_agent) {
    // 2. Calculate Totals for Summary Cards
    // Total Collected
    $col_stmt = $pdo->prepare("SELECT SUM(physical_cash) FROM shop_visits WHERE agent_id = ?");
    $col_stmt->execute([$selected_agent]);
    $total_collected = (float)$col_stmt->fetchColumn();

    // Total Deposited (Ignoring rejected ones)
    $dep_stmt = $pdo->prepare("SELECT SUM(deposited_cash) FROM bank_submissions WHERE agent_id = ? AND status != 'rejected'");
    $dep_stmt->execute([$selected_agent]);
    $total_deposited = (float)$dep_stmt->fetchColumn();
    
    $current_balance = $total_collected - $total_deposited;
    if($current_balance < 0) $current_balance = 0;

    // 3. Fetch Unified Ledger (Combining Collections and Deposits into one timeline)
    $ledger_query = "
        SELECT 
            'Collection' as action_type,
            sv.visit_date as transaction_date,
            s.name as description,
            sv.physical_cash as amount_in,
            0 as amount_out
        FROM shop_visits sv
        LEFT JOIN stores s ON sv.shop_id = s.id
        WHERE sv.agent_id = :agent1
        
        UNION ALL
        
        SELECT 
            'Deposit' as action_type,
            bs.created_at as transaction_date,
            CONCAT('Bank: ', bs.bank_name, ' (', bs.status, ')') as description,
            0 as amount_in,
            bs.deposited_cash as amount_out
        FROM bank_submissions bs
        WHERE bs.agent_id = :agent2 AND bs.status != 'rejected'
        
        ORDER BY transaction_date DESC
    ";
    
    $ledger_stmt = $pdo->prepare($ledger_query);
    $ledger_stmt->execute(['agent1' => $selected_agent, 'agent2' => $selected_agent]);
    $ledger_entries = $ledger_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Ledger - Apparels Collection</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
    <style>
        /* Exact UI match to your existing assignments.php structure */
        :root {
            --primary-bg: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            --sidebar-bg: rgba(23, 25, 49, 0.95);
            --background-color: #0f0f1a;
            --card-bg: rgba(255, 255, 255, 0.08);
            --card-border: rgba(255, 255, 255, 0.1);
            --input-bg: rgba(255, 255, 255, 0.05);
            --input-border: rgba(255, 255, 255, 0.15);
            --table-header-bg: rgba(255, 255, 255, 0.1);
            --table-row-hover: rgba(255, 255, 255, 0.05);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --accent-color: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --elevation-1: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--primary-bg); background-attachment: fixed; color: var(--text-primary); line-height: 1.6; min-height: 100vh; }
        .container { display: flex; min-height: 100vh; position: relative; width: 100%; }

        /* Sidebar Replicated */
        .sidebar { width: 280px; height: 100vh; position: fixed; left: 0; top: 0; background: var(--sidebar-bg); backdrop-filter: blur(16px); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); z-index: 100; overflow-y: auto; }
        .sidebar-header { padding: 24px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-logo img { height: 40px; min-width: 100% !important; object-fit: contain !important; display: block !important; }
        .sidebar-nav { padding: 15px; }
        .nav-item { display: flex; align-items: center; padding: 12px 15px; margin-bottom: 5px; border-radius: 8px; color: var(--text-secondary); text-decoration: none; transition: var(--transition); }
        .nav-item:hover, .nav-item.active { background: rgba(255, 255, 255, 0.05); color: var(--text-primary); }
        .nav-item.active { background: rgba(99, 102, 241, 0.1); color: var(--accent-color); }
        .nav-item .material-symbols-outlined { margin-right: 12px; font-size: 24px; }

        .main-content { flex: 1; margin-left: 280px; min-height: 100vh; display: flex; flex-direction: column; width: calc(100% - 280px); }
        .top-header { display: flex; justify-content: space-between; align-items: center; padding: 0 24px; height: 70px; background: var(--card-bg); box-shadow: var(--elevation-1); position: sticky; top: 0; z-index: 99; border-bottom: 1px solid var(--card-border); }
        .header-title { font-size: 20px; font-weight: 600; color: var(--text-primary); }
        
        .content { flex: 1; padding: 24px; background: var(--background-color); overflow-y: auto; min-height: calc(100vh - 70px); }
        .page-title { font-size: 24px; font-weight: 600; margin-bottom: 24px; position: relative; padding-bottom: 12px; }
        .page-title::after { content: ''; position: absolute; bottom: 0; left: 0; width: 60px; height: 3px; background: var(--accent-color); border-radius: 2px; }

        .card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: var(--elevation-1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: var(--text-secondary); }
        select { width: 100%; padding: 10px 15px; border: 1px solid var(--input-border); border-radius: 8px; font-size: 14px; color: var(--text-primary); background: rgb(33, 33, 43); transition: var(--transition); }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; border-radius: 8px; font-weight: 500; font-size: 14px; cursor: pointer; transition: var(--transition); border: none; text-decoration: none; margin-top: 10px; background: var(--accent-color); color: white; }
        .btn:hover { background: #4f46e5; }

        /* Summary Cards */
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .summary-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; padding: 20px; text-align: center; }
        .summary-card h4 { color: var(--text-secondary); font-size: 14px; font-weight: 500; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;}
        .summary-card h2 { font-size: 32px; font-weight: 700; margin: 0; }
        .text-success { color: var(--success); }
        .text-warning { color: var(--warning); }
        .text-accent { color: #3b82f6; }

        /* Table */
        .table-scroll-container { max-height: 600px; overflow-y: auto; border: 1px solid var(--card-border); border-radius: 8px; background: var(--card-bg); }
        .modern-table { width: 100%; border-collapse: collapse; }
        .modern-table thead { position: sticky; top: 0; z-index: 10; }
        .modern-table th { background: var(--table-header-bg); padding: 12px 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--card-border); }
        .modern-table td { padding: 12px 15px; border-bottom: 1px solid var(--card-border); font-size: 14px; }
        .modern-table tbody tr:hover { background: var(--table-row-hover); }
        
        .badge { display: inline-block; padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 4px; }
        .badge-col { background: rgba(16, 185, 129, 0.2); color: #10B981; }
        .badge-dep { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo"><img src="../images/logo.png" alt="Logo" height="40"></div>
            </div>
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item"><span class="material-symbols-outlined">home</span><span>Dashboard</span></a>
                <a href="assignments.php" class="nav-item"><span class="material-symbols-outlined">assignment</span><span>Assignments</span></a>
                <a href="agents.php" class="nav-item"><span class="material-symbols-outlined">people</span><span>Agents</span></a>
                <a href="agent_ledger.php" class="nav-item active"><span class="material-symbols-outlined">account_balance_wallet</span><span>Agent Ledger</span></a>
                <a href="management.php" class="nav-item"><span class="material-symbols-outlined">settings</span><span>Management</span></a>
            </div>
        </div>
        
        <div class="main-content">
            <div class="top-header">
                <div class="header-title">Accounting Ledger</div>
            </div>
            
            <div class="content">
                <h2 class="page-title">Agent Ledger & Audit</h2>
                
                <div class="card">
                    <form method="GET" action="">
                        <div class="form-group">
                            <label>Select Agent Profile:</label>
                            <select name="agent_id" required onchange="this.form.submit()">
                                <option value="">-- Choose an Agent --</option>
                                <?php foreach ($agents as $agent): ?>
                                    <option value="<?php echo $agent['id']; ?>" <?php echo ($selected_agent == $agent['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($agent['name']); ?> (@<?php echo htmlspecialchars($agent['username']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>

                <?php if ($selected_agent): ?>
                    <div class="summary-grid">
                        <div class="summary-card">
                            <h4>All-Time Cash Collected</h4>
                            <h2 class="text-success"><?php echo number_format($total_collected, 2); ?> SAR</h2>
                        </div>
                        <div class="summary-card">
                            <h4>All-Time Bank Deposits</h4>
                            <h2 class="text-accent"><?php echo number_format($total_deposited, 2); ?> SAR</h2>
                        </div>
                        <div class="summary-card">
                            <h4>Current Cash in Hand</h4>
                            <h2 class="text-warning"><?php echo number_format($current_balance, 2); ?> SAR</h2>
                        </div>
                    </div>

                    <div class="card">
                        <h3 style="margin-bottom: 15px;">Transaction Timeline</h3>
                        <div class="table-scroll-container">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Type</th>
                                        <th>Location / Reference</th>
                                        <th>Amount In (Collected)</th>
                                        <th>Amount Out (Deposited)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($ledger_entries)): ?>
                                        <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-secondary);">No transaction history found for this agent.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($ledger_entries as $entry): ?>
                                            <tr>
                                                <td style="color:var(--text-secondary);"><?php echo date('Y-m-d h:i A', strtotime($entry['transaction_date'])); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $entry['action_type'] == 'Collection' ? 'badge-col' : 'badge-dep'; ?>">
                                                        <?php echo $entry['action_type']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($entry['description']); ?></td>
                                                
                                                <td style="color:var(--success); font-weight:600;">
                                                    <?php echo $entry['amount_in'] > 0 ? '+ ' . number_format($entry['amount_in'], 2) : '-'; ?>
                                                </td>
                                                
                                                <td style="color:var(--accent-color); font-weight:600;">
                                                    <?php echo $entry['amount_out'] > 0 ? '- ' . number_format($entry['amount_out'], 2) : '-'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</body>
</html>