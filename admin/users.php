<?php
require_once __DIR__ . '/../includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $balance  = (float)($_POST['balance'] ?? 0);
        $acctType = in_array($_POST['account_type']??'', ['rookie','professional','expert']) ? $_POST['account_type'] : 'rookie';

        if (!$username || strlen($password) < 6) {
            flash('Username required, password min 6 chars', 'error');
        } else {
            // Check uniqueness directly
            $stmt = db()->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                flash('Username already exists', 'error');
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO users
                     (id,username,password,email,full_name,phone,address,telegram_id,business_name,business_address,doc_verified,balance,account_type,disabled,created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,0,?,?,0,?)'
                );
                $stmt->execute([
                    'USR-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8)),
                    $username,
                    password_hash($password, PASSWORD_DEFAULT),
                    trim($_POST['email']            ?? ''),
                    trim($_POST['full_name']        ?? ''),
                    trim($_POST['phone']            ?? ''),
                    trim($_POST['address']          ?? ''),
                    ltrim(trim($_POST['telegram_id'] ?? ''), '@'),
                    trim($_POST['business_name']    ?? ''),
                    trim($_POST['business_address'] ?? ''),
                    $balance,
                    $acctType,
                    time(),
                ]);
                flash('User created', 'success');
            }
        }

    } elseif ($action === 'update_details') {
        $uid = $_POST['user_id'] ?? '';
        if (!$uid) {
            flash('Missing user id', 'error');
        } else {
            $stmt = db()->prepare(
                'UPDATE users SET
                   full_name=?, email=?, phone=?, address=?, telegram_id=?,
                   business_name=?, business_address=?
                 WHERE id = ?'
            );
            $stmt->execute([
                trim($_POST['full_name']        ?? ''),
                trim($_POST['email']            ?? ''),
                trim($_POST['phone']            ?? ''),
                trim($_POST['address']          ?? ''),
                ltrim(trim($_POST['telegram_id'] ?? ''), '@'),
                trim($_POST['business_name']    ?? ''),
                trim($_POST['business_address'] ?? ''),
                $uid,
            ]);
            flash('User details updated', 'success');
        }

    } elseif ($action === 'update_balance') {
        $uid = $_POST['user_id'] ?? '';
        $bal = (float)($_POST['balance'] ?? 0);
        if ($uid) {
            $stmt = db()->prepare('UPDATE users SET balance = ? WHERE id = ?');
            $stmt->execute([$bal, $uid]);
            flash('Balance updated', 'success');
        }

    } elseif ($action === 'set_account_type') {
        $uid  = $_POST['user_id'] ?? '';
        $type = in_array($_POST['account_type']??'', ['rookie','professional','expert']) ? $_POST['account_type'] : 'rookie';
        if ($uid) {
            $stmt = db()->prepare('UPDATE users SET account_type = ? WHERE id = ?');
            $stmt->execute([$type, $uid]);
            flash('Account type updated', 'success');
        }

    } elseif ($action === 'reset_password') {
        $uid  = $_POST['user_id'] ?? '';
        $pass = $_POST['new_password'] ?? '';
        if (strlen($pass) < 6) {
            flash('Password must be at least 6 chars', 'error');
        } else {
            $stmt = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([password_hash($pass, PASSWORD_DEFAULT), $uid]);
            flash('Password reset', 'success');
        }

    } elseif ($action === 'set_doc_verified') {
        $uid      = $_POST['user_id'] ?? '';
        $verified = (($_POST['doc_verified'] ?? '0') === '1') ? 1 : 0;
        if ($uid) {
            $stmt = db()->prepare('UPDATE users SET doc_verified = ? WHERE id = ?');
            $stmt->execute([$verified, $uid]);
            flash($verified ? 'Document verified' : 'Document verification revoked', 'success');
        }

    } elseif ($action === 'toggle_disabled') {
        $uid = $_POST['user_id'] ?? '';
        if ($uid) {
            $stmt = db()->prepare('UPDATE users SET disabled = 1 - disabled WHERE id = ?');
            $stmt->execute([$uid]);
            flash('User status updated', 'success');
        }

    } elseif ($action === 'delete') {
        $uid = $_POST['user_id'] ?? '';
        if ($uid) {
            $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$uid]);
            flash('User deleted', 'success');
        }
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
        <div class="page-title">Users</div>
        <div class="page-subtitle">Manage user accounts and details</div>
    </div>
    <button class="btn btn-primary" onclick="openModal('newUserModal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        Create User
    </button>
