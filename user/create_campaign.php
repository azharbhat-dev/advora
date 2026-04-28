<?php
require_once __DIR__ . '/../includes/user_header.php';
require_once __DIR__ . '/../includes/states_data.php';

$settings = getSettings();

// Load approved creatives for this user directly from DB
$stmt = db()->prepare("SELECT * FROM creatives WHERE user_id = ? AND status = 'approved' ORDER BY uploaded_at DESC");
$stmt->execute([$user['id']]);
$approvedCreatives = $stmt->fetchAll();

// ── CAMPAIGN LIMIT CHECK (block before form is even shown for NEW campaigns) ─
$editId  = $_GET['edit'] ?? null;
$editing = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM campaigns WHERE campaign_id = ? AND user_id = ?');
    $stmt->execute([$editId, $user['id']]);
    if ($row = $stmt->fetch()) {
        $editing = [
            'campaign_id'  => $row['campaign_id'],
            'name'         => $row['name'],
            'cpv'          => (float)$row['cpv'],
            'cpc'          => (float)$row['cpc'],
            'creative_id'  => $row['creative_id'],
            'countries'    => jdec($row['countries']),
            'states'       => jdec($row['states']),
            'schedule'     => jdec($row['schedule']),
            'ip_mode'      => $row['ip_mode'],
            'domain_mode'  => $row['domain_mode'],
            'ip_list'      => jdec($row['ip_list']),
            'domain_list'  => jdec($row['domain_list']),
            'daily_budget' => (float)$row['daily_budget'],
            'budget'       => (float)$row['budget'],
            'delivery'     => $row['delivery'],
            'sources'      => jdec($row['sources']),
        ];
    }
}

$userLimit  = getUserCampaignLimit($user['id']);
$userCount  = getUserCampaignCount($user['id']);
$atLimit    = !$editing && $userCount >= $userLimit;  // editing existing camp doesn't add to count

if ($atLimit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Show a friendly capped page instead of the wizard
    ?>
    <div class="page-header">
      <div>
        <div class="page-title">Create Campaign</div>
        <div class="page-subtitle">Campaign capacity reached</div>
      </div>
      <a href="/user/campaigns.php" class="btn btn-secondary">&larr; Back</a>
    </div>
    <div class="card" style="max-width:640px;margin:0 auto">
      <div style="text-align:center;padding:30px 20px">
        <div style="width:64px;height:64px;background:rgba(255,144,0,.1);border:1px solid rgba(255,144,0,.25);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;color:var(--orange)">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div style="font-size:20px;font-weight:700;margin-bottom:8px">You've reached your campaign limit</div>
        <div style="font-size:14px;color:var(--text-2);margin-bottom:20px;line-height:1.6">
          You currently have <strong><?= $userCount ?></strong> of <strong><?= $userLimit ?></strong> allowed campaigns.<br>
          This includes active, paused, under review, and rejected campaigns.
        </div>
        <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:var(--r-sm);padding:14px 18px;font-size:13px;color:var(--text-2);margin-bottom:20px;text-align:left">
          <strong style="color:var(--text)">To create more campaigns:</strong>
          <ul style="margin:8px 0 0 18px;line-height:1.8">
            <li>Delete a campaign you no longer need from the <a href="/user/campaigns.php">campaigns page</a></li>
            <li>Or contact your admin to request a higher campaign limit</li>
          </ul>
        </div>
        <a href="/user/campaigns.php" class="btn btn-primary">View My Campaigns</a>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $cpv         = (float)($_POST['cpv'] ?? 0);
    $creativeId  = $_POST['creative_id'] ?? '';
    $countries   = $_POST['countries'] ?? [];
    $states      = $_POST['states']    ?? [];
    $schedule    = $_POST['schedule']  ?? [];
    $ipMode      = $_POST['ip_mode']   ?? 'off';
    $domainMode  = $_POST['domain_mode'] ?? 'off';
    $ipList      = array_values(array_filter(array_map('trim', explode(',', $_POST['ip_list'] ?? ''))));
    $domainList  = array_values(array_filter(array_map('trim', explode(',', $_POST['domain_list'] ?? ''))));
    $dailyBudget = (float)($_POST['daily_budget'] ?? 0);
    $delivery    = in_array($_POST['delivery'] ?? '', ['asap','even']) ? $_POST['delivery'] : 'even';
    $sources     = $_POST['sources'] ?? [];

    $errors = [];
    if (!$name)             $errors[] = 'Campaign name is required';
    if ($cpv <= 0)          $errors[] = 'CPV bid must be greater than 0';
    if (!$creativeId)       $errors[] = 'Select a creative';
    if (empty($countries))  $errors[] = 'Select at least one country';
    if ($dailyBudget < MIN_DAILY_BUDGET) $errors[] = 'Daily budget must be at least ' . fmtMoney(MIN_DAILY_BUDGET);
    if (empty($sources))    $errors[] = 'Select at least one traffic source';

    // Re-check campaign limit on POST (defence in depth)
    if (!$editing && getUserCampaignCount($user['id']) >= $userLimit) {
        $errors[] = 'Campaign limit reached (' . $userLimit . '). Delete a campaign or contact admin.';
    }

    if (!empty($errors)) {
        flash(implode('. ', $errors), 'error');
    } else {
        if ($editing) {
            $stmt = db()->prepare(
                'UPDATE campaigns SET name=?, cpv=?, cpc=?, creative_id=?,
                   countries=?, states=?, schedule=?, ip_mode=?, domain_mode=?,
                   ip_list=?, domain_list=?, daily_budget=?, budget=?, delivery=?,
                   sources=?, status="review", updated_at=?
                 WHERE campaign_id = ? AND user_id = ?'
            );
            $stmt->execute([
                $name, $cpv, $cpv, $creativeId,
                jenc($countries), jenc($states), jenc($schedule), $ipMode, $domainMode,
                jenc($ipList), jenc($domainList), $dailyBudget, $dailyBudget, $delivery,
                jenc($sources), time(), $editing['campaign_id'], $user['id']
            ]);
            addAdminNotification($user['id'], $user['username'], 'campaign_updated',
                'Campaign Updated',
                $user['username'] . ' updated campaign "' . $name . '" (' . $editing['campaign_id'] . ') and sent for re-review.'
            );
            flash('Campaign updated and sent for review', 'success');
            safeRedirect('/user/campaign_view.php?id=' . urlencode($editing['campaign_id']));
        } else {
            $campaignId = 'CMP-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            $stmt = db()->prepare(
                'INSERT INTO campaigns
                 (campaign_id,user_id,name,cpv,cpc,creative_id,countries,states,schedule,ip_mode,domain_mode,
                  ip_list,domain_list,daily_budget,budget,delivery,sources,spent,impressions,clicks,good_hits,views_count,
                  status,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,0,0,0,0,"review",?,?)'
            );
            $stmt->execute([
                $campaignId, $user['id'], $name, $cpv, $cpv, $creativeId,
                jenc($countries), jenc($states), jenc($schedule), $ipMode, $domainMode,
                jenc($ipList), jenc($domainList), $dailyBudget, $dailyBudget, $delivery,
                jenc($sources), time(), time()
            ]);
            addAdminNotification($user['id'], $user['username'], 'campaign_created',
                'New Campaign Created',
                $user['username'] . ' created campaign "' . $name . '" (' . $campaignId . ') — CPV: ' . fmtMoney($cpv) . ', Budget: ' . fmtMoney($dailyBudget) . '/day'
            );
            safeRedirect('/user/campaign_view.php?id=' . urlencode($campaignId) . '&new=1');
        }
    }
}

