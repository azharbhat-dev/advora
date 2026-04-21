<?php
require_once __DIR__ . '/../includes/user_header.php';

$campaigns = readJson(CAMPAIGNS_FILE);
$userCampaigns = array_filter($campaigns, fn($c) => $c['user_id'] === $user['id']);
$stats = readJson(STATS_FILE);
$userStats = array_filter($stats, fn($s) => $s['user_id'] === $user['id']);

$range = (int)($_GET['range'] ?? 30);
if (!in_array($range, [7, 14, 30, 90])) $range = 30;

// CSV export — must happen before ANY output including headers from user_header
// We re-check here; if export param is set we flush buffer and send CSV
if (isset($_GET['export'])) {
    // Clear any output buffered so far (from user_header ob_start)
    while (ob_get_level() > 0) ob_end_clean();

    // Build campaign stats for export
    $exportCampaigns = readJson(CAMPAIGNS_FILE);
    $exportUserCampaigns = array_filter($exportCampaigns, fn($c) => $c['user_id'] === $user['id']);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="advora-metrics-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Campaign ID', 'Campaign Name', 'Status', 'Views', 'Impressions', 'Hits', 'CTR (%)', 'Spent ($)', 'CPV ($)']);

    foreach ($exportUserCampaigns as $c) {
        $rctr = ($c['impressions'] ?? 0) > 0 ? round(($c['clicks'] ?? 0) / ($c['impressions'] ?? 1) * 100, 2) : 0;
        $rcpv = ($c['good_hits'] ?? 0) > 0 ? round(($c['spent'] ?? 0) / ($c['good_hits'] ?? 1), 4) : 0;
        fputcsv($out, [
            $c['campaign_id'],
            $c['name'],
            $c['status'],
            $c['good_hits'] ?? 0,
            $c['impressions'] ?? 0,
            $c['clicks'] ?? 0,
            $rctr,
            number_format($c['spent'] ?? 0, 4),
            number_format($rcpv, 4),
        ]);
    }
    fclose($out);
    exit;
}

// Build per-campaign totals (all-time from campaign object)
$campaignStats = [];
foreach ($userCampaigns as $c) {
    $campaignStats[$c['campaign_id']] = [
        'name'        => $c['name'],
        'campaign_id' => $c['campaign_id'],
        'status'      => $c['status'],
        'views'       => $c['good_hits'] ?? 0,
        'impressions' => $c['impressions'] ?? 0,
        'clicks'      => $c['clicks'] ?? 0,
        'spent'       => $c['spent'] ?? 0,
    ];
}

$totalViews = array_sum(array_column($campaignStats, 'views'));
$totalImp   = array_sum(array_column($campaignStats, 'impressions'));
$totalClk   = array_sum(array_column($campaignStats, 'clicks'));
$totalSpend = array_sum(array_column($campaignStats, 'spent'));
$ctr        = $totalImp > 0 ? round($totalClk / $totalImp * 100, 2) : 0;
$cpv        = $totalViews > 0 ? round($totalSpend / $totalViews, 4) : 0;

// Build chart data for selected range
$chartLabels = [];
$chartViews = [];
$chartImpressions = [];
$chartClicks = [];
$chartSpend = [];

