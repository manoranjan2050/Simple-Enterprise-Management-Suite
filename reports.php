<?php
session_start();
include 'db.php';
if (!isset($_SESSION['admin_user'])) { header("Location: login.php"); exit(); }

$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to start of month
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// 1. Fetch Sales
$sales = $conn->query("SELECT SUM(cash_sales) as cash, SUM(online_sales) as online FROM transactions WHERE date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc();

// 2. Fetch Expenses (Combined)
$bills = $conn->query("SELECT SUM(amount) as b FROM expense_details WHERE date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc();
$daily_exp = $conn->query("SELECT SUM(grocery_expense + staff_expense + other_expense) as d FROM transactions WHERE date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc();

// 3. Detailed Transaction List
$report_list = $conn->query("SELECT date, (cash_sales + online_sales) as rev, (grocery_expense + staff_expense + other_expense) as exp, note FROM transactions WHERE date BETWEEN '$start_date' AND '$end_date' ORDER BY date ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Report | Resto Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; padding: 0; }
            .print-container { box-shadow: none; border: none; width: 100%; }
        }
    </style>
</head>
<body class="bg-slate-100 p-4 md:p-10">

    <div class="max-w-4xl mx-auto no-print bg-white p-6 rounded-2xl shadow-md mb-8">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <h2 class="font-bold text-slate-700">Select Report Period</h2>
            <form class="flex flex-wrap gap-2">
                <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="border p-2 rounded-lg text-sm">
                <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="border p-2 rounded-lg text-sm">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-bold">Generate</button>
            </form>
            <div class="flex gap-2">
                <button onclick="window.print()" class="bg-slate-800 text-white px-4 py-2 rounded-lg text-sm font-bold"><i class="fas fa-print mr-2"></i> Save as PDF</button>
                <a href="index.php" class="text-slate-400 text-sm py-2 px-2">Back</a>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto bg-white p-10 rounded-xl shadow-2xl print-container border border-slate-200">
        
        <div class="flex justify-between border-b-4 border-slate-900 pb-6 mb-8">
            <div>
                <h1 class="text-3xl font-black tracking-tighter uppercase">HOTEL RESTO PRO</h1>
                <p class="text-slate-500 font-bold uppercase text-xs">Back Office Financial Statement</p>
            </div>
            <div class="text-right">
                <p class="text-xs font-bold text-slate-400 uppercase">Report Period</p>
                <p class="font-black"><?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-10">
            <div class="border p-4 rounded-lg bg-slate-50">
                <p class="text-[10px] font-black text-slate-400 uppercase">Total Revenue</p>
                <p class="text-xl font-black text-green-600">₹<?php echo number_format($sales['cash'] + $sales['online'], 2); ?></p>
            </div>
            <div class="border p-4 rounded-lg bg-slate-50">
                <p class="text-[10px] font-black text-slate-400 uppercase">Total Expenses</p>
                <p class="text-xl font-black text-red-600">₹<?php echo number_format($bills['b'] + $daily_exp['d'], 2); ?></p>
            </div>
            <div class="border p-4 rounded-lg bg-slate-900 text-white">
                <p class="text-[10px] font-black text-slate-400 uppercase">Net Profit/Loss</p>
                <p class="text-xl font-black text-indigo-400">₹<?php echo number_format(($sales['cash'] + $sales['online']) - ($bills['b'] + $daily_exp['d']), 2); ?></p>
            </div>
        </div>

        <table class="w-full text-left text-sm mb-10">
            <thead>
                <tr class="border-b-2 border-slate-200 text-slate-400 uppercase text-[10px] font-black">
                    <th class="py-3">Date</th>
                    <th class="py-3">Revenue</th>
                    <th class="py-3">Expense</th>
                    <th class="py-3">Daily Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php while($row = $report_list->fetch_assoc()): ?>
                <tr>
                    <td class="py-3 font-bold"><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                    <td class="py-3 text-green-600 font-medium">₹<?php echo number_format($row['rev'], 2); ?></td>
                    <td class="py-3 text-red-500 font-medium">₹<?php echo number_format($row['exp'], 2); ?></td>
                    <td class="py-3 font-black">₹<?php echo number_format($row['rev'] - $row['exp'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="mt-20 flex justify-between items-end border-t pt-10">
            <div class="text-xs text-slate-400">
                <p>Generated on: <?php echo date('d M Y H:i:s'); ?></p>
                <p>System User: Administrator</p>
            </div>
            <div class="text-center border-t border-slate-900 w-48 pt-2">
                <p class="text-xs font-bold uppercase">Authorized Signatory</p>
            </div>
        </div>
    </div>
</body>
</html>