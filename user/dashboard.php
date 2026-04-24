<?php
require_once __DIR__ . '/../includes/user_header.php';

// Pull campaign totals directly from DB
$stmt = db()->prepare(
    'SELECT SUM(impressions) AS imp, SUM(clicks) AS clk, SUM(good_hits) AS vw,
            SUM(spent) AS sp, SUM(status="active") AS active_count
     FROM campaigns WHERE user_id = ?'
);
$stmt->execute([$user['id']]);
$r = $stmt->fetch();
$totalImpressions = (int)($r['imp'] ?? 0);
$totalClicks      = (int)($r['clk'] ?? 0);
$totalViews       = (int)($r['vw']  ?? 0);
$totalSpent       = (float)($r['sp'] ?? 0);
$activeCampaigns  = (int)($r['active_count'] ?? 0);
$ctr = $totalImpressions > 0 ? round($totalViews / $totalImpressions * 100, 2) : 0;

// ── 24-hour CST rolling chart ─────────────────────────
$cstTz     = new DateTimeZone('America/Chicago');
$cstNow    = new DateTime('now', $cstTz);
$nowHour   = (int)$cstNow->format('G');
$today     = $cstNow->format('Y-m-d');
$yesterday = (new DateTime('yesterday', $cstTz))->format('Y-m-d');

$stmt = db()->prepare(
    'SELECT `date`, SUM(impressions) AS imp, SUM(good_hits) AS vw,
            SUM(clicks) AS ht, SUM(spent) AS sp
     FROM stats WHERE user_id = ? AND `date` IN (?, ?) GROUP BY `date`'
);
$stmt->execute([$user['id'], $yesterday, $today]);
$zero  = ['imp'=>0,'vw'=>0,'ht'=>0,'sp'=>0];
$days  = [$yesterday => $zero, $today => $zero];
foreach ($stmt->fetchAll() as $row) {
    $days[$row['date']] = [
        'imp'=>(int)$row['imp'], 'vw'=>(int)$row['vw'],
        'ht'=>(int)$row['ht'],   'sp'=>(float)$row['sp'],
    ];
}

$chartLabels = $chartViews = $chartImpressions = $chartHits = $chartSpend = $chartCtr = [];
for ($h = 0; $h < 24; $h++) {
    $isYest = $h > $nowHour;
    $d = $days[$isYest ? $yesterday : $today];
    $chartLabels[]      = str_pad($h,2,'0',STR_PAD_LEFT) . ':00 CST';
    $hi = (int)floor($d['imp']/24);
    $hv = (int)floor($d['vw']/24);
    $chartImpressions[] = $hi;
    $chartViews[]       = $hv;
    $chartHits[]        = (int)floor($d['ht']/24);
    $chartSpend[]       = round($d['sp']/24, 2);
    $chartCtr[]         = $hi > 0 ? round($hv/$hi*100, 2) : 0;
}

