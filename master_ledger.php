<?php
session_start();
include 'db.php';
if (!isset($_SESSION['admin_user'])) { header("Location: login.php"); exit(); }

// --- 1. PERIOD SELECTION LOGIC ---
$f_month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$f_year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

// Calculate start and end date based on selected month/year
$start_date = "$f_year-$f_month-01";
$end_date = date("Y-m-t", strtotime($start_date)); // Last day of that month

// --- 2. MASTER QUERY (Revenue + Expenses + Staff) ---
$master_query = "
    (SELECT date, 'REVENUE' as type, 
        CONCAT('Sales (Cash: ₹', cash_sales, ' | UPI: ₹', online_sales, ')') as details, 
        (cash_sales + online_sales) as money_in, 0 as money_out 
    FROM transactions 
    WHERE (cash_sales + online_sales) > 0 AND date BETWEEN '$start_date' AND '$end_date')
    
    UNION ALL
    
    (SELECT e.date, c.category_name as type, 
        e.note as details, 
        0 as money_in, e.amount as money_out 
    FROM expense_details e 
    LEFT JOIN expense_categories c ON e.category_id = c.id
    WHERE e.date BETWEEN '$start_date' AND '$end_date')
    
    UNION ALL
    
    (SELECT s.date, 'STAFF' as type, 
        CONCAT('To: ', sl.name, ' (', s.type, ')') as details, 
        0 as money_in, s.amount as money_out 
    FROM staff_ledger s
    LEFT JOIN staff_list sl ON s.staff_id = sl.id
    WHERE s.date BETWEEN '$start_date' AND '$end_date')
    
    ORDER BY date DESC";

$ledger_res = $conn->query($master_query);

