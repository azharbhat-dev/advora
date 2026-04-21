/* =========================================================
   Advora — app.js
   Universal live-update system via data-live attributes.

   Keys used in templates:
     data-live="key"           → textContent updated
     data-live-badge="key"     → className + textContent updated
     data-live-money="key"     → formatted as $X.XX
     data-live-balance         → topbar balance pill

   Metric naming:
     impressions — raw ad loads
     views       — quality views (good_hits in DB)
     hits        — user interactions (clicks in DB)
     spend       — money spent
   ========================================================= */

// ── Modals ───────────────────────────────────────────────
function openModal(id)  { const m=document.getElementById(id); if(m) m.classList.add('active'); }
function closeModal(id) { const m=document.getElementById(id); if(m) m.classList.remove('active'); }
document.addEventListener('click', e => { if(e.target.classList.contains('modal')) e.target.classList.remove('active'); });

// ── Copy ─────────────────────────────────────────────────
function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    if (!btn) return;
    const o = btn.innerHTML;
    btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Copied';
    setTimeout(() => { btn.innerHTML = o; }, 1500);
  });
}

// ── QR ───────────────────────────────────────────────────
function genQR(container, text) {
  container.innerHTML = '';
  const img = document.createElement('img');
  img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(text);
  img.style.cssText = 'width:100%;height:100%';
  container.appendChild(img);
}

// ── Flash auto-dismiss ───────────────────────────────────
document.querySelectorAll('.alert:not(.alert-info):not(.alert-warning)').forEach(el => {
  if (el.closest('.modal') || el.closest('form')) return;
  setTimeout(() => { el.style.transition='opacity .4s'; el.style.opacity='0'; setTimeout(()=>el.remove(),400); }, 5000);
});

// ── Helpers ──────────────────────────────────────────────
const IS_ADMIN = document.body.dataset.role === 'admin';
const POLL_MS  = 3500;

function n(v)   { return Number(v).toLocaleString(); }
function money(v) { return '$' + parseFloat(v).toFixed(2); }
function pct(a,b) { return b > 0 ? ((a/b)*100).toFixed(2) + '%' : '0.00%'; }

const BADGE_CLASS = {
  active:   'badge-success',
  pending:  'badge-pending',
  paused:   'badge-muted',
  review:   'badge-info',
  rejected: 'badge-danger',
  approved: 'badge-success',
  disabled: 'badge-danger',
  enabled:  'badge-success'
};
function badgeClass(s) { return BADGE_CLASS[s] || 'badge-muted'; }

// Badge label — maps internal status to display label
function badgeLabel(s) {
  if (s === 'review') return 'Under Review';
  return s;
}

function setLive(key, value) {
  document.querySelectorAll('[data-live="'+key+'"]').forEach(el => { if (el.textContent !== String(value)) el.textContent = value; });
}
function setLiveMoney(key, value) {
  const v = money(value);
  document.querySelectorAll('[data-live-money="'+key+'"]').forEach(el => { if (el.textContent !== v) el.textContent = v; });
}
function setLiveBadge(key, status) {
  document.querySelectorAll('[data-live-badge="'+key+'"]').forEach(el => {
    if (el.dataset.currentStatus === status) return;
    el.dataset.currentStatus = status;
    el.className = 'badge ' + badgeClass(status);
    el.textContent = badgeLabel(status);
  });
}