// Default schedule: Mon-Fri 9-19 CST
$defaultScheduleMap = [];
for ($d = 0; $d <= 4; $d++) for ($h = 9; $h <= 19; $h++) $defaultScheduleMap[$d . '_' . $h] = true;
$scheduleMap = $editing && !empty($editing['schedule'])
    ? array_fill_keys($editing['schedule'], true)
    : $defaultScheduleMap;

$editingSources = $editing['sources'] ?? [];
$steps = ['Basics','Creative','Countries','Schedule','Sources','Filters','Budget','Review'];

// Divisor used for daily reach estimate (0.20 per view)
define('EST_CPV_DIVISOR', 0.20);
?>

<div class="page-header">
  <div>
    <div class="page-title"><?= $editing ? 'Edit Campaign' : 'Create Campaign' ?></div>
    <div class="page-subtitle"><?= $editing ? 'Editing ' . htmlspecialchars($editing['campaign_id']) : 'Set up your new ad campaign' ?>
      <?php if (!$editing): ?>
      <span style="margin-left:10px;font-size:11px;background:var(--bg-3);border:1px solid var(--border);padding:3px 9px;border-radius:5px;color:var(--text-2)">
        Campaign <?= $userCount + 1 ?> of <?= $userLimit ?>
      </span>
      <?php endif; ?>
    </div>
  </div>
  <a href="/user/campaigns.php" class="btn btn-secondary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="15 18 9 12 15 6"/></svg>
    Back
  </a>
</div>

<div class="cmp-create-layout">

  <div class="cmp-steps-panel" id="stepsPanel">
    <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:var(--text-3);font-weight:700;margin-bottom:10px">Steps</div>
    <?php foreach ($steps as $i => $s): ?>
    <div class="cmp-step-item <?= $i===0?'current':'' ?>" data-step="<?= $i ?>" onclick="goStep(<?= $i ?>)">
      <div class="cmp-step-num"><?= $i+1 ?></div>
      <?= $s ?>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border)">
      <div style="font-size:11px;color:var(--text-3);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px;font-weight:600">Balance</div>
      <div style="font-size:18px;font-weight:700;color:var(--yellow)" data-live-balance><?= fmtMoney($user['balance']) ?></div>
    </div>
  </div>

  <div class="cmp-main-panel">
    <div class="cmp-panel-head">
      <h2 id="stepTitle">Campaign Basics</h2>
      <p id="stepDesc">Name your campaign and set your CPV bid</p>
      <div class="cmp-progress"><div class="cmp-progress-bar" id="progressBar" style="width:12.5%"></div></div>
    </div>

    <form method="POST" id="wizardForm">

      <!-- STEP 0: Basics -->
      <div class="tab-content active cmp-panel-body" data-content="0">
        <div class="form-group">
          <label class="form-label">Campaign Name *</label>
          <input type="text" name="name" id="f_name" class="form-control" value="<?= htmlspecialchars($editing['name']??'') ?>" placeholder="e.g. Campaign-1">
        </div>
        <div class="form-group">
          <label class="form-label">CPV Bid (USD) *</label>
          <div style="position:relative">
            <span style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-2);font-weight:600">$</span>
            <input type="number" name="cpv" id="f_cpv" class="form-control" style="padding-left:26px" min="0.0001" step="0.0001" value="<?= htmlspecialchars((string)($editing['cpv'] ?? $editing['cpc'] ?? '')) ?>" placeholder="0.12">
          </div>
          <div class="form-hint">Cost per view — balance deducted per view received. Minimum $0.0001.</div>
        </div>
      </div>

      <!-- STEP 1: Creative -->
      <div class="tab-content cmp-panel-body" data-content="1">
        <?php if (empty($approvedCreatives)): ?>
        <div class="alert alert-warning">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          No approved creatives yet. <a href="/user/creatives.php" style="margin-left:4px">Upload one &rarr;</a>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label class="form-label">Select Creative *</label>
          <?php if (!empty($approvedCreatives)): ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px">
            <?php foreach ($approvedCreatives as $cr):
              $sel = ($editing['creative_id']??'')===$cr['id'] ? 'border-color:rgba(255,200,0,.35);background:var(--yellow-dim)' : '';
            ?>
            <label style="background:var(--bg-3);border:1px solid var(--border-2);border-radius:var(--r-sm);padding:14px;cursor:pointer;transition:all .15s;display:block;<?= $sel ?>" class="creative-card">
              <input type="radio" name="creative_id" value="<?= htmlspecialchars($cr['id']) ?>" <?= ($editing['creative_id']??'')===$cr['id']?'checked':'' ?> style="display:none">
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                <div style="width:36px;height:36px;background:var(--bg-4);border-radius:7px;display:flex;align-items:center;justify-content:center">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--yellow)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div>
                  <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($cr['name']) ?></div>
                  <div style="font-size:11px;color:var(--text-3)"><?= $cr['id'] ?></div>
                </div>
              </div>
              <div style="font-size:11px;color:var(--text-2)"><?= htmlspecialchars($cr['filename']) ?> &middot; <?= number_format($cr['file_size']/1024,1) ?> KB</div>
            </label>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <select name="creative_id" class="form-control" disabled><option value="">No approved creatives available</option></select>
          <?php endif; ?>
          <div class="form-hint">Only approved creatives appear here. <a href="/user/creatives.php">Manage creatives</a></div>
        </div>
      </div>

      <!-- STEP 2: Countries + States -->
      <div class="tab-content cmp-panel-body" data-content="2">
        <?php
        $statesJson        = json_encode(defined('COUNTRY_STATES') ? COUNTRY_STATES : []);
        $editingStatesJson = json_encode(array_values($editing['states'] ?? []));
        ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start" class="geo-grid">
        <style>@media(max-width:820px){.geo-grid{grid-template-columns:1fr!important}}</style>
