<?php
require_once __DIR__ . '/../includes/admin_header.php';

$settings = getSettings();
$insights = readJson(INSIGHTS_FILE, []);

$insightMap = [];
foreach ($insights as $row) $insightMap[$row['code']] = $row;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $code        = strtoupper(trim($_POST['code'] ?? ''));
        $impressions = max(0, (int)($_POST['impressions'] ?? 0));
        $win_rate    = min(100, max(0, (int)($_POST['win_rate'] ?? 0)));
        $name        = $code;
        foreach ($settings['countries'] as $c) { if ($c['code'] === $code) { $name = $c['name']; break; } }

        $found = false;
        foreach ($insights as &$row) {
            if ($row['code'] === $code) {
                $row['impressions'] = $impressions;
                $row['win_rate']    = $win_rate;
                $row['updated_at']  = time();
                $found = true; break;
            }
        }
        if (!$found) {
            $insights[] = ['code'=>$code,'name'=>$name,'impressions'=>$impressions,'win_rate'=>$win_rate,'updated_at'=>time()];
        }
        writeJson(INSIGHTS_FILE, $insights);
        flash('Updated: ' . $name, 'success');

    } elseif ($action === 'bulk_random') {
        foreach ($settings['countries'] as $country) {
            $code  = $country['code'];
            // Realistic-ish traffic numbers
            $tiers = [
                'US'=>[800000,5000000], 'UK'=>[400000,2000000], 'CA'=>[300000,1500000],
                'AU'=>[250000,1200000], 'DE'=>[350000,1800000], 'FR'=>[300000,1500000],
                'NL'=>[150000,700000],  'NZ'=>[80000,400000],   'IE'=>[90000,450000],  'SE'=>[120000,600000],
            ];
            $range = $tiers[$code] ?? [50000, 500000];
            $imp   = rand($range[0], $range[1]);
            $wr    = rand(28, 88);
            $found = false;
            foreach ($insights as &$row) {
                if ($row['code'] === $code) { $row['impressions']=$imp; $row['win_rate']=$wr; $row['updated_at']=time(); $found=true; break; }
            }
            if (!$found) $insights[] = ['code'=>$code,'name'=>$country['name'],'impressions'=>$imp,'win_rate'=>$wr,'updated_at'=>time()];
        }
        writeJson(INSIGHTS_FILE, $insights);
        flash('Random traffic data generated for all countries', 'success');

    } elseif ($action === 'delete') {
        $code     = $_POST['code'] ?? '';
        $insights = array_values(array_filter($insights, fn($r) => $r['code'] !== $code));
        writeJson(INSIGHTS_FILE, $insights);
        flash('Cleared data for ' . $code, 'success');
    }

    safeRedirect('/admin/insights.php');
}

$insights   = readJson(INSIGHTS_FILE, []);
$insightMap = [];
foreach ($insights as $row) $insightMap[$row['code']] = $row;
?>

<div class="page-header">
  <div>
    <div class="page-title">Insights Manager</div>
    <div class="page-subtitle">Set traffic inventory shown to users on the Insights page</div>
  </div>
  <form method="POST" onsubmit="return confirm('Generate random traffic for all countries?')">
    <input type="hidden" name="action" value="bulk_random">
    <button type="submit" class="btn btn-secondary">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
      Auto-generate Random Data
    </button>
  </form>
</div>

<div style="display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start" class="ins-grid">
<style>@media(max-width:900px){.ins-grid{grid-template-columns:1fr!important}}</style>

<!-- Form -->
<div class="card" style="position:sticky;top:80px">
  <div class="card-title" style="margin-bottom:18px">Edit Country Data</div>
  <form method="POST" id="insightForm">
    <input type="hidden" name="action" value="save">
    <div class="form-group">
      <label class="form-label">Country *</label>
      <select name="code" class="form-control" required id="countrySelect">
        <option value="">— Select country —</option>
        <?php foreach ($settings['countries'] as $c):
          $ex = $insightMap[$c['code']] ?? null;
        ?>
        <option value="<?= $c['code'] ?>"
                data-imp="<?= $ex['impressions'] ?? '' ?>"
                data-wr="<?= $ex['win_rate'] ?? '' ?>">
          <?= htmlspecialchars($c['name']) ?> (<?= $c['code'] ?>)<?= $ex ? ' ✓' : '' ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Total Impressions</label>
      <input type="number" name="impressions" id="f_imp" class="form-control" min="0" required placeholder="e.g. 1500000">
      <div class="form-hint" id="impPreview" style="color:var(--yellow);font-weight:600;font-size:13px"></div>
    </div>
    <div class="form-group">
      <label class="form-label">Win Rate (%)</label>
      <div style="position:relative">
        <input type="number" name="win_rate" id="f_wr" class="form-control" min="0" max="100" required placeholder="e.g. 68" oninput="updateWr()">
        <span style="position:absolute;right:13px;top:50%;transform:translateY(-50%);color:var(--text-2)">%</span>
      </div>
      <div style="margin-top:8px;height:5px;background:var(--bg-3);border-radius:5px;overflow:hidden">
        <div id="wrBar" style="height:100%;width:0%;border-radius:5px;background:var(--green);transition:width .2s,background .2s"></div>
      </div>
    </div>
    <div style="display:flex;gap:8px">
      <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>
        Save
      </button>
      <button type="button" class="btn btn-secondary" onclick="clearForm()">Clear</button>
    </div>
  </form>
