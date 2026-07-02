<?php
/**
 * Project: Simple Enterprise Management Suite
 * Feature: Data Shield v2.5 (Backup & Recovery with History)
 */
session_start();
include 'db.php';
if (!isset($_SESSION['admin_user'])) { header("Location: login.php"); exit(); }

$msg = "";
$status = "";

function sems_settings_column_exists_dbm(mysqli $conn, $column) {
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM global_settings LIKE '$column'");
    return $result && $result->num_rows > 0;
}

function sems_build_backup_sql(mysqli $conn, array $all_tables, $mode, $start_date, $end_date) {
    $sql_dump = "-- SIMPLE EMS ENTERPRISE BACKUP\n-- Mode: " . strtoupper($mode) . "\n-- Timestamp: " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

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

    return $sql_dump;
}

// --- 1. FETCH METADATA (Backup & Restore History) ---
$meta = $conn->query("SELECT * FROM global_settings WHERE id=1")->fetch_assoc();
$brand_name = !empty($meta['hotel_name']) ? $meta['hotel_name'] : 'Simple EMS';

// Format dates for display
$last_backup_display = (!empty($meta['last_backup'])) ? date('d M Y | h:i A', strtotime($meta['last_backup'])) : "NO HISTORY FOUND";
$last_restore_display = (!empty($meta['last_restore'])) ? date('d M Y | h:i A', strtotime($meta['last_restore'])) : "NO HISTORY FOUND";
$last_remote_backup_display = (!empty($meta['last_remote_backup'])) ? date('d M Y | h:i A', strtotime($meta['last_remote_backup'])) : "NO HISTORY FOUND";

$remote_sync_ready = sems_settings_column_exists_dbm($conn, 'remote_sync_enabled');
$remote_sync_enabled = $remote_sync_ready && !empty($meta['remote_sync_enabled']);
$remote_sync_url = $meta['remote_sync_url'] ?? '';
$remote_sync_api_key = $meta['remote_sync_api_key'] ?? '';
$remote_sync_last_status = $meta['remote_sync_last_status'] ?? '';

// Get table list
$all_tables = [];
$table_res = $conn->query("SHOW TABLES");
while ($t = $table_res->fetch_row()) { $all_tables[] = $t[0]; }

// --- 2. BACKUP ENGINE ---
if (isset($_POST['run_backup'])) {
    $mode = $_POST['backup_mode'] ?? 'full';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';

    $sql_dump = sems_build_backup_sql($conn, $all_tables, $mode, $start_date, $end_date);

    // UPDATE HISTORY LOG
    $now = date('Y-m-d H:i:s');
    $conn->query("UPDATE global_settings SET last_backup = '$now' WHERE id=1");

    header('Content-Type: application/octet-stream');
    header("Content-disposition: attachment; filename=\"SEMS_".strtoupper($mode)."_" . date('Ymd_Hi') . ".sql\"");
    echo $sql_dump; exit;
}

