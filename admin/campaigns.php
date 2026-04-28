<?php
require_once __DIR__ . '/../includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $cid       = $_POST['campaign_id'] ?? '';
    $campaigns = readJson(CAMPAIGNS_FILE);
    foreach ($campaigns as &$c) {
        if ($c['campaign_id'] === $cid) {
            if ($action === 'approve') {
                $c['status'] = 'active';
                addNotification($c['user_id'], 'campaign_approved',
                    'Campaign Approved',
                    'Your campaign "' . $c['name'] . '" has been approved and is now live.'
                );
                flash('Campaign approved', 'success');
            } elseif ($action === 'reject') {
                $c['status']        = 'rejected';
                $c['reject_reason'] = trim($_POST['reason'] ?? '');
                addNotification($c['user_id'], 'campaign_rejected',
                    'Campaign Declined',
                    'Your campaign "' . $c['name'] . '" was not approved.' . (!empty($c['reject_reason']) ? ' Reason: ' . $c['reject_reason'] : '')
                );
                flash('Campaign rejected', 'success');
            } elseif ($action === 'set_review') {
                $c['status'] = 'review';
                addNotification($c['user_id'], 'manual',
                    'Campaign Under Review',
                    'Your campaign "' . $c['name'] . '" has been put back under review by the network.'
                );
                flash('Campaign set to Under Review', 'success');
            } elseif ($action === 'pause') {
                $c['status'] = 'paused';
                flash('Campaign paused', 'success');
            } elseif ($action === 'resume') {
                $c['status'] = 'active';
                flash('Campaign resumed', 'success');
            } elseif ($action === 'delete') {
                $campaigns = array_values(array_filter($campaigns, fn($x) => $x['campaign_id'] !== $cid));
                writeJson(CAMPAIGNS_FILE, $campaigns);
                flash('Campaign deleted', 'success');
                safeRedirect('/admin/campaigns.php');
            }
            break;
        }
    }
    writeJson(CAMPAIGNS_FILE, $campaigns);
    safeRedirect('/admin/campaigns.php');
}

$campaigns = readJson(CAMPAIGNS_FILE);
$users     = readJson(USERS_FILE);
$creatives = readJson(CREATIVES_FILE);
$userMap   = [];
foreach ($users as $u) $userMap[$u['id']] = $u['username'];
$creativeMap = [];
foreach ($creatives as $cr) $creativeMap[$cr['id']] = $cr;

$filter = $_GET['filter'] ?? 'all';
if ($filter !== 'all') {
    $campaigns = array_filter($campaigns, fn($c) => $c['status'] === $filter);
}
$campaigns = array_reverse($campaigns);
?>

