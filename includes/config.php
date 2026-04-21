<?php
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH', ROOT_PATH . '/data');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

define('USERS_FILE',         DATA_PATH . '/users.json');
define('CAMPAIGNS_FILE',     DATA_PATH . '/campaigns.json');
define('CREATIVES_FILE',     DATA_PATH . '/creatives.json');
define('TOPUPS_FILE',        DATA_PATH . '/topups.json');
define('STATS_FILE',         DATA_PATH . '/stats.json');
define('SETTINGS_FILE',      DATA_PATH . '/settings.json');
define('NETWORK_FILE',       DATA_PATH . '/network.json');
define('NOTIFICATIONS_FILE', DATA_PATH . '/notifications.json');
define('INSIGHTS_FILE',       DATA_PATH . '/insights.json');

// ── JSON helpers ─────────────────────────────────────────
function readJson($file, $default = []) {
    if (!file_exists($file)) return $default;
    $data    = file_get_contents($file);
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

// ── Auth ─────────────────────────────────────────────────
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

// ── Settings ─────────────────────────────────────────────
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
            'BTC'   => ['address' => 'bc1qxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',  'network' => 'Bitcoin'],
            'TRC20' => ['address' => 'TXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',   'network' => 'Tron TRC20 (USDT)'],
        ]
    ];
    $s = readJson(SETTINGS_FILE, $defaults);
    if (!isset($s['countries'])) $s['countries'] = $defaults['countries'];
    if (!isset($s['wallets']))   $s['wallets']   = $defaults['wallets'];
    return $s;
}

function getNetworkNotice() {
    return readJson(NETWORK_FILE, [
        'enabled' => false,
        'text'    => ''
    ]);
}

// ── Flash messages ────────────────────────────────────────
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

// ── Formatting ────────────────────────────────────────────
function fmtMoney($n) {
    return '$' . number_format((float)$n, 2);
}

function fmtMoneyPrecise($n) {
    return '$' . number_format((float)$n, 2);
}

function fmtNum($n) {
    return number_format((int)$n);
}

function fmtShort($n) {
    if ($n === null) return '—';
    $n = (int)$n;
    if ($n >= 1000000000) return round($n / 1000000000, 1) . 'B';
    if ($n >= 1000000)    return round($n / 1000000, 1)    . 'M';
    if ($n >= 1000)       return round($n / 1000, 1)       . 'K';
    return (string)$n;
}

function timeAgo($ts) {
    $diff = time() - $ts;
    if ($diff < 60)    return $diff . 's ago';
    if ($diff < 3600)  return floor($diff/60)   . 'm ago';
    if ($diff < 86400) return floor($diff/3600)  . 'h ago';
    return floor($diff/86400) . 'd ago';
}

function safeRedirect($url) {
    ob_end_clean();
    header('Location: ' . $url);
    exit;
}

// ── Notifications ─────────────────────────────────────────
function getNotifications($userId = null) {
    $all = readJson(NOTIFICATIONS_FILE, []);
    if ($userId) {
        return array_values(array_filter($all, fn($n) => $n['user_id'] === $userId));
    }
    return $all;
}

function addNotification($userId, $type, $title, $message) {
    $all   = readJson(NOTIFICATIONS_FILE, []);
    $all[] = [
        'id'         => 'N-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8)),
        'user_id'    => $userId,
        'type'       => $type,
        'title'      => $title,
        'message'    => $message,
        'read'       => false,
        'created_at' => time()
    ];
    writeJson(NOTIFICATIONS_FILE, $all);
}

function markNotificationRead($notifId, $userId) {
    $all = readJson(NOTIFICATIONS_FILE, []);
    foreach ($all as &$n) {
        if ($n['id'] === $notifId && $n['user_id'] === $userId) {
            $n['read'] = true;
            break;
        }
    }
    writeJson(NOTIFICATIONS_FILE, $all);
}

function markAllNotificationsRead($userId) {
    $all = readJson(NOTIFICATIONS_FILE, []);
    foreach ($all as &$n) {
        if ($n['user_id'] === $userId) $n['read'] = true;
    }
    writeJson(NOTIFICATIONS_FILE, $all);
}

function countUnread($userId) {
    $notifs = getNotifications($userId);
    return count(array_filter($notifs, fn($n) => !$n['read']));
}

function notifIcon($type) {
    return match($type) {
        'campaign_approved'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
        'campaign_rejected'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        'topup_approved'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'topup_rejected'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'creative_approved'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
        'creative_rejected'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
        'manual'             => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>',
        default              => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
    };
}

function notifColor($type) {
    return match($type) {
        'campaign_approved', 'topup_approved', 'creative_approved' => 'var(--green)',
        'campaign_rejected', 'topup_rejected', 'creative_rejected' => 'var(--red)',
        'manual'  => 'var(--yellow)',
        default   => 'var(--blue)',
    };
}

// ── Auto-create missing data files ────────────────────────
foreach ([
    USERS_FILE        => [],
    CAMPAIGNS_FILE    => [],
    CREATIVES_FILE    => [],
    TOPUPS_FILE       => [],
    STATS_FILE        => [],
    NOTIFICATIONS_FILE=> [],
    INSIGHTS_FILE      => [],
] as $file => $default) {
    if (!file_exists($file)) writeJson($file, $default);
}

// Create topup screenshots folder
$ssDir = DATA_PATH . '/topup_screenshots';
if (!is_dir($ssDir)) mkdir($ssDir, 0755, true);