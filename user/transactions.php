<?php
require_once __DIR__ . '/../includes/user_header.php';

$topups = readJson(TOPUPS_FILE);
$userTopups = array_reverse(array_filter($topups, fn($t) => $t['user_id'] === $user['id']));
?>

<div class="page-header">
    <div>
        <div class="page-title">Transactions</div>
        <div class="page-subtitle">All your topup history</div>
    </div>
    <a href="/user/funds.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Funds
    </a>
</div>

<div class="card">
    <?php if (empty($userTopups)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        <h3>No transactions yet</h3>
        <p>Your topup history will appear here</p>
        <a href="/user/funds.php" class="btn btn-primary" style="margin-top: 16px;">Add Funds Now</a>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Network</th>
                    <th>Amount</th>
                    <th>TX Hash</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($userTopups as $t):
                    $sCls = ['pending' => 'badge-pending', 'approved' => 'badge-success', 'rejected' => 'badge-danger'][$t['status']] ?? 'badge-muted';
                ?>
                <tr>
                    <td><code style="color: var(--yellow); font-size: 12px;"><?= htmlspecialchars($t['id']) ?></code></td>
                    <td><span class="badge badge-yellow"><?= htmlspecialchars($t['network']) ?></span></td>
                    <td><strong><?= fmtMoney($t['amount']) ?></strong></td>
                    <td style="font-family: 'Courier New', monospace; font-size: 11px; color: var(--text-2);"><?= htmlspecialchars(substr($t['txid'], 0, 20)) ?>...</td>
                    <td><span class="badge <?= $sCls ?>"><?= $t['status'] ?></span></td>
                    <td style="font-size: 12px; color: var(--text-2);"><?= date('M d, Y H:i', $t['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