</div>

<!-- Table -->
<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
    <div style="font-size:14px;font-weight:600">All Countries
      <span style="font-size:12px;font-weight:400;color:var(--text-2);margin-left:6px"><?= count($settings['countries']) ?> total</span>
    </div>
    <div style="font-size:12px;color:var(--text-3)"><?= count($insightMap) ?> with data</div>
  </div>
  <div class="table-wrap" style="border:none;border-radius:0">
    <table>
      <thead>
        <tr>
          <th style="width:36px"></th>
          <th>Country</th>
          <th>Impressions</th>
          <th>Win Rate</th>
          <th>Updated</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($settings['countries'] as $country):
          $code     = $country['code'];
          $data     = $insightMap[$code] ?? null;
          $flag     = strtolower($code === 'UK' ? 'gb' : $code);
          $wrColor  = 'var(--text-3)';
          if ($data) {
              $wr = $data['win_rate'];
              $wrColor = $wr >= 70 ? 'var(--green)' : ($wr >= 40 ? 'var(--orange)' : 'var(--red)');
          }
        ?>
        <tr>
          <td style="padding:10px 14px">
            <img src="https://flagcdn.com/w40/<?= $flag ?>.png" alt="<?= $code ?>"
                 style="width:24px;height:16px;border-radius:2px;object-fit:cover;border:1px solid var(--border);display:block">
          </td>
          <td>
            <strong><?= htmlspecialchars($country['name']) ?></strong>
            <span style="font-size:11px;color:var(--yellow);font-family:'Courier New',monospace;margin-left:6px"><?= $code ?></span>
          </td>
          <td>
            <?php if ($data): ?>
            <span style="font-size:15px;font-weight:800;color:var(--text)"><?= fmtShort($data['impressions']) ?></span>
            <span style="font-size:11px;color:var(--text-3);margin-left:4px"><?= number_format($data['impressions']) ?></span>
            <?php else: ?>
            <span style="color:var(--text-3)">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($data): ?>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:60px;height:5px;background:var(--bg-3);border-radius:5px;overflow:hidden">
                <div style="width:<?= $data['win_rate'] ?>%;height:100%;background:<?= $wrColor ?>;border-radius:5px"></div>
              </div>
              <span style="font-weight:700;color:<?= $wrColor ?>"><?= $data['win_rate'] ?>%</span>
            </div>
            <?php else: ?>
            <span style="color:var(--text-3)">—</span>
            <?php endif; ?>
          </td>
          <td style="font-size:11px;color:var(--text-3)"><?= $data ? timeAgo($data['updated_at']) : '—' ?></td>
          <td>
            <div style="display:flex;gap:5px">
              <button class="btn btn-secondary btn-sm"
                onclick="editRow('<?= $code ?>',<?= $data['impressions'] ?? 0 ?>,<?= $data['win_rate'] ?? 50 ?>)">
                Edit
              </button>
              <?php if ($data): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Clear data for <?= $code ?>?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="code"   value="<?= $code ?>">
                <button type="submit" class="btn btn-danger btn-sm">Clear</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</div>

<script>
function fmtShortJS(n) {
    n = parseInt(n) || 0;
    if (n >= 1000000000) return (n/1000000000).toFixed(1).replace(/\.0$/,'') + 'B';
    if (n >= 1000000)    return (n/1000000).toFixed(1).replace(/\.0$/,'')    + 'M';
    if (n >= 1000)       return (n/1000).toFixed(1).replace(/\.0$/,'')       + 'K';
    return n.toString();
}

document.getElementById('countrySelect').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (!opt.value) return;
    if (opt.dataset.imp) { document.getElementById('f_imp').value = opt.dataset.imp; updateImpPreview(); }
    if (opt.dataset.wr)  { document.getElementById('f_wr').value  = opt.dataset.wr;  updateWr(); }
});

document.getElementById('f_imp').addEventListener('input', updateImpPreview);

function updateImpPreview() {
    const v = parseInt(document.getElementById('f_imp').value) || 0;
    document.getElementById('impPreview').textContent = v > 0 ? '→ ' + fmtShortJS(v) : '';
}

function updateWr() {
    const v   = Math.min(100, Math.max(0, parseInt(document.getElementById('f_wr').value) || 0));
    const bar = document.getElementById('wrBar');
    bar.style.width      = v + '%';
    bar.style.background = v >= 70 ? 'var(--green)' : v >= 40 ? 'var(--orange)' : 'var(--red)';
}

function editRow(code, imp, wr) {
    const sel = document.getElementById('countrySelect');
    for (let i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === code) { sel.selectedIndex = i; break; }
    }
    document.getElementById('f_imp').value = imp;
    document.getElementById('f_wr').value  = wr;
    updateImpPreview();
    updateWr();
    document.getElementById('insightForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function clearForm() {
    document.getElementById('countrySelect').selectedIndex = 0;
    document.getElementById('f_imp').value = '';
    document.getElementById('f_wr').value  = '';
    document.getElementById('impPreview').textContent = '';
    document.getElementById('wrBar').style.width = '0%';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>