<div class="page-header">
    <div>
        <div class="page-title">Campaigns</div>
        <div class="page-subtitle">Approve, reject, or manage all campaigns</div>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <select class="form-control" onchange="location.href='?filter='+this.value">
            <option value="all"      <?= $filter==='all'      ?'selected':'' ?>>All Statuses</option>
            <option value="pending"  <?= $filter==='pending'  ?'selected':'' ?>>Pending</option>
            <option value="review"   <?= $filter==='review'   ?'selected':'' ?>>Under Review</option>
            <option value="active"   <?= $filter==='active'   ?'selected':'' ?>>Active</option>
            <option value="paused"   <?= $filter==='paused'   ?'selected':'' ?>>Paused</option>
            <option value="rejected" <?= $filter==='rejected' ?'selected':'' ?>>Rejected</option>
        </select>
    </div>

    <?php if (empty($campaigns)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 11l18-5v12L3 14v-3z"/></svg>
        <h3>No campaigns found</h3>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Name</th>
                    <th>Creative</th>
                    <th>Status</th>
                    <th>CPV</th>
                    <th>Daily Budget</th>
                    <th>Spent</th>
                    <th>Impressions</th>
                    <th>Views</th>
                    <th>Hits</th>
                    <th>Sources</th>
                    <th>Delivery</th>
                    <th>Countries / States</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $c):
                    $sCls = ['active'=>'badge-success','pending'=>'badge-pending','paused'=>'badge-muted','review'=>'badge-info','rejected'=>'badge-danger'][$c['status']] ?? 'badge-muted';
                    $statusLabel = $c['status'] === 'review' ? 'Under Review' : $c['status'];
                    $cpvRate     = $c['cpv'] ?? $c['cpc'] ?? 0;
                    $dailyBudget = $c['daily_budget'] ?? $c['budget'] ?? 0;
                    $sources     = $c['sources'] ?? [];
                    $delivery    = $c['delivery'] ?? 'even';
                    $cr          = !empty($c['creative_id']) && isset($creativeMap[$c['creative_id']]) ? $creativeMap[$c['creative_id']] : null;
                ?>
                <tr>
                    <td><code style="color:var(--yellow);font-size:11px"><?= $c['campaign_id'] ?></code></td>
                    <td><?= htmlspecialchars($userMap[$c['user_id']] ?? 'Unknown') ?></td>
                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                    <td>
                        <?php if ($cr): ?>
                        <div style="display:flex;align-items:center;gap:6px">
                            <div style="font-size:12px;font-weight:600;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($cr['name']) ?>"><?= htmlspecialchars($cr['name']) ?></div>
                            <button type="button" class="btn btn-secondary btn-sm" style="padding:3px 8px"
                                onclick="previewCreative('<?= htmlspecialchars($cr['id']) ?>', '<?= htmlspecialchars(addslashes($cr['name'])) ?>')">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                View
                            </button>
                        </div>
                        <div style="font-size:10px;color:var(--text-3);font-family:'Courier New',monospace;margin-top:2px"><?= htmlspecialchars($cr['id']) ?></div>
                        <?php else: ?>
                        <span style="color:var(--text-3);font-size:12px">— None —</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $sCls ?>"
                          data-live-badge="camp:<?= $c['campaign_id'] ?>:status"
                          data-current-status="<?= $c['status'] ?>"><?= $statusLabel ?></span>
                    </td>
                    <td><?= fmtMoney($cpvRate) ?></td>
                    <td><?= fmtMoney($dailyBudget) ?></td>
                    <td data-live-money="camp:<?= $c['campaign_id'] ?>:spent"><?= fmtMoney($c['spent']??0) ?></td>
                    <td data-live="camp:<?= $c['campaign_id'] ?>:impressions"><?= fmtNum($c['impressions']??0) ?></td>
                    <td data-live="camp:<?= $c['campaign_id'] ?>:views"><?= fmtNum($c['good_hits']??0) ?></td>
                    <td data-live="camp:<?= $c['campaign_id'] ?>:hits"><?= fmtNum($c['clicks']??0) ?></td>
                    <td>
                        <?php if(!empty($sources)): ?>
                        <div style="display:flex;gap:3px;flex-wrap:wrap">
                            <?php foreach($sources as $src): ?>
                            <span class="badge badge-info" style="font-size:10px;padding:2px 6px"><?= ucfirst(htmlspecialchars($src)) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?><span style="color:var(--text-3);font-size:12px">—</span><?php endif; ?>
                    </td>
                    <td style="font-size:12px;text-transform:capitalize"><?= htmlspecialchars($delivery) ?></td>
                    <td>
                        <div style="display:flex;flex-wrap:wrap;gap:4px">
                        <?php foreach ($c['countries']??[] as $cc):
                          $flag = strtolower($cc==='UK'?'gb':$cc);
                          $campStates = array_filter($c['states']??[], fn($s) => strpos($s, $cc.':')===0);
                          $stateCount = count($campStates);
                        ?>
                        <div style="display:inline-flex;align-items:center;gap:4px;background:var(--bg-3);border:1px solid var(--border);border-radius:5px;padding:3px 7px;cursor:pointer"
                             title="<?= $stateCount>0 ? htmlspecialchars(implode(', ', array_map(fn($s)=>explode(':',$s)[1]??$s, array_values($campStates)))) : 'All states' ?>"
                             onclick="showStates(this)">
                          <img src="https://flagcdn.com/w40/<?= $flag ?>.png" style="width:16px;height:11px;border-radius:1px;object-fit:cover">
                          <span style="font-size:11px;font-weight:600"><?= $cc ?></span>
                          <?php if ($stateCount > 0): ?>
                          <span style="font-size:10px;background:var(--yellow-dim);color:var(--yellow);padding:1px 5px;border-radius:3px"><?= $stateCount ?></span>
                          <?php else: ?>
                          <span style="font-size:10px;color:var(--text-3)">All</span>
                          <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;gap:4px;flex-wrap:wrap">
                            <?php if (in_array($c['status'], ['pending','review'])): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action"      value="approve">
                                <input type="hidden" name="campaign_id" value="<?= $c['campaign_id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm">Approve</button>
                            </form>
                            <button class="btn btn-danger btn-sm" onclick='rejectCamp("<?= $c['campaign_id'] ?>")'>Reject</button>
                            <?php endif; ?>
                            <?php if (in_array($c['status'], ['active','paused','rejected'])): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action"      value="set_review">
                                <input type="hidden" name="campaign_id" value="<?= $c['campaign_id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm" style="color:var(--blue)">Review</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($c['status'] === 'active'): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action"      value="pause">
                                <input type="hidden" name="campaign_id" value="<?= $c['campaign_id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Pause</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($c['status'] === 'paused'): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action"      value="resume">
                                <input type="hidden" name="campaign_id" value="<?= $c['campaign_id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm">Resume</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this campaign?')">
                                <input type="hidden" name="action"      value="delete">
                                <input type="hidden" name="campaign_id" value="<?= $c['campaign_id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Del</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="modal" id="rejectModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Reject Campaign</div>
            <div class="modal-close" onclick="closeModal('rejectModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action"      value="reject">
            <input type="hidden" name="campaign_id" id="reject_cid">
            <div class="form-group">
                <label class="form-label">Rejection Reason</label>
                <textarea name="reason" class="form-control" required placeholder="Explain why this campaign is being rejected..."></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject Campaign</button>
            </div>
        </form>
    </div>
