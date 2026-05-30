<?php
$config_file = __DIR__ . '/config.php';
$schema_file = __DIR__ . '/setup.sql';
$errors = [];
$success = false;

function old($key, $default = '') {
    return htmlspecialchars($_POST[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}

function install_clean($value) {
    return trim((string)$value);
}

function install_require($data, $keys) {
    $missing = [];
    foreach ($keys as $key => $label) {
        if (install_clean($data[$key] ?? '') === '') {
            $missing[] = $label;
        }
    }
    return $missing;
}

function install_write_config($path, $settings) {
    $content = "<?php\nreturn " . var_export($settings, true) . ";\n";
    return file_put_contents($path, $content, LOCK_EX) !== false;
}

function install_run_schema(mysqli $conn, $schema_file) {
    if (!file_exists($schema_file)) {
        throw new RuntimeException('setup.sql was not found beside install.php.');
    }

    $schema = file_get_contents($schema_file);
    if ($schema === false || trim($schema) === '') {
        throw new RuntimeException('setup.sql is empty or unreadable.');
    }

    $schema = preg_replace('/^\xEF\xBB\xBF/', '', $schema);

    if (!$conn->multi_query($schema)) {
        throw new RuntimeException('Schema import failed: ' . $conn->error);
    }

    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }

        if ($conn->errno) {
            throw new RuntimeException('Schema import failed: ' . $conn->error);
        }
    } while ($conn->more_results() && $conn->next_result());
}

