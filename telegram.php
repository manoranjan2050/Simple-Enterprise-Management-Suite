<?php
include 'db.php';

function tg_setting(mysqli $conn) {
    $result = $conn->query("SELECT * FROM global_settings WHERE id=1");
    return $result ? $result->fetch_assoc() : [];
}

function tg_allowed_ids($raw) {
    $ids = preg_split('/[\s,]+/', trim((string)$raw));
    return array_values(array_filter($ids, fn($id) => $id !== ''));
}

function tg_send($token, $chat_id, $text, $reply_markup = null) {
    if (!$token || !$chat_id) return false;

    $payload = [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => true,
    ];

    if ($reply_markup) {
        $payload['reply_markup'] = json_encode($reply_markup);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($payload),
            'timeout' => 10,
        ],
    ]);

    return @file_get_contents("https://api.telegram.org/bot{$token}/sendMessage", false, $context) !== false;
}

function tg_log(mysqli $conn, $chat_id, $username, $command, $message, $response, $status = 'Success') {
    $chat_id = $conn->real_escape_string((string)$chat_id);
    $username = $conn->real_escape_string((string)$username);
    $command = $conn->real_escape_string((string)$command);
    $message = $conn->real_escape_string((string)$message);
    $response = $conn->real_escape_string((string)$response);
    $status = $conn->real_escape_string((string)$status);
    @$conn->query("INSERT INTO telegram_logs (chat_id, username, command, message, response, status) VALUES ('$chat_id', '$username', '$command', '$message', '$response', '$status')");
}

function tg_money($amount, $currency) {
    return $currency . number_format((float)$amount, 2);
}

function tg_help() {
    return "Simple EMS Telegram Commands\n\n"
        . "/menu - Show command menu\n"
        . "/today - Today summary\n"
        . "/month - Current month summary\n"
        . "/add_collection cash 1000 online 500 note Today sale\n"
        . "/add_expense category Grocery amount 300 status Paid note Rice\n"
        . "/vendors - Vendor list\n"
        . "/vendor_due - Pending vendor balances\n"
        . "/add_vendor_payment vendor Grocery amount 500\n\n"
        . "Tip: Use exact category/vendor names for best result.";
}

function tg_value($text, $key) {
    $keys = 'cash|online|note|category|amount|status|vendor';
    if (preg_match('/\b' . preg_quote($key, '/') . '\s+(.+?)(?=\s+(?:' . $keys . ')\b|$)/i', $text, $m)) {
        return trim($m[1]);
    }
    return '';
}

function tg_number_value($text, $key) {
    if (preg_match('/\b' . preg_quote($key, '/') . '\s+(-?\d+(?:\.\d+)?)/i', $text, $m)) {
        return (float)$m[1];
    }
    return 0;
}

function tg_find_category(mysqli $conn, $name) {
    $safe = $conn->real_escape_string(trim($name));
    if ($safe === '') return null;

    $result = $conn->query("SELECT id, category_name FROM expense_categories WHERE category_name='$safe' LIMIT 1");
    if ($result && $result->num_rows) return $result->fetch_assoc();

    $result = $conn->query("SELECT id, category_name FROM expense_categories WHERE category_name LIKE '%$safe%' ORDER BY category_name LIMIT 1");
    return ($result && $result->num_rows) ? $result->fetch_assoc() : null;
}

function tg_today(mysqli $conn, $currency) {
    $today = date('Y-m-d');
    $sales = $conn->query("SELECT SUM(cash_sales) c, SUM(online_sales) o FROM transactions WHERE date='$today'")->fetch_assoc();
    $expenses = $conn->query("SELECT SUM(amount) total FROM expense_details WHERE date='$today'")->fetch_assoc();
    $staff = $conn->query("SELECT SUM(amount) total FROM staff_ledger WHERE date='$today'")->fetch_assoc();

    $cash = (float)($sales['c'] ?? 0);
    $online = (float)($sales['o'] ?? 0);
    $exp = (float)($expenses['total'] ?? 0) + (float)($staff['total'] ?? 0);
    $profit = $cash + $online - $exp;

    return "Today Summary (" . date('d M Y') . ")\n"
        . "Cash: " . tg_money($cash, $currency) . "\n"
        . "Online: " . tg_money($online, $currency) . "\n"
        . "Revenue: " . tg_money($cash + $online, $currency) . "\n"
        . "Expenses: " . tg_money($exp, $currency) . "\n"
        . "Net: " . tg_money($profit, $currency);
}

