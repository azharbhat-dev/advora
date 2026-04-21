<?php
require_once __DIR__ . '/../includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = getSettings();
    foreach ($settings['wallets'] as $code => $w) {
        $addr = trim($_POST['address_' . $code] ?? '');
        $name = trim($_POST['name_' . $code] ?? '');
        if ($addr) $settings['wallets'][$code]['address'] = $addr;
        if ($name) $settings['wallets'][$code]['network'] = $name;
    }
    writeJson(SETTINGS_FILE, $settings);
    flash('Wallet settings saved', 'success');
    safeRedirect('/admin/wallets.php');
}

$settings = getSettings();
?>

<div class="page-header">
    <div>
        <div class="page-title">Wallet Settings</div>
        <div class="page-subtitle">Configure crypto deposit addresses shown to users</div>
    </div>
</div>

<div class="card">
    <form method="POST">
        <?php foreach ($settings['wallets'] as $code => $w): ?>
        <div style="border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 16px; background: var(--bg-3);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--yellow); color: var(--bg); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px;"><?= substr($code, 0, 1) ?></div>
                <div>
                    <div style="font-size: 16px; font-weight: 700;"><?= htmlspecialchars($code) ?></div>
                    <div style="font-size: 12px; color: var(--text-2);">Network identifier</div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Network Name</label>
                <input type="text" name="name_<?= $code ?>" class="form-control" value="<?= htmlspecialchars($w['network']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Deposit Address</label>
                <input type="text" name="address_<?= $code ?>" class="form-control" value="<?= htmlspecialchars($w['address']) ?>" style="font-family: 'Courier New', monospace;">
                <div class="form-hint">This address is shown to users when they select this network for topup</div>
            </div>
        </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            Save All Wallets
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