<style>
.state-cb{width:16px;height:16px;border-radius:3px;border:1.5px solid var(--border-2);display:flex;align-items:center;justify-content:center;color:transparent;flex-shrink:0;transition:all .15s}
.state-cb.checked{background:var(--yellow);border-color:var(--yellow);color:#000}
#stateGrid > div:hover{background:rgba(255,200,0,.06)!important}
#stateGrid::-webkit-scrollbar{width:5px}
#stateGrid::-webkit-scrollbar-thumb{background:var(--border-2);border-radius:3px}
</style>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Target Countries *</label>
          <div style="display:flex;gap:7px;flex-wrap:wrap;margin-bottom:10px;align-items:center">
            <button type="button" class="btn btn-secondary btn-sm" onclick="selectAllCountries()">All</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="clearAllCountries()">Clear</button>
            <span id="countryCount" style="margin-left:auto;color:var(--text-2);font-size:12px">0 selected</span>
          </div>
          <div class="country-grid" id="countryGrid" style="max-height:360px">
          <?php foreach ($settings['countries'] as $country):
            $isSel = in_array($country['code'], $editing['countries']??[]);
            $flag  = strtolower($country['code']==='UK'?'gb':$country['code']);
          ?>
            <div class="country-item <?= $isSel?'selected':'' ?>" data-code="<?= htmlspecialchars($country['code']) ?>" onclick="countryClick(this)">
              <img class="country-flag" src="https://flagcdn.com/w40/<?= $flag ?>.png" alt="<?= $country['code'] ?>">
              <span class="country-code"><?= htmlspecialchars($country['code']) ?></span>
              <span class="country-name"><?= htmlspecialchars($country['name']) ?></span>
            </div>
          <?php endforeach; ?>
          </div>
        </div>
        <div>
          <div id="statePickerWrap" style="display:none">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
              <label class="form-label" style="margin:0" id="statePickerLabel">States</label>
              <div style="display:flex;gap:6px">
                <button type="button" class="btn btn-secondary btn-sm" onclick="selectAllStates()">All States</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="clearAllStates()">None</button>
              </div>
            </div>
            <div style="font-size:11.5px;color:var(--text-2);margin-bottom:8px">Leave all unselected to target the entire country</div>
            <div id="stateGrid" style="background:var(--bg);border:1px solid var(--border);border-radius:var(--r-sm);max-height:320px;overflow-y:auto;padding:8px;display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:5px"></div>
            <div style="margin-top:8px;font-size:12px;color:var(--text-3)" id="stateSelCount"></div>
          </div>
          <div id="statePickerEmpty" style="background:var(--bg-3);border:1px solid var(--border);border-radius:var(--r-sm);padding:28px;text-align:center;color:var(--text-3);font-size:13px">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;opacity:.4"><circle cx="12" cy="10" r="3"/><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
            Click a country to view<br>and select states
          </div>
        </div>
        </div>
        <div id="geoSummary" style="margin-top:14px;display:none">
          <div style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;font-weight:600">Selected Targeting</div>
          <div id="geoSummaryItems" style="display:flex;flex-wrap:wrap;gap:6px"></div>
        </div>
        <div id="countryInputs"></div>
      </div>

      <!-- STEP 3: Schedule -->
      <div class="tab-content cmp-panel-body" data-content="3">
        <div class="form-group">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;flex-wrap:wrap;gap:8px">
            <label class="form-label" style="margin:0">Ad Schedule</label>
            <span style="font-size:11px;color:var(--text-3);background:var(--bg-3);border:1px solid var(--border);padding:3px 9px;border-radius:4px;display:inline-flex;align-items:center;gap:5px">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              Timezone: CST (UTC&minus;6)
            </span>
          </div>
          <div style="font-size:12px;color:var(--text-2);margin-bottom:12px">Click cells to toggle time slots. Default: Mon–Fri 9AM–7PM CST.</div>
          <div class="schedule-grid">
            <div class="schedule-header"></div>
            <?php for ($h=0;$h<24;$h++): ?><div class="schedule-header"><?= str_pad($h,2,'0',STR_PAD_LEFT) ?></div><?php endfor; ?>
            <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $di => $day): ?>
            <div class="schedule-day"><?= $day ?></div>
            <?php for ($h=0;$h<24;$h++):
              $key = $di . '_' . $h;
              $act = isset($scheduleMap[$key]) ? 'active' : '';
            ?><div class="schedule-cell <?= $act ?>" data-key="<?= $key ?>" onclick="toggleCell(this)"></div><?php endfor; ?>
            <?php endforeach; ?>
          </div>
          <div id="scheduleInputs"></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
            <button type="button" class="btn btn-secondary btn-sm" onclick="selectAllSchedule()">24/7</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="clearSchedule()">Clear</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="selectBusinessHours()">Business Hours (Mon–Fri 9–19 CST)</button>
          </div>
        </div>
      </div>

      <!-- STEP 4: Sources -->
      <div class="tab-content cmp-panel-body" data-content="4">
        <div class="form-group">
          <label class="form-label">Traffic Sources *</label>
          <div style="font-size:12px;color:var(--text-2);margin-bottom:16px">Select one or more sources.</div>
          <div class="sources-table" id="sourcesGrid">
            <div class="sources-thead">
              <div class="sc-col"></div>
              <div class="sc-col">Source</div>
              <div class="sc-col sc-center">Sources</div>
              <div class="sc-col sc-center">CPV Starts</div>
              <div class="sc-col">Best For</div>
            </div>
            <?php
            $src_defs = [
              ['premium',  'Premium',  '101', '$0.25', 'Best for start',      '#0095F6'],
              ['standard', 'Standard', '115', '$0.19', 'Best to scale',       '#F59E0B'],
              ['remnant',  'Remnant',  '230', '$0.18', 'Best to buy cheap',   '#6366F1'],
              ['new',      'New',      '250', '$0.23', 'Best to expand',      '#10B981'],
            ];
            foreach ($src_defs as [$code,$label,$cnt,$cpv,$tag,$color]):
              $on = in_array($code, $editingSources);
            ?>
            <div class="sources-row <?= $on?'selected':'' ?>" data-source="<?= $code ?>" onclick="toggleSource(this)">
              <input type="checkbox" name="sources[]" value="<?= $code ?>" <?= $on?'checked':'' ?> style="display:none">
              <div class="sc-col sc-check"><div class="source-check"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div></div>
              <div class="sc-col sc-name"><div class="source-icon" style="background:<?= $color ?>22"><div style="width:10px;height:10px;border-radius:50%;background:<?= $color ?>"></div></div><span><?= $label ?></span></div>
              <div class="sc-col sc-center"><?= $cnt ?></div>
              <div class="sc-col sc-center sc-price"><?= $cpv ?></div>
              <div class="sc-col"><span class="source-tag"><?= $tag ?></span></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- STEP 5: Filters -->
      <div class="tab-content cmp-panel-body" data-content="5">
        <div class="form-group">
          <label class="form-label">IP Filter Mode</label>
          <div class="radio-group" data-group="ip_mode">
            <?php foreach (['off'=>['Off','No IP filtering'],'whitelist'=>['Whitelist','Only allow listed IPs'],'blacklist'=>['Blacklist','Block listed IPs']] as $v => [$l,$d]):
              $sel = ($editing['ip_mode']??'off')===$v ? 'selected' : '';
            ?>
            <label class="radio-option <?= $sel ?>">
              <input type="radio" name="ip_mode" value="<?= $v ?>" <?= $sel?'checked':'' ?>>
              <div class="opt-label"><?= $l ?></div>
              <div class="opt-desc"><?= $d ?></div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">IP Addresses</label>
          <div class="ip-list" id="ipListDisplay"></div>
          <div class="list-input-row">
            <input type="text" id="ipInput" class="form-control" placeholder="192.168.1.1">
            <button type="button" class="btn btn-primary btn-sm" onclick="addIp()">Add</button>
          </div>
          <input type="hidden" name="ip_list" id="ipListHidden" value="<?= htmlspecialchars(implode(',', $editing['ip_list']??[])) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Domain Filter Mode</label>
          <div class="radio-group" data-group="domain_mode">
            <?php foreach (['off'=>['Off','No domain filtering'],'whitelist'=>['Whitelist','Only allow listed'],'blacklist'=>['Blacklist','Block listed domains']] as $v => [$l,$d]):
              $sel = ($editing['domain_mode']??'off')===$v ? 'selected' : '';
            ?>
            <label class="radio-option <?= $sel ?>">
              <input type="radio" name="domain_mode" value="<?= $v ?>" <?= $sel?'checked':'' ?>>
              <div class="opt-label"><?= $l ?></div>
              <div class="opt-desc"><?= $d ?></div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Domains</label>
          <div class="ip-list" id="domainListDisplay"></div>
          <div class="list-input-row">
            <input type="text" id="domainInput" class="form-control" placeholder="example.com">
            <button type="button" class="btn btn-primary btn-sm" onclick="addDomain()">Add</button>
          </div>
          <input type="hidden" name="domain_list" id="domainListHidden" value="<?= htmlspecialchars(implode(',', $editing['domain_list']??[])) ?>">
        </div>
      </div>

      <!-- STEP 6: Budget — MIN $50 -->
      <div class="tab-content cmp-panel-body" data-content="6">
        <div class="form-group">
          <label class="form-label">Daily Budget (USD) *</label>
          <div style="position:relative">
            <span style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-2);font-weight:600">$</span>
            <input type="number" name="daily_budget" id="f_budget" class="form-control"
                   style="padding-left:26px"
                   min="<?= MIN_DAILY_BUDGET ?>" step="0.01"
                   value="<?= htmlspecialchars((string)($editing['daily_budget'] ?? $editing['budget'] ?? '')) ?>"
                   placeholder="<?= number_format(MIN_DAILY_BUDGET, 2) ?>"
                   oninput="updateEstimate()">
          </div>
          <div class="form-hint">Minimum daily budget is <strong style="color:var(--yellow)"><?= fmtMoney(MIN_DAILY_BUDGET) ?></strong>. Campaign pauses when daily budget is reached. Resets every day.</div>
        </div>
        <div class="form-group">
          <label class="form-label">Delivery Mode *</label>
          <div style="display:flex;gap:10px">
            <div class="delivery-btn <?= ($editing['delivery']??'even')==='asap'?'active':'' ?>" id="d_asap" onclick="setDelivery('asap')">
              <input type="radio" name="delivery" value="asap" <?= ($editing['delivery']??'even')==='asap'?'checked':'' ?> style="display:none" id="r_asap">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                <span style="font-weight:700;font-size:14px">ASAP</span>
                <div class="delivery-check" style="margin-left:auto"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div>
              </div>
              <div style="font-size:11.5px;color:var(--text-2)">Spend budget as fast as possible</div>
            </div>
            <div class="delivery-btn <?= ($editing['delivery']??'even')==='even'?'active':'' ?>" id="d_even" onclick="setDelivery('even')">
              <input type="radio" name="delivery" value="even" <?= ($editing['delivery']??'even')==='even'?'checked':'' ?> style="display:none" id="r_even">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                <span style="font-weight:700;font-size:14px">Even</span>
                <div class="delivery-check" style="margin-left:auto"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div>
              </div>
              <div style="font-size:11.5px;color:var(--text-2)">Spread budget evenly throughout day</div>
            </div>
          </div>
        </div>
        <div class="alert alert-info" style="font-size:13px">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          Your current balance: <strong style="margin-left:4px" data-live-balance><?= fmtMoney($user['balance']) ?></strong>
        </div>
        <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:var(--r-sm);padding:14px;margin-top:8px">
          <div style="font-size:12px;color:var(--text-2);margin-bottom:6px">Estimated daily views</div>
          <div style="font-size:22px;font-weight:700;color:var(--yellow)" id="estViews">~0</div>
          
        </div>
      </div>

      <!-- STEP 7: Review -->
      <div class="tab-content cmp-panel-body" data-content="7">
        <div style="margin-bottom:18px">
          <div style="font-size:16px;font-weight:700;margin-bottom:14px">Campaign Summary</div>
          <div class="summary-grid" id="summaryArea"></div>
        </div>
        <div id="reviewCountries" style="margin-bottom:12px"></div>
        <div id="reviewSources"   style="margin-bottom:16px"></div>
        <div class="alert alert-info" style="font-size:12.5px">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          Campaign status will be set to <strong>Under Review</strong>.
        </div>
      </div>

    </form>

    <div class="cmp-panel-footer">
      <button type="button" class="btn btn-secondary" id="btnBack" onclick="prevStep()" style="visibility:hidden">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="15 18 9 12 15 6"/></svg>
        Back
      </button>
      <button type="button" class="btn btn-primary" id="btnNext" onclick="nextStep()">
        Continue
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
    </div>
  </div>
