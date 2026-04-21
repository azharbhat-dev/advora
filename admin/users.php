<?php
require_once __DIR__ . '/../includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $users = readJson(USERS_FILE);

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $balance = (float)($_POST['balance'] ?? 0);
        $email = trim($_POST['email'] ?? '');

        if (!$username || strlen($password) < 6) {
            flash('Username required, password min 6 chars', 'error');
        } else {
            $exists = false;
            foreach ($users as $u) if ($u['username'] === $username) { $exists = true; break; }
            if ($exists) {
                flash('Username already exists', 'error');
            } else {
                $users[] = [
                    'id' => 'USR-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8)),
                    'username' => $username,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'email' => $email,
                    'balance' => $balance,
                    'disabled' => false,
                    'created_at' => time()
                ];
                writeJson(USERS_FILE, $users);
                flash('User created successfully', 'success');
            }
        }
    } elseif ($action === 'update_balance') {
        $userId = $_POST['user_id'] ?? '';
        $newBal = (float)($_POST['balance'] ?? 0);
        foreach ($users as &$u) {
            if ($u['id'] === $userId) { $u['balance'] = $newBal; break; }
        }
        writeJson(USERS_FILE, $users);
        flash('Balance updated', 'success');
    } elseif ($action === 'reset_password') {
        $userId = $_POST['user_id'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        if (strlen($newPass) < 6) {
            flash('Password must be at least 6 chars', 'error');
        } else {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) { $u['password'] = password_hash($newPass, PASSWORD_DEFAULT); break; }
            }
            writeJson(USERS_FILE, $users);
            flash('Password reset', 'success');
        }
    } elseif ($action === 'toggle_disabled') {
        $userId = $_POST['user_id'] ?? '';
        foreach ($users as &$u) {
            if ($u['id'] === $userId) { $u['disabled'] = !($u['disabled'] ?? false); break; }
        }
        writeJson(USERS_FILE, $users);
        flash('User status updated', 'success');
    } elseif ($action === 'delete') {
        $userId = $_POST['user_id'] ?? '';
        $users = array_values(array_filter($users, fn($u) => $u['id'] !== $userId));
        writeJson(USERS_FILE, $users);
        flash('User deleted', 'success');
    }
    safeRedirect('/admin/users.php');
}

$users = readJson(USERS_FILE);
$campaigns = readJson(CAMPAIGNS_FILE);
$showNew = ($_GET['action'] ?? '') === 'new';
?>

<div class="page-header">
    <div>
        <div class="page-title">Users Management</div>
        <div class="page-subtitle">Create and manage user accounts</div>
    </div>
    <button class="btn btn-primary" onclick="openModal('newUserModal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        Create User
    </button>
</div>

<div class="card">
    <?php if (empty($users)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <h3>No users yet</h3>
        <p>Create the first user account to get started</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Balance</th>
                    <th>Campaigns</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u):
                    $userCampCount = count(array_filter($campaigns, fn($c) => $c['user_id'] === $u['id']));
                ?>
                <tr>
                    <td><code style="color: var(--yellow); font-size: 11px;"><?= htmlspecialchars($u['id']) ?></code></td>
                    <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                    <td style="font-size: 12px; color: var(--text-2);"><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                    <td><strong style="color:var(--yellow)" data-live-money="user:<?= $u['id'] ?>:balance"><?= fmtMoney($u['balance']) ?></strong></td>
                    <td><?= $userCampCount ?></td>
                    <td>
                        <span class="badge <?= !empty($u['disabled'])?'badge-danger':'badge-success' ?>" data-live-badge="user:<?= $u['id'] ?>:status" data-current-status="<?= !empty($u['disabled'])?'disabled':'active' ?>"><?= !empty($u['disabled'])?'Disabled':'Active' ?></span>
                    </td>
                    <td style="font-size: 12px; color: var(--text-2);"><?= date('M d, Y', $u['created_at']) ?></td>
                    <td>
                        <div style="display: flex; gap: 6px;">
                            <button class="btn btn-secondary btn-sm" onclick='editBalance(<?= json_encode($u) ?>)'>Balance</button>
                            <button class="btn btn-secondary btn-sm" onclick='resetPass(<?= json_encode($u) ?>)'>Password</button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Toggle account status?')">
                                <input type="hidden" name="action" value="toggle_disabled">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm"><?= !empty($u['disabled']) ? 'Enable' : 'Disable' ?></button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete user permanently? This cannot be undone.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
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

<div class="modal <?= $showNew ? 'active' : '' ?>" id="newUserModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Create New User</div>
            <div class="modal-close" onclick="closeModal('newUserModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label class="form-label">Username *</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password *</label>
                <input type="text" name="password" class="form-control" required minlength="6">
                <div class="form-hint">Minimum 6 characters. User can change this later.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Email (optional)</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Initial Balance (USD)</label>
                <input type="number" name="balance" class="form-control" min="0" step="0.01" value="0">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('newUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="balanceModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Update Balance</div>
            <div class="modal-close" onclick="closeModal('balanceModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_balance">
            <input type="hidden" name="user_id" id="bal_user_id">
            <div class="form-group">
                <label class="form-label">User</label>
                <input type="text" id="bal_user_name" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">New Balance (USD)</label>
                <input type="number" name="balance" id="bal_amount" class="form-control" required min="0" step="0.0001">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('balanceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Balance</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="passwordModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Reset Password</div>
            <div class="modal-close" onclick="closeModal('passwordModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="pw_user_id">
            <div class="form-group">
                <label class="form-label">User</label>
                <input type="text" id="pw_user_name" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="text" name="new_password" class="form-control" required minlength="6">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('passwordModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function editBalance(user) {
    document.getElementById('bal_user_id').value = user.id;
    document.getElementById('bal_user_name').value = user.username;
    document.getElementById('bal_amount').value = user.balance;
    openModal('balanceModal');
}
function resetPass(user) {
    document.getElementById('pw_user_id').value = user.id;
    document.getElementById('pw_user_name').value = user.username;
    openModal('passwordModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