for ($i = $range - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $label = $range <= 14 ? date('M d', strtotime($date)) : date('M d', strtotime($date));
    $chartLabels[] = $label;
    $di = $dc = $dv = $ds = 0;
    foreach ($userStats as $s) {
        if ($s['date'] === $date) {
            $di += $s['impressions'] ?? 0;
            $dc += $s['clicks'] ?? 0;
            $dv += $s['good_hits'] ?? 0;
            $ds += $s['spent'] ?? 0;
        }
    }
    $chartImpressions[] = $di;
    $chartClicks[]      = $dc;
    $chartViews[]       = $dv;
    $chartSpend[]       = round($ds, 4);
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
    <div class="stat-label">Hits</div>
    <div class="stat-value"><?= fmtNum($totalClk) ?></div>
    <div class="stat-change">CTR: <?= $ctr ?>%</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(255,144,0,.1);color:var(--orange);border-color:rgba(255,144,0,.12)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    </div>
    <div class="stat-label">Total Spent</div>
    <div class="stat-value"><?= fmtMoney($totalSpend) ?></div>
    <div class="stat-change">Avg CPV: <?= fmtMoneyPrecise($cpv) ?></div>
  </div>
</div>

<!-- Chart -->
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
      <div class="card-title" id="metricsChartLabel">Views — Last <?= $range ?> Days</div>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
      <!-- Metric switcher -->
      <div style="display:flex;gap:4px;margin-right:8px">
        <button class="metric-switch-btn active" data-metric="views" onclick="switchChartMetric(this)">Views</button>
        <button class="metric-switch-btn" data-metric="impressions" onclick="switchChartMetric(this)">Impr.</button>
        <button class="metric-switch-btn" data-metric="hits" onclick="switchChartMetric(this)">Hits</button>
        <button class="metric-switch-btn" data-metric="spend" onclick="switchChartMetric(this)">Spend</button>
      </div>
      <!-- Range switcher -->
      <a href="?range=7" class="range-btn <?= $range===7?'active':'' ?>">7D</a>
      <a href="?range=14" class="range-btn <?= $range===14?'active':'' ?>">14D</a>
      <a href="?range=30" class="range-btn <?= $range===30?'active':'' ?>">30D</a>
      <a href="?range=90" class="range-btn <?= $range===90?'active':'' ?>">90D</a>
    </div>
  </div>
  <div style="height:260px;position:relative"><canvas id="metricsChart"></canvas></div>
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
          <th>Hits</th>
          <th>CTR</th>
          <th>Spent</th>
          <th>CPV</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($campaignStats as $row):
        $rctr = ($row['impressions'] ?? 0) > 0 ? round($row['clicks'] / $row['impressions'] * 100, 2) : 0;
        $rcpv = ($row['views'] ?? 0) > 0 ? round($row['spent'] / $row['views'], 4) : 0;
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
        <td style="font-size:12px;color:var(--text-2)"><?= fmtMoneyPrecise($rcpv) ?></td>
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

<style>
.range-btn{background:var(--bg-3);border:1px solid var(--border-2);color:var(--text-2);padding:4px 10px;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s;text-decoration:none;display:inline-block;}
.range-btn:hover{color:var(--text);border-color:var(--border-hi);text-decoration:none}
.range-btn.active{background:var(--yellow-dim);color:var(--yellow);border-color:rgba(255,200,0,.25)}
.metric-switch-btn{background:var(--bg-3);border:1px solid var(--border-2);color:var(--text-2);padding:4px 10px;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s;}
.metric-switch-btn:hover{color:var(--text)}
.metric-switch-btn.active{background:rgba(255,200,0,.1);color:var(--yellow);border-color:rgba(255,200,0,.25)}
</style>

<script>
const mChartData = {
  views:       { data: <?= json_encode($chartViews) ?>,       color: '#ffc800', bg: 'rgba(255,200,0,0.1)',   label: 'Views' },
  impressions: { data: <?= json_encode($chartImpressions) ?>, color: '#4d9eff', bg: 'rgba(77,158,255,0.1)',  label: 'Impressions' },
  hits:      { data: <?= json_encode($chartClicks) ?>,      color: '#00e599', bg: 'rgba(0,229,153,0.1)',   label: 'Hits' },
  spend:       { data: <?= json_encode($chartSpend) ?>,       color: '#ff9000', bg: 'rgba(255,144,0,0.1)',   label: 'Spend ($)' }
};
const mLabels = <?= json_encode($chartLabels) ?>;
let activeMMetric = 'views';

const metricsChart = new Chart(document.getElementById('metricsChart').getContext('2d'), {
  type: 'bar',
  data: {
    labels: mLabels,
    datasets: [{
      label: 'Views',
      data: mChartData.views.data,
      backgroundColor: 'rgba(255,200,0,0.25)',
      borderColor: '#ffc800',
      borderWidth: 2,
      borderRadius: 4,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: 'rgba(13,13,23,0.95)',
        borderColor: 'rgba(255,255,255,0.1)',
        borderWidth: 1,
        titleColor: '#8888a8',
        bodyColor: '#eeeef8',
        padding: 12,
        callbacks: {
          label: ctx => ' ' + ctx.dataset.label + ': ' + (activeMMetric === 'spend' ? '$' + parseFloat(ctx.raw).toFixed(4) : Number(ctx.raw).toLocaleString())
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: 'rgba(255,255,255,0.04)' },
        ticks: { color: '#8888a8', font: { size: 11 }, callback: v => activeMMetric === 'spend' ? '$' + v : v }
      },
      x: {
        grid: { display: false },
        ticks: {
          color: '#8888a8',
          font: { size: 10 },
          maxTicksLimit: <?= $range <= 14 ? $range : 15 ?>,
          maxRotation: 45
        }
      }
    },
    interaction: { intersect: false, mode: 'index' },
    animation: { duration: 300 }
  }
});

function switchChartMetric(btn) {
  document.querySelectorAll('.metric-switch-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  activeMMetric = btn.dataset.metric;
  const d = mChartData[activeMMetric];
  metricsChart.data.datasets[0].data = d.data;
  metricsChart.data.datasets[0].borderColor = d.color;
  metricsChart.data.datasets[0].backgroundColor = d.bg;
  metricsChart.data.datasets[0].label = d.label;
  metricsChart.update();
  document.getElementById('metricsChartLabel').textContent = d.label + ' \u2014 Last <?= $range ?> Days';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>