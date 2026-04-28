<?php
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ── Path constants ──────────────────────────────────────
define('ROOT_PATH',    dirname(__DIR__));
define('DATA_PATH',    ROOT_PATH . '/data');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// Legacy "file" constants are kept as TAGS that our shim functions
// route to the right DB table. Existing pages reference these, so
// changing them would touch every file. Instead we KEEP them as tags.
define('USERS_FILE',         'users');
define('CAMPAIGNS_FILE',     'campaigns');
define('CREATIVES_FILE',     'creatives');
define('TOPUPS_FILE',        'topups');
define('STATS_FILE',         'stats');
define('SETTINGS_FILE',      'settings');
define('NETWORK_FILE',       'network');
define('NOTIFICATIONS_FILE', 'notifications');
define('INSIGHTS_FILE',      'insights');
define('ADMIN_NOTIF_FILE',   'admin_notifications');

// ── Default global campaign limit ───────────────────────
// Hard cap on how many campaigns a single user may have
// (counts ALL statuses: active, paused, review, pending, rejected).
// Admin can override per-user via /admin/campaign_capacity.php
if (!defined('DEFAULT_CAMPAIGN_LIMIT')) define('DEFAULT_CAMPAIGN_LIMIT', 3);

// ── Daily budget minimum (USD) ──────────────────────────
if (!defined('MIN_DAILY_BUDGET')) define('MIN_DAILY_BUDGET', 50.0);

// ── DB Config ───────────────────────────────────────────
if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
}
if (!defined('DB_HOST'))    define('DB_HOST',    'localhost');
if (!defined('DB_NAME'))    define('DB_NAME',    'u668995464_advoradb');
if (!defined('DB_USER'))    define('DB_USER',    'u668995464_advora');
if (!defined('DB_PASS'))    define('DB_PASS',    'Cl@ssm@t3@007');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// ── PDO Connection (singleton) ──────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
    }
    return $pdo;
}

// ── Auto-detect campaign_limit column (graceful before migration) ─
function _hasCampaignLimitColumn(): bool {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $r = db()->query("SHOW COLUMNS FROM users LIKE 'campaign_limit'")->fetch();
        $has = !empty($r);
    } catch (Exception $e) {
        $has = false;
    }
    return $has;
}

// ── Auto-detect stats_hourly table (graceful before migration) ─
function _hasHourlyStatsTable(): bool {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $r = db()->query("SHOW TABLES LIKE 'stats_hourly'")->fetch();
        $has = !empty($r);
    } catch (Exception $e) {
        $has = false;
    }
    return $has;
}

/**
 * Add stats to the hourly bucket for the current CST hour.
 * Called by admin/stats_injector.php so the dashboard chart shows
 * true hour-accurate data live.
 */
function addHourlyStats($userId, $campaignId, $impressions, $views, $hits, $spent) {
    if (!_hasHourlyStatsTable()) return;
    $cstTz   = new DateTimeZone('America/Chicago');
    $cstNow  = new DateTime('now', $cstTz);
    // Floor to the hour
    $hour    = $cstNow->format('Y-m-d H:00:00');

    $stmt = db()->prepare(
        'INSERT INTO stats_hourly (user_id,campaign_id,hour_cst,impressions,clicks,good_hits,spent)
         VALUES (?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           impressions = impressions + VALUES(impressions),
           clicks      = clicks      + VALUES(clicks),
           good_hits   = good_hits   + VALUES(good_hits),
           spent       = spent       + VALUES(spent)'
    );
    $stmt->execute([$userId, $campaignId, $hour, (int)$impressions, (int)$hits, (int)$views, (float)$spent]);
}

// ── Generic helpers ─────────────────────────────────────
function generateId($prefix = '') {
    return $prefix . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
}

function safeRedirect($url) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Location: ' . $url);
    exit;
}

// ── JSON encode/decode helpers for TEXT fields ──────────
function jenc($v) {
    if ($v === null) return null;
    return json_encode($v, JSON_UNESCAPED_SLASHES);
}
function jdec($v, $default = []) {
    if ($v === null || $v === '') return $default;
    $d = json_decode($v, true);
    return $d !== null ? $d : $default;
}

// ════════════════════════════════════════════════════════
// readJson() / writeJson() — SQL-BACKED SHIMS
// ════════════════════════════════════════════════════════

function readJson($fileTag, $default = []) {
    switch ($fileTag) {
        case 'users':               return _loadUsers();
        case 'campaigns':           return _loadCampaigns();
        case 'creatives':           return _loadCreatives();
        case 'topups':              return _loadTopups();
        case 'stats':               return _loadStats();
        case 'notifications':       return _loadNotifications();
        case 'admin_notifications': return _loadAdminNotifs();
        case 'insights':            return _loadInsights();
        case 'settings':            return _loadSettings();
        case 'network':             return _loadNetworkNotice();
        default:
            if (str_contains($fileTag, 'subscriptions')) return _loadSubscriptions();
            return $default;
    }
}

