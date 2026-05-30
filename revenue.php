<?php
session_start();
if (!isset($_SESSION['admin_user'])) { header("Location: login.php"); exit(); }
include 'db.php';

// --- FILTER LOGIC ---
$f_month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$f_year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

// 1. Fetch Filtered Totals
$totals_query = "SELECT 
    SUM(cash_sales) as total_cash, 
    SUM(online_sales) as total_online, 
    SUM(cash_sales + online_sales) as total_rev 
    FROM transactions 
    WHERE MONTH(date) = $f_month AND YEAR(date) = $f_year";
$totals = $conn->query($totals_query)->fetch_assoc();

// 2. Fetch Daily Data for Chart (Filtered Month)
$chart_res = $conn->query("SELECT date, cash_sales, online_sales FROM transactions WHERE MONTH(date) = $f_month AND YEAR(date) = $f_year ORDER BY date ASC");
$labels = []; $cash_data = []; $online_data = [];
while($row = $chart_res->fetch_assoc()){
    $labels[] = date('d M', strtotime($row['date']));
    $cash_data[] = (float)$row['cash_sales'];
    $online_data[] = (float)$row['online_sales'];
}

// 3. Fetch Ledger for Table (Filtered Month)
$ledger = $conn->query("SELECT * FROM transactions WHERE MONTH(date) = $f_month AND YEAR(date) = $f_year ORDER BY date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Analytics | Resto-Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50 text-slate-800 pb-20">

    <nav class="bg-green-600 text-white shadow-xl p-4 sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold uppercase tracking-tighter"><i class="fas fa-chart-line mr-2"></i> Revenue Analytics</h1>
            <a href="index.php" class="bg-green-700 px-4 py-2 rounded-xl text-xs font-bold transition-all"><i class="fas fa-arrow-left mr-1"></i> Dashboard</a>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-6xl">
        
        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 mb-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Financial Period</h3>
                <p class="text-sm font-bold text-slate-700"><?php echo date('F Y', mktime(0,0,0,$f_month, 1, $f_year)); ?></p>
            </div>
            <form method="GET" class="flex gap-2">
                <select name="m" class="p-3 border rounded-xl text-xs font-black bg-slate-50 outline-none focus:ring-2 focus:ring-green-500">
                    <?php for($i=1; $i<=12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($f_month == $i) ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$i,1)); ?></option>
                    <?php endfor; ?>
                </select>
                <select name="y" class="p-3 border rounded-xl text-xs font-black bg-slate-50 outline-none focus:ring-2 focus:ring-green-500">
                    <?php for($i=2024; $i<=2030; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($f_year == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="bg-slate-800 text-white px-6 rounded-xl text-[10px] font-black uppercase hover:bg-slate-700 transition-all">Filter</button>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-3xl shadow-sm border-l-8 border-green-500 transition-transform hover:scale-105">
                <p class="text-slate-400 text-[10px] font-black uppercase">Total Cash</p>
                <h2 class="text-3xl font-black text-slate-700">₹<?php echo number_format($totals['total_cash'] ?? 0, 2); ?></h2>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border-l-8 border-blue-500 transition-transform hover:scale-105">
                <p class="text-slate-400 text-[10px] font-black uppercase">Total Online</p>
                <h2 class="text-3xl font-black text-slate-700">₹<?php echo number_format($totals['total_online'] ?? 0, 2); ?></h2>
            </div>
            <div class="bg-gradient-to-br from-green-600 to-teal-700 p-6 rounded-3xl shadow-lg text-white transition-transform hover:scale-105">
                <p class="opacity-80 text-[10px] font-black uppercase">Month Gross Revenue</p>
                <h2 class="text-3xl font-black">₹<?php echo number_format($totals['total_rev'] ?? 0, 2); ?></h2>
            </div>
        </div>

        <div class="bg-white p-6 rounded-3xl shadow-xl border border-slate-100 mb-8">
            <h3 class="font-black text-slate-700 mb-6 uppercase text-[10px] tracking-widest"><i class="fas fa-bolt text-yellow-400 mr-2"></i> Monthly Revenue Trend</h3>
            <div class="h-[300px] md:h-[400px]">
                <?php if(!empty($labels)): ?>
                    <canvas id="revenueChart"></canvas>
                <?php else: ?>
                    <div class="h-full flex items-center justify-center text-slate-300 font-bold italic">No data available for this month</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] shadow-xl overflow-hidden border border-slate-100">
            <div class="bg-slate-800 p-5 text-white font-black text-[10px] tracking-widest flex justify-between items-center uppercase">
                <span>Income Log for <?php echo date('M Y', mktime(0,0,0,$f_month, 1, $f_year)); ?></span>
                <i class="fas fa-calendar-check opacity-50"></i>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-[10px] uppercase text-slate-400 font-black">
                        <tr>
                            <th class="px-6 py-4">Date</th>
                            <th class="px-6 py-4">Cash Received</th>
                            <th class="px-6 py-4">Online Received</th>
                            <th class="px-6 py-4">Daily Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm font-bold">
                        <?php if($ledger->num_rows > 0): ?>
                            <?php while($row = $ledger->fetch_assoc()): ?>
                            <tr class="hover:bg-green-50 transition-colors">
                                <td class="px-6 py-4 text-slate-600"><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                                <td class="px-6 py-4 font-mono text-green-600">₹<?php echo number_format($row['cash_sales'], 2); ?></td>
                                <td class="px-6 py-4 font-mono text-blue-600">₹<?php echo number_format($row['online_sales'], 2); ?></td>
                                <td class="px-6 py-4 bg-slate-50 font-black text-slate-800">₹<?php echo number_format($row['cash_sales'] + $row['online_sales'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="p-20 text-center text-slate-300 font-bold italic">No transactions recorded in this period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [
                    {
                        label: 'Cash Sales',
                        data: <?php echo json_encode($cash_data); ?>,
                        backgroundColor: '#22c55e',
                        borderRadius: 8,
                    },
                    {
                        label: 'Online Sales',
                        data: <?php echo json_encode($online_data); ?>,
                        backgroundColor: '#3b82f6',
                        borderRadius: 8,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { font: { weight: 'bold' } }
                    },
                    x: { 
                        grid: { display: false },
                        ticks: { font: { weight: 'bold' } }
                    }
                }
            }
        });
    </script>
</body>
</html>