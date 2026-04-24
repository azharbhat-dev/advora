<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if (!isUser()) { echo json_encode(['success' => false]); exit; }
$user = currentUser();
if (!$user) { echo json_encode(['success' => false]); exit; }

$range = (int)($_GET['range'] ?? 7);
if (!in_array($range, [7,14,30])) $range = 7;

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

// ── 24h ROLLING CHART IN CST ────────────────────────────
// Stats table stores daily totals per campaign. For a 24h rolling view
// we take today + yesterday (in CST) and split each day's totals evenly
// across 24 hours.
$cstTz     = new DateTimeZone('America/Chicago');
$cstNow    = new DateTime('now', $cstTz);
$nowHour   = (int)$cstNow->format('G');   // 0-23
$today     = $cstNow->format('Y-m-d');
$yesterday = (new DateTime('yesterday', $cstTz))->format('Y-m-d');

// Aggregate today + yesterday for this user across ALL their campaigns
$stmt = db()->prepare(
    'SELECT `date`, SUM(impressions) AS imp, SUM(good_hits) AS vw,
            SUM(clicks) AS ht, SUM(spent) AS sp
     FROM stats WHERE user_id = ? AND `date` IN (?, ?)
     GROUP BY `date`'
);
$stmt->execute([$user['id'], $yesterday, $today]);
$dayMap = ['imp'=>0,'vw'=>0,'ht'=>0,'sp'=>0];
$days   = [$yesterday => $dayMap, $today => $dayMap];
foreach ($stmt->fetchAll() as $r) {
    $days[$r['date']] = [
        'imp' => (int)$r['imp'],
        'vw'  => (int)$r['vw'],
        'ht'  => (int)$r['ht'],
        'sp'  => (float)$r['sp'],
    ];
}

$chart = ['labels'=>[],'impressions'=>[],'views'=>[],'hits'=>[],'spend'=>[],'ctr'=>[]];
// Walk 24 hours — hours AFTER "now" belong to YESTERDAY (they come from past day)
// hours 0..nowHour belong to TODAY
for ($h = 0; $h < 24; $h++) {
    $chart['labels'][] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00 CST';
    $isYesterday      = $h > $nowHour;
    $srcDate          = $isYesterday ? $yesterday : $today;
    $d                = $days[$srcDate];

    $hi = (int)floor($d['imp'] / 24);
    $hv = (int)floor($d['vw']  / 24);
    $hh = (int)floor($d['ht']  / 24);
    $hs = round($d['sp'] / 24, 4);

    $chart['impressions'][] = $hi;
    $chart['views'][]       = $hv;
    $chart['hits'][]        = $hh;
    $chart['spend'][]       = $hs;
    $chart['ctr'][]         = $hi > 0 ? round($hv / $hi * 100, 2) : 0;
}

// ── Per-campaign 24h chart (for campaign_view live updates) ──
$campCharts = [];
if (!empty($_GET['campaign_id'])) {
    $cid  = $_GET['campaign_id'];
    // Verify ownership
    $own = false;
    foreach ($userCampaigns as $c) if ($c['campaign_id'] === $cid) { $own = true; break; }
    if ($own) {
        $stmt = db()->prepare(
            'SELECT `date`, SUM(impressions) AS imp, SUM(good_hits) AS vw,
                    SUM(clicks) AS ht, SUM(spent) AS sp
             FROM stats WHERE campaign_id = ? AND `date` IN (?, ?) GROUP BY `date`'
        );
        $stmt->execute([$cid, $yesterday, $today]);
        $cdays = [$yesterday => $dayMap, $today => $dayMap];
        foreach ($stmt->fetchAll() as $r) {
            $cdays[$r['date']] = [
                'imp'=>(int)$r['imp'], 'vw'=>(int)$r['vw'],
                'ht'=>(int)$r['ht'],   'sp'=>(float)$r['sp'],
            ];
        }
        $cc = ['labels'=>$chart['labels'],'impressions'=>[],'views'=>[],'hits'=>[],'spend'=>[],'ctr'=>[]];
        for ($h = 0; $h < 24; $h++) {
            $d  = $cdays[$h > $nowHour ? $yesterday : $today];
            $hi = (int)floor($d['imp']/24); $hv = (int)floor($d['vw']/24);
            $cc['impressions'][] = $hi;
            $cc['views'][]       = $hv;
            $cc['hits'][]        = (int)floor($d['ht']/24);
            $cc['spend'][]       = round($d['sp']/24, 4);
            $cc['ctr'][]         = $hi > 0 ? round($hv/$hi*100, 2) : 0;
        }
        $campCharts[$cid] = $cc;
    }
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