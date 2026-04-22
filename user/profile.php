<?php
require_once __DIR__ . '/../includes/user_header.php';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
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

// Handle profile details save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $update = [
        'full_name'        => trim($_POST['full_name']        ?? ''),
        'email'            => trim($_POST['email']            ?? ''),
        'phone'            => trim($_POST['phone']            ?? ''),
        'address'          => trim($_POST['address']          ?? ''),
        'telegram_id'      => trim($_POST['telegram_id']      ?? ''),
        'business_name'    => trim($_POST['business_name']    ?? ''),
        'business_address' => trim($_POST['business_address'] ?? ''),
    ];
    updateUser($user['id'], $update);
    flash('Profile updated', 'success');
    safeRedirect('/user/profile.php');
}

$campaigns    = readJson(CAMPAIGNS_FILE);
$userCampaigns = array_filter($campaigns, fn($c) => $c['user_id'] === $user['id']);
$docVerified  = $user['doc_verified'] ?? false;
?>

<div class="page-header">
  <div>
    <div class="page-title">Profile</div>
    <div class="page-subtitle">Manage your account and business details</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="prof-grid">
<style>@media(max-width:800px){.prof-grid{grid-template-columns:1fr!important}}</style>

<!-- Personal & Business Details -->
<div style="display:flex;flex-direction:column;gap:20px">

  <div class="card">
    <div class="card-title" style="margin-bottom:20px">Personal Details</div>
    <form method="POST">
      <input type="hidden" name="save_profile" value="1">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" placeholder="John Doe">
      </div>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="john@example.com">
      </div>
      <div class="form-group">
        <label class="form-label">Phone Number</label>
        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+1 555 000 0000">
      </div>
      <div class="form-group">
        <label class="form-label">Address</label>
        <textarea name="address" class="form-control" rows="2" placeholder="123 Main St, City, Country"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Telegram ID</label>
        <div style="position:relative">
          <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-2)">@</span>
          <input type="text" name="telegram_id" class="form-control" style="padding-left:28px"
            value="<?= htmlspecialchars(ltrim($user['telegram_id'] ?? '', '@')) ?>"
            placeholder="yourusername">
        </div>
        <div class="form-hint">Your Telegram username (without @)</div>
      </div>

      <div class="card-title" style="margin-bottom:16px;margin-top:4px;padding-top:16px;border-top:1px solid var(--border)">Business Information</div>

      <div class="form-group">
        <label class="form-label">Business Name</label>
        <input type="text" name="business_name" class="form-control" value="<?= htmlspecialchars($user['business_name'] ?? '') ?>" placeholder="Acme Corp">
      </div>
      <div class="form-group">
        <label class="form-label">Business Address</label>
        <textarea name="business_address" class="form-control" rows="2" placeholder="Business street, City, Country"><?= htmlspecialchars($user['business_address'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Business Document Status</label>
        <?php if ($docVerified): ?>
        <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(0,229,153,.08);color:var(--green);border:1px solid rgba(0,229,153,.2);padding:9px 16px;border-radius:8px;font-weight:700;font-size:13px">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          Verified
        </div>
        <?php else: ?>
        <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,144,0,.08);color:var(--orange);border:1px solid rgba(255,144,0,.2);padding:9px 16px;border-radius:8px;font-weight:700;font-size:13px">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          Unverified — Contact your manager
        </div>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>
        Save Changes
      </button>
    </form>
  </div>

</div>

<!-- Account info + Password -->
<div style="display:flex;flex-direction:column;gap:20px">

  <div class="card">
    <div class="card-title" style="margin-bottom:18px">Account Information</div>
    <div style="font-size:13px">
      <div class="detail-row"><span class="dk">Username</span><strong class="dv"><?= htmlspecialchars($user['username']) ?></strong></div>
      <div class="detail-row"><span class="dk">User ID</span><code style="color:var(--yellow);font-size:11px"><?= $user['id'] ?></code></div>
      <div class="detail-row"><span class="dk">Balance</span><strong class="dv" style="color:var(--yellow)"><span data-live-balance><?= fmtMoney($user['balance']) ?></span></strong></div>
      <div class="detail-row"><span class="dk">Account Type</span>
        <?php
          $at = $user['account_type'] ?? 'rookie';
          $atCfg = ['rookie'=>['#8888a8','Rookie'],'professional'=>['#4d9eff','Professional'],'expert'=>['#ffc800','Expert']][$at] ?? ['#8888a8','Rookie'];
        ?>
        <span style="color:<?= $atCfg[0] ?>;font-weight:700"><?= $atCfg[1] ?></span>
      </div>
      <div class="detail-row"><span class="dk">Campaigns</span><strong class="dv"><?= count($userCampaigns) ?></strong></div>
      <div class="detail-row"><span class="dk">Document Status</span>
        <?php if ($docVerified): ?>
        <span class="badge badge-success">Verified</span>
        <?php else: ?>
        <span class="badge badge-pending">Unverified</span>
        <?php endif; ?>
      </div>
      <div class="detail-row"><span class="dk">Member Since</span><strong class="dv"><?= date('M d, Y', $user['created_at']) ?></strong></div>
    </div>
  </div>

  <div class="card">
    <div class="card-title" style="margin-bottom:20px">Change Password</div>
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>