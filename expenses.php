<?php
session_start();
include 'db.php';
if (!isset($_SESSION['admin_user'])) { header("Location: login.php"); exit(); }

// --- PAGINATION & FILTER LOGIC ---
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$filter_cat = isset($_GET['filter_cat']) ? (int)$_GET['filter_cat'] : 0;
// NEW: Month and Year Filters
$filter_month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$filter_year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

// Helper for building URLs
$filter_params = "&filter_cat=$filter_cat&m=$filter_month&y=$filter_year";

// 1. ADD NEW CATEGORY
if (isset($_POST['add_cat'])) {
    $cat = strtoupper($conn->real_escape_string($_POST['cat_name']));
    $conn->query("INSERT INTO expense_categories (category_name) VALUES ('$cat')");
}

// 2. SAVE OR UPDATE
if (isset($_POST['save_expense'])) {
    $date = $_POST['date'] ?: date('Y-m-d');
    $cat_id = $_POST['cat_id'];
    $amount = (float)$_POST['amount'];
    $note = $conn->real_escape_string($_POST['note']);
    $status = $_POST['status'] ?? 'Paid'; 

    if (!empty($_POST['exp_id'])) {
        $id = (int)$_POST['exp_id'];
        $conn->query("UPDATE expense_details SET date='$date', category_id='$cat_id', amount='$amount', note='$note', status='$status' WHERE id=$id");
    } else {
        $conn->query("INSERT INTO expense_details (date, category_id, amount, note, status) VALUES ('$date', '$cat_id', '$amount', '$note', '$status')");
    }
    header("Location: expenses.php?page=$page" . $filter_params); exit();
}

// 3. DELETE
if (isset($_GET['delete'])) { 
    $id = (int)$_GET['delete']; 
    $conn->query("DELETE FROM expense_details WHERE id=$id"); 
    header("Location: expenses.php?page=$page" . $filter_params); exit();
}

$edit_data = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit_data = $conn->query("SELECT * FROM expense_details WHERE id=$id")->fetch_assoc();
}

// 4. CATEGORIES LIST
$categories = $conn->query("SELECT * FROM expense_categories ORDER BY category_name ASC");

// --- 5. FIXED SQL QUERIES (Aligning Columns for UNION) ---
$where_bill = "WHERE MONTH(e.date) = $filter_month AND YEAR(e.date) = $filter_year";
if ($filter_cat > 0) {
    $where_bill .= " AND e.category_id = $filter_cat";
}

// Only include Staff in the "All Categories" view for the selected month/year
$staff_union_query = "";
if ($filter_cat == 0) {
    $staff_union_query = " UNION ALL 
        SELECT s.date, CONCAT('STAFF: ', sl.name) as category_name, s.amount, CONCAT(s.type, ': ', s.note) as note, 'Paid' as status, s.id, 'staff' as entry_type 
        FROM staff_ledger s 
        LEFT JOIN staff_list sl ON s.staff_id = sl.id
        WHERE MONTH(s.date) = $filter_month AND YEAR(s.date) = $filter_year";
}

// A. Get Total Spent Summary (Date Filtered)
$sum_query = "SELECT SUM(amount) as total FROM (
    SELECT e.amount FROM expense_details e $where_bill
    " . ($filter_cat == 0 ? " UNION ALL SELECT amount FROM staff_ledger WHERE MONTH(date) = $filter_month AND YEAR(date) = $filter_year" : "") . "
) as total_sum";
$sum_res = $conn->query($sum_query)->fetch_assoc();
$total_spent_val = $sum_res['total'] ?? 0;

// B. Get Total Rows for Pagination
$count_query = "SELECT COUNT(*) as total FROM (
    SELECT e.id FROM expense_details e $where_bill
    " . ($filter_cat == 0 ? " UNION ALL SELECT id FROM staff_ledger WHERE MONTH(date) = $filter_month AND YEAR(date) = $filter_year" : "") . "
) as combined_count";
$total_rows_res = $conn->query($count_query)->fetch_assoc();
$total_rows = $total_rows_res['total'];
$total_pages = ceil($total_rows / $limit);

// C. Main Data Query
$main_query = "
    (SELECT e.date, c.category_name, e.amount, e.note, e.status, e.id, 'bill' as entry_type 
     FROM expense_details e 
     LEFT JOIN expense_categories c ON e.category_id = c.id 
     $where_bill) 
    $staff_union_query 
    ORDER BY date DESC LIMIT $limit OFFSET $offset";

