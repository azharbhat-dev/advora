<?php
require_once __DIR__ . '/../includes/user_header.php';

// Mark all read if requested
if (isset($_GET['mark_all'])) {
    markAllNotificationsRead($user['id']);
    safeRedirect('/user/notifications.php');
}

// Mark single read via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    markNotificationRead($_POST['notif_id'], $user['id']);
    safeRedirect('/user/notifications.php');
}

$notifications = array_reverse(getNotifications($user['id']));
$unread = countUnread($user['id']);
?>

<div class="page-header">
  <div>
    <div class="page-title">Notifications
      <?php if ($unread > 0): ?>
      <span style="display:inline-block;background:var(--red);color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:12px;margin-left:8px;vertical-align:middle"><?= $unread ?></span>
      <?php endif; ?>
    </div>
    <div class="page-subtitle">Account alerts and updates</div>
  </div>
  <?php if ($unread > 0): ?>
  <a href="?mark_all=1" class="btn btn-secondary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>
    Mark All Read
  </a>
  <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
<div class="card">
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    <h3>No notifications yet</h3>
    <p>You'll see account updates and alerts here</p>
  </div>
</div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden">
  <?php foreach ($notifications as $n):
    $color = notifColor($n['type']);
    $icon  = notifIcon($n['type']);
    $unreadStyle = !$n['read'] ? 'background:rgba(255,200,0,0.03);border-left:3px solid var(--yellow)' : 'border-left:3px solid transparent';
  ?>
  <div style="display:flex;align-items:flex-start;gap:14px;padding:16px 20px;border-bottom:1px solid var(--border);<?= $unreadStyle ?>;transition:background .15s">
    <div style="width:36px;height:36px;border-radius:50%;background:<?= $color ?>1a;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:<?= $color ?>">
      <?= $icon ?>
    </div>
    <div style="flex:1;min-width:0">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
        <div>
          <div style="font-weight:600;font-size:13.5px;margin-bottom:3px"><?= htmlspecialchars($n['title']) ?></div>
          <div style="font-size:13px;color:var(--text-2);line-height:1.5"><?= htmlspecialchars($n['message']) ?></div>
        </div>
        <div style="flex-shrink:0;text-align:right">
          <div style="font-size:11px;color:var(--text-3);white-space:nowrap"><?= timeAgo($n['created_at']) ?></div>
          <?php if (!$n['read']): ?>
          <form method="POST" style="margin-top:6px">
            <input type="hidden" name="mark_read" value="1">
            <input type="hidden" name="notif_id"  value="<?= $n['id'] ?>">
            <button type="submit" style="background:none;border:none;color:var(--text-3);font-size:11px;cursor:pointer;padding:0;font-family:inherit">Mark read</button>
          </form>
          <?php else: ?>
          <div style="font-size:11px;color:var(--text-3);margin-top:4px">Read</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>