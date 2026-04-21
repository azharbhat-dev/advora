<?php
require_once __DIR__ . '/../includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid    = $_POST['campaign_id'] ?? '';
    $addImp = (int)($_POST['impressions'] ?? 0);
    $addHits = (int)($_POST['hits'] ?? 0);       // UI: hits → stored as clicks field
    $addGh  = (int)($_POST['good_hits'] ?? 0);
    $addViews = (int)($_POST['views'] ?? 0);      // views = good_hits alias
    $date   = $_POST['date'] ?? date('Y-m-d');

    // views input takes priority over good_hits if provided
    $addViews = $addViews ?: $addGh;

    $campaigns = readJson(CAMPAIGNS_FILE);
    $found     = null;
    $foundIdx  = null;
    foreach ($campaigns as $idx => $c) {
        if ($c['campaign_id'] === $cid) { $found = $c; $foundIdx = $idx; break; }
    }

    if (!$found) {
        flash('Campaign not found', 'error');
    } else {
        $users   = readJson(USERS_FILE);
        $userIdx = null;
        foreach ($users as $idx => $u) {
            if ($u['id'] === $found['user_id']) { $userIdx = $idx; break; }
        }

        if ($userIdx === null) {
            flash('User not found', 'error');
        } else {
            $cpv        = (float)($found['cpv'] ?? $found['cpc'] ?? 0);
            $cost       = $addViews * $cpv;   // cost based on views (CPV model)
            $currentBal = (float)$users[$userIdx]['balance'];

            if ($cost > $currentBal) {
                flash('User has insufficient balance ($' . number_format($currentBal,4) . ') for ' . $addViews . ' views at ' . fmtMoneyPrecise($cpv) . ' CPV (needs $' . number_format($cost,4) . ')', 'error');
            } else {
                $campaigns[$foundIdx]['impressions'] = ($campaigns[$foundIdx]['impressions'] ?? 0) + $addImp;
                $campaigns[$foundIdx]['clicks']      = ($campaigns[$foundIdx]['clicks']      ?? 0) + $addHits;   // hits stored in clicks
                $campaigns[$foundIdx]['good_hits']   = ($campaigns[$foundIdx]['good_hits']   ?? 0) + $addViews;
                $campaigns[$foundIdx]['views_count'] = ($campaigns[$foundIdx]['views_count'] ?? 0) + $addViews;
                $campaigns[$foundIdx]['spent']       = ($campaigns[$foundIdx]['spent']       ?? 0) + $cost;
                writeJson(CAMPAIGNS_FILE, $campaigns);

                $users[$userIdx]['balance'] = $currentBal - $cost;
                writeJson(USERS_FILE, $users);

                $stats     = readJson(STATS_FILE);
                $statFound = false;
                foreach ($stats as &$s) {
                    if ($s['campaign_id'] === $cid && $s['date'] === $date) {
                        $s['impressions'] = ($s['impressions'] ?? 0) + $addImp;
                        $s['clicks']      = ($s['clicks']      ?? 0) + $addHits;
                        $s['good_hits']   = ($s['good_hits']   ?? 0) + $addViews;
                        $s['spent']       = ($s['spent']       ?? 0) + $cost;
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
                        'spent'       => $cost
                    ];
                }
                writeJson(STATS_FILE, $stats);

                flash('Stats injected. Balance deducted: $' . number_format($cost,4) . ' (' . $addImp . ' impressions, ' . $addViews . ' views, ' . $addHits . ' hits)', 'success');
            }
        }
    }
    safeRedirect('/admin/stats_injector.php');
}

$campaigns     = readJson(CAMPAIGNS_FILE);
$users         = readJson(USERS_FILE);
$userMap       = [];
foreach ($users as $u) $userMap[$u['id']] = $u;

$activeCampaigns = array_filter($campaigns, fn($c) => in_array($c['status'], ['active','pending','paused','review']));
?>

<div class="page-header">
    <div>
        <div class="page-title">Stats Injector</div>
        <div class="page-subtitle">Add impressions, views &amp; hits &mdash; balance auto-deducts based on CPV</div>
    </div>
</div>

<div class="alert alert-info">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <span>When you add <strong>views</strong>, the user&apos;s balance is automatically deducted based on the campaign&apos;s CPV rate. Formula: <strong>Cost = Views &times; CPV</strong></span>
</div>

