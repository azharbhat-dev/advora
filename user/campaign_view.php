<?php
require_once __DIR__ . '/../includes/user_header.php';

$id = $_GET['id'] ?? '';
$isNew = isset($_GET['new']);
$campaigns = readJson(CAMPAIGNS_FILE);
$campaign  = null;
foreach ($campaigns as $c) {
    if ($c['campaign_id'] === $id && $c['user_id'] === $user['id']) { $campaign = $c; break; }
}
if (!$campaign) { flash('Campaign not found', 'error'); safeRedirect('/user/campaigns.php'); }

// Handle user pause / resume
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['pause','resume'])) {
        $campaigns = readJson(CAMPAIGNS_FILE);
        foreach ($campaigns as &$c) {
            if ($c['campaign_id'] === $id && $c['user_id'] === $user['id']) {
                if ($action === 'pause'  && $c['status'] === 'active') { $c['status'] = 'paused'; flash('Campaign paused', 'success'); }
                if ($action === 'resume' && $c['status'] === 'paused') { $c['status'] = 'active'; flash('Campaign resumed', 'success'); }
                break;
            }
        }
        writeJson(CAMPAIGNS_FILE, $campaigns);
        safeRedirect('/user/campaign_view.php?id=' . urlencode($id));
    }
}

$creatives = readJson(CREATIVES_FILE);
$creative  = null;
foreach ($creatives as $cr) { if ($cr['id'] === $campaign['creative_id']) { $creative = $cr; break; } }

$settings   = getSettings();
$countryMap = [];
foreach ($settings['countries'] as $cty) $countryMap[$cty['code']] = $cty['name'];

$cpvRate     = $campaign['cpv'] ?? $campaign['cpc'] ?? 0;
$dailyBudget = $campaign['daily_budget'] ?? $campaign['budget'] ?? 0;
$ctr         = ($campaign['impressions'] ?? 0) > 0 ? round(($campaign['good_hits'] ?? 0) / ($campaign['impressions'] ?? 1) * 100, 2) : 0;

$statusClass = ['pending'=>'badge-pending','active'=>'badge-success','paused'=>'badge-muted','review'=>'badge-info','rejected'=>'badge-danger'][$campaign['status']] ?? 'badge-muted';
$statusLabel = $campaign['status'] === 'review' ? 'Under Review' : $campaign['status'];

// Build 24-hour chart for this campaign in CST timezone
$allStats  = readJson(STATS_FILE);
$campStats = array_filter($allStats, fn($s) => ($s['campaign_id'] ?? '') === $campaign['campaign_id']);


// Sanitize chart array: replace any non-finite values with 0
function sanitizeChartData(array $arr): array {
    return array_map(function($v) {
        $f = (float)$v;
        return (is_finite($f) && !is_nan($f)) ? $f : 0;
    }, $arr);
}

$cstTz     = new DateTimeZone('America/Chicago');
$cstNow    = new DateTime('now', $cstTz);
$nowHour   = (int)$cstNow->format('G');
$today     = $cstNow->format('Y-m-d');
$yesterday = (new DateTime('yesterday', $cstTz))->format('Y-m-d');

$dayData = [];
foreach ([$yesterday, $today] as $d) {
    $di = $dv = $dh = $ds = 0;
    foreach ($campStats as $s) {
        if ($s['date'] === $d) {
            $di += $s['impressions'] ?? 0;
            $dv += $s['good_hits']  ?? 0;
            $dh += $s['clicks']     ?? 0;
            $ds += $s['spent']      ?? 0;
        }
    }
    $dayData[$d] = ['imp'=>$di,'views'=>$dv,'hits'=>$dh,'spend'=>round($ds,2)];
}

$chartLabels = $chartViews = $chartImpressions = $chartHits = $chartSpend = $chartCtr = [];
for ($h = 0; $h < 24; $h++) {
    $isYesterday        = $h > $nowHour;
    $chartLabels[]      = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00 CST';
    $srcDate            = $isYesterday ? $yesterday : $today;
    $dd                 = $dayData[$srcDate];
    $chartImpressions[] = (int)floor($dd['imp']   / 24);
    $chartViews[]       = (int)floor($dd['views'] / 24);
    $chartHits[]        = (int)floor($dd['hits']  / 24);
    $chartSpend[]       = round($dd['spend'] / 24, 2);
    $di                 = (int)floor($dd['imp']   / 24);
    $dv                 = (int)floor($dd['views'] / 24);
    $chartCtr[]         = $di > 0 ? round($dv / $di * 100, 2) : 0;
}
?>

