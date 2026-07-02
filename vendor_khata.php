<?php
session_start();
include 'db.php';
if (!isset($_SESSION['admin_user'])) { header("Location: login.php"); exit(); }

// 1. Record a Payment to a Vendor
if (isset($_POST['pay_vendor'])) {
    $cat_id = $_POST['cat_id'];
    $amount = (float)$_POST['amount'];
    $date = date('Y-m-d');
    $note = "Payment made to clear dues";
    
    // We record a negative expense to reduce the pending balance
    $conn->query("INSERT INTO expense_details (date, category_id, amount, note, status) VALUES ('$date', '$cat_id', '-$amount', '$note', 'Paid')");
    header("Location: vendor_khata.php"); exit();
}

// 2. Fetch all Vendors and their Pending Balances
$vendors = $conn->query("SELECT 
    c.id, 
    c.category_name as vendor_name, 
    c.vendor_mobile,
    SUM(CASE WHEN e.status = 'Pending' THEN e.amount ELSE 0 END) as total_pending,
    SUM(CASE WHEN e.status = 'Paid' AND e.amount < 0 THEN ABS(e.amount) ELSE 0 END) as total_paid_back
    FROM expense_categories c
    LEFT JOIN expense_details e ON c.id = e.category_id
    GROUP BY c.id");

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
    <title>Vendor Khata | Credit Tracker</title>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-slate-100 pb-10">

<nav class="bg-slate-900 text-white p-3 sm:p-4 shadow-lg" x-data="{ mobileOpen: false }">
    <div class="flex justify-between items-center">
        <h1 class="font-bold uppercase text-sm tracking-widest"><i class="fas fa-truck mr-2 text-orange-400"></i> Vendor Credit Manager</h1>
        <div class="hidden sm:flex items-center">
            <a href="index.php" class="bg-slate-700 px-4 py-2 rounded-xl text-xs">Back to Dashboard</a>
        </div>
        <button @click="mobileOpen = !mobileOpen" class="sm:hidden p-2 bg-slate-700 rounded-xl">
            <i class="fas" :class="mobileOpen ? 'fa-xmark' : 'fa-bars'"></i>
        </button>
    </div>
    <div x-show="mobileOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="sm:hidden mt-4 pb-2 flex flex-col gap-2 border-t border-slate-700 pt-4">
        <a href="index.php" class="p-3 bg-slate-700 rounded-xl text-[10px] font-black uppercase text-center"><i class="fas fa-arrow-left mr-2"></i>Back to Dashboard</a>
    </div>
</nav>

<div class="container mx-auto px-4 mt-8" x-data="{ show: false }" x-init="setTimeout(() => show = true, 50)" x-show="show" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <div class="lg:col-span-8">
            <div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-slate-200">
                <div class="p-6 bg-slate-50 border-b flex justify-between items-center">
                    <h2 class="font-black text-slate-700 uppercase text-xs">Supplier Balances (Udhaar)</h2>
                    <span class="text-[10px] bg-orange-100 text-orange-600 px-2 py-1 rounded-full font-bold">Manage Dues</span>
                </div>
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-[10px] uppercase text-slate-400 font-bold">
                        <tr>
                            <th class="p-6">Vendor Name</th>
                            <th class="p-6">Total Dues</th>
                            <th class="p-6">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php while($v = $vendors->fetch_assoc()): 
                            $balance = $v['total_pending'] - $v['total_paid_back'];
                        ?>
                        <tr class="hover:bg-slate-50 transition-all">
                            <td class="p-6">
                                <p class="font-bold text-slate-800"><?php echo $v['vendor_name']; ?></p>
                                <p class="text-xs text-slate-400"><?php echo $v['vendor_mobile'] ?: 'No Mobile'; ?></p>
                            </td>
                            <td class="p-6">
                                <span class="text-xl font-black <?php echo $balance > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                    ₹<?php echo number_format($balance, 2); ?>
                                </span>
                            </td>
                            <td class="p-6">
                                <button onclick="openPaymentModal('<?php echo $v['id']; ?>', '<?php echo $v['vendor_name']; ?>', '<?php echo $balance; ?>')" class="bg-slate-800 text-white text-[10px] font-bold px-4 py-2 rounded-lg hover:bg-slate-700">PAY VENDOR</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="lg:col-span-4 space-y-6">
            <div class="bg-indigo-900 text-white p-8 rounded-3xl shadow-2xl relative overflow-hidden">
                <i class="fas fa-info-circle absolute -right-4 -bottom-4 text-8xl opacity-10"></i>
                <h3 class="font-bold text-lg mb-4">Back Office Tip</h3>
                <p class="text-sm opacity-80 leading-relaxed">
                    Always mark daily supplies (Milk/Paneer) as <b>'Pending'</b> in the Expenses page. 
                    Once you pay the vendor at the end of the week, use this page to record the payment. 
                    The balance will automatically update.
                </p>
            </div>
        </div>
    </div>
</div>

<div id="paymentModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-white p-8 rounded-3xl shadow-2xl w-full max-w-md">
        <h3 id="modalTitle" class="text-xl font-black mb-6 uppercase text-slate-800">Clear Payment</h3>
        <form method="POST" x-data="{ loading: false }" @submit="loading = true">
            <input type="hidden" name="cat_id" id="modal_cat_id">
            <div class="space-y-4">
                <div>
                    <label class="text-xs font-bold text-slate-400">AMOUNT TO PAY</label>
                    <input type="number" name="amount" id="modal_amount" class="w-full p-4 bg-slate-50 border rounded-2xl text-2xl font-black outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <button type="submit" name="pay_vendor" :disabled="loading" class="w-full bg-indigo-600 text-white font-black py-4 rounded-2xl shadow-lg disabled:opacity-60">
                    <span x-show="!loading">CONFIRM PAYMENT</span>
                    <i x-show="loading" x-cloak class="fas fa-circle-notch fa-spin"></i>
                </button>
                <button type="button" onclick="closeModal()" class="w-full text-slate-400 text-xs font-bold uppercase">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPaymentModal(id, name, balance) {
    document.getElementById('modal_cat_id').value = id;
    document.getElementById('modalTitle').innerText = 'Pay ' + name;
    document.getElementById('modal_amount').value = balance;
    document.getElementById('paymentModal').classList.remove('hidden');
}
function closeModal() {
    document.getElementById('paymentModal').classList.add('hidden');
}
</script>

</body>
</html>