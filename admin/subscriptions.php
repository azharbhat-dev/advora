<?php
require_once __DIR__ . '/../includes/admin_header.php';

$defaultPlans = [
    ['id'=>'starter','name'=>'Starter','price'=>150,'campaigns'=>2,'tagline'=>'Perfect for getting started','features'=>['2 Active Campaigns','HTML Creative Uploads','Basic Analytics','Email Support'],'active'=>true],
    ['id'=>'pro','name'=>'Pro','price'=>300,'campaigns'=>3,'tagline'=>'For growing advertisers','features'=>['3 Active Campaigns','Priority Review','Advanced Analytics','URL Tracking','Priority Support'],'active'=>true],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $plans  = readJson('subscriptions', $defaultPlans);

    if ($action === 'create') {
        $name        = trim($_POST['plan_name'] ?? '');
        $price       = (float)($_POST['price'] ?? 0);
        $campaigns   = (int)($_POST['campaigns'] ?? 1);
        $tagline     = trim($_POST['tagline'] ?? '');
        $featuresRaw = trim($_POST['features'] ?? '');
        $features    = array_values(array_filter(array_map('trim', explode("\n", $featuresRaw))));
        if ($name && $price > 0) {
            $plans[] = [
                'id'        => 'plan_' . strtolower(preg_replace('/[^a-z0-9]/i','_',$name)) . '_' . time(),
                'name'      => $name,
                'price'     => $price,
                'campaigns' => $campaigns,
                'tagline'   => $tagline,
                'features'  => $features,
                'active'    => true,
            ];
            writeJson('subscriptions', $plans);
            flash('Plan created', 'success');
        } else {
            flash('Name and price are required', 'error');
        }
    } elseif ($action === 'toggle') {
        $planId = $_POST['plan_id'] ?? '';
        foreach ($plans as &$p) {
            if ($p['id'] === $planId) { $p['active'] = !($p['active'] ?? true); break; }
        }
        writeJson('subscriptions', $plans);
        flash('Plan updated', 'success');
    } elseif ($action === 'delete') {
        $planId = $_POST['plan_id'] ?? '';
        $plans  = array_values(array_filter($plans, fn($p) => $p['id'] !== $planId));
        writeJson('subscriptions', $plans);
        flash('Plan deleted', 'success');
    }
    safeRedirect('/admin/subscriptions.php');
}

// Load plans from DB; seed defaults on first visit if table is empty
$plans = readJson('subscriptions', []);
if (empty($plans)) {
    writeJson('subscriptions', $defaultPlans);
    $plans = $defaultPlans;
}
?>

<div class="page-header">
  <div>
    <div class="page-title">Subscription Plans</div>
    <div class="page-subtitle">Create and manage subscription plans for users</div>
  </div>
  <button class="btn btn-primary" onclick="openModal('newPlanModal')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add Plan
  </button>
</div>

<div class="alert alert-info" style="font-size:13px">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
  All users are currently subscribed by default. Plans below are displayed to users on their subscription page.
</div>

<div class="sub-grid">
  <?php foreach ($plans as $plan): ?>
  <div class="sub-card <?= $plan['id']==='pro'?'featured':'' ?>" style="<?= empty($plan['active'])?'opacity:.5':'' ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <div class="sub-name"><?= htmlspecialchars($plan['name']) ?></div>
      <span class="badge <?= !empty($plan['active'])?'badge-success':'badge-muted' ?>"><?= !empty($plan['active'])?'Active':'Inactive' ?></span>
    </div>
    <div class="sub-price">$<?= $plan['price'] ?><span>/mo</span></div>
    <div class="sub-tagline"><?= htmlspecialchars($plan['tagline']??'') ?></div>
    <div style="margin:12px 0">
      <?php foreach (($plan['features']??[]) as $feat): ?>
      <div class="sub-feature">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        <?= htmlspecialchars($feat) ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:8px;margin-top:14px">
      <form method="POST" style="flex:1">
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
        <button type="submit" class="btn btn-secondary btn-sm" style="width:100%"><?= !empty($plan['active'])?'Deactivate':'Activate' ?></button>
      </form>
      <form method="POST" onsubmit="return confirm('Delete this plan?')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm">Del</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($plans)): ?>
  <div class="card" style="grid-column:1/-1">
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      <h3>No plans yet</h3>
      <p>Create your first subscription plan</p>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="modal" id="newPlanModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">Create Subscription Plan</div>
      <div class="modal-close" onclick="closeModal('newPlanModal')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </div>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Plan Name *</label>
          <input type="text" name="plan_name" class="form-control" required placeholder="e.g. Pro">
        </div>
        <div class="form-group">
          <label class="form-label">Price (USD/mo) *</label>
          <input type="number" name="price" class="form-control" required min="1" step="0.01" placeholder="150">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Campaign Limit</label>
        <input type="number" name="campaigns" class="form-control" min="1" value="2" placeholder="2">
      </div>
      <div class="form-group">
        <label class="form-label">Tagline</label>
        <input type="text" name="tagline" class="form-control" placeholder="Perfect for getting started">
      </div>
      <div class="form-group">
        <label class="form-label">Features (one per line)</label>
        <textarea name="features" class="form-control" placeholder="2 Active Campaigns&#10;HTML Creative Uploads&#10;Basic Analytics"></textarea>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('newPlanModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Plan</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
