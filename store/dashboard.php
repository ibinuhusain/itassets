<?php
// Temporarily display errors to debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

// --- 1. HANDLE LOGOUT ---
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: ../api_logout.php");
    exit();
}

// --- 2. HANDLE LOGIN ---
$loginError = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if ($password === "apprl@" . $username) {
        $_SESSION['store_id'] = $username;
        header("Location: dashboard.php");
        exit();
    } else {
        $loginError = "Invalid Username or Password.";
    }
}

// --- 3. IF NOT LOGGED IN, SHOW LOGIN PAGE ---
if (!isset($_SESSION['store_id'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store Manager Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 h-screen flex items-center justify-center">
    <div class="bg-white p-10 rounded-2xl shadow-xl border border-slate-100 w-96">
        <div class="w-12 h-12 bg-indigo-600 text-white rounded-xl flex items-center justify-center text-2xl font-bold mb-6 mx-auto shadow-lg shadow-indigo-200">✦</div>
        <h2 class="text-2xl font-bold mb-8 text-center text-slate-800">Store Portal</h2>
        <?php if ($loginError): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-3 rounded mb-6 text-sm font-medium"><?php echo $loginError; ?></div>
        <?php endif; ?>
        <form method="POST" action="dashboard.php">
            <div class="mb-5">
                <label class="block text-slate-600 text-xs font-bold mb-2 uppercase tracking-wide">Store ID</label>
                <input type="text" name="username" required class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all bg-slate-50 focus:bg-white">
            </div>
            <div class="mb-8">
                <label class="block text-slate-600 text-xs font-bold mb-2 uppercase tracking-wide">Password</label>
                <input type="password" name="password" required class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all bg-slate-50 focus:bg-white">
            </div>
            <button type="submit" name="login" class="w-full bg-slate-900 text-white font-bold py-3 px-4 rounded-xl hover:bg-indigo-600 transition-colors shadow-md">Secure Login</button>
        </form>
    </div>
</body>
</html>
<?php
    exit(); 
}

// --- 4. IF LOGGED IN, FETCH STORE-SPECIFIC DATA ---
$store_id = $conn->real_escape_string($_SESSION['store_id']);

// Safely Fetch Total Cash Metrics
$metricsQuery = "SELECT SUM(total_amount) as total_sales, SUM(physical_cash) as total_cash 
                 FROM shop_visits WHERE shop_id = '$store_id'";
$metricsResult = $conn->query($metricsQuery);
$metrics = $metricsResult ? $metricsResult->fetch_assoc() : null;

$totalSalesNum = ($metrics && $metrics['total_sales']) ? $metrics['total_sales'] : 0;
$totalCashNum = ($metrics && $metrics['total_cash']) ? $metrics['total_cash'] : 0;
$totalSales = number_format($totalSalesNum, 2);
$totalCash = number_format($totalCashNum, 2);
$discrepancy = number_format($totalSalesNum - $totalCashNum, 2); // Calculate difference

// Safely Fetch Latest Collection Details
$latestQuery = "SELECT collection_date, agent_id 
                FROM shop_visits 
                WHERE shop_id = '$store_id' AND collection_date IS NOT NULL 
                ORDER BY collection_date DESC LIMIT 1";
$latestResult = $conn->query($latestQuery);
$latest = $latestResult ? $latestResult->fetch_assoc() : null;
$lastCollectionDate = $latest ? date('M j, Y', strtotime($latest['collection_date'])) : "N/A";
$lastAgent = $latest ? $latest['agent_id'] : "N/A";

// Safely Fetch Chart Data (Pulling both Cash and Sales for comparison)
$chartQuery = "SELECT DATE(collection_date) as c_date, SUM(physical_cash) as daily_cash, SUM(total_amount) as daily_sales
               FROM shop_visits 
               WHERE shop_id = '$store_id' AND collection_date IS NOT NULL 
               GROUP BY DATE(collection_date) ORDER BY c_date ASC LIMIT 14";
$chartResult = $conn->query($chartQuery);
$chartLabels = [];
$chartCashData = [];
$chartSalesData = [];
if ($chartResult && $chartResult->num_rows > 0) {
    while($row = $chartResult->fetch_assoc()) {
        $chartLabels[] = date('M j', strtotime($row['c_date']));
        $chartCashData[] = $row['daily_cash'];
        $chartSalesData[] = $row['daily_sales'];
    }
}

// Safely Fetch IT Assets & Count
$itAssetsQuery = "SELECT sku, item_name, dispatch_qty, dispatched_at 
                  FROM ict_dispatch_logs WHERE store_code = '$store_id' ORDER BY dispatched_at DESC LIMIT 6";
$itAssetsResult = $conn->query($itAssetsQuery);
$totalItCount = $conn->query("SELECT SUM(dispatch_qty) as count FROM ict_dispatch_logs WHERE store_code = '$store_id'")->fetch_assoc()['count'] ?? 0;

// Safely Fetch Normal Inventory & Count
$normalInvQuery = "SELECT sku, dispatch_qty, dispatched_at 
                   FROM dispatch_logs WHERE store_code = '$store_id' ORDER BY dispatched_at DESC LIMIT 6";
$normalInvResult = $conn->query($normalInvQuery);
$totalInvCount = $conn->query("SELECT SUM(dispatch_qty) as count FROM dispatch_logs WHERE store_code = '$store_id'")->fetch_assoc()['count'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store <?php echo htmlspecialchars($store_id); ?> - Command Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        /* Custom Scrollbar for tables */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-100 flex h-screen overflow-hidden text-slate-800">

    <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col justify-between shadow-2xl z-10 relative">
        <div class="p-6">
            <h2 class="text-xl font-bold mb-10 flex items-center gap-3 text-white">
                <span class="bg-indigo-500 text-white rounded-lg w-10 h-10 flex items-center justify-center text-lg shadow-lg shadow-indigo-500/30">✦</span>
                Store <?php echo htmlspecialchars($store_id); ?>
            </h2>
            <nav class="space-y-3">
                <a href="#dashboard" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/10 text-white font-medium hover:bg-white/20 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    Dashboard
                </a>
                <a href="#request-inventory" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition-all text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    Request Assets
                </a>
            </nav>
        </div>
        <div class="p-6 border-t border-slate-800">
            <a href="?action=logout" class="flex items-center gap-3 px-4 py-3 text-red-400 hover:bg-red-500/10 rounded-xl font-medium transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                Secure Logout
            </a>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto relative">
        <div class="absolute top-0 left-0 w-full h-64 bg-indigo-600 rounded-b-[3rem] opacity-10 pointer-events-none"></div>

        <div class="p-8 max-w-7xl mx-auto space-y-8 relative z-10">
            
            <header class="flex justify-between items-end mb-4">
                <div>
                    <p class="text-indigo-600 font-bold tracking-wide uppercase text-sm mb-1">Command Center</p>
                    <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight">Performance Overview</h1>
                </div>
            </header>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="md:col-span-2 bg-gradient-to-br from-indigo-600 to-indigo-800 p-6 rounded-2xl shadow-lg shadow-indigo-200 text-white relative overflow-hidden group">
                    <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-2xl group-hover:scale-110 transition-transform duration-700"></div>
                    <h3 class="text-indigo-100 font-semibold text-sm uppercase tracking-wider mb-2 opacity-90">Total Cash Collected</h3>
                    <div class="flex items-baseline gap-2 mb-6">
                        <span class="text-5xl font-black tracking-tighter">   <?php echo $totalCash; ?> SAR</span>
                    </div>
                    <div class="flex justify-between items-center bg-black/20 rounded-xl p-4 backdrop-blur-sm border border-white/10">
                        <div>
                            <p class="text-xs text-indigo-200 mb-1">Total Sales Recorded</p>
                            <p class="text-sm font-bold">$<?php echo $totalSales; ?></p>
                        </div>
                        <div class="w-px h-8 bg-white/20"></div>
                        <div>
                            <p class="text-xs text-indigo-200 mb-1">Last Collection</p>
                            <p class="text-sm font-bold flex items-center gap-2"><?php echo $lastCollectionDate; ?> <span class="bg-white/20 text-[10px] px-2 py-0.5 rounded-full">Agt #<?php echo $lastAgent; ?></span></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col justify-center hover:border-indigo-200 transition-colors">
                    <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center mb-4"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg></div>
                    <h3 class="text-slate-500 font-medium text-sm mb-1">Assigned IT Assets</h3>
                    <p class="text-3xl font-bold text-slate-800"><?php echo $totalItCount; ?></p>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col justify-center hover:border-indigo-200 transition-colors">
                    <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center mb-4"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg></div>
                    <h3 class="text-slate-500 font-medium text-sm mb-1">Standard Inventory</h3>
                    <p class="text-3xl font-bold text-slate-800"><?php echo $totalInvCount; ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-2 glass-card p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col min-h-[320px]">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-bold text-slate-800">Cash Collection Trend</h3>
                        <span class="text-xs font-semibold bg-indigo-50 text-indigo-600 px-3 py-1 rounded-full">Last 14 Days</span>
                    </div>
                    <div class="flex-1 relative w-full">
                        <canvas id="cashTrendChart"></canvas>
                    </div>
                </div>

                <div class="glass-card p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col justify-between">
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Asset Distribution</h3>
                    <div class="relative w-full h-48 mx-auto flex items-center justify-center">
                        <canvas id="assetDoughnutChart"></canvas>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2 text-center text-sm">
                        <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                            <div class="w-2 h-2 bg-indigo-500 rounded-full mx-auto mb-1"></div>
                            <span class="text-slate-500 text-xs">IT Assets</span>
                            <p class="font-bold text-slate-800"><?php echo $totalItCount; ?></p>
                        </div>
                        <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                            <div class="w-2 h-2 bg-slate-300 rounded-full mx-auto mb-1"></div>
                            <span class="text-slate-500 text-xs">Standard</span>
                            <p class="font-bold text-slate-800"><?php echo $totalInvCount; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                
                <div class="xl:col-span-1 glass-card p-6 rounded-2xl shadow-sm border border-slate-100">
                    <h3 class="text-lg font-bold text-slate-800 mb-1">Sales vs. Cash Collected</h3>
                    <p class="text-xs text-slate-500 mb-6">Identifying collection discrepancies</p>
                    <div class="relative w-full h-56">
                        <canvas id="discrepancyChart"></canvas>
                    </div>
                </div>

                <div class="xl:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="glass-card p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col">
                        <h3 class="text-md font-bold text-slate-800 mb-4 border-b border-slate-100 pb-3">Recent IT Deployments</h3>
                        <div class="overflow-y-auto flex-1 pr-2">
                            <table class="w-full text-left border-collapse">
                                <tbody class="text-sm">
                                    <?php 
                                    if ($itAssetsResult && $itAssetsResult->num_rows > 0) {
                                        while($row = $itAssetsResult->fetch_assoc()) {
                                            echo "<tr class='border-b border-slate-50 hover:bg-slate-50/80 transition-colors group'>";
                                            echo "<td class='py-3 font-medium text-slate-700'>" . htmlspecialchars($row['sku']) . "</td>";
                                            echo "<td class='py-3 text-slate-500 truncate max-w-[120px]'>" . htmlspecialchars($row['item_name']) . "</td>";
                                            echo "<td class='py-3 text-right'><span class='bg-slate-100 text-slate-600 px-2 py-1 rounded text-xs font-bold'>" . htmlspecialchars($row['dispatch_qty']) . "x</span></td>";
                                            echo "</tr>";
                                        }
                                    } else { echo "<tr><td colspan='3' class='py-4 text-slate-400 text-center text-sm'>No IT assets assigned.</td></tr>"; }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="glass-card p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col">
                        <h3 class="text-md font-bold text-slate-800 mb-4 border-b border-slate-100 pb-3">Recent Inventory</h3>
                        <div class="overflow-y-auto flex-1 pr-2">
                            <table class="w-full text-left border-collapse">
                                <tbody class="text-sm">
                                    <?php 
                                    if ($normalInvResult && $normalInvResult->num_rows > 0) {
                                        while($row = $normalInvResult->fetch_assoc()) {
                                            echo "<tr class='border-b border-slate-50 hover:bg-slate-50/80 transition-colors group'>";
                                            echo "<td class='py-3 font-medium text-slate-700'>" . htmlspecialchars($row['sku']) . "</td>";
                                            echo "<td class='py-3 text-slate-500'>" . date('M j', strtotime($row['dispatched_at'])) . "</td>";
                                            echo "<td class='py-3 text-right'><span class='bg-slate-100 text-slate-600 px-2 py-1 rounded text-xs font-bold'>" . htmlspecialchars($row['dispatch_qty']) . "x</span></td>";
                                            echo "</tr>";
                                        }
                                    } else { echo "<tr><td colspan='3' class='py-4 text-slate-400 text-center text-sm'>No inventory assigned.</td></tr>"; }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <section id="request-inventory" class="mt-8">
                <div class="bg-slate-900 rounded-3xl p-1 shadow-xl">
                    <div class="bg-white rounded-[1.4rem] p-8 lg:p-10">
                        <div class="max-w-3xl">
                            <h2 class="text-2xl font-bold text-slate-900 mb-2">Request New Assets</h2>
                            <p class="text-slate-500 mb-8 text-sm">Submit a formal request to regional procurement for IT hardware or standard store inventory.</p>
                            
                            <form method="POST" action="process_request.php">
                                <input type="hidden" name="store_id" value="<?php echo htmlspecialchars($store_id); ?>">
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                    <div>
                                        <label class="block text-slate-700 text-xs font-bold mb-2 uppercase tracking-wide">Category</label>
                                        <select name="category" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-colors">
                                            <option value="it_asset">IT Hardware</option>
                                            <option value="procurement">Standard Inventory</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-slate-700 text-xs font-bold mb-2 uppercase tracking-wide">Item Name / SKU</label>
                                        <input type="text" name="item_name" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-colors">
                                    </div>
                                    <div>
                                        <label class="block text-slate-700 text-xs font-bold mb-2 uppercase tracking-wide">Quantity</label>
                                        <input type="number" name="qty" min="1" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-colors">
                                    </div>
                                </div>

                                <div class="mb-6">
                                    <label class="block text-slate-700 text-xs font-bold mb-2 uppercase tracking-wide">Business Justification</label>
                                    <textarea name="reason" rows="2" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-colors resize-none" placeholder="Explain why this store needs these items..."></textarea>
                                </div>

                                <button type="submit" class="bg-indigo-600 text-white font-bold py-3 px-8 rounded-xl hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-500/30 transition-all active:scale-95">Submit Official Request</button>
                            </form>
                            <?php if (isset($_GET['request']) && $_GET['request'] == 'success'): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-3">
        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span class="text-sm font-medium">Your request has been successfully submitted and is pending approval.</span>
    </div>
<?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
            
            <footer class="pb-8 pt-4 text-center text-slate-400 text-xs">
                Secure Store Portal v2.0 • Data encrypted in transit
            </footer>
        </div>
    </main>

    <script>
        // Shared Data from PHP
        const chartLabels = <?php echo json_encode($chartLabels); ?>;
        const cashData = <?php echo json_encode($chartCashData); ?>;
        const salesData = <?php echo json_encode($chartSalesData); ?>;
        
        // Common Tooltip Style
        const tooltipConfig = {
            backgroundColor: 'rgba(15, 23, 42, 0.9)',
            titleFont: { size: 13, family: 'Inter' },
            bodyFont: { size: 14, weight: 'bold', family: 'Inter' },
            padding: 12,
            cornerRadius: 8,
            callbacks: { label: (ctx) => ' $' + ctx.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2}) }
        };

        // 1. MAIN LINE CHART (Cash Trend)
        const ctxTrend = document.getElementById('cashTrendChart').getContext('2d');
        
        // Create Gradient for Line Chart
        let gradientTrend = ctxTrend.createLinearGradient(0, 0, 0, 400);
        gradientTrend.addColorStop(0, 'rgba(79, 70, 229, 0.2)');
        gradientTrend.addColorStop(1, 'rgba(79, 70, 229, 0)');

        new Chart(ctxTrend, {
            type: 'line',
            data: { 
                labels: chartLabels, 
                datasets: [{ 
                    label: 'Physical Cash', 
                    data: cashData, 
                    borderColor: '#4f46e5', // Indigo 600
                    backgroundColor: gradientTrend, 
                    borderWidth: 3, 
                    tension: 0.4, 
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#4f46e5',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }] 
            },
            options: { 
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: tooltipConfig },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [4, 4], color: '#f1f5f9' }, border: {display: false}, ticks: { font: {family: 'Inter'}, color: '#64748b', callback: (val) => '$' + val.toLocaleString() } },
                    x: { grid: { display: false }, border: {display: false}, ticks: { font: {family: 'Inter'}, color: '#64748b' } }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });

        // 2. DISCREPANCY BAR CHART (Sales vs Cash)
        const ctxBar = document.getElementById('discrepancyChart').getContext('2d');
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: chartLabels.slice(-7), // Only show last 7 days for cleanliness
                datasets: [
                    { label: 'Sales', data: salesData.slice(-7), backgroundColor: '#e2e8f0', borderRadius: 4 },
                    { label: 'Cash', data: cashData.slice(-7), backgroundColor: '#4f46e5', borderRadius: 4 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'top', align: 'end', labels: { boxWidth: 10, usePointStyle: true, font: {family:'Inter'} } }, 
                    tooltip: tooltipConfig 
                },
                scales: {
                    y: { display: false }, // Hide Y axis for minimal look
                    x: { grid: { display: false }, border: {display: false}, ticks: { font: {family: 'Inter'}, color: '#94a3b8', font: {size: 10} } }
                }
            }
        });

        // 3. DOUGHNUT CHART (Asset Breakdown)
        const ctxDoughnut = document.getElementById('assetDoughnutChart').getContext('2d');
        new Chart(ctxDoughnut, {
            type: 'doughnut',
            data: {
                labels: ['IT Assets', 'Standard Inv'],
                datasets: [{
                    data: [<?php echo $totalItCount; ?>, <?php echo $totalInvCount; ?>],
                    backgroundColor: ['#4f46e5', '#cbd5e1'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '75%',
                plugins: { legend: { display: false }, tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)', padding: 10, cornerRadius: 8,
                    bodyFont: { size: 13, font: {family: 'Inter'} }
                } }
            }
        });
    </script>
</body>
</html>