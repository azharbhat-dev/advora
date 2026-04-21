<?php
require_once __DIR__ . '/../includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tid = $_POST['topup_id'] ?? '';
    $topups = readJson(TOPUPS_FILE);

    foreach ($topups as &$t) {
        if ($t['id'] === $tid) {
            if ($action === 'approve' && $t['status'] === 'pending') {
                $users = readJson(USERS_FILE);
                foreach ($users as &$u) {
                    if ($u['id'] === $t['user_id']) {
                        $u['balance'] = (float)$u['balance'] + (float)$t['amount'];
                        break;
                    }
                }
                writeJson(USERS_FILE, $users);
                $t['status'] = 'approved';
                $t['approved_at'] = time();
                flash('Topup approved. $' . number_format($t['amount'], 2) . ' credited to ' . $t['username'], 'success');
            } elseif ($action === 'reject') {
                $t['status'] = 'rejected';
                flash('Topup rejected', 'success');
            } elseif ($action === 'delete') {
                $topups = array_values(array_filter($topups, fn($x) => $x['id'] !== $tid));
                writeJson(TOPUPS_FILE, $topups);
                flash('Topup deleted', 'success');
                safeRedirect('/admin/topups.php');
            }
            break;
        }
    }
    writeJson(TOPUPS_FILE, $topups);
    safeRedirect('/admin/topups.php');
}

$topups = readJson(TOPUPS_FILE);
$filter = $_GET['filter'] ?? 'all';
if ($filter !== 'all') {
    $topups = array_filter($topups, fn($t) => $t['status'] === $filter);
}
$topups = array_reverse($topups);
?>

<div class="page-header">
    <div>
        <div class="page-title">Topup Requests</div>
        <div class="page-subtitle">Review and approve user deposits</div>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <select class="form-control" onchange="location.href='?filter='+this.value">
            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
            <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="approved" <?= $filter === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="rejected" <?= $filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
    </div>

    <?php if (empty($topups)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"/></svg>
        <h3>No topup requests</h3>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Network</th>
                    <th>Amount</th>
                    <th>Address</th>
                    <th>TX Hash</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topups as $t):
                    $sCls = ['pending' => 'badge-pending', 'approved' => 'badge-success', 'rejected' => 'badge-danger'][$t['status']] ?? 'badge-muted';
                ?>
                <tr>
                    <td><code style="color: var(--yellow); font-size: 11px;"><?= $t['id'] ?></code></td>
                    <td><strong><?= htmlspecialchars($t['username']) ?></strong></td>
                    <td><span class="badge badge-yellow"><?= htmlspecialchars($t['network']) ?></span></td>
                    <td><strong><?= fmtMoney($t['amount']) ?></strong></td>
                    <td style="font-family: 'Courier New', monospace; font-size: 10px; color: var(--text-3);" title="<?= htmlspecialchars($t['address']) ?>">
                        <?= htmlspecialchars(substr($t['address'], 0, 12)) ?>...
                    </td>
                    <td style="font-family: 'Courier New', monospace; font-size: 10px; color: var(--text-2);" title="<?= htmlspecialchars($t['txid']) ?>">
                        <?= htmlspecialchars(substr($t['txid'], 0, 16)) ?>...
                        <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($t['txid']) ?>', this)">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                    </td>
                    <td><span class="badge <?= $sCls ?>" data-live-badge="topup:<?= $t['id'] ?>:status" data-current-status="<?= $t['status'] ?>"><?= $t['status'] ?></span></td>
                    <td style="font-size: 12px; color: var(--text-2);"><?= date('M d H:i', $t['created_at']) ?></td>
                    <td>
                        <div style="display: flex; gap: 4px;">
                            <?php if ($t['status'] === 'pending'): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Approve and credit $<?= number_format($t['amount'], 2) ?> to <?= htmlspecialchars($t['username']) ?>?')">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="topup_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm">Approve</button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="topup_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete record?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="topup_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Del</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
