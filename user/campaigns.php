<?php
require_once __DIR__ . '/../includes/user_header.php';

$campaigns     = readJson(CAMPAIGNS_FILE);
$userCampaigns = array_filter($campaigns, fn($c) => $c['user_id'] === $user['id']);
$userCampaigns = array_reverse($userCampaigns);

$filter = $_GET['status'] ?? 'all';
if ($filter !== 'all') {
    $userCampaigns = array_filter($userCampaigns, fn($c) => $c['status'] === $filter);
}
?>

<div class="page-header">
    <div>
        <div class="page-title">Campaigns</div>
        <div class="page-subtitle">Manage all your advertising campaigns</div>
    </div>
    <a href="/user/create_campaign.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Create Campaign
    </a>
</div>

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
        <a href="/user/create_campaign.php" class="btn btn-primary" style="margin-top:16px">Create Campaign</a>
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
                    <td><a href="/user/campaign_view.php?id=<?= urlencode($cid) ?>" class="btn btn-secondary btn-sm">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>