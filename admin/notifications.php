<?php
require_once __DIR__ . '/../includes/admin_header.php';

$users = readJson(USERS_FILE);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId  = $_POST['user_id']  ?? '';
    $title   = trim($_POST['title']   ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($userId === 'all') {
        foreach ($users as $u) {
            addNotification($u['id'], 'manual', $title, $message);
        }
        flash('Notification sent to all users', 'success');
    } elseif ($userId) {
        addNotification($userId, 'manual', $title, $message);
        flash('Notification sent', 'success');
    } else {
        flash('Select a user', 'error');
    }
    safeRedirect('/admin/notifications.php');
}

// Show recent notifications (all users)
$allNotifs = array_reverse(readJson(NOTIFICATIONS_FILE, []));
$userMap   = [];
foreach ($users as $u) $userMap[$u['id']] = $u['username'];
?>

<div class="page-header">
  <div>
    <div class="page-title">Notifications</div>
    <div class="page-subtitle">Send manual notifications to users</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="notif-grid">
<style>@media(max-width:900px){.notif-grid{grid-template-columns:1fr!important}}</style>

<div class="card">
  <div class="card-title" style="margin-bottom:20px">Send Notification</div>
  <form method="POST">
    <div class="form-group">
      <label class="form-label">Recipient *</label>
      <select name="user_id" class="form-control" required>
        <option value="">— Select user —</option>
        <option value="all" style="font-weight:700">📢 All Users</option>
        <?php foreach ($users as $u): ?>
        <option value="<?= htmlspecialchars($u['id']) ?>"><?= htmlspecialchars($u['username']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Title *</label>
      <input type="text" name="title" class="form-control" required placeholder="e.g. Account Update">
    </div>
    <div class="form-group">
      <label class="form-label">Message *</label>
      <textarea name="message" class="form-control" required placeholder="Write your notification message..." rows="4"></textarea>
    </div>
    <button type="submit" class="btn btn-primary">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      Send Notification
    </button>
  </form>
</div>

<div class="card">
  <div class="card-title" style="margin-bottom:16px">Recent Notifications (All Users)</div>
  <?php if (empty($allNotifs)): ?>
  <div class="empty-state" style="padding:30px 20px">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:40px;height:40px"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
    <h3 style="font-size:14px">No notifications sent yet</h3>
  </div>
  <?php else: ?>
  <div style="max-height:480px;overflow-y:auto">
    <?php foreach (array_slice($allNotifs, 0, 30) as $n):
      $color = notifColor($n['type']);
    ?>
    <div style="display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid var(--border)">
      <div style="width:30px;height:30px;border-radius:50%;background:<?= $color ?>1a;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:<?= $color ?>">
        <?= notifIcon($n['type']) ?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
          <span style="font-weight:600;font-size:12.5px"><?= htmlspecialchars($n['title']) ?></span>
          <span style="font-size:10px;color:var(--text-3);white-space:nowrap"><?= timeAgo($n['created_at']) ?></span>
        </div>
        <div style="font-size:12px;color:var(--text-2);margin-top:2px"><?= htmlspecialchars($n['message']) ?></div>
        <div style="font-size:11px;color:var(--text-3);margin-top:3px">
          To: <strong><?= htmlspecialchars($userMap[$n['user_id']] ?? 'Unknown') ?></strong>
          &middot; <?= $n['type'] ?>
          &middot; <?= $n['read'] ? 'Read' : '<span style="color:var(--orange)">Unread</span>' ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>