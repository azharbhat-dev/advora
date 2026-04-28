<?php
require_once __DIR__ . '/../includes/admin_header.php';

$users     = readJson(USERS_FILE);
$campaigns = readJson(CAMPAIGNS_FILE);
$stats     = readJson(STATS_FILE);

// Build user map
$userMap = [];
foreach ($users as $u) $userMap[$u['id']] = $u;

// Pre-selected user/campaign from URL (e.g. from user_details page)
$preUser = $_GET['user'] ?? '';
$preCamp = $_GET['camp'] ?? '';

// ── POST: inject stats ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid      = $_POST['campaign_id'] ?? '';
    $addImp   = max(0, (int)($_POST['impressions'] ?? 0));
    $addViews = max(0, (int)($_POST['views']       ?? 0));
    $addHits  = max(0, (int)($_POST['hits']        ?? 0));
    $date     = $_POST['date'] ?? date('Y-m-d');

    $found    = null;
    $foundIdx = null;
    foreach ($campaigns as $idx => $camp) {
        if ($camp['campaign_id'] === $cid) { $found = $camp; $foundIdx = $idx; break; }
    }

    if (!$found) {
        flash('Campaign not found', 'error');
    } else {
        $userIdx = null;
        foreach ($users as $idx => $u) {
            if ($u['id'] === $found['user_id']) { $userIdx = $idx; break; }
        }
        if ($userIdx === null) {
            flash('User not found', 'error');
        } else {
            $cpv     = (float)($found['cpv'] ?? $found['cpc'] ?? 0);
            $cost    = round($addViews * $cpv, 4);
            $curBal  = (float)$users[$userIdx]['balance'];

            if ($cost > $curBal) {
                flash('Insufficient balance — user has '.fmtMoney($curBal).' but needs '.fmtMoney($cost).' for '.$addViews.' views at '.fmtMoney($cpv).'/view', 'error');
            } else {
                // Update campaign totals
                $campaigns[$foundIdx]['impressions'] = ($campaigns[$foundIdx]['impressions'] ?? 0) + $addImp;
                $campaigns[$foundIdx]['clicks']      = ($campaigns[$foundIdx]['clicks']      ?? 0) + $addHits;
                $campaigns[$foundIdx]['good_hits']   = ($campaigns[$foundIdx]['good_hits']   ?? 0) + $addViews;
                $campaigns[$foundIdx]['views_count'] = ($campaigns[$foundIdx]['views_count'] ?? 0) + $addViews;
                $campaigns[$foundIdx]['spent']       = (float)(($campaigns[$foundIdx]['spent'] ?? 0) + $cost);
                writeJson(CAMPAIGNS_FILE, $campaigns);

                // Deduct balance
                $users[$userIdx]['balance'] = round($curBal - $cost, 4);
                writeJson(USERS_FILE, $users);

                // Update / insert daily stats (kept for Metrics page historical view)
                $statFound = false;
                foreach ($stats as &$s) {
                    if ($s['campaign_id'] === $cid && $s['date'] === $date) {
                        $s['impressions'] = ($s['impressions'] ?? 0) + $addImp;
                        $s['clicks']      = ($s['clicks']      ?? 0) + $addHits;
                        $s['good_hits']   = ($s['good_hits']   ?? 0) + $addViews;
                        $s['spent']       = (float)(($s['spent'] ?? 0) + $cost);
                        $statFound = true;
                        break;
                    }
                }
                if (!$statFound) {
                    $stats[] = [
                        'user_id'     => $found['user_id'],
                        'campaign_id' => $cid,
                        'date'        => $date,
                        'impressions' => $addImp,
                        'clicks'      => $addHits,
                        'good_hits'   => $addViews,
                        'spent'       => $cost,
                    ];
                }
                writeJson(STATS_FILE, $stats);

                // ── HOURLY BUCKET — drives the live dashboard chart ──
                // Records into the CURRENT CST hour so the chart's bar at e.g. 14:00
                // grows by exactly the injected amount, live.
                addHourlyStats($found['user_id'], $cid, $addImp, $addViews, $addHits, $cost);

                flash('Injected: '.$addImp.' impressions, '.$addViews.' views, '.$addHits.' hits. Balance deducted: '.fmtMoney($cost), 'success');
                safeRedirect('/admin/stats_injector.php?user='.urlencode($found['user_id']).'&camp='.urlencode($cid));
            }
        }
    }
    safeRedirect('/admin/stats_injector.php'.($preUser ? '?user='.urlencode($preUser) : ''));
}

