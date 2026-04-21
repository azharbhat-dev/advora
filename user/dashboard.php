<?php
require_once __DIR__ . '/../includes/user_header.php';

$campaigns = readJson(CAMPAIGNS_FILE);
$userCampaigns = array_filter($campaigns, fn($c) => $c['user_id'] === $user['id']);

$totalImpressions=$totalClicks=$totalViews=$totalSpent=$activeCampaigns=0;
foreach($userCampaigns as $c){
    $totalImpressions += $c['impressions']??0;
    $totalClicks      += $c['clicks']??0;
    $totalViews       += $c['good_hits']??0;
    $totalSpent       += $c['spent']??0;
    if(($c['status']??'')==='active') $activeCampaigns++;
}
$ctr = $totalImpressions>0 ? round($totalClicks/$totalImpressions*100,2) : 0;

// Build 24-hour chart data (last 24 hours, per-hour)
$stats = readJson(STATS_FILE);
$userStats = array_filter($stats, fn($s) => $s['user_id'] === $user['id']);

$chartLabels = [];
$chartViews = [];
$chartImpressions = [];
$chartClicks = [];
$chartSpend = [];

// We only have daily stats granularity in the stats file, so we show today vs yesterday split by hour labels
// Since stats are daily, we show last 24h as today's data spread and yesterday's
// Build 24 hour labels for display
for($h = 23; $h >= 0; $h--) {
    $ts = strtotime("-{$h} hours");
    $chartLabels[] = date('H:i', $ts);
    // Find stats for this hour's date
    $date = date('Y-m-d', $ts);
    $di = $dc = $dv = $ds = 0;
    foreach($userStats as $s) {
        if($s['date'] === $date) {
            // Distribute daily stats evenly across 24 hours for display
            $di += round(($s['impressions']??0) / 24);
            $dc += round(($s['clicks']??0) / 24);
            $dv += round(($s['good_hits']??0) / 24);
            $ds += round(($s['spent']??0) / 24, 4);
        }
    }
    $chartImpressions[] = $di;
    $chartClicks[] = $dc;
    $chartViews[] = $dv;
    $chartSpend[] = round($ds, 4);
}

$recent = array_slice(array_reverse(array_values($userCampaigns)), 0, 5);
?>

<div class="page-header">
  <div>
    <div class="page-title">Dashboard</div>
    <div class="page-subtitle"><span class="live-dot"></span> Live &mdash; updates every 3.5s</div>
  </div>
  <a href="/user/create_campaign.php" class="btn btn-primary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    New Campaign
  </a>
</div>

<div class="card" style="padding:0;overflow:hidden">
  <div style="display:flex;flex-wrap:wrap;border-bottom:1px solid var(--border)">
    <div class="db-metric active" data-metric="views" data-color="#ffc800" data-bg="rgba(255,200,0,0.08)" style="color:var(--yellow)" onclick="switchMetric(this)">
      <div class="dbm-label">Views</div>
      <div class="dbm-value" data-live="total-views"><?= number_format($totalViews) ?></div>
      <div class="dbm-sub">Quality traffic</div>
    </div>
    <div class="db-metric" data-metric="impressions" data-color="#4d9eff" data-bg="rgba(77,158,255,0.08)" style="color:var(--blue)" onclick="switchMetric(this)">
      <div class="dbm-label">Impressions</div>
      <div class="dbm-value" data-live="total-impressions"><?= number_format($totalImpressions) ?></div>
      <div class="dbm-sub">Total ad loads</div>
    </div>
    <div class="db-metric" data-metric="hits" data-color="#00e599" data-bg="rgba(0,229,153,0.08)" style="color:var(--green)" onclick="switchMetric(this)">
      <div class="dbm-label">Hits</div>
      <div class="dbm-value" data-live="total-hits"><?= number_format($totalClicks) ?></div>
      <div class="dbm-sub">CTR: <span data-live="total-ctr"><?= $ctr ?>%</span></div>
    </div>
    <div class="db-metric" data-metric="spend" data-color="#ff9000" data-bg="rgba(255,144,0,0.08)" style="color:var(--orange)" onclick="switchMetric(this)">
      <div class="dbm-label">Total Spent</div>
      <div class="dbm-value" data-live-money="total-spent"><?= fmtMoney($totalSpent) ?></div>
      <div class="dbm-sub"><?= $activeCampaigns ?> active campaign<?= $activeCampaigns!==1?'s':'' ?></div>
    </div>
  </div>
  <div style="padding:20px 22px 18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
      <div style="font-size:13px;font-weight:600" id="chartLabel">Views &mdash; Last 24 Hours</div>
      <div style="font-size:11px;color:var(--text-3);display:flex;align-items:center;gap:5px">
        <span class="live-dot"></span> Live 24h window
      </div>
    </div>
    <div style="height:240px;position:relative"><canvas id="perfChart"></canvas></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Recent Campaigns</div>
    <a href="/user/campaigns.php" class="btn btn-secondary btn-sm">View All</a>
  </div>
  <?php if(empty($recent)): ?>
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 11l18-5v12L3 14v-3z"/></svg>
    <h3>No campaigns yet</h3>
    <p>Create your first campaign to start advertising</p>
    <a href="/user/create_campaign.php" class="btn btn-primary" style="margin-top:14px">Create Campaign</a>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Campaign</th><th>Status</th><th>Views</th><th>Impressions</th><th>Hits</th><th>CTR</th><th>Spent</th><th></th></tr></thead>
      <tbody>
      <?php foreach($recent as $c):
        $sc=['pending'=>'badge-pending','active'=>'badge-success','paused'=>'badge-muted','review'=>'badge-info','rejected'=>'badge-danger'][$c['status']??'pending']??'badge-muted';
        $cctr=($c['impressions']??0)>0?round($c['clicks']/$c['impressions']*100,2):0;
        $cid=$c['campaign_id'];
      ?>
        <tr>
          <td><div style="font-weight:600"><?= htmlspecialchars($c['name']) ?></div>
              <div style="font-size:11px;color:var(--text-3);font-family:'Courier New',monospace"><?= $cid ?></div></td>
          <td><span class="badge <?= $sc ?>" data-live-badge="camp:<?= $cid ?>:status" data-current-status="<?= $c['status'] ?>"><?= $c['status'] ?></span></td>
          <td data-live="camp:<?= $cid ?>:views"><?= number_format($c['good_hits']??0) ?></td>
          <td data-live="camp:<?= $cid ?>:impressions"><?= number_format($c['impressions']??0) ?></td>
          <td data-live="camp:<?= $cid ?>:hits"><?= number_format($c['clicks']??0) ?></td>
          <td data-live="camp:<?= $cid ?>:ctr"><?= $cctr ?>%</td>
          <td data-live-money="camp:<?= $cid ?>:spent"><?= fmtMoney($c['spent']??0) ?></td>
          <td><a href="/user/campaign_view.php?id=<?= urlencode($cid) ?>" class="btn btn-secondary btn-sm">View</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<style>
