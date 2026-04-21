<?php
require_once __DIR__ . '/../includes/user_header.php';

$settings = getSettings();
$creatives = readJson(CREATIVES_FILE);
$approvedCreatives = array_values(array_filter($creatives, fn($cr) => $cr['user_id'] === $user['id'] && $cr['status'] === 'approved'));

$editId = $_GET['edit'] ?? null;
$editing = null;
if ($editId) {
    foreach (readJson(CAMPAIGNS_FILE) as $c) {
        if ($c['campaign_id'] === $editId && $c['user_id'] === $user['id']) { $editing = $c; break; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $cpc = (float)($_POST['cpc'] ?? 0);
    $creativeId = $_POST['creative_id'] ?? '';
    $countries = $_POST['countries'] ?? [];
    $schedule = $_POST['schedule'] ?? [];
    $ipMode = $_POST['ip_mode'] ?? 'off';
    $domainMode = $_POST['domain_mode'] ?? 'off';
    $ipList = array_values(array_filter(array_map('trim', explode(',', $_POST['ip_list'] ?? ''))));
    $domainList = array_values(array_filter(array_map('trim', explode(',', $_POST['domain_list'] ?? ''))));
    $budget = (float)($_POST['budget'] ?? 0);

    $errors = [];
    if (!$name) $errors[] = 'Campaign name is required';
    if ($cpc <= 0) $errors[] = 'CPC bid must be greater than 0';
    if (!$creativeId) $errors[] = 'Select a creative';
    if (empty($countries)) $errors[] = 'Select at least one country';
    if ($budget < 1) $errors[] = 'Budget must be at least $1.00';

    if (!empty($errors)) {
        flash(implode('. ', $errors), 'error');
    } else {
        $campaigns = readJson(CAMPAIGNS_FILE);
        if ($editing) {
            foreach ($campaigns as &$c) {
                if ($c['campaign_id'] === $editing['campaign_id']) {
                    $c = array_merge($c, compact('name','cpc','creativeId','countries','schedule','ipMode','domainMode','ipList','domainList','budget'));
                    $c['creative_id'] = $creativeId;
                    $c['status'] = 'review';
                    $c['updated_at'] = time();
                    break;
                }
            }
            writeJson(CAMPAIGNS_FILE, $campaigns);
            flash('Campaign updated and sent for review', 'success');
            safeRedirect('/user/campaign_view.php?id=' . urlencode($editing['campaign_id']));
        } else {
            $campaignId = 'CMP-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            $campaigns[] = [
                'campaign_id' => $campaignId, 'user_id' => $user['id'], 'name' => $name,
                'cpc' => $cpc, 'creative_id' => $creativeId, 'countries' => $countries,
                'schedule' => $schedule, 'ip_mode' => $ipMode, 'domain_mode' => $domainMode,
                'ip_list' => $ipList, 'domain_list' => $domainList, 'budget' => $budget,
                'spent' => 0, 'impressions' => 0, 'clicks' => 0, 'good_hits' => 0,
                'status' => 'pending', 'created_at' => time(), 'updated_at' => time()
            ];
            writeJson(CAMPAIGNS_FILE, $campaigns);
            safeRedirect('/user/campaign_view.php?id=' . urlencode($campaignId) . '&new=1');
        }
    }
}

$scheduleMap = [];
foreach (($editing['schedule'] ?? []) as $s) $scheduleMap[$s] = true;
$steps = ['Basics','Creative','Countries','Schedule','Filters','Budget','Review'];
?>

<div class="page-header">
  <div>
    <div class="page-title"><?= $editing ? 'Edit Campaign' : 'Create Campaign' ?></div>
    <div class="page-subtitle"><?= $editing ? 'Editing ' . htmlspecialchars($editing['campaign_id']) : 'Set up your new ad campaign' ?></div>
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
      <div style="font-size:18px;font-weight:700;color:var(--yellow)"><?= fmtMoney($user['balance']) ?></div>
    </div>
  </div>

  <div class="cmp-main-panel">
    <div class="cmp-panel-head">
      <h2 id="stepTitle">Campaign Basics</h2>
      <p id="stepDesc">Name your campaign and set your CPC bid</p>
      <div class="cmp-progress"><div class="cmp-progress-bar" id="progressBar" style="width:14%"></div></div>
    </div>

    <form method="POST" id="wizardForm">

      <div class="tab-content active cmp-panel-body" data-content="0">
        <div class="form-group">
          <label class="form-label">Campaign Name *</label>
          <input type="text" name="name" id="f_name" class="form-control" value="<?= htmlspecialchars($editing['name']??'') ?>" placeholder="e.g. US Desktop Push Q1 2025">
          <div class="form-hint">Give your campaign a descriptive name</div>
        </div>
        <div class="form-group">
          <label class="form-label">CPC Bid (USD) *</label>
          <div style="position:relative">
            <span style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-2);font-weight:600">$</span>
            <input type="number" name="cpc" id="f_cpc" class="form-control" style="padding-left:26px" min="0.0001" step="0.0001" value="<?= htmlspecialchars($editing['cpc']??'') ?>" placeholder="0.0015">
          </div>
          <div class="form-hint">Cost per click &mdash; minimum $0.0001, supports 4 decimal places</div>
        </div>
      </div>

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
              <input type="radio" name="creative_id" id="f_creative" value="<?= htmlspecialchars($cr['id']) ?>" <?= ($editing['creative_id']??'')===$cr['id']?'checked':'' ?> style="display:none">
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
          <select name="creative_id" id="f_creative" class="form-control" disabled>
            <option value="">No approved creatives available</option>
          </select>
          <?php endif; ?>
          <div class="form-hint">Only admin-approved HTML creatives appear here. <a href="/user/creatives.php">Manage creatives</a></div>
        </div>
      </div>

      <div class="tab-content cmp-panel-body" data-content="2">
        <div class="form-group">
          <label class="form-label">Target Countries *</label>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;align-items:center">
            <button type="button" class="btn btn-secondary btn-sm" onclick="selectAllCountries()">Select All</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="clearAllCountries()">Clear All</button>
            <span id="countryCount" style="margin-left:auto;color:var(--text-2);font-size:12px">0 selected</span>
          </div>
          <div class="country-grid" id="countryGrid">
          <?php foreach ($settings['countries'] as $country):
            $isSel = in_array($country['code'], $editing['countries']??[]);
          ?>
            <div class="country-item <?= $isSel?'selected':'' ?>" data-code="<?= htmlspecialchars($country['code']) ?>">
              <img class="country-flag" src="https://flagcdn.com/w40/<?= strtolower($country['code']==='UK'?'gb':$country['code']) ?>.png" alt="<?= $country['code'] ?>">
              <span class="country-code"><?= htmlspecialchars($country['code']) ?></span>
              <span class="country-name"><?= htmlspecialchars($country['name']) ?></span>
            </div>
          <?php endforeach; ?>
          </div>
          <div id="countryInputs"></div>
        </div>
      </div>

      <div class="tab-content cmp-panel-body" data-content="3">
        <div class="form-group">
          <label class="form-label">Ad Schedule (UTC)</label>
          <div style="font-size:12px;color:var(--text-2);margin-bottom:12px">Click cells to activate time slots. Leave all empty to run 24/7.</div>
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
            <button type="button" class="btn btn-secondary btn-sm" onclick="selectBusinessHours()">Business hours (Mon-Fri 9–17)</button>
          </div>
        </div>
      </div>

      <div class="tab-content cmp-panel-body" data-content="4">
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

      <div class="tab-content cmp-panel-body" data-content="5">
        <div class="form-group">
          <label class="form-label">Total Budget (USD) *</label>
          <div style="position:relative">
            <span style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-2);font-weight:600">$</span>
            <input type="number" name="budget" id="f_budget" class="form-control" style="padding-left:26px" min="1" step="0.01" value="<?= htmlspecialchars($editing['budget']??'') ?>" placeholder="100.00">
          </div>
          <div class="form-hint">Campaign pauses automatically when budget is reached</div>
        </div>
        <div class="alert alert-info" style="font-size:13px">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          Your current balance: <strong style="margin-left:4px"><?= fmtMoney($user['balance']) ?></strong>
        </div>
        <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:var(--r-sm);padding:14px;margin-top:8px">
          <div style="font-size:12px;color:var(--text-2);margin-bottom:6px">Estimated reach</div>
          <div style="font-size:22px;font-weight:700" id="estClicks">0</div>
          <div style="font-size:12px;color:var(--text-2);margin-top:2px">estimated clicks at your CPC bid</div>
        </div>
      </div>

      <div class="tab-content cmp-panel-body" data-content="6">
        <div style="margin-bottom:18px">
          <div style="font-size:16px;font-weight:700;margin-bottom:14px">Campaign Summary</div>
          <div class="summary-grid" id="summaryArea"></div>
        </div>
        <div id="reviewCountries" style="margin-bottom:16px"></div>
        <div class="alert alert-info" style="font-size:12.5px">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          Campaign goes to admin review after submission. You'll get a unique Campaign ID immediately.
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