// Build per-user campaign list for JS
$userCampaignsJson = [];
foreach ($campaigns as $camp) {
    $uid = $camp['user_id'];
    if (!isset($userCampaignsJson[$uid])) $userCampaignsJson[$uid] = [];
    $u   = $userMap[$uid] ?? null;
    $userCampaignsJson[$uid][] = [
        'id'      => $camp['campaign_id'],
        'name'    => $camp['name'],
        'status'  => $camp['status'],
        'cpv'     => (float)($camp['cpv'] ?? $camp['cpc'] ?? 0),
        'balance' => (float)($u['balance'] ?? 0),
        'spent'   => (float)($camp['spent'] ?? 0),
        'views'   => (int)($camp['good_hits'] ?? 0),
        'imp'     => (int)($camp['impressions'] ?? 0),
        'hits'    => (int)($camp['clicks'] ?? 0),
    ];
}

// Sorted users for select
usort($users, fn($a,$b) => strcmp($a['username']??'',$b['username']??''));
?>

<div class="page-header">
    <div>
        <div class="page-title">Stats Injector</div>
        <div class="page-subtitle">Select a user → select their campaign → inject traffic data</div>
    </div>
    <a href="/admin/user_details.php" class="btn btn-secondary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        User Overview
    </a>
</div>

<?php if (!_hasHourlyStatsTable()): ?>
<div class="alert alert-warning">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div>
        <strong>Migration pending:</strong> Run the SQL in <code>MIGRATION.sql</code> to enable live hourly chart updates. Stats injected now will still update totals correctly, but won't appear on the dashboard's hourly graph until the migration is run.
    </div>
</div>
<?php endif; ?>

<div class="alert alert-info">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <span>Adding <strong>views</strong> deducts from user balance: <strong>Cost = Views × CPV</strong>. Impressions and hits do not affect balance. Stats land in the <strong>current CST hour</strong> on the user's live chart.</span>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start" class="inj-grid">
<style>@media(max-width:900px){.inj-grid{grid-template-columns:1fr!important}}</style>

