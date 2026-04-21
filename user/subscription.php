<?php
require_once __DIR__ . '/../includes/user_header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Subscription</div>
    <div class="page-subtitle">Your current plan status</div>
  </div>
</div>

<div style="max-width:540px;margin:0 auto;text-align:center;padding:48px 24px">

  <div style="width:72px;height:72px;background:var(--yellow-dim);border:1px solid rgba(255,200,0,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px">
    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--yellow)" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
  </div>

  <div style="font-size:22px;font-weight:700;margin-bottom:8px;letter-spacing:-.3px">You're Subscribed</div>
  <div style="font-size:14px;color:var(--text-2);margin-bottom:28px;line-height:1.6">
    You have full access to the Advora advertising platform.<br>
    All features are unlocked and available to use.
  </div>

  <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(0,229,153,.1);color:var(--green);border:1px solid rgba(0,229,153,.2);padding:10px 22px;border-radius:24px;font-size:14px;font-weight:700;margin-bottom:32px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    Active Subscription
  </div>

  <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r);padding:20px;text-align:left">
    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.6px;color:var(--text-3);font-weight:700;margin-bottom:14px">What's Included</div>
    <?php
    $features = ['Campaign creation & management','HTML creative uploads','Real-time dashboard analytics','Historical metrics & CSV export','Geo targeting & scheduling','IP & domain filtering','Full admin support'];
    foreach ($features as $f): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px;color:var(--text-2)">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
      <?= htmlspecialchars($f) ?>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="margin-top:20px;font-size:12.5px;color:var(--text-3)">
    Need to change your plan? Contact your admin.
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
