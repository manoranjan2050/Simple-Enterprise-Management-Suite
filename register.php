<?php
/**
 * Project: Simple Enterprise Management Suite
 * Feature: Disabled Registration Security Page
 */
include 'db.php';

// Fetch Branding
$global_set = $conn->query("SELECT hotel_name FROM global_settings WHERE id=1")->fetch_assoc();
$brand_name = $global_set['hotel_name'] ?? "SIMPLE EMS";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Restricted | <?php echo $brand_name; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; }
        .glow { box-shadow: 0 0 50px rgba(99, 102, 241, 0.2); }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-6">

    <div class="max-w-md w-full bg-slate-900 rounded-[3rem] p-10 text-center border border-slate-800 glow">
        
        <div class="w-24 h-24 bg-rose-500/10 rounded-[2.5rem] flex items-center justify-center mx-auto mb-8 border border-rose-500/20">
            <i class="fas fa-user-lock text-rose-500 text-4xl"></i>
        </div>

        <h1 class="text-2xl font-black italic tracking-tighter text-white uppercase mb-2">
            Simple <span class="text-indigo-500">EMS</span>
        </h1>
        <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.4em] mb-8">Management Suite</p>

        <div class="space-y-4 mb-10">
            <h2 class="text-xl font-bold text-white uppercase tracking-tight">Registration Disabled</h2>
            <p class="text-slate-400 text-sm leading-relaxed">
                For security reasons, public registration is currently <b>locked</b>. New user accounts can only be created by the <span class="text-indigo-400">System Administrator</span>.
            </p>
        </div>

        <div class="space-y-4">
            <a href="login.php" class="block w-full bg-indigo-600 hover:bg-indigo-500 text-white font-black py-4 rounded-2xl transition-all uppercase tracking-widest text-xs">
                Back to Login
            </a>
            <p class="text-[9px] font-bold text-slate-600 uppercase">
                Please contact the branch manager if you need access.
            </p>
        </div>

        <div class="mt-12 pt-6 border-t border-slate-800 opacity-40">
            <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">
                &copy; <?php echo date('Y'); ?> <?php echo $brand_name; ?> Security Engine
            </p>
        </div>
    </div>

</body>
</html>
