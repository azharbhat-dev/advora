<?php
require_once __DIR__ . '/../includes/user_header.php';

// CSV export — run before any output
if (isset($_GET['export'])) {
    while (ob_get_level() > 0) ob_end_clean();
    $range = (int)($_GET['range'] ?? 7);
    if (!in_array($range, [7,14,30])) $range = 7;

    $cstTz  = new DateTimeZone('America/Chicago');
    $cutoff = (new DateTime('now', $cstTz))->modify("-{$range} days")->format('Y-m-d');

    $stmt = db()->prepare(
        'SELECT c.campaign_id, c.name, c.status,
                COALESCE(SUM(s.impressions),0) AS imp,
                COALESCE(SUM(s.good_hits),0)   AS vw,
                COALESCE(SUM(s.clicks),0)      AS ht,
                COALESCE(SUM(s.spent),0)       AS sp
         FROM campaigns c
         LEFT JOIN stats s ON s.campaign_id = c.campaign_id AND s.`date` >= ?
         WHERE c.user_id = ?
         GROUP BY c.campaign_id
         ORDER BY vw DESC'
    );
    $stmt->execute([$cutoff, $user['id']]);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="advora-metrics-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Campaign ID','Campaign Name','Status','Views','Impressions','Hits','CTR (%)','Spent ($)','CPV ($)']);
    foreach ($stmt->fetchAll() as $r) {
        $rctr = $r['imp'] > 0 ? round($r['vw'] / $r['imp'] * 100, 2) : 0;
        $rcpv = $r['vw']  > 0 ? round($r['sp'] / $r['vw'], 4) : 0;
        fputcsv($out, [
            $r['campaign_id'], $r['name'], $r['status'],
            $r['vw'], $r['imp'], $r['ht'], $rctr,
            number_format($r['sp'],2), number_format($rcpv,4),
        ]);
    }
    fclose($out);
    exit;
}

$range = (int)($_GET['range'] ?? 7);
if (!in_array($range, [7,14,30])) $range = 7;

$cstTz  = new DateTimeZone('America/Chicago');
$cutoff = (new DateTime('now', $cstTz))->modify("-{$range} days")->format('Y-m-d');

// ── Per-day totals (historical) ────────────────────────
$stmt = db()->prepare(
    'SELECT `date`,
            COALESCE(SUM(impressions),0) AS imp,
            COALESCE(SUM(good_hits),0)   AS vw,
            COALESCE(SUM(clicks),0)      AS ht,
            COALESCE(SUM(spent),0)       AS sp
     FROM stats WHERE user_id = ? AND `date` >= ?
     GROUP BY `date` ORDER BY `date` ASC'
);
$stmt->execute([$user['id'], $cutoff]);
$dailyRaw = $stmt->fetchAll();

$sumImp = $sumVw = $sumHt = 0; $sumSp = 0.0;
foreach ($dailyRaw as $r) {
    $sumImp += (int)$r['imp'];
    $sumVw  += (int)$r['vw'];
    $sumHt  += (int)$r['ht'];
    $sumSp  += (float)$r['sp'];
}
$totalCtr = $sumImp > 0 ? round($sumVw / $sumImp * 100, 2) : 0;
$avgCpv   = $sumVw  > 0 ? round($sumSp / $sumVw, 4)         : 0;

// ── Per-campaign breakdown within window ───────────────
$stmt = db()->prepare(
    'SELECT c.campaign_id, c.name, c.status,
            COALESCE(SUM(s.impressions),0) AS imp,
            COALESCE(SUM(s.good_hits),0)   AS vw,
            COALESCE(SUM(s.clicks),0)      AS ht,
            COALESCE(SUM(s.spent),0)       AS sp
     FROM campaigns c
     LEFT JOIN stats s ON s.campaign_id = c.campaign_id AND s.`date` >= ?
     WHERE c.user_id = ?
     GROUP BY c.campaign_id
     HAVING imp > 0 OR vw > 0 OR ht > 0 OR sp > 0
     ORDER BY vw DESC'
);
$stmt->execute([$cutoff, $user['id']]);
$campaignStats = $stmt->fetchAll();

// ── Day-by-day per-campaign rollup (historical detail table) ─
$stmt = db()->prepare(
    'SELECT s.`date`, c.name, c.campaign_id,
            s.impressions, s.good_hits, s.clicks, s.spent
     FROM stats s
     INNER JOIN campaigns c ON c.campaign_id = s.campaign_id
     WHERE s.user_id = ? AND s.`date` >= ?
     ORDER BY s.`date` DESC, c.name ASC'
);
$stmt->execute([$user['id'], $cutoff]);
$dailyDetail = $stmt->fetchAll();
?>

<div class="page-header">
  <div>
    <div class="page-title">Metrics</div>
    <div class="page-subtitle">Historical performance in CST (UTC−6) for the last <?= $range ?> days · For live data, see your <a href="/user/dashboard.php">Dashboard</a></div>
  </div>
  <a href="?range=<?= $range ?>&export=1" class="btn btn-primary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Export CSV
  </a>
</div>