<script>
var totalSteps = 7;
var currentStep = 0;

// Create error banner dynamically so it's always available
(function() {
  var errEl = document.createElement('div');
  errEl.id = 'tabError';
  errEl.className = 'alert alert-danger';
  errEl.style.cssText = 'display:none;margin:0 0 16px';
  var form = document.getElementById('wizardForm');
  if (form) form.parentNode.insertBefore(errEl, form);
})();
var selectedCountries = <?= json_encode(array_values($editing['countries']??[])) ?>;

var stepTitles = ['Campaign Basics','Select Creative','Geo Targeting','Ad Schedule','Filters','Budget & Reach','Review & Submit'];
var stepDescs = ['Name your campaign and set your CPC bid','Choose the HTML creative for this campaign','Select which countries to target','Choose when your ads should run (optional)','Set IP and domain filters (optional)','Set your total spend budget','Review all settings before submitting'];

function goStep(n) {
  if (n > currentStep) {
    for (var i = currentStep; i < n; i++) {
      if (!validateStep(i)) return;
    }
  }
  showStep(n);
}

function showStep(n) {
  document.querySelectorAll('.tab-content').forEach(function(c,i){ c.classList.toggle('active', i===n); });
  document.querySelectorAll('.cmp-step-item').forEach(function(s,i){
    s.classList.remove('current','done');
    if (i === n) s.classList.add('current');
    else if (i < n) s.classList.add('done');
  });
  document.getElementById('stepTitle').textContent = stepTitles[n];
  document.getElementById('stepDesc').textContent = stepDescs[n];
  document.getElementById('progressBar').style.width = ((n+1)/totalSteps*100) + '%';
  document.getElementById('btnBack').style.visibility = n === 0 ? 'hidden' : 'visible';
  var btnNext = document.getElementById('btnNext');
  if (n === totalSteps - 1) {
    btnNext.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15"><polyline points="20 6 9 17 4 12"/></svg> <?= $editing ? "Update Campaign" : "Create Campaign" ?>';
    btnNext.onclick = function() { submitForm(); };
  } else {
    btnNext.innerHTML = 'Continue <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="9 18 15 12 9 6"/></svg>';
    btnNext.onclick = nextStep;
  }
  hideError();
  currentStep = n;
  if (n === 6) buildSummary();
  if (n === 5) updateEstimate();
  window.scrollTo({top:0, behavior:'smooth'});
}

