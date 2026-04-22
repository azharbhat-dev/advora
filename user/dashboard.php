<?php
require_once __DIR__ . '/../includes/user_header.php';

$campaigns     = readJson(CAMPAIGNS_FILE);
$userCampaigns = array_filter($campaigns, fn($c) => $c['user_id'] === $user['id']);

$totalImpressions = $totalClicks = $totalViews = $totalSpent = $activeCampaigns = 0;
foreach ($userCampaigns as $c) {
    $totalImpressions += $c['impressions'] ?? 0;
    $totalClicks      += $c['clicks']      ?? 0;
    $totalViews       += $c['good_hits']   ?? 0;
    $totalSpent       += $c['spent']       ?? 0;
    if (($c['status'] ?? '') === 'active') $activeCampaigns++;
}
$ctr = $totalImpressions > 0 ? round($totalViews / $totalImpressions * 100, 2) : 0;

// Last 24 hours - today and yesterday from daily stats
$stats     = readJson(STATS_FILE);
$userStats = array_filter($stats, fn($s) => $s['user_id'] === $user['id']);

// We have daily granularity so show today vs yesterday as 2 points,
// then fill 24 hour labels distributing evenly
$chartLabels = $chartViews = $chartImpressions = $chartHits = $chartSpend = $chartCtr = [];

// Sanitize chart array: replace any non-finite values with 0
function sanitizeChartData(array $arr): array {
    return array_map(function($v) {
        $f = (float)$v;
        return (is_finite($f) && !is_nan($f)) ? $f : 0;
    }, $arr);
}

$cstTz     = new DateTimeZone('America/Chicago');
$cstNow    = new DateTime('now', $cstTz);
$today     = $cstNow->format('Y-m-d');
$yesterday = (new DateTime('yesterday', $cstTz))->format('Y-m-d');

// Get today and yesterday totals
$dayData = [];
foreach ([$yesterday, $today] as $d) {
    $di = $dv = $dh = $ds = 0;
    foreach ($userStats as $s) {
        if ($s['date'] === $d) {
            $di += $s['impressions'] ?? 0;
            $dv += $s['good_hits']  ?? 0;
            $dh += $s['clicks']     ?? 0;
            $ds += $s['spent']      ?? 0;
        }
    }
    $dayData[$d] = ['imp'=>$di, 'views'=>$dv, 'hits'=>$dh, 'spend'=>round($ds,2)];
}

// Build 24 hourly points in CST
$nowHour = (int)$cstNow->format('G');
for ($h = 0; $h < 24; $h++) {
    $isYesterday = $h > $nowHour;
    $label = ($isYesterday ? date('M j', strtotime('-1 day')) : 'Today') . ' ' . str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
    $chartLabels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00 CST';
    $srcDate = $isYesterday ? $yesterday : $today;
    $d = $dayData[$srcDate];
    // distribute daily total evenly across 24 hrs, add remainder to last hour
    $chartImpressions[] = (int)floor($d['imp']   / 24);
    $chartViews[]       = (int)floor($d['views'] / 24);
    $chartHits[]        = (int)floor($d['hits']  / 24);
    $chartSpend[]       = round($d['spend'] / 24, 2);
    $di = (int)floor($d['imp'] / 24);
    $dv = (int)floor($d['views'] / 24);
    $chartCtr[]         = $di > 0 ? round($dv / $di * 100, 2) : 0;
}

$recent = array_slice(array_reverse(array_values($userCampaigns)), 0, 5);
?>

<div class="page-header">
  <div>
    <div class="page-title">Dashboard</div>
    <div class="page-subtitle"><span class="live-dot"></span> Live </div>
  </div>
  <a href="/user/create_campaign.php" class="btn btn-primary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    New Campaign
  </a>
</div>