// --- 2b. REMOTE SYNC ENGINE ---
if (isset($_POST['run_sync'])) {
    if (!$remote_sync_ready || !$remote_sync_enabled || empty($remote_sync_url)) {
        $msg = "Remote Cloud Sync is not configured. Enable it in Settings first.";
        $status = "error";
    } else {
        $sql_dump = sems_build_backup_sql($conn, $all_tables, 'full', '', '');

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/sql\r\nAuthorization: Bearer {$remote_sync_api_key}\r\n",
                'content' => $sql_dump,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($remote_sync_url, false, $context);
        $http_status = 0;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header_line) {
                if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header_line, $m)) { $http_status = (int)$m[1]; }
            }
        }

        $now = date('Y-m-d H:i:s');
        if ($result !== false && $http_status >= 200 && $http_status < 300) {
            $status_text = "Success: " . $now;
            $conn->query("UPDATE global_settings SET remote_sync_last_status = '" . $conn->real_escape_string($status_text) . "', last_remote_backup = '$now' WHERE id=1");
            $msg = "Remote Cloud Sync completed successfully.";
            $status = "success";
        } else {
            $reason = $http_status > 0 ? "HTTP $http_status" : "connection failed";
            $status_text = "Failed: $reason ($now)";
            $conn->query("UPDATE global_settings SET remote_sync_last_status = '" . $conn->real_escape_string($status_text) . "' WHERE id=1");
            $msg = "Remote Cloud Sync failed ($reason). Check the URL and API key in Settings.";
            $status = "error";
        }
        $last_remote_backup_display = (!empty($now) && $status === 'success') ? date('d M Y | h:i A', strtotime($now)) : $last_remote_backup_display;
    }
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
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="pwa-register.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #020617; background-image: radial-gradient(circle at 50% -20%, #1e1b4b 0%, #020617 100%); }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .neon-border { border: 1px solid rgba(99, 102, 241, 0.2); box-shadow: 0 0 20px rgba(99, 102, 241, 0.1); }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 text-slate-300">

    <div class="max-w-6xl w-full" x-data="{ show: false }" x-init="setTimeout(() => show = true, 50)" x-show="show" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="text-center mb-10">
            <h1 class="text-3xl sm:text-5xl font-black italic tracking-tighter text-white uppercase flex items-center justify-center gap-4">
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
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="fixed top-6 right-6 z-[100] max-w-sm p-4 rounded-2xl text-[10px] font-black uppercase text-center shadow-2xl <?php echo $status == 'success' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'; ?>">
                <i class="fas fa-info-circle mr-2"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">

            <div class="glass p-10 rounded-[3.5rem] relative overflow-hidden flex flex-col justify-between neon-border">
                <div>
                    <div class="flex items-center justify-between mb-8">
                        <div class="w-12 h-12 bg-indigo-500/20 rounded-xl flex items-center justify-center border border-indigo-500/30">
                            <i class="fas fa-file-export text-indigo-400"></i>
                        </div>
                        <span class="text-[9px] font-black text-indigo-400 uppercase tracking-widest">Backup Engine</span>
                    </div>
                    
                    <h2 class="text-2xl font-black text-white uppercase mb-4 tracking-tighter">Export Archive</h2>

                    <form method="POST" x-data="{ loading: false }" @submit="loading = true">
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

                        <button type="submit" name="run_backup" :disabled="loading" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-black py-5 rounded-2xl shadow-lg transition-all uppercase tracking-[0.2em] text-[10px] disabled:opacity-60">
                            <span x-show="!loading">Download Secured Vault</span>
                            <i x-show="loading" x-cloak class="fas fa-circle-notch fa-spin"></i>
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

                    <form method="POST" enctype="multipart/form-data" x-data="{ loading: false }" @submit="loading = true">
                        <input type="file" name="sql_file" id="sql_file" class="hidden" accept=".sql" required>
                        <label for="sql_file" class="block cursor-pointer bg-white/5 border-2 border-dashed border-slate-800 rounded-[2rem] p-8 text-center hover:border-emerald-500/40 transition-all mb-8">
                            <i class="fas fa-cloud-upload-alt text-slate-600 text-4xl mb-4"></i>
                            <p id="file-display-name" class="text-[9px] font-black text-slate-500 uppercase tracking-widest leading-loose">
                                Drag & Drop Backup File<br><span class="text-slate-700">Only .sql files supported</span>
                            </p>
                        </label>

                        <button type="submit" name="run_restore" :disabled="loading" onclick="return confirm('CRITICAL: This will replace your entire database. Are you sure?')" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-black py-5 rounded-2xl shadow-lg transition-all uppercase tracking-[0.2em] text-[10px] disabled:opacity-60">
                            <span x-show="!loading">Begin System Recovery</span>
                            <i x-show="loading" x-cloak class="fas fa-circle-notch fa-spin"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="glass p-10 rounded-[3.5rem] relative overflow-hidden flex flex-col justify-between border-sky-500/10">
                <div>
                    <div class="flex items-center justify-between mb-8">
                        <div class="w-12 h-12 bg-sky-500/20 rounded-xl flex items-center justify-center border border-sky-500/30">
                            <i class="fas fa-cloud-arrow-up text-sky-400"></i>
                        </div>
                        <span class="text-[9px] font-black text-sky-400 uppercase tracking-widest">Remote Sync Engine</span>
                    </div>

                    <h2 class="text-2xl font-black text-white uppercase mb-4 tracking-tighter">Cloud Sync</h2>

                    <?php if (!$remote_sync_ready || !$remote_sync_enabled || empty($remote_sync_url)): ?>
                        <div class="bg-amber-500/10 border border-amber-500/20 text-amber-400 p-4 rounded-2xl text-[9px] font-black uppercase mb-6 leading-5">
                            <?php if (!$remote_sync_ready): ?>
                                Run <a href="update.php" class="underline">update.php</a> once to enable Remote Cloud Sync.
                            <?php else: ?>
                                Not configured. Set your sync URL and API key in <a href="settings.php" class="underline">Settings</a>.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-white/5 border border-white/10 rounded-2xl p-4 mb-6 space-y-2">
                            <p class="text-[8px] font-bold text-slate-500 uppercase tracking-widest">Last Sync Status</p>
                            <p class="text-xs font-black text-white truncate"><?php echo htmlspecialchars($remote_sync_last_status ?: 'Never synced'); ?></p>
                            <p class="text-[8px] font-bold text-slate-500 uppercase tracking-widest pt-2">Last Remote Backup</p>
                            <p class="text-xs font-black text-white"><?php echo $last_remote_backup_display; ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="POST" x-data="{ loading: false }" @submit="loading = true">
                        <button type="submit" name="run_sync" :disabled="loading" <?php echo (!$remote_sync_ready || !$remote_sync_enabled || empty($remote_sync_url)) ? 'disabled' : ''; ?> class="w-full bg-sky-600 hover:bg-sky-500 text-white font-black py-5 rounded-2xl shadow-lg transition-all uppercase tracking-[0.2em] text-[10px] disabled:opacity-40 disabled:cursor-not-allowed">
                            <span x-show="!loading">Sync Now</span>
                            <i x-show="loading" x-cloak class="fas fa-circle-notch fa-spin"></i>
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
