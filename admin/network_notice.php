<?php
require_once __DIR__ . '/../includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enabled = isset($_POST['enabled']) ? true : false;
    $text = trim($_POST['text'] ?? '');
    writeJson(NETWORK_FILE, ['enabled' => $enabled, 'text' => $text]);
    flash('Network notice updated', 'success');
    safeRedirect('/admin/network_notice.php');
}

$notice = getNetworkNotice();
?>

<div class="page-header">
    <div>
        <div class="page-title">Network Notice</div>
        <div class="page-subtitle">Banner shown at the top of user panel pages (for alternative payment methods, announcements, etc.)</div>
    </div>
</div>

<div class="card">
    <form method="POST">
        <div class="form-group">
            <label class="form-label">
                <input type="checkbox" name="enabled" value="1" <?= !empty($notice['enabled']) ? 'checked' : '' ?> style="margin-right: 8px;">
                Enable Notice Banner
            </label>
            <div class="form-hint">When enabled, the notice will appear at the top of every user panel page</div>
        </div>
        <div class="form-group">
            <label class="form-label">Notice Text</label>
            <textarea name="text" class="form-control" rows="4" placeholder="e.g. Now accepting Amazon Gift Cards, CashApp, Zelle..."><?= htmlspecialchars($notice['text'] ?? '') ?></textarea>
            <div class="form-hint">You can mention alternative payment methods like Amazon, CashApp, Zelle, etc.</div>
        </div>
        <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            Save Notice
        </button>
    </form>
</div>

<?php if (!empty($notice['text'])): ?>
<div class="card">
    <div class="card-title" style="margin-bottom: 16px;">Preview</div>
    <div class="network-ticker">
        <svg class="ticker-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
        <div class="ticker-text"><?= htmlspecialchars($notice['text']) ?></div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
