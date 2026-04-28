<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/logo.php';
requireAdmin();
$currentPage      = basename($_SERVER['PHP_SELF'], '.php');
$pendingTopups    = count(array_filter(readJson(TOPUPS_FILE),    fn($t) => $t['status']==='pending'));
$pendingCreatives = count(array_filter(readJson(CREATIVES_FILE), fn($c) => $c['status']==='pending'));
$pendingCampaigns = count(array_filter(readJson(CAMPAIGNS_FILE), fn($c) => in_array($c['status'],['pending','review'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Advora Admin | <?= ucfirst(str_replace('_',' ',$currentPage)) ?></title>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="stylesheet" href="/assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body data-role="admin">

<aside class="sidebar" id="sidebar">
  <div class="logo"><?= advoraLogoFullSvg(34) ?></div>
  <div class="nav-section" style="color:var(--yellow)">Admin Panel</div>
  <a href="/admin/index.php"        class="nav-item <?= $currentPage==='index'          ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
    Overview
  </a>
  <a href="/admin/user_details.php"  class="nav-item <?= $currentPage==='user_details'   ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    User Overview
  </a>
  <a href="/admin/users.php"        class="nav-item <?= $currentPage==='users'          ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    Users
  </a>
  <a href="/admin/campaigns.php"    class="nav-item <?= $currentPage==='campaigns'      ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-5v12L3 14v-3z"/></svg>
    Campaigns
    <span class="nav-badge" id="badge-campaigns" style="<?= $pendingCampaigns>0?'':'display:none' ?>"><?= $pendingCampaigns ?></span>
  </a>
  <a href="/admin/creatives.php"    class="nav-item <?= $currentPage==='creatives'      ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    Creatives
    <span class="nav-badge" id="badge-creatives" style="<?= $pendingCreatives>0?'':'display:none' ?>"><?= $pendingCreatives ?></span>
  </a>
  <a href="/admin/subscriptions.php" class="nav-item <?= $currentPage==='subscriptions' ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
    Subscriptions
  </a>
  <a href="/admin/campaign_capacity.php" class="nav-item <?= $currentPage==='campaign_capacity' ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7L12 3 4 7l8 4 8-4z"/><path d="M4 7v10l8 4 8-4V7"/><path d="M12 11v10"/></svg>
    Campaign Capacity
  </a>
  <a href="/admin/stats_injector.php" class="nav-item <?= $currentPage==='stats_injector'?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
    Stats Injector
  </a>
  <a href="/admin/insights.php"      class="nav-item <?= $currentPage==='insights'        ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><polyline points="2 12 6 8 10 12 14 6 18 10"/></svg>
    Insights
  </a>
    <a href="/admin/admin_notifications.php" class="nav-item <?= $currentPage==='admin_notifications' ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    Activity Feed
    <span class="nav-badge" id="badge-admin-notifs" style="display:none">0</span>
  </a>
  <a href="/admin/notifications.php" class="nav-item <?= $currentPage==='notifications'  ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    Notifications
  </a>
  <div class="nav-section">Finance</div>
  <a href="/admin/topups.php"       class="nav-item <?= $currentPage==='topups'         ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    Topup Requests
    <span class="nav-badge" id="badge-topups" style="<?= $pendingTopups>0?'':'display:none' ?>"><?= $pendingTopups ?></span>
  </a>
  <div class="nav-section">Settings</div>
  <a href="/admin/wallets.php"       class="nav-item <?= $currentPage==='wallets'        ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/></svg>
    Wallets
  </a>
  <a href="/admin/countries.php"     class="nav-item <?= $currentPage==='countries'      ?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
    Countries
  </a>
  <a href="/admin/network_notice.php" class="nav-item <?= $currentPage==='network_notice'?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5L6 9H2v6h4l5 4V5z"/></svg>
    Network Notice
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
  <div class="topbar-title">Admin &mdash; <?= ucfirst(str_replace('_',' ',$currentPage)) ?></div>
  <div class="topbar-user">
    <!-- Admin notification bell -->
    <a href="/admin/admin_notifications.php" id="admin-notif-bell"
       style="position:relative;width:34px;height:34px;background:var(--bg-3);border:1px solid var(--border);border-radius:7px;display:flex;align-items:center;justify-content:center;color:var(--text-2);text-decoration:none;transition:border-color .15s;flex-shrink:0"
       onmouseover="this.style.borderColor='var(--border-hi)'" onmouseout="this.style.borderColor='var(--border)'">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <span id="admin-notif-badge" style="display:none;position:absolute;top:-4px;right:-4px;background:var(--red);color:#fff;font-size:9px;font-weight:800;min-width:15px;height:15px;border-radius:8px;align-items:center;justify-content:center;border:2px solid var(--bg);padding:0 3px">0</span>
    </a>
    <div class="balance-pill" style="background:rgba(0,229,153,.08);color:var(--green);border-color:rgba(0,229,153,.2)">
      <span class="live-dot"></span> Admin Mode
    </div>
    <div class="user-avatar" style="background:linear-gradient(135deg,var(--red),#c00)">A</div>
  </div>
</header>

<main class="main">
<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type']==='error'?'danger':$flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>