function nextStep() { if (validateStep(currentStep)) showStep(currentStep + 1); }
function prevStep() { if (currentStep > 0) showStep(currentStep - 1); }

function showError(msg) {
  var el = document.getElementById('tabError');
  if (!el) { alert(msg); return; }
  el.textContent = msg; el.style.display = 'flex';
  el.scrollIntoView({behavior:'smooth',block:'center'});
}
function hideError() {
  var el = document.getElementById('tabError');
  if (el) el.style.display = 'none';
}

function validateStep(n) {
  hideError();
  if (n === 0) {
    if (!document.getElementById('f_name').value.trim()) { showError('Please enter a campaign name'); return false; }
    var cpc = parseFloat(document.getElementById('f_cpc').value);
    if (!cpc || cpc <= 0) { showError('Please enter a valid CPC bid'); return false; }
  }
  if (n === 1) {
    var cr = document.querySelector('input[name="creative_id"]:checked');
    if (!cr) { showError('Please select a creative'); return false; }
  }
  if (n === 2) {
    if (selectedCountries.length === 0) { showError('Select at least one country'); return false; }
  }
  if (n === 5) {
    var budget = parseFloat(document.getElementById('f_budget').value);
    if (!budget || budget < 1) { showError('Budget must be at least $1.00'); return false; }
  }
  return true;
}

