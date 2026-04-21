<?php
require_once __DIR__ . '/../includes/user_header.php';

$settings = getSettings();
$insights = readJson(INSIGHTS_FILE, []);

// Build a map of country code -> insight data
$insightMap = [];
foreach ($insights as $row) {
    $insightMap[$row['code']] = $row;
}

// Merge with all available countries, fill missing with defaults
$rows = [];
foreach ($settings['countries'] as $country) {
    $code = $country['code'];
    if (isset($insightMap[$code])) {
        $rows[] = $insightMap[$code];
    } else {
        // Country exists but no insight data set yet
        $rows[] = [
            'code'        => $code,
            'name'        => $country['name'],
            'win_rate'    => null,
            'impressions' => null,
        ];
    }
}

// Sort by impressions descending, nulls last
usort($rows, function($a, $b) {
    if ($a['impressions'] === null && $b['impressions'] === null) return 0;
    if ($a['impressions'] === null) return 1;
    if ($b['impressions'] === null) return -1;
    return $b['impressions'] - $a['impressions'];
});
?>

<div class="page-header">
  <div>
    <div class="page-title">Insights</div>
    <div class="page-subtitle">Available traffic inventory by country</div>
  </div>
</div>

<div class="card" style="padding:0;overflow:hidden">

  <!-- Header bar -->
  <div style="padding:18px 22px 14px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div>
      <div style="font-size:15px;font-weight:600">Traffic Inventory</div>
      <div style="font-size:12px;color:var(--text-2);margin-top:2px"><?= count($rows) ?> countries available</div>
    </div>
    <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-3)">
      <span class="live-dot"></span> Updated by network
    </div>
  </div>

  <div class="table-wrap" style="border:none;border-radius:0">
    <table>
      <thead>
        <tr>
          <th style="width:40px"></th>
          <th>Country</th>
          <th>Code</th>
          <th>Total Impressions</th>
          <th>Win Rate</th>
          <th>Availability</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row):
          $hasData     = $row['impressions'] !== null;
          $winRate     = $row['win_rate']    ?? null;
          $impressions = $row['impressions'] ?? null;

          // Win rate color
          $wrColor = 'var(--text-3)';
          $wrBg    = 'var(--bg-3)';
          if ($winRate !== null) {
              if ($winRate >= 70) { $wrColor = 'var(--green)';  $wrBg = 'rgba(0,229,153,0.1)'; }
              elseif ($winRate >= 40) { $wrColor = 'var(--orange)'; $wrBg = 'rgba(255,144,0,0.1)'; }
              else { $wrColor = 'var(--red)'; $wrBg = 'rgba(255,68,102,0.1)'; }
          }

          // Availability bar width (relative to max impressions)
          $maxImp = max(array_column(array_filter($rows, fn($r) => $r['impressions'] !== null), 'impressions') ?: [1]);
          $barPct = $hasData ? round($impressions / $maxImp * 100) : 0;
          $barColor = $barPct >= 70 ? 'var(--green)' : ($barPct >= 30 ? 'var(--yellow)' : 'var(--orange)');

          $flagCode = strtolower($row['code'] === 'UK' ? 'gb' : $row['code']);
        ?>
        <tr style="<?= !$hasData ? 'opacity:.5' : '' ?>">
          <td style="padding:10px 14px">
            <img src="https://flagcdn.com/w40/<?= $flagCode ?>.png"
                 alt="<?= $row['code'] ?>"
                 style="width:26px;height:18px;border-radius:2px;object-fit:cover;border:1px solid var(--border);display:block">
          </td>
          <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
          <td>
            <span style="font-family:'Courier New',monospace;font-size:12px;background:var(--bg-3);border:1px solid var(--border);padding:2px 8px;border-radius:4px;color:var(--yellow)">
              <?= htmlspecialchars($row['code']) ?>
            </span>
          </td>
          <td>
            <?php if ($hasData): ?>
            <strong style="font-size:15px"><?= fmtShort($impressions) ?></strong>
            <?php else: ?>
            <span style="color:var(--text-3);font-size:12px">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($winRate !== null): ?>
            <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $wrBg ?>;color:<?= $wrColor ?>;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700">
              <?= $winRate ?>%
            </span>
            <?php else: ?>
            <span style="color:var(--text-3);font-size:12px">—</span>
            <?php endif; ?>
          </td>
          <td style="width:200px">
            <?php if ($hasData): ?>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="flex:1;height:6px;background:var(--bg-3);border-radius:6px;overflow:hidden">
                <div style="width:<?= $barPct ?>%;height:100%;background:<?= $barColor ?>;border-radius:6px;transition:width .3s"></div>
              </div>
              <span style="font-size:11px;color:var(--text-2);min-width:32px;text-align:right"><?= $barPct ?>%</span>
            </div>
            <?php else: ?>
            <span style="color:var(--text-3);font-size:12px">No data</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>