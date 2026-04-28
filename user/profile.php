<?php
require_once __DIR__ . '/../includes/user_header.php';

// Only allow password change — no profile editing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        addAdminNotification($user['id'], $user['username'], 'password_changed',
            'Password Changed',
            $user['username'] . ' changed their account password.'
        );
        flash('Password updated successfully', 'success');
    }
    safeRedirect('/user/profile.php');
}

$campaigns   = readJson(CAMPAIGNS_FILE);
$userCamps   = array_filter($campaigns, fn($c) => $c['user_id'] === $user['id']);
$docVerified = $user['doc_verified'] ?? false;
$at          = $user['account_type'] ?? 'rookie';
$atColors    = [
    'rookie'       => ['#8888a8', 'Rookie'],
    'professional' => ['#4d9eff', 'Professional'],
    'expert'       => ['#ffc800', 'Expert'],
];
$atc = $atColors[$at] ?? $atColors['rookie'];
?>

<div class="page-header">
  <div>
    <div class="page-title">Profile</div>
    <div class="page-subtitle">Your account details</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="prof-grid">
<style>@media(max-width:800px){.prof-grid{grid-template-columns:1fr!important}}</style>

<!-- Left: view-only -->
<div style="display:flex;flex-direction:column;gap:18px">

  <div class="card">
    <div class="card-title" style="margin-bottom:16px">Account</div>
    <div class="detail-row"><span class="dk">Username</span><strong class="dv"><?= htmlspecialchars($user['username']) ?></strong></div>
    <div class="detail-row"><span class="dk">User ID</span><code style="color:var(--yellow);font-size:11px"><?= $user['id'] ?></code></div>
    <div class="detail-row"><span class="dk">Balance</span><strong class="dv" style="color:var(--yellow)"><span data-live-balance><?= fmtMoney($user['balance']) ?></span></strong></div>
    <div class="detail-row"><span class="dk">Account Type</span><strong style="color:<?= $atc[0] ?>"><?= $atc[1] ?></strong></div>
    <div class="detail-row"><span class="dk">Campaigns</span><strong class="dv"><?= count($userCamps) ?></strong></div>
    <div class="detail-row"><span class="dk">Member Since</span><strong class="dv"><?= date('M d, Y', $user['created_at']) ?></strong></div>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div class="card-title" style="margin:0">Personal Details</div>
      <span style="font-size:11px;color:var(--text-3);background:var(--bg-3);border:1px solid var(--border);padding:3px 9px;border-radius:5px"></span>
    </div>
    <?php
    $fields = [
      ['Full Name',  $user['full_name']  ?? ''],
      ['Email',      $user['email']       ?? ''],
      ['Phone',      $user['phone']       ?? ''],
      ['Address',    $user['address']     ?? ''],
      ['Telegram',   !empty($user['telegram_id']) ? '@'.ltrim($user['telegram_id'],'@') : ''],
    ];
    foreach ($fields as [$label, $val]):
    ?>
    <div class="detail-row">
      <span class="dk"><?= $label ?></span>
      <span class="dv" style="color:<?= $val?'var(--text)':'var(--text-3)' ?>"><?= $val ? htmlspecialchars($val) : '—' ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div class="card-title" style="margin:0">Business</div>
      <span style="font-size:11px;color:var(--text-3);background:var(--bg-3);border:1px solid var(--border);padding:3px 9px;border-radius:5px"></span>
    </div>
    <div class="detail-row">
      <span class="dk">Business Name</span>
      <span class="dv" style="color:<?= !empty($user['business_name'])?'var(--text)':'var(--text-3)' ?>"><?= !empty($user['business_name']) ? htmlspecialchars($user['business_name']) : '—' ?></span>
    </div>
    <div class="detail-row">
      <span class="dk">Business Address</span>
      <span class="dv" style="color:<?= !empty($user['business_address'])?'var(--text)':'var(--text-3)' ?>"><?= !empty($user['business_address']) ? htmlspecialchars($user['business_address']) : '—' ?></span>
    </div>
    <div class="detail-row">
      <span class="dk">Document</span>
      <?php if ($docVerified): ?>
      <span class="badge badge-success">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="margin-right:3px"><polyline points="20 6 9 17 4 12"/></svg>
        Verified
      </span>
      <?php else: ?>
      <span class="badge badge-pending">Unverified</span>
      <?php endif; ?>
    </div>
    <?php if (!$docVerified): ?>
    <div style="margin-top:12px;padding:10px 13px;background:var(--bg-3);border-radius:6px;font-size:12.5px;color:var(--text-2)">
      Contact your manager to verify your business document.
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- Right: password change -->
<div>
  <div class="card">
    <div class="card-title" style="margin-bottom:6px">Change Password</div>
    <p style="font-size:13px;color:var(--text-2);margin-bottom:20px">Update your account password below.</p>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Current Password</label>
        <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
      </div>
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input type="password" name="new_password" class="form-control" required minlength="6" autocomplete="new-password">
        <div class="form-hint">Minimum 6 characters</div>
      </div>
      <div class="form-group">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="confirm_password" class="form-control" required minlength="6" autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Update Password
      </button>
    </form>
  </div>
</div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>