<!-- Google Ads-style performance chart -->
<div class="card" style="padding:0;overflow:hidden">
  <div class="ga-metrics-row">
    <div class="ga-metric" data-metric="impressions" data-color="#e8710a" onclick="gaSwitch(this)">
      <div class="gam-label">Impressions</div>
      <div class="gam-value" data-live="total-impressions"><?= number_format($totalImpressions) ?></div>
    </div>
    <div class="ga-metric active" data-metric="views" data-color="#1a73e8" onclick="gaSwitch(this)">
      <div class="gam-label">Views</div>
      <div class="gam-value" data-live="total-views"><?= number_format($totalViews) ?></div>
    </div>
    
    <div class="ga-metric" data-metric="hits" data-color="#34a853" onclick="gaSwitch(this)">
      <div class="gam-label">Hits</div>
      <div class="gam-value" data-live="total-hits"><?= number_format($totalClicks) ?></div>
    </div>
    <div class="ga-metric" data-metric="spend" data-color="#ea4335" onclick="gaSwitch(this)">
      <div class="gam-label">Spend</div>
      <div class="gam-value" data-live-money="total-spent"><?= fmtMoney($totalSpent) ?></div>
    </div>
    <div class="ga-metric" data-metric="ctr" data-color="#9334e8" onclick="gaSwitch(this)">
      <div class="gam-label">CTR</div>
      <div class="gam-value" data-live="total-ctr"><?= $ctr ?>%</div>
    </div>
  </div>
  <div style="padding:8px 24px 20px">
    <div style="font-size:11px;color:var(--text-3);margin-bottom:10px;text-align:right">Last 24 Hours (CST)</div>
    <div style="position:relative;height:260px"><canvas id="gaChart"></canvas></div>
  </div>
</div>

