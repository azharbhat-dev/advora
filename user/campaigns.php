<?php
require_once __DIR__ . '/../includes/user_header.php';

// Handle delete from list
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'delete') {
    $delId = $_POST['campaign_id'] ?? '';
    // Look up campaign first so we can log it
    $stmt = db()->prepare('SELECT name FROM campaigns WHERE campaign_id = ? AND user_id = ?');
    $stmt->execute([$delId, $user['id']]);
    $row = $stmt->fetch();

    $stmt = db()->prepare('DELETE FROM campaigns WHERE campaign_id = ? AND user_id = ?');
    $stmt->execute([$delId, $user['id']]);

    if ($row) {
        addAdminNotification($user['id'], $user['username'], 'campaign_deleted',
            'Campaign Deleted',
            $user['username'] . ' deleted campaign "' . $row['name'] . '" (' . $delId . ')'
        );
    }

    flash('Campaign deleted', 'success');
    safeRedirect('/user/campaigns.php');
}

$campaigns     = readJson(CAMPAIGNS_FILE);
$userCampaigns = array_filter($campaigns, fn($c) => $c['user_id'] === $user['id']);
$userCampaigns = array_reverse($userCampaigns);

// Limit info
$userLimit = getUserCampaignLimit($user['id']);
$userCount = count(array_filter($campaigns, fn($c) => $c['user_id'] === $user['id']));
$atLimit   = $userCount >= $userLimit;
$pctUsed   = $userLimit > 0 ? round($userCount / $userLimit * 100) : 100;

$filter = $_GET['status'] ?? 'all';
if ($filter !== 'all') {
    $userCampaigns = array_filter($userCampaigns, fn($c) => $c['status'] === $filter);
}
?>

<div class="page-header">
    <div>
        <div class="page-title">Campaigns</div>
        <div class="page-subtitle">Manage all your advertising campaigns &middot; Using <strong style="color:var(--<?= $atLimit?'red':'yellow' ?>)"><?= $userCount ?> of <?= $userLimit ?></strong></div>
    </div>
    <?php if ($atLimit): ?>
    <button class="btn btn-secondary" onclick="alert('Campaign limit reached (<?= $userLimit ?>). Delete a campaign or contact admin to increase your limit.')" style="opacity:.6;cursor:not-allowed">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Limit Reached (<?= $userLimit ?>)
    </button>
    <?php else: ?>
    <a href="/user/create_campaign.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Create Campaign
    </a>
    <?php endif; ?>
</div>

<?php if ($atLimit): ?>
<div class="alert alert-warning">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div>
      <strong>Campaign limit reached.</strong>
      You have <?= $userCount ?> of <?= $userLimit ?> allowed campaigns (counts active, paused, under review, and rejected).
      Delete a campaign or contact your admin for a higher limit.
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="filter-bar">
        <select class="form-control" onchange="location.href='?status='+this.value">
            <option value="all"      <?= $filter==='all'     ?'selected':'' ?>>All Statuses</option>
            <option value="review"   <?= $filter==='review'  ?'selected':'' ?>>Under Review</option>
            <option value="active"   <?= $filter==='active'  ?'selected':'' ?>>Active</option>
            <option value="paused"   <?= $filter==='paused'  ?'selected':'' ?>>Paused</option>
            <option value="rejected" <?= $filter==='rejected'?'selected':'' ?>>Rejected</option>
        </select>
    </div>

    <?php if (empty($userCampaigns)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 11l18-5v12L3 14v-3z"/></svg>
        <h3>No campaigns found</h3>
        <p>Create your first campaign to get started</p>
        <?php if (!$atLimit): ?>
        <a href="/user/create_campaign.php" class="btn btn-primary" style="margin-top:16px">Create Campaign</a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Campaign ID</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>CPV</th>
                    <th>Daily Budget</th>
                    <th>Spent</th>
                    <th>Impressions</th>
                    <th>Views</th>
                    <th>Hits</th>
                    <th>CTR</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($userCampaigns as $c):
                $statusClass = [
                    'review'   => 'badge-info',
                    'active'   => 'badge-success',
                    'paused'   => 'badge-muted',
                    'rejected' => 'badge-danger',
                    'pending'  => 'badge-pending',
                ][$c['status'] ?? 'review'] ?? 'badge-muted';
                $statusLabel = $c['status'] === 'review' ? 'Under Review' : ($c['status'] ?? 'review');
                $cpvRate     = $c['cpv'] ?? $c['cpc'] ?? 0;
                $dailyBudget = $c['daily_budget'] ?? $c['budget'] ?? 0;
                $cid         = $c['campaign_id'];
            ?>
                <tr>
                    <td><code style="color:var(--yellow);font-size:12px"><?= htmlspecialchars($cid) ?></code></td>
                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                    <td>
                        <span class="badge <?= $statusClass ?>"
                          data-live-badge="camp:<?= $cid ?>:status"
                          data-current-status="<?= $c['status'] ?>"><?= $statusLabel ?></span>
                    </td>
                    <td><?= fmtMoney($cpvRate) ?></td>
                    <td><?= fmtMoney($dailyBudget) ?></td>
                    <td data-live-money="camp:<?= $cid ?>:spent"><?= fmtMoney($c['spent']??0) ?></td>
                    <td data-live="camp:<?= $cid ?>:impressions"><?= fmtNum($c['impressions']??0) ?></td>
                    <td data-live="camp:<?= $cid ?>:views"><?= fmtNum($c['good_hits']??0) ?></td>
                    <td data-live="camp:<?= $cid ?>:hits"><?= fmtNum($c['clicks']??0) ?></td>
                    <td data-live="camp:<?= $cid ?>:ctr"><?= ($c['impressions']??0)>0 ? round(($c['good_hits']??0)/($c['impressions']??1)*100,2).'%' : '0%' ?></td>
                    <td style="font-size:12px;color:var(--text-2)"><?= timeAgo($c['created_at']) ?></td>
                    <td style="display:flex;gap:5px">
                        <a href="/user/campaign_view.php?id=<?= urlencode($cid) ?>" class="btn btn-secondary btn-sm">View</a>
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Delete campaign \'<?= htmlspecialchars(addslashes($c['name'])) ?>\'? Cannot be undone.')">
                            <input type="hidden" name="action"      value="delete">
                            <input type="hidden" name="campaign_id" value="<?= $cid ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
