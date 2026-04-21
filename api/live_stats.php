<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');
if (!isUser()) { echo json_encode(['success'=>false]); exit; }
$user = currentUser();
if (!$user) { echo json_encode(['success'=>false]); exit; }

$range = (int)($_GET['range'] ?? 7);
if (!in_array($range,[7,14,30])) $range=7;

$campaigns  = readJson(CAMPAIGNS_FILE);
$creatives  = readJson(CREATIVES_FILE);
$topups     = readJson(TOPUPS_FILE);
$stats      = readJson(STATS_FILE);

$userCampaigns = array_filter($campaigns, fn($c)=>$c['user_id']===$user['id']);
$userCreatives = array_filter($creatives, fn($c)=>$c['user_id']===$user['id']);
$userTopups    = array_filter($topups,    fn($t)=>$t['user_id']===$user['id']);
$userStats     = array_filter($stats,     fn($s)=>$s['user_id']===$user['id']);

$totals=['impressions'=>0,'clicks'=>0,'good_hits'=>0,'spent'=>0];
$campList=[];
foreach($userCampaigns as $c){
    $totals['impressions'] += $c['impressions']??0;
    $totals['clicks']      += $c['clicks']??0;
    $totals['good_hits']   += $c['good_hits']??0;
    $totals['spent']       += $c['spent']??0;
    $campList[$c['campaign_id']]=[
        'status'      => $c['status'],
        'impressions' => $c['impressions']??0,
        'clicks'      => $c['clicks']??0,
        'good_hits'   => $c['good_hits']??0,
        'spent'       => $c['spent']??0,
        'budget'      => $c['budget']??0,
    ];
}

$crList=[];
foreach($userCreatives as $cr){
    $crList[$cr['id']]=['status'=>$cr['status']];
}

$topupList=[];
foreach($userTopups as $t){
    $topupList[$t['id']]=['status'=>$t['status'],'amount'=>$t['amount']??0];
}

$chart=['labels'=>[],'impressions'=>[],'clicks'=>[],'good_hits'=>[],'spend'=>[]];
for($i=$range-1;$i>=0;$i--){
    $date=date('Y-m-d',strtotime("-$i days"));
    $chart['labels'][]=date('M d',strtotime($date));
    $di=$dc=$dg=$ds=0;
    foreach($userStats as $s){
        if($s['date']===$date){$di+=$s['impressions']??0;$dc+=$s['clicks']??0;$dg+=$s['good_hits']??0;$ds+=$s['spent']??0;}
    }
    $chart['impressions'][]=$di; $chart['clicks'][]=$dc; $chart['good_hits'][]=$dg; $chart['spend'][]=round($ds,4);
}

echo json_encode(['success'=>true,'balance'=>$user['balance'],'totals'=>$totals,
    'campaigns'=>$campList,'creatives'=>$crList,'topups'=>$topupList,
    'chart'=>$chart,'timestamp'=>time()]);
