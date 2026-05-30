<?php
session_start();
if (!isset($_SESSION['admin_user'])) { header("Location: login.php"); exit(); }
include 'db.php';

// --- 1. FETCH GLOBAL SETTINGS & BRANDING ---
$global_set = $conn->query("SELECT * FROM global_settings WHERE id=1")->fetch_assoc();
$brand_name = !empty($global_set['hotel_name']) ? $global_set['hotel_name'] : "RESTO PRO";
$currency = !empty($global_set['currency_symbol']) ? $global_set['currency_symbol'] : "₹";

// --- 2. GLOBAL MONTH & YEAR FILTER ---
$f_month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$f_year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$filter_sql = " WHERE MONTH(date) = $f_month AND YEAR(date) = $f_year ";

// --- 3. FETCH LOGGED-IN USER DATA ---
$admin_session_user = $_SESSION['admin_user'];
$user_res = $conn->query("SELECT * FROM admin_users WHERE username='$admin_session_user'");
$user_data = $user_res->fetch_assoc();

// --- 4. FILTERED KPI CALCULATIONS ---
// Revenue
$sales = $conn->query("SELECT SUM(cash_sales) as c, SUM(online_sales) as o FROM transactions $filter_sql")->fetch_assoc();
$total_rev = ($sales['c'] ?? 0) + ($sales['o'] ?? 0);

// Expenses (Daily Transactions + Detailed Bills + Staff Khata)
$bills = $conn->query("SELECT SUM(amount) as b FROM expense_details $filter_sql")->fetch_assoc();
$daily_exp = $conn->query("SELECT SUM(grocery_expense + staff_expense + other_expense) as d FROM transactions $filter_sql")->fetch_assoc();
$staff_khata = $conn->query("SELECT SUM(amount) as s FROM staff_ledger $filter_sql")->fetch_assoc();

$total_exp = ($bills['b'] ?? 0) + ($daily_exp['d'] ?? 0) + ($staff_khata['s'] ?? 0);
$total_profit = $total_rev - $total_exp;

// --- 5. CHART LOGIC ---
$range = isset($_GET['range']) ? $_GET['range'] : '15';
$labels = []; $rev_data = []; $exp_data = [];