<!-- Recent Campaigns table -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Recent Campaigns</div>
    <a href="/user/campaigns.php" class="btn btn-secondary btn-sm">View All</a>
  </div>
  <?php if (empty($recent)): ?>
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
      <?php foreach ($recent as $c):
        $sc  = ['pending'=>'badge-pending','active'=>'badge-success','paused'=>'badge-muted','review'=>'badge-info','rejected'=>'badge-danger'][$c['status']??'pending'] ?? 'badge-muted';
        $sl  = $c['status'] === 'review' ? 'Under Review' : ($c['status'] ?? '');
        $cct = ($c['impressions']??0) > 0 ? round(($c['good_hits']??0)/($c['impressions']??1)*100,2) : 0;
        $cid = $c['campaign_id'];
      ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($c['name']) ?></div>
            <div style="font-size:11px;color:var(--text-3);font-family:'Courier New',monospace"><?= $cid ?></div>
          </td>
          <td><span class="badge <?= $sc ?>" data-live-badge="camp:<?= $cid ?>:status" data-current-status="<?= $c['status'] ?>"><?= $sl ?></span></td>
          <td data-live="camp:<?= $cid ?>:views"><?= number_format($c['good_hits']??0) ?></td>
          <td data-live="camp:<?= $cid ?>:impressions"><?= number_format($c['impressions']??0) ?></td>
          <td data-live="camp:<?= $cid ?>:hits"><?= number_format($c['clicks']??0) ?></td>
          <td data-live="camp:<?= $cid ?>:ctr"><?= $cct ?>%</td>
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
.ga-metrics-row{display:flex;border-bottom:1px solid var(--border);overflow-x:auto;-webkit-overflow-scrolling:touch}
.ga-metric{flex:1;min-width:110px;padding:16px 18px;cursor:pointer;border-bottom:3px solid transparent;transition:all .15s;user-select:none}
.ga-metric:hover{background:rgba(255,255,255,.025)}
.ga-metric.active{border-bottom-color:var(--ga-color,#1a73e8)}
.gam-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-3);margin-bottom:5px}
.ga-metric.active .gam-label{color:var(--ga-color,#1a73e8)}
.gam-value{font-size:21px;font-weight:700;letter-spacing:-.5px;color:var(--text)}
.ga-metric.active .gam-value{color:var(--ga-color,#1a73e8)}
</style>

<script>

// Safe number helper for chart
function safeNum(v) { const n = parseFloat(v); return isNaN(n) ? 0 : n; }
function safeFixed(v, d) { const n = parseFloat(v); return isNaN(n) ? (0).toFixed(d) : n.toFixed(d); }

const gaAllData = {
  views:       { data: <?= json_encode(sanitizeChartData($chartViews)) ?>,       color: '#1a73e8', fill: 'rgba(26,115,232,0.1)',   label: 'Views' },
  impressions: { data: <?= json_encode(sanitizeChartData($chartImpressions)) ?>, color: '#e8710a', fill: 'rgba(232,113,10,0.1)',   label: 'Impressions' },
  hits:        { data: <?= json_encode(sanitizeChartData($chartHits)) ?>,        color: '#34a853', fill: 'rgba(52,168,83,0.1)',    label: 'Hits' },
  spend:       { data: <?= json_encode(sanitizeChartData($chartSpend)) ?>,       color: '#ea4335', fill: 'rgba(234,67,53,0.1)',    label: 'Spend ($)' },
  ctr:         { data: <?= json_encode(sanitizeChartData($chartCtr)) ?>,         color: '#9334e8', fill: 'rgba(147,52,232,0.1)',   label: 'CTR (%)' }
};
const gaLabels = <?= json_encode($chartLabels) ?>;
let gaActive = 'views';

const gaChart = new Chart(document.getElementById('gaChart').getContext('2d'), {
  type: 'line',
  data: {
    labels: gaLabels,
    datasets: [{
      label:                gaAllData.views.label,
      data:                 gaAllData.views.data,
      borderColor:          gaAllData.views.color,
      backgroundColor:      gaAllData.views.fill,
      borderWidth:          2.5,
      fill:                 true,
      tension:              0.4,
      pointRadius:          5,
      pointHoverRadius:     8,
      pointBackgroundColor: '#fff',
      pointBorderColor:     gaAllData.views.color,
      pointBorderWidth:     2.5,
      pointHoverBackgroundColor: '#fff',
      pointHoverBorderWidth: 3,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { intersect: false, mode: 'index' },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor:  'rgba(28,28,50,0.97)',
        titleColor:       '#eeeef8',
        bodyColor:        '#eeeef8',
        borderColor:      'rgba(255,255,255,0.1)',
        borderWidth:      1,
        padding:          14,
        cornerRadius:     8,
        displayColors:    true,
        callbacks: {
          title: items => items[0].label,
          label: ctx => {
            const v = parseFloat(ctx.raw) || 0;
            if (gaActive === 'spend') return '  ' + ctx.dataset.label + ': $' + v.toFixed(2);
            if (gaActive === 'ctr')   return '  ' + ctx.dataset.label + ': ' + v.toFixed(2) + '%';
            return '  ' + ctx.dataset.label + ': ' + Number(v).toLocaleString();
          }
        }
      }
    },
    scales: {
      x: {
        grid:   { color: 'rgba(255,255,255,0.05)', lineWidth: 1 },
        border: { color: 'rgba(255,255,255,0.08)' },
        ticks:  { color: '#8888a8', font: { size: 11 }, maxTicksLimit: 14, maxRotation: 0 }
      },
      y: {
        beginAtZero: true,
        grid:   { color: 'rgba(255,255,255,0.05)', lineWidth: 1 },
        border: { color: 'rgba(255,255,255,0.08)', dash: [4,4] },
        ticks:  {
          color: '#8888a8',
          font:  { size: 11 },
          callback: v => { const n=parseFloat(v)||0; return gaActive==='spend'?'$'+n.toFixed(2):(gaActive==='ctr'?n.toFixed(2)+'%':Number(n).toLocaleString()); }
        }
      }
    },
    animation: { duration: 350, easing: 'easeInOutQuart' }
  }
});

function gaSwitch(el) {
  document.querySelectorAll('.ga-metric').forEach(m => {
    m.classList.remove('active');
    m.style.removeProperty('--ga-color');
  });
  el.classList.add('active');
  el.style.setProperty('--ga-color', el.dataset.color);
  gaActive = el.dataset.metric;
  const d = gaAllData[gaActive];
  gaChart.data.datasets[0].data                 = d.data;
  gaChart.data.datasets[0].label                = d.label;
  gaChart.data.datasets[0].borderColor          = d.color;
  gaChart.data.datasets[0].backgroundColor      = d.fill;
  gaChart.data.datasets[0].pointBorderColor     = d.color;
  gaChart.update();
}

// Set initial CSS var
document.querySelector('.ga-metric.active').style.setProperty('--ga-color', '#1a73e8');
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>