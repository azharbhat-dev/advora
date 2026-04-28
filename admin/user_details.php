<?php
require_once __DIR__ . '/../includes/admin_header.php';

$users     = readJson(USERS_FILE);
$campaigns = readJson(CAMPAIGNS_FILE);
$topups    = readJson(TOPUPS_FILE);
$stats     = readJson(STATS_FILE);
$creatives = readJson(CREATIVES_FILE);

// Sort users by created_at desc
usort($users, fn($a,$b) => ($b['created_at']??0) <=> ($a['created_at']??0));

$creativeMap = [];
foreach ($creatives as $cr) $creativeMap[$cr['id']] = $cr;

$acctColors = [
    'rookie'       => ['#8888a8','rgba(136,136,168,0.1)','Rookie'],
    'professional' => ['#4d9eff','rgba(77,158,255,0.1)','Professional'],
    'expert'       => ['#ffc800','rgba(255,200,0,0.1)','Expert'],
];

// View single user?
$viewUserId = $_GET['user'] ?? null;
$viewUser   = null;
if ($viewUserId) {
    foreach ($users as $u) { if ($u['id'] === $viewUserId) { $viewUser = $u; break; } }
}
?>

<?php if ($viewUser): ?>
<?php
  // Single user detail view
  $uCampaigns = array_values(array_filter($campaigns, fn($c) => $c['user_id'] === $viewUser['id']));
  $uTopups    = array_values(array_filter($topups,    fn($t) => $t['user_id'] === $viewUser['id']));
  $uStats     = array_values(array_filter($stats,     fn($s) => $s['user_id'] === $viewUser['id']));

  $totalImp   = array_sum(array_column($uCampaigns, 'impressions'));
  $totalViews = array_sum(array_column($uCampaigns, 'good_hits'));
  $totalHits  = array_sum(array_column($uCampaigns, 'clicks'));
  $totalSpent = array_sum(array_column($uCampaigns, 'spent'));
  $totalDep   = array_sum(array_column(array_filter($uTopups, fn($t) => $t['status']==='approved'), 'amount_after_fee'));

  $at  = $viewUser['account_type'] ?? 'rookie';
  $atc = $acctColors[$at] ?? $acctColors['rookie'];
  $docVerified = $viewUser['doc_verified'] ?? false;
?>

<div class="page-header">
  <div>
    <div class="page-title" style="display:flex;align-items:center;gap:12px">
      <div class="user-avatar" style="width:40px;height:40px;font-size:16px"><?= strtoupper(substr($viewUser['username'],0,1)) ?></div>
      <?= htmlspecialchars($viewUser['username']) ?>
      <span style="background:<?= $atc[1] ?>;color:<?= $atc[0] ?>;border:1px solid <?= $atc[0] ?>33;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700"><?= $atc[2] ?></span>
    </div>
    <div class="page-subtitle">
      <code style="color:var(--yellow)"><?= $viewUser['id'] ?></code>
      &nbsp;&middot;&nbsp; Member since <?= date('M d, Y', $viewUser['created_at']) ?>
    </div>
  </div>
  <div style="display:flex;gap:8px">
    <a href="/admin/users.php" class="btn btn-secondary">Edit User</a>
    <a href="/admin/user_details.php" class="btn btn-secondary">&larr; All Users</a>
  </div>
</div>