function updateCountryInputs() {
  var c = document.getElementById('countryInputs');
  c.innerHTML = '';
  selectedCountries.forEach(function(code){
    var i = document.createElement('input');
    i.type='hidden'; i.name='countries[]'; i.value=code;
    c.appendChild(i);
  });
  document.getElementById('countryCount').textContent = selectedCountries.length + ' selected';
}

document.querySelectorAll('#countryGrid .country-item').forEach(function(item){
  item.addEventListener('click', function(){
    var code = item.dataset.code;
    var idx = selectedCountries.indexOf(code);
    if (idx === -1) { selectedCountries.push(code); item.classList.add('selected'); }
    else { selectedCountries.splice(idx,1); item.classList.remove('selected'); }
    updateCountryInputs();
  });
});

function selectAllCountries() {
  selectedCountries = [];
  document.querySelectorAll('#countryGrid .country-item').forEach(function(item){
    selectedCountries.push(item.dataset.code); item.classList.add('selected');
  });
  updateCountryInputs();
}
function clearAllCountries() {
  selectedCountries = [];
  document.querySelectorAll('#countryGrid .country-item').forEach(function(item){ item.classList.remove('selected'); });
  updateCountryInputs();
}
updateCountryInputs();

document.querySelectorAll('.creative-card').forEach(function(card){
  card.addEventListener('click', function(){
    document.querySelectorAll('.creative-card').forEach(function(c){
      c.style.borderColor=''; c.style.background='';
    });
    card.style.borderColor='rgba(255,200,0,.35)'; card.style.background='var(--yellow-dim)';
    card.querySelector('input[type="radio"]').checked = true;
  });
});

document.querySelectorAll('.radio-group').forEach(function(grp){
  grp.querySelectorAll('.radio-option').forEach(function(opt){
    opt.addEventListener('click', function(e){
      if (e.target.tagName === 'INPUT') return;
      grp.querySelectorAll('.radio-option').forEach(function(o){ o.classList.remove('selected'); });
      opt.classList.add('selected');
      var rad = opt.querySelector('input');
      if (rad) rad.checked = true;
    });
  });
});

function toggleCell(cell) { cell.classList.toggle('active'); }
function selectAllSchedule() { document.querySelectorAll('.schedule-cell').forEach(function(c){ c.classList.add('active'); }); }
function clearSchedule() { document.querySelectorAll('.schedule-cell').forEach(function(c){ c.classList.remove('active'); }); }
function selectBusinessHours() {
  document.querySelectorAll('.schedule-cell').forEach(function(c){
    var parts = c.dataset.key.split('_').map(Number);
    if (parts[0] < 5 && parts[1] >= 9 && parts[1] < 17) c.classList.add('active');
    else c.classList.remove('active');
  });
}
function buildSchedulePayload() {
  var c = document.getElementById('scheduleInputs'); c.innerHTML = '';
  document.querySelectorAll('.schedule-cell.active').forEach(function(s){
    var i = document.createElement('input'); i.type='hidden'; i.name='schedule[]'; i.value=s.dataset.key;
    c.appendChild(i);
  });
}

function renderTags(containerId, hiddenId, items, type) {
  var c = document.getElementById(containerId); c.innerHTML = '';
  if (!items.length) { c.innerHTML='<div style="color:var(--text-3);font-size:12px;padding:6px">No '+type+' added</div>'; return; }
  items.forEach(function(val,i){
    var tag = document.createElement('div'); tag.className='ip-tag';
    tag.innerHTML='<span>'+val+'</span><span class="remove-tag" data-idx="'+i+'">&times;</span>';
    tag.querySelector('.remove-tag').addEventListener('click', function(){
      items.splice(i,1);
      document.getElementById(hiddenId).value = items.join(',');
      renderTags(containerId, hiddenId, items, type);
    });
    c.appendChild(tag);
  });
}