</div>

<div class="card" style="padding:0;overflow:hidden">
    <?php if (empty($users)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <h3>No users yet</h3>
    </div>
    <?php else: ?>
    <div class="table-wrap" style="border:none;border-radius:0">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Contact</th>
                    <th>Balance</th>
                    <th>Type</th>
                    <th>Doc</th>
                    <th>Campaigns</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u):
                    $campCount = count(array_filter($campaigns, fn($c) => $c['user_id'] === $u['id']));
                    $at  = $u['account_type'] ?? 'rookie';
                    $ac  = $acctColors[$at] ?? $acctColors['rookie'];
                    $dis = !empty($u['disabled']);
                    $doc = !empty($u['doc_verified']);
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="user-avatar" style="width:32px;height:32px;font-size:12px;flex-shrink:0;<?= $dis?'opacity:.5':'' ?>"><?= strtoupper(substr($u['username'],0,1)) ?></div>
                            <div>
                                <div style="font-weight:600"><?= htmlspecialchars($u['username']) ?></div>
                                <code style="font-size:10px;color:var(--yellow)"><?= $u['id'] ?></code>
                                <?php if (!empty($u['full_name'])): ?>
                                <div style="font-size:11px;color:var(--text-2)"><?= htmlspecialchars($u['full_name']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12px">
                        <div style="color:var(--text-2)"><?= htmlspecialchars($u['email'] ?? '—') ?></div>
                        <?php if (!empty($u['phone'])): ?><div style="color:var(--text-3)"><?= htmlspecialchars($u['phone']) ?></div><?php endif; ?>
                        <?php if (!empty($u['telegram_id'])): ?><div style="color:var(--blue)">@<?= htmlspecialchars(ltrim($u['telegram_id'],'@')) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <strong style="color:var(--yellow)" data-live-money="user:<?= $u['id'] ?>:balance"><?= fmtMoney($u['balance']) ?></strong>
                    </td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $ac['bg'] ?>;border:1px solid <?= $ac['color'] ?>33;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;color:<?= $ac['color'] ?>"><?= $ac['label'] ?></span>
                    </td>
                    <td>
                        <?php if ($doc): ?>
                        <span class="badge badge-success" style="font-size:10px">Verified</span>
                        <?php else: ?>
                        <span class="badge badge-pending" style="font-size:10px">Unverified</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight:600"><?= $campCount ?></td>
                    <td>
                        <span class="badge <?= $dis?'badge-danger':'badge-success' ?>"><?= $dis?'Disabled':'Active' ?></span>
                    </td>
                    <td style="font-size:12px;color:var(--text-2)"><?= date('M d, Y', $u['created_at']) ?></td>
                    <td>
                        <div style="display:flex;gap:5px;flex-wrap:wrap">
                            <button type="button" class="btn btn-primary btn-sm" onclick='openEditDetails(<?= json_encode($u, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Details</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick='openEditBalance(<?= json_encode($u, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Balance</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick='openSetType(<?= json_encode($u, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Type</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick='openResetPass(<?= json_encode($u, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Password</button>

                            <?php /* Doc verify – standalone form */ ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action"       value="set_doc_verified">
                                <input type="hidden" name="user_id"      value="<?= $u['id'] ?>">
                                <input type="hidden" name="doc_verified" value="<?= $doc ? '0' : '1' ?>">
                                <button type="submit" class="btn <?= $doc ? 'btn-danger' : 'btn-success' ?> btn-sm">
                                    <?= $doc ? 'Revoke Doc' : 'Verify Doc' ?>
                                </button>
                            </form>

                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action"  value="toggle_disabled">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm"><?= $dis?'Enable':'Disable' ?></button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete user permanently?')">
                                <input type="hidden" name="action"  value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
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

<!-- ── MODALS ─────────────────────────────────────── -->

<!-- Create User -->
<div class="modal <?= $showNew?'active':'' ?>" id="newUserModal">
    <div class="modal-box" style="max-width:560px">
        <div class="modal-header">
            <div class="modal-title">Create New User</div>
            <div class="modal-close" onclick="closeModal('newUserModal')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="text" name="password" class="form-control" required minlength="6">
                    <div class="form-hint">Min 6 characters</div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="John Doe">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" placeholder="+1 555 000 0000">
                </div>
                <div class="form-group">
                    <label class="form-label">Telegram</label>
                    <div style="position:relative">
                        <span style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-2)">@</span>
                        <input type="text" name="telegram_id" class="form-control" style="padding-left:24px" placeholder="username">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" placeholder="Street, City, Country">
            </div>
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
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Account Type</label>
                    <select name="account_type" class="form-control">
                        <option value="rookie">Rookie</option>
                        <option value="professional">Professional</option>
                        <option value="expert">Expert</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Initial Balance ($)</label>
                    <input type="number" name="balance" class="form-control" min="0" step="0.01" value="0">
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('newUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Details (SINGLE form — no nesting) -->
<div class="modal" id="detailsModal">
    <div class="modal-box" style="max-width:580px">
        <div class="modal-header">
            <div class="modal-title">Edit User Details</div>
            <div class="modal-close" onclick="closeModal('detailsModal')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:7px;padding:10px 14px;margin-bottom:18px;font-size:13px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <div style="flex:1">
                <span style="color:var(--text-2)">Editing:</span>
                <strong id="det_lbl_username" style="margin-left:6px"></strong>
                <code id="det_lbl_uid" style="font-size:10px;color:var(--yellow);margin-left:8px"></code>
            </div>
            <span id="det_doc_badge" class="badge badge-pending">Unverified</span>
        </div>

        <form method="POST">
            <input type="hidden" name="action"  value="update_details">
            <input type="hidden" name="user_id" id="det_user_id">

            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-3);margin-bottom:12px">Personal</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" id="det_full_name" class="form-control" placeholder="John Doe">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="det_email" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="det_phone" class="form-control" placeholder="+1 555 000 0000">
                </div>
                <div class="form-group">
                    <label class="form-label">Telegram ID</label>
                    <div style="position:relative">
                        <span style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-2)">@</span>
                        <input type="text" name="telegram_id" id="det_telegram_id" class="form-control" style="padding-left:24px" placeholder="username">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea name="address" id="det_address" class="form-control" rows="2" placeholder="Street, City, Country"></textarea>
            </div>

            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-3);margin:4px 0 12px;padding-top:14px;border-top:1px solid var(--border)">Business</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Business Name</label>
                    <input type="text" name="business_name" id="det_business_name" class="form-control" placeholder="Acme Corp">
                </div>
                <div class="form-group">
                    <label class="form-label">Business Address</label>
                    <input type="text" name="business_address" id="det_business_address" class="form-control" placeholder="456 Corp Ave">
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:14px;border-top:1px solid var(--border)">
                <button type="button" class="btn btn-secondary" onclick="closeModal('detailsModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13"><polyline points="20 6 9 17 4 12"/></svg>
                    Save All Details
                </button>
            </div>
        </form>

        <!-- Separate doc verify form OUTSIDE details form -->
        <form method="POST" id="docVerifyForm" style="margin-top:12px;padding-top:14px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:10px">
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-3);margin-bottom:4px">Document Status</div>
                <div style="font-size:12px;color:var(--text-2)">Click below to toggle verification</div>
            </div>
            <input type="hidden" name="action"       value="set_doc_verified">
            <input type="hidden" name="user_id"      id="det_doc_uid">
            <input type="hidden" name="doc_verified" id="det_doc_value">
            <button type="submit" id="det_doc_btn" class="btn btn-success btn-sm">Verify</button>
        </form>
    </div>
</div>

<!-- Edit Balance -->
<div class="modal" id="balanceModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Update Balance</div>
            <div class="modal-close" onclick="closeModal('balanceModal')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action"  value="update_balance">
            <input type="hidden" name="user_id" id="bal_user_id">
            <div class="form-group">
                <label class="form-label">User</label>
                <input type="text" id="bal_username" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">New Balance (USD)</label>
                <input type="number" name="balance" id="bal_balance" class="form-control" required min="0" step="0.01">
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('balanceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Balance</button>
            </div>
        </form>
    </div>
</div>

<!-- Set Account Type -->
<div class="modal" id="typeModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Set Account Type</div>
            <div class="modal-close" onclick="closeModal('typeModal')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action"  value="set_account_type">
            <input type="hidden" name="user_id" id="type_user_id">
            <div class="form-group">
                <label class="form-label">User</label>
                <input type="text" id="type_username" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Account Type</label>
                <div class="radio-group">
                    <label class="radio-option" id="type_rookie">
                        <input type="radio" name="account_type" value="rookie">
                        <div class="opt-label" style="color:#8888a8">Rookie</div>
                        <div class="opt-desc">Entry level</div>
                    </label>
                    <label class="radio-option" id="type_professional">
                        <input type="radio" name="account_type" value="professional">
                        <div class="opt-label" style="color:#4d9eff">Professional</div>
                        <div class="opt-desc">Experienced</div>
                    </label>
                    <label class="radio-option" id="type_expert">
                        <input type="radio" name="account_type" value="expert">
                        <div class="opt-label" style="color:#ffc800">Expert</div>
                        <div class="opt-desc">Top tier</div>
                    </label>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('typeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password -->
<div class="modal" id="passModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Reset Password</div>
            <div class="modal-close" onclick="closeModal('passModal')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action"  value="reset_password">
            <input type="hidden" name="user_id" id="pass_user_id">
            <div class="form-group">
                <label class="form-label">User</label>
                <input type="text" id="pass_username" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="text" name="new_password" class="form-control" required minlength="6">
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('passModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Reset</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditDetails(user) {
    document.getElementById('det_lbl_username').textContent = user.username;
    document.getElementById('det_lbl_uid').textContent      = user.id;
    document.getElementById('det_user_id').value            = user.id;
    document.getElementById('det_full_name').value          = user.full_name        || '';
    document.getElementById('det_email').value              = user.email            || '';
    document.getElementById('det_phone').value              = user.phone            || '';
    document.getElementById('det_telegram_id').value        = (user.telegram_id     || '').replace(/^@/, '');
    document.getElementById('det_address').value            = user.address          || '';
    document.getElementById('det_business_name').value      = user.business_name    || '';
    document.getElementById('det_business_address').value   = user.business_address || '';
    document.getElementById('det_doc_uid').value            = user.id;

    var badge = document.getElementById('det_doc_badge');
    var btn   = document.getElementById('det_doc_btn');
    if (user.doc_verified) {
        badge.className   = 'badge badge-success';
        badge.textContent = 'Verified';
        btn.textContent   = 'Revoke Verification';
        btn.className     = 'btn btn-danger btn-sm';
        document.getElementById('det_doc_value').value = '0';
    } else {
        badge.className   = 'badge badge-pending';
        badge.textContent = 'Unverified';
        btn.textContent   = 'Verify Document';
        btn.className     = 'btn btn-success btn-sm';
        document.getElementById('det_doc_value').value = '1';
    }
    openModal('detailsModal');
}

function openEditBalance(user) {
    document.getElementById('bal_user_id').value   = user.id;
    document.getElementById('bal_username').value  = user.username;
    document.getElementById('bal_balance').value   = user.balance;
    openModal('balanceModal');
}

function openSetType(user) {
    document.getElementById('type_user_id').value  = user.id;
    document.getElementById('type_username').value = user.username;
    document.querySelectorAll('#typeModal .radio-option').forEach(function(o) { o.classList.remove('selected'); });
    var cur = user.account_type || 'rookie';
    var el  = document.getElementById('type_' + cur);
    if (el) { el.classList.add('selected'); el.querySelector('input').checked = true; }
    document.querySelectorAll('#typeModal .radio-option').forEach(function(opt) {
        opt.onclick = function() {
            document.querySelectorAll('#typeModal .radio-option').forEach(function(o) { o.classList.remove('selected'); });
            opt.classList.add('selected');
            opt.querySelector('input').checked = true;
        };
    });
    openModal('typeModal');
}

function openResetPass(user) {
    document.getElementById('pass_user_id').value  = user.id;
    document.getElementById('pass_username').value = user.username;
    openModal('passModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
