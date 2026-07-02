<?php
include 'db.php';
$msg = "";
$step = 1; // 1: Username, 2: Answer & New Pass
$user_data = null;

if (isset($_POST['check_user'])) {
    $user = $conn->real_escape_string($_POST['username']);
    $res = $conn->query("SELECT * FROM admin_users WHERE username='$user'");
    if ($res->num_rows > 0) {
        $user_data = $res->fetch_assoc();
        $step = 2;
    } else {
        $msg = "Username not found!";
    }
}

if (isset($_POST['reset_password'])) {
    $user = $_POST['username'];
    $answer = $_POST['answer'];
    $new_pass = $_POST['new_password'];
    
    $res = $conn->query("SELECT * FROM admin_users WHERE username='$user'");
    $row = $res->fetch_assoc();
    
    if (strtolower($answer) == strtolower($row['security_answer'])) {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $conn->query("UPDATE admin_users SET password='$hashed' WHERE username='$user'");
        $msg = "Password reset successful! <a href='login.php' class='underline'>Login Now</a>";
        $step = 1;
    } else {
        $msg = "Wrong answer! Try again.";
        $user_data = $row; // Keep user data for step 2
        $step = 2;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | Simple EMS</title>
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
        body { background: linear-gradient(135deg, #ef4444 0%, #7f1d1d 100%); }
        .glass { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-md">
        <div class="glass p-8 rounded-[2.5rem] shadow-2xl" x-data="{ show: false }" x-init="setTimeout(() => show = true, 50)" x-show="show" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
            <h2 class="text-2xl font-black text-slate-800 mb-6 uppercase tracking-tighter">Reset Password</h2>

            <?php if($msg): ?>
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="fixed top-6 right-6 z-[100] max-w-sm mb-6 p-4 bg-red-50 text-red-600 rounded-2xl text-[10px] font-black uppercase text-center border border-red-100 shadow-2xl">
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <?php if($step == 1): ?>
            <form method="POST" class="space-y-4" x-data="{ loading: false }" @submit="loading = true">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Enter Username</label>
                    <input type="text" name="username" class="w-full p-4 bg-slate-50 border rounded-2xl outline-none" placeholder="admin" required>
                </div>
                <button type="submit" name="check_user" :disabled="loading" class="w-full bg-slate-900 text-white font-black py-4 rounded-2xl uppercase tracking-widest text-xs disabled:opacity-60">
                    <span x-show="!loading">Verify User</span>
                    <i x-show="loading" x-cloak class="fas fa-circle-notch fa-spin"></i>
                </button>
            </form>
            <?php endif; ?>

            <?php if($step == 2): ?>
            <form method="POST" class="space-y-4" x-data="{ loading: false }" @submit="loading = true">
                <input type="hidden" name="username" value="<?php echo $user_data['username']; ?>">

                <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100">
                    <p class="text-[10px] font-black text-indigo-400 uppercase mb-1">Security Question</p>
                    <p class="text-sm font-bold text-slate-700"><?php echo $user_data['security_question']; ?></p>
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Your Answer</label>
                    <input type="text" name="answer" class="w-full p-4 bg-slate-50 border rounded-2xl outline-none" placeholder="Enter answer..." required>
                </div>

                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2">New Password</label>
                    <input type="password" name="new_password" class="w-full p-4 bg-slate-50 border rounded-2xl outline-none" placeholder="••••••••" required>
                </div>

                <button type="submit" name="reset_password" :disabled="loading" class="w-full bg-red-600 text-white font-black py-4 rounded-2xl shadow-lg uppercase tracking-widest text-xs disabled:opacity-60">
                    <span x-show="!loading">Update Password</span>
                    <i x-show="loading" x-cloak class="fas fa-circle-notch fa-spin"></i>
                </button>
            </form>
            <?php endif; ?>

            <a href="login.php" class="block text-center text-[10px] font-black uppercase text-slate-400 mt-6 tracking-widest">Back to Login</a>
        </div>
    </div>
</body>
</html>