function tg_month(mysqli $conn, $currency) {
    $month = (int)date('m');
    $year = (int)date('Y');
    $filter = "MONTH(date)=$month AND YEAR(date)=$year";
    $sales = $conn->query("SELECT SUM(cash_sales) c, SUM(online_sales) o FROM transactions WHERE $filter")->fetch_assoc();
    $expenses = $conn->query("SELECT SUM(amount) total FROM expense_details WHERE $filter")->fetch_assoc();
    $staff = $conn->query("SELECT SUM(amount) total FROM staff_ledger WHERE $filter")->fetch_assoc();

    $cash = (float)($sales['c'] ?? 0);
    $online = (float)($sales['o'] ?? 0);
    $exp = (float)($expenses['total'] ?? 0) + (float)($staff['total'] ?? 0);

    return "Month Summary (" . date('F Y') . ")\n"
        . "Revenue: " . tg_money($cash + $online, $currency) . "\n"
        . "Expenses: " . tg_money($exp, $currency) . "\n"
        . "Net: " . tg_money($cash + $online - $exp, $currency);
}

function tg_vendors(mysqli $conn, $currency, $only_due = false) {
    $result = $conn->query("SELECT c.category_name, c.vendor_mobile,
        SUM(CASE WHEN e.status='Pending' THEN e.amount ELSE 0 END) pending,
        SUM(CASE WHEN e.status='Paid' AND e.amount < 0 THEN ABS(e.amount) ELSE 0 END) paid_back
        FROM expense_categories c
        LEFT JOIN expense_details e ON c.id=e.category_id
        GROUP BY c.id, c.category_name, c.vendor_mobile
        ORDER BY c.category_name");

    $lines = [$only_due ? "Pending Vendor Dues" : "Vendor List"];
    while ($row = $result->fetch_assoc()) {
        $balance = (float)$row['pending'] - (float)$row['paid_back'];
        if ($only_due && $balance <= 0) continue;
        $mobile = $row['vendor_mobile'] ? " (" . $row['vendor_mobile'] . ")" : "";
        $lines[] = "- " . $row['category_name'] . $mobile . ": " . tg_money($balance, $currency);
    }

    return count($lines) > 1 ? implode("\n", $lines) : "No vendor dues found.";
}

$settings = tg_setting($conn);
$token = $settings['telegram_bot_token'] ?? '';
$enabled = (int)($settings['telegram_enabled'] ?? 0);
$currency = $settings['currency_symbol'] ?? 'Rs.';

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);
$message = $update['message'] ?? $update['edited_message'] ?? null;

if (!$message) {
    http_response_code(200);
    exit('OK');
}

$chat_id = (string)($message['chat']['id'] ?? '');
$username = $message['from']['username'] ?? ($message['from']['first_name'] ?? '');
$text = trim((string)($message['text'] ?? ''));
$command = strtolower(strtok($text, " \n") ?: '');
$allowed = tg_allowed_ids($settings['telegram_allowed_chat_ids'] ?? '');

if (!$enabled || !$token) {
    tg_log($conn, $chat_id, $username, $command, $text, 'Telegram is disabled or bot token is missing.', 'Blocked');
    http_response_code(200);
    exit('Disabled');
}

if (!in_array($chat_id, $allowed, true)) {
    $response = "Access blocked. Your Telegram chat ID is: $chat_id\nAdd this ID in Settings > Telegram Integration.";
    tg_send($token, $chat_id, $response);
    tg_log($conn, $chat_id, $username, $command, $text, $response, 'Blocked');
    http_response_code(200);
    exit('Blocked');
}

