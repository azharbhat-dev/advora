<?php
require_once __DIR__ . '/../includes/user_header.php';

$id    = $_GET['id'] ?? '';
$isNew = isset($_GET['new']);

// Fetch campaign directly from DB (with ownership check)
$stmt = db()->prepare('SELECT * FROM campaigns WHERE campaign_id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$campRow = $stmt->fetch();
if (!$campRow) { flash('Campaign not found', 'error'); safeRedirect('/user/campaigns.php'); }

// Decode JSON-in-column fields
$campaign = [
    'campaign_id'   => $campRow['campaign_id'],
    'user_id'       => $campRow['user_id'],
    'name'          => $campRow['name'],
    'cpv'           => (float)$campRow['cpv'],
    'cpc'           => (float)$campRow['cpc'],
    'creative_id'   => $campRow['creative_id'],
    'countries'     => jdec($campRow['countries']),
    'states'        => jdec($campRow['states']),
    'schedule'      => jdec($campRow['schedule']),
    'ip_mode'       => $campRow['ip_mode'],
    'domain_mode'   => $campRow['domain_mode'],
    'ip_list'       => jdec($campRow['ip_list']),
    'domain_list'   => jdec($campRow['domain_list']),
    'daily_budget'  => (float)$campRow['daily_budget'],
    'budget'        => (float)$campRow['budget'],
    'delivery'      => $campRow['delivery'],
    'sources'       => jdec($campRow['sources']),
    'spent'         => (float)$campRow['spent'],
    'impressions'   => (int)$campRow['impressions'],
    'clicks'        => (int)$campRow['clicks'],
    'good_hits'     => (int)$campRow['good_hits'],
    'views_count'   => (int)$campRow['views_count'],
    'status'        => $campRow['status'],
    'reject_reason' => $campRow['reject_reason'] ?? '',
    'created_at'    => (int)$campRow['created_at'],
];

// Handle POST actions (pause/resume/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM campaigns WHERE campaign_id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        addAdminNotification($user['id'], $user['username'], 'campaign_deleted',
            'Campaign Deleted',
            $user['username'] . ' deleted campaign "' . $campaign['name'] . '" (' . $id . ')'
        );
        flash('Campaign deleted', 'success');
        safeRedirect('/user/campaigns.php');
    }

    if (in_array($action, ['pause','resume'])) {
        if ($action === 'pause' && $campaign['status'] === 'active') {
            $stmt = db()->prepare('UPDATE campaigns SET status = "paused" WHERE campaign_id = ? AND user_id = ?');
            $stmt->execute([$id, $user['id']]);
            flash('Campaign paused', 'success');
            addAdminNotification($user['id'], $user['username'], 'campaign_paused',
                'Campaign Paused',
                $user['username'] . ' paused campaign "' . $campaign['name'] . '" (' . $id . ')'
            );
        }
        if ($action === 'resume' && $campaign['status'] === 'paused') {
            $stmt = db()->prepare('UPDATE campaigns SET status = "active" WHERE campaign_id = ? AND user_id = ?');
            $stmt->execute([$id, $user['id']]);
            flash('Campaign resumed', 'success');
            addAdminNotification($user['id'], $user['username'], 'campaign_resumed',
                'Campaign Resumed',
                $user['username'] . ' resumed campaign "' . $campaign['name'] . '" (' . $id . ')'
            );
        }
        safeRedirect('/user/campaign_view.php?id=' . urlencode($id));
    }
}

// Creative
$creative = null;
if (!empty($campaign['creative_id'])) {
    $stmt = db()->prepare('SELECT * FROM creatives WHERE id = ?');
    $stmt->execute([$campaign['creative_id']]);
    $creative = $stmt->fetch() ?: null;
}

$settings   = getSettings();
$countryMap = [];
foreach ($settings['countries'] as $cty) $countryMap[$cty['code']] = $cty['name'];

$cpvRate     = $campaign['cpv'] ?: $campaign['cpc'];
$dailyBudget = $campaign['daily_budget'] ?: $campaign['budget'];
$ctr = $campaign['impressions'] > 0 ? round($campaign['good_hits'] / $campaign['impressions'] * 100, 2) : 0;