<div class="card">
    <div class="card-title" style="margin-bottom:20px">Inject Traffic Data</div>
    <form method="POST">
        <div class="form-group">
            <label class="form-label">Select Campaign *</label>
            <select name="campaign_id" class="form-control" required id="campSelect" onchange="updateCampInfo()">
                <option value="">&mdash; Choose campaign &mdash;</option>
                <?php foreach ($activeCampaigns as $c):
                    $u = $userMap[$c['user_id']] ?? null;
                    $cpvRate = $c['cpv'] ?? $c['cpc'] ?? 0;
                ?>
                <option value="<?= htmlspecialchars($c['campaign_id']) ?>"
                        data-cpv="<?= $cpvRate ?>"
                        data-balance="<?= $u['balance'] ?? 0 ?>"
                        data-user="<?= htmlspecialchars($u['username'] ?? 'Unknown') ?>"
                        data-status="<?= $c['status'] ?>">
                    <?= htmlspecialchars($c['name']) ?> &mdash; <?= $c['campaign_id'] ?> (<?= htmlspecialchars($u['username'] ?? '?') ?>, <?= fmtMoneyPrecise($cpvRate) ?>/view)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="campInfo" style="display:none;margin-bottom:20px"></div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Impressions to Add</label>
                <input type="number" name="impressions" class="form-control" min="0" value="0">
            </div>
            <div class="form-group">
                <label class="form-label">Views to Add *</label>
                <input type="number" name="views" id="viewInput" class="form-control" min="0" value="0" oninput="updateCost()">
                <div class="form-hint">Each view deducts CPV from user balance</div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Hits to Add</label>
                <input type="number" name="hits" class="form-control" min="0" value="0">
                <div class="form-hint">Hits do not deduct balance</div>
            </div>
            <div class="form-group">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <div id="costPreview" class="alert alert-warning" style="display:none"></div>

        <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Inject Stats &amp; Deduct Balance
        </button>
    </form>
</div>

<div class="card">
    <div class="card-title" style="margin-bottom:20px">Campaigns Summary</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>User</th>
                    <th>Balance</th>
                    <th>CPV</th>
                    <th>Impressions</th>
                    <th>Views</th>
                    <th>Hits</th>
                    <th>Spent</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse(array_values($activeCampaigns)) as $c):
                    $u    = $userMap[$c['user_id']] ?? null;
                    $sCls = ['active'=>'badge-success','pending'=>'badge-pending','paused'=>'badge-muted','review'=>'badge-info'][$c['status']] ?? 'badge-muted';
                    $cpvRate = $c['cpv'] ?? $c['cpc'] ?? 0;
                    $statusLabel = $c['status'] === 'review' ? 'Under Review' : $c['status'];
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($c['name']) ?></strong>
                        <div style="font-size:11px;color:var(--text-3)"><?= $c['campaign_id'] ?></div>
                    </td>
                    <td><?= htmlspecialchars($u['username'] ?? 'Unknown') ?></td>
                    <td><strong style="color:var(--yellow)"><?= fmtMoney($u['balance'] ?? 0) ?></strong></td>
                    <td><?= fmtMoneyPrecise($cpvRate) ?></td>
                    <td><?= fmtNum($c['impressions'] ?? 0) ?></td>
                    <td><?= fmtNum($c['good_hits']   ?? 0) ?></td>
                    <td><?= fmtNum($c['clicks']      ?? 0) ?></td>
                    <td><?= fmtMoney($c['spent']     ?? 0) ?></td>
                    <td><span class="badge <?= $sCls ?>"><?= $statusLabel ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function updateCampInfo() {
    const sel = document.getElementById('campSelect');
    const opt = sel.options[sel.selectedIndex];
    const info = document.getElementById('campInfo');
    if (!opt.value) { info.style.display='none'; return; }

    const cpv  = parseFloat(opt.dataset.cpv);
    const bal  = parseFloat(opt.dataset.balance);
    const user = opt.dataset.user;
    const status = opt.dataset.status;
    const maxViews = Math.floor(bal / cpv);

    info.style.display = 'block';
    info.className = 'alert ' + (bal > 0 ? 'alert-info' : 'alert-danger');
    info.innerHTML = `
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;width:100%">
            <div><div style="font-size:11px;color:var(--text-3)">User</div><strong>${user}</strong></div>
            <div><div style="font-size:11px;color:var(--text-3)">Balance</div><strong>$${bal.toFixed(4)}</strong></div>
            <div><div style="font-size:11px;color:var(--text-3)">CPV</div><strong>$${cpv.toFixed(4)}</strong></div>
            <div><div style="font-size:11px;color:var(--text-3)">Max Views Possible</div><strong>${maxViews.toLocaleString()}</strong></div>
            <div><div style="font-size:11px;color:var(--text-3)">Status</div><strong style="text-transform:capitalize">${status}</strong></div>
        </div>`;
    updateCost();
}

function updateCost() {
    const sel  = document.getElementById('campSelect');
    const opt  = sel.options[sel.selectedIndex];
    if (!opt.value) return;

    const cpv    = parseFloat(opt.dataset.cpv);
    const bal    = parseFloat(opt.dataset.balance);
    const views  = parseInt(document.getElementById('viewInput').value) || 0;
    const cost   = views * cpv;
    const preview = document.getElementById('costPreview');

    if (views > 0) {
        preview.style.display = 'flex';
        if (cost > bal) {
            preview.className = 'alert alert-danger';
            preview.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> INSUFFICIENT BALANCE. User has $' + bal.toFixed(4) + ' but needs $' + cost.toFixed(4) + ' for ' + views + ' views.';
        } else {
            preview.className = 'alert alert-warning';
            preview.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/></svg> This will deduct <strong style="margin:0 4px">$' + cost.toFixed(4) + '</strong> from user balance. Remaining: <strong style="margin-left:4px">$' + (bal - cost).toFixed(4) + '</strong>';
        }
    } else {
        preview.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>