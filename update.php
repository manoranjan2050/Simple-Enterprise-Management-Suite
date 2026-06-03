<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_user'])) {
    header("Location: login.php");
    exit();
}

$messages = [];
$errors = [];

function sems_column_exists(mysqli $conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

function sems_add_column(mysqli $conn, $table, $column, $definition, &$messages, &$errors) {
    if (sems_column_exists($conn, $table, $column)) {
        $messages[] = "$table.$column already exists";
        return;
    }

    if ($conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition")) {
        $messages[] = "Added $table.$column";
    } else {
        $errors[] = "Failed adding $table.$column: " . $conn->error;
    }
}

if (isset($_POST['run_update'])) {
    sems_add_column($conn, 'global_settings', 'telegram_enabled', "tinyint(1) NOT NULL DEFAULT 0", $messages, $errors);
    sems_add_column($conn, 'global_settings', 'telegram_bot_token', "varchar(255) DEFAULT NULL", $messages, $errors);
    sems_add_column($conn, 'global_settings', 'telegram_allowed_chat_ids', "text DEFAULT NULL", $messages, $errors);

    $sql = "CREATE TABLE IF NOT EXISTS `telegram_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `chat_id` varchar(64) NOT NULL,
        `username` varchar(100) DEFAULT NULL,
        `command` varchar(80) DEFAULT NULL,
        `message` text DEFAULT NULL,
        `response` text DEFAULT NULL,
        `status` enum('Success','Error','Blocked') DEFAULT 'Success',
        `created_at` timestamp NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `chat_id` (`chat_id`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    if ($conn->query($sql)) {
        $messages[] = "telegram_logs table is ready";
    } else {
        $errors[] = "Failed creating telegram_logs: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Update | Simple EMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
    <main class="mx-auto flex min-h-screen max-w-3xl items-center px-4 py-10">
        <section class="w-full rounded-3xl bg-white p-8 shadow-2xl">
            <div class="mb-8 flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600 text-white">
                    <i class="fas fa-wand-magic-sparkles"></i>
                </div>
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.3em] text-indigo-600">Migration</p>
                    <h1 class="text-2xl font-black uppercase tracking-tight">Update Old Installation</h1>
                </div>
            </div>

            <p class="mb-6 text-sm font-medium leading-6 text-slate-500">
                Run this once after uploading a new version to an existing installed system. This update adds Telegram integration settings and log tables.
            </p>

            <?php if ($messages): ?>
                <div class="mb-4 rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-sm font-bold text-emerald-700">
                    <?php foreach ($messages as $message): ?>
                        <p><i class="fas fa-check mr-2"></i><?php echo htmlspecialchars($message); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="mb-4 rounded-2xl border border-rose-100 bg-rose-50 p-4 text-sm font-bold text-rose-700">
                    <?php foreach ($errors as $error): ?>
                        <p><i class="fas fa-circle-exclamation mr-2"></i><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="flex flex-wrap gap-3">
                <button type="submit" name="run_update" class="rounded-2xl bg-indigo-600 px-6 py-4 text-xs font-black uppercase tracking-widest text-white shadow-lg hover:bg-slate-900">
                    Run Update
                </button>
                <a href="settings.php" class="rounded-2xl bg-slate-100 px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-600 hover:bg-slate-200">
                    Open Settings
                </a>
            </form>
        </section>
    </main>
</body>
</html>
