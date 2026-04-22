<?php
require_once __DIR__ . '/../includes/admin_header.php';

// Mark all read
if (isset($_GET['mark_all'])) {
    markAllAdminNotifsRead();
    safeRedirect('/admin/admin_notifications.php');
}

// Mark single read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $nid  = $_POST['notif_id'] ?? '';
    $all  = readJson(ADMIN_NOTIF_FILE, []);
    foreach ($all as &$n) { if ($n['id'] === $nid) { $n['read'] = true; break; } }
    writeJson(ADMIN_NOTIF_FILE, $all);
    safeRedirect('/admin/admin_notifications.php' . (!empty($_GET['user']) ? '?user='.urlencode($_GET['user']) : ''));
}

// Delete single
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notif'])) {
    $nid = $_POST['notif_id'] ?? '';
    $all = array_values(array_filter(readJson(ADMIN_NOTIF_FILE, []), fn($n) => $n['id'] !== $nid));
    writeJson(ADMIN_NOTIF_FILE, $all);
    safeRedirect('/admin/admin_notifications.php' . (!empty($_GET['user']) ? '?user='.urlencode($_GET['user']) : ''));
}

// Clear all (with confirm in UI)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all'])) {
    writeJson(ADMIN_NOTIF_FILE, []);
    safeRedirect('/admin/admin_notifications.php');
}

$users     = readJson(USERS_FILE);
$userMap   = [];
foreach ($users as $u) $userMap[$u['id']] = $u;

$filterUser = $_GET['user'] ?? '';
$allNotifs  = getAdminNotifications($filterUser ?: null);
$unread     = count(array_filter($allNotifs, fn($n) => !$n['read']));

// Build per-user unread counts for sidebar
$userUnread = [];
foreach (getAdminNotifications() as $n) {
    if (!$n['read']) {
        $userUnread[$n['user_id']] = ($userUnread[$n['user_id']] ?? 0) + 1;
    }
}

// Icon + color per type
function activityIcon($type) {
    return match($type) {
        'campaign_created'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-5v12L3 14v-3z"/></svg>',
        'campaign_updated'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        'campaign_paused'   => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>',
        'campaign_resumed'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>',
        'campaign_deleted'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>',
        'creative_uploaded' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
        'creative_deleted'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>',
        'deposit_submitted' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'password_changed'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        default             => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>',
    };
}

function activityColor($type) {
    return match($type) {
        'campaign_created'  => 'var(--green)',
        'campaign_updated'  => 'var(--blue)',
        'campaign_paused'   => 'var(--orange)',
        'campaign_resumed'  => 'var(--green)',
        'campaign_deleted'  => 'var(--red)',
        'creative_uploaded' => 'var(--blue)',
        'creative_deleted'  => 'var(--red)',
        'deposit_submitted' => 'var(--yellow)',
        'password_changed'  => 'var(--purple)',
        default             => 'var(--text-2)',
    };
}
?>

<div class="page-header">
  <div>
    <div class="page-title">Activity Feed
      <?php if ($unread > 0): ?>
      <span style="display:inline-block;background:var(--red);color:#fff;font-size:11px;font-weight:800;padding:2px 8px;border-radius:12px;margin-left:8px;vertical-align:middle"><?= $unread ?> new</span>
      <?php endif; ?>
    </div>
    <div class="page-subtitle">All user actions and account changes</div>
  </div>
  <div style="display:flex;gap:8px">
    <?php if ($unread > 0): ?>
    <a href="?mark_all=1" class="btn btn-secondary">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>
      Mark All Read
    </a>
    <?php endif; ?>
    <form method="POST" onsubmit="return confirm('Clear all activity notifications?')">
      <input type="hidden" name="clear_all" value="1">
      <button type="submit" class="btn btn-secondary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
        Clear All
      </button>
    </form>
  </div>
</div>

<div style="display:grid;grid-template-columns:240px 1fr;gap:20px;align-items:start" class="af-grid">
<style>@media(max-width:900px){.af-grid{grid-template-columns:1fr!important}}</style>

