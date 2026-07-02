<?php
/**
 * Example SEMS Remote Cloud Sync receiver.
 *
 * Deploy this single file to ANY separate PHP host (a different server,
 * a different AMPPS vhost/port, shared hosting, etc.) — it must NOT live
 * inside the sems/ app itself, since the whole point of "remote" sync is
 * that the backup leaves this machine/app.
 *
 * Setup:
 *   1. Copy this file to your remote PHP host.
 *   2. Change RECEIVER_API_KEY below to a long random secret.
 *   3. Make sure the sibling "backups/" folder exists and is writable,
 *      and is NOT directly web-accessible (e.g. block it via .htaccess
 *      or place it outside the web root).
 *   4. In SEMS -> Settings -> Remote Cloud Sync, set:
 *        Remote Sync URL = https://your-host/path/sems_sync_receiver.php
 *        API Key         = the same secret you set below
 */

const RECEIVER_API_KEY = 'change-this-to-a-long-random-secret';

header('Content-Type: application/json');

// Some Apache/PHP setups don't populate $_SERVER['HTTP_AUTHORIZATION'] even
// though the header was sent — fall back to getallheaders()/REDIRECT_* forms.
$auth_header = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? (function_exists('getallheaders') ? (getallheaders()['Authorization'] ?? '') : '');

if (!preg_match('/^Bearer\s+(.+)$/i', $auth_header, $m) || !hash_equals(RECEIVER_API_KEY, $m[1])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$body = file_get_contents('php://input');
if ($body === false || trim($body) === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty payload']);
    exit;
}

$backup_dir = __DIR__ . '/backups';
if (!is_dir($backup_dir) && !mkdir($backup_dir, 0750, true)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Cannot create backups directory']);
    exit;
}

$filename = 'backup_' . date('Ymd_His') . '.sql';
$path = $backup_dir . '/' . $filename;

if (file_put_contents($path, $body) === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to write backup file']);
    exit;
}

http_response_code(200);
echo json_encode(['status' => 'ok', 'file' => $filename, 'bytes' => strlen($body)]);
