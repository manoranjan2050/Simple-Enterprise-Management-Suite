<?php
/**
 * Project: Simple Enterprise Management Suite
 * Author: MANORANJAN
 * Website: https://manoranjan.dev/
 */
session_start();
include 'db.php';
if (!isset($_SESSION['admin_user'])) { header("Location: login.php"); exit(); }

// Fetch Global Settings
$global_set = $conn->query("SELECT * FROM global_settings WHERE id=1")->fetch_assoc();
$brand_name = $global_set['hotel_name'] ?? "SIMPLE EMS";
$currency = $global_set['currency_symbol'] ?? "₹";

$msg = "";

// --- MONTH & YEAR FILTER LOGIC ---
$current_month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$current_year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$filter_params = "&m=$current_month&y=$current_year";

// --- 1. STAFF DIRECTORY LOGIC ---
if (isset($_POST['save_staff'])) {
    $name = $conn->real_escape_string($_POST['staff_name']);
    $mobile = $conn->real_escape_string($_POST['mobile']);
    $salary = (float)$_POST['monthly_salary'];
    $address = $conn->real_escape_string($_POST['address']);

    if (!empty($_POST['staff_id_ref'])) {
        $id = (int)$_POST['staff_id_ref'];
        $conn->query("UPDATE staff_list SET name='$name', mobile='$mobile', monthly_salary='$salary', address='$address' WHERE id=$id");
    } else {
        $conn->query("INSERT INTO staff_list (name, mobile, monthly_salary, address) VALUES ('$name', '$mobile', '$salary', '$address')");
    }
    header("Location: staff_khata.php"); exit();
}

if (isset($_GET['delete_staff'])) {
    $id = (int)$_GET['delete_staff'];
    $conn->query("DELETE FROM staff_list WHERE id=$id");
    $conn->query("DELETE FROM staff_ledger WHERE staff_id=$id");
    $conn->query("DELETE FROM staff_attendance WHERE staff_id=$id");
    header("Location: staff_khata.php"); exit();
}

// --- 2. ATTENDANCE LOGIC ---
if (isset($_POST['mark_attendance'])) {
    $staff_id = $_POST['staff_id'];
    $date = $_POST['att_date'];
    $status = $_POST['status'];
    $conn->query("INSERT INTO staff_attendance (staff_id, date, status) VALUES ('$staff_id', '$date', '$status') ON DUPLICATE KEY UPDATE status='$status'");
    $msg = "Attendance synchronized!";
}

// --- 3. LEDGER LOGIC (PAYOUTS/ADVANCES) ---
if (isset($_GET['delete_ledger_id'])) {
    $id = (int)$_GET['delete_ledger_id'];
    $staff_id = (int)$_GET['view_id'];
    $conn->query("DELETE FROM staff_ledger WHERE id=$id");
    header("Location: staff_khata.php?view_id=$staff_id" . $filter_params); exit();
}

if (isset($_POST['save_entry'])) {
    $staff_id = $_POST['staff_id'];
    $type = $_POST['type'];
    $amount = (float)$_POST['amount'];
    $date = $_POST['payout_date'] ?: date('Y-m-d');
    $note = $conn->real_escape_string($_POST['note']); 
    
    if (!empty($_POST['entry_id'])) {
        $id = (int)$_POST['entry_id'];
        $conn->query("UPDATE staff_ledger SET type='$type', amount='$amount', date='$date', note='$note' WHERE id=$id");
    } else {
        $conn->query("INSERT INTO staff_ledger (staff_id, type, amount, date, note) VALUES ('$staff_id', '$type', '$amount', '$date', '$note')");
    }
    header("Location: staff_khata.php?view_id=$staff_id" . $filter_params); exit();
}

// --- 4. DATA FETCHING ---
$staff_res = $conn->query("SELECT * FROM staff_list ORDER BY name ASC");
$edit_entry = (isset($_GET['edit_ledger_id'])) ? $conn->query("SELECT * FROM staff_ledger WHERE id=".(int)$_GET['edit_ledger_id'])->fetch_assoc() : null;
$staff_to_edit = (isset($_GET['edit_staff'])) ? $conn->query("SELECT * FROM staff_list WHERE id=".(int)$_GET['edit_staff'])->fetch_assoc() : null;