$expenses = $conn->query($main_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="pwa-register.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <title>Expense Manager | Simple EMS</title>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-slate-50 pb-20">

<nav class="bg-red-600 text-white p-3 sm:p-4 shadow-lg no-print" x-data="{ mobileOpen: false }">
    <div class="flex justify-between items-center">
        <h1 class="font-bold uppercase tracking-tighter text-sm md:text-base"><i class="fas fa-file-invoice-dollar mr-2"></i> Expense Management</h1>
        <div class="hidden sm:flex items-center">
            <a href="index.php" class="text-[10px] bg-red-900 px-4 py-2 rounded-lg font-bold uppercase tracking-widest">Dashboard</a>
        </div>
        <button @click="mobileOpen = !mobileOpen" class="sm:hidden p-2 bg-red-900 rounded-xl">
            <i class="fas" :class="mobileOpen ? 'fa-xmark' : 'fa-bars'"></i>
        </button>
    </div>
    <div x-show="mobileOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="sm:hidden mt-4 pb-2 flex flex-col gap-2 border-t border-red-500 pt-4">
        <a href="index.php" class="p-3 bg-red-900 rounded-xl text-[10px] font-black uppercase text-center"><i class="fas fa-arrow-left mr-2"></i>Dashboard</a>
    </div>
</nav>

<div class="container mx-auto px-4 mt-8 grid grid-cols-1 lg:grid-cols-12 gap-6" x-data="{ show: false }" x-init="setTimeout(() => show = true, 50)" x-show="show" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
    
    <div class="lg:col-span-4 space-y-6 no-print">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-[10px] font-black text-slate-400 uppercase mb-3">Add Category/Vendor</h3>
            <form method="POST" class="flex gap-2">
                <input type="text" name="cat_name" placeholder="GAS, MILK, etc." class="flex-1 p-2 border rounded-xl text-xs outline-none focus:ring-2 focus:ring-red-400">
                <button name="add_cat" class="bg-slate-800 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase">Add</button>
            </form>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-xl border-t-4 border-red-500">
            <h3 class="font-black mb-4 text-slate-800 uppercase text-xs"><?php echo $edit_data ? 'Update Entry' : 'New Expense Entry'; ?></h3>
            <form method="POST" class="space-y-4" x-data="{ loading: false }" @submit="loading = true">
                <input type="hidden" name="exp_id" value="<?php echo $edit_data['id'] ?? ''; ?>">
                <input type="date" name="date" value="<?php echo $edit_data['date'] ?? date('Y-m-d'); ?>" class="w-full p-3 border rounded-xl bg-slate-50 text-sm font-bold">
                
                <select name="cat_id" class="w-full p-3 border rounded-xl font-bold text-sm bg-white" required>
                    <option value="">-- Choose Vendor --</option>
                    <?php $categories->data_seek(0); while($c = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo (@$edit_data['category_id'] == $c['id']) ? 'selected' : ''; ?>><?php echo $c['category_name']; ?></option>
                    <?php endwhile; ?>
                </select>

                <select name="status" class="w-full p-3 border rounded-xl font-bold text-sm">
                    <option value="Paid" <?php echo (@$edit_data['status'] == 'Paid') ? 'selected' : ''; ?>>PAID (Instantly)</option>
                    <option value="Pending" <?php echo (@$edit_data['status'] == 'Pending') ? 'selected' : ''; ?>>PENDING (Credit)</option>
                </select>

                <input type="number" step="0.01" name="amount" placeholder="Amount ₹" value="<?php echo $edit_data['amount'] ?? ''; ?>" class="w-full p-3 border rounded-xl font-black text-2xl text-red-600 focus:ring-2 focus:ring-red-200 outline-none" required>
                <textarea name="note" placeholder="Note: e.g. 50 Ltrs Milk" class="w-full p-3 border rounded-xl h-24 text-sm outline-none focus:ring-2 focus:ring-red-200"><?php echo $edit_data['note'] ?? ''; ?></textarea>
                
                <button type="submit" name="save_expense" :disabled="loading" class="w-full bg-red-600 text-white font-black py-4 rounded-2xl shadow-lg uppercase tracking-widest text-xs hover:bg-red-700 transition-all disabled:opacity-60">
                    <span x-show="!loading">Save to Ledger</span>
                    <i x-show="loading" x-cloak class="fas fa-circle-notch fa-spin"></i>
                </button>
                <?php if($edit_data): ?>
                    <a href="expenses.php" class="block text-center text-[10px] font-black text-slate-400 mt-2 uppercase underline">Discard Changes</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="lg:col-span-8">
        
        <div class="bg-slate-900 p-6 rounded-[2rem] shadow-2xl mb-6 flex justify-between items-center border border-white/10">
            <div>
                <p class="text-[10px] font-black uppercase text-indigo-400 tracking-[0.2em] mb-1">Total Spent in View</p>
                <h2 class="text-4xl font-black text-white">₹<?php echo number_format($total_spent_val, 2); ?></h2>
                <p class="text-[9px] text-slate-400 font-bold uppercase mt-1">Period: <?php echo date('F Y', mktime(0,0,0,$filter_month, 1, $filter_year)); ?></p>
            </div>
            <div class="bg-red-500/10 p-4 rounded-2xl">
                <i class="fas fa-receipt text-3xl text-red-500"></i>
            </div>
        </div>

        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200 mb-6 space-y-4">
            <form method="GET" class="flex flex-wrap items-center gap-4 border-b pb-4">
                <input type="hidden" name="filter_cat" value="<?php echo $filter_cat; ?>">
                <span class="text-[10px] font-black uppercase text-slate-400">Select Period:</span>
                <select name="m" class="p-2 border rounded-xl text-xs font-bold bg-slate-50">
                    <?php for($i=1; $i<=12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($filter_month == $i) ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$i,1)); ?></option>
                    <?php endfor; ?>
                </select>
                <select name="y" class="p-2 border rounded-xl text-xs font-bold bg-slate-50">
                    <?php for($i=2024; $i<=2026; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($filter_year == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase">Filter Date</button>
            </form>

            <div class="flex flex-wrap gap-2">
                <a href="?m=<?php echo $filter_month; ?>&y=<?php echo $filter_year; ?>" class="px-4 py-2 rounded-xl text-[10px] font-black uppercase transition-all <?php echo ($filter_cat == 0) ? 'bg-red-600 text-white shadow-lg' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'; ?>">All Categories</a>
                <?php $categories->data_seek(0); while($c = $categories->fetch_assoc()): ?>
                    <a href="?filter_cat=<?php echo $c['id']; ?>&m=<?php echo $filter_month; ?>&y=<?php echo $filter_year; ?>" class="px-4 py-2 rounded-xl text-[10px] font-black uppercase transition-all <?php echo ($filter_cat == $c['id']) ? 'bg-red-600 text-white shadow-lg' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'; ?>">
                        <?php echo $c['category_name']; ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] shadow-xl overflow-hidden border border-slate-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-800 text-white text-[10px] uppercase font-black tracking-widest">
                        <tr>
                            <th class="p-5">Date</th>
                            <th class="p-5">Category</th>
                            <th class="p-5 text-right">Amount</th>
                            <th class="p-5 text-center">Status</th>
                            <th class="p-5 text-right no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-sm">
                        <?php if($expenses && $expenses->num_rows > 0): ?>
                            <?php while($row = $expenses->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50 transition-all <?php echo $row['entry_type'] == 'staff' ? 'bg-indigo-50/40' : ''; ?>">
                                <td class="p-5 text-slate-500 font-bold"><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                                <td class="p-5">
                                    <span class="px-3 py-1 rounded text-[9px] font-black uppercase <?php echo $row['entry_type'] == 'staff' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600'; ?>">
                                        <?php echo $row['category_name']; ?>
                                    </span>
                                    <?php if($row['note']): ?><p class="text-[10px] text-slate-400 mt-1 italic tracking-tight font-medium"><?php echo $row['note']; ?></p><?php endif; ?>
                                </td>
                                <td class="p-5 text-right font-black text-slate-900">₹<?php echo number_format($row['amount'], 2); ?></td>
                                <td class="p-5 text-center">
                                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase <?php echo $row['status'] == 'Pending' ? 'bg-orange-100 text-orange-600' : 'bg-green-100 text-green-600'; ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td class="p-5 text-right space-x-3 no-print">
                                    <?php if($row['entry_type'] == 'bill'): ?>
                                        <a href="?edit=<?php echo $row['id']; ?>&page=<?php echo $page . $filter_params; ?>" class="text-blue-500 hover:scale-110 inline-block"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $row['id']; ?>&page=<?php echo $page . $filter_params; ?>" class="text-red-300 hover:text-red-600" onclick="return confirm('Delete this bill?')"><i class="fas fa-trash-alt"></i></a>
                                    <?php else: ?>
                                        <a href="staff_khata.php?view_id=<?php echo $row['id']; ?>" class="text-indigo-400 font-black text-[9px] uppercase hover:underline">View Khata</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="p-16 text-center text-slate-400 font-bold italic">No records found for this period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if($total_pages > 1): ?>
            <div class="bg-slate-50 p-5 border-t border-slate-200 flex justify-center items-center gap-2 no-print">
                <?php if($page > 1): ?>
                    <a href="?page=<?php echo $page-1 . $filter_params; ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-[10px] font-black uppercase text-slate-500 hover:bg-slate-100">Prev</a>
                <?php endif; ?>

                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for($i = $start; $i <= $end; $i++): ?>
                    <a href="?page=<?php echo $i . $filter_params; ?>" class="w-10 h-10 flex items-center justify-center rounded-xl text-[10px] font-black transition-all <?php echo ($i == $page) ? 'bg-red-600 text-white shadow-lg' : 'bg-white text-slate-500 border border-slate-200 hover:bg-slate-100'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1 . $filter_params; ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-[10px] font-black uppercase text-slate-500 hover:bg-slate-100">Next</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>