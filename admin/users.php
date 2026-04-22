<?php
require_once __DIR__ . '/../includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $users  = readJson(USERS_FILE);

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $balance  = (float)($_POST['balance'] ?? 0);
        $email           = trim($_POST['email']           ?? '');
        $fullName        = trim($_POST['full_name']        ?? '');
        $phone           = trim($_POST['phone']            ?? '');
        $address         = trim($_POST['address']          ?? '');
        $telegram        = trim($_POST['telegram']         ?? '');
        $businessName    = trim($_POST['business_name']    ?? '');
        $businessAddress = trim($_POST['business_address'] ?? '');
        $docStatus       = in_array($_POST['doc_status']??'', ['verified','unverified']) ? $_POST['doc_status'] : 'unverified';
        $acctType = in_array($_POST['account_type']??'', ['rookie','professional','expert']) ? $_POST['account_type'] : 'rookie';
        if (!$username || strlen($password) < 6) {
            flash('Username required, password min 6 chars', 'error');
        } else {
            $exists = false;
            foreach ($users as $u) if ($u['username'] === $username) { $exists = true; break; }
            if ($exists) {
                flash('Username already exists', 'error');
            } else {
                $users[] = [
                    'id'           => 'USR-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8)),
                    'full_name'    => trim($_POST['full_name'] ?? ''),
                    'phone'        => trim($_POST['phone'] ?? ''),
                    'telegram_id'  => trim($_POST['telegram_id'] ?? ''),
                    'username'     => $username,
                    'password'     => password_hash($password, PASSWORD_DEFAULT),
                    'email'            => $email,
                    'full_name'        => $fullName,
                    'phone'            => $phone,
                    'address'          => $address,
                    'telegram'         => $telegram,
                    'business_name'    => $businessName,
                    'business_address' => $businessAddress,
                    'doc_status'       => $docStatus,
                    'balance'          => $balance,
                    'account_type'     => $acctType,
                    'disabled'     => false,
                    'created_at'   => time()
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
    } elseif ($action === 'set_account_type') {
        $userId  = $_POST['user_id'] ?? '';
        $newType = in_array($_POST['account_type']??'', ['rookie','professional','expert']) ? $_POST['account_type'] : 'rookie';
        foreach ($users as &$u) {
            if ($u['id'] === $userId) { $u['account_type'] = $newType; break; }
        }
        writeJson(USERS_FILE, $users);
        flash('Account type updated', 'success');
    } elseif ($action === 'reset_password') {
        $userId  = $_POST['user_id'] ?? '';
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
    } elseif ($action === 'toggle_doc_status') {
        $userId = $_POST['user_id'] ?? '';
        foreach ($users as &$u) {
            if ($u['id'] === $userId) {
                $u['doc_status'] = ($u['doc_status'] ?? 'unverified') === 'verified' ? 'unverified' : 'verified';
                break;
            }
        }
        writeJson(USERS_FILE, $users);
        flash('Document status updated', 'success');
    } elseif ($action === 'set_doc_verified') {
        $userId   = $_POST['user_id'] ?? '';
        $verified = ($_POST['doc_verified'] ?? '0') === '1';
        foreach ($users as &$u) {
            if ($u['id'] === $userId) { $u['doc_verified'] = $verified; break; }
        }
        writeJson(USERS_FILE, $users);
        flash('Document status updated', 'success');
    } elseif ($action === 'toggle_disabled') {
        $userId = $_POST['user_id'] ?? '';
        foreach ($users as &$u) {
            if ($u['id'] === $userId) { $u['disabled'] = !($u['disabled'] ?? false); break; }
        }
        writeJson(USERS_FILE, $users);
        flash('User status updated', 'success');
    } elseif ($action === 'delete') {
        $userId = $_POST['user_id'] ?? '';
        $users  = array_values(array_filter($users, fn($u) => $u['id'] !== $userId));
        writeJson(USERS_FILE, $users);
        flash('User deleted', 'success');
    }
    safeRedirect('/admin/users.php');
}

$users     = readJson(USERS_FILE);
$campaigns = readJson(CAMPAIGNS_FILE);
$showNew   = ($_GET['action'] ?? '') === 'new';

$acctColors = [
    'rookie'       => ['color'=>'#8888a8','bg'=>'rgba(136,136,168,0.12)','label'=>'Rookie'],
    'professional' => ['color'=>'#4d9eff','bg'=>'rgba(77,158,255,0.12)','label'=>'Professional'],
    'expert'       => ['color'=>'#ffc800','bg'=>'rgba(255,200,0,0.12)','label'=>'Expert'],
];
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
                    <th>Account Type</th>
                    <th>Campaigns</th>
                    <th>Doc</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u):
                    $userCampCount = count(array_filter($campaigns, fn($c) => $c['user_id'] === $u['id']));
                    $acctType = $u['account_type'] ?? 'rookie';
                    $ac = $acctColors[$acctType] ?? $acctColors['rookie'];
                ?>
                <tr>
                    <td><code style="color:var(--yellow);font-size:11px"><?= htmlspecialchars($u['id']) ?></code></td>
                    <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                    <td style="font-size:12px;color:var(--text-2)">
                        <div><?= htmlspecialchars($u['email'] ?? '—') ?></div>
                        <?php if (!empty($u['phone'])): ?><div style="color:var(--text-3)"><?= htmlspecialchars($u['phone']) ?></div><?php endif; ?>
                        <?php if (!empty($u['telegram_id'])): ?><div style="color:var(--blue)">@<?= htmlspecialchars(ltrim($u['telegram_id'],'@')) ?></div><?php endif; ?>
                    </td>
                    <td><strong style="color:var(--yellow)" data-live-money="user:<?= $u['id'] ?>:balance"><?= fmtMoney($u['balance']) ?></strong></td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $ac['bg'] ?>;border:1px solid <?= $ac['color'] ?>33;padding:3px 9px;border-radius:20px;font-size:11.5px;font-weight:700;color:<?= $ac['color'] ?>">
                            <?= $ac['label'] ?>
                        </span>
                    </td>
                    <td><?= $userCampCount ?></td>
                    <td>
                        <?php if (!empty($u['doc_verified'])): ?>
                        <span class="badge badge-success">Verified</span>
                        <?php else: ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="set_doc_verified">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="doc_verified" value="1">
                            <button type="submit" class="btn btn-secondary btn-sm" style="font-size:11px">Unverified — Verify</button>
                        </form>
                        <?php endif; ?>
                        <?php if (!empty($u['doc_verified'])): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="set_doc_verified">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="doc_verified" value="0">
                            <button type="submit" class="btn btn-danger btn-sm" style="font-size:11px">Revoke</button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $ds = $u['doc_status'] ?? 'unverified'; ?>
                        <span class="badge <?= $ds==='verified'?'badge-success':'badge-pending' ?>"><?= $ds ?></span>
                    </td>
                    <td>
                        <span class="badge <?= !empty($u['disabled'])?'badge-danger':'badge-success' ?>"
                          data-live-badge="user:<?= $u['id'] ?>:status"
                          data-current-status="<?= !empty($u['disabled'])?'disabled':'active' ?>">
                          <?= !empty($u['disabled'])?'Disabled':'Active' ?>
                        </span>
                    </td>
                    <td style="font-size:12px;color:var(--text-2)"><?= date('M d, Y', $u['created_at']) ?></td>
                    <td>
                        <div style="display:flex;gap:5px;flex-wrap:wrap">
                            <button class="btn btn-secondary btn-sm" onclick='editBalance(<?= json_encode($u) ?>)'>Balance</button>
                            <button class="btn btn-secondary btn-sm" onclick='setAcctType(<?= json_encode($u) ?>)'>Account Type</button>
                            <button class="btn btn-secondary btn-sm" onclick='resetPass(<?= json_encode($u) ?>)'>Password</button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Toggle document verification?')">
                                <input type="hidden" name="action"  value="toggle_doc_status">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm"><?= ($u['doc_status']??'unverified')==='verified' ? 'Unverify Doc' : 'Verify Doc' ?></button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Toggle account status?')">
                                <input type="hidden" name="action"  value="toggle_disabled">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm"><?= !empty($u['disabled']) ? 'Enable' : 'Disable' ?></button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete user permanently?')">
                                <input type="hidden" name="action"  value="delete">
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

