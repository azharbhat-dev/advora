<?php
require_once __DIR__ . '/../includes/admin_header.php';

if (!_hasCampaignLimitColumn()) {
    ?>
    <div class="page-header">
      <div>
        <div class="page-title">Campaign Capacity</div>
        <div class="page-subtitle">Per-user campaign limit overrides</div>
      </div>
    </div>
    <div class="alert alert-warning">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div>
        <strong>Database migration needed.</strong>
        Run this SQL in phpMyAdmin to enable per-user campaign limits:
        <pre style="background:var(--bg-3);border:1px solid var(--border);border-radius:6px;padding:12px;margin-top:10px;overflow-x:auto;font-size:12px;color:var(--text)">ALTER TABLE `users`
  ADD COLUMN `campaign_limit` INT NOT NULL DEFAULT 3 AFTER `account_type`;</pre>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'set_limit') {
        $uid   = $_POST['user_id'] ?? '';
        $limit = max(0, (int)($_POST['campaign_limit'] ?? 0));
        if ($uid) {
            $stmt = db()->prepare('UPDATE users SET campaign_limit = ? WHERE id = ?');
            $stmt->execute([$limit, $uid]);
            flash('Campaign limit updated', 'success');
        }
    } elseif ($action === 'reset_default') {
        $uid = $_POST['user_id'] ?? '';
        if ($uid) {
            $stmt = db()->prepare('UPDATE users SET campaign_limit = ? WHERE id = ?');
            $stmt->execute([DEFAULT_CAMPAIGN_LIMIT, $uid]);
            flash('Limit reset to default (' . DEFAULT_CAMPAIGN_LIMIT . ')', 'success');
        }
    } elseif ($action === 'apply_global') {
        $newDefault = max(0, (int)($_POST['new_default'] ?? 0));
        $applyTo    = $_POST['apply_to'] ?? 'none';
        if ($applyTo === 'all') {
            $stmt = db()->prepare('UPDATE users SET campaign_limit = ?');
            $stmt->execute([$newDefault]);
            flash('Updated campaign limit for ALL users to ' . $newDefault, 'success');
        } elseif ($applyTo === 'defaults') {
            // Only update users still on the OLD default
            $stmt = db()->prepare('UPDATE users SET campaign_limit = ? WHERE campaign_limit = ?');
            $stmt->execute([$newDefault, DEFAULT_CAMPAIGN_LIMIT]);
            flash('Updated default-limit users to ' . $newDefault, 'success');
        } else {
            flash('No users updated. Pick "all" or "defaults" to apply.', 'error');
        }
    }
    safeRedirect('/admin/campaign_capacity.php');
}

// Load users with their current campaign count
$rows = db()->query(
    'SELECT u.id, u.username, u.email, u.account_type, u.campaign_limit, u.disabled,
            (SELECT COUNT(*) FROM campaigns c WHERE c.user_id = u.id) AS camp_count
     FROM users u
     ORDER BY u.username ASC'
)->fetchAll();

$totalUsers   = count($rows);
$atOrOver     = 0;
$customLimits = 0;
foreach ($rows as $r) {
    if ((int)$r['camp_count'] >= (int)$r['campaign_limit']) $atOrOver++;
    if ((int)$r['campaign_limit'] !== DEFAULT_CAMPAIGN_LIMIT) $customLimits++;
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $needle = strtolower($search);
    $rows = array_values(array_filter($rows, fn($r) =>
        strpos(strtolower($r['username']), $needle) !== false ||
        strpos(strtolower($r['email'] ?? ''), $needle) !== false
    ));
}
?>

<div class="page-header">
  <div>
    <div class="page-title">Campaign Capacity</div>
    <div class="page-subtitle">Set per-user campaign limits &middot; Default is <strong><?= DEFAULT_CAMPAIGN_LIMIT ?></strong></div>
  </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
  <div class="stat-card">
    <div class="stat-label">Total Users</div>
    <div class="stat-value"><?= $totalUsers ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">At/Over Limit</div>
    <div class="stat-value" style="color:<?= $atOrOver>0?'var(--orange)':'var(--text)' ?>"><?= $atOrOver ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Custom Limits</div>
    <div class="stat-value"><?= $customLimits ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Default Limit</div>
    <div class="stat-value" style="color:var(--yellow)"><?= DEFAULT_CAMPAIGN_LIMIT ?></div>
  </div>
</div>

