<?php
/**
 * Project: Simple Enterprise Management Suite
 * Author: MANORANJAN
 * Feature: Profile & Security Management
 */
session_start();
include 'db.php';

if (!isset($_SESSION['admin_user'])) { 
    header("Location: login.php"); 
    exit(); 
}

$admin_username = $_SESSION['admin_user'];
$msg = "";
$status = "";

// 1. FETCH USER DATA
$safe_admin_username = $conn->real_escape_string($admin_username);
$user_res = $conn->query("SELECT * FROM admin_users WHERE username='$safe_admin_username'");
$user = $user_res->fetch_assoc();

if (!$user) {
    $user = [
        'full_name' => '', 'email' => '', 'mobile' => '',
        'address' => '', 'profile_pic' => 'default_user.png',
        'username' => $admin_username
    ];
}

// 2. UPDATE LOGIC
if (isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username'] ?? '');
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $mobile = $conn->real_escape_string($_POST['mobile']);
    $address = $conn->real_escape_string($_POST['address']);
    $new_pass = $_POST['new_password'];
    $current_user_id = (int)($user['id'] ?? 0);

    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $new_username)) {
        $msg = "Username must be 3-50 characters and can use letters, numbers, dot, dash, or underscore.";
        $status = "error";
    }

    if ($status !== "error") {
        $safe_new_username = $conn->real_escape_string($new_username);
        $check_user = $conn->query("SELECT id FROM admin_users WHERE username='$safe_new_username' AND id != $current_user_id LIMIT 1");
        if ($check_user && $check_user->num_rows > 0) {
            $msg = "This username is already taken. Please choose another one.";
            $status = "error";
        }
    }

    if ($status !== "error") {
        // Handle Photo
        $photo_name = $_POST['old_photo'] ?: 'default_user.png';
        if (!empty($_FILES['profile_pic']['name'])) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $photo_name = time() . "_" . basename($_FILES['profile_pic']['name']);
            move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_dir . $photo_name);
        }

        // Password Logic: Only update if field is not empty
        $pass_update_sql = "";
        if (!empty($new_pass)) {
            // We use password_hash for modern security
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $pass_update_sql = ", password='$hashed_pass'";
        }

        $sql = "UPDATE admin_users SET 
                username='$safe_new_username',
                full_name='$full_name', email='$email', 
                mobile='$mobile', address='$address', 
                profile_pic='$photo_name' $pass_update_sql
                WHERE id=$current_user_id";
        
        if ($conn->query($sql)) {
            $_SESSION['admin_user'] = $new_username;
            header("Location: profile.php?status=success");
            exit();
        } else {
            $msg = "Update failed: " . $conn->error;
            $status = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Security Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 font-sans text-slate-800 pb-20">

    <nav class="bg-slate-900 text-white p-6 shadow-xl flex justify-between items-center sticky top-0 z-50 border-b border-indigo-500/30">
        <div class="flex items-center gap-3">
            <i class="fas fa-shield-halved text-indigo-400 text-2xl"></i>
            <h1 class="font-black uppercase text-sm tracking-widest italic">Account <span class="text-indigo-400">Settings</span></h1>
        </div>
        <a href="index.php" class="bg-slate-700 px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all hover:bg-slate-600">Back to Dashboard</a>
    </nav>

    <div class="container mx-auto px-4 py-10 max-w-5xl">
        <div class="bg-white rounded-[3rem] shadow-2xl overflow-hidden border border-slate-200">
            <div class="flex flex-col lg:flex-row">
                
                <div class="lg:w-1/3 bg-slate-50 p-10 flex flex-col items-center text-center border-r border-slate-100">
                    <div class="relative mb-6">
                        <?php 
                            $img_path = "uploads/" . ($user['profile_pic'] ?: 'default_user.png');
                            if (!file_exists($img_path)) { $img_path = 'https://ui-avatars.com/api/?name=' . urlencode($admin_username) . '&background=6366f1&color=fff'; }
                        ?>
                        <img src="<?php echo $img_path; ?>" class="w-48 h-48 rounded-[3rem] object-cover border-8 border-white shadow-2xl transform hover:rotate-2 transition-transform">
                        <div class="absolute -bottom-2 -right-2 bg-indigo-600 text-white w-10 h-10 rounded-2xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <h2 class="text-2xl font-black text-slate-800 tracking-tight"><?php echo !empty($user['full_name']) ? $user['full_name'] : 'Administrator'; ?></h2>
                    <p class="text-[10px] font-black text-indigo-500 uppercase tracking-[0.3em] mt-2"><?php echo $admin_username; ?></p>
                    
                    <div class="mt-10 w-full space-y-3">
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 text-left">
                            <p class="text-[9px] font-black text-slate-400 uppercase">System Role</p>
                            <p class="text-xs font-bold text-slate-700">Super Admin / Root</p>
                        </div>
                    </div>
                </div>

                <div class="lg:w-2/3 p-8 md:p-12">
                    <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                        <div class="mb-8 p-4 bg-emerald-50 text-emerald-600 rounded-2xl text-[10px] font-black uppercase border border-emerald-100 text-center tracking-widest">
                            <i class="fas fa-check-circle mr-2"></i> Profile & Password Updated Successfully!
                        </div>
                    <?php endif; ?>
                    <?php if($msg): ?>
                        <div class="mb-8 p-4 <?php echo $status == 'error' ? 'bg-rose-50 text-rose-600 border-rose-100' : 'bg-emerald-50 text-emerald-600 border-emerald-100'; ?> rounded-2xl text-[10px] font-black uppercase border text-center tracking-widest">
                            <i class="fas fa-circle-info mr-2"></i> <?php echo htmlspecialchars($msg); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="space-y-8">
                        <input type="hidden" name="old_photo" value="<?php echo $user['profile_pic']; ?>">
                        
                        <div>
                            <h3 class="text-[11px] font-black text-indigo-600 uppercase tracking-widest mb-6 border-b pb-2">Personal Identity</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 block mb-1">Display Name</label>
                                    <input type="text" name="full_name" value="<?php echo $user['full_name']; ?>" placeholder="Full Name" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold">
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 block mb-1">Login Username</label>
                                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" placeholder="admin" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" required>
                                    <p class="text-[9px] text-slate-400 font-bold mt-2 ml-2 uppercase">Letters, numbers, dot, dash, underscore.</p>
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 block mb-1">Email Connection</label>
                                    <input type="email" name="email" value="<?php echo $user['email']; ?>" placeholder="admin@hotel.com" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold">
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 block mb-1">Mobile Access</label>
                                    <input type="text" name="mobile" value="<?php echo $user['mobile']; ?>" placeholder="+91..." class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl font-bold">
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2 block mb-1">Avatar / Photo</label>
                                    <input type="file" name="profile_pic" class="w-full text-[10px] text-slate-500 file:mr-4 file:py-3 file:px-6 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:bg-indigo-50 file:text-indigo-600 file:uppercase cursor-pointer">
                                </div>
                            </div>
                        </div>

                        <div class="bg-indigo-50/50 p-6 rounded-[2rem] border border-indigo-100">
                            <h3 class="text-[11px] font-black text-indigo-600 uppercase tracking-widest mb-4">Security Credentials</h3>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase ml-2 block mb-1">Change Password</label>
                                <div class="relative">
                                    <i class="fas fa-lock absolute left-4 top-5 text-indigo-300"></i>
                                    <input type="password" name="new_password" placeholder="Leave blank to keep current password" class="w-full p-4 pl-12 bg-white border border-indigo-100 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 font-black text-indigo-900 tracking-widest">
                                </div>
                                <p class="text-[9px] text-indigo-400 font-bold mt-2 ml-2 italic uppercase tracking-tighter">Tip: Use 8+ characters with a mix of symbols.</p>
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase ml-2 block mb-1">Business Address</label>
                            <textarea name="address" placeholder="Residential or Office Address" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 h-24 font-medium"><?php echo $user['address']; ?></textarea>
                        </div>

                        <div class="pt-6 border-t border-slate-100 flex justify-end items-center gap-6">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Double check your info before saving</span>
                            <button type="submit" name="update_profile" class="bg-slate-900 text-white px-10 py-5 rounded-3xl font-black text-[11px] uppercase tracking-[0.2em] shadow-2xl hover:bg-indigo-600 hover:-translate-y-1 transition-all active:scale-95">
                                Save Profile Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-10 opacity-40 hover:opacity-100 transition-opacity">
        <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.4em]">
            System Control Panel &bull; Simple EMS v1.0
        </p>
    </div>

</body>
</html>
