<?php
/**
 * Project: Resto Pro ERP - Intelligence Module
 * Feature: Advanced Financial Analytics & Reporting (Full Update)
 */
session_start();
include 'db.php';

if (!isset($_SESSION['admin_user'])) { header("Location: login.php"); exit(); }

// --- 1. GLOBAL SETTINGS ---
$global_set = $conn->query("SELECT * FROM global_settings WHERE id=1")->fetch_assoc();
$brand_name = $global_set['hotel_name'] ?? "RESTO PRO";
$currency = $global_set['currency_symbol'] ?? "₹";

// --- 2. SELECTION LOGIC ---
$view_year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$view_month = isset($_GET['m']) ? (int)$_GET['m'] : null; // Detects if a specific month report is requested

$months_full = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
$report_title = $view_month ? $months_full[$view_month-1] . " " . $view_year : "Annual Summary " . $view_year;

// --- 3. DYNAMIC DATA FILTERING ---
$date_filter = $view_month ? "MONTH(date) = $view_month AND YEAR(date) = $view_year" : "YEAR(date) = $view_year";

// --- 4. TOP KPI STATS ---
$stats = $conn->query("
    SELECT 
        (SELECT SUM(cash_sales + online_sales) FROM transactions WHERE $date_filter) as total_rev,
        (SELECT SUM(grocery_expense + staff_expense + other_expense) FROM transactions WHERE $date_filter) as daily_exp,
        (SELECT SUM(amount) FROM expense_details WHERE $date_filter) as bill_exp,
        (SELECT SUM(amount) FROM staff_ledger WHERE $date_filter) as salary_exp
")->fetch_assoc();

$y_rev = $stats['total_rev'] ?? 0;
$y_exp = ($stats['daily_exp'] ?? 0) + ($stats['bill_exp'] ?? 0) + ($stats['salary_exp'] ?? 0);
$y_profit = $y_rev - $y_exp;

// --- 5. TREND GRAPH DATA ---
if ($view_month) {
    // Show Daily Breakdown for the selected month
    $labels = range(1, 31);
    $chart_rev = array_fill(0, 31, 0);
    $chart_exp = array_fill(0, 31, 0);
    
    $res_rev = $conn->query("SELECT DAY(date) as d, SUM(cash_sales + online_sales) as amt FROM transactions WHERE $date_filter GROUP BY d");
    while($r = $res_rev->fetch_assoc()) { $chart_rev[$r['d']-1] = (float)$r['amt']; }
    
    $res_exp = $conn->query("SELECT d, SUM(amt) as total FROM (
        SELECT DAY(date) as d, SUM(grocery_expense + staff_expense + other_expense) as amt FROM transactions WHERE $date_filter GROUP BY d
        UNION ALL
        SELECT DAY(date) as d, SUM(amount) as amt FROM expense_details WHERE $date_filter GROUP BY d
        UNION ALL
        SELECT DAY(date) as d, SUM(amount) as amt FROM staff_ledger WHERE $date_filter GROUP BY d
    ) as combined GROUP BY d");
    while($r = $res_exp->fetch_assoc()) { $chart_exp[$r['d']-1] = (float)$r['total']; }
} else {
    // Show Monthly Breakdown for the selected year
    $labels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    $chart_rev = array_fill(0, 12, 0);
    $chart_exp = array_fill(0, 12, 0);
    
    $res_rev = $conn->query("SELECT MONTH(date) as m, SUM(cash_sales + online_sales) as amt FROM transactions WHERE YEAR(date) = $view_year GROUP BY m");
    while($r = $res_rev->fetch_assoc()) { $chart_rev[$r['m']-1] = (float)$r['amt']; }

    $res_exp = $conn->query("SELECT m, SUM(amt) as total FROM (
        SELECT MONTH(date) as m, SUM(grocery_expense + staff_expense + other_expense) as amt FROM transactions WHERE YEAR(date) = $view_year GROUP BY m
        UNION ALL
        SELECT MONTH(date) as m, SUM(amount) as amt FROM expense_details WHERE YEAR(date) = $view_year GROUP BY m
        UNION ALL
        SELECT MONTH(date) as m, SUM(amount) as amt FROM staff_ledger WHERE YEAR(date) = $view_year GROUP BY m
    ) as combined GROUP BY m");
    while($r = $res_exp->fetch_assoc()) { $chart_exp[$r['m']-1] = (float)$r['total']; }
}

// --- 6. CATEGORY DATA (Including Staff) ---
$cat_labels = []; $cat_values = [];
$cat_res = $conn->query("
    SELECT category_name, SUM(amt) as total FROM (
        SELECT c.category_name, SUM(e.amount) as amt FROM expense_details e 
        JOIN expense_categories c ON e.category_id = c.id WHERE $date_filter GROUP BY c.category_name
        UNION ALL
        SELECT 'STAFF PAYOUT' as category_name, SUM(amount) as amt FROM staff_ledger WHERE $date_filter
    ) as combined GROUP BY category_name HAVING total > 0
");
while($r = $cat_res->fetch_assoc()) { $cat_labels[] = strtoupper($r['category_name']); $cat_values[] = (float)$r['total']; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $report_title; ?> | <?php echo $brand_name; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print { .no-print { display: none; } body { background: white; } .chart-card { border: 1px solid #eee; } }
        .chart-container { position: relative; height: 320px; width: 100%; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 pb-20">

    <nav class="bg-slate-900 text-white p-6 shadow-2xl sticky top-0 z-50 no-print">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-indigo-500 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/20">
                    <i class="fas fa-chart-line text-white"></i>
                </div>
                <h1 class="text-xl font-black uppercase tracking-tighter italic">Resto <span class="text-indigo-400">Intelligence</span></h1>
            </div>
            <div class="flex items-center gap-4">
                <?php if($view_month): ?>
                    <a href="analytics.php?y=<?php echo $view_year; ?>" class="bg-slate-700 px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-600 transition-all">
                        <i class="fas fa-arrow-left mr-2"></i> Annual View
                    </a>
                <?php endif; ?>
                <button onclick="window.print()" class="bg-emerald-600 px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-500 transition-all">
                    <i class="fas fa-file-pdf mr-2"></i> Export Report
                </button>
                <a href="index.php" class="bg-slate-800 border border-slate-700 px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-700 transition-all flex items-center">
                        <i class="fas fa-home mr-2 text-sm text-indigo-400"></i> Back to Dashboard
                    </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 mt-10">
        
        <div class="flex justify-between items-end mb-10 no-print">
            <div>
                <h2 class="text-4xl font-black text-slate-800 tracking-tighter uppercase"><?php echo $report_title; ?></h2>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]"><?php echo $view_month ? 'Deep Dive Analysis' : 'Fiscal Overview'; ?></p>
            </div>
            <?php if(!$view_month): ?>
            <form method="GET" class="flex gap-2">
                <select name="y" onchange="this.form.submit()" class="p-4 border rounded-2xl bg-white font-black text-sm outline-none shadow-sm focus:ring-2 focus:ring-indigo-500">
                    <?php for($i=2024; $i<=2030; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($view_year == $i) ? 'selected' : ''; ?>>Year <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            <div class="bg-white p-8 rounded-[2.5rem] shadow-xl border-t-8 border-emerald-500">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-2">Total Revenue</p>
                <h2 class="text-4xl font-black text-slate-900"><?php echo $currency.number_format($y_rev); ?></h2>
                <p class="text-[9px] text-emerald-600 font-bold mt-2 uppercase tracking-tighter"><i class="fas fa-arrow-trend-up mr-1"></i> Gross Inflow</p>
            </div>

            <div class="bg-white p-8 rounded-[2.5rem] shadow-xl border-t-8 border-rose-500">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-2">Total Expenses</p>
                <h2 class="text-4xl font-black text-slate-900"><?php echo $currency.number_format($y_exp); ?></h2>
                <p class="text-[9px] text-rose-600 font-bold mt-2 uppercase tracking-tighter"><i class="fas fa-arrow-trend-down mr-1"></i> Total Outflow</p>
            </div>

            <div class="bg-slate-900 p-8 rounded-[2.5rem] shadow-2xl text-white relative overflow-hidden">
                <div class="absolute -right-4 -top-4 opacity-10 text-8xl"><i class="fas fa-coins"></i></div>
                <p class="text-indigo-300 text-[10px] font-black uppercase tracking-widest mb-2">Net Profit</p>
                <h2 class="text-4xl font-black"><?php echo $currency.number_format($y_profit); ?></h2>
                <p class="text-[9px] text-indigo-400 font-bold mt-2 uppercase tracking-widest">Margin: <?php echo ($y_rev > 0) ? round(($y_profit/$y_rev)*100, 1) : 0; ?>%</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
            <div class="bg-white p-8 rounded-[3rem] shadow-xl border border-slate-100 chart-card">
                <h3 class="text-sm font-black text-slate-800 uppercase mb-8 tracking-widest"><?php echo $view_month ? 'Daily' : 'Monthly'; ?> Performance</h3>
                <div class="chart-container"><canvas id="mainTrendChart"></canvas></div>
            </div>

            <div class="bg-white p-8 rounded-[3rem] shadow-xl border border-slate-100 chart-card">
                <h3 class="text-sm font-black text-slate-800 uppercase mb-8 tracking-widest">Expense Distribution</h3>
                <div class="chart-container"><canvas id="expenseDoughnut"></canvas></div>
            </div>
        </div>

        <?php if(!$view_month): ?>
        <div class="bg-white rounded-[2.5rem] shadow-xl overflow-hidden border border-slate-100">
            <div class="bg-slate-800 p-6 text-white flex justify-between items-center">
                <h3 class="text-[10px] font-black uppercase tracking-[0.3em]">Monthly Financial Audit Log</h3>
                <span class="text-[10px] bg-white/10 px-4 py-1 rounded-full"><?php echo $view_year; ?></span>
            </div>
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b text-[10px] uppercase text-slate-400 font-black">
                    <tr>
                        <th class="p-6">Month</th>
                        <th class="p-6">Revenue</th>
                        <th class="p-6">Expense</th>
                        <th class="p-6">Net Profit</th>
                        <th class="p-6 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y text-sm font-bold">
                    <?php for($i=0; $i<12; $i++): 
                        $m_rev = $chart_rev[$i];
                        $m_exp = $chart_exp[$i];
                        $m_pro = $m_rev - $m_exp;
                        if($m_rev == 0 && $m_exp == 0) continue; 
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="p-6 text-slate-800"><?php echo $labels[$i]; ?> <?php echo $view_year; ?></td>
                        <td class="p-6 text-emerald-600"><?php echo $currency.number_format($m_rev); ?></td>
                        <td class="p-6 text-rose-500"><?php echo $currency.number_format($m_exp); ?></td>
                        <td class="p-6 <?php echo ($m_pro >= 0) ? 'text-indigo-600' : 'text-rose-700'; ?>">
                            <?php echo $currency.number_format($m_pro); ?>
                        </td>
                        <td class="p-6 text-center">
                            <a href="analytics.php?y=<?php echo $view_year; ?>&m=<?php echo ($i+1); ?>" 
                               class="bg-slate-900 text-white px-5 py-2 rounded-xl text-[10px] uppercase font-black tracking-widest hover:bg-indigo-600 transition-all shadow-lg hover:shadow-indigo-500/30">
                                <i class="fas fa-eye mr-2"></i> View Report
                            </a>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // 1. Performance Trend (Bar or Line)
        const ctxMain = document.getElementById('mainTrendChart').getContext('2d');
        new Chart(ctxMain, {
            type: '<?php echo $view_month ? "line" : "bar"; ?>',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [
                    {
                        label: 'Revenue',
                        data: <?php echo json_encode($chart_rev); ?>,
                        backgroundColor: '#10b981',
                        borderColor: '#10b981',
                        borderWidth: 2,
                        borderRadius: 5,
                        tension: 0.4,
                        fill: <?php echo $view_month ? 'true' : 'false'; ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.74)'
                    },
                    {
                        label: 'Expense',
                        data: <?php echo json_encode($chart_exp); ?>,
                        backgroundColor: '#f43f5e',
                        borderColor: '#f43f5e',
                        borderWidth: 2,
                        borderRadius: 5,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Expense Categories (Includes Staff Payout)
        const ctxExp = document.getElementById('expenseDoughnut').getContext('2d');
        new Chart(ctxExp, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($cat_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($cat_values); ?>,
                    backgroundColor: ['#6366f1', '#f59e0b', '#ec4899', '#14b8a6', '#8b5cf6', '#f43f5e', '#334155'],
                    borderWidth: 0,
                    hoverOffset: 20
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'bottom', labels: { padding: 20, font: { weight: 'bold', size: 10 } } } 
                },
                cutout: '70%'
            }
        });
    </script>
</body>
</html>