<?php if ($isNew): ?>
<div class="campaign-id-display">
    <div class="label">Campaign Created — Your Campaign ID:</div>
    <div class="id"><?= htmlspecialchars($campaign['campaign_id']) ?>
        <button class="copy-btn" onclick="copyText('<?= $campaign['campaign_id'] ?>', this)" style="margin-left:12px">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            Copy
        </button>
    </div>
    <p style="font-size:12px;color:var(--text-2);margin-top:10px">Status: Under Review</p>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <div class="page-title"><?= htmlspecialchars($campaign['name']) ?></div>
        <div class="page-subtitle">
            <code style="color:var(--yellow)"><?= htmlspecialchars($campaign['campaign_id']) ?></code>
            <span class="badge <?= $statusClass ?>" data-live-badge="camp:<?= $campaign['campaign_id'] ?>:status" data-current-status="<?= $campaign['status'] ?>" style="margin-left:8px"><?= $statusLabel ?></span>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if ($campaign['status'] === 'active'): ?>
        <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="pause">
            <button type="submit" class="btn btn-secondary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                Pause
            </button>
        </form>
        <?php elseif ($campaign['status'] === 'paused'): ?>
        <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="resume">
            <button type="submit" class="btn btn-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                Resume
            </button>
        </form>
        <?php endif; ?>
        <a href="/user/create_campaign.php?edit=<?= urlencode($campaign['campaign_id']) ?>" class="btn btn-secondary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
        </a>
        <a href="/user/campaigns.php" class="btn btn-secondary">&larr; Back</a>
    </div>
</div>

<?php if ($campaign['status'] === 'rejected'): ?>
<div class="alert alert-danger">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    Campaign was declined.<?= !empty($campaign['reject_reason']) ? ' Reason: ' . htmlspecialchars($campaign['reject_reason']) : '' ?>
</div>
<?php elseif (in_array($campaign['status'], ['review','pending'])): ?>
<div class="alert alert-info">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    Campaign is currently <strong>Under Review</strong>.
</div>
<?php elseif ($campaign['status'] === 'active'): ?>
<div class="alert alert-success">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    Campaign is live and running.
</div>
<?php elseif ($campaign['status'] === 'paused'): ?>
<div class="alert alert-warning">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
    Campaign is paused.
</div>
<?php endif; ?>

