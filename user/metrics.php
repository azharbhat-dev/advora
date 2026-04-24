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

// ── Per-day totals for line chart (CST) ────────────────
$stmt = db()->prepare(
    'SELECT `date`,
            COALESCE(SUM(impressions),0) AS imp,
            COALESCE(SUM(good_hits),0)   AS vw,
            COALESCE(SUM(clicks),0)      AS ht,
            COALESCE(SUM(spent),0)       AS sp
     FROM stats WHERE user_id = ? AND `date` >= ?
     GROUP BY `date` ORDER BY `date` ASC'
) ;
$stmt->execute([$user['id'], $cutoff]);
$dailyRaw = $stmt->fetchAll();
$dailyMap = [];
foreach ($dailyRaw as $r) { $dailyMap[$r['date']] = $r; }

$chartLabels = $dImp = $dVw = $dHt = $dSp = $dCtr = [];
$sumImp = $sumVw = $sumHt = 0; $sumSp = 0.0;
for ($i = $range - 1; $i >= 0; $i--) {
    $d    = (new DateTime('now', $cstTz))->modify("-{$i} days")->format('Y-m-d');
    $lbl  = (new DateTime($d, $cstTz))->format('M j');
    $row  = $dailyMap[$d] ?? ['imp'=>0,'vw'=>0,'ht'=>0,'sp'=>0];
    $chartLabels[] = $lbl;
    $dImp[] = (int)$row['imp'];
    $dVw[]  = (int)$row['vw'];
    $dHt[]  = (int)$row['ht'];
    $dSp[]  = round((float)$row['sp'], 2);
    $dCtr[] = $row['imp'] > 0 ? round($row['vw'] / $row['imp'] * 100, 2) : 0;
    $sumImp += (int)$row['imp'];
    $sumVw  += (int)$row['vw'];
    $sumHt  += (int)$row['ht'];
    $sumSp  += (float)$row['sp'];
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
    <div class="page-subtitle">Historical performance in CST (UTC−6) for the last <?= $range ?> days</div>
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

<!-- Historical chart -->
<div class="card" style="padding:0;overflow:hidden">
  <div class="ga-metrics-row">
    <div class="ga-metric"        data-metric="impressions" data-color="#e8710a" onclick="gaSwitch(this)"><div class="gam-label">Impressions</div><div class="gam-value"><?= fmtNum($sumImp) ?></div></div>
    <div class="ga-metric active" data-metric="views"       data-color="#1a73e8" onclick="gaSwitch(this)"><div class="gam-label">Views</div><div class="gam-value"><?= fmtNum($sumVw) ?></div></div>
    <div class="ga-metric"        data-metric="hits"        data-color="#34a853" onclick="gaSwitch(this)"><div class="gam-label">Hits</div><div class="gam-value"><?= fmtNum($sumHt) ?></div></div>
    <div class="ga-metric"        data-metric="spend"       data-color="#ea4335" onclick="gaSwitch(this)"><div class="gam-label">Spend</div><div class="gam-value"><?= fmtMoney($sumSp) ?></div></div>
    <div class="ga-metric"        data-metric="ctr"         data-color="#9334e8" onclick="gaSwitch(this)"><div class="gam-label">CTR</div><div class="gam-value"><?= $totalCtr ?>%</div></div>
  </div>
  <div style="padding:8px 24px 20px">
    <div style="font-size:11px;color:var(--text-3);margin-bottom:10px;text-align:right">Historical — last <?= $range ?> days (CST)</div>
    <div style="position:relative;height:300px"><canvas id="gaChart"></canvas></div>
  </div>
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
.ga-metrics-row{display:flex;border-bottom:1px solid var(--border);overflow-x:auto}
.ga-metric{flex:1;min-width:110px;padding:14px 16px;cursor:pointer;border-bottom:3px solid transparent;transition:all .15s;user-select:none}
.ga-metric:hover{background:rgba(255,255,255,.025)}
.ga-metric.active{border-bottom-color:var(--ga-color,#1a73e8)}
.gam-label{font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-3);margin-bottom:5px}
.ga-metric.active .gam-label{color:var(--ga-color,#1a73e8)}
.gam-value{font-size:19px;font-weight:700;letter-spacing:-.5px;color:var(--text)}
.ga-metric.active .gam-value{color:var(--ga-color,#1a73e8)}
</style>

<script>
let metActive = 'views';
const metData = {
  views:       { data: <?= json_encode($dVw) ?>,  color:'#1a73e8', fill:'rgba(26,115,232,0.1)', label:'Views' },
  impressions: { data: <?= json_encode($dImp) ?>, color:'#e8710a', fill:'rgba(232,113,10,0.1)', label:'Impressions' },
  hits:        { data: <?= json_encode($dHt) ?>,  color:'#34a853', fill:'rgba(52,168,83,0.1)',  label:'Hits' },
  spend:       { data: <?= json_encode($dSp) ?>,  color:'#ea4335', fill:'rgba(234,67,53,0.1)',  label:'Spend ($)' },
  ctr:         { data: <?= json_encode($dCtr) ?>, color:'#9334e8', fill:'rgba(147,52,232,0.1)', label:'CTR (%)' }
};
const metLabels = <?= json_encode($chartLabels) ?>;

const gaChart = new Chart(document.getElementById('gaChart').getContext('2d'), {
  type:'line',
  data:{ labels:metLabels, datasets:[{
    label:metData.views.label, data:metData.views.data,
    borderColor:metData.views.color, backgroundColor:metData.views.fill,
    borderWidth:2.5, fill:true, tension:0.35,
    pointRadius:4, pointHoverRadius:7,
    pointBackgroundColor:'#fff', pointBorderColor:metData.views.color, pointBorderWidth:2
  }]},
  options:{
    responsive:true, maintainAspectRatio:false,
    interaction:{intersect:false,mode:'index'},
    plugins:{
      legend:{display:false},
      tooltip:{
        backgroundColor:'rgba(28,28,50,0.97)', titleColor:'#eeeef8', bodyColor:'#eeeef8',
        borderColor:'rgba(255,255,255,0.1)', borderWidth:1, padding:14, cornerRadius:8,
        callbacks:{
          label: ctx => {
            const v = parseFloat(ctx.raw)||0;
            if (metActive==='spend') return '  '+ctx.dataset.label+': $'+v.toFixed(2);
            if (metActive==='ctr')   return '  '+ctx.dataset.label+': '+v.toFixed(2)+'%';
            return '  '+ctx.dataset.label+': '+Number(v).toLocaleString();
          }
        }
      }
    },
    scales:{
      x:{ grid:{color:'rgba(255,255,255,0.05)'}, ticks:{color:'#8888a8',font:{size:11}} },
      y:{ beginAtZero:true, grid:{color:'rgba(255,255,255,0.05)'},
          ticks:{color:'#8888a8',font:{size:11},callback:v=>{const n=parseFloat(v)||0;return metActive==='spend'?'$'+n.toFixed(2):(metActive==='ctr'?n.toFixed(2)+'%':Number(n).toLocaleString());}} }
    }
  }
});

function gaSwitch(el) {
  document.querySelectorAll('.ga-metric').forEach(m => { m.classList.remove('active'); m.style.removeProperty('--ga-color'); });
  el.classList.add('active');
  el.style.setProperty('--ga-color', el.dataset.color);
  metActive = el.dataset.metric;
  const d = metData[metActive];
  gaChart.data.datasets[0].data            = d.data;
  gaChart.data.datasets[0].label           = d.label;
  gaChart.data.datasets[0].borderColor     = d.color;
  gaChart.data.datasets[0].backgroundColor = d.fill;
  gaChart.data.datasets[0].pointBorderColor = d.color;
  gaChart.update();
}
document.querySelector('.ga-metric.active').style.setProperty('--ga-color', '#1a73e8');
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>