<!-- Stats row -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
  <div class="stat-card">
    <div class="stat-label">Balance</div>
    <div class="stat-value" style="color:var(--yellow)"><?= fmtMoney($viewUser['balance']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total Deposited</div>
    <div class="stat-value"><?= fmtMoney($totalDep) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total Spent</div>
    <div class="stat-value"><?= fmtMoney($totalSpent) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Campaigns</div>
    <div class="stat-value"><?= count($uCampaigns) ?> / <?= getUserCampaignLimit($viewUser['id']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Impressions</div>
    <div class="stat-value"><?= fmtNum($totalImp) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Views</div>
    <div class="stat-value"><?= fmtNum($totalViews) ?></div>
  </div>
</div>

<!-- Personal + Business Info -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="ud-grid">
<style>@media(max-width:900px){.ud-grid{grid-template-columns:1fr!important}}</style>

<div class="card">
  <div class="card-title" style="margin-bottom:16px">Personal Information</div>
  <div style="font-size:13px">
    <?php
    $fields = [
      ['Full Name',   $viewUser['full_name']   ?? '—'],
      ['Email',       $viewUser['email']        ?? '—'],
      ['Phone',       $viewUser['phone']        ?? '—'],
      ['Address',     $viewUser['address']      ?? '—'],
      ['Telegram',    !empty($viewUser['telegram_id']) ? '@'.ltrim($viewUser['telegram_id'],'@') : '—'],
    ];
    foreach ($fields as [$k,$v]):
    ?>
    <div class="detail-row">
      <span class="dk"><?= $k ?></span>
      <strong class="dv" style="text-align:right;max-width:60%;word-break:break-word"><?= htmlspecialchars($v) ?></strong>
    </div>
    <?php endforeach; ?>
    <div class="detail-row">
      <span class="dk">Status</span>
      <span class="badge <?= empty($viewUser['disabled']) ? 'badge-success' : 'badge-danger' ?>"><?= empty($viewUser['disabled']) ? 'Active' : 'Disabled' ?></span>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-title" style="margin-bottom:16px">Business Information</div>
  <div style="font-size:13px">
    <?php
    $bfields = [
      ['Business Name',    $viewUser['business_name']    ?? '—'],
      ['Business Address', $viewUser['business_address'] ?? '—'],
    ];
    foreach ($bfields as [$k,$v]):
    ?>
    <div class="detail-row">
      <span class="dk"><?= $k ?></span>
      <strong class="dv" style="text-align:right;max-width:60%;word-break:break-word"><?= htmlspecialchars($v) ?></strong>
    </div>
    <?php endforeach; ?>
    <div class="detail-row">
      <span class="dk">Document Status</span>
      <div style="display:flex;align-items:center;gap:8px">
        <?php if ($docVerified): ?>
        <span class="badge badge-success">Verified</span>
        <form method="POST" action="/admin/users.php" style="display:inline">
          <input type="hidden" name="action" value="set_doc_verified">
          <input type="hidden" name="user_id" value="<?= $viewUser['id'] ?>">
          <input type="hidden" name="doc_verified" value="0">
          <button type="submit" class="btn btn-danger btn-sm">Revoke</button>
        </form>
        <?php else: ?>
        <span class="badge badge-pending">Unverified</span>
        <form method="POST" action="/admin/users.php" style="display:inline">
          <input type="hidden" name="action" value="set_doc_verified">
          <input type="hidden" name="user_id" value="<?= $viewUser['id'] ?>">
          <input type="hidden" name="doc_verified" value="1">
          <button type="submit" class="btn btn-success btn-sm">Verify</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <div class="detail-row">
      <span class="dk">Account Type</span>
      <span style="color:<?= $atc[0] ?>;font-weight:700"><?= $atc[2] ?></span>
    </div>
    <div class="detail-row">
      <span class="dk">Campaign Limit</span>
      <span>
        <strong><?= getUserCampaignLimit($viewUser['id']) ?></strong>
        <a href="/admin/campaign_capacity.php?q=<?= urlencode($viewUser['username']) ?>" class="btn btn-secondary btn-sm" style="margin-left:6px;padding:3px 8px;font-size:11px">Edit</a>
      </span>
    </div>
  </div>
</div>
</div>

<!-- All Campaigns -->
<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:16px 22px;border-bottom:1px solid var(--border);font-size:15px;font-weight:600">
    Campaigns (<?= count($uCampaigns) ?>)
  </div>
  <?php if (empty($uCampaigns)): ?>
  <div class="empty-state"><h3>No campaigns</h3></div>
  <?php else: ?>
  <div class="table-wrap" style="border:none;border-radius:0">
    <table>
      <thead>
        <tr>
          <th>Campaign ID</th><th>Name</th><th>Creative</th><th>Status</th><th>CPV</th>
          <th>Daily Budget</th><th>Spent</th><th>Impressions</th>
          <th>Views</th><th>Hits</th><th>CTR</th><th>Countries</th><th>Created</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (array_reverse($uCampaigns) as $camp):
        $sc  = ['active'=>'badge-success','pending'=>'badge-pending','paused'=>'badge-muted','review'=>'badge-info','rejected'=>'badge-danger'][$camp['status']]??'badge-muted';
        $sl  = $camp['status']==='review' ? 'Under Review' : $camp['status'];
        $ctr = ($camp['impressions']??0)>0 ? round(($camp['good_hits']??0)/($camp['impressions']??1)*100,2) : 0;
        $cr  = !empty($camp['creative_id']) && isset($creativeMap[$camp['creative_id']]) ? $creativeMap[$camp['creative_id']] : null;
      ?>
      <tr>
        <td><code style="color:var(--yellow);font-size:11px"><?= $camp['campaign_id'] ?></code></td>
        <td><strong><?= htmlspecialchars($camp['name']) ?></strong></td>
        <td>
          <?php if ($cr): ?>
          <div style="display:flex;align-items:center;gap:6px">
            <span style="font-size:12px;font-weight:600;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($cr['name']) ?>"><?= htmlspecialchars($cr['name']) ?></span>
            <button type="button" class="btn btn-secondary btn-sm" style="padding:3px 7px"
              onclick="previewCreative('<?= htmlspecialchars($cr['id']) ?>', '<?= htmlspecialchars(addslashes($cr['name'])) ?>')">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              View
            </button>
          </div>
          <?php else: ?>
          <span style="color:var(--text-3);font-size:11px">— None —</span>
          <?php endif; ?>
        </td>
        <td><span class="badge <?= $sc ?>"><?= $sl ?></span></td>
        <td><?= fmtMoney($camp['cpv']??$camp['cpc']??0) ?></td>
        <td><?= fmtMoney($camp['daily_budget']??$camp['budget']??0) ?></td>
        <td style="color:var(--orange)"><?= fmtMoney($camp['spent']??0) ?></td>
        <td><?= fmtNum($camp['impressions']??0) ?></td>
        <td><?= fmtNum($camp['good_hits']??0) ?></td>
        <td><?= fmtNum($camp['clicks']??0) ?></td>
        <td><?= $ctr ?>%</td>
        <td>
          <?php foreach ($camp['countries']??[] as $cc): ?>
          <span class="badge badge-yellow" style="font-size:10px;padding:2px 6px"><?= $cc ?></span>
          <?php endforeach; ?>
        </td>
        <td style="font-size:11px;color:var(--text-2)"><?= date('M d, Y', $camp['created_at']) ?></td>
        <td>
          <a href="/admin/stats_injector.php?user=<?= $viewUser['id'] ?>&camp=<?= urlencode($camp['campaign_id']) ?>" class="btn btn-secondary btn-sm">Inject</a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Topup History -->
<div class="card" style="padding:0;overflow:hidden;margin-top:20px">
  <div style="padding:16px 22px;border-bottom:1px solid var(--border);font-size:15px;font-weight:600">
    Deposit History (<?= count($uTopups) ?>)
  </div>
  <?php if (empty($uTopups)): ?>
  <div class="empty-state"><h3>No deposits</h3></div>
  <?php else: ?>
  <div class="table-wrap" style="border:none;border-radius:0">
    <table>
      <thead><tr><th>ID</th><th>Network</th><th>Amount</th><th>Fee</th><th>Credited</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach (array_reverse($uTopups) as $t):
        $ts = ['pending'=>'badge-pending','approved'=>'badge-success','rejected'=>'badge-danger'][$t['status']]??'badge-muted';
      ?>
      <tr>
        <td><code style="font-size:11px;color:var(--yellow)"><?= $t['id'] ?></code></td>
        <td><span class="badge badge-yellow"><?= htmlspecialchars($t['network_label']??$t['network']) ?></span></td>
        <td><?= fmtMoney($t['amount']) ?></td>
        <td style="color:var(--orange)"><?= $t['fee']>0?'-'.fmtMoney($t['fee']):'—' ?></td>
        <td style="color:var(--green)"><?= fmtMoney($t['amount_after_fee']??$t['amount']) ?></td>
        <td><span class="badge <?= $ts ?>"><?= $t['status'] ?></span></td>
        <td style="font-size:12px;color:var(--text-2)"><?= date('M d, Y H:i', $t['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Creative Preview Modal -->
<div class="modal" id="previewModal">
  <div class="modal-box" style="max-width:900px;width:95vw">
    <div class="modal-header">
      <div class="modal-title" id="previewTitle">Creative Preview</div>
      <div style="display:flex;gap:8px;align-items:center">
        <a id="previewOpenBtn" href="#" target="_blank" class="btn btn-secondary btn-sm">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          Open in Tab
        </a>
        <div class="modal-close" onclick="closeModal('previewModal')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </div>
      </div>
    </div>
    <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--r-sm);overflow:hidden">
      <div style="background:var(--bg-3);padding:8px 12px;display:flex;gap:6px;align-items:center;border-bottom:1px solid var(--border)">
        <div style="width:10px;height:10px;border-radius:50%;background:var(--red);opacity:.7"></div>
        <div style="width:10px;height:10px;border-radius:50%;background:var(--orange);opacity:.7"></div>
        <div style="width:10px;height:10px;border-radius:50%;background:var(--green);opacity:.7"></div>
        <div style="flex:1;background:var(--bg-4);border-radius:4px;padding:3px 10px;font-size:11px;color:var(--text-3);margin-left:6px" id="previewUrlBar">Preview</div>
      </div>
      <iframe id="previewFrame" style="width:100%;height:500px;border:none;background:#fff" sandbox="allow-scripts allow-same-origin"></iframe>
    </div>
  </div>
</div>

<script>
function previewCreative(id, name) {
  var url = '/admin/creatives.php?preview=' + encodeURIComponent(id);
  document.getElementById('previewTitle').textContent  = name + ' — Creative Preview';
  document.getElementById('previewFrame').src          = url;
  document.getElementById('previewOpenBtn').href       = url;
  document.getElementById('previewUrlBar').textContent = name + '.html';
  openModal('previewModal');
}
</script>

<?php else: ?>
<!-- ── USER LIST VIEW ── -->

<div class="page-header">
  <div>
    <div class="page-title">User Overview</div>
    <div class="page-subtitle">Detailed view of all users and their campaign activity</div>
  </div>
  <a href="/admin/users.php" class="btn btn-secondary">Manage Users</a>
</div>

<!-- Search -->
<div class="card" style="padding:14px 18px;margin-bottom:18px">
  <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <input type="text" id="userSearch" class="form-control" style="max-width:280px"
      placeholder="Search by username, email, name..." oninput="filterUsers()">
    <select id="userStatusFilter" class="form-control" style="max-width:160px" onchange="filterUsers()">
      <option value="">All Status</option>
      <option value="active">Active</option>
      <option value="disabled">Disabled</option>
    </select>
    <select id="userTypeFilter" class="form-control" style="max-width:160px" onchange="filterUsers()">
      <option value="">All Types</option>
      <option value="expert">Expert</option>
      <option value="professional">Professional</option>
      <option value="rookie">Rookie</option>
    </select>
    <span id="userCount" style="margin-left:auto;font-size:12px;color:var(--text-2)"><?= count($users) ?> users</span>
  </div>
</div>

<!-- User cards grid -->
<div id="usersGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px">

<?php foreach ($users as $u):
  $uCamps   = array_values(array_filter($campaigns, fn($c) => $c['user_id'] === $u['id']));
  $uTopupA  = array_filter($topups, fn($t) => $t['user_id']===$u['id'] && $t['status']==='approved');
  $totalDep = array_sum(array_column(array_values($uTopupA), 'amount_after_fee'));
  $totalSp  = array_sum(array_column($uCamps, 'spent'));
  $activeCm = count(array_filter($uCamps, fn($c) => $c['status']==='active'));
  $at       = $u['account_type'] ?? 'rookie';
  $atc      = $acctColors[$at] ?? $acctColors['rookie'];
  $isDisabled = !empty($u['disabled']);
  $docV     = !empty($u['doc_verified']);
  $statusCounts = ['active'=>0,'review'=>0,'paused'=>0,'pending'=>0,'rejected'=>0];
  foreach ($uCamps as $camp) { $s = $camp['status']??'pending'; if (isset($statusCounts[$s])) $statusCounts[$s]++; }
  $userLim  = getUserCampaignLimit($u['id']);
?>
<div class="user-card" data-username="<?= strtolower(htmlspecialchars($u['username'])) ?>"
     data-name="<?= strtolower(htmlspecialchars($u['full_name']??'')) ?>"
     data-email="<?= strtolower(htmlspecialchars($u['email']??'')) ?>"
     data-status="<?= $isDisabled?'disabled':'active' ?>"
     data-type="<?= $at ?>">
  <div style="padding:18px">

    <!-- User header row -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
      <div class="user-avatar" style="width:42px;height:42px;font-size:16px;flex-shrink:0;<?= $isDisabled?'opacity:.5':'' ?>"><?= strtoupper(substr($u['username'],0,1)) ?></div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap">
          <strong style="font-size:15px"><?= htmlspecialchars($u['username']) ?></strong>
          <span style="background:<?= $atc[1] ?>;color:<?= $atc[0] ?>;border:1px solid <?= $atc[0] ?>33;padding:2px 8px;border-radius:12px;font-size:10.5px;font-weight:700"><?= $atc[2] ?></span>
          <?php if ($isDisabled): ?><span class="badge badge-danger" style="font-size:10px">Disabled</span><?php endif; ?>
          <?php if ($docV): ?><span class="badge badge-success" style="font-size:10px">Doc ✓</span><?php endif; ?>
        </div>
        <div style="font-size:11.5px;color:var(--text-2);margin-top:2px">
          <?= htmlspecialchars($u['full_name']??'') ?>
          <?php if (!empty($u['email'])): ?>&nbsp;·&nbsp;<?= htmlspecialchars($u['email']) ?><?php endif; ?>
        </div>
        <?php if (!empty($u['telegram_id'])): ?>
        <div style="font-size:11px;color:var(--blue);margin-top:1px">@<?= htmlspecialchars(ltrim($u['telegram_id'],'@')) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Balance row -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px">
      <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:8px;padding:10px;text-align:center">
        <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px">Balance</div>
        <div style="font-size:15px;font-weight:800;color:var(--yellow)"><?= fmtMoney($u['balance']) ?></div>
      </div>
      <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:8px;padding:10px;text-align:center">
        <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px">Deposited</div>
        <div style="font-size:15px;font-weight:800"><?= fmtMoney($totalDep) ?></div>
      </div>
      <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:8px;padding:10px;text-align:center">
        <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px">Spent</div>
        <div style="font-size:15px;font-weight:800;color:var(--orange)"><?= fmtMoney($totalSp) ?></div>
      </div>
    </div>

    <!-- Campaign status breakdown -->
    <div style="margin-bottom:14px">
      <div style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;font-weight:600">
        Campaigns (<?= count($uCamps) ?> / <?= $userLim ?>)
      </div>
      <?php if (empty($uCamps)): ?>
      <div style="font-size:12px;color:var(--text-3)">No campaigns yet</div>
      <?php else: ?>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php if ($statusCounts['active']>0): ?><span class="badge badge-success"><?= $statusCounts['active'] ?> active</span><?php endif; ?>
        <?php if ($statusCounts['review']>0): ?><span class="badge badge-info"><?= $statusCounts['review'] ?> review</span><?php endif; ?>
        <?php if ($statusCounts['paused']>0): ?><span class="badge badge-muted"><?= $statusCounts['paused'] ?> paused</span><?php endif; ?>
        <?php if ($statusCounts['pending']>0): ?><span class="badge badge-pending"><?= $statusCounts['pending'] ?> pending</span><?php endif; ?>
        <?php if ($statusCounts['rejected']>0): ?><span class="badge badge-danger"><?= $statusCounts['rejected'] ?> rejected</span><?php endif; ?>
      </div>
      <!-- Latest campaign preview -->
      <?php $latest = end($uCamps); if ($latest): ?>
      <div style="margin-top:8px;padding:8px 10px;background:var(--bg-3);border-radius:6px;font-size:12px">
        <span style="color:var(--text-2)">Latest: </span>
        <strong><?= htmlspecialchars($latest['name']) ?></strong>
        <code style="color:var(--yellow);font-size:10px;margin-left:6px"><?= $latest['campaign_id'] ?></code>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Actions -->
    <div style="display:flex;gap:7px">
      <a href="/admin/user_details.php?user=<?= $u['id'] ?>" class="btn btn-primary btn-sm" style="flex:1;justify-content:center">
        View Details
      </a>
      <a href="/admin/stats_injector.php?user=<?= $u['id'] ?>" class="btn btn-secondary btn-sm">Inject Stats</a>
      <a href="/admin/users.php" class="btn btn-secondary btn-sm">Edit</a>
    </div>

  </div>
</div>
<?php endforeach; ?>
</div>

<script>
function filterUsers() {
  var q    = document.getElementById('userSearch').value.toLowerCase();
  var st   = document.getElementById('userStatusFilter').value;
  var tp   = document.getElementById('userTypeFilter').value;
  var cards= document.querySelectorAll('.user-card');
  var vis  = 0;
  cards.forEach(function(card) {
    var match = true;
    if (q && card.dataset.username.indexOf(q)===-1 && card.dataset.name.indexOf(q)===-1 && card.dataset.email.indexOf(q)===-1) match=false;
    if (st && card.dataset.status !== st) match=false;
    if (tp && card.dataset.type   !== tp) match=false;
    card.style.display = match ? '' : 'none';
    if (match) vis++;
  });
  document.getElementById('userCount').textContent = vis + ' users';
}
</script>

<?php endif; ?>

<style>
.user-card{background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r);transition:all .2s;overflow:hidden}
.user-card:hover{border-color:rgba(255,200,0,.18);transform:translateY(-2px);box-shadow:0 8px 32px rgba(0,0,0,.3)}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