var ipItems = <?= json_encode(array_values($editing['ip_list']??[])) ?>;
var domainItems = <?= json_encode(array_values($editing['domain_list']??[])) ?>;
renderTags('ipListDisplay','ipListHidden',ipItems,'IPs');
renderTags('domainListDisplay','domainListHidden',domainItems,'domains');

function addIp() {
  var v = document.getElementById('ipInput').value.trim(); if (!v) return;
  ipItems.push(v); document.getElementById('ipListHidden').value=ipItems.join(',');
  renderTags('ipListDisplay','ipListHidden',ipItems,'IPs'); document.getElementById('ipInput').value='';
}
function addDomain() {
  var v = document.getElementById('domainInput').value.trim(); if (!v) return;
  domainItems.push(v); document.getElementById('domainListHidden').value=domainItems.join(',');
  renderTags('domainListDisplay','domainListHidden',domainItems,'domains'); document.getElementById('domainInput').value='';
}
document.getElementById('ipInput').addEventListener('keypress', function(e){ if(e.key==='Enter'){e.preventDefault();addIp();} });
document.getElementById('domainInput').addEventListener('keypress', function(e){ if(e.key==='Enter'){e.preventDefault();addDomain();} });

function updateEstimate() {
  var cpc = parseFloat(document.getElementById('f_cpc').value||0);
  var budget = parseFloat(document.getElementById('f_budget').value||0);
  var est = cpc > 0 && budget > 0 ? Math.round(budget/cpc) : 0;
  var el = document.getElementById('estClicks');
  if (el) el.textContent = '~' + est.toLocaleString();
}
document.getElementById('f_budget').addEventListener('input', updateEstimate);

function buildSummary() {
  buildSchedulePayload(); updateCountryInputs();
  var name = document.getElementById('f_name').value || '-';
  var cpc = parseFloat(document.getElementById('f_cpc').value||0).toFixed(4);
  var budget = parseFloat(document.getElementById('f_budget').value||0).toFixed(2);
  var cr = document.querySelector('input[name="creative_id"]:checked');
  var crName = cr ? (cr.closest('label').querySelector('.creative-card div') || {textContent:cr.value}).textContent : '-';
  var schedCnt = document.querySelectorAll('.schedule-cell.active').length;
  var ipMode = document.querySelector('input[name="ip_mode"]:checked');
  var domMode = document.querySelector('input[name="domain_mode"]:checked');
  var est = cpc > 0 && budget > 0 ? Math.round(budget/cpc) : 0;

  var items = [
    ['Name', name],['CPC Bid','$'+cpc],['Total Budget','$'+budget],
    ['Countries', selectedCountries.length+' selected'],
    ['Schedule', schedCnt===0?'24/7':schedCnt+' slots'],
    ['IP Filter', ipMode?ipMode.value:'off'],
    ['Domain Filter', domMode?domMode.value:'off'],
    ['Est. Clicks','~'+est.toLocaleString()]
  ];

  document.getElementById('summaryArea').innerHTML = items.map(function(it){
    return '<div class="summary-item"><div class="summary-label">'+it[0]+'</div><div class="summary-value" style="font-size:14px">'+it[1]+'</div></div>';
  }).join('');

  document.getElementById('reviewCountries').innerHTML = selectedCountries.length > 0
    ? '<div style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;font-weight:600">Targeted Countries</div><div style="display:flex;gap:5px;flex-wrap:wrap">'
      + selectedCountries.map(function(c){ return '<span class="badge badge-yellow">'+c+'</span>'; }).join('')+'</div>'
    : '';
}

function submitForm() {
  buildSchedulePayload(); updateCountryInputs();
  for (var i = 0; i < 6; i++) {
    if (!validateStep(i)) { showStep(i); return; }
  }
  document.getElementById('wizardForm').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
