<?php
require_once __DIR__ . '/../includes/user_header.php';

$campaigns = readJson(CAMPAIGNS_FILE);
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
            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
            <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="paused" <?= $filter === 'paused' ? 'selected' : '' ?>>Paused</option>
            <option value="review" <?= $filter === 'review' ? 'selected' : '' ?>>In Review</option>
            <option value="rejected" <?= $filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
    </div>

    <?php if (empty($userCampaigns)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 11l18-5v12L3 14v-3z"/></svg>
        <h3>No campaigns found</h3>
        <p>Create your first campaign to get started</p>
        <a href="/user/create_campaign.php" class="btn btn-primary" style="margin-top: 16px;">Create Campaign</a>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Campaign ID</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>CPC</th>
                    <th>Budget</th>
                    <th>Spent</th>
                    <th>Impressions</th>
                    <th>Clicks</th>
                    <th>Views</th>
                    <th>CTR</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($userCampaigns as $c):
                $statusClass = [
                    'pending' => 'badge-pending',
                    'active' => 'badge-success',
                    'paused' => 'badge-muted',
                    'review' => 'badge-info',
                    'rejected' => 'badge-danger'
                ][$c['status'] ?? 'pending'] ?? 'badge-muted';
                $ctr = ($c['impressions'] ?? 0) > 0 ? round(($c['clicks'] / $c['impressions']) * 100, 2) : 0;
            ?>
                <tr>
                    <td><code style="color:var(--yellow);font-size:12px"><?= htmlspecialchars($c['campaign_id']) ?></code></td>
                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                    <td><span class="badge <?= $statusClass ?>" data-live-badge="camp:<?= $c['campaign_id'] ?>:status" data-current-status="<?= $c['status'] ?>"><?= htmlspecialchars($c['status']) ?></span></td>
                    <td><?= fmtMoneyPrecise($c["cpc"]) ?></td>
                    <td><?= fmtMoney($c['budget']) ?></td>
                    <td data-live-money="camp:<?= $c['campaign_id'] ?>:spent"><?= fmtMoney($c['spent']??0) ?></td>
                    <td data-live="camp:<?= $c['campaign_id'] ?>:impressions"><?= fmtNum($c['impressions']??0) ?></td>
                    <td data-live="camp:<?= $c['campaign_id'] ?>:clicks"><?= fmtNum($c['clicks']??0) ?></td>
                    <td data-live="camp:<?= $c['campaign_id'] ?>:views"><?= fmtNum($c['good_hits']??0) ?></td>
                    <td data-live="camp:<?= $c['campaign_id'] ?>:ctr"><?= $ctr ?>%</td>
                    <td style="font-size:12px;color:var(--text-2)"><?= timeAgo($c['created_at']) ?></td>
                    <td><a href="/user/campaign_view.php?id=<?= urlencode($c['campaign_id']) ?>" class="btn btn-secondary btn-sm">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
