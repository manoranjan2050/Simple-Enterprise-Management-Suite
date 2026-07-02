<?php
/**
 * Project: Simple Enterprise Management Suite
 * Author: MANORANJAN
 * Website: https://manoranjan.dev/
 */

session_start();
include 'db.php';
if (!isset($_SESSION['admin_user'])) { header("Location: login.php"); exit(); }

$msg = "";
$status = "";

function sems_settings_column_exists(mysqli $conn, $column) {
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM global_settings LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// 1. UPDATE SETTINGS LOGIC
if (isset($_POST['update_settings'])) {
    $name = $conn->real_escape_string($_POST['hotel_name']);
    $addr = $conn->real_escape_string($_POST['hotel_address']);
    $phone = $conn->real_escape_string($_POST['hotel_mobile']);
    $lic = $conn->real_escape_string($_POST['license_no']);
    $curr = $conn->real_escape_string($_POST['currency_symbol']);
    $email = $conn->real_escape_string($_POST['hotel_email']);
    $telegram_enabled = isset($_POST['telegram_enabled']) ? 1 : 0;
    $telegram_bot_token = $conn->real_escape_string(trim($_POST['telegram_bot_token'] ?? ''));
    $telegram_allowed_chat_ids = $conn->real_escape_string(trim($_POST['telegram_allowed_chat_ids'] ?? ''));

    $telegram_sql = "";
    if (sems_settings_column_exists($conn, 'telegram_enabled')) {
        $telegram_sql = ",
            telegram_enabled='$telegram_enabled',
            telegram_bot_token='$telegram_bot_token',
            telegram_allowed_chat_ids='$telegram_allowed_chat_ids'";
    }

    $remote_sync_enabled = isset($_POST['remote_sync_enabled']) ? 1 : 0;
    $remote_sync_url = $conn->real_escape_string(trim($_POST['remote_sync_url'] ?? ''));
    $remote_sync_api_key = $conn->real_escape_string(trim($_POST['remote_sync_api_key'] ?? ''));

    $remote_sync_sql = "";
    if (sems_settings_column_exists($conn, 'remote_sync_enabled')) {
        $remote_sync_sql = ",
            remote_sync_enabled='$remote_sync_enabled',
            remote_sync_url='$remote_sync_url',
            remote_sync_api_key='$remote_sync_api_key'";
    }

    $sql = "UPDATE global_settings SET
            hotel_name='$name',
            hotel_address='$addr',
            hotel_mobile='$phone',
            hotel_email='$email',
            license_no='$lic',
            currency_symbol='$curr'
            $telegram_sql
            $remote_sync_sql
            WHERE id=1";
    
    if($conn->query($sql)) {
        $msg = "Configuration saved successfully!";
        $status = "success";
    } else {
        $msg = "Error: " . $conn->error;
        $status = "error";
    }
}

// 2. FETCH CURRENT SETTINGS
$set = $conn->query("SELECT * FROM global_settings WHERE id=1")->fetch_assoc();
$telegram_ready = sems_settings_column_exists($conn, 'telegram_enabled');
$remote_sync_ready = sems_settings_column_exists($conn, 'remote_sync_enabled');
$last_remote_backup_display = (!empty($set['last_remote_backup'])) ? date('d M Y | h:i A', strtotime($set['last_remote_backup'])) : 'Never synced';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings & Branding | Simple EMS</title>
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
        body { background: #f8fafc; }
        .glass { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); }
        .dev-badge { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="pb-10">

<nav class="bg-slate-900 text-white p-4 sm:p-6 shadow-xl flex justify-between items-center no-print" x-data="{ mobileOpen: false }">
    <div class="flex items-center gap-4">
        <div class="w-10 h-10 bg-indigo-500 rounded-xl flex items-center justify-center">
            <i class="fas fa-cog animate-spin-slow"></i>
        </div>
        <div>
            <h1 class="font-black uppercase text-xs sm:text-sm tracking-widest">Control Panel</h1>
            <p class="text-[9px] text-slate-400 font-bold tracking-[0.3em]">Simple Enterprise Management Suite v2.5</p>
        </div>
    </div>

    <div class="hidden sm:flex items-center">
        <a href="index.php" class="text-[10px] bg-white/10 px-5 py-2 rounded-full font-black uppercase tracking-widest hover:bg-white/20 transition-all">
            <i class="fas fa-arrow-left mr-2"></i> Dashboard
        </a>
    </div>

    <button @click="mobileOpen = !mobileOpen" class="sm:hidden p-2 bg-white/10 rounded-xl">
        <i class="fas" :class="mobileOpen ? 'fa-xmark' : 'fa-bars'"></i>
    </button>

    <div x-show="mobileOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="sm:hidden absolute top-full left-0 right-0 bg-slate-900 border-t border-slate-700 mt-0 p-4 flex flex-col gap-2 z-50">
        <a href="index.php" class="p-3 bg-white/10 rounded-xl text-[10px] font-black uppercase"><i class="fas fa-arrow-left mr-2"></i>Dashboard</a>
        <a href="profile.php" class="p-3 bg-white/10 rounded-xl text-[10px] font-black uppercase"><i class="fas fa-user mr-2"></i>Edit Profile</a>
        <a href="logout.php" class="p-3 bg-red-500/10 text-red-500 border border-red-500/20 rounded-xl text-[10px] font-black uppercase"><i class="fas fa-right-from-bracket mr-2"></i>Logout</a>
    </div>
</nav>

<div class="container mx-auto px-4 mt-10" x-data="{ show: false }" x-init="setTimeout(() => show = true, 50)" x-show="show" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
    <div class="max-w-4xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-8">

        <div class="lg:col-span-8">
            <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 overflow-hidden">
                <div class="p-8 border-b border-slate-50 flex justify-between items-center">
                    <h3 class="font-black text-slate-800 uppercase text-xs tracking-widest">Business Identity</h3>
                    <i class="fas fa-building text-slate-200 text-2xl"></i>
                </div>

                <?php if($msg): ?>
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                         class="fixed top-6 right-6 z-[100] max-w-sm p-4 rounded-2xl text-[10px] font-black uppercase text-center border shadow-2xl <?php echo $status == 'success' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-red-50 text-red-600 border-red-100'; ?>">
                        <?php echo $msg; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="p-8 grid grid-cols-1 md:grid-cols-2 gap-6" x-data="{ loading: false }" @submit="loading = true">
                    <div class="md:col-span-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Business / Organization Name</label>
                        <input type="text" name="hotel_name" value="<?php echo $set['hotel_name']; ?>" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold mt-1 focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Official Address</label>
                        <textarea name="hotel_address" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl font-medium mt-1 h-24 outline-none"><?php echo $set['hotel_address']; ?></textarea>
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Contact Number</label>
                        <input type="text" name="hotel_mobile" value="<?php echo $set['hotel_mobile']; ?>" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold mt-1">
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Email Address</label>
                        <input type="email" name="hotel_email" value="<?php echo $set['hotel_email'] ?? ''; ?>" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold mt-1">
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase ml-2">License / GST Number</label>
                        <input type="text" name="license_no" value="<?php echo $set['license_no']; ?>" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold mt-1">
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Currency Symbol</label>
                        <input type="text" name="currency_symbol" value="<?php echo $set['currency_symbol']; ?>" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold mt-1">
                    </div>

                    <div class="md:col-span-2 pt-4">
                        <button type="submit" name="update_settings" :disabled="loading" class="w-full bg-indigo-600 text-white font-black py-5 rounded-3xl shadow-xl shadow-indigo-100 uppercase tracking-widest text-[11px] hover:bg-indigo-700 hover:-translate-y-1 transition-all active:scale-95 disabled:opacity-60">
                            <span x-show="!loading">Update Configuration</span>
                            <i x-show="loading" x-cloak class="fas fa-circle-notch fa-spin"></i>
                        </button>
                    </div>

                    <div class="md:col-span-2 border-t border-slate-100 pt-8 mt-2">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="font-black text-slate-800 uppercase text-xs tracking-widest">Telegram Integration</h3>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Fast reports and daily entry by bot</p>
                            </div>
                            <i class="fab fa-telegram-plane text-sky-500 text-3xl"></i>
                        </div>

                        <?php if(!$telegram_ready): ?>
                            <div class="bg-amber-50 border border-amber-100 text-amber-700 p-4 rounded-2xl text-[10px] font-black uppercase mb-6">
                                Run update.php once to enable Telegram fields for this old installation.
                            </div>
                        <?php endif; ?>

                        <label class="flex items-center gap-3 bg-slate-50 border border-slate-100 rounded-2xl p-4 mb-5">
                            <input type="checkbox" name="telegram_enabled" value="1" class="w-5 h-5 accent-indigo-600" <?php echo !empty($set['telegram_enabled']) ? 'checked' : ''; ?> <?php echo !$telegram_ready ? 'disabled' : ''; ?>>
                            <span class="text-xs font-black uppercase tracking-widest text-slate-600">Enable Telegram Bot</span>
                        </label>

                        <div class="grid grid-cols-1 gap-5">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Bot Token</label>
                                <input type="password" name="telegram_bot_token" value="<?php echo htmlspecialchars($set['telegram_bot_token'] ?? ''); ?>" placeholder="123456:ABC..." class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold mt-1" <?php echo !$telegram_ready ? 'disabled' : ''; ?>>
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Allowed Chat IDs</label>
                                <textarea name="telegram_allowed_chat_ids" placeholder="Example: 123456789, 987654321" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl font-medium mt-1 h-24 outline-none" <?php echo !$telegram_ready ? 'disabled' : ''; ?>><?php echo htmlspecialchars($set['telegram_allowed_chat_ids'] ?? ''); ?></textarea>
                                <p class="text-[9px] text-slate-400 font-bold mt-2 ml-2 uppercase">Send any message to the bot first; blocked reply will show your chat ID.</p>
                            </div>
                            <div class="bg-sky-50 border border-sky-100 rounded-2xl p-4 text-[10px] font-bold text-sky-700 leading-5">
                                Webhook URL: <span class="font-black"><?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'your-domain.com') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/telegram.php'); ?></span>
                            </div>
                            <button type="submit" name="update_settings" :disabled="loading" class="w-full bg-sky-600 text-white font-black py-5 rounded-3xl shadow-xl shadow-sky-100 uppercase tracking-widest text-[11px] hover:bg-slate-900 hover:-translate-y-1 transition-all active:scale-95 disabled:opacity-60">
                                <span x-show="!loading">Save Telegram Settings</span>
                                <i x-show="loading" x-cloak class="fas fa-circle-notch fa-spin"></i>
                            </button>
                        </div>
                    </div>

                    <div class="md:col-span-2 border-t border-slate-100 pt-8 mt-2">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="font-black text-slate-800 uppercase text-xs tracking-widest">Remote Cloud Sync</h3>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Auto-backup your database to your own server</p>
                            </div>
                            <i class="fas fa-cloud-arrow-up text-emerald-500 text-3xl"></i>
                        </div>

                        <?php if(!$remote_sync_ready): ?>
                            <div class="bg-amber-50 border border-amber-100 text-amber-700 p-4 rounded-2xl text-[10px] font-black uppercase mb-6">
                                Run update.php once to enable Remote Cloud Sync fields for this old installation.
                            </div>
                        <?php endif; ?>

                        <label class="flex items-center gap-3 bg-slate-50 border border-slate-100 rounded-2xl p-4 mb-5">
                            <input type="checkbox" name="remote_sync_enabled" value="1" class="w-5 h-5 accent-emerald-600" <?php echo !empty($set['remote_sync_enabled']) ? 'checked' : ''; ?> <?php echo !$remote_sync_ready ? 'disabled' : ''; ?>>
                            <span class="text-xs font-black uppercase tracking-widest text-slate-600">Enable Remote Cloud Sync</span>
                        </label>

                        <div class="grid grid-cols-1 gap-5">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Remote Sync URL</label>
                                <input type="text" name="remote_sync_url" value="<?php echo htmlspecialchars($set['remote_sync_url'] ?? ''); ?>" placeholder="https://your-backup-server.com/sems_sync_receiver.php" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold mt-1" <?php echo !$remote_sync_ready ? 'disabled' : ''; ?>>
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase ml-2">API Key</label>
                                <input type="password" name="remote_sync_api_key" value="<?php echo htmlspecialchars($set['remote_sync_api_key'] ?? ''); ?>" placeholder="A secret key shared with your receiver server" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold mt-1" <?php echo !$remote_sync_ready ? 'disabled' : ''; ?>>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-4">
                                    <p class="text-[9px] font-black text-emerald-700 uppercase tracking-widest mb-1">Last Sync Status</p>
                                    <p class="text-xs font-black text-emerald-900"><?php echo htmlspecialchars($set['remote_sync_last_status'] ?? 'Never synced'); ?></p>
                                </div>
                                <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4">
                                    <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Last Remote Backup</p>
                                    <p class="text-xs font-black text-slate-800"><?php echo $last_remote_backup_display; ?></p>
                                </div>
                            </div>
                            <div class="bg-sky-50 border border-sky-100 rounded-2xl p-4 text-[10px] font-bold text-sky-700 leading-5">
                                Transmitted as plain SQL over HTTPS &mdash; use a URL with TLS. Trigger a sync anytime from
                                <a href="db_manager.php" class="font-black underline">Cloud Sync &rarr; Sync Now</a>.
                            </div>
                            <button type="submit" name="update_settings" :disabled="loading" class="w-full bg-emerald-600 text-white font-black py-5 rounded-3xl shadow-xl shadow-emerald-100 uppercase tracking-widest text-[11px] hover:bg-slate-900 hover:-translate-y-1 transition-all active:scale-95 disabled:opacity-60">
                                <span x-show="!loading">Save Remote Sync Settings</span>
                                <i x-show="loading" x-cloak class="fas fa-circle-notch fa-spin"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="lg:col-span-4 space-y-6">
            <div class="dev-badge p-8 rounded-[2.5rem] text-white shadow-2xl relative overflow-hidden">
                <i class="fas fa-code absolute -bottom-4 -right-4 text-white/10 text-9xl"></i>
                <h3 class="text-[10px] font-black uppercase tracking-[0.3em] mb-4 opacity-70">Project Architect</h3>
                <div class="flex items-center gap-4 mb-4">
                    <img src="https://github.com/manoranjan2050.png?size=96" alt="MANORANJAN" class="w-14 h-14 rounded-2xl border border-white/20 object-cover shadow-lg">
                    <div>
                        <h2 class="text-2xl font-black tracking-tighter">MANORANJAN</h2>
                        <p class="text-[9px] font-black uppercase tracking-widest text-indigo-100/80">Developer</p>
                    </div>
                </div>
                <p class="text-xs font-bold text-indigo-100 mb-6">Full Stack Developer & Open Source Contributor</p>
                
                <div class="space-y-4">
                    <a href="https://manoranjan.dev/" target="_blank" class="flex items-center gap-3 bg-white/10 p-3 rounded-2xl hover:bg-white/20 transition-all border border-white/10">
                        <i class="fas fa-globe text-sm"></i>
                        <span class="text-[10px] font-black uppercase tracking-widest">manoranjan.dev</span>
                    </a>
                    <a href="https://github.com/manoranjan2050" target="_blank" class="flex items-center gap-3 bg-white/10 p-3 rounded-2xl hover:bg-white/20 transition-all border border-white/10">
                        <i class="fab fa-github text-sm"></i>
                        <span class="text-[10px] font-black uppercase tracking-widest">github.com/manoranjan2050</span>
                    </a>
                </div>
            </div>

            <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm">
                <h3 class="text-[10px] font-black text-slate-400 uppercase mb-4 tracking-widest">System Health</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-bold text-slate-600">PHP Version</span>
                        <span class="text-[10px] font-black text-indigo-600 bg-indigo-50 px-2 py-1 rounded-md"><?php echo phpversion(); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-bold text-slate-600">Database</span>
                        <span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-2 py-1 rounded-md">MySQL Connected</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="text-center mt-20 mb-10 opacity-50 hover:opacity-100 transition-opacity">
    <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.5em]">
        Open Source Initiative &bull; Developed by <a href="https://github.com/manoranjan2050" class="text-indigo-600 border-b-2 border-indigo-100">MANORANJAN</a> &bull; <a href="https://manoranjan.dev/" class="text-indigo-600 border-b-2 border-indigo-100">manoranjan.dev</a>
    </p>
</div>

</body>
</html>
