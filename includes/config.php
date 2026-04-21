<?php
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH', ROOT_PATH . '/data');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

define('USERS_FILE', DATA_PATH . '/users.json');
define('CAMPAIGNS_FILE', DATA_PATH . '/campaigns.json');
define('CREATIVES_FILE', DATA_PATH . '/creatives.json');
define('TOPUPS_FILE', DATA_PATH . '/topups.json');
define('STATS_FILE', DATA_PATH . '/stats.json');
define('SETTINGS_FILE', DATA_PATH . '/settings.json');
define('NETWORK_FILE', DATA_PATH . '/network.json');

function readJson($file, $default = []) {
    if (!file_exists($file)) return $default;
    $data = file_get_contents($file);
    $decoded = json_decode($data, true);
    return $decoded ?: $default;
}

function writeJson($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function generateId($prefix = '') {
    return $prefix . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
}

function isAdmin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function isUser() {
    return isset($_SESSION['user_id']);
}

function requireAdmin() {
    if (!isAdmin()) {
        ob_end_clean(); header('Location: /admin/login.php'); exit;
    }
}

function requireUser() {
    if (!isUser()) {
        ob_end_clean(); header('Location: /login.php'); exit;
    }
}

function currentUser() {
    if (!isUser()) return null;
    $users = readJson(USERS_FILE);
    foreach ($users as $u) {
        if ($u['id'] === $_SESSION['user_id']) return $u;
    }
    return null;
}

function updateUser($userId, $data) {
    $users = readJson(USERS_FILE);
    foreach ($users as &$u) {
        if ($u['id'] === $userId) {
            $u = array_merge($u, $data);
            break;
        }
    }
    writeJson(USERS_FILE, $users);
}

function getSettings() {
    $defaults = [
        'countries' => [
            ['code' => 'US', 'name' => 'United States'],
            ['code' => 'UK', 'name' => 'United Kingdom'],
            ['code' => 'CA', 'name' => 'Canada'],
            ['code' => 'AU', 'name' => 'Australia'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'FR', 'name' => 'France'],
            ['code' => 'NL', 'name' => 'Netherlands'],
            ['code' => 'NZ', 'name' => 'New Zealand'],
            ['code' => 'IE', 'name' => 'Ireland'],
            ['code' => 'SE', 'name' => 'Sweden']
        ],
        'wallets' => [
            'BTC' => ['address' => 'bc1qxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'network' => 'Bitcoin'],
            'TRC20' => ['address' => 'TXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'network' => 'Tron TRC20 (USDT)'],
            'SOL' => ['address' => 'SoLxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'network' => 'Solana']
        ]
    ];
    $s = readJson(SETTINGS_FILE, $defaults);
    if (!isset($s['countries'])) $s['countries'] = $defaults['countries'];
    if (!isset($s['wallets'])) $s['wallets'] = $defaults['wallets'];
    return $s;
}

function getNetworkNotice() {
    return readJson(NETWORK_FILE, [
        'enabled' => true,
        'text' => 'Now accepting Amazon Gift Cards, CashApp, Zelle and more. Contact support for alternative payment methods.'
    ]);
}

function flash($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function fmtMoney($n) {
    return '$' . number_format((float)$n, 2);
}

function fmtMoneyPrecise($n) {
    return '$' . number_format((float)$n, 4);
}

function fmtNum($n) {
    return number_format((int)$n);
}

function timeAgo($ts) {
    $diff = time() - $ts;
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}


function safeRedirect($url) {
    ob_end_clean();
    header('Location: ' . $url);
    exit;
}

if (!file_exists(USERS_FILE)) {
    writeJson(USERS_FILE, []);
}
if (!file_exists(CAMPAIGNS_FILE)) {
    writeJson(CAMPAIGNS_FILE, []);
}
if (!file_exists(CREATIVES_FILE)) {
    writeJson(CREATIVES_FILE, []);
}
if (!file_exists(TOPUPS_FILE)) {
    writeJson(TOPUPS_FILE, []);
}
if (!file_exists(STATS_FILE)) {
    writeJson(STATS_FILE, []);
}
if (!file_exists(SETTINGS_FILE)) {
    writeJson(SETTINGS_FILE, getSettings());
}
