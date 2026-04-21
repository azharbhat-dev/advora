<?php
require_once __DIR__ . '/../includes/admin_header.php';

$users = readJson(USERS_FILE);
$campaigns = readJson(CAMPAIGNS_FILE);
$creatives = readJson(CREATIVES_FILE);
$topups = readJson(TOPUPS_FILE);

$totalUsers = count($users);
$totalCampaigns = count($campaigns);
$pendingCampaigns = count(array_filter($campaigns, fn($c) => $c['status'] === 'pending'));
$reviewCampaigns = count(array_filter($campaigns, fn($c) => $c['status'] === 'review'));
$pendingCreatives = count(array_filter($creatives, fn($c) => $c['status'] === 'pending'));
$pendingTopups = count(array_filter($topups, fn($t) => $t['status'] === 'pending'));

$totalImp = 0; $totalClk = 0; $totalGh = 0; $totalSpent = 0; $totalBalance = 0;
foreach ($campaigns as $c) {
    $totalImp += $c['impressions'] ?? 0;
    $totalClk += $c['clicks'] ?? 0;
    $totalGh += $c['views_count'] ?? 0;
    $totalSpent += $c['spent'] ?? 0;
}
foreach ($users as $u) $totalBalance += $u['balance'];
?>

<div class="page-header">
    <div>
        <div class="page-title">Admin Overview</div>
        <div class="page-subtitle">System-wide statistics and pending items</div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
        <div class="stat-label">Total Users</div>
        <div class="stat-value" data-live="total-users"><?= $totalUsers ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-5v12L3 14v-3z"/></svg></div>
        <div class="stat-label">Total Campaigns</div>
        <div class="stat-value" data-live="total-campaigns"><?= $totalCampaigns ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(255, 149, 0, 0.1); color: var(--orange);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div class="stat-label">Pending Review</div>
        <div class="stat-value" data-live="pending-total"><?= $pendingCampaigns + $reviewCampaigns + $pendingCreatives + $pendingTopups ?></div>
        <div class="stat-change neg">Action required</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(0, 208, 132, 0.1); color: var(--green);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div class="stat-label">Total Balance</div>
        <div class="stat-value" data-live-money="total-balance"><?= fmtMoney($totalBalance) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
        <div class="stat-label">Total Impressions</div>
        <div class="stat-value" data-live="total-impressions"><?= fmtNum($totalImp) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(52, 152, 219, 0.1); color: var(--blue);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/></svg></div>
        <div class="stat-label">Total Clicks</div>
        <div class="stat-value" data-live="total-clicks"><?= fmtNum($totalClk) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(0, 208, 132, 0.1); color: var(--green);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div class="stat-label">Views</div>
        <div class="stat-value" data-live="total-views"><?= fmtNum($totalGh) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(255, 149, 0, 0.1); color: var(--orange);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/></svg></div>
        <div class="stat-label">Revenue (Spent)</div>
        <div class="stat-value" data-live-money="total-spent"><?= fmtMoney($totalSpent) ?></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;" class="adm-grid">
<style>@media (max-width: 900px) { .adm-grid { grid-template-columns: 1fr !important; } }</style>

<div class="card">
    <div class="card-title" style="margin-bottom: 16px;">Quick Actions</div>
    <div style="display: grid; gap: 10px;">
        <a href="/admin/users.php?action=new" class="btn btn-primary" style="justify-content: flex-start;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            Create New User
        </a>
        <a href="/admin/campaigns.php?filter=pending" class="btn btn-secondary" style="justify-content: flex-start;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-5v12L3 14v-3z"/></svg>
            Review Pending Campaigns (<?= $pendingCampaigns + $reviewCampaigns ?>)
        </a>
        <a href="/admin/creatives.php?filter=pending" class="btn btn-secondary" style="justify-content: flex-start;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
            Review Pending Creatives (<?= $pendingCreatives ?>)
        </a>
        <a href="/admin/topups.php?filter=pending" class="btn btn-secondary" style="justify-content: flex-start;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/></svg>
            Review Pending Topups (<?= $pendingTopups ?>)
        </a>
        <a href="/admin/stats_injector.php" class="btn btn-secondary" style="justify-content: flex-start;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            Inject Stats / Traffic
        </a>
    </div>
</div>

<div class="card">
    <div class="card-title" style="margin-bottom: 16px;">Recent Activity</div>
    <?php
    $activity = [];
    foreach (array_slice(array_reverse($campaigns), 0, 5) as $c) {
        $activity[] = ['type' => 'campaign', 'label' => 'Campaign: ' . $c['name'], 'time' => $c['created_at'], 'status' => $c['status']];
    }
    foreach (array_slice(array_reverse($topups), 0, 5) as $t) {
        $activity[] = ['type' => 'topup', 'label' => 'Topup: ' . fmtMoney($t['amount']) . ' by ' . $t['username'], 'time' => $t['created_at'], 'status' => $t['status']];
    }
    usort($activity, fn($a, $b) => $b['time'] <=> $a['time']);
    $activity = array_slice($activity, 0, 8);
    ?>
    <?php if (empty($activity)): ?>
    <div style="padding: 20px; text-align: center; color: var(--text-3);">No activity yet</div>
    <?php else: ?>
    <?php foreach ($activity as $a):
        $sCls = ['pending' => 'badge-pending', 'approved' => 'badge-success', 'active' => 'badge-success', 'rejected' => 'badge-danger', 'review' => 'badge-info'][$a['status']] ?? 'badge-muted';
    ?>
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border);">
        <div>
            <div style="font-size: 13px;"><?= htmlspecialchars($a['label']) ?></div>
            <div style="font-size: 11px; color: var(--text-3); margin-top: 2px;"><?= timeAgo($a['time']) ?></div>
        </div>
        <span class="badge <?= $sCls ?>"><?= $a['status'] ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