<!-- User sidebar -->
<div class="card" style="padding:0;overflow:hidden;position:sticky;top:80px">
  <div style="padding:13px 16px;border-bottom:1px solid var(--border);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-3)">Filter by User</div>
  <a href="/admin/admin_notifications.php"
     style="display:flex;align-items:center;gap:10px;padding:11px 16px;text-decoration:none;border-bottom:1px solid var(--border);transition:background .15s;<?= !$filterUser ? 'background:var(--yellow-dim)' : '' ?>"
     onmouseover="this.style.background='var(--bg-3)'" onmouseout="this.style.background='<?= !$filterUser ? 'var(--yellow-dim)' : '' ?>'">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="<?= !$filterUser ? 'var(--yellow)' : 'var(--text-2)' ?>" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
    <span style="flex:1;font-size:13px;font-weight:600;color:<?= !$filterUser ? 'var(--yellow)' : 'var(--text)' ?>">All Users</span>
    <span style="font-size:11px;color:var(--text-3)"><?= count(getAdminNotifications()) ?></span>
  </a>
  <?php
  // Get unique users who have notifications
  $notifUsers = [];
  foreach (getAdminNotifications() as $n) {
      $uid = $n['user_id'];
      if (!isset($notifUsers[$uid])) {
          $notifUsers[$uid] = ['count'=>0,'unread'=>0,'latest'=>$n['created_at']];
      }
      $notifUsers[$uid]['count']++;
      if (!$n['read']) $notifUsers[$uid]['unread']++;
  }
  arsort($notifUsers);
  foreach ($notifUsers as $uid => $info):
      $u       = $userMap[$uid] ?? null;
      $uname   = $u ? $u['username'] : 'Unknown';
      $isActive = $filterUser === $uid;
  ?>
  <a href="/admin/admin_notifications.php?user=<?= urlencode($uid) ?>"
     style="display:flex;align-items:center;gap:10px;padding:11px 16px;text-decoration:none;border-bottom:1px solid var(--border);transition:background .15s;<?= $isActive ? 'background:var(--yellow-dim)' : '' ?>"
     onmouseover="this.style.background='var(--bg-3)'" onmouseout="this.style.background='<?= $isActive ? 'var(--yellow-dim)' : '' ?>'">
    <div style="width:28px;height:28px;background:var(--yellow);color:#000;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0"><?= strtoupper(substr($uname,0,1)) ?></div>
    <div style="flex:1;min-width:0">
      <div style="font-size:13px;font-weight:600;color:<?= $isActive ? 'var(--yellow)' : 'var(--text)' ?>;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($uname) ?></div>
      <div style="font-size:11px;color:var(--text-3)"><?= $info['count'] ?> events</div>
    </div>
    <?php if ($info['unread'] > 0): ?>
    <span style="background:var(--red);color:#fff;font-size:9px;font-weight:800;padding:2px 6px;border-radius:10px;flex-shrink:0"><?= $info['unread'] ?></span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
  <?php if (empty($notifUsers)): ?>
  <div style="padding:20px;text-align:center;font-size:13px;color:var(--text-3)">No activity yet</div>
  <?php endif; ?>
</div>