<!-- Bulk action: apply a new default to many users at once -->
<div class="card">
  <div class="card-title" style="margin-bottom:14px">Bulk Update</div>
  <form method="POST" onsubmit="return confirm('Apply this campaign limit to multiple users?')">
    <input type="hidden" name="action" value="apply_global">
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">New Limit</label>
        <input type="number" name="new_default" class="form-control" min="0" value="<?= DEFAULT_CAMPAIGN_LIMIT ?>">
        <div class="form-hint">Set 0 to block all new campaigns. No upper cap.</div>
      </div>
      <div class="form-group">
        <label class="form-label">Apply To</label>
        <select name="apply_to" class="form-control">
          <option value="none">— Choose scope —</option>
          <option value="defaults">Only users still on default (<?= DEFAULT_CAMPAIGN_LIMIT ?>)</option>
          <option value="all">ALL users (overwrites custom limits)</option>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>
      Apply
    </button>
  </form>
</div>

<!-- Per-user list -->
<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <div style="font-size:14px;font-weight:600">All Users</div>
    <form method="GET" style="margin-left:auto;display:flex;gap:8px">
      <input type="text" name="q" class="form-control" placeholder="Search username or email..." value="<?= htmlspecialchars($search) ?>" style="max-width:280px">
      <button type="submit" class="btn btn-secondary btn-sm">Search</button>
      <?php if ($search !== ''): ?><a href="/admin/campaign_capacity.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
    </form>
  </div>
  <?php if (empty($rows)): ?>
  <div class="empty-state"><h3>No users found</h3></div>
  <?php else: ?>
  <div class="table-wrap" style="border:none;border-radius:0">
    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Email</th>
          <th>Type</th>
          <th>Current Campaigns</th>
          <th>Limit</th>
          <th>Usage</th>
          <th>Update Limit</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
        $count   = (int)$r['camp_count'];
        $limit   = (int)$r['campaign_limit'];
        $atLim   = $count >= $limit;
        $custom  = $limit !== DEFAULT_CAMPAIGN_LIMIT;
        $pct     = $limit > 0 ? min(100, round($count / $limit * 100)) : 100;
        $barCol  = $pct >= 100 ? 'var(--red)' : ($pct >= 75 ? 'var(--orange)' : 'var(--green)');
      ?>
      <tr style="<?= !empty($r['disabled']) ? 'opacity:.55' : '' ?>">
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="user-avatar" style="width:30px;height:30px;font-size:12px;flex-shrink:0"><?= strtoupper(substr($r['username'],0,1)) ?></div>
            <div>
              <strong><?= htmlspecialchars($r['username']) ?></strong>
              <?php if (!empty($r['disabled'])): ?> <span class="badge badge-danger" style="font-size:9px;margin-left:4px">Disabled</span><?php endif; ?>
              <div style="font-family:'Courier New',monospace;font-size:10px;color:var(--text-3)"><?= $r['id'] ?></div>
            </div>
          </div>
        </td>
        <td style="font-size:12px;color:var(--text-2)"><?= htmlspecialchars($r['email'] ?? '—') ?></td>
        <td>
          <span style="font-size:11px;text-transform:capitalize;color:var(--text-2)"><?= htmlspecialchars($r['account_type'] ?? 'rookie') ?></span>
        </td>
        <td>
          <strong style="font-size:14px;color:<?= $atLim?'var(--orange)':'var(--text)' ?>"><?= $count ?></strong>
        </td>
        <td>
          <strong style="font-size:14px;color:<?= $custom?'var(--blue)':'var(--text)' ?>"><?= $limit ?></strong>
          <?php if ($custom): ?><span class="badge badge-info" style="font-size:9px;margin-left:4px">Custom</span><?php endif; ?>
        </td>
        <td style="min-width:160px">
          <div style="display:flex;align-items:center;gap:8px">
            <div style="flex:1;height:5px;background:var(--bg-3);border-radius:5px;overflow:hidden">
              <div style="width:<?= $pct ?>%;height:100%;background:<?= $barCol ?>;border-radius:5px"></div>
            </div>
            <span style="font-size:11px;color:var(--text-2);min-width:34px;text-align:right"><?= $count ?>/<?= $limit ?></span>
          </div>
        </td>
        <td>
          <form method="POST" style="display:flex;gap:5px;align-items:center">
            <input type="hidden" name="action"  value="set_limit">
            <input type="hidden" name="user_id" value="<?= $r['id'] ?>">
            <input type="number" name="campaign_limit" class="form-control" min="0" value="<?= $limit ?>" style="width:78px;padding:6px 9px;font-size:13px">
            <button type="submit" class="btn btn-primary btn-sm">Save</button>
            <?php if ($custom): ?>
            <button type="submit" name="action" value="reset_default" formnovalidate class="btn btn-secondary btn-sm" title="Reset to default (<?= DEFAULT_CAMPAIGN_LIMIT ?>)">↺</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