// Recent campaigns
$stmt = db()->prepare('SELECT * FROM campaigns WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$stmt->execute([$user['id']]);
$recent = $stmt->fetchAll();
?>

<div class="page-header">
  <div>
    <div class="page-title">Dashboard</div>
    <div class="page-subtitle"><span class="live-dot"></span> Live &mdash; last 24h (CST)</div>
  </div>
  <a href="/user/create_campaign.php" class="btn btn-primary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    New Campaign
  </a>
</div>

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
    <div style="font-size:11px;color:var(--text-3);margin-bottom:10px;text-align:right;display:flex;justify-content:space-between;align-items:center">
      <span><span class="live-dot" style="margin-right:5px"></span>Auto-refreshing every 3.5s</span>
      <span>Rolling last 24 hours (CST)</span>
    </div>
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
window.gaActive = 'views';
const gaAllData = {
  views:       { data: <?= json_encode($chartViews) ?>,       color: '#1a73e8', fill: 'rgba(26,115,232,0.1)', label: 'Views' },
  impressions: { data: <?= json_encode($chartImpressions) ?>, color: '#e8710a', fill: 'rgba(232,113,10,0.1)', label: 'Impressions' },
  hits:        { data: <?= json_encode($chartHits) ?>,        color: '#34a853', fill: 'rgba(52,168,83,0.1)',  label: 'Hits' },
  spend:       { data: <?= json_encode($chartSpend) ?>,       color: '#ea4335', fill: 'rgba(234,67,53,0.1)',  label: 'Spend ($)' },
  ctr:         { data: <?= json_encode($chartCtr) ?>,         color: '#9334e8', fill: 'rgba(147,52,232,0.1)', label: 'CTR (%)' }
};
const gaLabels = <?= json_encode($chartLabels) ?>;

const gaChart = new Chart(document.getElementById('gaChart').getContext('2d'), {
  type: 'line',
  data: {
    labels: gaLabels,
    datasets: [{
      label: gaAllData.views.label,
      data:  gaAllData.views.data,
      borderColor:          gaAllData.views.color,
      backgroundColor:      gaAllData.views.fill,
      borderWidth: 2.5, fill: true, tension: 0.4,
      pointRadius: 5, pointHoverRadius: 8,
      pointBackgroundColor: '#fff',
      pointBorderColor:     gaAllData.views.color,
      pointBorderWidth: 2.5,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    interaction: { intersect: false, mode: 'index' },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor:'rgba(28,28,50,0.97)', titleColor:'#eeeef8', bodyColor:'#eeeef8',
        borderColor:'rgba(255,255,255,0.1)', borderWidth:1, padding:14, cornerRadius:8,
        callbacks: {
          label: ctx => {
            const v = parseFloat(ctx.raw)||0;
            if (window.gaActive==='spend') return '  '+ctx.dataset.label+': $'+v.toFixed(2);
            if (window.gaActive==='ctr')   return '  '+ctx.dataset.label+': '+v.toFixed(2)+'%';
            return '  '+ctx.dataset.label+': '+Number(v).toLocaleString();
          }
        }
      }
    },
    scales: {
      x: { grid:{color:'rgba(255,255,255,0.05)'}, border:{color:'rgba(255,255,255,0.08)'}, ticks:{color:'#8888a8',font:{size:11},maxTicksLimit:14,maxRotation:0} },
      y: { beginAtZero:true, grid:{color:'rgba(255,255,255,0.05)'}, border:{color:'rgba(255,255,255,0.08)',dash:[4,4]},
           ticks:{color:'#8888a8',font:{size:11},callback:v=>{const n=parseFloat(v)||0;return window.gaActive==='spend'?'$'+n.toFixed(2):(window.gaActive==='ctr'?n.toFixed(2)+'%':Number(n).toLocaleString());}}
      }
    },
    animation: { duration: 250, easing: 'easeOutQuart' }
  }
});

function gaSwitch(el) {
  document.querySelectorAll('.ga-metric').forEach(m => { m.classList.remove('active'); m.style.removeProperty('--ga-color'); });
  el.classList.add('active');
  el.style.setProperty('--ga-color', el.dataset.color);
  window.gaActive = el.dataset.metric;
  const d = gaAllData[window.gaActive];
  gaChart.data.datasets[0].data            = d.data;
  gaChart.data.datasets[0].label           = d.label;
  gaChart.data.datasets[0].borderColor     = d.color;
  gaChart.data.datasets[0].backgroundColor = d.fill;
  gaChart.data.datasets[0].pointBorderColor = d.color;
  gaChart.update();
}
document.querySelector('.ga-metric.active').style.setProperty('--ga-color', '#1a73e8');

// Register for live updates from app.js poll loop
window.registerLiveChart(gaChart, 'user', null);

// When live data arrives, also refresh the "big number" above each metric
// (these are already updated by app.js via data-live attrs — this keeps the
// metric card number in sync with the chart's underlying array)
window.addEventListener('liveStatsUpdate', function(e) {
  const d = e.detail;
  if (d && d.chart) {
    gaAllData.views.data       = d.chart.views       || [];
    gaAllData.impressions.data = d.chart.impressions || [];
    gaAllData.hits.data        = d.chart.hits        || [];
    gaAllData.spend.data       = d.chart.spend       || [];
    gaAllData.ctr.data         = d.chart.ctr         || [];
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>