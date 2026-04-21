<?php
require_once __DIR__ . '/../includes/admin_header.php';

$settings = getSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        if ($code && $name) {
            $exists = false;
            foreach ($settings['countries'] as $c) if ($c['code'] === $code) { $exists = true; break; }
            if (!$exists) {
                $settings['countries'][] = ['code' => $code, 'name' => $name];
                writeJson(SETTINGS_FILE, $settings);
                flash('Country added', 'success');
            } else {
                flash('Country code already exists', 'error');
            }
        }
    } elseif ($action === 'remove') {
        $code = $_POST['code'] ?? '';
        $settings['countries'] = array_values(array_filter($settings['countries'], fn($c) => $c['code'] !== $code));
        writeJson(SETTINGS_FILE, $settings);
        flash('Country removed', 'success');
    }
    safeRedirect('/admin/countries.php');
}
?>

<div class="page-header">
    <div>
        <div class="page-title">Countries Management</div>
        <div class="page-subtitle">Control which countries users can target for campaigns</div>
    </div>
</div>

<div class="card">
    <div class="card-title" style="margin-bottom: 20px;">Add New Country</div>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Country Code (ISO 2-letter)</label>
                <input type="text" name="code" class="form-control" required maxlength="2" placeholder="e.g. JP" style="text-transform: uppercase;">
                <div class="form-hint">Use "UK" for United Kingdom (flags will use GB)</div>
            </div>
            <div class="form-group">
                <label class="form-label">Country Name</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Japan">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Add Country</button>
    </form>
</div>

<div class="card">
    <div class="card-title" style="margin-bottom: 20px;">Available Countries (<?= count($settings['countries']) ?>)</div>
    <div class="country-grid">
        <?php foreach ($settings['countries'] as $c): ?>
        <div class="country-item" style="justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <img class="country-flag" src="https://flagcdn.com/w40/<?= strtolower($c['code'] === 'UK' ? 'gb' : $c['code']) ?>.png" alt="<?= $c['code'] ?>">
                <span class="country-code"><?= $c['code'] ?></span>
                <span class="country-name"><?= htmlspecialchars($c['name']) ?></span>
            </div>
            <form method="POST" style="display: inline;" onsubmit="return confirm('Remove <?= htmlspecialchars($c['name']) ?>?')">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="code" value="<?= $c['code'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" style="padding: 4px 8px;">&times;</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