// Summary Calculations
$totals = $conn->query("SELECT SUM(money_in) as total_in, SUM(money_out) as total_out FROM ($master_query) as subquery")->fetch_assoc();
$net_profit = $totals['total_in'] - $totals['total_out'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Master Ledger | Fiscal Record</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .print-padding { padding: 0 !important; }
            .shadow-2xl { shadow: none !important; }
            table { border: 1px solid #e2e8f0; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 pb-20">

    <nav class="bg-slate-900 text-white p-4 sticky top-0 z-50 shadow-2xl no-print">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="font-black text-xl tracking-tighter italic uppercase">Master <span class="text-amber-400">Ledger</span></h1>
            <div class="flex items-center gap-4">
                <form class="flex gap-2">
                    <select name="m" class="bg-slate-800 border-none rounded-xl p-2 text-xs text-white font-bold">
                        <?php for($i=1; $i<=12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($f_month == $i) ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$i,1)); ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="y" class="bg-slate-800 border-none rounded-xl p-2 text-xs text-white font-bold">
                        <?php for($i=2024; $i<=2030; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($f_year == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="bg-amber-500 hover:bg-amber-600 px-4 py-2 rounded-xl text-xs font-black text-slate-900 transition-all uppercase">Load</button>
                </form>
                <div class="h-8 w-[1px] bg-slate-700"></div>
                <button onclick="window.print()" class="bg-emerald-600 px-4 py-2 rounded-xl text-xs font-black uppercase hover:bg-emerald-500 transition-all">
                    <i class="fas fa-file-pdf mr-2"></i> Export
                </button>
                <a href="index.php" class="bg-slate-700 px-4 py-2 rounded-xl text-xs font-bold uppercase hover:bg-slate-600 transition-all">Close</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-10 max-w-6xl print-padding">
        
        <div class="hidden print:block text-center mb-10 border-b-2 border-slate-900 pb-6">
            <h1 class="text-4xl font-black uppercase tracking-widest"><?php echo "HOTEL MANAGEMENT SYSTEM"; ?></h1>
            <p class="text-lg font-bold text-slate-600 uppercase mt-2">Consolidated Financial Statement</p>
            <p class="text-sm font-black text-indigo-600 mt-1 uppercase"><?php echo date('F Y', strtotime($start_date)); ?></p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border-l-8 border-green-500">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Period Inflow</p>
                <h2 class="text-3xl font-black text-slate-800">₹<?php echo number_format($totals['total_in'] ?? 0, 2); ?></h2>
                <span class="text-[9px] font-bold text-green-600 uppercase tracking-tighter italic">Total Cash & UPI Sales</span>
            </div>
            
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border-l-8 border-red-500">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Period Outflow</p>
                <h2 class="text-3xl font-black text-slate-800">₹<?php echo number_format($totals['total_out'] ?? 0, 2); ?></h2>
                <span class="text-[9px] font-bold text-red-600 uppercase tracking-tighter italic">Bills + Salaries + Groceries</span>
            </div>

            <div class="<?php echo $net_profit >= 0 ? 'bg-slate-900' : 'bg-red-900'; ?> p-8 rounded-[2.5rem] shadow-2xl text-white relative overflow-hidden">
                <p class="text-indigo-300 text-[10px] font-black uppercase tracking-widest mb-1">Net Balance</p>
                <h2 class="text-3xl font-black">₹<?php echo number_format($net_profit, 2); ?></h2>
                <div class="mt-2">
                    <span class="px-3 py-1 rounded-full text-[8px] font-black uppercase <?php echo $net_profit >= 0 ? 'bg-green-500' : 'bg-red-500'; ?>">
                        <?php echo $net_profit >= 0 ? 'Profitable Period' : 'Operating Loss'; ?>
                    </span>
                </div>
                <i class="fas fa-vault absolute -right-4 -bottom-4 opacity-10 text-7xl"></i>
            </div>
        </div>

        <div class="bg-white rounded-[3rem] shadow-2xl overflow-hidden border border-slate-200">
            <div class="bg-slate-800 p-6 text-white no-print flex justify-between items-center">
                <h3 class="text-[10px] font-black uppercase tracking-[0.3em]"><i class="fas fa-list-check mr-2 text-amber-400"></i> Detailed Transaction Feed</h3>
                <span class="text-[9px] bg-white/10 px-4 py-1 rounded-full font-bold italic"><?php echo date('F Y', strtotime($start_date)); ?></span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-400 tracking-widest border-b">
                        <tr>
                            <th class="p-6">Txn Date</th>
                            <th class="p-6">Class</th>
                            <th class="p-6">Narration / Details</th>
                            <th class="p-6 text-right">Credit (+)</th>
                            <th class="p-6 text-right">Debit (-)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-bold text-sm">
                        <?php if($ledger_res->num_rows > 0): ?>
                            <?php while($row = $ledger_res->fetch_assoc()): ?>
                            <tr class="hover:bg-indigo-50/30 transition-all">
                                <td class="p-6 text-slate-500"><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                                <td class="p-6">
                                    <span class="px-3 py-1 rounded-lg text-[9px] font-black uppercase
                                        <?php 
                                            if($row['type'] == 'REVENUE') echo 'bg-green-100 text-green-700';
                                            elseif($row['type'] == 'STAFF') echo 'bg-indigo-100 text-indigo-700';
                                            elseif(in_array($row['type'], ['GAS', 'MILK', 'CHENA', 'GROCERY'])) echo 'bg-amber-100 text-amber-700';
                                            else echo 'bg-slate-200 text-slate-700';
                                        ?>
                                    ">
                                        <?php echo $row['type']; ?>
                                    </span>
                                </td>
                                <td class="p-6 text-slate-600 italic tracking-tight font-medium">
                                    <?php echo $row['details']; ?>
                                </td>
                                <td class="p-6 text-right font-black text-emerald-600">
                                    <?php echo $row['money_in'] > 0 ? '₹'.number_format($row['money_in'], 2) : '-'; ?>
                                </td>
                                <td class="p-6 text-right font-black text-rose-600">
                                    <?php echo $row['money_out'] > 0 ? '₹'.number_format($row['money_out'], 2) : '-'; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="p-20 text-center text-slate-300 font-bold italic uppercase tracking-widest text-xs">No records found for this period</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-slate-900 text-white font-black text-sm">
                        <tr>
                            <td colspan="3" class="p-6 text-right uppercase tracking-widest text-[10px] opacity-70">Closing Totals</td>
                            <td class="p-6 text-right text-green-400">₹<?php echo number_format($totals['total_in'] ?? 0, 2); ?></td>
                            <td class="p-6 text-right text-red-400">₹<?php echo number_format($totals['total_out'] ?? 0, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="mt-10 text-center no-print opacity-40 hover:opacity-100 transition-opacity">
            <p class="text-[9px] font-black uppercase text-slate-500 tracking-[0.5em]">Audit Record System &bull; Manoranjan ERP Suite</p>
        </div>
    </div>

</body>
</html>