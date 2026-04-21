<?php
require_once __DIR__ . '/../includes/user_header.php';

// CSV export - before any output
if (isset($_GET['export'])) {
    while (ob_get_level() > 0) ob_end_clean();
    $exportCampaigns = array_filter(readJson(CAMPAIGNS_FILE), fn($c) => $c['user_id'] === $user['id']);
    $exportStats     = array_filter(readJson(STATS_FILE),     fn($s) => $s['user_id'] === $user['id']);
    $cutoff = date('Y-m-d', strtotime('-14 days'));

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="advora-metrics-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Campaign ID', 'Campaign Name', 'Status', 'Views', 'Impressions', 'Hits', 'CTR (%)', 'Spent ($)', 'CPV ($)']);
    foreach ($exportCampaigns as $c) {
        // Only include campaigns that have stats within last 14 days
        $hasRecent = false;
        foreach ($exportStats as $s) {
            if ($s['campaign_id'] === $c['campaign_id'] && $s['date'] >= $cutoff) { $hasRecent = true; break; }
        }
        if (!$hasRecent && ($c['impressions']??0) === 0) continue;
        $rctr = ($c['impressions']??0) > 0 ? round(($c['good_hits']??0)/($c['impressions']??1)*100,2) : 0;
        $rcpv = ($c['good_hits']??0)   > 0 ? round(($c['spent']??0)/($c['good_hits']??1),2) : 0;
        fputcsv($out, [
            $c['campaign_id'], $c['name'], $c['status'],
            $c['good_hits']??0, $c['impressions']??0, $c['clicks']??0,
            $rctr, number_format($c['spent']??0,2), number_format($rcpv,2)
        ]);
    }
    fclose($out);
    exit;
}

$range = (int)($_GET['range'] ?? 7);
if (!in_array($range, [7, 14])) $range = 7;

$campaigns = readJson(CAMPAIGNS_FILE);
$stats     = readJson(STATS_FILE);

$userCampaigns = array_filter($campaigns, fn($c) => $c['user_id'] === $user['id']);
$userStats     = array_filter($stats,     fn($s) => $s['user_id'] === $user['id']);

$cutoff = date('Y-m-d', strtotime("-{$range} days"));

// Build per-campaign stats summed within the selected range
$campaignStats = [];
foreach ($userCampaigns as $c) {
    $cid  = $c['campaign_id'];
    $views = $imp = $hits = $spent = 0;

    foreach ($userStats as $s) {
        if ($s['campaign_id'] === $cid && $s['date'] >= $cutoff) {
            $views += $s['good_hits']  ?? 0;
            $imp   += $s['impressions'] ?? 0;
            $hits  += $s['clicks']     ?? 0;
            $spent += $s['spent']      ?? 0;
        }
    }

    // Skip campaigns with zero activity in this window
    if ($views === 0 && $imp === 0 && $hits === 0 && $spent == 0) continue;

    $campaignStats[] = [
        'campaign_id' => $cid,
        'name'        => $c['name'],
        'status'      => $c['status'],
        'views'       => $views,
        'impressions' => $imp,
        'hits'        => $hits,
        'spent'       => $spent,
    ];
}

// Sort by views desc
usort($campaignStats, fn($a,$b) => $b['views'] <=> $a['views']);
?>

<div class="page-header">
  <div>
    <div class="page-title">Metrics</div>
    <div class="page-subtitle">Campaign performance for the selected period</div>
  </div>
  <a href="?range=<?= $range ?>&export=1" class="btn btn-primary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Export CSV
  </a>
</div>

<div class="card">
  <div class="card-header" style="flex-wrap:wrap;gap:12px">
    <div>
      <div class="card-title">Campaign Breakdown</div>
      <div style="font-size:12px;color:var(--text-2);margin-top:2px">All-time totals per campaign</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <a href="?range=7"  class="range-btn <?= $range===7  ? 'active':'' ?>">Last 7 Days</a>
      <a href="?range=14" class="range-btn <?= $range===14 ? 'active':'' ?>">Last 14 Days</a>
      <a href="?range=<?= $range ?>&export=1" class="btn btn-secondary btn-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        CSV
      </a>
    </div>
  </div>

  <?php if (empty($campaignStats)): ?>
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
    <h3>No data for this period</h3>
    <p>No campaign activity in the last <?= $range ?> days</p>
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
          <th>Hits</th>
          <th>CTR</th>
          <th>Spent</th>
          <th>CPV</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($campaignStats as $row):
        $rctr = $row['impressions'] > 0 ? round($row['views'] / $row['impressions'] * 100, 2) : 0;
        $rcpv = $row['views']       > 0 ? round($row['spent'] / $row['views'], 2) : 0;
        $sc   = ['active'=>'badge-success','pending'=>'badge-pending','paused'=>'badge-muted','review'=>'badge-info','rejected'=>'badge-danger'][$row['status']] ?? 'badge-muted';
        $sl   = $row['status'] === 'review' ? 'Under Review' : $row['status'];
      ?>
      <tr>
        <td>
          <a href="/user/campaign_view.php?id=<?= urlencode($row['campaign_id']) ?>" style="font-weight:600;color:var(--text)"><?= htmlspecialchars($row['name']) ?></a>
          <div style="font-size:11px;color:var(--text-3);font-family:'Courier New',monospace;margin-top:2px"><?= $row['campaign_id'] ?></div>
        </td>
        <td><span class="badge <?= $sc ?>"><?= $sl ?></span></td>
        <td style="font-weight:600"><?= fmtNum($row['views']) ?></td>
        <td><?= fmtNum($row['impressions']) ?></td>
        <td><?= fmtNum($row['hits']) ?></td>
        <td><?= $rctr ?>%</td>
        <td><?= fmtMoney($row['spent']) ?></td>
        <td><?= fmtMoney($rcpv) ?></td>
        <td><a href="/user/campaign_view.php?id=<?= urlencode($row['campaign_id']) ?>" class="btn btn-secondary btn-sm">View</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<style>
.range-btn{background:var(--bg-3);border:1px solid var(--border-2);color:var(--text-2);padding:5px 12px;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s;text-decoration:none;display:inline-block}
.range-btn:hover{color:var(--text);border-color:var(--border-hi);text-decoration:none}
.range-btn.active{background:var(--yellow-dim);color:var(--yellow);border-color:rgba(255,200,0,.25)}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>