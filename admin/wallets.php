<?php
require_once __DIR__ . '/../includes/admin_header.php';

$settings = getSettings();

// Define all wallet slots — these always appear even if not yet saved
$walletDefs = [
    'TRC20' => [
        'label'       => 'USDT — TRC20',
        'network'     => 'Tron TRC20',
        'coin'        => 'USDT',
        'color'       => '#26A17B',
        'placeholder' => 'TXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'hint'        => 'Tron TRC20 wallet address (34 chars, starts with T)',
    ],
    'ERC20' => [
        'label'       => 'USDT — ERC20',
        'network'     => 'Ethereum ERC20',
        'coin'        => 'USDT',
        'color'       => '#627EEA',
        'placeholder' => '0xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'hint'        => 'Ethereum ERC20 wallet address (0x… format)',
    ],
    'BEP20' => [
        'label'       => 'USDT — BEP20',
        'network'     => 'BNB Smart Chain BEP20',
        'coin'        => 'USDT',
        'color'       => '#F0B90B',
        'placeholder' => '0xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'hint'        => 'BSC BEP20 wallet address (0x… format)',
    ],
    'BTC' => [
        'label'       => 'Bitcoin',
        'network'     => 'Bitcoin Network',
        'coin'        => 'BTC',
        'color'       => '#F7931A',
        'placeholder' => 'bc1qxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'hint'        => 'Native SegWit (bc1q…) or Legacy Bitcoin address',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = getSettings();

    // Ensure wallets key exists
    if (!isset($settings['wallets'])) $settings['wallets'] = [];

    $updated = 0;
    foreach ($walletDefs as $code => $def) {
        $addr = trim($_POST['address_' . $code] ?? '');
        $net  = trim($_POST['network_' . $code] ?? $def['network']);

        // Only save if address provided
        if ($addr !== '') {
            $settings['wallets'][$code] = [
                'address' => $addr,
                'network' => $net,
            ];
            $updated++;
        }
    }

    writeJson(SETTINGS_FILE, $settings);
    flash('Wallet settings saved (' . $updated . ' address' . ($updated !== 1 ? 'es' : '') . ' updated)', 'success');
    safeRedirect('/admin/wallets.php');
}

// Reload after possible redirect
$settings = getSettings();
?>

<div class="page-header">
    <div>
        <div class="page-title">Wallet Settings</div>
        <div class="page-subtitle">Set the deposit addresses shown to users on the Add Funds page</div>
    </div>
</div>

<div class="alert alert-info" style="font-size:13px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    These are the crypto addresses users will send funds to. Double-check every address before saving — incorrect addresses cause permanent loss of funds.
</div>

<form method="POST">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px" class="wallet-grid">
    <style>@media(max-width:900px){.wallet-grid{grid-template-columns:1fr!important}}</style>

    <?php foreach ($walletDefs as $code => $def):
        $saved   = $settings['wallets'][$code] ?? [];
        $addr    = $saved['address'] ?? '';
        $net     = $saved['network'] ?? $def['network'];
        $isSet   = $addr !== '' && !str_contains($addr, 'xxx');
        $preview = $isSet ? substr($addr, 0, 12) . '...' . substr($addr, -8) : null;
    ?>
    <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;transition:border-color .2s"
         onmouseover="this.style.borderColor='rgba(255,200,0,.18)'" onmouseout="this.style.borderColor='var(--border)'">

        <!-- Card header -->
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:14px;background:var(--bg-3)">
            <!-- Coin logo -->
            <div style="width:44px;height:44px;border-radius:50%;background:<?= $def['color'] ?>22;border:2px solid <?= $def['color'] ?>44;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <?php if ($def['coin'] === 'USDT'): ?>
                <svg width="22" height="22" viewBox="0 0 36 36" fill="none">
                    <circle cx="18" cy="18" r="18" fill="<?= $def['color'] ?>"/>
                    <path d="M20.3 16.6v-2.6h5.2v-3.1H10.5V14h5.2v2.6C11.5 17 8.1 18 8.1 19.2s3.4 2.2 7.6 2.5v8.7h4.5v-8.7c4.3-.3 7.6-1.3 7.6-2.5s-3.3-2.2-7.5-2.6z" fill="white"/>
                </svg>
                <?php else: ?>
                <svg width="22" height="22" viewBox="0 0 36 36" fill="none">
                    <circle cx="18" cy="18" r="18" fill="<?= $def['color'] ?>"/>
                    <path d="M25.3 15.7c.3-2.3-1.4-3.6-3.9-4.4l.8-3.1-1.9-.5-.8 3c-.5-.1-1-.2-1.5-.4l.8-3-1.9-.5-.8 3.1c-.4-.1-.8-.2-1.2-.3l-2.6-.7-.5 2s1.4.3 1.3.3c.7.2.9.7.9 1.1l-2.1 8.6c-.1.3-.4.6-.9.5l-1.3-.3-.9 2.2 2.4.6 1.3.3-.8 3.1 1.9.5.8-3.1 1.5.4-.8 3 1.9.5.8-3.1c3.2.6 5.6-.2 6.6-3.2.8-2.3-.1-3.6-1.7-4.4 1.2-.3 2.1-1.1 2.4-2.8z" fill="white"/>
                </svg>
                <?php endif; ?>
            </div>
            <div style="flex:1">
                <div style="font-size:15px;font-weight:700"><?= $def['label'] ?></div>
                <div style="font-size:12px;color:var(--text-2);margin-top:1px"><?= $def['network'] ?></div>
            </div>
            <!-- Status badge -->
            <?php if ($isSet): ?>
            <span class="badge badge-success" style="flex-shrink:0">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                Set
            </span>
            <?php else: ?>
            <span class="badge badge-pending" style="flex-shrink:0">Not Set</span>
            <?php endif; ?>
        </div>

        <!-- Card body -->
        <div style="padding:18px 20px">

            <?php if ($isSet): ?>
            <!-- Current address preview -->
            <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:var(--r-sm);padding:10px 14px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:10px">
                <div>
                    <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px">Current Address</div>
                    <div style="font-family:'Courier New',monospace;font-size:12px;color:var(--green);word-break:break-all"><?= htmlspecialchars($addr) ?></div>
                </div>
                <button type="button" class="copy-btn" onclick="copyText('<?= htmlspecialchars(addslashes($addr)) ?>', this)" style="flex-shrink:0">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Copy
                </button>
            </div>
            <?php endif; ?>

            <div class="form-group" style="margin-bottom:12px">
                <label class="form-label" style="font-size:12px"><?= $isSet ? 'Update Address' : 'Deposit Address' ?></label>
                <input type="text"
                    name="address_<?= $code ?>"
                    class="form-control"
                    value="<?= $isSet ? htmlspecialchars($addr) : '' ?>"
                    placeholder="<?= htmlspecialchars($def['placeholder']) ?>"
                    style="font-family:'Courier New',monospace;font-size:12px"
                    autocomplete="off"
                    spellcheck="false">
                <div class="form-hint"><?= $def['hint'] ?></div>
            </div>

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label" style="font-size:12px">Network Label</label>
                <input type="text"
                    name="network_<?= $code ?>"
                    class="form-control"
                    value="<?= htmlspecialchars($net) ?>"
                    placeholder="<?= htmlspecialchars($def['network']) ?>">
                <div class="form-hint">Label shown to users (e.g. "Tron TRC20")</div>
            </div>

        </div>
    </div>
    <?php endforeach; ?>

    </div>

    <div style="margin-top:20px;display:flex;gap:12px;align-items:center">
        <button type="submit" class="btn btn-primary" style="padding:11px 28px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15"><polyline points="20 6 9 17 4 12"/></svg>
            Save All Wallets
        </button>
        <span style="font-size:12px;color:var(--text-3)">All 4 networks will be updated</span>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>