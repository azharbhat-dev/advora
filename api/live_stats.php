<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if (!isUser()) { echo json_encode(['success' => false]); exit; }
$user = currentUser();
if (!$user) { echo json_encode(['success' => false]); exit; }

// ── Campaigns ──────────────────────────────────────────
$stmt = db()->prepare('SELECT * FROM campaigns WHERE user_id = ?');
$stmt->execute([$user['id']]);
$userCampaigns = $stmt->fetchAll();

$totals = ['impressions'=>0,'views'=>0,'hits'=>0,'spent'=>0];
$campList = [];
foreach ($userCampaigns as $c) {
    $totals['impressions'] += (int)$c['impressions'];
    $totals['views']       += (int)$c['good_hits'];
    $totals['hits']        += (int)$c['clicks'];
    $totals['spent']       += (float)$c['spent'];
    $campList[$c['campaign_id']] = [
        'status'      => $c['status'],
        'impressions' => (int)$c['impressions'],
        'views'       => (int)$c['good_hits'],
        'hits'        => (int)$c['clicks'],
        'spent'       => (float)$c['spent'],
        'budget'      => (float)($c['daily_budget'] ?: $c['budget']),
    ];
}

// ── Creatives ──────────────────────────────────────────
$stmt = db()->prepare('SELECT id, status FROM creatives WHERE user_id = ?');
$stmt->execute([$user['id']]);
$crList = [];
foreach ($stmt->fetchAll() as $cr) { $crList[$cr['id']] = ['status' => $cr['status']]; }

// ── Topups ─────────────────────────────────────────────
$stmt = db()->prepare('SELECT id, status, amount FROM topups WHERE user_id = ?');
$stmt->execute([$user['id']]);
$topupList = [];
foreach ($stmt->fetchAll() as $t) {
    $topupList[$t['id']] = ['status' => $t['status'], 'amount' => (float)$t['amount']];
}

// ════════════════════════════════════════════════════════
// 24-HOUR ROLLING CHART — true hourly buckets
// Each hour shows ONLY what was actually injected during that hour.
// Resets naturally as time moves forward (older hours fall off).
// ════════════════════════════════════════════════════════
$cstTz   = new DateTimeZone('America/Chicago');
$cstNow  = new DateTime('now', $cstTz);
$nowHour = (int)$cstNow->format('G');

// Build the 24 hour buckets we want to display, oldest first → current
// Window: from (now - 23h, hour-floored) up to current hour (inclusive).
$windowStart = (clone $cstNow)->modify('-23 hours');
$windowStart = new DateTime($windowStart->format('Y-m-d H:00:00'), $cstTz);

$labels = [];
$bucketKeys = [];   // 'Y-m-d H:00:00' strings for matching
for ($i = 0; $i < 24; $i++) {
    $b = (clone $windowStart)->modify("+{$i} hours");
    $bucketKeys[] = $b->format('Y-m-d H:00:00');
    $labels[]     = $b->format('H:00') . ' CST';
}

// Initialize all-zero buckets
$emptyBucket = ['imp'=>0,'vw'=>0,'ht'=>0,'sp'=>0.0];
$userBuckets = array_fill_keys($bucketKeys, $emptyBucket);

// Per-campaign buckets if a campaign_id was passed
$campCharts = [];
$wantCampId = $_GET['campaign_id'] ?? null;
$campOwn = false;
if ($wantCampId) {
    foreach ($userCampaigns as $c) {
        if ($c['campaign_id'] === $wantCampId) { $campOwn = true; break; }
    }
}
$campBuckets = $campOwn ? array_fill_keys($bucketKeys, $emptyBucket) : null;

// Pull from stats_hourly if the table exists
if (_hasHourlyStatsTable()) {
    $stmt = db()->prepare(
        'SELECT campaign_id,
                DATE_FORMAT(hour_cst, "%Y-%m-%d %H:%i:%s") AS hk,
                impressions, clicks, good_hits, spent
         FROM stats_hourly
         WHERE user_id = ? AND hour_cst >= ? AND hour_cst <= ?'
    );
    $startStr = $windowStart->format('Y-m-d H:00:00');
    $endStr   = (clone $cstNow)->format('Y-m-d H:00:00');
    $stmt->execute([$user['id'], $startStr, $endStr]);

    foreach ($stmt->fetchAll() as $r) {
        // hk should match a bucket key. MySQL DATETIME columns return without T,
        // so the format above produces "Y-m-d H:00:00" matching bucketKeys exactly.
        $hk = $r['hk'];
        if (!isset($userBuckets[$hk])) continue;
        $userBuckets[$hk]['imp'] += (int)$r['impressions'];
        $userBuckets[$hk]['vw']  += (int)$r['good_hits'];
        $userBuckets[$hk]['ht']  += (int)$r['clicks'];
        $userBuckets[$hk]['sp']  += (float)$r['spent'];

        if ($campBuckets !== null && $r['campaign_id'] === $wantCampId) {
            $campBuckets[$hk]['imp'] += (int)$r['impressions'];
            $campBuckets[$hk]['vw']  += (int)$r['good_hits'];
            $campBuckets[$hk]['ht']  += (int)$r['clicks'];
            $campBuckets[$hk]['sp']  += (float)$r['spent'];
        }
    }
}

// Build the response chart arrays
function _bucketsToChart($labels, $buckets) {
    $imp = $vw = $ht = $sp = $ctr = [];
    foreach ($buckets as $b) {
        $imp[] = (int)$b['imp'];
        $vw[]  = (int)$b['vw'];
        $ht[]  = (int)$b['ht'];
        $sp[]  = round((float)$b['sp'], 4);
        $ctr[] = $b['imp'] > 0 ? round($b['vw'] / $b['imp'] * 100, 2) : 0;
    }
    return [
        'labels'      => $labels,
        'impressions' => $imp,
        'views'       => $vw,
        'hits'        => $ht,
        'spend'       => $sp,
        'ctr'         => $ctr,
    ];
}

$chart = _bucketsToChart($labels, $userBuckets);
if ($campBuckets !== null) {
    $campCharts[$wantCampId] = _bucketsToChart($labels, $campBuckets);
}

// Unread notifications for bell
$stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$user['id']]);
$unreadNotifs = (int)$stmt->fetchColumn();

echo json_encode([
    'success'              => true,
    'balance'              => (float)$user['balance'],
    'totals'               => $totals,
    'campaigns'            => $campList,
    'creatives'            => $crList,
    'topups'               => $topupList,
    'chart'                => $chart,
    'camp_charts'          => $campCharts,
    'unread_notifications' => $unreadNotifs,
    'cst_now_hour'         => $nowHour,
    'timestamp'            => time(),
]);
