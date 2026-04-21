<?php
// Screenshot must be served BEFORE admin_header outputs any HTML
if (isset($_GET['screenshot'])) {
    require_once __DIR__ . '/../includes/config.php';
    requireAdmin();
    // config.php calls ob_start() — flush it before sending binary image
    while (ob_get_level() > 0) ob_end_clean();
    $file = basename($_GET['screenshot']);
    $path = DATA_PATH . '/topup_screenshots/' . $file;
    if (file_exists($path)) {
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'][$ext] ?? 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('X-Frame-Options: SAMEORIGIN');
        header('Cache-Control: private, max-age=3600');
        readfile($path);
    } else {
        http_response_code(404);
        echo 'Screenshot not found';
    }
    exit;
}

require_once __DIR__ . '/../includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tid    = $_POST['topup_id'] ?? '';
    $topups = readJson(TOPUPS_FILE);

    foreach ($topups as &$t) {
        if ($t['id'] === $tid) {
            if ($action === 'approve' && $t['status'] === 'pending') {
                $creditAmt = $t['amount_after_fee'] ?? $t['amount'];
                $users = readJson(USERS_FILE);
                foreach ($users as &$u) {
                    if ($u['id'] === $t['user_id']) {
                        $u['balance'] = (float)$u['balance'] + (float)$creditAmt;
                        break;
                    }
                }
                writeJson(USERS_FILE, $users);
                $t['status']      = 'approved';
                $t['approved_at'] = time();

                addNotification($t['user_id'], 'topup_approved',
                    'Deposit Approved',
                    '$' . number_format($creditAmt, 2) . ' has been added to your balance.'
                );
                flash('Topup approved. $' . number_format($creditAmt, 2) . ' credited to ' . $t['username'], 'success');

            } elseif ($action === 'reject') {
                $t['status'] = 'rejected';
                addNotification($t['user_id'], 'topup_rejected',
                    'Deposit Declined',
                    'Your deposit of $' . number_format($t['amount'], 2) . ' was not approved. Please contact support.'
                );
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
            <option value="all"      <?= $filter==='all'     ?'selected':'' ?>>All</option>
            <option value="pending"  <?= $filter==='pending' ?'selected':'' ?>>Pending</option>
            <option value="approved" <?= $filter==='approved'?'selected':'' ?>>Approved</option>
            <option value="rejected" <?= $filter==='rejected'?'selected':'' ?>>Rejected</option>
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
                    <th>Fee</th>
                    <th>Credited</th>
                    <th>TX Hash</th>
                    <th>Screenshot</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topups as $t):
                    $sCls     = ['pending'=>'badge-pending','approved'=>'badge-success','rejected'=>'badge-danger'][$t['status']] ?? 'badge-muted';
                    $fee      = $t['fee'] ?? 0;
                    $credited = $t['amount_after_fee'] ?? $t['amount'];
                ?>
                <tr>
                    <td><code style="color:var(--yellow);font-size:11px"><?= $t['id'] ?></code></td>
                    <td><strong><?= htmlspecialchars($t['username']) ?></strong></td>
                    <td><span class="badge badge-yellow"><?= htmlspecialchars($t['network']) ?></span></td>
                    <td><strong><?= fmtMoney($t['amount']) ?></strong></td>
                    <td style="font-size:12px;color:var(--orange)"><?= $fee > 0 ? '-'.fmtMoney($fee) : '—' ?></td>
                    <td style="font-size:12px;color:var(--green)"><strong><?= fmtMoney($credited) ?></strong></td>
                    <td style="font-family:'Courier New',monospace;font-size:10px;color:var(--text-2)" title="<?= htmlspecialchars($t['txid']) ?>">
                        <?= htmlspecialchars(substr($t['txid'], 0, 16)) ?>...
                        <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($t['txid']) ?>', this)">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                    </td>
                    <td>
                        <?php if (!empty($t['screenshot'])): ?>
                        <button class="btn btn-secondary btn-sm" onclick="viewScreenshot('<?= htmlspecialchars($t['screenshot']) ?>')">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            View
                        </button>
                        <?php else: ?>
                        <span style="font-size:11px;color:var(--text-3)">None</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $sCls ?>" data-live-badge="topup:<?= $t['id'] ?>:status" data-current-status="<?= $t['status'] ?>"><?= $t['status'] ?></span></td>
                    <td style="font-size:12px;color:var(--text-2)"><?= date('M d H:i', $t['created_at']) ?></td>
                    <td>
                        <div style="display:flex;gap:4px">
                            <?php if ($t['status'] === 'pending'): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Approve and credit <?= fmtMoney($credited) ?> to <?= htmlspecialchars($t['username']) ?>?')">
                                <input type="hidden" name="action"   value="approve">
                                <input type="hidden" name="topup_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm">Approve</button>
                            </form>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action"   value="reject">
                                <input type="hidden" name="topup_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete record?')">
                                <input type="hidden" name="action"   value="delete">
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

<!-- Screenshot Modal -->
<div class="modal" id="ssModal">
  <div class="modal-box" style="max-width:700px;width:95vw">
    <div class="modal-header">
      <div class="modal-title">Payment Screenshot</div>
      <div class="modal-close" onclick="closeModal('ssModal')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </div>
    </div>
    <div style="text-align:center">
      <img id="ssImg" src="" style="max-width:100%;max-height:70vh;border-radius:var(--r-sm);border:1px solid var(--border)">
    </div>
  </div>
</div>

<script>
function viewScreenshot(file) {
  document.getElementById('ssImg').src = '/admin/topups.php?screenshot=' + encodeURIComponent(file);
  openModal('ssModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>