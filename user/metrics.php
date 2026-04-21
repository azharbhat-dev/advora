<?php
require_once __DIR__ . '/../includes/user_header.php';

$campaigns = readJson(CAMPAIGNS_FILE);
$userCampaigns = array_filter($campaigns, fn($c) => $c['user_id'] === $user['id']);
$stats = readJson(STATS_FILE);
$userStats = array_filter($stats, fn($s) => $s['user_id'] === $user['id']);

$range = (int)($_GET['range'] ?? 30);
if (!in_array($range, [7, 14, 30, 90])) $range = 30;

// Build per-campaign totals for the selected date range
$cutoff = date('Y-m-d', strtotime("-{$range} days"));
$campaignStats = [];
foreach ($userCampaigns as $c) {
    $campaignStats[$c['campaign_id']] = [
        'name'        => $c['name'],
        'campaign_id' => $c['campaign_id'],
        'status'      => $c['status'],
        'views'       => 0,
        'impressions' => 0,
        'clicks'      => 0,
        'spent'       => 0,
    ];
}
// Use all-time from campaign object (stats file may vary)
foreach ($userCampaigns as $c) {
    $cid = $c['campaign_id'];
    if (!isset($campaignStats[$cid])) continue;
    $campaignStats[$cid]['views']       = $c['good_hits'] ?? 0;
    $campaignStats[$cid]['impressions'] = $c['impressions'] ?? 0;
    $campaignStats[$cid]['clicks']      = $c['clicks'] ?? 0;
    $campaignStats[$cid]['spent']       = $c['spent'] ?? 0;
}

$totalViews = array_sum(array_column($campaignStats, 'views'));
$totalImp   = array_sum(array_column($campaignStats, 'impressions'));
$totalClk   = array_sum(array_column($campaignStats, 'clicks'));
$totalSpend = array_sum(array_column($campaignStats, 'spent'));
$ctr = $totalImp > 0 ? round($totalClk / $totalImp * 100, 2) : 0;
$cpc = $totalClk > 0 ? round($totalSpend / $totalClk, 4) : 0;

// CSV export
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="advora-metrics-' . date('Y-m-d') . '.csv"');
    echo "Campaign ID,Campaign Name,Status,Views,Impressions,Clicks,CTR (%),Spent ($),CPC ($)\n";
    foreach ($campaignStats as $row) {
        $rctr = $row['impressions'] > 0 ? round($row['clicks'] / $row['impressions'] * 100, 2) : 0;
        $rcpc = $row['clicks'] > 0 ? round($row['spent'] / $row['clicks'], 4) : 0;
        echo implode(',', [
            $row['campaign_id'],
            '"' . str_replace('"', '""', $row['name']) . '"',
            $row['status'],
            $row['views'],
            $row['impressions'],
            $row['clicks'],
            $rctr,
            number_format($row['spent'], 4),
            number_format($rcpc, 4),
        ]) . "\n";
    }
    exit;
}
?>

<div class="page-header">
  <div>
    <div class="page-title">Metrics</div>
    <div class="page-subtitle">Historical campaign performance data</div>
  </div>
  <a href="?range=<?= $range ?>&export=1" class="btn btn-primary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Export CSV
  </a>
</div>

<!-- Summary stat cards -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
    </div>
    <div class="stat-label">Total Views</div>
    <div class="stat-value"><?= fmtNum($totalViews) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(77,158,255,.1);color:var(--blue);border-color:rgba(77,158,255,.12)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
    </div>
    <div class="stat-label">Impressions</div>
    <div class="stat-value"><?= fmtNum($totalImp) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(0,229,153,.1);color:var(--green);border-color:rgba(0,229,153,.12)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
    </div>
    <div class="stat-label">Clicks</div>
    <div class="stat-value"><?= fmtNum($totalClk) ?></div>
    <div class="stat-change">CTR: <?= $ctr ?>%</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(255,144,0,.1);color:var(--orange);border-color:rgba(255,144,0,.12)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    </div>
    <div class="stat-label">Total Spent</div>
    <div class="stat-value"><?= fmtMoney($totalSpend) ?></div>
    <div class="stat-change">Avg CPC: <?= fmtMoneyPrecise($cpc) ?></div>
  </div>
</div>

<!-- Campaign breakdown table -->
<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">Campaign Breakdown</div>
      <div style="font-size:12px;color:var(--text-2);margin-top:2px">All-time totals per campaign</div>
    </div>
    <a href="?range=<?= $range ?>&export=1" class="btn btn-secondary btn-sm">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      CSV
    </a>
  </div>

  <?php if (empty($campaignStats)): ?>
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
    <h3>No campaigns yet</h3>
    <p>Create a campaign to see your metrics here</p>
    <a href="/user/create_campaign.php" class="btn btn-primary" style="margin-top:14px">Create Campaign</a>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Campaign</th>
          <th>Status</th>
          <th>Views</th>
          <th>Impressions</th>
          <th>Clicks</th>
          <th>CTR</th>
          <th>Spent</th>
          <th>CPC</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($campaignStats as $row):
        $rctr = $row['impressions'] > 0 ? round($row['clicks'] / $row['impressions'] * 100, 2) : 0;
        $rcpc = $row['clicks'] > 0 ? round($row['spent'] / $row['clicks'], 4) : 0;
        $sc = ['active'=>'badge-success','pending'=>'badge-pending','paused'=>'badge-muted','review'=>'badge-info','rejected'=>'badge-danger'][$row['status']]??'badge-muted';
      ?>
      <tr>
        <td>
          <a href="/user/campaign_view.php?id=<?= urlencode($row['campaign_id']) ?>" style="font-weight:600;color:var(--text)"><?= htmlspecialchars($row['name']) ?></a>
          <div style="font-size:11px;color:var(--text-3);font-family:'Courier New',monospace;margin-top:2px"><?= $row['campaign_id'] ?></div>
        </td>
        <td><span class="badge <?= $sc ?>"><?= $row['status'] ?></span></td>
        <td style="font-weight:600"><?= fmtNum($row['views']) ?></td>
        <td><?= fmtNum($row['impressions']) ?></td>
        <td><?= fmtNum($row['clicks']) ?></td>
        <td><?= $rctr ?>%</td>
        <td><?= fmtMoney($row['spent']) ?></td>
        <td style="font-size:12px;color:var(--text-2)"><?= fmtMoneyPrecise($rcpc) ?></td>
        <td>
          <a href="/user/campaign_view.php?id=<?= urlencode($row['campaign_id']) ?>" class="btn btn-secondary btn-sm">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
