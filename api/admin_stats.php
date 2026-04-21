<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');
if (!isAdmin()) { echo json_encode(['success'=>false]); exit; }

$campaigns = readJson(CAMPAIGNS_FILE);
$users     = readJson(USERS_FILE);
$topups    = readJson(TOPUPS_FILE);
$creatives = readJson(CREATIVES_FILE);

$totals=['impressions'=>0,'clicks'=>0,'good_hits'=>0,'spent'=>0,'balance'=>0,'users'=>count($users),'campaigns'=>count($campaigns)];
foreach($campaigns as $c){
    $totals['impressions'] += $c['impressions']??0;
    $totals['clicks']      += $c['clicks']??0;
    $totals['good_hits']   += $c['good_hits']??0;
    $totals['spent']       += $c['spent']??0;
}
foreach($users as $u) $totals['balance'] += $u['balance']??0;

$pending=[
    'campaigns' => count(array_filter($campaigns, fn($c)=>in_array($c['status'],['pending','review']))),
    'creatives' => count(array_filter($creatives, fn($c)=>$c['status']==='pending')),
    'topups'    => count(array_filter($topups,    fn($t)=>$t['status']==='pending')),
];

$campList=[];
foreach($campaigns as $c){
    $campList[$c['campaign_id']]=[
        'status'      => $c['status'],
        'impressions' => $c['impressions']??0,
        'clicks'      => $c['clicks']??0,
        'good_hits'   => $c['good_hits']??0,
        'spent'       => $c['spent']??0,
    ];
}

$userList=[];
foreach($users as $u){
    $userList[$u['id']]=['balance'=>$u['balance']??0,'disabled'=>$u['disabled']??false];
}

$crList=[];
foreach($creatives as $cr){
    $crList[$cr['id']]=['status'=>$cr['status']];
}

$topupList=[];
foreach($topups as $t){
    $topupList[$t['id']]=['status'=>$t['status']];
}

echo json_encode(['success'=>true,'totals'=>$totals,'pending'=>$pending,
    'campaigns'=>$campList,'users'=>$userList,'creatives'=>$crList,
    'topups'=>$topupList,'timestamp'=>time()]);
