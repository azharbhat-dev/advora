<?php
require_once __DIR__ . '/../includes/user_header.php';

$id = $_GET['id'] ?? '';
$isNew = isset($_GET['new']);
$campaigns = readJson(CAMPAIGNS_FILE);
$campaign  = null;
foreach ($campaigns as $c) {
    if ($c['campaign_id'] === $id && $c['user_id'] === $user['id']) { $campaign = $c; break; }
}
if (!$campaign) {
    flash('Campaign not found', 'error');
    safeRedirect('/user/campaigns.php');
}

// Handle user pause / resume
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['pause','resume'])) {
        $campaigns = readJson(CAMPAIGNS_FILE);
        foreach ($campaigns as &$c) {
            if ($c['campaign_id'] === $id && $c['user_id'] === $user['id']) {
                if ($action === 'pause' && $c['status'] === 'active') {
                    $c['status'] = 'paused';
                    flash('Campaign paused', 'success');
                } elseif ($action === 'resume' && $c['status'] === 'paused') {
                    $c['status'] = 'active';
                    flash('Campaign resumed', 'success');
                }
                break;
            }
        }
        writeJson(CAMPAIGNS_FILE, $campaigns);
        safeRedirect('/user/campaign_view.php?id=' . urlencode($id));
    }
}

$creatives = readJson(CREATIVES_FILE);
$creative  = null;
foreach ($creatives as $cr) {
    if ($cr['id'] === $campaign['creative_id']) { $creative = $cr; break; }
}

$settings   = getSettings();
$countryMap = [];
foreach ($settings['countries'] as $cty) $countryMap[$cty['code']] = $cty['name'];

$ctr = ($campaign['impressions'] ?? 0) > 0 ? round(($campaign['clicks'] / $campaign['impressions']) * 100, 2) : 0;

// CPV - support both legacy cpc field and new cpv field
$cpvRate = $campaign['cpv'] ?? $campaign['cpc'] ?? 0;

// Daily budget - support both legacy budget and new daily_budget field
$dailyBudget = $campaign['daily_budget'] ?? $campaign['budget'] ?? 0;

$statusClass = [
    'pending'  => 'badge-pending',
    'active'   => 'badge-success',
    'paused'   => 'badge-muted',
    'review'   => 'badge-info',
    'rejected' => 'badge-danger'
][$campaign['status']] ?? 'badge-muted';

// Status display label
$statusLabel = $campaign['status'] === 'review' ? 'Under Review' : $campaign['status'];
?>

<?php if ($isNew): ?>
<div class="campaign-id-display">
    <div class="label">Campaign Created Successfully! Your Unique Campaign ID:</div>
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
            <span class="badge <?= $statusClass ?>"
              data-live-badge="camp:<?= $campaign['campaign_id'] ?>:status"
              data-current-status="<?= $campaign['status'] ?>"
              style="margin-left:8px"><?= $statusLabel ?></span>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <!-- User pause / resume -->
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
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    This campaign was rejected. <?= !empty($campaign['reject_reason']) ? 'Reason: ' . htmlspecialchars($campaign['reject_reason']) : '' ?>
</div>
<?php elseif (in_array($campaign['status'], ['review', 'pending'])): ?>
<div class="alert alert-info">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    Campaign is currently <strong>Under Review</strong>.
</div>
<?php elseif ($campaign['status'] === 'active'): ?>
<div class="alert alert-success">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    Campaign is live and running.
</div>
<?php elseif ($campaign['status'] === 'paused'): ?>
<div class="alert alert-warning">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
    Campaign is paused. Click Resume to restart it.
</div>
<?php endif; ?>

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
        <div class="stat-label">Spent Today</div>
        <div class="stat-value" data-live-money="camp:<?= $campaign['campaign_id'] ?>:spent"><?= fmtMoney($campaign['spent'] ?? 0) ?></div>
        <div class="stat-change">Daily: <?= fmtMoney($dailyBudget) ?></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px" class="cv-grid">
<style>@media(max-width:900px){.cv-grid{grid-template-columns:1fr!important}}</style>

<div class="card">
    <div class="card-header">
        <div class="card-title">Performance Chart</div>
        <span class="live-dot"></span>
    </div>
    <div class="chart-wrap"><canvas id="campChart"></canvas></div>
</div>