<!-- ── Step 1 + 2: User → Campaign selector ── -->
<div style="display:flex;flex-direction:column;gap:16px">

    <!-- Step 1: Select User -->
    <div class="card">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
            <div style="width:26px;height:26px;background:var(--yellow);color:#000;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0">1</div>
            <div class="card-title" style="margin:0">Select User</div>
        </div>
        <select id="userSelect" class="form-control" onchange="onUserChange()">
            <option value="">— Choose user —</option>
            <?php foreach ($users as $u): ?>
            <option value="<?= htmlspecialchars($u['id']) ?>"
                    <?= $u['id']===$preUser ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['username']) ?>
                (<?= fmtMoney($u['balance']) ?>)
                <?= !empty($u['disabled']) ? ' [DISABLED]' : '' ?>
            </option>
            <?php endforeach; ?>
        </select>

        <!-- User info card -->
        <div id="userInfoCard" style="display:none;margin-top:14px;padding:14px;background:var(--bg-3);border:1px solid var(--border);border-radius:var(--r-sm)">
            <div style="display:flex;align-items:center;gap:12px">
                <div class="user-avatar" id="uAvatar" style="width:38px;height:38px;font-size:15px">?</div>
                <div>
                    <div style="font-weight:700;font-size:14px" id="uUsername">—</div>
                    <div style="font-size:12px;color:var(--text-2)" id="uEmail">—</div>
                </div>
                <div style="margin-left:auto;text-align:right">
                    <div style="font-size:11px;color:var(--text-3)">Balance</div>
                    <div style="font-size:17px;font-weight:800;color:var(--yellow)" id="uBalance">$0.00</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 2: Select Campaign -->
    <div class="card" id="step2Card" style="opacity:.4;pointer-events:none">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
            <div style="width:26px;height:26px;background:var(--bg-4);border:2px solid var(--border);color:var(--text-2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0" id="step2Num">2</div>
            <div class="card-title" style="margin:0">Select Campaign</div>
        </div>
        <select id="campSelect" class="form-control" onchange="onCampChange()">
            <option value="">— Choose campaign —</option>
        </select>

        <!-- Campaign info -->
        <div id="campInfoCard" style="display:none;margin-top:14px">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
                <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:7px;padding:10px;text-align:center">
                    <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;margin-bottom:3px">CPV</div>
                    <div style="font-size:15px;font-weight:700;color:var(--yellow)" id="ci_cpv">—</div>
                </div>
                <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:7px;padding:10px;text-align:center">
                    <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;margin-bottom:3px">Views</div>
                    <div style="font-size:15px;font-weight:700" id="ci_views">—</div>
                </div>
                <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:7px;padding:10px;text-align:center">
                    <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;margin-bottom:3px">Spent</div>
                    <div style="font-size:15px;font-weight:700;color:var(--orange)" id="ci_spent">—</div>
                </div>
            </div>
            <div style="margin-top:8px;padding:10px 12px;background:var(--bg-3);border:1px solid var(--border);border-radius:7px;font-size:12.5px;display:flex;justify-content:space-between">
                <span style="color:var(--text-2)">Max possible views (by balance)</span>
                <strong id="ci_maxviews" style="color:var(--green)">0</strong>
            </div>
        </div>
    </div>

</div>

<!-- ── Step 3: Inject form ── -->
<div class="card" id="step3Card" style="opacity:.4;pointer-events:none">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
        <div style="width:26px;height:26px;background:var(--bg-4);border:2px solid var(--border);color:var(--text-2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0" id="step3Num">3</div>
        <div class="card-title" style="margin:0">Inject Traffic</div>
    </div>

    <form method="POST" id="injectForm">
        <input type="hidden" name="campaign_id" id="formCampId">

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Impressions to Add</label>
                <input type="number" name="impressions" id="inp_imp" class="form-control" min="0" value="0">
                <div class="form-hint">Does not affect balance</div>
            </div>
            <div class="form-group">
                <label class="form-label">Views to Add</label>
                <input type="number" name="views" id="inp_views" class="form-control" min="0" value="0" oninput="updateCostPreview()">
                <div class="form-hint">Deducts CPV per view from balance</div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Hits to Add</label>
                <input type="number" name="hits" id="inp_hits" class="form-control" min="0" value="0">
                <div class="form-hint">Does not affect balance</div>
            </div>
            <div class="form-group">
                <label class="form-label">Date (for historical metrics)</label>
                <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
                <div class="form-hint">Live chart always uses current CST hour</div>
            </div>
        </div>

        <!-- Cost preview -->
        <div id="costPreview" style="display:none;margin-bottom:16px"></div>

        <button type="submit" class="btn btn-primary" id="submitBtn" disabled style="width:100%;justify-content:center;padding:12px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Inject Stats
        </button>
    </form>
</div>

</div>

