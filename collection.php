<?php
session_start();
if (!isset($_SESSION['admin_user'])) { header("Location: login.php"); exit(); }
include 'db.php';

// 1. DELETE COLLECTION
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM transactions WHERE id=$id");
    header("Location: collection.php?status=deleted");
    exit();
}

// 2. FETCH DATA FOR EDITING
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit_data = $conn->query("SELECT * FROM transactions WHERE id=$id")->fetch_assoc();
}

// 3. SAVE OR UPDATE COLLECTION
if (isset($_POST['save_collection'])) {
    $date = !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d');
    $cash = (float)($_POST['cash'] ?: 0);
    $online = (float)($_POST['online'] ?: 0);
    $note = $conn->real_escape_string($_POST['note']);

    if (!empty($_POST['transaction_id'])) {
        // UPDATE Existing
        $id = (int)$_POST['transaction_id'];
        $sql = "UPDATE transactions SET date='$date', cash_sales='$cash', online_sales='$online', note='$note' WHERE id=$id";
    } else {
        // INSERT New
        $sql = "INSERT INTO transactions (date, cash_sales, online_sales, note) VALUES ('$date', '$cash', '$online', '$note')";
    }
    
    if($conn->query($sql)) {
        header("Location: collection.php?status=success");
        exit();
    }
}

// 4. FETCH TOTALS
$totals = $conn->query("SELECT SUM(cash_sales) as total_cash, SUM(online_sales) as total_online FROM transactions")->fetch_assoc();

// 5. FETCH RECENT HISTORY
$history = $conn->query("SELECT id, date, cash_sales, online_sales, note FROM transactions ORDER BY date DESC LIMIT 30");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Money Manager | Resto Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50">

    <nav class="bg-emerald-700 text-white p-4 shadow-xl flex justify-between items-center">
        <h1 class="font-bold uppercase tracking-widest"><i class="fas fa-cash-register mr-2"></i> Money Collection</h1>
        <a href="index.php" class="bg-emerald-800 px-4 py-2 rounded-xl text-xs font-bold transition-all hover:bg-emerald-900">Back to Dashboard</a>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-6xl">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
            <div class="bg-white p-6 rounded-3xl shadow-sm border-l-8 border-emerald-500">
                <p class="text-slate-400 text-[10px] font-black uppercase mb-1">Total Cash Collected</p>
                <h2 class="text-3xl font-black text-slate-800">₹<?php echo number_format($totals['total_cash'] ?? 0, 2); ?></h2>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border-l-8 border-blue-500">
                <p class="text-slate-400 text-[10px] font-black uppercase mb-1">Total Online (UPI)</p>
                <h2 class="text-3xl font-black text-slate-800">₹<?php echo number_format($totals['total_online'] ?? 0, 2); ?></h2>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <div class="lg:col-span-4">
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border <?php echo $edit_data ? 'border-orange-400' : 'border-slate-100'; ?> sticky top-24">
                    <h3 class="font-black text-slate-800 mb-6 uppercase text-sm tracking-widest">
                        <?php echo $edit_data ? 'Edit Entry' : 'Add Daily Income'; ?>
                    </h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="transaction_id" value="<?php echo $edit_data['id'] ?? ''; ?>">
                        
                        <div>
                            <label class="text-[10px] font-bold text-slate-400 uppercase">Collection Date</label>
                            <input type="date" name="date" value="<?php echo $edit_data['date'] ?? date('Y-m-d'); ?>" class="w-full p-3 bg-slate-50 border rounded-xl outline-none">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-emerald-600 uppercase">Cash Amount</label>
                            <input type="number" step="0.01" name="cash" value="<?php echo $edit_data['cash_sales'] ?? ''; ?>" placeholder="0.00" class="w-full p-4 bg-emerald-50 border border-emerald-100 rounded-2xl font-black text-xl text-emerald-700 outline-none">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-blue-600 uppercase">UPI / Online Amount</label>
                            <input type="number" step="0.01" name="online" value="<?php echo $edit_data['online_sales'] ?? ''; ?>" placeholder="0.00" class="w-full p-4 bg-blue-50 border border-blue-100 rounded-2xl font-black text-xl text-blue-700 outline-none">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-slate-400 uppercase">Remark / Note</label>
                            <textarea name="note" placeholder="E.g. Lunch Peak, Sunday Special" class="w-full p-3 bg-slate-50 border rounded-xl h-20 text-sm"><?php echo $edit_data['note'] ?? ''; ?></textarea>
                        </div>
                        
                        <button type="submit" name="save_collection" class="w-full <?php echo $edit_data ? 'bg-orange-500 hover:bg-orange-600' : 'bg-emerald-600 hover:bg-emerald-700'; ?> text-white font-black py-4 rounded-2xl shadow-lg transition-all uppercase tracking-widest text-xs">
                            <?php echo $edit_data ? 'Update Entry' : 'Save Collection'; ?>
                        </button>

                        <?php if($edit_data): ?>
                            <a href="collection.php" class="block text-center text-xs text-slate-400 mt-2 underline font-bold uppercase">Cancel Edit</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-8">
                <div class="bg-white rounded-[2rem] shadow-xl overflow-hidden border border-slate-100">
                    <div class="bg-slate-800 p-5 text-white text-xs font-black uppercase tracking-widest flex justify-between">
                        Recent Collections Log
                        <i class="fas fa-history opacity-50"></i>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase">
                                <tr>
                                    <th class="p-5">Date</th>
                                    <th class="p-5">Cash</th>
                                    <th class="p-5">Online</th>
                                    <th class="p-5">Daily Total</th>
                                    <th class="p-5">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-sm">
                                <?php while($row = $history->fetch_assoc()): ?>
                                <tr class="<?php echo (isset($_GET['edit']) && $_GET['edit'] == $row['id']) ? 'bg-orange-50' : 'hover:bg-slate-50'; ?> transition-colors">
                                    <td class="p-5 font-bold text-slate-600"><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                                    <td class="p-5 text-emerald-600 font-bold">₹<?php echo number_format($row['cash_sales'], 2); ?></td>
                                    <td class="p-5 text-blue-600 font-bold">₹<?php echo number_format($row['online_sales'], 2); ?></td>
                                    <td class="p-5 font-black text-slate-900 bg-slate-50/50">₹<?php echo number_format($row['cash_sales'] + $row['online_sales'], 2); ?></td>
                                    <td class="p-5 space-x-3">
                                        <a href="?edit=<?php echo $row['id']; ?>" class="text-blue-500 hover:text-blue-700"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $row['id']; ?>" class="text-slate-300 hover:text-red-500" onclick="return confirm('Delete this collection entry?')"><i class="fas fa-trash-alt"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>