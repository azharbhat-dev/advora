<?php
require_once __DIR__ . '/../includes/user_header.php';

$settings = getSettings();
$wallets = $settings['wallets'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $network = $_POST['network'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $txid = trim($_POST['txid'] ?? '');

    if (!$network || !isset($wallets[$network])) {
        flash('Invalid network selected', 'error');
    } elseif ($amount < 10) {
        flash('Minimum topup is $10', 'error');
    } elseif (!$txid) {
        flash('Transaction ID is required', 'error');
    } else {
        $topups = readJson(TOPUPS_FILE);
        $topups[] = [
            'id' => 'TX-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8)),
            'user_id' => $user['id'],
            'username' => $user['username'],
            'network' => $network,
            'address' => $wallets[$network]['address'],
            'amount' => $amount,
            'txid' => $txid,
            'status' => 'pending',
            'created_at' => time()
        ];
        writeJson(TOPUPS_FILE, $topups);
        flash('Topup request submitted. Admin will review and credit your account.', 'success');
        safeRedirect('/user/transactions.php');
    }
}
?>

<div class="page-header">
    <div>
        <div class="page-title">Add Funds</div>
        <div class="page-subtitle">Top up your account via cryptocurrency</div>
    </div>
</div>

<div class="alert alert-warning">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Please send the EXACT amount to the provided address, then submit your transaction ID below. Admin approval required before funds appear in your balance.
</div>

<div class="card">
    <div class="card-title" style="margin-bottom: 20px;">Step 1: Select Network</div>
    <div class="radio-group" id="networkGroup">
        <?php $first = true; foreach ($wallets as $code => $w): ?>
        <label class="radio-option <?= $first ? 'selected' : '' ?>" data-network="<?= $code ?>">
            <input type="radio" name="network_select" value="<?= $code ?>" <?= $first ? 'checked' : '' ?>>
            <div class="opt-label"><?= htmlspecialchars($code) ?></div>
            <div class="opt-desc"><?= htmlspecialchars($w['network']) ?></div>
        </label>
        <?php $first = false; endforeach; ?>
    </div>
</div>

<div class="card" id="walletDisplay">
    <div class="card-title" style="margin-bottom: 20px;">Step 2: Send Payment</div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: center;" class="pay-grid">
        <style>@media (max-width: 700px) { .pay-grid { grid-template-columns: 1fr !important; } }</style>
        <div class="wallet-card">
            <div style="font-size: 12px; color: var(--text-2); text-transform: uppercase; letter-spacing: 1px;">Scan QR Code</div>
            <div class="wallet-qr" id="qrContainer"></div>
            <div style="font-size: 11px; color: var(--text-3);">Scan with your wallet app</div>
        </div>
        <div>
            <div style="font-size: 12px; color: var(--text-2); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Network</div>
            <div id="networkName" style="font-size: 16px; font-weight: 600; margin-bottom: 16px;"></div>

            <div style="font-size: 12px; color: var(--text-2); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Address</div>
            <div class="wallet-addr" id="walletAddr"></div>
            <button class="copy-btn" onclick="copyText(document.getElementById('walletAddr').textContent, this)" style="margin-bottom: 16px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                Copy Address
            </button>

            <div class="alert alert-info" style="margin-top: 16px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span style="font-size: 12px;">Send only <strong id="sendOnly"></strong> to this address. Sending other assets may result in permanent loss.</span>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title" style="margin-bottom: 20px;">Step 3: Submit Transaction</div>
    <form method="POST">
        <input type="hidden" name="network" id="selectedNetwork" value="<?= htmlspecialchars(array_key_first($wallets)) ?>">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Amount Paid (USD) *</label>
                <input type="number" name="amount" class="form-control" required min="10" step="0.01" placeholder="50.00">
                <div class="form-hint">Enter the USD value you sent. Minimum $10.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Transaction ID / Hash *</label>
                <input type="text" name="txid" class="form-control" required placeholder="0x..." style="font-family: 'Courier New', monospace;">
                <div class="form-hint">The TX hash from your wallet</div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            Submit Topup Request
        </button>
    </form>
</div>

<script>
const wallets = <?= json_encode($wallets) ?>;

function updateWallet(code) {
    const w = wallets[code];
    if (!w) return;
    document.getElementById('selectedNetwork').value = code;
    document.getElementById('networkName').textContent = w.network;
    document.getElementById('walletAddr').textContent = w.address;
    document.getElementById('sendOnly').textContent = code;
    genQR(document.getElementById('qrContainer'), w.address);
}

document.querySelectorAll('#networkGroup .radio-option').forEach(opt => {
    opt.addEventListener('click', () => {
        document.querySelectorAll('#networkGroup .radio-option').forEach(o => o.classList.remove('selected'));
        opt.classList.add('selected');
        opt.querySelector('input').checked = true;
        updateWallet(opt.dataset.network);
    });
});

updateWallet('<?= array_key_first($wallets) ?>');
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
