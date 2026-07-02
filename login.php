<?php
/**
 * Project: Simple Enterprise Management Suite
 * Author: MANORANJAN
 * Website: https://manoranjan.dev/
 */
session_start();
include 'db.php'; 

$global_set = $conn->query("SELECT hotel_name FROM global_settings WHERE id=1")->fetch_assoc();
$brand_name = !empty($global_set['hotel_name']) ? $global_set['hotel_name'] : 'Simple EMS';

// If already logged in, skip login page
if (isset($_SESSION['admin_user'])) {
    header("Location: index.php");
    exit();
}

$error = "";

if (isset($_POST['login'])) {
    // Sanitize the identifier (could be username, email, or mobile)
    $identifier = $conn->real_escape_string($_POST['identifier']);
    $pass = $_POST['password'];

    // 1. UNIVERSAL QUERY: Check against username, email, OR mobile
    $sql = "SELECT * FROM admin_users 
            WHERE username='$identifier' 
            OR email='$identifier' 
            OR mobile='$identifier' 
            LIMIT 1";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // 2. SECURE PASSWORD VERIFICATION
        if (password_verify($pass, $row['password'])) {
            // Log in using the unique username
            $_SESSION['admin_user'] = $row['username'];
            header("Location: index.php");
            exit();
        } else {
            $error = "The password you entered is incorrect.";
        }
    } else {
        $error = "No account found with those details.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Access | <?php echo htmlspecialchars($brand_name); ?></title>
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
        body {
            background: #0f172a;
            background-image: radial-gradient(circle at top right, #1e1b4b 0%, #0f172a 100%);
            background-attachment: fixed;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .input-focus:focus-within {
            border-color: #6366f1;
            background: white;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.1);
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-md" x-data="{ show: false }" x-init="setTimeout(() => show = true, 50)" x-show="show" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-indigo-600 rounded-[2.5rem] mb-4 shadow-2xl shadow-indigo-500/20 transform -rotate-6">
                <i class="fas fa-fingerprint text-white text-3xl"></i>
            </div>
            <h1 class="text-4xl font-black text-white tracking-tighter italic uppercase"><?php echo htmlspecialchars($brand_name); ?></h1>
            <p class="text-slate-500 text-[9px] font-black uppercase tracking-[0.4em] mt-2">Enterprise Management Suite</p>
        </div>

        <div class="glass-card p-8 md:p-12 rounded-[3.5rem] shadow-2xl relative overflow-hidden">
            <div class="absolute -top-12 -right-12 w-32 h-32 bg-indigo-600/5 rounded-full"></div>
            
            <h2 class="text-2xl font-black text-slate-800 mb-2 tracking-tight uppercase italic">Login</h2>
            <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-10">Username / Email / Mobile</p>

            <?php if($error): ?>
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="fixed top-6 right-6 z-[100] max-w-sm flex items-center bg-rose-50 text-rose-600 p-4 rounded-2xl mb-8 text-[11px] font-black uppercase border border-rose-100 shadow-2xl">
                    <i class="fas fa-circle-exclamation mr-3 text-lg"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6" x-data="{ loading: false }" @submit="loading = true">
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-4 tracking-widest">Account ID</label>
                    <div class="input-focus flex items-center bg-slate-100 rounded-2xl px-5 transition-all duration-300 border-2 border-transparent">
                        <i class="fas fa-shield-halved text-slate-300 mr-3"></i>
                        <input type="text" name="identifier" placeholder="Username, Email or Mobile" 
                               class="w-full bg-transparent py-4 outline-none text-sm font-bold text-slate-700 placeholder:text-slate-300 placeholder:font-medium" required>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-4 tracking-widest">Security Pin</label>
                    <div class="input-focus flex items-center bg-slate-100 rounded-2xl px-5 transition-all duration-300 border-2 border-transparent">
                        <i class="fas fa-key text-slate-300 mr-3"></i>
                        <input type="password" name="password" placeholder="••••••••" 
                               class="w-full bg-transparent py-4 outline-none text-sm font-black text-slate-700 tracking-widest" required>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" name="login" :disabled="loading"
                            class="w-full bg-slate-900 text-white font-black py-5 rounded-[2rem] shadow-xl hover:bg-indigo-600 hover:-translate-y-1 transition-all active:scale-95 uppercase tracking-[0.2em] text-[11px] disabled:opacity-60">
                        <span x-show="!loading">Verify & Enter</span>
                        <i x-show="loading" x-cloak class="fas fa-circle-notch fa-spin"></i>
                    </button>
                </div>
            </form>

            <div class="mt-12 pt-8 border-t border-slate-100 flex flex-col items-center space-y-4">
                <a href="register.php" class="text-indigo-600 text-[10px] font-black uppercase tracking-widest hover:underline">
                    Create New Admin
                </a>
            </div>
        </div>

        <div class="text-center mt-12 space-y-2">
            <p class="text-white/20 text-[9px] font-black uppercase tracking-[0.5em]">
                System Architecture by
            </p>
            <span class="text-white/40 font-black text-xs uppercase tracking-widest"><?php echo htmlspecialchars($brand_name); ?></span>
        </div>
    </div>

</body>
</html>
