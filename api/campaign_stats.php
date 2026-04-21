<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if (!isUser()) { echo json_encode(['success' => false]); exit; }

$id = $_GET['id'] ?? '';
$user = currentUser();
$campaigns = readJson(CAMPAIGNS_FILE);
foreach ($campaigns as $c) {
    if ($c['campaign_id'] === $id && $c['user_id'] === $user['id']) {
        echo json_encode([
            'success' => true,
            'impressions' => $c['impressions'] ?? 0,
            'clicks' => $c['clicks'] ?? 0,
            'good_hits' => $c['good_hits'] ?? 0,
            'spent' => $c['spent'] ?? 0
        ]);
        exit;
    }
}
echo json_encode(['success' => false]);