.db-metric{flex:1;min-width:140px;padding:18px 22px;cursor:pointer;border-right:1px solid var(--border);transition:background .15s;position:relative;}
.db-metric:last-child{border-right:none}
.db-metric:hover{background:rgba(255,255,255,.02)}
.db-metric.active::after{content:'';position:absolute;bottom:-1px;left:0;right:0;height:2px;background:currentColor;}
.db-metric.active{background:rgba(255,255,255,.025)}
.dbm-label{font-size:10.5px;text-transform:uppercase;letter-spacing:.7px;font-weight:700;color:var(--text-2);margin-bottom:6px}
.db-metric.active .dbm-label{color:currentColor}
.dbm-value{font-size:22px;font-weight:700;letter-spacing:-.5px;color:var(--text)}
.db-metric.active .dbm-value{color:currentColor}
.dbm-sub{font-size:11px;color:var(--text-3);margin-top:3px}
.db-metric.active .dbm-sub{color:currentColor;opacity:.65}
</style>

<script>
const chartData = {
  views:       { data: <?= json_encode($chartViews) ?>,       color: '#ffc800', bg: 'rgba(255,200,0,0.1)',   label: 'Views' },
  impressions: { data: <?= json_encode($chartImpressions) ?>, color: '#4d9eff', bg: 'rgba(77,158,255,0.1)',  label: 'Impressions' },
  hits:      { data: <?= json_encode($chartClicks) ?>,      color: '#00e599', bg: 'rgba(0,229,153,0.1)',   label: 'Hits' },
  spend:       { data: <?= json_encode($chartSpend) ?>,       color: '#ff9000', bg: 'rgba(255,144,0,0.1)',   label: 'Spend ($)' }
};
const chartLabels = <?= json_encode($chartLabels) ?>;
let activeMetric = 'views';

const perfChart = new Chart(document.getElementById('perfChart').getContext('2d'), {
  type: 'line',
  data: {
    labels: chartLabels,
    datasets: [{
      label: 'Views',
      data: chartData.views.data,
      borderColor: '#ffc800',
      backgroundColor: 'rgba(255,200,0,0.1)',
      borderWidth: 2.5,
      fill: true,
      tension: 0.4,
      pointRadius: 2,
      pointHoverRadius: 6,
      pointBackgroundColor: '#ffc800',
      pointBorderColor: 'var(--bg-2)',
      pointBorderWidth: 2
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
          label: ctx => ' ' + ctx.dataset.label + ': ' + (activeMetric === 'spend' ? '$' + parseFloat(ctx.raw).toFixed(4) : Number(ctx.raw).toLocaleString())
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: 'rgba(255,255,255,0.04)' },
        ticks: { color: '#8888a8', font: { size: 11 }, callback: v => activeMetric === 'spend' ? '$' + v : v }
      },
      x: {
        grid: { display: false },
        ticks: { color: '#8888a8', font: { size: 10 }, maxTicksLimit: 12 }
      }
    },
    interaction: { intersect: false, mode: 'index' },
    animation: { duration: 250 }
  }
});

function switchMetric(el) {
  document.querySelectorAll('.db-metric').forEach(e => e.classList.remove('active'));
  el.classList.add('active');
  activeMetric = el.dataset.metric;
  const d = chartData[activeMetric];
  perfChart.data.datasets[0].data = d.data;
  perfChart.data.datasets[0].borderColor = d.color;
  perfChart.data.datasets[0].backgroundColor = d.bg;
  perfChart.data.datasets[0].pointBackgroundColor = d.color;
  perfChart.data.datasets[0].label = d.label;
  perfChart.update();
  document.getElementById('chartLabel').textContent = d.label + ' \u2014 Last 24 Hours';
}

// Live update just refreshes the stat cards via app.js polling; chart stays as 24h snapshot
window.addEventListener('liveStatsUpdate', e => {
  // chart data is a 24h snapshot rendered server-side; no chart update needed on poll
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>