$statusClass = ['pending'=>'badge-pending','active'=>'badge-success','paused'=>'badge-muted','review'=>'badge-info','rejected'=>'badge-danger'][$campaign['status']] ?? 'badge-muted';
$statusLabel = $campaign['status'] === 'review' ? 'Under Review' : $campaign['status'];

// ── Build 24-hour CST rolling chart for this campaign ──
$cstTz     = new DateTimeZone('America/Chicago');
$cstNow    = new DateTime('now', $cstTz);
$nowHour   = (int)$cstNow->format('G');
$today     = $cstNow->format('Y-m-d');
$yesterday = (new DateTime('yesterday', $cstTz))->format('Y-m-d');

$stmt = db()->prepare(
    'SELECT `date`, SUM(impressions) AS imp, SUM(good_hits) AS vw,
            SUM(clicks) AS ht, SUM(spent) AS sp
     FROM stats WHERE campaign_id = ? AND `date` IN (?, ?) GROUP BY `date`'
);
$stmt->execute([$id, $yesterday, $today]);
$zero = ['imp'=>0,'vw'=>0,'ht'=>0,'sp'=>0];
$days = [$yesterday => $zero, $today => $zero];
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
        <form method="POST" style="display:inline"
              onsubmit="return confirm('Permanently delete this campaign? This cannot be undone.')">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                Delete Campaign
            </button>
        </form>
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

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
        <div class="stat-label">Impressions</div>
        <div class="stat-value" data-live="camp:<?= $campaign['campaign_id'] ?>:impressions"><?= fmtNum($campaign['impressions']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(0,208,132,.1);color:var(--green)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
        <div class="stat-label">Views</div>
        <div class="stat-value" data-live="camp:<?= $campaign['campaign_id'] ?>:views"><?= fmtNum($campaign['good_hits']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(52,152,219,.1);color:var(--blue)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/></svg></div>
        <div class="stat-label">Hits</div>
        <div class="stat-value" data-live="camp:<?= $campaign['campaign_id'] ?>:hits"><?= fmtNum($campaign['clicks']) ?></div>
        <div class="stat-change">CTR: <span data-live="camp:<?= $campaign['campaign_id'] ?>:ctr"><?= $ctr ?>%</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,149,0,.1);color:var(--orange)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/></svg></div>
        <div class="stat-label">Spent</div>
        <div class="stat-value" data-live-money="camp:<?= $campaign['campaign_id'] ?>:spent"><?= fmtMoney($campaign['spent']) ?></div>
        <div class="stat-change">Daily: <?= fmtMoney($dailyBudget) ?></div>
    </div>
</div>

<div class="card" style="padding:0;overflow:hidden">
  <div class="ga-metrics-row">
    <div class="ga-metric"        data-metric="impressions" data-color="#e8710a" onclick="gaSwitch(this)"><div class="gam-label">Impressions</div><div class="gam-value" data-live="camp:<?= $campaign['campaign_id'] ?>:impressions"><?= fmtNum($campaign['impressions']) ?></div></div>
    <div class="ga-metric active" data-metric="views"       data-color="#1a73e8" onclick="gaSwitch(this)"><div class="gam-label">Views</div><div class="gam-value" data-live="camp:<?= $campaign['campaign_id'] ?>:views"><?= fmtNum($campaign['good_hits']) ?></div></div>
    <div class="ga-metric"        data-metric="hits"        data-color="#34a853" onclick="gaSwitch(this)"><div class="gam-label">Hits</div><div class="gam-value" data-live="camp:<?= $campaign['campaign_id'] ?>:hits"><?= fmtNum($campaign['clicks']) ?></div></div>
    <div class="ga-metric"        data-metric="spend"       data-color="#ea4335" onclick="gaSwitch(this)"><div class="gam-label">Spend</div><div class="gam-value" data-live-money="camp:<?= $campaign['campaign_id'] ?>:spent"><?= fmtMoney($campaign['spent']) ?></div></div>
    <div class="ga-metric"        data-metric="ctr"         data-color="#9334e8" onclick="gaSwitch(this)"><div class="gam-label">CTR</div><div class="gam-value" data-live="camp:<?= $campaign['campaign_id'] ?>:ctr"><?= $ctr ?>%</div></div>
  </div>
  <div style="padding:8px 24px 20px">
    <div style="font-size:11px;color:var(--text-3);margin-bottom:10px;display:flex;justify-content:space-between;align-items:center">
      <span><span class="live-dot" style="margin-right:5px"></span>Auto-refreshing every 3.5s</span>
      <span>Rolling last 24 hours (CST)</span>
    </div>
    <div style="position:relative;height:260px"><canvas id="gaChart"></canvas></div>
  </div>
</div>

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
window.gaActive = 'views';
const gaAllData = {
  views:       { data: <?= json_encode($chartViews) ?>,       color:'#1a73e8', fill:'rgba(26,115,232,0.1)', label:'Views' },
  impressions: { data: <?= json_encode($chartImpressions) ?>, color:'#e8710a', fill:'rgba(232,113,10,0.1)', label:'Impressions' },
  hits:        { data: <?= json_encode($chartHits) ?>,        color:'#34a853', fill:'rgba(52,168,83,0.1)',  label:'Hits' },
  spend:       { data: <?= json_encode($chartSpend) ?>,       color:'#ea4335', fill:'rgba(234,67,53,0.1)',  label:'Spend ($)' },
  ctr:         { data: <?= json_encode($chartCtr) ?>,         color:'#9334e8', fill:'rgba(147,52,232,0.1)', label:'CTR (%)' }
};
const gaLabels = <?= json_encode($chartLabels) ?>;

const gaChart = new Chart(document.getElementById('gaChart').getContext('2d'), {
  type:'line',
  data:{ labels:gaLabels, datasets:[{
    label:gaAllData.views.label, data:gaAllData.views.data,
    borderColor:gaAllData.views.color, backgroundColor:gaAllData.views.fill,
    borderWidth:2.5, fill:true, tension:0.4,
    pointRadius:5, pointHoverRadius:8,
    pointBackgroundColor:'#fff', pointBorderColor:gaAllData.views.color, pointBorderWidth:2.5
  }]},
  options:{
    responsive:true, maintainAspectRatio:false,
    interaction:{ intersect:false, mode:'index' },
    plugins:{
      legend:{display:false},
      tooltip:{
        backgroundColor:'rgba(28,28,50,0.97)', titleColor:'#eeeef8', bodyColor:'#eeeef8',
        borderColor:'rgba(255,255,255,0.1)', borderWidth:1, padding:14, cornerRadius:8,
        callbacks:{
          label: ctx => {
            const v = parseFloat(ctx.raw)||0;
            if (window.gaActive==='spend') return '  '+ctx.dataset.label+': $'+v.toFixed(2);
            if (window.gaActive==='ctr')   return '  '+ctx.dataset.label+': '+v.toFixed(2)+'%';
            return '  '+ctx.dataset.label+': '+Number(v).toLocaleString();
          }
        }
      }
    },
    scales:{
      x:{ grid:{color:'rgba(255,255,255,0.05)'}, border:{color:'rgba(255,255,255,0.08)'}, ticks:{color:'#8888a8',font:{size:11},maxTicksLimit:14,maxRotation:0}},
      y:{ beginAtZero:true, grid:{color:'rgba(255,255,255,0.05)'}, border:{color:'rgba(255,255,255,0.08)'},
          ticks:{color:'#8888a8',font:{size:11},callback:v=>{const n=parseFloat(v)||0;return window.gaActive==='spend'?'$'+n.toFixed(2):(window.gaActive==='ctr'?n.toFixed(2)+'%':Number(n).toLocaleString());}} }
    },
    animation:{ duration:250, easing:'easeOutQuart' }
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

// Register for live updates — campaign-specific
window.registerLiveChart(gaChart, 'campaign', '<?= addslashes($campaign['campaign_id']) ?>');

// Keep gaAllData in sync with live data so metric switching uses fresh values
window.addEventListener('liveStatsUpdate', function(e) {
  const d = e.detail;
  if (d && d.camp_charts && d.camp_charts['<?= addslashes($campaign['campaign_id']) ?>']) {
    const cc = d.camp_charts['<?= addslashes($campaign['campaign_id']) ?>'];
    gaAllData.views.data       = cc.views       || [];
    gaAllData.impressions.data = cc.impressions || [];
    gaAllData.hits.data        = cc.hits        || [];
    gaAllData.spend.data       = cc.spend       || [];
    gaAllData.ctr.data         = cc.ctr         || [];
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>