</div>

<!-- States detail modal -->
<div class="modal" id="statesModal">
  <div class="modal-box" style="max-width:500px">
    <div class="modal-header">
      <div class="modal-title" id="statesModalTitle">States / Regions</div>
      <div class="modal-close" onclick="closeModal('statesModal')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </div>
    </div>
    <div id="statesModalBody" style="display:flex;flex-wrap:wrap;gap:6px;max-height:400px;overflow-y:auto"></div>
  </div>
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
function rejectCamp(id) {
    document.getElementById('reject_cid').value = id;
    openModal('rejectModal');
}

function showStates(el) {
  var title = el.querySelector('span').textContent + ' — States';
  var tip   = el.getAttribute('title');
  document.getElementById('statesModalTitle').textContent = title;
  var body  = document.getElementById('statesModalBody');
  body.innerHTML = '';
  if (!tip || tip === 'All states') {
    body.innerHTML = '<div style="color:var(--text-2);font-size:13px">Targeting all states in this country</div>';
  } else {
    tip.split(', ').forEach(function(s) {
      var sp = document.createElement('span');
      sp.className = 'badge badge-yellow';
      sp.textContent = s.trim();
      body.appendChild(sp);
    });
  }
  openModal('statesModal');
}

// Reuse the existing creatives.php preview endpoint — admin already has access
function previewCreative(id, name) {
  var url = '/admin/creatives.php?preview=' + encodeURIComponent(id);
  document.getElementById('previewTitle').textContent  = name + ' — Creative Preview';
  document.getElementById('previewFrame').src          = url;
  document.getElementById('previewOpenBtn').href       = url;
  document.getElementById('previewUrlBar').textContent = name + '.html';
  openModal('previewModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