$keyboard = [
    'keyboard' => [
        [['text' => '/today'], ['text' => '/month']],
        [['text' => '/vendors'], ['text' => '/vendor_due']],
        [['text' => '/help']],
    ],
    'resize_keyboard' => true,
];

try {
    if ($command === '/start' || $command === '/menu' || $command === '/help') {
        $response = tg_help();
        tg_send($token, $chat_id, $response, $keyboard);
    } elseif ($command === '/today') {
        $response = tg_today($conn, $currency);
        tg_send($token, $chat_id, $response, $keyboard);
    } elseif ($command === '/month') {
        $response = tg_month($conn, $currency);
        tg_send($token, $chat_id, $response, $keyboard);
    } elseif ($command === '/add_collection') {
        $cash = tg_number_value($text, 'cash');
        $online = tg_number_value($text, 'online');
        $note = $conn->real_escape_string(tg_value($text, 'note') ?: 'Telegram entry');
        $date = date('Y-m-d');
        $conn->query("INSERT INTO transactions (date, cash_sales, online_sales, note) VALUES ('$date', '$cash', '$online', '$note')");
        $response = "Collection added.\nCash: " . tg_money($cash, $currency) . "\nOnline: " . tg_money($online, $currency);
        tg_send($token, $chat_id, $response, $keyboard);
    } elseif ($command === '/add_expense') {
        $category_name = tg_value($text, 'category');
        $amount = tg_number_value($text, 'amount');
        $status = ucfirst(strtolower(tg_value($text, 'status') ?: 'Paid'));
        $status = in_array($status, ['Paid', 'Pending'], true) ? $status : 'Paid';
        $note = $conn->real_escape_string(tg_value($text, 'note') ?: 'Telegram expense');
        $category = tg_find_category($conn, $category_name);
        if (!$category || $amount <= 0) {
            throw new RuntimeException("Use: /add_expense category Grocery amount 300 status Paid note Rice");
        }
        $date = date('Y-m-d');
        $cat_id = (int)$category['id'];
        $conn->query("INSERT INTO expense_details (date, category_id, amount, note, status) VALUES ('$date', '$cat_id', '$amount', '$note', '$status')");
        $response = "Expense added.\nCategory: " . $category['category_name'] . "\nAmount: " . tg_money($amount, $currency) . "\nStatus: $status";
        tg_send($token, $chat_id, $response, $keyboard);
    } elseif ($command === '/vendors') {
        $response = tg_vendors($conn, $currency, false);
        tg_send($token, $chat_id, $response, $keyboard);
    } elseif ($command === '/vendor_due') {
        $response = tg_vendors($conn, $currency, true);
        tg_send($token, $chat_id, $response, $keyboard);
    } elseif ($command === '/add_vendor_payment') {
        $vendor_name = tg_value($text, 'vendor');
        $amount = tg_number_value($text, 'amount');
        $category = tg_find_category($conn, trim($vendor_name, "\"'"));
        if (!$category || $amount <= 0) {
            throw new RuntimeException("Use: /add_vendor_payment vendor Grocery amount 500");
        }
        $date = date('Y-m-d');
        $cat_id = (int)$category['id'];
        $note = $conn->real_escape_string('Telegram vendor payment');
        $negative = -1 * abs($amount);
        $conn->query("INSERT INTO expense_details (date, category_id, amount, note, status) VALUES ('$date', '$cat_id', '$negative', '$note', 'Paid')");
        $response = "Vendor payment added.\nVendor: " . $category['category_name'] . "\nAmount: " . tg_money($amount, $currency);
        tg_send($token, $chat_id, $response, $keyboard);
    } else {
        $response = "Unknown command.\n\n" . tg_help();
        tg_send($token, $chat_id, $response, $keyboard);
    }

    tg_log($conn, $chat_id, $username, $command, $text, $response, 'Success');
} catch (Throwable $e) {
    $response = "Error: " . $e->getMessage();
    tg_send($token, $chat_id, $response, $keyboard);
    tg_log($conn, $chat_id, $username, $command, $text, $response, 'Error');
}

http_response_code(200);
echo 'OK';