$selected_staff = null;
$ledger_data = [];
$att_grid = [];
$yearly_earned = 0; // NEW FEATURE VARIABLE
$yearly_paid = 0;   // NEW FEATURE VARIABLE

if (isset($_GET['view_id'])) {
    $view_id = (int)$_GET['view_id'];
    $selected_staff = $conn->query("SELECT * FROM staff_list WHERE id=$view_id")->fetch_assoc();
    
    // Monthly Data
    $res = $conn->query("SELECT * FROM staff_ledger WHERE staff_id=$view_id AND MONTH(date)=$current_month AND YEAR(date)=$current_year ORDER BY date DESC");
    while($row = $res->fetch_assoc()) { $ledger_data[] = $row; }
    
    $att_res = $conn->query("SELECT * FROM staff_attendance WHERE staff_id=$view_id AND MONTH(date)=$current_month AND YEAR(date)=$current_year");
    while($row = $att_res->fetch_assoc()) { $att_grid[date('j', strtotime($row['date']))] = $row['status']; }

    // --- NEW: YEARLY TOTAL CALCULATION ---
    // 1. Calculate Yearly Paid
    $y_p_res = $conn->query("SELECT SUM(amount) as y_total FROM staff_ledger WHERE staff_id=$view_id AND YEAR(date)=$current_year")->fetch_assoc();
    $yearly_paid = $y_p_res['y_total'] ?? 0;

    // 2. Calculate Yearly Earned (Based on attendance work days throughout the selected year)
    $y_att_res = $conn->query("SELECT status, date FROM staff_attendance WHERE staff_id=$view_id AND YEAR(date)=$current_year");
    while($y_row = $y_att_res->fetch_assoc()){
        $m = date('n', strtotime($y_row['date']));
        $y = date('Y', strtotime($y_row['date']));
        $dim = cal_days_in_month(CAL_GREGORIAN, $m, $y);
        $day_val = ($y_row['status'] == 'Present') ? 1 : (($y_row['status'] == 'Half-Day') ? 0.5 : 0);
        $yearly_earned += ($selected_staff['monthly_salary'] / $dim) * $day_val;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <title>Staff Master Hub | <?php echo $brand_name; ?></title>
    <style>
        html { scroll-behavior: smooth; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #6366f1; border-radius: 10px; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

<nav class="bg-slate-900 text-white p-5 shadow-2xl sticky top-0 z-50 border-b border-indigo-500/30">
    <div class="container mx-auto flex justify-between items-center">
        <h1 class="text-xl font-black italic tracking-tighter uppercase text-indigo-400"><?php echo $brand_name; ?> <span class="text-white text-[10px] not-italic ml-2 opacity-50 tracking-widest">STAFF HUB</span></h1>
        <a href="index.php" class="bg-indigo-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-500 transition-all">Dashboard</a>
    </div>
</nav>

<div class="container mx-auto px-4 mt-10 grid grid-cols-1 lg:grid-cols-12 gap-8">
    
    <div class="lg:col-span-4 space-y-6">
        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200">
            <h3 class="text-[10px] font-black text-slate-400 uppercase mb-4 tracking-widest"><?php echo $staff_to_edit ? 'Update Employee' : 'Add New Employee'; ?></h3>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="staff_id_ref" value="<?php echo $staff_to_edit['id'] ?? ''; ?>">
                <input type="text" name="staff_name" value="<?php echo $staff_to_edit['name'] ?? ''; ?>" placeholder="Full Name" class="w-full p-3 border rounded-xl text-xs font-bold bg-slate-50 outline-none focus:ring-2 focus:ring-indigo-400" required>
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="mobile" value="<?php echo $staff_to_edit['mobile'] ?? ''; ?>" placeholder="Mobile" class="p-3 border rounded-xl text-xs bg-slate-50">
                    <input type="number" name="monthly_salary" value="<?php echo $staff_to_edit['monthly_salary'] ?? ''; ?>" placeholder="Salary <?php echo $currency; ?>" class="p-3 border rounded-xl text-xs font-black text-indigo-600 bg-slate-50">
                </div>
                <button name="save_staff" class="w-full <?php echo $staff_to_edit ? 'bg-orange-500 shadow-orange-100' : 'bg-slate-800 shadow-slate-200'; ?> text-white font-black py-3 rounded-xl text-[10px] uppercase tracking-widest shadow-lg transition-all">
                    <?php echo $staff_to_edit ? 'Save Changes' : 'Register Staff'; ?>
                </button>
            </form>

            <div class="mt-6">
                <p class="text-[9px] font-black text-slate-300 uppercase mb-2">Current Directory</p>
                <div class="max-h-40 overflow-y-auto space-y-2 pr-2">
                    <?php $staff_res->data_seek(0); while($s = $staff_res->fetch_assoc()): ?>
                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100 <?php echo ($view_id == $s['id']) ? 'border-indigo-400 ring-1 ring-indigo-100' : ''; ?>">
                        <a href="?view_id=<?php echo $s['id']; ?><?php echo $filter_params; ?>" class="text-[11px] font-bold text-slate-700 truncate"><?php echo $s['name']; ?></a>
                        <div class="flex gap-2">
                            <a href="?edit_staff=<?php echo $s['id']; ?>" class="text-indigo-400 hover:text-indigo-600"><i class="fas fa-edit text-[10px]"></i></a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <?php if($selected_staff): ?>
        <div id="ledger-form" class="bg-white p-6 rounded-[2rem] shadow-xl border-t-8 <?php echo $edit_entry ? 'border-orange-500' : 'border-indigo-600'; ?>">
            <h3 class="font-black text-slate-800 text-[10px] uppercase mb-4 tracking-widest">
                <?php echo $edit_entry ? 'Edit Ledger' : 'Record Payout'; ?>
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="staff_id" value="<?php echo $selected_staff['id']; ?>">
                <input type="hidden" name="entry_id" value="<?php echo $edit_entry['id'] ?? ''; ?>">
                
                <select name="type" class="w-full p-3 border rounded-xl font-black bg-indigo-50 text-indigo-700 text-[10px] uppercase">
                    <option value="Advance" <?php echo (@$edit_entry['type'] == 'Advance') ? 'selected' : ''; ?>>Give Advance</option>
                    <option value="Salary_Payment" <?php echo (@$edit_entry['type'] == 'Salary_Payment') ? 'selected' : ''; ?>>Salary Payment</option>
                </select>

                <input type="number" name="amount" value="<?php echo $edit_entry['amount'] ?? ''; ?>" placeholder="Amount" class="w-full p-4 border rounded-xl font-black text-2xl text-indigo-900 focus:ring-2 focus:ring-indigo-100 outline-none" required>
                
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2 mb-1 block">Transaction Date</label>
                    <input type="date" name="payout_date" value="<?php echo $edit_entry['date'] ?? date('Y-m-d'); ?>" class="w-full p-3 border rounded-xl text-xs font-bold bg-slate-50">
                </div>

                <input type="text" name="note" value="<?php echo $edit_entry['note'] ?? ''; ?>" placeholder="Reason / Note" class="w-full p-3 border rounded-xl text-xs font-bold bg-slate-50">
                
                <button type="submit" name="save_entry" class="w-full bg-indigo-600 text-white font-black py-4 rounded-2xl shadow-lg uppercase text-[10px] tracking-widest">Record Transaction</button>
            </form>
        </div>

        <div class="bg-indigo-900 p-6 rounded-[2rem] shadow-xl text-white">
            <h3 class="text-[10px] font-black uppercase mb-4 tracking-widest text-indigo-300">Daily Attendance</h3>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="staff_id" value="<?php echo $selected_staff['id']; ?>">
                <input type="date" name="att_date" value="<?php echo date('Y-m-d'); ?>" class="w-full p-3 rounded-xl text-xs font-black bg-white/10 border border-white/10 outline-none">
                <select name="status" class="w-full p-3 rounded-xl text-xs font-black bg-slate-800 border-none outline-none">
                    <option value="Present">Present (Full)</option>
                    <option value="Half-Day">Half Day</option>
                    <option value="Absent">Absent</option>
                    <option value="Leave">Leave</option>
                </select>
                <button type="submit" name="mark_attendance" class="w-full bg-indigo-500 text-white font-black py-3 rounded-xl text-[10px] uppercase tracking-widest hover:bg-indigo-400">Mark Status</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div class="lg:col-span-8">
        <?php if($selected_staff): ?>
            
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200 mb-8 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="font-black text-slate-800 uppercase text-xs tracking-widest"><?php echo $selected_staff['name']; ?>'s Hub</h3>
                    <p class="text-[10px] text-indigo-500 font-bold uppercase">Viewing <?php echo date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)); ?></p>
                </div>
                <form method="GET" class="flex gap-2">
                    <input type="hidden" name="view_id" value="<?php echo $selected_staff['id']; ?>">
                    <select name="m" class="p-3 border rounded-xl text-[10px] font-black bg-slate-50">
                        <?php for($i=1; $i<=12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($current_month == $i) ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$i,1)); ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="y" class="p-3 border rounded-xl text-[10px] font-black bg-slate-50">
                        <?php for($i=2024; $i<=2026; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($current_year == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="bg-slate-800 text-white px-5 rounded-xl text-[10px] font-black uppercase">Filter</button>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-slate-900 text-white p-6 rounded-[2.5rem] shadow-xl relative overflow-hidden">
                    <div class="absolute right-0 top-0 p-4 opacity-10">
                        <i class="fas fa-calendar-alt text-6xl"></i>
                    </div>
                    <p class="text-[10px] font-black text-indigo-300 uppercase tracking-widest mb-1">Full Year Work Value (<?php echo $current_year; ?>)</p>
                    <h2 class="text-3xl font-black"><?php echo $currency.number_format($yearly_earned, 2); ?></h2>
                    <p class="text-[8px] uppercase font-bold text-slate-400 mt-2">Calculated based on attendance history</p>
                </div>

                <div class="bg-indigo-600 text-white p-6 rounded-[2.5rem] shadow-xl relative overflow-hidden">
                    <div class="absolute right-0 top-0 p-4 opacity-10">
                        <i class="fas fa-hand-holding-usd text-6xl"></i>
                    </div>
                    <p class="text-[10px] font-black text-indigo-100 uppercase tracking-widest mb-1">Full Year Total Paid (<?php echo $current_year; ?>)</p>
                    <h2 class="text-3xl font-black"><?php echo $currency.number_format($yearly_paid, 2); ?></h2>
                    <p class="text-[8px] uppercase font-bold text-indigo-200 mt-2">Includes Salary + Advances for <?php echo $current_year; ?></p>
                </div>
            </div>
            <?php 
                $adv = 0; foreach($ledger_data as $l) { if($l['type'] == 'Advance') $adv += $l['amount']; }
                $p_days = 0; $h_days = 0; $a_days = 0;
                foreach($att_grid as $status) { 
                    if($status == 'Present') $p_days++; 
                    if($status == 'Half-Day') $h_days++;
                    if($status == 'Absent') $a_days++;
                }
                $work_days = $p_days + ($h_days * 0.5);
                $days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
                $earned = ($selected_staff['monthly_salary'] / $days_in_month) * $work_days;
            ?>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="bg-white p-6 rounded-3xl border shadow-sm">
                    <p class="text-[9px] font-black text-slate-400 uppercase">Fixed Salary</p>
                    <p class="text-xl font-black text-slate-800"><?php echo $currency.number_format($selected_staff['monthly_salary']); ?></p>
                </div>
                <div class="bg-white p-6 rounded-3xl border-l-4 border-orange-500 shadow-sm">
                    <p class="text-[9px] font-black text-orange-400 uppercase">Monthly Advance</p>
                    <p class="text-xl font-black text-orange-600"><?php echo $currency.number_format($adv); ?></p>
                </div>
                <div class="bg-indigo-50 p-6 rounded-3xl shadow-sm border border-indigo-100">
                    <p class="text-[9px] font-black text-indigo-400 uppercase">Monthly Earned</p>
                    <p class="text-xl font-black text-indigo-700"><?php echo $currency.number_format($earned, 2); ?></p>
                </div>
            </div>

            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border mb-8">
                <div class="grid grid-cols-7 md:grid-cols-10 gap-2">
                    <?php 
                    for($d=1; $d<=$days_in_month; $d++): 
                        $st = $att_grid[$d] ?? 'None';
                        $bg = "bg-slate-50 text-slate-300 border border-slate-100";
                        if($st == 'Present') $bg = "bg-emerald-500 text-white shadow-lg shadow-emerald-100";
                        if($st == 'Half-Day') $bg = "bg-orange-500 text-white shadow-lg shadow-orange-100";
                        if($st == 'Absent') $bg = "bg-rose-600 text-white shadow-lg shadow-rose-100";
                        if($st == 'Leave') $bg = "bg-sky-500 text-white shadow-lg shadow-sky-100";
                    ?>
                    <div class="aspect-square flex flex-col items-center justify-center rounded-xl <?php echo $bg; ?> transition-all hover:scale-110 cursor-help" title="Status: <?php echo $st; ?>">
                        <span class="text-[8px] font-bold opacity-70"><?php echo $d; ?></span>
                        <span class="text-[10px] font-black"><?php echo ($st != 'None') ? substr($st, 0, 1) : ''; ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] shadow-xl overflow-hidden border">
                <div class="bg-slate-800 p-4 text-white font-black text-[10px] uppercase tracking-[0.2em] flex justify-between items-center">
                    <span>Monthly Transaction Ledger</span>
                    <i class="fas fa-history text-slate-500"></i>
                </div>
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b text-slate-400 font-black uppercase">
                        <tr>
                            <th class="p-4">Date</th>
                            <th class="p-4">Type</th>
                            <th class="p-4">Reason</th>
                            <th class="p-4 text-right">Amount</th>
                            <th class="p-4 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach($ledger_data as $row): ?>
                        <tr class="hover:bg-indigo-50/30 transition-all <?php echo (@$_GET['edit_ledger_id'] == $row['id']) ? 'bg-orange-50' : ''; ?>">
                            <td class="p-4 font-bold text-slate-500"><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                            <td class="p-4">
                                <span class="px-2 py-0.5 rounded-md text-[8px] font-black uppercase <?php echo $row['type']=='Advance'?'bg-orange-100 text-orange-600':'bg-green-100 text-green-600'; ?>"><?php echo $row['type']; ?></span>
                            </td>
                            <td class="p-4 text-slate-600 font-medium italic truncate max-w-[150px]"><?php echo $row['note'] ?: '-'; ?></td>
                            <td class="p-4 text-right font-black text-slate-900"><?php echo $currency.number_format($row['amount']); ?></td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-4">
                                    <a href="?view_id=<?php echo $selected_staff['id']; ?>&edit_ledger_id=<?php echo $row['id']; ?><?php echo $filter_params; ?>#ledger-form" class="text-indigo-500 hover:scale-125 transition-transform"><i class="fas fa-edit"></i></a>
                                    <a href="?view_id=<?php echo $selected_staff['id']; ?>&delete_ledger_id=<?php echo $row['id']; ?><?php echo $filter_params; ?>" onclick="return confirm('Delete record permanently?')" class="text-red-300 hover:text-red-600 transition-colors"><i class="fas fa-trash-alt"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($ledger_data)): ?>
                            <tr><td colspan="5" class="p-10 text-center text-slate-300 font-bold uppercase text-[9px] tracking-widest italic">No transactions this month</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <div class="h-full min-h-[500px] flex flex-col items-center justify-center bg-white rounded-[3rem] border-4 border-dashed border-slate-100 text-slate-300">
                <i class="fas fa-id-card text-8xl mb-6 opacity-10"></i>
                <p class="font-black uppercase tracking-widest text-[10px]">Select an employee from directory</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