<!-- ── All users campaign summary table ── -->
<div class="card" style="margin-top:24px;padding:0;overflow:hidden">
    <div style="padding:16px 22px;border-bottom:1px solid var(--border);font-size:15px;font-weight:600">
        All Active Campaigns
        <span style="font-size:12px;font-weight:400;color:var(--text-2);margin-left:8px">click row to inject</span>
    </div>
    <div class="table-wrap" style="border:none;border-radius:0">
        <table>
            <thead>
                <tr><th>User</th><th>Balance</th><th>Campaign</th><th>Status</th><th>CPV</th><th>Impressions</th><th>Views</th><th>Hits</th><th>Spent</th><th></th></tr>
            </thead>
            <tbody>
            <?php
            $activeCamps = array_filter($campaigns, fn($c) => in_array($c['status'],['active','pending','paused','review']));
            usort($activeCamps, fn($a,$b) => strcmp($userMap[$a['user_id']]['username']??'',$userMap[$b['user_id']]['username']??''));
            foreach ($activeCamps as $camp):
                $u   = $userMap[$camp['user_id']] ?? null;
                $cpv = $camp['cpv'] ?? $camp['cpc'] ?? 0;
                $sc  = ['active'=>'badge-success','pending'=>'badge-pending','paused'=>'badge-muted','review'=>'badge-info'][$camp['status']]??'badge-muted';
            ?>
            <tr style="cursor:pointer" onclick="quickSelect('<?= $camp['user_id'] ?>','<?= $camp['campaign_id'] ?>')"
                onmouseover="this.style.background='rgba(255,200,0,.04)'" onmouseout="this.style.background=''">
                <td>
                    <strong><?= htmlspecialchars($u['username']??'?') ?></strong>
                    <div style="font-size:10px;color:var(--text-3)"><?= $camp['user_id'] ?></div>
                </td>
                <td style="color:var(--yellow);font-weight:700"><?= fmtMoney($u['balance']??0) ?></td>
                <td>
                    <div><?= htmlspecialchars($camp['name']) ?></div>
                    <code style="font-size:10px;color:var(--text-3)"><?= $camp['campaign_id'] ?></code>
                </td>
                <td><span class="badge <?= $sc ?>"><?= $camp['status']==='review'?'Under Review':$camp['status'] ?></span></td>
                <td><?= fmtMoney($cpv) ?></td>
                <td><?= fmtNum($camp['impressions']??0) ?></td>
                <td><?= fmtNum($camp['good_hits']??0) ?></td>
                <td><?= fmtNum($camp['clicks']??0) ?></td>
                <td><?= fmtMoney($camp['spent']??0) ?></td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick="event.stopPropagation();quickSelect('<?= $camp['user_id'] ?>','<?= $camp['campaign_id'] ?>')">Select</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const USER_CAMPS  = <?= json_encode($userCampaignsJson) ?>;
const USER_MAP    = <?= json_encode(array_map(fn($u) => [
    'id'       => $u['id'],
    'username' => $u['username'],
    'email'    => $u['email'] ?? '',
    'balance'  => (float)($u['balance'] ?? 0),
], $users)) ?>;

let activeCpv     = 0;
let activeBalance = 0;

function onUserChange() {
    const uid  = document.getElementById('userSelect').value;
    const step2 = document.getElementById('step2Card');
    const camp  = document.getElementById('campSelect');

    if (!uid) {
        step2.style.opacity        = '.4';
        step2.style.pointerEvents  = 'none';
        document.getElementById('userInfoCard').style.display = 'none';
        lockStep3();
        return;
    }

    const uData = USER_MAP.find(u => u.id === uid);
    if (uData) {
        document.getElementById('uAvatar').textContent   = uData.username[0].toUpperCase();
        document.getElementById('uUsername').textContent = uData.username;
        document.getElementById('uEmail').textContent    = uData.email || '—';
        document.getElementById('uBalance').textContent  = '$' + uData.balance.toFixed(2);
        activeBalance = uData.balance;
        document.getElementById('userInfoCard').style.display = 'block';
    }

    camp.innerHTML = '<option value="">— Choose campaign —</option>';
    const camps = USER_CAMPS[uid] || [];
    camps.forEach(function(c) {
        const opt = document.createElement('option');
        opt.value       = c.id;
        opt.textContent = c.name + ' — ' + c.id + ' (' + c.status + ', $' + c.cpv.toFixed(4) + '/view)';
        opt.dataset.cpv    = c.cpv;
        opt.dataset.views  = c.views;
        opt.dataset.imp    = c.imp;
        opt.dataset.hits   = c.hits;
        opt.dataset.spent  = c.spent;
        camp.appendChild(opt);
    });

    step2.style.opacity       = '1';
    step2.style.pointerEvents = 'auto';
    document.getElementById('step2Num').style.background  = 'var(--yellow)';
    document.getElementById('step2Num').style.color       = '#000';
    document.getElementById('step2Num').style.border      = 'none';
    document.getElementById('campInfoCard').style.display = 'none';
    lockStep3();
}