<!-- Totals -->
<div class="stats-grid">
  <div class="stat-card"><div class="stat-label">Total Impressions</div><div class="stat-value"><?= fmtNum($sumImp) ?></div></div>
  <div class="stat-card"><div class="stat-label">Total Views</div>      <div class="stat-value"><?= fmtNum($sumVw) ?></div></div>
  <div class="stat-card"><div class="stat-label">Total Hits</div>       <div class="stat-value"><?= fmtNum($sumHt) ?></div></div>
  <div class="stat-card"><div class="stat-label">Total Spend</div>      <div class="stat-value"><?= fmtMoney($sumSp) ?></div></div>
  <div class="stat-card"><div class="stat-label">Avg CTR</div>          <div class="stat-value"><?= $totalCtr ?>%</div></div>
  <div class="stat-card"><div class="stat-label">Avg CPV</div>          <div class="stat-value"><?= fmtMoney($avgCpv) ?></div></div>
</div>

<!-- Range selector -->
<div class="card" style="padding:14px 18px;margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
  <div style="font-size:12px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;font-weight:600">Range:</div>
  <a href="?range=7"  class="range-btn <?= $range===7  ? 'active':'' ?>">Last 7 Days</a>
  <a href="?range=14" class="range-btn <?= $range===14 ? 'active':'' ?>">Last 14 Days</a>
  <a href="?range=30" class="range-btn <?= $range===30 ? 'active':'' ?>">Last 30 Days</a>
</div>

<!-- Per-campaign breakdown -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Campaign Breakdown</div>
    <div style="font-size:12px;color:var(--text-2)">Activity within the selected window</div>
  </div>
  <?php if (empty($campaignStats)): ?>
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
    <h3>No activity in this window</h3>
    <p>Pick a wider range, or wait for your campaigns to deliver traffic</p>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Campaign</th><th>Status</th><th>Views</th><th>Impressions</th><th>Hits</th><th>CTR</th><th>Spent</th><th>CPV</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($campaignStats as $row):
        $rctr = $row['imp'] > 0 ? round($row['vw'] / $row['imp'] * 100, 2) : 0;
        $rcpv = $row['vw']  > 0 ? round($row['sp'] / $row['vw'], 4)        : 0;
        $sc   = ['active'=>'badge-success','pending'=>'badge-pending','paused'=>'badge-muted','review'=>'badge-info','rejected'=>'badge-danger'][$row['status']] ?? 'badge-muted';
        $sl   = $row['status'] === 'review' ? 'Under Review' : $row['status'];
      ?>
      <tr>
        <td>
          <a href="/user/campaign_view.php?id=<?= urlencode($row['campaign_id']) ?>" style="font-weight:600;color:var(--text)"><?= htmlspecialchars($row['name']) ?></a>
          <div style="font-size:11px;color:var(--text-3);font-family:'Courier New',monospace;margin-top:2px"><?= $row['campaign_id'] ?></div>
        </td>
        <td><span class="badge <?= $sc ?>"><?= $sl ?></span></td>
        <td style="font-weight:600"><?= fmtNum($row['vw']) ?></td>
        <td><?= fmtNum($row['imp']) ?></td>
        <td><?= fmtNum($row['ht']) ?></td>
        <td><?= $rctr ?>%</td>
        <td><?= fmtMoney($row['sp']) ?></td>
        <td><?= fmtMoney($rcpv) ?></td>
        <td><a href="/user/campaign_view.php?id=<?= urlencode($row['campaign_id']) ?>" class="btn btn-secondary btn-sm">View</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Day-by-day detail -->
<?php if (!empty($dailyDetail)): ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Daily Detail (CST)</div>
    <div style="font-size:12px;color:var(--text-2)">Every campaign on every day</div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Date (CST)</th><th>Campaign</th><th>Impressions</th><th>Views</th><th>Hits</th><th>Spent</th><th>CTR</th></tr>
      </thead>
      <tbody>
      <?php
      $lastDate = null;
      foreach ($dailyDetail as $row):
        $rctr = $row['impressions'] > 0 ? round($row['good_hits'] / $row['impressions'] * 100, 2) : 0;
        $showDate = $row['date'] !== $lastDate;
        $lastDate = $row['date'];
      ?>
      <tr>
        <td style="<?= !$showDate ? 'color:var(--text-3)' : 'font-weight:600' ?>">
          <?= $showDate ? htmlspecialchars(date('M j, Y', strtotime($row['date']))) : '' ?>
        </td>
        <td>
          <a href="/user/campaign_view.php?id=<?= urlencode($row['campaign_id']) ?>" style="color:var(--text)"><?= htmlspecialchars($row['name']) ?></a>
        </td>
        <td><?= fmtNum($row['impressions']) ?></td>
        <td style="font-weight:600"><?= fmtNum($row['good_hits']) ?></td>
        <td><?= fmtNum($row['clicks']) ?></td>
        <td><?= fmtMoney($row['spent']) ?></td>
        <td><?= $rctr ?>%</td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<style>
.range-btn{background:var(--bg-3);border:1px solid var(--border-2);color:var(--text-2);padding:6px 14px;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s;text-decoration:none}
.range-btn:hover{color:var(--text);border-color:var(--border-hi);text-decoration:none}
.range-btn.active{background:var(--yellow-dim);color:var(--yellow);border-color:rgba(255,200,0,.25)}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