if (file_exists($config_file) && !isset($_GET['status'])) {
    $success = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !file_exists($config_file)) {
    $required = [
        'db_host' => 'Database host',
        'db_name' => 'Database name',
        'db_user' => 'Database username',
        'brand_name' => 'Business name',
        'admin_username' => 'Admin username',
        'admin_password' => 'Admin password',
        'admin_email' => 'Admin email',
        'admin_mobile' => 'Admin mobile',
    ];

    $missing = install_require($_POST, $required);
    if ($missing) {
        $errors[] = 'Please fill: ' . implode(', ', $missing) . '.';
    }

    if (strlen($_POST['admin_password'] ?? '') < 8) {
        $errors[] = 'Admin password must be at least 8 characters.';
    }

    if (!filter_var($_POST['admin_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid admin email.';
    }

    if (!$errors) {
        $db_host = install_clean($_POST['db_host']);
        $db_name = install_clean($_POST['db_name']);
        $db_user = install_clean($_POST['db_user']);
        $db_pass = (string)($_POST['db_pass'] ?? '');

        mysqli_report(MYSQLI_REPORT_OFF);
        $server = new mysqli($db_host, $db_user, $db_pass);

        if ($server->connect_error) {
            $errors[] = 'Database login failed: ' . $server->connect_error;
        } else {
            $server->set_charset('utf8mb4');
            $safe_db = '`' . str_replace('`', '``', $db_name) . '`';
            $server->query("CREATE DATABASE IF NOT EXISTS $safe_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

            if (!$server->select_db($db_name)) {
                $errors[] = 'Could not select database. Please create it in hosting panel first or check permissions.';
            } else {
                try {
                    install_run_schema($server, $schema_file);

                    $brand_name = install_clean($_POST['brand_name']);
                    $brand_address = install_clean($_POST['brand_address'] ?? '');
                    $brand_mobile = install_clean($_POST['brand_mobile'] ?? $_POST['admin_mobile']);
                    $brand_email = install_clean($_POST['brand_email'] ?? $_POST['admin_email']);
                    $license_no = install_clean($_POST['license_no'] ?? '');
                    $currency_symbol = install_clean($_POST['currency_symbol'] ?? 'Rs.');
                    $footer_text = 'Powered by ' . $brand_name;

                    $settings = $server->prepare("INSERT INTO global_settings (id, hotel_name, hotel_address, hotel_mobile, hotel_email, license_no, currency_symbol, footer_text) VALUES (1, ?, ?, ?, ?, ?, ?, ?)");
                    $settings->bind_param('sssssss', $brand_name, $brand_address, $brand_mobile, $brand_email, $license_no, $currency_symbol, $footer_text);
                    $settings->execute();

                    $admin_username = install_clean($_POST['admin_username']);
                    $admin_password = password_hash((string)$_POST['admin_password'], PASSWORD_DEFAULT);
                    $admin_name = install_clean($_POST['admin_name'] ?? $admin_username);
                    $admin_email = install_clean($_POST['admin_email']);
                    $admin_mobile = install_clean($_POST['admin_mobile']);
                    $admin_address = install_clean($_POST['admin_address'] ?? '');
                    $profile_pic = 'default_user.png';
                    $security_question = 'What is your pet name?';
                    $security_answer = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);

                    $admin = $server->prepare("INSERT INTO admin_users (username, password, full_name, email, mobile, address, profile_pic, security_question, security_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $admin->bind_param('sssssssss', $admin_username, $admin_password, $admin_name, $admin_email, $admin_mobile, $admin_address, $profile_pic, $security_question, $security_answer);
                    $admin->execute();

                    $config = [
                        'db_host' => $db_host,
                        'db_name' => $db_name,
                        'db_user' => $db_user,
                        'db_pass' => $db_pass,
                        'installed_at' => date('c'),
                    ];

                    if (!install_write_config($config_file, $config)) {
                        $errors[] = 'Tables were created, but config.php could not be written. Check folder write permission.';
                    } else {
                        header('Location: install.php?status=done');
                        exit();
                    }
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}

if (isset($_GET['status']) && $_GET['status'] === 'done') {
    $success = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Resto ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <main class="mx-auto flex min-h-screen w-full max-w-6xl items-center px-4 py-10">
        <div class="grid w-full gap-8 lg:grid-cols-[0.9fr_1.4fr]">
            <section class="flex flex-col justify-between rounded-3xl border border-white/10 bg-white/5 p-8 shadow-2xl">
                <div>
                    <div class="mb-8 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-500 text-2xl shadow-lg shadow-indigo-500/30">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <p class="mb-3 text-xs font-black uppercase tracking-[0.35em] text-indigo-300">Universal setup</p>
                    <h1 class="text-4xl font-black uppercase tracking-tight text-white">Resto ERP Installer</h1>
                    <p class="mt-4 text-sm leading-6 text-slate-400">Upload the folder to hosting, open install.php, enter database and branding details, and the system becomes ready for a new business.</p>
                </div>
                <div class="mt-10 grid gap-3 text-xs font-bold text-slate-300">
                    <div class="flex items-center gap-3"><i class="fas fa-database text-emerald-400"></i> Creates clean tables from setup.sql</div>
                    <div class="flex items-center gap-3"><i class="fas fa-paint-brush text-sky-400"></i> Saves business branding</div>
                    <div class="flex items-center gap-3"><i class="fas fa-user-shield text-amber-400"></i> Creates first admin account</div>
                </div>
            </section>

            <section class="rounded-3xl bg-white p-6 text-slate-900 shadow-2xl md:p-8">
                <?php if ($success): ?>
                    <div class="flex min-h-[520px] flex-col items-center justify-center text-center">
                        <div class="mb-6 flex h-20 w-20 items-center justify-center rounded-3xl bg-emerald-100 text-4xl text-emerald-600">
                            <i class="fas fa-check"></i>
                        </div>
                        <h2 class="text-3xl font-black uppercase tracking-tight">Installation Ready</h2>
                        <p class="mt-3 max-w-md text-sm font-medium leading-6 text-slate-500">The database config exists and the app is ready to use. For security, remove or rename install.php after setup on a live server.</p>
                        <a href="login.php" class="mt-8 inline-flex items-center gap-3 rounded-2xl bg-slate-950 px-6 py-4 text-xs font-black uppercase tracking-widest text-white transition hover:bg-indigo-600">
                            Open Login <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="mb-6">
                        <p class="text-xs font-black uppercase tracking-[0.3em] text-indigo-600">First time setup</p>
                        <h2 class="mt-2 text-2xl font-black uppercase tracking-tight">Connect, Brand, Admin</h2>
                    </div>

                    <?php if ($errors): ?>
                        <div class="mb-6 rounded-2xl border border-rose-100 bg-rose-50 p-4 text-sm font-bold text-rose-700">
                            <?php foreach ($errors as $error): ?>
                                <div class="flex gap-2"><i class="fas fa-circle-exclamation mt-1"></i><span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="grid gap-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="grid gap-2 text-xs font-black uppercase text-slate-500">DB Host
                                <input name="db_host" value="<?php echo old('db_host', 'localhost'); ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 outline-none focus:border-indigo-500" required>
                            </label>
                            <label class="grid gap-2 text-xs font-black uppercase text-slate-500">DB Name
                                <input name="db_name" value="<?php echo old('db_name'); ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 outline-none focus:border-indigo-500" required>
                            </label>
                            <label class="grid gap-2 text-xs font-black uppercase text-slate-500">DB Username
                                <input name="db_user" value="<?php echo old('db_user'); ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 outline-none focus:border-indigo-500" required>
                            </label>
                            <label class="grid gap-2 text-xs font-black uppercase text-slate-500">DB Password
                                <input type="password" name="db_pass" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 outline-none focus:border-indigo-500">
                            </label>
                        </div>

                        <div class="h-px bg-slate-100"></div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="grid gap-2 text-xs font-black uppercase text-slate-500 md:col-span-2">Business / Brand Name
                                <input name="brand_name" value="<?php echo old('brand_name', 'Resto ERP'); ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 outline-none focus:border-indigo-500" required>
                            </label>
                            <label class="grid gap-2 text-xs font-black uppercase text-slate-500 md:col-span-2">Address
                                <textarea name="brand_address" class="min-h-20 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 outline-none focus:border-indigo-500"><?php echo old('brand_address'); ?></textarea>
                            </label>
                            <label class="grid gap-2 text-xs font-black uppercase text-slate-500">Business Email
                                <input type="email" name="brand_email" value="<?php echo old('brand_email'); ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 outline-none focus:border-indigo-500">
                            </label>
                            <label class="grid gap-2 text-xs font-black uppercase text-slate-500">Business Mobile
                                <input name="brand_mobile" value="<?php echo old('brand_mobile'); ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 outline-none focus:border-indigo-500">
                            </label>
                            <label class="grid gap-2 text-xs font-black uppercase text-slate-500">License / GST
                                <input name="license_no" value="<?php echo old('license_no'); ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 outline-none focus:border-indigo-500">
                            </label>
                            <label class="grid gap-2 text-xs font-black uppercase text-slate-500">Currency
                                <input name="currency_symbol" value="<?php echo old('currency_symbol', 'Rs.'); ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 outline-none focus:border-indigo-500">
                            </label>
                        </div>

                        <div class="h-px bg-slate-100"></div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="grid gap-2 text-xs font-black uppercase text-slate-500">Admin Name
                                <input name="admin_name" value="<?php echo old('admin_name'); ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 outline-none focus:border-indigo-500">
                            </label>
                            <label class="grid gap-2 text-xs font-black uppercase text-slate-500">Admin Username
                                <input name="admin_username" value="<?php echo old('admin_username', 'admin'); ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 outline-none focus:border-indigo-500" required>
                            </label>
                            <label class="grid gap-2 text-xs font-black uppercase text-slate-500">Admin Email
                                <input type="email" name="admin_email" value="<?php echo old('admin_email'); ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 outline-none focus:border-indigo-500" required>
                            </label>
                            <label class="grid gap-2 text-xs font-black uppercase text-slate-500">Admin Mobile
                                <input name="admin_mobile" value="<?php echo old('admin_mobile'); ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 outline-none focus:border-indigo-500" required>
                            </label>
                            <label class="grid gap-2 text-xs font-black uppercase text-slate-500 md:col-span-2">Admin Password
                                <input type="password" name="admin_password" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 outline-none focus:border-indigo-500" required>
                            </label>
                        </div>

                        <button class="rounded-2xl bg-indigo-600 px-6 py-5 text-xs font-black uppercase tracking-widest text-white shadow-xl shadow-indigo-100 transition hover:-translate-y-0.5 hover:bg-slate-950" type="submit">
                            Install Now
                        </button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
