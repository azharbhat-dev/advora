<?php
require_once __DIR__ . '/../includes/user_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password'])) {
        flash('Current password is incorrect', 'error');
    } elseif (strlen($new) < 6) {
        flash('New password must be at least 6 characters', 'error');
    } elseif ($new !== $confirm) {
        flash('Passwords do not match', 'error');
    } else {
        updateUser($user['id'], ['password' => password_hash($new, PASSWORD_DEFAULT)]);
        flash('Password updated successfully', 'success');
    }
    safeRedirect('/user/profile.php');
}

$campaigns = readJson(CAMPAIGNS_FILE);
$userCampaigns = array_filter($campaigns, fn($c) => $c['user_id'] === $user['id']);
?>

<div class="page-header">
    <div>
        <div class="page-title">Profile</div>
        <div class="page-subtitle">Manage your account</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;" class="prof-grid">
<style>@media (max-width: 800px) { .prof-grid { grid-template-columns: 1fr !important; } }</style>

<div class="card">
    <div class="card-title" style="margin-bottom: 20px;">Account Information</div>
    <div style="font-size: 13px;">
        <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border);">
            <span style="color: var(--text-2);">Username</span>
            <strong><?= htmlspecialchars($user['username']) ?></strong>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border);">
            <span style="color: var(--text-2);">User ID</span>
            <code style="color: var(--yellow); font-size: 12px;"><?= htmlspecialchars($user['id']) ?></code>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border);">
            <span style="color: var(--text-2);">Balance</span>
            <strong style="color: var(--yellow);"><span data-live-balance><?= fmtMoney($user['balance']) ?></span></strong>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border);">
            <span style="color: var(--text-2);">Total Campaigns</span>
            <strong><?= count($userCampaigns) ?></strong>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 12px 0;">
            <span style="color: var(--text-2);">Member Since</span>
            <strong><?= date('M d, Y', $user['created_at']) ?></strong>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title" style="margin-bottom: 20px;">Change Password</div>
    <form method="POST">
        <input type="hidden" name="change_password" value="1">
        <div class="form-group">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="form-group">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" required minlength="6">
        </div>
        <div class="form-group">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required minlength="6">
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
    </form>
</div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
