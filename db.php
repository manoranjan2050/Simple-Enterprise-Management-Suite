<?php
$config_file = __DIR__ . '/config.php';

if (!file_exists($config_file)) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        header('Location: install.php');
        exit();
    }
    return;
}

$db_config = require $config_file;

$conn = new mysqli(
    $db_config['db_host'] ?? 'localhost',
    $db_config['db_user'] ?? '',
    $db_config['db_pass'] ?? '',
    $db_config['db_name'] ?? ''
);

if ($conn->connect_error) {
    die('Database connection failed. Please check config.php or run install.php again.');
}

$conn->set_charset('utf8mb4');
?>
