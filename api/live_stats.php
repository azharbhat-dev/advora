<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if (!isUser()) { echo json_encode(['success'=>false]); exit; }
$user = currentUser();
if (!$user)    { echo json_encode(['success'=>false]); exit; }

$range = (int)($_GET['range'] ?? 7);
if (!in_array($range, [7,14,30])) $range = 7;

$campaigns = readJson(CAMPAIGNS_FILE);
$creatives = readJson(CREATIVES_FILE);
$topups    = readJson(TOPUPS_FILE);
$stats     = readJson(STATS_FILE);

$userCampaigns = array_filter($campaigns, fn($c) => $c['user_id'] === $user['id']);
$userCreatives = array_filter($creatives, fn($c) => $c['user_id'] === $user['id']);
$userTopups    = array_filter($topups,    fn($t) => $t['user_id'] === $user['id']);
$userStats     = array_filter($stats,     fn($s) => $s['user_id'] === $user['id']);

$totals = ['impressions'=>0,'views'=>0,'hits'=>0,'spent'=>0];
$campList = [];
foreach ($userCampaigns as $c) {
    $totals['impressions'] += $c['impressions'] ?? 0;
    $totals['views']       += $c['good_hits']   ?? 0;
    $totals['hits']        += $c['clicks']      ?? 0;
    $totals['spent']       += $c['spent']       ?? 0;
    $campList[$c['campaign_id']] = [
        'status'      => $c['status'],
        'impressions' => $c['impressions'] ?? 0,
        'views'       => $c['good_hits']   ?? 0,
        'hits'        => $c['clicks']      ?? 0,
        'spent'       => $c['spent']       ?? 0,
        'budget'      => $c['daily_budget'] ?? $c['budget'] ?? 0,
    ];
}

$crList = [];
foreach ($userCreatives as $cr) { $crList[$cr['id']] = ['status' => $cr['status']]; }

$topupList = [];
foreach ($userTopups as $t) { $topupList[$t['id']] = ['status' => $t['status'], 'amount' => $t['amount'] ?? 0]; }

$chart = ['labels'=>[],'impressions'=>[],'views'=>[],'hits'=>[],'spend'=>[]];
for ($i = $range - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart['labels'][] = date('M d', strtotime($date));
    $di = $dv = $dh = $ds = 0;
    foreach ($userStats as $s) {
        if ($s['date'] === $date) {
            $di += $s['impressions'] ?? 0;
            $dv += $s['good_hits']  ?? 0;
            $dh += $s['clicks']     ?? 0;
            $ds += $s['spent']      ?? 0;
        }
    }
    $chart['impressions'][] = $di;
    $chart['views'][]       = $dv;
    $chart['hits'][]        = $dh;
    $chart['spend'][]       = round($ds, 2);
}

// Unread notifications count for bell badge
$unreadNotifs = countUnread($user['id']);

echo json_encode([
    'success'              => true,
    'balance'              => $user['balance'],
    'totals'               => $totals,
    'campaigns'            => $campList,
    'creatives'            => $crList,
    'topups'               => $topupList,
    'chart'                => $chart,
    'unread_notifications' => $unreadNotifs,
    'timestamp'            => time()
]);