function writeJson($fileTag, $data) {
    switch ($fileTag) {
        case 'users':               _saveUsers($data); return;
        case 'campaigns':           _saveCampaigns($data); return;
        case 'creatives':           _saveCreatives($data); return;
        case 'topups':              _saveTopups($data); return;
        case 'stats':               _saveStats($data); return;
        case 'notifications':       _saveNotifications($data); return;
        case 'admin_notifications': _saveAdminNotifs($data); return;
        case 'insights':            _saveInsights($data); return;
        case 'settings':            _saveSettings($data); return;
        case 'network':             _saveNetworkNotice($data); return;
        default:
            if (str_contains($fileTag, 'subscriptions')) { _saveSubscriptions($data); return; }
    }
}

// ── USERS ───────────────────────────────────────────────
function _loadUsers() {
    $rows = db()->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
    return array_map('_userRowToArr', $rows);
}
function _userRowToArr($r) {
    return [
        'id'               => $r['id'],
        'username'         => $r['username'],
        'password'         => $r['password'],
        'email'            => $r['email']            ?? '',
        'full_name'        => $r['full_name']        ?? '',
        'phone'            => $r['phone']            ?? '',
        'address'          => $r['address']          ?? '',
        'telegram_id'      => $r['telegram_id']      ?? '',
        'business_name'    => $r['business_name']    ?? '',
        'business_address' => $r['business_address'] ?? '',
        'doc_verified'     => (bool)$r['doc_verified'],
        'balance'          => (float)$r['balance'],
        'account_type'     => $r['account_type']     ?? 'rookie',
        'campaign_limit'   => isset($r['campaign_limit']) ? (int)$r['campaign_limit'] : DEFAULT_CAMPAIGN_LIMIT,
        'disabled'         => (bool)$r['disabled'],
        'created_at'       => (int)$r['created_at'],
    ];
}
function _saveUsers($users) {
    $pdo = db();
    $hasLimitCol = _hasCampaignLimitColumn();
    $pdo->beginTransaction();
    try {
        $existing    = $pdo->query('SELECT id FROM users')->fetchAll(PDO::FETCH_COLUMN);
        $incomingIds = array_column($users, 'id');

        $toDelete = array_diff($existing, $incomingIds);
        if ($toDelete) {
            $ph = implode(',', array_fill(0, count($toDelete), '?'));
            $del = $pdo->prepare("DELETE FROM users WHERE id IN ($ph)");
            $del->execute(array_values($toDelete));
        }

        if ($hasLimitCol) {
            $sql = 'INSERT INTO users (id,username,password,email,full_name,phone,address,telegram_id,business_name,business_address,doc_verified,balance,account_type,campaign_limit,disabled,created_at)
                    VALUES (:id,:username,:password,:email,:full_name,:phone,:address,:telegram_id,:business_name,:business_address,:doc_verified,:balance,:account_type,:campaign_limit,:disabled,:created_at)
                    ON DUPLICATE KEY UPDATE
                      username=VALUES(username), password=VALUES(password), email=VALUES(email),
                      full_name=VALUES(full_name), phone=VALUES(phone), address=VALUES(address),
                      telegram_id=VALUES(telegram_id), business_name=VALUES(business_name),
                      business_address=VALUES(business_address), doc_verified=VALUES(doc_verified),
                      balance=VALUES(balance), account_type=VALUES(account_type),
                      campaign_limit=VALUES(campaign_limit), disabled=VALUES(disabled)';
        } else {
            $sql = 'INSERT INTO users (id,username,password,email,full_name,phone,address,telegram_id,business_name,business_address,doc_verified,balance,account_type,disabled,created_at)
                    VALUES (:id,:username,:password,:email,:full_name,:phone,:address,:telegram_id,:business_name,:business_address,:doc_verified,:balance,:account_type,:disabled,:created_at)
                    ON DUPLICATE KEY UPDATE
                      username=VALUES(username), password=VALUES(password), email=VALUES(email),
                      full_name=VALUES(full_name), phone=VALUES(phone), address=VALUES(address),
                      telegram_id=VALUES(telegram_id), business_name=VALUES(business_name),
                      business_address=VALUES(business_address), doc_verified=VALUES(doc_verified),
                      balance=VALUES(balance), account_type=VALUES(account_type), disabled=VALUES(disabled)';
        }
        $stmt = $pdo->prepare($sql);
        foreach ($users as $u) {
            $params = [
                'id'               => $u['id'],
                'username'         => $u['username'],
                'password'         => $u['password'],
                'email'            => $u['email']            ?? '',
                'full_name'        => $u['full_name']        ?? '',
                'phone'            => $u['phone']            ?? '',
                'address'          => $u['address']          ?? '',
                'telegram_id'      => $u['telegram_id']      ?? '',
                'business_name'    => $u['business_name']    ?? '',
                'business_address' => $u['business_address'] ?? '',
                'doc_verified'     => !empty($u['doc_verified']) ? 1 : 0,
                'balance'          => (float)($u['balance'] ?? 0),
                'account_type'     => $u['account_type']     ?? 'rookie',
                'disabled'         => !empty($u['disabled']) ? 1 : 0,
                'created_at'       => (int)($u['created_at'] ?? time()),
            ];
            if ($hasLimitCol) {
                $params['campaign_limit'] = (int)($u['campaign_limit'] ?? DEFAULT_CAMPAIGN_LIMIT);
            }
            $stmt->execute($params);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ── CAMPAIGNS ───────────────────────────────────────────
function _loadCampaigns() {
    $rows = db()->query('SELECT * FROM campaigns ORDER BY created_at ASC')->fetchAll();
    return array_map('_campRowToArr', $rows);
}
function _campRowToArr($r) {
    return [
        'campaign_id'   => $r['campaign_id'],
        'user_id'       => $r['user_id'],
        'name'          => $r['name'],
        'cpv'           => (float)$r['cpv'],
        'cpc'           => (float)$r['cpc'],
        'creative_id'   => $r['creative_id'],
        'countries'     => jdec($r['countries']),
        'states'        => jdec($r['states']),
        'schedule'      => jdec($r['schedule']),
        'ip_mode'       => $r['ip_mode']     ?? 'off',
        'domain_mode'   => $r['domain_mode'] ?? 'off',
        'ip_list'       => jdec($r['ip_list']),
        'domain_list'   => jdec($r['domain_list']),
        'daily_budget'  => (float)$r['daily_budget'],
        'budget'        => (float)$r['budget'],
        'delivery'      => $r['delivery'] ?? 'even',
        'sources'       => jdec($r['sources']),
        'spent'         => (float)$r['spent'],
        'impressions'   => (int)$r['impressions'],
        'clicks'        => (int)$r['clicks'],
        'good_hits'     => (int)$r['good_hits'],
        'views_count'   => (int)$r['views_count'],
        'status'        => $r['status'],
        'reject_reason' => $r['reject_reason'] ?? '',
        'created_at'    => (int)$r['created_at'],
        'updated_at'    => (int)$r['updated_at'],
    ];
}
function _saveCampaigns($camps) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $existing = $pdo->query('SELECT campaign_id FROM campaigns')->fetchAll(PDO::FETCH_COLUMN);
        $incomingIds = array_column($camps, 'campaign_id');
        $toDelete = array_diff($existing, $incomingIds);
        if ($toDelete) {
            $ph = implode(',', array_fill(0, count($toDelete), '?'));
            $del = $pdo->prepare("DELETE FROM campaigns WHERE campaign_id IN ($ph)");
            $del->execute(array_values($toDelete));
        }

        $sql = 'INSERT INTO campaigns (campaign_id,user_id,name,cpv,cpc,creative_id,countries,states,schedule,ip_mode,domain_mode,ip_list,domain_list,daily_budget,budget,delivery,sources,spent,impressions,clicks,good_hits,views_count,status,reject_reason,created_at,updated_at)
                VALUES (:campaign_id,:user_id,:name,:cpv,:cpc,:creative_id,:countries,:states,:schedule,:ip_mode,:domain_mode,:ip_list,:domain_list,:daily_budget,:budget,:delivery,:sources,:spent,:impressions,:clicks,:good_hits,:views_count,:status,:reject_reason,:created_at,:updated_at)
                ON DUPLICATE KEY UPDATE
                  name=VALUES(name),cpv=VALUES(cpv),cpc=VALUES(cpc),creative_id=VALUES(creative_id),
                  countries=VALUES(countries),states=VALUES(states),schedule=VALUES(schedule),
                  ip_mode=VALUES(ip_mode),domain_mode=VALUES(domain_mode),ip_list=VALUES(ip_list),domain_list=VALUES(domain_list),
                  daily_budget=VALUES(daily_budget),budget=VALUES(budget),delivery=VALUES(delivery),sources=VALUES(sources),
                  spent=VALUES(spent),impressions=VALUES(impressions),clicks=VALUES(clicks),good_hits=VALUES(good_hits),
                  views_count=VALUES(views_count),status=VALUES(status),reject_reason=VALUES(reject_reason),updated_at=VALUES(updated_at)';
        $stmt = $pdo->prepare($sql);
        foreach ($camps as $c) {
            $stmt->execute([
                'campaign_id'   => $c['campaign_id'],
                'user_id'       => $c['user_id'],
                'name'          => $c['name'],
                'cpv'           => (float)($c['cpv'] ?? 0),
                'cpc'           => (float)($c['cpc'] ?? $c['cpv'] ?? 0),
                'creative_id'   => $c['creative_id'] ?? null,
                'countries'     => jenc($c['countries'] ?? []),
                'states'        => jenc($c['states'] ?? []),
                'schedule'      => jenc($c['schedule'] ?? []),
                'ip_mode'       => $c['ip_mode']     ?? 'off',
                'domain_mode'   => $c['domain_mode'] ?? 'off',
                'ip_list'       => jenc($c['ip_list'] ?? []),
                'domain_list'   => jenc($c['domain_list'] ?? []),
                'daily_budget'  => (float)($c['daily_budget'] ?? 0),
                'budget'        => (float)($c['budget'] ?? $c['daily_budget'] ?? 0),
                'delivery'      => $c['delivery'] ?? 'even',
                'sources'       => jenc($c['sources'] ?? []),
                'spent'         => (float)($c['spent'] ?? 0),
                'impressions'   => (int)($c['impressions'] ?? 0),
                'clicks'        => (int)($c['clicks'] ?? 0),
                'good_hits'     => (int)($c['good_hits'] ?? 0),
                'views_count'   => (int)($c['views_count'] ?? 0),
                'status'        => $c['status'] ?? 'review',
                'reject_reason' => $c['reject_reason'] ?? '',
                'created_at'    => (int)($c['created_at'] ?? time()),
                'updated_at'    => (int)($c['updated_at'] ?? time()),
            ]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ── CREATIVES ───────────────────────────────────────────
function _loadCreatives() {
    $rows = db()->query('SELECT * FROM creatives ORDER BY uploaded_at ASC')->fetchAll();
    return array_map(fn($r) => [
        'id'          => $r['id'],
        'user_id'     => $r['user_id'],
        'name'        => $r['name'],
        'filename'    => $r['filename'],
        'stored_file' => $r['stored_file'],
        'file_size'   => (int)$r['file_size'],
        'track_url'   => (bool)$r['track_url'],
        'status'      => $r['status'],
        'uploaded_at' => (int)$r['uploaded_at'],
    ], $rows);
}
function _saveCreatives($items) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $existing = $pdo->query('SELECT id FROM creatives')->fetchAll(PDO::FETCH_COLUMN);
        $incoming = array_column($items, 'id');
        $toDelete = array_diff($existing, $incoming);
        if ($toDelete) {
            $ph = implode(',', array_fill(0, count($toDelete), '?'));
            $pdo->prepare("DELETE FROM creatives WHERE id IN ($ph)")->execute(array_values($toDelete));
        }
        $stmt = $pdo->prepare(
            'INSERT INTO creatives (id,user_id,name,filename,stored_file,file_size,track_url,status,uploaded_at)
             VALUES (:id,:user_id,:name,:filename,:stored_file,:file_size,:track_url,:status,:uploaded_at)
             ON DUPLICATE KEY UPDATE
               user_id=VALUES(user_id),name=VALUES(name),filename=VALUES(filename),
               stored_file=VALUES(stored_file),file_size=VALUES(file_size),
               track_url=VALUES(track_url),status=VALUES(status)'
        );
        foreach ($items as $c) {
            $stmt->execute([
                'id'          => $c['id'],
                'user_id'     => $c['user_id'],
                'name'        => $c['name'],
                'filename'    => $c['filename']    ?? '',
                'stored_file' => $c['stored_file'] ?? '',
                'file_size'   => (int)($c['file_size'] ?? 0),
                'track_url'   => !empty($c['track_url']) ? 1 : 0,
                'status'      => $c['status'] ?? 'pending',
                'uploaded_at' => (int)($c['uploaded_at'] ?? time()),
            ]);
        }
        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

// ── TOPUPS ──────────────────────────────────────────────
function _loadTopups() {
    $rows = db()->query('SELECT * FROM topups ORDER BY created_at ASC')->fetchAll();
    return array_map(fn($r) => [
        'id'               => $r['id'],
        'user_id'          => $r['user_id'],
        'username'         => $r['username'],
        'network'          => $r['network'],
        'network_label'    => $r['network_label'] ?? '',
        'address'          => $r['address'] ?? '',
        'amount'           => (float)$r['amount'],
        'fee'              => (float)$r['fee'],
        'amount_after_fee' => (float)$r['amount_after_fee'],
        'txid'             => $r['txid'],
        'screenshot'       => $r['screenshot'],
        'status'           => $r['status'],
        'created_at'       => (int)$r['created_at'],
        'approved_at'      => $r['approved_at'] ? (int)$r['approved_at'] : null,
    ], $rows);
}
function _saveTopups($items) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $existing = $pdo->query('SELECT id FROM topups')->fetchAll(PDO::FETCH_COLUMN);
        $incoming = array_column($items, 'id');
        $toDelete = array_diff($existing, $incoming);
        if ($toDelete) {
            $ph = implode(',', array_fill(0, count($toDelete), '?'));
            $pdo->prepare("DELETE FROM topups WHERE id IN ($ph)")->execute(array_values($toDelete));
        }
        $stmt = $pdo->prepare(
            'INSERT INTO topups (id,user_id,username,network,network_label,address,amount,fee,amount_after_fee,txid,screenshot,status,created_at,approved_at)
             VALUES (:id,:user_id,:username,:network,:network_label,:address,:amount,:fee,:amount_after_fee,:txid,:screenshot,:status,:created_at,:approved_at)
             ON DUPLICATE KEY UPDATE
               status=VALUES(status), approved_at=VALUES(approved_at),
               amount=VALUES(amount), fee=VALUES(fee), amount_after_fee=VALUES(amount_after_fee),
               screenshot=VALUES(screenshot)'
        );
        foreach ($items as $t) {
            $stmt->execute([
                'id'               => $t['id'],
                'user_id'          => $t['user_id'],
                'username'         => $t['username'],
                'network'          => $t['network'],
                'network_label'    => $t['network_label'] ?? '',
                'address'          => $t['address'] ?? '',
                'amount'           => (float)($t['amount'] ?? 0),
                'fee'              => (float)($t['fee']    ?? 0),
                'amount_after_fee' => (float)($t['amount_after_fee'] ?? $t['amount'] ?? 0),
                'txid'             => $t['txid'] ?? '',
                'screenshot'       => $t['screenshot'] ?? null,
                'status'           => $t['status'] ?? 'pending',
                'created_at'       => (int)($t['created_at'] ?? time()),
                'approved_at'      => $t['approved_at'] ?? null,
            ]);
        }
        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

// ── STATS ───────────────────────────────────────────────
function _loadStats() {
    $rows = db()->query('SELECT * FROM stats ORDER BY date ASC')->fetchAll();
    return array_map(fn($r) => [
        'user_id'     => $r['user_id'],
        'campaign_id' => $r['campaign_id'],
        'date'        => $r['date'],
        'impressions' => (int)$r['impressions'],
        'clicks'      => (int)$r['clicks'],
        'good_hits'   => (int)$r['good_hits'],
        'spent'       => (float)$r['spent'],
    ], $rows);
}
function _saveStats($items) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM stats');
        $stmt = $pdo->prepare(
            'INSERT INTO stats (user_id,campaign_id,date,impressions,clicks,good_hits,spent)
             VALUES (:user_id,:campaign_id,:date,:impressions,:clicks,:good_hits,:spent)
             ON DUPLICATE KEY UPDATE
               impressions=VALUES(impressions),clicks=VALUES(clicks),
               good_hits=VALUES(good_hits),spent=VALUES(spent)'
        );
        foreach ($items as $s) {
            $stmt->execute([
                'user_id'     => $s['user_id'],
                'campaign_id' => $s['campaign_id'],
                'date'        => $s['date'],
                'impressions' => (int)($s['impressions'] ?? 0),
                'clicks'      => (int)($s['clicks']      ?? 0),
                'good_hits'   => (int)($s['good_hits']   ?? 0),
                'spent'       => (float)($s['spent']     ?? 0),
            ]);
        }
        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

// ── NOTIFICATIONS ───────────────────────────────────────
function _loadNotifications() {
    $rows = db()->query('SELECT * FROM notifications ORDER BY created_at ASC')->fetchAll();
    return array_map(fn($r) => [
        'id'         => $r['id'],
        'user_id'    => $r['user_id'],
        'type'       => $r['type'],
        'title'      => $r['title'],
        'message'    => $r['message'],
        'read'       => (bool)$r['is_read'],
        'created_at' => (int)$r['created_at'],
    ], $rows);
}
function _saveNotifications($items) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $existing = $pdo->query('SELECT id FROM notifications')->fetchAll(PDO::FETCH_COLUMN);
        $incoming = array_column($items, 'id');
        $toDelete = array_diff($existing, $incoming);
        if ($toDelete) {
            $ph = implode(',', array_fill(0, count($toDelete), '?'));
            $pdo->prepare("DELETE FROM notifications WHERE id IN ($ph)")->execute(array_values($toDelete));
        }
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (id,user_id,type,title,message,is_read,created_at)
             VALUES (:id,:user_id,:type,:title,:message,:is_read,:created_at)
             ON DUPLICATE KEY UPDATE is_read=VALUES(is_read)'
        );
        foreach ($items as $n) {
            $stmt->execute([
                'id'         => $n['id'],
                'user_id'    => $n['user_id'],
                'type'       => $n['type']  ?? 'manual',
                'title'      => $n['title'] ?? '',
                'message'    => $n['message'] ?? '',
                'is_read'    => !empty($n['read']) ? 1 : 0,
                'created_at' => (int)($n['created_at'] ?? time()),
            ]);
        }
        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

// ── ADMIN NOTIFICATIONS ─────────────────────────────────
function _loadAdminNotifs() {
    $rows = db()->query('SELECT * FROM admin_notifications ORDER BY created_at ASC')->fetchAll();
    return array_map(fn($r) => [
        'id'         => $r['id'],
        'user_id'    => $r['user_id'],
        'username'   => $r['username'],
        'type'       => $r['type'],
        'title'      => $r['title'],
        'message'    => $r['message'],
        'read'       => (bool)$r['is_read'],
        'created_at' => (int)$r['created_at'],
    ], $rows);
}
function _saveAdminNotifs($items) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $existing = $pdo->query('SELECT id FROM admin_notifications')->fetchAll(PDO::FETCH_COLUMN);
        $incoming = array_column($items, 'id');
        $toDelete = array_diff($existing, $incoming);
        if ($toDelete) {
            $ph = implode(',', array_fill(0, count($toDelete), '?'));
            $pdo->prepare("DELETE FROM admin_notifications WHERE id IN ($ph)")->execute(array_values($toDelete));
        }
        $stmt = $pdo->prepare(
            'INSERT INTO admin_notifications (id,user_id,username,type,title,message,is_read,created_at)
             VALUES (:id,:user_id,:username,:type,:title,:message,:is_read,:created_at)
             ON DUPLICATE KEY UPDATE is_read=VALUES(is_read)'
        );
        foreach ($items as $n) {
            $stmt->execute([
                'id'         => $n['id'],
                'user_id'    => $n['user_id'],
                'username'   => $n['username'] ?? '',
                'type'       => $n['type']  ?? 'manual',
                'title'      => $n['title'] ?? '',
                'message'    => $n['message'] ?? '',
                'is_read'    => !empty($n['read']) ? 1 : 0,
                'created_at' => (int)($n['created_at'] ?? time()),
            ]);
        }
        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

// ── INSIGHTS ────────────────────────────────────────────
function _loadInsights() {
    $rows = db()->query('SELECT * FROM insights ORDER BY impressions DESC')->fetchAll();
    return array_map(fn($r) => [
        'code'        => $r['code'],
        'name'        => $r['name'],
        'impressions' => (int)$r['impressions'],
        'win_rate'    => (int)$r['win_rate'],
        'updated_at'  => (int)$r['updated_at'],
    ], $rows);
}
function _saveInsights($items) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $existing = $pdo->query('SELECT code FROM insights')->fetchAll(PDO::FETCH_COLUMN);
        $incoming = array_column($items, 'code');
        $toDelete = array_diff($existing, $incoming);
        if ($toDelete) {
            $ph = implode(',', array_fill(0, count($toDelete), '?'));
            $pdo->prepare("DELETE FROM insights WHERE code IN ($ph)")->execute(array_values($toDelete));
        }
        $stmt = $pdo->prepare(
            'INSERT INTO insights (code,name,impressions,win_rate,updated_at)
             VALUES (:code,:name,:impressions,:win_rate,:updated_at)
             ON DUPLICATE KEY UPDATE
               name=VALUES(name),impressions=VALUES(impressions),
               win_rate=VALUES(win_rate),updated_at=VALUES(updated_at)'
        );
        foreach ($items as $i) {
            $stmt->execute([
                'code'        => $i['code'],
                'name'        => $i['name'] ?? $i['code'],
                'impressions' => (int)($i['impressions'] ?? 0),
                'win_rate'    => (int)($i['win_rate']    ?? 0),
                'updated_at'  => (int)($i['updated_at']  ?? time()),
            ]);
        }
        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

// ── SETTINGS (countries + wallets) ──────────────────────
function _loadSettings() {
    $pdo = db();
    $cRows = $pdo->query('SELECT code,name FROM countries ORDER BY name ASC')->fetchAll();
    $wRows = $pdo->query('SELECT code,address,network FROM wallets')->fetchAll();
    $wallets = [];
    foreach ($wRows as $w) {
        $wallets[$w['code']] = ['address' => $w['address'], 'network' => $w['network']];
    }
    foreach (['BTC','TRC20','ERC20','BEP20'] as $need) {
        if (!isset($wallets[$need])) {
            $wallets[$need] = ['address' => '', 'network' => $need];
        }
    }
    return [
        'countries' => $cRows,
        'wallets'   => $wallets,
    ];
}
function _saveSettings($data) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if (isset($data['countries']) && is_array($data['countries'])) {
            $pdo->exec('DELETE FROM countries');
            $stmt = $pdo->prepare('INSERT INTO countries (code,name) VALUES (:code,:name)');
            foreach ($data['countries'] as $c) {
                $stmt->execute(['code' => $c['code'], 'name' => $c['name']]);
            }
        }
        if (isset($data['wallets']) && is_array($data['wallets'])) {
            $stmt = $pdo->prepare(
                'INSERT INTO wallets (code,address,network) VALUES (:code,:address,:network)
                 ON DUPLICATE KEY UPDATE address=VALUES(address), network=VALUES(network)'
            );
            foreach ($data['wallets'] as $code => $w) {
                $stmt->execute([
                    'code'    => $code,
                    'address' => $w['address'] ?? '',
                    'network' => $w['network'] ?? '',
                ]);
            }
        }
        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

// ── NETWORK NOTICE ──────────────────────────────────────
function _loadNetworkNotice() {
    $stmt = db()->prepare('SELECT v FROM kv_settings WHERE k = ?');
    $stmt->execute(['network_notice']);
    $row = $stmt->fetch();
    if (!$row) return ['enabled' => false, 'text' => ''];
    $data = jdec($row['v'], ['enabled' => false, 'text' => '']);
    return [
        'enabled' => !empty($data['enabled']),
        'text'    => $data['text'] ?? '',
    ];
}
function _saveNetworkNotice($data) {
    $stmt = db()->prepare(
        'INSERT INTO kv_settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)'
    );
    $stmt->execute(['network_notice', jenc([
        'enabled' => !empty($data['enabled']),
        'text'    => $data['text'] ?? '',
    ])]);
}

// ── SUBSCRIPTIONS ───────────────────────────────────────
function _loadSubscriptions() {
    $rows = db()->query('SELECT * FROM subscriptions ORDER BY sort_order ASC, price ASC')->fetchAll();
    return array_map(fn($r) => [
        'id'        => $r['id'],
        'name'      => $r['name'],
        'price'     => (float)$r['price'],
        'campaigns' => (int)$r['campaigns'],
        'tagline'   => $r['tagline']  ?? '',
        'features'  => jdec($r['features'], []),
        'active'    => (bool)$r['active'],
    ], $rows);
}
function _saveSubscriptions($items) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $existing = $pdo->query('SELECT id FROM subscriptions')->fetchAll(PDO::FETCH_COLUMN);
        $incoming = array_column($items, 'id');
        $toDelete = array_diff($existing, $incoming);
        if ($toDelete) {
            $ph = implode(',', array_fill(0, count($toDelete), '?'));
            $pdo->prepare("DELETE FROM subscriptions WHERE id IN ($ph)")->execute(array_values($toDelete));
        }
        $stmt = $pdo->prepare(
            'INSERT INTO subscriptions (id,name,price,campaigns,tagline,features,active,sort_order)
             VALUES (:id,:name,:price,:campaigns,:tagline,:features,:active,:sort_order)
             ON DUPLICATE KEY UPDATE
               name=VALUES(name),price=VALUES(price),campaigns=VALUES(campaigns),
               tagline=VALUES(tagline),features=VALUES(features),active=VALUES(active),
               sort_order=VALUES(sort_order)'
        );
        $order = 0;
        foreach ($items as $p) {
            $stmt->execute([
                'id'         => $p['id'],
                'name'       => $p['name'],
                'price'      => (float)($p['price'] ?? 0),
                'campaigns'  => (int)($p['campaigns'] ?? 1),
                'tagline'    => $p['tagline'] ?? '',
                'features'   => jenc($p['features'] ?? []),
                'active'     => !empty($p['active']) ? 1 : 0,
                'sort_order' => $order++,
            ]);
        }
        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

// ════════════════════════════════════════════════════════
// AUTH
// ════════════════════════════════════════════════════════
function isAdmin() { return isset($_SESSION['admin']) && $_SESSION['admin'] === true; }
function isUser()  { return isset($_SESSION['user_id']); }

function requireAdmin() {
    if (!isAdmin()) {
        while (ob_get_level() > 0) ob_end_clean();
        header('Location: /admin/login.php'); exit;
    }
}

function requireUser() {
    if (!isUser()) {
        while (ob_get_level() > 0) ob_end_clean();
        header('Location: /login.php'); exit;
    }
    $stmt = db()->prepare('SELECT disabled FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $r = $stmt->fetch();
    if (!$r) {
        session_destroy();
        while (ob_get_level() > 0) ob_end_clean();
        header('Location: /login.php'); exit;
    }
    if (!empty($r['disabled'])) {
        session_destroy();
        while (ob_get_level() > 0) ob_end_clean();
        header('Location: /login.php?disabled=1'); exit;
    }
}

function currentUser() {
    if (!isUser()) return null;
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $r = $stmt->fetch();
    return $r ? _userRowToArr($r) : null;
}

function updateUser($userId, $data) {
    if (empty($data)) return;
    $allowed = ['username','password','email','full_name','phone','address','telegram_id',
                'business_name','business_address','doc_verified','balance','account_type','disabled','campaign_limit'];
    $sets = [];
    $params = ['id' => $userId];
    foreach ($data as $k => $v) {
        if (!in_array($k, $allowed, true)) continue;
        if ($k === 'campaign_limit' && !_hasCampaignLimitColumn()) continue;
        if ($k === 'doc_verified' || $k === 'disabled') $v = !empty($v) ? 1 : 0;
        $sets[] = "$k = :$k";
        $params[$k] = $v;
    }
    if (empty($sets)) return;
    $sql = 'UPDATE users SET ' . implode(',', $sets) . ' WHERE id = :id';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
}

// ════════════════════════════════════════════════════════
// CAMPAIGN CAPACITY HELPERS
// ════════════════════════════════════════════════════════

/** Returns the campaign limit for a given user (per-user override or global default). */
function getUserCampaignLimit($userId): int {
    if (_hasCampaignLimitColumn()) {
        $stmt = db()->prepare('SELECT campaign_limit FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $v = $stmt->fetchColumn();
        if ($v !== false && $v !== null) return (int)$v;
    }
    return (int)DEFAULT_CAMPAIGN_LIMIT;
}

/** Returns the number of campaigns a user currently owns (ALL statuses). */
function getUserCampaignCount($userId): int {
    $stmt = db()->prepare('SELECT COUNT(*) FROM campaigns WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/** True if user is at or over their campaign limit. */
function userAtCampaignLimit($userId): bool {
    return getUserCampaignCount($userId) >= getUserCampaignLimit($userId);
}

// ════════════════════════════════════════════════════════
// HIGH-LEVEL HELPERS
// ════════════════════════════════════════════════════════
function getSettings() { return _loadSettings(); }
function getNetworkNotice() { return _loadNetworkNotice(); }

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

// ── Formatting ──────────────────────────────────────────
function fmtMoney($n)        { return '$' . number_format((float)$n, 2); }
function fmtMoneyPrecise($n) { return '$' . number_format((float)$n, 2); }
function fmtNum($n)          { return number_format((int)$n); }
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
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}

// ════════════════════════════════════════════════════════
// NOTIFICATIONS (user)
// ════════════════════════════════════════════════════════
function getNotifications($userId = null) {
    if ($userId) {
        $stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at ASC');
        $stmt->execute([$userId]);
    } else {
        $stmt = db()->query('SELECT * FROM notifications ORDER BY created_at ASC');
    }
    $rows = $stmt->fetchAll();
    return array_map(fn($r) => [
        'id'         => $r['id'],
        'user_id'    => $r['user_id'],
        'type'       => $r['type'],
        'title'      => $r['title'],
        'message'    => $r['message'],
        'read'       => (bool)$r['is_read'],
        'created_at' => (int)$r['created_at'],
    ], $rows);
}

function addNotification($userId, $type, $title, $message) {
    $stmt = db()->prepare(
        'INSERT INTO notifications (id,user_id,type,title,message,is_read,created_at)
         VALUES (?,?,?,?,?,0,?)'
    );
    $stmt->execute([
        'N-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8)),
        $userId, $type, $title, $message, time()
    ]);
}

function markNotificationRead($notifId, $userId) {
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->execute([$notifId, $userId]);
}
function markAllNotificationsRead($userId) {
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    $stmt->execute([$userId]);
}
function countUnread($userId) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function notifIcon($type) {
    return match($type) {
        'campaign_approved'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
        'campaign_rejected'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        'topup_approved'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'topup_rejected'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'creative_approved'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
        'creative_rejected'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
        'manual'             => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/></svg>',
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

// ════════════════════════════════════════════════════════
// ADMIN NOTIFICATIONS (user activity)
// ════════════════════════════════════════════════════════
function addAdminNotification($userId, $username, $type, $title, $message) {
    $stmt = db()->prepare(
        'INSERT INTO admin_notifications (id,user_id,username,type,title,message,is_read,created_at)
         VALUES (?,?,?,?,?,?,0,?)'
    );
    $stmt->execute([
        'AN-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8)),
        $userId, $username, $type, $title, $message, time()
    ]);
    db()->exec(
        "DELETE FROM admin_notifications
         WHERE id NOT IN (
             SELECT id FROM (
                 SELECT id FROM admin_notifications ORDER BY created_at DESC LIMIT 500
             ) x
         )"
    );
}

function getAdminNotifications($userId = null) {
    if ($userId) {
        $stmt = db()->prepare('SELECT * FROM admin_notifications WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
    } else {
        $stmt = db()->query('SELECT * FROM admin_notifications ORDER BY created_at DESC');
    }
    $rows = $stmt->fetchAll();
    return array_map(fn($r) => [
        'id'         => $r['id'],
        'user_id'    => $r['user_id'],
        'username'   => $r['username'],
        'type'       => $r['type'],
        'title'      => $r['title'],
        'message'    => $r['message'],
        'read'       => (bool)$r['is_read'],
        'created_at' => (int)$r['created_at'],
    ], $rows);
}

function countUnreadAdminNotifs() {
    return (int)db()->query('SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0')->fetchColumn();
}

/** Total count of admin notifications (read + unread). Used to detect new activity. */
function countTotalAdminNotifs() {
    return (int)db()->query('SELECT COUNT(*) FROM admin_notifications')->fetchColumn();
}

function markAllAdminNotifsRead() {
    db()->exec('UPDATE admin_notifications SET is_read = 1');
}

// ── Ensure upload folders exist ─────────────────────────
$ssDir = DATA_PATH . '/topup_screenshots';
if (!is_dir($ssDir)) @mkdir($ssDir, 0755, true);
$crDir = DATA_PATH . '/creatives_files';
if (!is_dir($crDir)) @mkdir($crDir, 0755, true);