function onCampChange() {
    const sel = document.getElementById('campSelect');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) { lockStep3(); return; }

    activeCpv     = parseFloat(opt.dataset.cpv)   || 0;
    activeBalance = parseFloat(document.getElementById('uBalance').textContent.replace('$','')) || 0;

    document.getElementById('ci_cpv').textContent   = '$' + activeCpv.toFixed(4);
    document.getElementById('ci_views').textContent = parseInt(opt.dataset.views).toLocaleString();
    document.getElementById('ci_spent').textContent = '$' + parseFloat(opt.dataset.spent).toFixed(2);

    const maxViews = activeCpv > 0 ? Math.floor(activeBalance / activeCpv) : '∞';
    document.getElementById('ci_maxviews').textContent = typeof maxViews === 'number' ? maxViews.toLocaleString() : maxViews;
    document.getElementById('campInfoCard').style.display = 'block';

    document.getElementById('formCampId').value = opt.value;

    const step3 = document.getElementById('step3Card');
    step3.style.opacity       = '1';
    step3.style.pointerEvents = 'auto';
    document.getElementById('step3Num').style.background = 'var(--yellow)';
    document.getElementById('step3Num').style.color      = '#000';
    document.getElementById('step3Num').style.border     = 'none';
    document.getElementById('submitBtn').disabled = false;

    ['inp_imp','inp_views','inp_hits'].forEach(function(id){ document.getElementById(id).value=0; });
    document.getElementById('costPreview').style.display = 'none';
}

function lockStep3() {
    const step3 = document.getElementById('step3Card');
    step3.style.opacity       = '.4';
    step3.style.pointerEvents = 'none';
    document.getElementById('step3Num').style.background = 'var(--bg-4)';
    document.getElementById('step3Num').style.color      = 'var(--text-2)';
    document.getElementById('step3Num').style.border     = '2px solid var(--border)';
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('formCampId').value = '';
}

function updateCostPreview() {
    const views = parseInt(document.getElementById('inp_views').value) || 0;
    const cp    = document.getElementById('costPreview');
    if (views <= 0 || activeCpv <= 0) { cp.style.display='none'; return; }
    const cost = views * activeCpv;
    cp.style.display = 'flex';
    if (cost > activeBalance) {
        cp.className = 'alert alert-danger';
        cp.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>&nbsp; INSUFFICIENT BALANCE — needs $'+cost.toFixed(2)+' but user has $'+activeBalance.toFixed(2);
    } else {
        cp.className = 'alert alert-warning';
        cp.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/></svg>&nbsp; Will deduct <strong style="margin:0 4px">$'+cost.toFixed(2)+'</strong> — remaining: <strong style="margin-left:4px">$'+(activeBalance-cost).toFixed(2)+'</strong>';
    }
}

function quickSelect(userId, campId) {
    const sel = document.getElementById('userSelect');
    for (let i=0;i<sel.options.length;i++) {
        if (sel.options[i].value === userId) { sel.selectedIndex=i; break; }
    }
    onUserChange();
    setTimeout(function() {
        const csel = document.getElementById('campSelect');
        for (let i=0;i<csel.options.length;i++) {
            if (csel.options[i].value === campId) { csel.selectedIndex=i; break; }
        }
        onCampChange();
        document.getElementById('step3Card').scrollIntoView({behavior:'smooth',block:'start'});
    }, 50);
}

<?php if ($preUser): ?>
(function() {
    const sel = document.getElementById('userSelect');
    for (let i=0;i<sel.options.length;i++) {
        if (sel.options[i].value === '<?= addslashes($preUser) ?>') { sel.selectedIndex=i; break; }
    }
    onUserChange();
    <?php if ($preCamp): ?>
    setTimeout(function() {
        const csel = document.getElementById('campSelect');
        for (let i=0;i<csel.options.length;i++) {
            if (csel.options[i].value === '<?= addslashes($preCamp) ?>') { csel.selectedIndex=i; break; }
        }
        onCampChange();
    }, 50);
    <?php endif; ?>
})();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