<!-- Notifications feed -->
<div>
  <?php if ($filterUser && isset($userMap[$filterUser])): ?>
  <?php $fu = $userMap[$filterUser]; ?>
  <div style="display:flex;align-items:center;gap:12px;padding:14px 18px;background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r);margin-bottom:16px">
    <div style="width:38px;height:38px;background:var(--yellow);color:#000;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:800"><?= strtoupper(substr($fu['username'],0,1)) ?></div>
    <div>
      <div style="font-weight:700;font-size:15px"><?= htmlspecialchars($fu['username']) ?></div>
      <div style="font-size:12px;color:var(--text-2)"><?= htmlspecialchars($fu['email']??'') ?> <?= !empty($fu['telegram_id'])?'· @'.ltrim($fu['telegram_id'],'@'):'' ?></div>
    </div>
    <a href="/admin/user_details.php?user=<?= $fu['id'] ?>" class="btn btn-secondary btn-sm" style="margin-left:auto">View Full Profile</a>
  </div>
  <?php endif; ?>

  <?php if (empty($allNotifs)): ?>
  <div class="card">
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
      <h3>No activity yet</h3>
      <p>User actions will appear here</p>
    </div>
  </div>
  <?php else: ?>

  <!-- Group by date -->
  <?php
  $grouped = [];
  foreach ($allNotifs as $n) {
      $day = date('Y-m-d', $n['created_at']);
      $grouped[$day][] = $n;
  }
  foreach ($grouped as $day => $dayNotifs):
      $label = $day === date('Y-m-d') ? 'Today' : ($day === date('Y-m-d', strtotime('-1 day')) ? 'Yesterday' : date('F j, Y', strtotime($day)));
  ?>
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text-3);margin:20px 0 10px;padding:0 2px"><?= $label ?></div>

  <div class="card" style="padding:0;overflow:hidden;margin-bottom:0">
  <?php foreach ($dayNotifs as $idx => $n):
    $color = activityColor($n['type']);
    $icon  = activityIcon($n['type']);
    $u     = $userMap[$n['user_id']] ?? null;
    $isLast = $idx === count($dayNotifs)-1;
  ?>
  <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 18px;<?= !$isLast ? 'border-bottom:1px solid var(--border);' : '' ?><?= !$n['read'] ? 'background:rgba(255,200,0,.025);border-left:3px solid var(--yellow);' : 'border-left:3px solid transparent;' ?>transition:background .15s">

    <!-- Icon -->
    <div style="width:34px;height:34px;border-radius:50%;background:<?= $color ?>1a;border:1px solid <?= $color ?>33;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:<?= $color ?>">
      <?= $icon ?>
    </div>

    <!-- Content -->
    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px">
        <div style="flex:1">
          <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:2px">
            <?php if (!$filterUser && $u): ?>
            <a href="/admin/admin_notifications.php?user=<?= $u['id'] ?>"
               style="display:inline-flex;align-items:center;gap:5px;background:var(--bg-3);border:1px solid var(--border);padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;color:var(--text);text-decoration:none">
              <div style="width:14px;height:14px;background:var(--yellow);color:#000;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:8px;font-weight:800"><?= strtoupper(substr($n['username'],0,1)) ?></div>
              <?= htmlspecialchars($n['username']) ?>
            </a>
            <?php endif; ?>
            <strong style="font-size:13.5px"><?= htmlspecialchars($n['title']) ?></strong>
            <?php if (!$n['read']): ?>
            <span style="width:7px;height:7px;background:var(--yellow);border-radius:50%;display:inline-block;flex-shrink:0"></span>
            <?php endif; ?>
          </div>
          <div style="font-size:12.5px;color:var(--text-2);line-height:1.5"><?= htmlspecialchars($n['message']) ?></div>
        </div>
        <div style="flex-shrink:0;text-align:right">
          <div style="font-size:11px;color:var(--text-3);white-space:nowrap"><?= date('H:i', $n['created_at']) ?></div>
          <div style="font-size:10px;color:var(--text-3)"><?= timeAgo($n['created_at']) ?></div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div style="display:flex;gap:4px;flex-shrink:0;align-self:center">
      <?php if (!$n['read']): ?>
      <form method="POST" style="display:inline">
        <input type="hidden" name="mark_read" value="1">
        <input type="hidden" name="notif_id"  value="<?= $n['id'] ?>">
        <button type="submit" class="btn btn-secondary btn-sm" title="Mark read" style="padding:4px 8px">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><polyline points="20 6 9 17 4 12"/></svg>
        </button>
      </form>
      <?php endif; ?>
      <form method="POST" style="display:inline">
        <input type="hidden" name="delete_notif" value="1">
        <input type="hidden" name="notif_id"     value="<?= $n['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm" title="Delete" style="padding:4px 8px">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </form>
    </div>

  </div>
  <?php endforeach; ?>
  </div>

  <?php endforeach; ?>
  <?php endif; ?>
</div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>