if ($range == '365') {
    $chart_res = $conn->query("SELECT 
        DATE_FORMAT(date, '%b %Y') as display_label, 
        SUM(cash_sales + online_sales) as daily_rev,
        SUM(grocery_expense + staff_expense + other_expense + 
         IFNULL((SELECT SUM(amount) FROM expense_details e WHERE e.date = t.date), 0) +
         IFNULL((SELECT SUM(amount) FROM staff_ledger s WHERE s.date = t.date), 0)) as daily_exp
        FROM transactions t 
        GROUP BY display_label, YEAR(date), MONTH(date) 
        ORDER BY YEAR(date) ASC, MONTH(date) ASC 
        LIMIT 12");
} else {
    $day_limit = (int)$range;
    $chart_res = $conn->query("SELECT date as label, 
        (cash_sales + online_sales) as daily_rev,
        (grocery_expense + staff_expense + other_expense + 
         IFNULL((SELECT SUM(amount) FROM expense_details e WHERE e.date = t.date), 0) +
         IFNULL((SELECT SUM(amount) FROM staff_ledger s WHERE s.date = t.date), 0)) as daily_exp
        FROM transactions t ORDER BY date DESC LIMIT $day_limit");
    $temp_data = [];
    while($row = $chart_res->fetch_assoc()) { $temp_data[] = $row; }
    $temp_data = array_reverse($temp_data);
}

if($range == '365') {
    while($row = $chart_res->fetch_assoc()){
        $labels[] = $row['display_label'];
        $rev_data[] = (float)$row['daily_rev'];
        $exp_data[] = (float)$row['daily_exp'];
    }
} else {
    foreach($temp_data as $row) {
        $labels[] = date('d M', strtotime($row['label']));
        $rev_data[] = (float)$row['daily_rev'];
        $exp_data[] = (float)$row['daily_exp'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $brand_name; ?> | Executive Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .glass { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        .card-hover:hover { transform: translateY(-5px); transition: all 0.3s ease; }
    </style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 pb-10">

    <nav class="bg-slate-900 text-white p-4 sticky top-0 z-50 border-b border-slate-700 shadow-2xl">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl md:text-2xl font-black italic tracking-tighter text-indigo-400 uppercase">
                <?php echo $brand_name; ?>
            </h1>
            
            <div class="flex items-center space-x-6">
                <a href="settings.php" class="p-2 bg-slate-800 hover:bg-indigo-600 rounded-xl transition">
                    <i class="fas fa-cog text-slate-300"></i>
                </a>
                
                <div class="flex items-center space-x-3 border-l border-slate-700 pl-6">
                    <div class="text-right hidden md:block">
                        <p class="text-[10px] font-black uppercase text-indigo-400 leading-none">
                            <?php echo !empty($user_data['full_name']) ? $user_data['full_name'] : $admin_session_user; ?>
                        </p>
                        <a href="profile.php" class="text-[9px] text-slate-400 font-bold uppercase tracking-tighter hover:text-white transition">Edit Profile</a>
                    </div>
                    <?php 
                        $img_src = (!empty($user_data['profile_pic']) && file_exists("uploads/".$user_data['profile_pic'])) 
                                   ? "uploads/".$user_data['profile_pic'] 
                                   : "https://ui-avatars.com/api/?name=".urlencode($admin_session_user)."&background=6366f1&color=fff";
                    ?>
                    <a href="profile.php">
                        <img src="<?php echo $img_src; ?>" class="w-10 h-10 rounded-full border-2 border-indigo-500 object-cover shadow-lg hover:scale-110 transition">
                    </a>
                    <a href="logout.php" class="bg-red-500/10 text-red-500 border border-red-500/20 px-3 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-red-500 hover:text-white transition-all">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        
        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200 mb-10 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="text-[10px] font-black uppercase text-slate-400 tracking-[0.2em]">Dashboard Data Filter</h3>
                <p class="text-xs font-bold text-slate-600 uppercase">Showing stats for: <span class="text-indigo-600"><?php echo date('F Y', mktime(0,0,0,$f_month, 1, $f_year)); ?></span></p>
            </div>
            <form method="GET" class="flex gap-2">
                <select name="m" class="p-3 border rounded-xl text-[10px] font-black bg-slate-50 uppercase">
                    <?php for($i=1; $i<=12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($f_month == $i) ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$i,1)); ?></option>
                    <?php endfor; ?>
                </select>
                <select name="y" class="p-3 border rounded-xl text-[10px] font-black bg-slate-50">
                    <?php for($i=2024; $i<=2030; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($f_year == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="bg-indigo-600 text-white px-6 rounded-xl text-[10px] font-black uppercase shadow-lg hover:bg-indigo-700 transition-all">Apply</button>
            </form>
        </div>

        <div class="flex flex-nowrap lg:flex-wrap gap-4 mb-10 overflow-x-auto pb-4">
            <a href="collection.php" class="flex-shrink-0 bg-white px-6 py-4 rounded-3xl shadow-sm border border-slate-200 card-hover">
                <i class="fas fa-cash-register text-green-500 mb-2 block text-xl"></i>
                <span class="font-bold text-[10px] uppercase">Cash Collection</span>
            </a>
            <a href="revenue.php" class="flex-shrink-0 bg-white px-6 py-4 rounded-3xl shadow-sm border border-slate-200 card-hover">
                <i class="fas fa-chart-line text-indigo-500 mb-2 block text-xl"></i>
                <span class="font-bold text-[10px] uppercase">Revenue Log</span>
            </a>
            <a href="expenses.php" class="flex-shrink-0 bg-white px-6 py-4 rounded-3xl shadow-sm border border-slate-200 card-hover">
                <i class="fas fa-wallet text-red-500 mb-2 block text-xl"></i>
                <span class="font-bold text-[10px] uppercase">Expenses</span>
            </a>
            <a href="vendor_khata.php" class="flex-shrink-0 bg-white px-6 py-4 rounded-3xl shadow-sm border border-slate-200 card-hover">
                <i class="fas fa-truck-loading text-orange-600 mb-2 block text-xl"></i>
                <span class="font-bold text-[10px] uppercase">Vendors</span>
            </a>
            <a href="staff_khata.php" class="flex-shrink-0 bg-white px-6 py-4 rounded-3xl shadow-sm border border-slate-200 card-hover">
                <i class="fas fa-users-cog text-indigo-500 mb-2 block text-xl"></i>
                <span class="font-bold text-[10px] uppercase">Staff Hub</span>
            </a>
            <a href="reports.php" class="flex-shrink-0 bg-white px-6 py-4 rounded-3xl shadow-sm border border-slate-200 card-hover group">
                <div class="relative mb-2">
                    <i class="fas fa-file-lines text-slate-300 text-xl"></i>
                    <i class="fas fa-chart-pie text-emerald-500 text-[10px] absolute -bottom-1 -right-1 bg-white rounded-full p-0.5"></i>
                </div>
                <span class="font-bold text-[10px] uppercase block text-slate-800">Reports</span>
            </a>
            <a href="analytics.php" class="flex-shrink-0 bg-white px-6 py-4 rounded-3xl shadow-sm border border-slate-200 card-hover group transition-all hover:border-indigo-200 hover:bg-indigo-50/30">
        <div class="w-10 h-10 bg-indigo-50 rounded-2xl flex items-center justify-center mb-2 group-hover:bg-indigo-600 transition-all duration-300">
            <i class="fas fa-brain text-indigo-600 text-lg group-hover:text-white transition-colors"></i>
        </div>
        <span class="font-bold text-[10px] uppercase block text-indigo-900 tracking-wider">Analytics</span>
    </a>
    <a href="db_manager.php" class="flex-shrink-0 bg-white px-6 py-4 rounded-3xl shadow-sm border border-slate-200 card-hover">
        <i class="fas fa-cloud-upload-alt text-emerald-500 mb-2 block text-xl"></i>
        <span class="font-bold text-[10px] uppercase tracking-wider">Cloud Sync</span>
    </a>
    <a href="master_ledger.php" class="flex-shrink-0 bg-white px-6 py-4 rounded-3xl shadow-sm border border-slate-200 card-hover group transition-all hover:border-amber-200 hover:bg-amber-50/30">
        <div class="w-10 h-10 bg-amber-50 rounded-2xl flex items-center justify-center mb-2 group-hover:bg-amber-500 transition-all duration-300">
            <i class="fas fa-book-bookmark text-amber-600 text-lg group-hover:text-white transition-colors"></i>
        </div>
        <span class="font-bold text-[10px] uppercase block text-amber-900 tracking-wider">Master Ledger</span>
    </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            <div class="glass p-8 rounded-[2.5rem] shadow-xl border border-white card-hover bg-gradient-to-br from-white to-green-50">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em] mb-2">Total Revenue</p>
                <h2 class="text-4xl font-black text-slate-900 mb-4"><?php echo $currency.number_format($total_rev, 2); ?></h2>
                <div class="flex space-x-2">
                    <span class="bg-green-100 text-green-600 text-[9px] px-2 py-1 rounded-lg font-black">CASH: <?php echo number_format($sales['c']); ?></span>
                    <span class="bg-blue-100 text-blue-600 text-[9px] px-2 py-1 rounded-lg font-black">UPI: <?php echo number_format($sales['o']); ?></span>
                </div>
            </div>

            <div class="glass p-8 rounded-[2.5rem] shadow-xl border border-white card-hover bg-gradient-to-br from-white to-red-50">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em] mb-2">Total Expenses</p>
                <h2 class="text-4xl font-black text-slate-900 mb-4"><?php echo $currency.number_format($total_exp, 2); ?></h2>
                <p class="text-[9px] text-slate-400 font-bold uppercase italic tracking-tighter">Bills, Daily Inventory & Staff</p>
            </div>

            <div class="bg-slate-900 p-8 rounded-[2.5rem] shadow-2xl card-hover text-white">
                <p class="text-indigo-300 text-[10px] font-black uppercase tracking-[0.2em] mb-2">Net Business Profit</p>
                <h2 class="text-4xl font-black text-white mb-4"><?php echo $currency.number_format($total_profit, 2); ?></h2>
                <div class="h-2 w-full bg-slate-700 rounded-full overflow-hidden">
                    <div class="h-full bg-indigo-500" style="width: <?php echo ($total_rev > 0) ? ($total_profit / $total_rev * 100) : 0; ?>%"></div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 md:p-10 rounded-[3rem] shadow-2xl border border-slate-100">
            <div class="flex flex-col md:flex-row justify-between items-center mb-10 gap-4">
                <div>
                    <h3 class="text-2xl font-black text-slate-800 uppercase tracking-tighter">Performance Trend</h3>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Growth analytics Visualization</p>
                </div>
                <div class="bg-slate-100 p-1.5 rounded-2xl flex items-center">
                    <a href="?range=15&m=<?php echo $f_month; ?>&y=<?php echo $f_year; ?>" class="px-5 py-2 text-[10px] font-black uppercase rounded-xl transition-all <?php echo ($range == '15') ? 'bg-white shadow-sm text-indigo-600' : 'text-slate-500'; ?>">15 Days</a>
                    <a href="?range=30&m=<?php echo $f_month; ?>&y=<?php echo $f_year; ?>" class="px-5 py-2 text-[10px] font-black uppercase rounded-xl transition-all <?php echo ($range == '30') ? 'bg-white shadow-sm text-indigo-600' : 'text-slate-500'; ?>">30 Days</a>
                    <a href="?range=365&m=<?php echo $f_month; ?>&y=<?php echo $f_year; ?>" class="px-5 py-2 text-[10px] font-black uppercase rounded-xl transition-all <?php echo ($range == '365') ? 'bg-white shadow-sm text-indigo-600' : 'text-slate-500'; ?>">Full Year</a>
                </div>
            </div>

            <div class="h-[400px]">
                <canvas id="mainTrendChart"></canvas>
            </div>
        </div>

        <div class="text-center mt-16 opacity-40 hover:opacity-100 transition duration-500">
            <p class="text-[10px] font-black uppercase tracking-[0.4em] text-slate-500">
                Developed by <a href="https://github.com/manoranjan2050" class="text-indigo-600 underline">MANORANJAN</a> &bull; <a href="https://manoranjan.dev/" class="text-indigo-600 underline">manoranjan.dev</a> &bull; <?php echo $brand_name; ?> ERP
            </p>
        </div>
    </div>

    <script>
    const ctx = document.getElementById('mainTrendChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [
                {
                    label: 'Revenue',
                    data: <?php echo json_encode($rev_data); ?>,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    fill: true, tension: 0.4, borderWidth: 4, pointRadius: 2
                },
                {
                    label: 'Expenses',
                    data: <?php echo json_encode($exp_data); ?>,
                    borderColor: '#f87171',
                    backgroundColor: 'rgba(248, 113, 113, 0.1)',
                    fill: true, tension: 0.4, borderWidth: 4, pointRadius: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: '#f1f5f9' }, ticks: { font: { weight: 'bold', size: 10 } } },
                x: { grid: { display: false }, ticks: { font: { weight: 'bold', size: 9 } } }
            }
        }
    });
    </script>
</body>
</html>
