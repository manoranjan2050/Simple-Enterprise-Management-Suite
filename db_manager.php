<?php
/**
 * Project: Resto Pro ERP - Intelligence Module
 * Feature: Data Shield v2.5 (Backup & Recovery with History)
 */
session_start();
include 'db.php';
if (!isset($_SESSION['admin_user'])) { header("Location: login.php"); exit(); }

$msg = "";
$status = "";

// --- 1. FETCH METADATA (Backup & Restore History) ---
$meta = $conn->query("SELECT hotel_name, last_backup, last_restore FROM global_settings WHERE id=1")->fetch_assoc();
$brand_name = !empty($meta['hotel_name']) ? $meta['hotel_name'] : 'Resto ERP';

// Format dates for display
$last_backup_display = (!empty($meta['last_backup'])) ? date('d M Y | h:i A', strtotime($meta['last_backup'])) : "NO HISTORY FOUND";
$last_restore_display = (!empty($meta['last_restore'])) ? date('d M Y | h:i A', strtotime($meta['last_restore'])) : "NO HISTORY FOUND";

// Get table list
$all_tables = [];
$table_res = $conn->query("SHOW TABLES");
while ($t = $table_res->fetch_row()) { $all_tables[] = $t[0]; }

// --- 2. BACKUP ENGINE ---
if (isset($_POST['run_backup'])) {
    $mode = $_POST['backup_mode'] ?? 'full';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    
    $sql_dump = "-- RESTO PRO ENTERPRISE BACKUP\n-- Mode: " . strtoupper($mode) . "\n-- Timestamp: " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($all_tables as $table) {
        $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
        $res = $conn->query("SHOW CREATE TABLE `$table` ");
        $row = $res->fetch_row();
        $sql_dump .= $row[1] . ";\n\n";

        $query = "SELECT * FROM `$table` ";
        $date_tables = ['transactions', 'expense_details', 'staff_ledger', 'orders'];
        
        if ($mode == 'custom' && in_array($table, $date_tables) && !empty($start_date) && !empty($end_date)) {
            $query .= " WHERE date BETWEEN '$start_date' AND '$end_date'";
        }

        $res = $conn->query($query);
        while ($row = $res->fetch_assoc()) {
            $sql_dump .= "INSERT INTO `$table` VALUES(";
            $values = array_map(function($v) use ($conn) { 
                if ($v === null) return "NULL";
                return "'" . $conn->real_escape_string($v) . "'"; 
            }, array_values($row));
            $sql_dump .= implode(",", $values) . ");\n";
        }
        $sql_dump .= "\n";
    }
    $sql_dump .= "SET FOREIGN_KEY_CHECKS = 1;";
    
    // UPDATE HISTORY LOG
    $now = date('Y-m-d H:i:s');
    $conn->query("UPDATE global_settings SET last_backup = '$now' WHERE id=1");

    header('Content-Type: application/octet-stream');
    header("Content-disposition: attachment; filename=\"RESTO_".strtoupper($mode)."_" . date('Ymd_Hi') . ".sql\"");
    echo $sql_dump; exit;
}

// --- 3. RESTORE ENGINE ---
if (isset($_POST['run_restore']) && isset($_FILES['sql_file'])) {
    $file = $_FILES['sql_file']['tmp_name'];
    if (file_exists($file)) {
        $lines = file($file);
        $query = '';
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || substr($line, 0, 2) == '--' || substr($line, 0, 1) == '#') continue;
            $query .= $line;
            if (substr($line, -1) == ';') {
                if(!$conn->query($query)) { $msg = "Critical Error: " . $conn->error; $status = "error"; break; }
                $query = '';
            }
        }
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        if ($status != "error") { 
            $now = date('Y-m-d H:i:s');
            $conn->query("UPDATE global_settings SET last_restore = '$now' WHERE id=1");
            header("Location: db_manager.php?status=restored"); // Refresh to show new date
            exit();
        }
    }
}