</div>

<style>
.sources-table{border:1px solid var(--border);border-radius:var(--r-sm);overflow:hidden;width:100%}
.sources-thead{display:grid;grid-template-columns:36px 1fr 90px 100px 1fr;background:var(--bg-3);border-bottom:1px solid var(--border);padding:9px 14px;gap:12px}
.sources-thead .sc-col{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-3)}
.sources-row{display:grid;grid-template-columns:36px 1fr 90px 100px 1fr;align-items:center;padding:11px 14px;gap:12px;cursor:pointer;border-bottom:1px solid var(--border);transition:background .15s;user-select:none}
.sources-row:last-child{border-bottom:none}
.sources-row:hover{background:var(--bg-3)}
.sources-row.selected{background:var(--yellow-dim)}
.sources-row.selected .source-check{background:var(--yellow);border-color:var(--yellow);color:#000}
.sources-row.selected .source-tag{background:rgba(255,200,0,.12);border-color:rgba(255,200,0,.2);color:var(--yellow)}
.sc-col{font-size:13px;color:var(--text)}
.sc-center{text-align:center}
.sc-check{display:flex;align-items:center;justify-content:center}
.sc-name{display:flex;align-items:center;gap:9px;font-weight:600;font-size:13.5px}
.sc-price{font-weight:700;color:var(--yellow);font-size:13.5px}
.source-check{width:20px;height:20px;border-radius:50%;border:2px solid var(--border-2);display:flex;align-items:center;justify-content:center;color:transparent;transition:all .15s;flex-shrink:0}
.source-icon{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.source-tag{display:inline-block;font-size:11px;padding:3px 9px;background:var(--bg-4);border:1px solid var(--border);border-radius:20px;color:var(--text-2)}
.delivery-btn{flex:1;background:var(--bg-3);border:2px solid var(--border-2);border-radius:var(--r);padding:14px 16px;cursor:pointer;transition:all .2s}
.delivery-btn:hover{border-color:rgba(255,200,0,.3)}
.delivery-btn.active{border-color:var(--yellow);background:var(--yellow-dim)}
.delivery-check{width:20px;height:20px;border-radius:50%;border:2px solid var(--border-2);display:flex;align-items:center;justify-content:center;color:transparent;transition:all .2s}
.delivery-btn.active .delivery-check{background:var(--yellow);border-color:var(--yellow);color:#000}
</style>

<script>
const EST_CPV_DIVISOR = 0.20;
const MIN_BUDGET      = <?= MIN_DAILY_BUDGET ?>;

var totalSteps=8, currentStep=0;
var selectedSources = <?= json_encode(array_values($editingSources)) ?>;

(function(){
  var e=document.createElement('div'); e.id='tabError'; e.className='alert alert-danger';
  e.style.cssText='display:none;margin:0 0 16px';
  var f=document.getElementById('wizardForm');
  if(f) f.parentNode.insertBefore(e,f);
})();

var stepTitles=['Campaign Basics','Select Creative','Geo Targeting','Ad Schedule','Traffic Sources','Filters','Daily Budget','Review & Submit'];
var stepDescs=['Name your campaign and set your CPV bid','Choose the HTML creative for this campaign','Select which countries to target','Choose when your ads should run (CST timezone)','Choose your traffic sources','Set IP and domain filters (optional)','Set your daily spend budget and delivery mode','Review all settings before submitting'];

function goStep(n){ if(n>currentStep){ for(var i=currentStep;i<n;i++){ if(!validateStep(i)) return; } } showStep(n); }
function showStep(n){
  document.querySelectorAll('.tab-content').forEach(function(c,i){ c.classList.toggle('active',i===n); });
  document.querySelectorAll('.cmp-step-item').forEach(function(s,i){
    s.classList.remove('current','done');
    if(i===n) s.classList.add('current'); else if(i<n) s.classList.add('done');
  });
  document.getElementById('stepTitle').textContent = stepTitles[n];
  document.getElementById('stepDesc').textContent  = stepDescs[n];
  document.getElementById('progressBar').style.width = ((n+1)/totalSteps*100) + '%';
  document.getElementById('btnBack').style.visibility = n===0?'hidden':'visible';
  var btn = document.getElementById('btnNext');
  if(n===totalSteps-1){
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15"><polyline points="20 6 9 17 4 12"/></svg> <?= $editing?"Update Campaign":"Create Campaign" ?>';
    btn.onclick = function(){ submitForm(); };
  } else {
    btn.innerHTML = 'Continue <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="9 18 15 12 9 6"/></svg>';
    btn.onclick = nextStep;
  }
  hideError();
  currentStep = n;
  if(n===7) buildSummary();
  if(n===6) updateEstimate();
  window.scrollTo({top:0,behavior:'smooth'});
}
function nextStep(){ if(validateStep(currentStep)) showStep(currentStep+1); }
function prevStep(){ if(currentStep>0) showStep(currentStep-1); }
function showError(msg){ var e=document.getElementById('tabError'); if(!e){alert(msg);return;} e.textContent=msg; e.style.display='flex'; e.scrollIntoView({behavior:'smooth',block:'center'}); }
function hideError(){ var e=document.getElementById('tabError'); if(e) e.style.display='none'; }

function validateStep(n){
  hideError();
  if(n===0){
    if(!document.getElementById('f_name').value.trim()){showError('Please enter a campaign name');return false;}
    if(!parseFloat(document.getElementById('f_cpv').value)||parseFloat(document.getElementById('f_cpv').value)<=0){showError('Please enter a valid CPV bid');return false;}
  }
  if(n===1){ if(!document.querySelector('input[name="creative_id"]:checked')){showError('Please select a creative');return false;} }
  if(n===2){ if(selectedCountries.length===0){showError('Select at least one country');return false;} }
  if(n===4){ if(selectedSources.length===0){showError('Select at least one traffic source');return false;} }
  if(n===6){
    var b = parseFloat(document.getElementById('f_budget').value);
    if(!b || b < MIN_BUDGET){
      showError('Daily budget must be at least $' + MIN_BUDGET.toFixed(2));
      return false;
    }
    if(!document.querySelector('input[name="delivery"]:checked')){showError('Please select a delivery mode');return false;}
  }
  return true;
}

const STATES_DATA = <?= $statesJson ?>;
var selectedCountries = <?= json_encode(array_values($editing['countries']??[])) ?>;
var selectedStates    = <?= $editingStatesJson ?>;
var activeCountry     = null;

function countryClick(el) {
  var code = el.dataset.code;
  var idx  = selectedCountries.indexOf(code);
  if (idx === -1) { selectedCountries.push(code); el.classList.add('selected'); }
  else {
    selectedCountries.splice(idx, 1); el.classList.remove('selected');
    selectedStates = selectedStates.filter(function(s){ return s.indexOf(code+':') !== 0; });
  }
  updateCountryInputs(); openStatePicker(code);
}
function openStatePicker(code) {
  activeCountry = code;
  var states = STATES_DATA[code] || [];
  var wrap=document.getElementById('statePickerWrap'), empty=document.getElementById('statePickerEmpty'),
      grid=document.getElementById('stateGrid'), label=document.getElementById('statePickerLabel');
  if (!states.length || selectedCountries.indexOf(code) === -1) { wrap.style.display='none'; empty.style.display='block'; return; }
  empty.style.display='none'; wrap.style.display='block';
  var name = code;
  document.querySelectorAll('#countryGrid .country-item').forEach(function(el){ if (el.dataset.code === code) name = el.querySelector('.country-name').textContent; });
  label.textContent = name + ' — States / Regions';
  grid.innerHTML = '';
  states.forEach(function(state) {
    var key = code + ':' + state;
    var checked = selectedStates.indexOf(key) !== -1;
    var div = document.createElement('div');
    div.style.cssText = 'display:flex;align-items:center;gap:7px;padding:5px 8px;border-radius:5px;cursor:pointer;transition:background .1s;font-size:12.5px;user-select:none';
    div.innerHTML = '<div class="state-cb ' + (checked?'checked':'') + '"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div><span>' + state + '</span>';
    div.addEventListener('click', function() {
      var cb = div.querySelector('.state-cb');
      if (selectedStates.indexOf(key) !== -1) { selectedStates.splice(selectedStates.indexOf(key), 1); cb.classList.remove('checked'); div.style.background = ''; }
      else { selectedStates.push(key); cb.classList.add('checked'); div.style.background = 'var(--yellow-dim)'; }
      updateStateCount(); updateCountryInputs();
    });
    if (checked) div.style.background = 'var(--yellow-dim)';
    grid.appendChild(div);
  });
  updateStateCount();
}
function updateStateCount() {
  if (!activeCountry) return;
  var count = selectedStates.filter(function(s){ return s.indexOf(activeCountry+':') === 0; }).length;
  var total = (STATES_DATA[activeCountry] || []).length;
  document.getElementById('stateSelCount').textContent = count > 0 ? count + ' of ' + total + ' states selected' : 'All ' + total + ' states — no restriction';
}
function selectAllStates(){ if(!activeCountry) return; var states=STATES_DATA[activeCountry]||[];
  selectedStates = selectedStates.filter(function(s){ return s.indexOf(activeCountry+':')!==0; });
  states.forEach(function(s){ selectedStates.push(activeCountry+':'+s); });
  document.querySelectorAll('#stateGrid .state-cb').forEach(function(cb){ cb.classList.add('checked'); cb.parentElement.style.background='var(--yellow-dim)'; });
  updateStateCount(); updateCountryInputs();
}
function clearAllStates(){ if(!activeCountry) return;
  selectedStates = selectedStates.filter(function(s){ return s.indexOf(activeCountry+':')!==0; });
  document.querySelectorAll('#stateGrid .state-cb').forEach(function(cb){ cb.classList.remove('checked'); cb.parentElement.style.background=''; });
  updateStateCount(); updateCountryInputs();
}
function selectAllCountries(){
  selectedCountries = [];
  document.querySelectorAll('#countryGrid .country-item').forEach(function(i){ selectedCountries.push(i.dataset.code); i.classList.add('selected'); });
  updateCountryInputs(); if (activeCountry) openStatePicker(activeCountry);
}
function clearAllCountries(){
  selectedCountries = []; selectedStates = [];
  document.querySelectorAll('#countryGrid .country-item').forEach(function(i){ i.classList.remove('selected'); });
  activeCountry = null;
  document.getElementById('statePickerWrap').style.display = 'none';
  document.getElementById('statePickerEmpty').style.display = 'block';
  updateCountryInputs();
}
function updateCountryInputs(){
  var c = document.getElementById('countryInputs'); c.innerHTML = '';
  selectedCountries.forEach(function(code){ var i=document.createElement('input'); i.type='hidden'; i.name='countries[]'; i.value=code; c.appendChild(i); });
  selectedStates.forEach(function(sv){ var cc=sv.split(':')[0]; if (selectedCountries.indexOf(cc)!==-1) { var i=document.createElement('input'); i.type='hidden'; i.name='states[]'; i.value=sv; c.appendChild(i); } });
  document.getElementById('countryCount').textContent = selectedCountries.length + ' selected';
  updateGeoSummary();
}
function updateGeoSummary(){
  var wrap=document.getElementById('geoSummary'), items=document.getElementById('geoSummaryItems');
  if (selectedCountries.length === 0) { wrap.style.display='none'; return; }
  wrap.style.display='block'; items.innerHTML='';
  selectedCountries.forEach(function(code){
    var countStates = selectedStates.filter(function(s){ return s.indexOf(code+':')===0; }).length;
    var badge = document.createElement('span'); badge.className='badge badge-yellow';
    badge.style.cssText='cursor:pointer;display:inline-flex;align-items:center;gap:5px;padding:4px 10px';
    badge.textContent = code + (countStates>0 ? ' ('+countStates+' states)' : ' — All');
    badge.onclick = function(){ openStatePicker(code); };
    items.appendChild(badge);
  });
}
updateCountryInputs();
if (selectedCountries.length > 0) openStatePicker(selectedCountries[0]);

document.querySelectorAll('.creative-card').forEach(function(card){
  card.addEventListener('click', function(){
    document.querySelectorAll('.creative-card').forEach(function(c){ c.style.borderColor=''; c.style.background=''; });
    card.style.borderColor = 'rgba(255,200,0,.35)'; card.style.background = 'var(--yellow-dim)';
    card.querySelector('input[type="radio"]').checked = true;
  });
});

document.querySelectorAll('.radio-group').forEach(function(grp){
  grp.querySelectorAll('.radio-option').forEach(function(opt){
    opt.addEventListener('click', function(e){
      if (e.target.tagName === 'INPUT') return;
      grp.querySelectorAll('.radio-option').forEach(function(o){ o.classList.remove('selected'); });
      opt.classList.add('selected');
      var r = opt.querySelector('input'); if (r) r.checked = true;
    });
  });
});

function toggleCell(c){ c.classList.toggle('active'); }
function selectAllSchedule(){ document.querySelectorAll('.schedule-cell').forEach(function(c){ c.classList.add('active'); }); }
function clearSchedule(){ document.querySelectorAll('.schedule-cell').forEach(function(c){ c.classList.remove('active'); }); }
function selectBusinessHours(){
  document.querySelectorAll('.schedule-cell').forEach(function(c){
    var p = c.dataset.key.split('_').map(Number);
    if (p[0]<5 && p[1]>=9 && p[1]<=19) c.classList.add('active'); else c.classList.remove('active');
  });
}
function buildSchedulePayload(){
  var c = document.getElementById('scheduleInputs'); c.innerHTML = '';
  document.querySelectorAll('.schedule-cell.active').forEach(function(s){
    var i=document.createElement('input'); i.type='hidden'; i.name='schedule[]'; i.value=s.dataset.key; c.appendChild(i);
  });
}

function toggleSource(row){
  var src=row.dataset.source, chk=row.querySelector('input[type="checkbox"]');
  if (row.classList.contains('selected')) {
    row.classList.remove('selected'); chk.checked=false;
    selectedSources = selectedSources.filter(function(s){ return s!==src; });
  } else {
    row.classList.add('selected'); chk.checked=true;
    if (selectedSources.indexOf(src) === -1) selectedSources.push(src);
  }
}

function setDelivery(mode){
  document.getElementById('d_asap').classList.toggle('active', mode==='asap');
  document.getElementById('d_even').classList.toggle('active', mode==='even');
  document.getElementById('r_asap').checked = mode==='asap';
  document.getElementById('r_even').checked = mode==='even';
}

var ipItems     = <?= json_encode(array_values($editing['ip_list']??[])) ?>;
var domainItems = <?= json_encode(array_values($editing['domain_list']??[])) ?>;
function renderTags(cid, hid, items, type){
  var c = document.getElementById(cid); c.innerHTML = '';
  if (!items.length) { c.innerHTML = '<div style="color:var(--text-3);font-size:12px;padding:6px">No '+type+' added</div>'; return; }
  items.forEach(function(val, i){
    var tag = document.createElement('div'); tag.className = 'ip-tag';
    tag.innerHTML = '<span>' + val + '</span><span class="remove-tag" data-idx="'+i+'">&times;</span>';
    tag.querySelector('.remove-tag').addEventListener('click', function(){ items.splice(i,1); document.getElementById(hid).value=items.join(','); renderTags(cid,hid,items,type); });
    c.appendChild(tag);
  });
}
renderTags('ipListDisplay','ipListHidden',ipItems,'IPs');
renderTags('domainListDisplay','domainListHidden',domainItems,'domains');
function addIp(){ var v=document.getElementById('ipInput').value.trim(); if(!v) return; ipItems.push(v); document.getElementById('ipListHidden').value=ipItems.join(','); renderTags('ipListDisplay','ipListHidden',ipItems,'IPs'); document.getElementById('ipInput').value=''; }
function addDomain(){ var v=document.getElementById('domainInput').value.trim(); if(!v) return; domainItems.push(v); document.getElementById('domainListHidden').value=domainItems.join(','); renderTags('domainListDisplay','domainListHidden',domainItems,'domains'); document.getElementById('domainInput').value=''; }
document.getElementById('ipInput').addEventListener('keypress', function(e){ if(e.key==='Enter'){ e.preventDefault(); addIp(); } });
document.getElementById('domainInput').addEventListener('keypress', function(e){ if(e.key==='Enter'){ e.preventDefault(); addDomain(); } });

function updateEstimate(){
  var budget = parseFloat(document.getElementById('f_budget').value || 0);
  var est = budget > 0 ? Math.round(budget / EST_CPV_DIVISOR) : 0;
  var el = document.getElementById('estViews');
  if (el) el.textContent = '~' + est.toLocaleString();
}
document.addEventListener('DOMContentLoaded', function(){
  var bi = document.getElementById('f_budget');
  if (bi) bi.addEventListener('input', updateEstimate);
  updateEstimate();
});

function buildSummary(){
  buildSchedulePayload(); updateCountryInputs();
  var name=document.getElementById('f_name').value||'-';
  var cpv=parseFloat(document.getElementById('f_cpv').value||0).toFixed(2);
  var budget=parseFloat(document.getElementById('f_budget').value||0).toFixed(2);
  var schedCnt=document.querySelectorAll('.schedule-cell.active').length;
  var delivery=document.querySelector('input[name="delivery"]:checked');
  var est = parseFloat(budget) > 0 ? Math.round(parseFloat(budget) / EST_CPV_DIVISOR) : 0;
  var srcLabels={premium:'Premium',standard:'Standard',remnant:'Remnant','new':'New'};

  var items = [
    ['Name',name], ['CPV Bid','$'+cpv], ['Daily Budget','$'+budget],
    ['Delivery', delivery?delivery.value.toUpperCase():'Even'],
    ['Countries', selectedCountries.length+' selected'],
    ['Schedule', schedCnt===0?'24/7':schedCnt+' slots (CST)'],
    ['Sources', selectedSources.length+' selected'],
    ['Est. Daily Views','~'+est.toLocaleString()]
  ];
  document.getElementById('summaryArea').innerHTML = items.map(function(it){
    return '<div class="summary-item"><div class="summary-label">'+it[0]+'</div><div class="summary-value" style="font-size:14px">'+it[1]+'</div></div>';
  }).join('');

  var geoHtml = '';
  if (selectedCountries.length > 0) {
    geoHtml += '<div style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;font-weight:600">Geo Targeting</div><div style="display:flex;gap:5px;flex-wrap:wrap">';
    selectedCountries.forEach(function(code){
      var countS = selectedStates.filter(function(s){ return s.indexOf(code+':')===0; }).length;
      geoHtml += '<span class="badge badge-yellow">' + code + (countS > 0 ? ' <span style="opacity:.7">+'+countS+' states</span>' : ' — All') + '</span>';
    });
    geoHtml += '</div>';
  }
  document.getElementById('reviewCountries').innerHTML = geoHtml;

  document.getElementById('reviewSources').innerHTML = selectedSources.length > 0
    ? '<div style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;font-weight:600">Traffic Sources</div><div style="display:flex;gap:5px;flex-wrap:wrap">' + selectedSources.map(function(s){ return '<span class="badge badge-info">'+(srcLabels[s]||s)+'</span>'; }).join('') + '</div>'
    : '';
}

function submitForm(){
  buildSchedulePayload(); updateCountryInputs();
  for (var i=0; i<totalSteps-1; i++) { if (!validateStep(i)) { showStep(i); return; } }
  document.getElementById('wizardForm').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
