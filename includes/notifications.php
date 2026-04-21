<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/logo.php';
require_once __DIR__ . '/../includes/notifications.php';
requireUser();
$user = currentUser();
if (!$user) { session_destroy(); header('Location: /login.php'); exit; }
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$campaigns = readJson(CAMPAIGNS_FILE);
$userCampaigns = array_filter($campaigns, fn($c) => $c['user_id'] === $user['id']);
$pendingCount  = count(array_filter($userCampaigns, fn($c) => $c['status'] === 'pending'));
$unreadNotifs  = countUnread($user['id']);

// Account type config
$accountType  = $user['account_type'] ?? 'rookie';
$acctConfig = [
    'rookie'       => ['label'=>'Rookie',       'color'=>'#8888a8', 'bg'=>'rgba(136,136,168,0.12)'],
    'professional' => ['label'=>'Professional', 'color'=>'#4d9eff', 'bg'=>'rgba(77,158,255,0.12)'],
    'expert'       => ['label'=>'Expert',       'color'=>'#ffc800', 'bg'=>'rgba(255,200,0,0.12)'],
];
$acct = $acctConfig[$accountType] ?? $acctConfig['rookie'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Advora | <?= ucfirst(str_replace('_',' ',$currentPage)) ?></title>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="stylesheet" href="/assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="logo"><?= advoraLogoFullSvg(34) ?></div>
  <div class="nav-section">Main</div>
  <a href="/user/dashboard.php"   class="nav-item <?= $currentPage==='dashboard'  ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
    Dashboard
  </a>
  <a href="/user/campaigns.php"   class="nav-item <?= in_array($currentPage,['campaigns','create_campaign','campaign_view'])?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-5v12L3 14v-3z"/></svg>
    Campaigns
    <?php if ($pendingCount > 0): ?><span class="nav-badge"><?= $pendingCount ?></span><?php endif; ?>
  </a>
  <a href="/user/metrics.php"     class="nav-item <?= $currentPage==='metrics'    ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
    Metrics
  </a>
  <a href="/user/creatives.php"   class="nav-item <?= $currentPage==='creatives'  ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    Creatives
  </a>
  <a href="/user/subscription.php" class="nav-item <?= $currentPage==='subscription'?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
    Subscription
  </a>
  <div class="nav-section">Finance</div>
  <a href="/user/funds.php"        class="nav-item <?= $currentPage==='funds'       ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    Add Funds
  </a>
  <a href="/user/transactions.php" class="nav-item <?= $currentPage==='transactions'?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    Transactions
  </a>
  <div class="nav-section">Account</div>
  <a href="/user/notifications.php" class="nav-item <?= $currentPage==='notifications'?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    Notifications
    <?php if ($unreadNotifs > 0): ?><span class="nav-badge"><?= $unreadNotifs ?></span><?php endif; ?>
  </a>
  <a href="/user/profile.php"      class="nav-item <?= $currentPage==='profile'    ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    Profile
  </a>
  <a href="/logout.php" class="nav-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    Logout
  </a>
</aside>

<header class="topbar">
  <div class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </div>
  <div class="topbar-title"><?= ucfirst(str_replace('_',' ',$currentPage)) ?></div>
  <div class="topbar-user" style="gap:10px">

    <!-- CST Clock -->
    <div style="display:flex;align-items:center;gap:6px;background:var(--bg-3);border:1px solid var(--border);padding:5px 11px;border-radius:8px;font-size:12px;color:var(--text-2)">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <span id="cst-clock">--:-- --</span>
      <span style="color:var(--text-3);font-size:10px">CST</span>
    </div>

    <!-- Account type badge -->
    <div style="display:flex;align-items:center;gap:5px;background:<?= $acct['bg'] ?>;border:1px solid <?= $acct['color'] ?>33;padding:5px 11px;border-radius:8px;font-size:12px;font-weight:700;color:<?= $acct['color'] ?>">
      <?php if ($accountType === 'expert'): ?>
      <svg width="12" height="12" viewBox="0 0 24 24" fill="<?= $acct['color'] ?>"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      <?php elseif ($accountType === 'professional'): ?>
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="<?= $acct['color'] ?>" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
      <?php else: ?>
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="<?= $acct['color'] ?>" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php endif; ?>
      <?= $acct['label'] ?>
    </div>

    <!-- Notification bell -->
    <a href="/user/notifications.php" style="position:relative;width:36px;height:36px;background:var(--bg-3);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--text-2);text-decoration:none;transition:all .15s" onmouseover="this.style.borderColor='var(--border-hi)'" onmouseout="this.style.borderColor='var(--border)'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <?php if ($unreadNotifs > 0): ?>
      <span style="position:absolute;top:-4px;right:-4px;background:var(--red);color:#fff;font-size:9px;font-weight:800;width:16px;height:16px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid var(--bg)"><?= $unreadNotifs > 9 ? '9+' : $unreadNotifs ?></span>
      <?php endif; ?>
    </a>

    <!-- Balance -->
    <div class="balance-pill">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      <span data-live-balance><?= fmtMoney($user['balance']) ?></span>
    </div>

    <div class="user-avatar"><?= strtoupper(substr($user['username'],0,1)) ?></div>
  </div>
</header>

<main class="main">
<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type']==='error'?'danger':$flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>
<?php $notice = getNetworkNotice(); if (!empty($notice['enabled']) && !empty($notice['text'])): ?>
<div class="network-ticker">
  <svg class="ticker-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
  <div class="ticker-text"><?= htmlspecialchars($notice['text']) ?></div>
</div>
<?php endif; ?>

<script>
// CST Clock (UTC-6)
function updateCSTClock() {
  const now = new Date();
  const cst = new Date(now.toLocaleString('en-US', { timeZone: 'America/Chicago' }));
  let h = cst.getHours(), m = cst.getMinutes(), s = cst.getSeconds();
  const ampm = h >= 12 ? 'PM' : 'AM';
  h = h % 12 || 12;
  document.getElementById('cst-clock').textContent =
    String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0') + ' ' + ampm;
}
updateCSTClock();
setInterval(updateCSTClock, 1000);
</script>