<!-- Create User Modal -->
<div class="modal <?= $showNew?'active':'' ?>" id="newUserModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Create New User</div>
            <div class="modal-close" onclick="closeModal('newUserModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="John Doe">
                </div>
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password *</label>
                <input type="text" name="password" class="form-control" required minlength="6">
                <div class="form-hint">Minimum 6 characters.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" placeholder="John Doe">
              </div>
              <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control" placeholder="+1 555 000 0000">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Address</label>
              <input type="text" name="address" class="form-control" placeholder="123 Main St, City, Country">
            </div>
            <div class="form-group">
              <label class="form-label">Telegram ID</label>
              <input type="text" name="telegram" class="form-control" placeholder="@username">
            </div>
            <div style="border-top:1px solid var(--border);padding-top:16px;margin-top:4px">
              <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-3);font-weight:700;margin-bottom:14px">Business Information</div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Business Name</label>
                  <input type="text" name="business_name" class="form-control" placeholder="Acme Corp">
                </div>
                <div class="form-group">
                  <label class="form-label">Business Address</label>
                  <input type="text" name="business_address" class="form-control" placeholder="456 Corp Ave">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Business Document</label>
                <select name="doc_status" class="form-control">
                  <option value="unverified">Unverified</option>
                  <option value="verified">Verified</option>
                </select>
              </div>
            </div>
            <div class="form-group">
                <label class="form-label">Account Type</label>
                <select name="account_type" class="form-control">
                    <option value="rookie">Rookie</option>
                    <option value="professional">Professional</option>
                    <option value="expert">Expert</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Initial Balance (USD)</label>
                <input type="number" name="balance" class="form-control" min="0" step="0.01" value="0">
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('newUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Balance Modal -->
<div class="modal" id="balanceModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Update Balance</div>
            <div class="modal-close" onclick="closeModal('balanceModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action"  value="update_balance">
            <input type="hidden" name="user_id" id="bal_user_id">
            <div class="form-group">
                <label class="form-label">User</label>
                <input type="text" id="bal_user_name" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">New Balance (USD)</label>
                <input type="number" name="balance" id="bal_amount" class="form-control" required min="0" step="0.0001">
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('balanceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Balance</button>
            </div>
        </form>
    </div>
</div>

<!-- Account Type Modal -->
<div class="modal" id="acctTypeModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Set Account Type</div>
            <div class="modal-close" onclick="closeModal('acctTypeModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action"  value="set_account_type">
            <input type="hidden" name="user_id" id="at_user_id">
            <div class="form-group">
                <label class="form-label">User</label>
                <input type="text" id="at_user_name" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Account Type</label>
                <div class="radio-group">
                    <label class="radio-option" id="at_rookie">
                        <input type="radio" name="account_type" value="rookie">
                        <div class="opt-label" style="color:#8888a8">Rookie</div>
                        <div class="opt-desc">Entry level account</div>
                    </label>
                    <label class="radio-option" id="at_professional">
                        <input type="radio" name="account_type" value="professional">
                        <div class="opt-label" style="color:#4d9eff">Professional</div>
                        <div class="opt-desc">Experienced advertiser</div>
                    </label>
                    <label class="radio-option" id="at_expert">
                        <input type="radio" name="account_type" value="expert">
                        <div class="opt-label" style="color:#ffc800">Expert</div>
                        <div class="opt-desc">Top tier account</div>
                    </label>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('acctTypeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Password Modal -->
<div class="modal" id="passwordModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Reset Password</div>
            <div class="modal-close" onclick="closeModal('passwordModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action"  value="reset_password">
            <input type="hidden" name="user_id" id="pw_user_id">
            <div class="form-group">
                <label class="form-label">User</label>
                <input type="text" id="pw_user_name" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="text" name="new_password" class="form-control" required minlength="6">
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('passwordModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function editBalance(user) {
    document.getElementById('bal_user_id').value   = user.id;
    document.getElementById('bal_user_name').value = user.username;
    document.getElementById('bal_amount').value    = user.balance;
    openModal('balanceModal');
}
function setAcctType(user) {
    document.getElementById('at_user_id').value   = user.id;
    document.getElementById('at_user_name').value = user.username;
    const cur = user.account_type || 'rookie';
    document.querySelectorAll('#acctTypeModal .radio-option').forEach(o => o.classList.remove('selected'));
    const el = document.getElementById('at_' + cur);
    if (el) { el.classList.add('selected'); el.querySelector('input').checked = true; }
    document.querySelectorAll('#acctTypeModal .radio-option').forEach(opt => {
        opt.onclick = () => {
            document.querySelectorAll('#acctTypeModal .radio-option').forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
            opt.querySelector('input').checked = true;
        };
    });
    openModal('acctTypeModal');
}
function resetPass(user) {
    document.getElementById('pw_user_id').value   = user.id;
    document.getElementById('pw_user_name').value = user.username;
    openModal('passwordModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>