<!-- Stat cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
        <div class="stat-label">Impressions</div>
        <div class="stat-value" data-live="camp:<?= $campaign['campaign_id'] ?>:impressions"><?= fmtNum($campaign['impressions'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,208,132,.1);color:var(--green)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
        <div class="stat-label">Views</div>
        <div class="stat-value" data-live="camp:<?= $campaign['campaign_id'] ?>:views"><?= fmtNum($campaign['good_hits'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(52,152,219,.1);color:var(--blue)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/></svg></div>
        <div class="stat-label">Hits</div>
        <div class="stat-value" data-live="camp:<?= $campaign['campaign_id'] ?>:hits"><?= fmtNum($campaign['clicks'] ?? 0) ?></div>
        <div class="stat-change">CTR: <?= $ctr ?>%</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,149,0,.1);color:var(--orange)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/></svg></div>
        <div class="stat-label">Spent</div>
        <div class="stat-value" data-live-money="camp:<?= $campaign['campaign_id'] ?>:spent"><?= fmtMoney($campaign['spent'] ?? 0) ?></div>
        <div class="stat-change">Daily: <?= fmtMoney($dailyBudget) ?></div>
    </div>
</div>

<!-- Google Ads style chart -->
<div class="card" style="padding:0;overflow:hidden">
  <div class="ga-metrics-row">
    <div class="ga-metric"        data-metric="impressions" data-color="#e8710a" onclick="gaSwitch(this)"><div class="gam-label">Impressions</div><div class="gam-value"><?= fmtNum($campaign['impressions']??0) ?></div></div>
    <div class="ga-metric active" data-metric="views"       data-color="#1a73e8" onclick="gaSwitch(this)"><div class="gam-label">Views</div><div class="gam-value"><?= fmtNum($campaign['good_hits']??0) ?></div></div>
    
    <div class="ga-metric"        data-metric="hits"        data-color="#34a853" onclick="gaSwitch(this)"><div class="gam-label">Hits</div><div class="gam-value"><?= fmtNum($campaign['clicks']??0) ?></div></div>
    <div class="ga-metric"        data-metric="spend"       data-color="#ea4335" onclick="gaSwitch(this)"><div class="gam-label">Spend</div><div class="gam-value"><?= fmtMoney($campaign['spent']??0) ?></div></div>
    <div class="ga-metric"        data-metric="ctr"         data-color="#9334e8" onclick="gaSwitch(this)"><div class="gam-label">CTR</div><div class="gam-value"><?= $ctr ?>%</div></div>
  </div>
  <div style="padding:8px 24px 20px">
    <div style="font-size:11px;color:var(--text-3);margin-bottom:10px;text-align:right">Last 24 Hours (CST)</div>
    <div style="position:relative;height:260px"><canvas id="gaChart"></canvas></div>
  </div>
</div>

<!-- Details + Targeting -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="cv-grid">
<style>@media(max-width:900px){.cv-grid{grid-template-columns:1fr!important}}</style>

<div class="card">
    <div class="card-title" style="margin-bottom:16px">Campaign Details</div>
    <div style="font-size:13px">
        <div class="detail-row"><span class="dk">CPV</span><strong class="dv"><?= fmtMoney($cpvRate) ?></strong></div>
        <div class="detail-row"><span class="dk">Daily Budget</span><strong class="dv"><?= fmtMoney($dailyBudget) ?></strong></div>
        <div class="detail-row"><span class="dk">Delivery</span><strong class="dv" style="text-transform:capitalize"><?= htmlspecialchars($campaign['delivery'] ?? 'even') ?></strong></div>
        <div class="detail-row"><span class="dk">Creative</span><strong class="dv"><?= $creative ? htmlspecialchars($creative['name']) : 'N/A' ?></strong></div>
        <?php if (!empty($campaign['sources'])): ?>
        <div class="detail-row">
            <span class="dk">Sources</span>
            <span class="dv" style="display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end">
                <?php foreach ($campaign['sources'] as $src): ?>
                <span class="badge badge-info" style="font-size:10px"><?= ucfirst(htmlspecialchars($src)) ?></span>
                <?php endforeach; ?>
            </span>
        </div>
        <?php endif; ?>
        <div class="detail-row"><span class="dk">IP Filter</span><strong class="dv" style="text-transform:capitalize"><?= $campaign['ip_mode'] ?></strong></div>
        <div class="detail-row"><span class="dk">Created</span><strong class="dv"><?= date('M d, Y', $campaign['created_at']) ?></strong></div>
    </div>
</div>

<div class="card">
    <div class="card-title" style="margin-bottom:16px">Targeting</div>
    <div style="font-size:12px;color:var(--text-2);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">Countries (<?= count($campaign['countries']) ?>)</div>
    <div style="display:flex;flex-wrap:wrap;gap:6px">
        <?php foreach ($campaign['countries'] as $cc): ?>
        <div class="country-item selected" style="padding:5px 10px">
            <img class="country-flag" src="https://flagcdn.com/w40/<?= strtolower($cc==='UK'?'gb':$cc) ?>.png" alt="<?= $cc ?>">
            <span class="country-code"><?= $cc ?></span>
            <span class="country-name" style="font-size:11px"><?= htmlspecialchars($countryMap[$cc] ?? $cc) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</div>

<style>
.ga-metrics-row{display:flex;border-bottom:1px solid var(--border);overflow-x:auto}
.ga-metric{flex:1;min-width:100px;padding:14px 16px;cursor:pointer;border-bottom:3px solid transparent;transition:all .15s;user-select:none}
.ga-metric:hover{background:rgba(255,255,255,.025)}
.ga-metric.active{border-bottom-color:var(--ga-color,#1a73e8)}
.gam-label{font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-3);margin-bottom:5px}
.ga-metric.active .gam-label{color:var(--ga-color,#1a73e8)}
.gam-value{font-size:19px;font-weight:700;letter-spacing:-.5px;color:var(--text)}
.ga-metric.active .gam-value{color:var(--ga-color,#1a73e8)}
</style>

<script>

// Safe number helper for chart
function safeNum(v) { const n = parseFloat(v); return isNaN(n) ? 0 : n; }
function safeFixed(v, d) { const n = parseFloat(v); return isNaN(n) ? (0).toFixed(d) : n.toFixed(d); }

const gaAllData = {
  views:       { data: <?= json_encode(sanitizeChartData($chartViews)) ?>,       color: '#1a73e8', fill: 'rgba(26,115,232,0.1)',  label: 'Views' },
  impressions: { data: <?= json_encode(sanitizeChartData($chartImpressions)) ?>, color: '#e8710a', fill: 'rgba(232,113,10,0.1)',  label: 'Impressions' },
  hits:        { data: <?= json_encode(sanitizeChartData($chartHits)) ?>,        color: '#34a853', fill: 'rgba(52,168,83,0.1)',   label: 'Hits' },
  spend:       { data: <?= json_encode(sanitizeChartData($chartSpend)) ?>,       color: '#ea4335', fill: 'rgba(234,67,53,0.1)',   label: 'Spend ($)' },
  ctr:         { data: <?= json_encode(sanitizeChartData($chartCtr)) ?>,         color: '#9334e8', fill: 'rgba(147,52,232,0.1)',  label: 'CTR (%)' }
};
const gaLabels = <?= json_encode($chartLabels) ?>;
let gaActive   = 'views';

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
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    interaction: { intersect: false, mode: 'index' },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: 'rgba(28,28,50,0.97)',
        titleColor: '#eeeef8', bodyColor: '#eeeef8',
        borderColor: 'rgba(255,255,255,0.1)', borderWidth: 1,
        padding: 14, cornerRadius: 8,
        callbacks: {
          label: ctx => {
            const v = ctx.raw;
            if (gaActive==='spend') return '  '+ctx.dataset.label+': $'+v.toFixed(2);
            if (gaActive==='ctr')   return '  '+ctx.dataset.label+': '+v.toFixed(2)+'%';
            return '  '+ctx.dataset.label+': '+Number(v).toLocaleString();
          }
        }
      }
    },
    scales: {
      x: { grid:{color:'rgba(255,255,255,0.05)'}, border:{color:'rgba(255,255,255,0.08)'}, ticks:{color:'#8888a8',font:{size:11},maxTicksLimit:14,maxRotation:0} },
      y: { beginAtZero:true, grid:{color:'rgba(255,255,255,0.05)'}, border:{color:'rgba(255,255,255,0.08)'},
           ticks:{color:'#8888a8',font:{size:11},callback:v=>gaActive==='spend'?'$'+v:(gaActive==='ctr'?v+'%':Number(v).toLocaleString())} }
    },
    animation: { duration: 350, easing: 'easeInOutQuart' }
  }
});

function gaSwitch(el) {
  document.querySelectorAll('.ga-metric').forEach(m => { m.classList.remove('active'); m.style.removeProperty('--ga-color'); });
  el.classList.add('active');
  el.style.setProperty('--ga-color', el.dataset.color);
  gaActive = el.dataset.metric;
  const d = gaAllData[gaActive];
  gaChart.data.datasets[0].data            = d.data.map(v => parseFloat(v)||0);
  gaChart.data.datasets[0].label           = d.label;
  gaChart.data.datasets[0].borderColor     = d.color;
  gaChart.data.datasets[0].backgroundColor = d.fill;
  gaChart.data.datasets[0].pointBorderColor = d.color;
  gaChart.update();
}
document.querySelector('.ga-metric.active').style.setProperty('--ga-color', '#1a73e8');
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>