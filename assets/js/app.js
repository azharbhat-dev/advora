/* =========================================================
   Advora — app.js
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

function n(v)     { return Number(v).toLocaleString(); }
function money(v) { const n = parseFloat(v); return '$' + (isNaN(n) ? '0.00' : n.toFixed(2)); }
function pct(a,b) { const r = (parseFloat(b)||0) > 0 ? ((parseFloat(a)||0)/(parseFloat(b)||1)*100) : 0; return (isNaN(r)?0:r).toFixed(2)+'%'; }

const BADGE_CLASS = {
  active:'badge-success', pending:'badge-pending', paused:'badge-muted',
  review:'badge-info', rejected:'badge-danger', approved:'badge-success',
  disabled:'badge-danger', enabled:'badge-success'
};
function badgeClass(s)  { return BADGE_CLASS[s] || 'badge-muted'; }
function badgeLabel(s)  { return s === 'review' ? 'Under Review' : s; }

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
    el.className   = 'badge ' + badgeClass(status);
    el.textContent = badgeLabel(status);
  });
}

// ── Sound System ─────────────────────────────────────────
// All sounds generated via Web Audio API — no external files needed

const AudioCtx = window.AudioContext || window.webkitAudioContext;
let _audioCtx  = null;

function getAudioCtx() {
  if (!_audioCtx) _audioCtx = new AudioCtx();
  return _audioCtx;
}

// User notification chime — soft two-tone ding
function playNotifSound() {
  try {
    const ctx = getAudioCtx();
    const notes = [880, 1100]; // A5, C#6 — pleasant chime
    notes.forEach((freq, i) => {
      const osc  = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.type      = 'sine';
      osc.frequency.setValueAtTime(freq, ctx.currentTime + i * 0.12);
      gain.gain.setValueAtTime(0, ctx.currentTime + i * 0.12);
      gain.gain.linearRampToValueAtTime(0.18, ctx.currentTime + i * 0.12 + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.12 + 0.35);
      osc.start(ctx.currentTime + i * 0.12);
      osc.stop(ctx.currentTime  + i * 0.12 + 0.35);
    });
  } catch(e) {}
}

// Admin alert sound — sharper ping for incoming submissions
function playAdminSound() {
  try {
    const ctx  = getAudioCtx();
    const osc  = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.type = 'triangle';
    osc.frequency.setValueAtTime(660, ctx.currentTime);
    osc.frequency.linearRampToValueAtTime(880, ctx.currentTime + 0.08);
    gain.gain.setValueAtTime(0.22, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
    osc.start(ctx.currentTime);
    osc.stop(ctx.currentTime + 0.4);
  } catch(e) {}
}

// Unlock audio on first user gesture (browser autoplay policy)
let _audioUnlocked = false;
function unlockAudio() {
  if (_audioUnlocked) return;
  _audioUnlocked = true;
  try {
    const ctx = getAudioCtx();
    const buf = ctx.createBuffer(1,1,22050);
    const src = ctx.createBufferSource();
    src.buffer = buf;
    src.connect(ctx.destination);
    src.start(0);
  } catch(e) {}
}
document.addEventListener('click',      unlockAudio, { once: true });
document.addEventListener('keydown',    unlockAudio, { once: true });
document.addEventListener('touchstart', unlockAudio, { once: true });

// ── Notification bell updater (user side) ─────────────────
let _lastUnreadCount = -1; // -1 = not yet initialised

function updateNotifBell(count) {
  // Update bell badge in topbar
  const badge = document.querySelector('.topbar-badge');
  const navBadge = document.querySelector('.nav-item[href="/user/notifications.php"] .nav-badge');

  if (badge) {
    if (count > 0) {
      badge.textContent    = count > 9 ? '9+' : count;
      badge.style.display  = 'flex';
    } else {
      badge.style.display  = 'none';
    }
  }
  if (navBadge) {
    if (count > 0) {
      navBadge.textContent   = count;
      navBadge.style.display = '';
    } else {
      navBadge.style.display = 'none';
    }
  }

  // Play sound only when count genuinely increases
  if (_lastUnreadCount >= 0 && count > _lastUnreadCount) {
    playNotifSound();
  }
  _lastUnreadCount = count;
}

// ── Admin pending tracker ─────────────────────────────────
let _lastPendingTotal = -1;

function checkAdminNewSubmissions(pending) {
  const total = (pending.campaigns || 0) + (pending.creatives || 0) + (pending.topups || 0);
  if (_lastPendingTotal >= 0 && total > _lastPendingTotal) {
    playAdminSound();
  }
  _lastPendingTotal = total;
}

// ── Apply user data ───────────────────────────────────────
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
    const _imp = parseFloat(t.impressions)||0, _vw = parseFloat(t.views)||0;
    setLive('total-ctr', _imp > 0 ? ((_vw/_imp)*100).toFixed(2)+'%' : '0.00%');
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
        const pctVal = c.budget > 0 ? Math.min(100, (c.spent/c.budget)*100).toFixed(1) : '0.0';
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

  // Update notification bell + play sound if new ones arrived
  if (d.unread_notifications !== undefined) {
    updateNotifBell(d.unread_notifications);
  }

  window.dispatchEvent(new CustomEvent('liveStatsUpdate', { detail: d }));
}

// ── Apply admin data ──────────────────────────────────────
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

    // Play sound when new submissions arrive
    checkAdminNewSubmissions(p);
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

  // Admin notification bell
  if (d.unread_admin_notifs !== undefined) {
    updateAdminNotifBell(d.unread_admin_notifs);
  }
}

let _lastAdminNotifCount = -1;

function updateAdminNotifBell(count) {
  // Update bell badge in admin topbar
  const bell  = document.getElementById('admin-notif-bell');
  const badge = document.getElementById('admin-notif-badge');
  if (badge) {
    badge.textContent   = count > 9 ? '9+' : count;
    badge.style.display = count > 0 ? 'flex' : 'none';
  }
  // Update nav badge
  const navBadge = document.getElementById('badge-admin-notifs');
  if (navBadge) {
    navBadge.textContent   = count > 9 ? '9+' : count;
    navBadge.style.display = count > 0 ? '' : 'none';
  }
  // Play sound when count increases
  if (_lastAdminNotifCount >= 0 && count > _lastAdminNotifCount) {
    playAdminNotifSound();
  }
  _lastAdminNotifCount = count;
}

// Distinct sound for user-activity admin notifications (softer than pending-item sound)
function playAdminNotifSound() {
  try {
    const ctx   = getAudioCtx();
    const notes = [660, 880, 1100];
    notes.forEach((freq, i) => {
      const osc  = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.type = 'sine';
      osc.frequency.setValueAtTime(freq, ctx.currentTime + i * 0.1);
      gain.gain.setValueAtTime(0, ctx.currentTime + i * 0.1);
      gain.gain.linearRampToValueAtTime(0.14, ctx.currentTime + i * 0.1 + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.1 + 0.3);
      osc.start(ctx.currentTime + i * 0.1);
      osc.stop(ctx.currentTime  + i * 0.1 + 0.3);
    });
  } catch(e) {}
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