// ── Apply user data ──────────────────────────────────────
function applyUser(d) {
  if (d.balance !== undefined) {
    document.querySelectorAll('[data-live-balance]').forEach(el => { el.textContent = money(d.balance); });
  }

  if (d.totals) {
    const t = d.totals;
    setLive('total-impressions', n(t.impressions));
    setLive('total-views',       n(t.views));
    setLive('total-hits',        n(t.hits));
    setLiveMoney('total-spent',  t.spent);
    setLive('total-ctr', t.impressions > 0 ? ((t.views / t.impressions)*100).toFixed(2)+'%' : '0.00%');
  }

  if (d.campaigns) {
    Object.entries(d.campaigns).forEach(([cid, c]) => {
      const p = 'camp:' + cid + ':';
      setLive(p+'impressions', n(c.impressions));
      setLive(p+'views',       n(c.views));
      setLive(p+'hits',        n(c.hits));
      setLiveMoney(p+'spent',  c.spent);
      setLive(p+'ctr',         pct(c.views, c.impressions));
      setLiveBadge(p+'status', c.status);
      if (c.budget > 0) {
        const pctVal = Math.min(100, (c.spent/c.budget)*100).toFixed(1);
        document.querySelectorAll('[data-live="'+p+'budget-pct"]').forEach(el => { el.style.width = pctVal+'%'; });
      }
    });
  }

  if (d.creatives) {
    Object.entries(d.creatives).forEach(([crid, cr]) => { setLiveBadge('cr:'+crid+':status', cr.status); });
  }

  if (d.topups) {
    Object.entries(d.topups).forEach(([tid, t]) => { setLiveBadge('topup:'+tid+':status', t.status); });
  }

  window.dispatchEvent(new CustomEvent('liveStatsUpdate', { detail: d }));
}

// ── Apply admin data ─────────────────────────────────────
function applyAdmin(d) {
  if (d.totals) {
    const t = d.totals;
    setLive('total-impressions', n(t.impressions));
    setLive('total-views',       n(t.views));
    setLive('total-hits',        n(t.hits));
    setLiveMoney('total-spent',  t.spent);
    setLiveMoney('total-balance',t.balance);
    setLive('total-users',       n(t.users));
    setLive('total-campaigns',   n(t.campaigns));
  }

  if (d.pending) {
    const p     = d.pending;
    const total = p.campaigns + p.creatives + p.topups;
    setLive('pending-total',     total);
    setLive('pending-campaigns', p.campaigns);
    setLive('pending-creatives', p.creatives);
    setLive('pending-topups',    p.topups);
    ['campaigns','creatives','topups'].forEach(k => {
      const el = document.getElementById('badge-'+k);
      if (!el) return;
      el.textContent   = p[k];
      el.style.display = p[k] > 0 ? '' : 'none';
    });
  }

  if (d.campaigns) {
    Object.entries(d.campaigns).forEach(([cid, c]) => {
      const p = 'camp:' + cid + ':';
      setLive(p+'impressions', n(c.impressions));
      setLive(p+'views',       n(c.views));
      setLive(p+'hits',        n(c.hits));
      setLiveMoney(p+'spent',  c.spent);
      setLiveBadge(p+'status', c.status);
    });
  }

  if (d.users) {
    Object.entries(d.users).forEach(([uid, u]) => {
      setLiveMoney('user:'+uid+':balance', u.balance);
      setLiveBadge('user:'+uid+':status',  u.disabled ? 'disabled' : 'active');
    });
  }

  if (d.creatives) {
    Object.entries(d.creatives).forEach(([crid, cr]) => { setLiveBadge('cr:'+crid+':status', cr.status); });
  }

  if (d.topups) {
    Object.entries(d.topups).forEach(([tid, t]) => { setLiveBadge('topup:'+tid+':status', t.status); });
  }
}

// ── Poll loop ─────────────────────────────────────────────
let pollTimer = null;
async function pollOnce() {
  try {
    const url = IS_ADMIN ? '/api/admin_stats.php' : '/api/live_stats.php';
    const r   = await fetch(url + '?t=' + Date.now());
    if (!r.ok) return;
    const d = await r.json();
    if (!d.success) return;
    IS_ADMIN ? applyAdmin(d) : applyUser(d);
  } catch(e) {}
}

if (document.getElementById('sidebar')) {
  pollOnce();
  pollTimer = setInterval(pollOnce, POLL_MS);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) { clearInterval(pollTimer); }
    else { pollOnce(); pollTimer = setInterval(pollOnce, POLL_MS); }
  });
}