if(isset($_GET['status']) && $_GET['status'] == 'restored'){
    $msg = "System Restore Successful. History Updated.";
    $status = "success";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Data Shield v2.5 | <?php echo $brand_name; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #020617; background-image: radial-gradient(circle at 50% -20%, #1e1b4b 0%, #020617 100%); }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .neon-border { border: 1px solid rgba(99, 102, 241, 0.2); box-shadow: 0 0 20px rgba(99, 102, 241, 0.1); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 text-slate-300">

    <div class="max-w-6xl w-full">
        <div class="text-center mb-10">
            <h1 class="text-5xl font-black italic tracking-tighter text-white uppercase flex items-center justify-center gap-4">
                <i class="fas fa-shield-virus text-indigo-500"></i> DATA <span class="text-indigo-500">SHIELD</span>
            </h1>
            
            <div class="flex flex-wrap justify-center gap-4 mt-6">
                <div class="glass px-6 py-3 rounded-2xl border-l-4 border-indigo-500">
                    <p class="text-[8px] font-bold text-slate-500 uppercase tracking-widest mb-1">Last Backup Timestamp</p>
                    <p class="text-xs font-black text-white"><?php echo $last_backup_display; ?></p>
                </div>
                <div class="glass px-6 py-3 rounded-2xl border-l-4 border-emerald-500">
                    <p class="text-[8px] font-bold text-slate-500 uppercase tracking-widest mb-1">Last Restore Timestamp</p>
                    <p class="text-xs font-black text-white"><?php echo $last_restore_display; ?></p>
                </div>
            </div>
        </div>

        <?php if($msg): ?>
            <div class="mb-8 p-4 rounded-2xl text-[10px] font-black uppercase text-center <?php echo $status == 'success' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'; ?>">
                <i class="fas fa-info-circle mr-2"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            
            <div class="glass p-10 rounded-[3.5rem] relative overflow-hidden flex flex-col justify-between neon-border">
                <div>
                    <div class="flex items-center justify-between mb-8">
                        <div class="w-12 h-12 bg-indigo-500/20 rounded-xl flex items-center justify-center border border-indigo-500/30">
                            <i class="fas fa-file-export text-indigo-400"></i>
                        </div>
                        <span class="text-[9px] font-black text-indigo-400 uppercase tracking-widest">Backup Engine</span>
                    </div>
                    
                    <h2 class="text-2xl font-black text-white uppercase mb-4 tracking-tighter">Export Archive</h2>
                    
                    <form method="POST">
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <label class="cursor-pointer group">
                                <input type="radio" name="backup_mode" value="full" checked class="hidden peer" onclick="toggleDates(false)">
                                <div class="peer-checked:bg-indigo-600 peer-checked:text-white bg-white/5 border border-white/10 p-4 rounded-2xl text-center transition-all group-hover:border-indigo-500">
                                    <p class="text-[9px] font-black uppercase">Full System</p>
                                </div>
                            </label>
                            <label class="cursor-pointer group">
                                <input type="radio" name="backup_mode" value="custom" class="hidden peer" onclick="toggleDates(true)">
                                <div class="peer-checked:bg-indigo-600 peer-checked:text-white bg-white/5 border border-white/10 p-4 rounded-2xl text-center transition-all group-hover:border-indigo-500">
                                    <p class="text-[9px] font-black uppercase">Custom Range</p>
                                </div>
                            </label>
                        </div>

                        <div id="date_selectors" class="hidden space-y-4 mb-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-[8px] font-bold text-slate-500 uppercase ml-2">Start</label>
                                    <input type="date" name="start_date" class="w-full bg-slate-900 border border-white/10 rounded-xl p-3 text-xs outline-none focus:border-indigo-500">
                                </div>
                                <div>
                                    <label class="text-[8px] font-bold text-slate-500 uppercase ml-2">End</label>
                                    <input type="date" name="end_date" class="w-full bg-slate-900 border border-white/10 rounded-xl p-3 text-xs outline-none focus:border-indigo-500">
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="run_backup" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-black py-5 rounded-2xl shadow-lg transition-all uppercase tracking-[0.2em] text-[10px]">
                            Download Secured Vault
                        </button>
                    </form>
                </div>
            </div>

            <div class="glass p-10 rounded-[3.5rem] relative overflow-hidden flex flex-col justify-between border-emerald-500/10">
                <div>
                    <div class="flex items-center justify-between mb-8">
                        <div class="w-12 h-12 bg-emerald-500/20 rounded-xl flex items-center justify-center border border-emerald-500/30">
                            <i class="fas fa-file-import text-emerald-400"></i>
                        </div>
                        <span class="text-[9px] font-black text-emerald-400 uppercase tracking-widest">Recovery Engine</span>
                    </div>

                    <h2 class="text-2xl font-black text-white uppercase mb-4 tracking-tighter">Restore Point</h2>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="file" name="sql_file" id="sql_file" class="hidden" accept=".sql" required>
                        <label for="sql_file" class="block cursor-pointer bg-white/5 border-2 border-dashed border-slate-800 rounded-[2rem] p-8 text-center hover:border-emerald-500/40 transition-all mb-8">
                            <i class="fas fa-cloud-upload-alt text-slate-600 text-4xl mb-4"></i>
                            <p id="file-display-name" class="text-[9px] font-black text-slate-500 uppercase tracking-widest leading-loose">
                                Drag & Drop Backup File<br><span class="text-slate-700">Only .sql files supported</span>
                            </p>
                        </label>

                        <button type="submit" name="run_restore" onclick="return confirm('CRITICAL: This will replace your entire database. Are you sure?')" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-black py-5 rounded-2xl shadow-lg transition-all uppercase tracking-[0.2em] text-[10px]">
                            Begin System Recovery
                        </button>
                    </form>
                </div>
            </div>

        </div>

        <div class="mt-12 text-center">
            <a href="index.php" class="inline-flex items-center gap-3 text-[10px] font-black text-slate-500 hover:text-white uppercase tracking-[0.3em] transition-all">
                <i class="fas fa-arrow-left"></i> Exit to Intelligence Dashboard
            </a>
        </div>
    </div>

    <script>
        function toggleDates(show) {
            const div = document.getElementById('date_selectors');
            div.style.display = show ? 'block' : 'none';
        }

        document.getElementById('sql_file').addEventListener('change', function() {
            if (this.files[0]) {
                const label = document.getElementById('file-display-name');
                label.innerHTML = `<span class="text-emerald-400 italic">Target File:</span><br>${this.files[0].name}`;
            }
        });
    </script>
</body>
</html>