<div class="card">
    <div class="card-title" style="margin-bottom:16px">Campaign Details</div>
    <div style="font-size:13px">
        <div class="detail-row"><span class="dk">CPV</span><strong class="dv"><?= fmtMoneyPrecise($cpvRate) ?></strong></div>
        <div class="detail-row"><span class="dk">Daily Budget</span><strong class="dv"><?= fmtMoney($dailyBudget) ?></strong></div>
        <div class="detail-row"><span class="dk">Delivery</span><strong class="dv" style="text-transform:capitalize"><?= htmlspecialchars($campaign['delivery'] ?? 'even') ?></strong></div>
        <div class="detail-row"><span class="dk">Creative</span><strong class="dv"><?= $creative ? htmlspecialchars($creative['name']) : 'N/A' ?></strong></div>
        <?php if (!empty($campaign['sources'])): ?>
        <div class="detail-row">
            <span class="dk">Sources</span>
            <span class="dv" style="display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end">
                <?php foreach ($campaign['sources'] as $src): ?>
                <span class="badge badge-info" style="font-size:10px"><?= htmlspecialchars(ucfirst($src)) ?></span>
                <?php endforeach; ?>
            </span>
        </div>
        <?php endif; ?>
        <div class="detail-row"><span class="dk">IP Filter</span><strong class="dv" style="text-transform:capitalize"><?= $campaign['ip_mode'] ?></strong></div>
        <div class="detail-row"><span class="dk">Domain Filter</span><strong class="dv" style="text-transform:capitalize"><?= $campaign['domain_mode'] ?></strong></div>
        <div class="detail-row"><span class="dk">Created</span><strong class="dv"><?= date('M d, Y', $campaign['created_at']) ?></strong></div>
    </div>
</div>
</div>

<div class="card">
    <div class="card-title" style="margin-bottom:16px">Targeting</div>
    <div style="margin-bottom:16px">
        <div style="font-size:12px;color:var(--text-2);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">Countries (<?= count($campaign['countries']) ?>)</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($campaign['countries'] as $cc): ?>
            <div class="country-item selected" style="padding:6px 12px">
                <img class="country-flag" src="https://flagcdn.com/w40/<?= strtolower($cc==='UK'?'gb':$cc) ?>.png" alt="<?= $cc ?>">
                <span class="country-code"><?= $cc ?></span>
                <span class="country-name" style="font-size:12px"><?= htmlspecialchars($countryMap[$cc] ?? $cc) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($campaign['ip_list'])): ?>
    <div style="margin-bottom:16px">
        <div style="font-size:12px;color:var(--text-2);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">IP List (<?= ucfirst($campaign['ip_mode']) ?>)</div>
        <div class="ip-list"><?php foreach ($campaign['ip_list'] as $ip): ?><div class="ip-tag"><?= htmlspecialchars($ip) ?></div><?php endforeach; ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($campaign['domain_list'])): ?>
    <div>
        <div style="font-size:12px;color:var(--text-2);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">Domain List (<?= ucfirst($campaign['domain_mode']) ?>)</div>
        <div class="ip-list"><?php foreach ($campaign['domain_list'] as $d): ?><div class="ip-tag"><?= htmlspecialchars($d) ?></div><?php endforeach; ?></div>
    </div>
    <?php endif; ?>
</div>

<script>
const rawStats = <?= json_encode(array_values(array_filter(readJson(STATS_FILE), fn($s) => ($s['campaign_id'] ?? '') === $campaign['campaign_id']))) ?>;

function buildChartData() {
    const labels=[], imp=[], clk=[], gh=[];
    for (let i=6;i>=0;i--) {
        const d=new Date(); d.setDate(d.getDate()-i);
        const ds=d.toISOString().slice(0,10);
        labels.push(d.toLocaleDateString('en',{month:'short',day:'numeric'}));
        let di=0,dc=0,dg=0;
        rawStats.forEach(s=>{ if(s.date===ds){di+=s.impressions||0;dc+=s.clicks||0;dg+=s.good_hits||0;} });
        imp.push(di); clk.push(dc); gh.push(dg);
    }
    return {labels,imp,clk,gh};
}
const cd=buildChartData();
const ctx=document.getElementById('campChart').getContext('2d');
new Chart(ctx,{
    type:'line',
    data:{
        labels:cd.labels,
        datasets:[
            {label:'Impressions',data:cd.imp,borderColor:'#ffcc00',backgroundColor:'rgba(255,204,0,.1)',fill:true,tension:.4},
            {label:'Hits',     data:cd.clk,borderColor:'#3498db',backgroundColor:'rgba(52,152,219,.1)',fill:true,tension:.4},
            {label:'Views',      data:cd.gh, borderColor:'#00d084',backgroundColor:'rgba(0,208,132,.1)', fill:true,tension:.4}
        ]
    },
    options:{
        responsive:true,maintainAspectRatio:false,
        plugins:{legend:{labels:{color:'#a0a0a0'}}},
        scales:{
            y:{beginAtZero:true,grid:{color:'rgba(255,255,255,.05)'},ticks:{color:'#a0a0a0'}},
            x:{grid:{color:'rgba(255,255,255,.05)'},ticks:{color:'#a0a0a0'}}
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>