<?php
require_once __DIR__ . '/../includes/user_header.php';

$settings = getSettings();
$wallets  = $settings['wallets'];

// Only keep BTC and USDT(TRC20)
$cryptoWallets = [];
foreach ($wallets as $code => $w) {
    if (in_array($code, ['BTC','TRC20'])) $cryptoWallets[$code] = $w;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method  = $_POST['method']  ?? '';
    $amount  = (float)($_POST['amount'] ?? 0);
    $network = $_POST['network'] ?? '';
    $txid    = trim($_POST['txid'] ?? '');

    if ($method !== 'crypto') {
        flash('This payment method is restricted. Please contact your manager.', 'error');
        safeRedirect('/user/funds.php');
    }
    if ($amount < 100) {
        flash('Minimum deposit is $100.00', 'error');
        safeRedirect('/user/funds.php');
    }
    if (!isset($cryptoWallets[$network])) {
        flash('Invalid network selected', 'error');
        safeRedirect('/user/funds.php');
    }
    if (!$txid) {
        flash('Transaction hash is required', 'error');
        safeRedirect('/user/funds.php');
    }

    $screenshotFile = null;
    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['screenshot'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            flash('Screenshot must be an image file', 'error');
            safeRedirect('/user/funds.php');
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            flash('Screenshot too large (max 5MB)', 'error');
            safeRedirect('/user/funds.php');
        }
        $ssDir = DATA_PATH . '/topup_screenshots/';
        if (!is_dir($ssDir)) mkdir($ssDir, 0755, true);
        $screenshotFile = 'SS-' . strtoupper(substr(md5(uniqid(mt_rand(),true)),0,8)) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $ssDir . $screenshotFile);
    }

    $fee         = round($amount * 0.035, 2);
    $amountAfter = round($amount - $fee, 2);

    $topups   = readJson(TOPUPS_FILE);
    $topups[] = [
        'id'               => 'TX-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8)),
        'user_id'          => $user['id'],
        'username'         => $user['username'],
        'network'          => $network,
        'address'          => $cryptoWallets[$network]['address'],
        'amount'           => $amount,
        'fee'              => $fee,
        'amount_after_fee' => $amountAfter,
        'txid'             => $txid,
        'screenshot'       => $screenshotFile,
        'status'           => 'pending',
        'created_at'       => time()
    ];
    writeJson(TOPUPS_FILE, $topups);

    addNotification($user['id'], 'topup_approved',
        'Deposit Submitted',
        'Your deposit of $' . number_format($amount,2) . ' via ' . $network . ' is under review.'
    );

    flash('Deposit submitted. It is now under review.', 'success');
    safeRedirect('/user/transactions.php');
}
?>

<div class="page-header">
  <div>
    <div class="page-title">Add Funds</div>
    <div class="page-subtitle">Top up your advertising balance</div>
  </div>
</div>

<div class="alert alert-info">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
  Minimum deposit: <strong>$100.00</strong> &nbsp;&middot;&nbsp; Platform fee: <strong>3.5%</strong> deducted from deposit amount
</div>

<!-- ── Step 1: Payment Method ───────────────────────────── -->
<div class="card">
  <div class="card-title" style="margin-bottom:20px">Select Payment Method</div>

  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px">

    <!-- Crypto - active/unlocked -->
    <div class="pm-card active" id="pm_crypto" onclick="selectPayment('crypto')">
      <div class="pm-icon-wrap">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
          <circle cx="16" cy="16" r="16" fill="#F7931A"/>
          <path d="M22.5 14.1c.3-2.1-1.3-3.2-3.5-4l.7-2.8-1.7-.4-.7 2.7-1.4-.3.7-2.7-1.7-.4-.7 2.8-1.1-.3-2.3-.6-.4 1.8s1.3.3 1.2.3c.7.2.8.6.8.9l-1.9 7.7c-.1.2-.3.5-.8.4 0 0-1.2-.3-1.2-.3l-.8 2 2.2.6 1.2.3-.7 2.8 1.7.4.7-2.8 1.4.4-.7 2.7 1.7.4.7-2.8c2.9.5 5-.2 5.9-2.8.7-2-.1-3.2-1.5-3.9 1.1-.3 1.9-1 2.1-2.5zm-3.8 5.3c-.5 2-3.9 1-5 .7l.9-3.5c1.1.3 4.6.8 4.1 2.8zm.5-5.3c-.5 1.8-3.3 1-4.2.7l.8-3.2c.9.2 3.9.7 3.4 2.5z" fill="#fff"/>
        </svg>
      </div>
      <div class="pm-name">Crypto</div>
      <div class="pm-sub">USDT · BTC</div>
      <div class="pm-checkmark"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
    </div>

    <!-- Debit Card - locked -->
    <div class="pm-card pm-locked" onclick="showRestricted(this)">
      <div class="pm-icon-wrap">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
          <rect width="32" height="32" rx="6" fill="#1A1F71"/>
          <rect x="4" y="9" width="24" height="5" fill="#F7B600"/>
          <rect x="4" y="18" width="7" height="4" rx="1" fill="#fff" opacity=".6"/>
          <rect x="13" y="18" width="5" height="4" rx="1" fill="#fff" opacity=".4"/>
        </svg>
      </div>
      <div class="pm-name">Debit Card</div>
      <div class="pm-sub">Visa · MC</div>
      <div class="pm-lock-icon"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
    </div>

    <!-- Credit Card - locked -->
    <div class="pm-card pm-locked" onclick="showRestricted(this)">
      <div class="pm-icon-wrap">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
          <rect width="32" height="32" rx="6" fill="#252525"/>
          <circle cx="13" cy="16" r="8" fill="#EB001B"/>
          <circle cx="19" cy="16" r="8" fill="#F79E1B"/>
          <path d="M16 9.8a8 8 0 0 1 0 12.4A8 8 0 0 1 16 9.8z" fill="#FF5F00"/>
        </svg>
      </div>
      <div class="pm-name">Credit Card</div>
      <div class="pm-sub">Visa · MC</div>
      <div class="pm-lock-icon"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
    </div>

    <!-- Capitalist - locked -->
    <div class="pm-card pm-locked" onclick="showRestricted(this)">
      <div class="pm-icon-wrap">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
          <rect width="32" height="32" rx="6" fill="#0055CC"/>
          <path d="M7 22l4-12h2l2 7 2-7h2l4 12h-2.5l-2.5-8-2.5 8H14l-2.5-8-2.5 8H7z" fill="#fff"/>
        </svg>
      </div>
      <div class="pm-name">Capitalist</div>
      <div class="pm-sub">E-wallet</div>
      <div class="pm-lock-icon"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
    </div>

    <!-- Wire Transfer - locked -->
    <div class="pm-card pm-locked" onclick="showRestricted(this)">
      <div class="pm-icon-wrap">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
          <rect width="32" height="32" rx="6" fill="#1B4332"/>
          <rect x="6" y="10" width="20" height="2" rx="1" fill="#fff"/>
          <rect x="6" y="14" width="20" height="2" rx="1" fill="#fff" opacity=".6"/>
          <rect x="6" y="18" width="12" height="2" rx="1" fill="#fff" opacity=".4"/>
          <circle cx="23" cy="21" r="4" fill="#40916C"/>
          <path d="M21 21l1.5 1.5L25 19.5" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="pm-name">Wire Transfer</div>
      <div class="pm-sub">Bank wire</div>
      <div class="pm-lock-icon"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
    </div>

  </div>

  <!-- Restricted notice -->
  <div id="restrictedNotice" style="display:none;margin-top:16px" class="alert alert-warning">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    This payment method is restricted. Please contact your manager to unlock it.
  </div>
</div>

<!-- ── Step 2 & 3: Crypto flow ──────────────────────────── -->
<div id="cryptoSection">

  <!-- Network selector -->
  <div class="card">
    <div class="card-title" style="margin-bottom:18px">Select Cryptocurrency</div>
    <div style="display:flex;gap:12px;flex-wrap:wrap">

      <!-- USDT -->
      <div class="crypto-opt active" id="co_TRC20" data-network="TRC20" onclick="selectCrypto('TRC20')">
        <div class="co-logo">
          <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
            <circle cx="18" cy="18" r="18" fill="#26A17B"/>
            <path d="M20.3 16.6v-2.6h5.2v-3.1H10.5V14h5.2v2.6C11.5 17 8.1 18 8.1 19.2s3.4 2.2 7.6 2.5v8.7h4.5v-8.7c4.3-.3 7.6-1.3 7.6-2.5s-3.3-2.2-7.5-2.6zm0 4v-.1c-.2 0-1.1.1-2.3.1s-2.1-.1-2.3-.1v.1c-3.8-.2-6.7-1-6.7-1.9s2.9-1.7 6.7-1.9v3c.2 0 1.1.1 2.3.1s2.1-.1 2.3-.1v-3c3.8.2 6.7 1 6.7 1.9s-2.9 1.7-6.7 1.9z" fill="white"/>
          </svg>
        </div>
        <div class="co-info">
          <div class="co-name">USDT</div>
          <div class="co-net">TRC20 Network</div>
        </div>
        <div class="co-check"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
      </div>

      <!-- BTC -->
      <div class="crypto-opt" id="co_BTC" data-network="BTC" onclick="selectCrypto('BTC')">
        <div class="co-logo">
          <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
            <circle cx="18" cy="18" r="18" fill="#F7931A"/>
            <path d="M25.3 15.7c.3-2.3-1.4-3.6-3.9-4.4l.8-3.1-1.9-.5-.8 3c-.5-.1-1-.2-1.5-.4l.8-3-1.9-.5-.8 3.1c-.4-.1-.8-.2-1.2-.3l-2.6-.7-.5 2s1.4.3 1.3.3c.7.2.9.7.9 1.1l-2.1 8.6c-.1.3-.4.6-.9.5 0 0-1.3-.3-1.3-.3l-.9 2.2 2.4.6 1.3.3-.8 3.1 1.9.5.8-3.1 1.5.4-.8 3 1.9.5.8-3.1c3.2.6 5.6-.2 6.6-3.2.8-2.3-.1-3.6-1.7-4.4 1.2-.3 2.1-1.1 2.4-2.8zm-4.3 6c-.6 2.2-4.3 1-5.6.7l1-4c1.3.3 5.1.9 4.6 3.3zm.6-6c-.5 2-3.7 1-4.7.8l.9-3.6c1.1.3 4.4.7 3.8 2.8z" fill="white"/>
          </svg>
        </div>
        <div class="co-info">
          <div class="co-name">Bitcoin</div>
          <div class="co-net">BTC Network</div>
        </div>
        <div class="co-check"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
      </div>

    </div>
  </div>

  <!-- Wallet address -->
  <div class="card">
    <div class="card-title" style="margin-bottom:18px">Send Payment To This Address</div>
    <div style="display:grid;grid-template-columns:180px 1fr;gap:28px;align-items:start" class="wallet-grid">
      <div style="text-align:center">
        <div style="width:160px;height:160px;background:#fff;border-radius:var(--r-sm);padding:8px;margin:0 auto 8px;display:flex;align-items:center;justify-content:center" id="qrContainer"></div>
        <div style="font-size:11px;color:var(--text-3)">Scan with your wallet app</div>
      </div>
      <div>
        <div style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Network</div>
        <div style="font-size:16px;font-weight:700;margin-bottom:16px" id="walletNetworkName"></div>
        <div style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Deposit Address</div>
        <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:var(--r-sm);padding:12px 14px;font-family:'Courier New',monospace;font-size:12px;word-break:break-all;line-height:1.6;margin-bottom:10px" id="walletAddr"></div>
        <button class="copy-btn" onclick="copyText(document.getElementById('walletAddr').textContent.trim(), this)">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          Copy Address
        </button>
        <div class="alert alert-warning" style="margin-top:14px;font-size:12.5px">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Only send <strong id="sendOnlyLabel"></strong> to this address. Sending anything else may result in permanent loss.
        </div>
      </div>
    </div>
  </div>

  <!-- Submit form -->
  <div class="card">
    <div class="card-title" style="margin-bottom:20px">Confirm Your Payment</div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="method"  value="crypto">
      <input type="hidden" name="network" id="selectedNetwork" value="TRC20">

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Amount Sent (USD) *</label>
          <div style="position:relative">
            <span style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-2);font-weight:700">$</span>
            <input type="number" name="amount" id="amountInput" class="form-control"
              style="padding-left:26px" required min="100" step="0.01"
              placeholder="100.00" oninput="updateFeePreview()">
          </div>
          <div class="form-hint">Minimum $100.00</div>
        </div>
        <div class="form-group">
          <label class="form-label">Transaction Hash *</label>
          <input type="text" name="txid" class="form-control" required
            placeholder="Paste TX hash here"
            style="font-family:'Courier New',monospace;font-size:12px">
          <div class="form-hint">The transaction ID from your wallet</div>
        </div>
      </div>

      <!-- Fee breakdown -->
      <div id="feePreview" style="display:none;margin-bottom:20px">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);background:var(--bg-3);border:1px solid var(--border);border-radius:var(--r-sm);overflow:hidden">
          <div style="padding:14px;text-align:center;border-right:1px solid var(--border)">
            <div style="font-size:10.5px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">You Send</div>
            <div style="font-size:18px;font-weight:700" id="fp_send">$0.00</div>
          </div>
          <div style="padding:14px;text-align:center;border-right:1px solid var(--border)">
            <div style="font-size:10.5px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Fee (3.5%)</div>
            <div style="font-size:18px;font-weight:700;color:var(--orange)" id="fp_fee">$0.00</div>
          </div>
          <div style="padding:14px;text-align:center">
            <div style="font-size:10.5px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">You Receive</div>
            <div style="font-size:18px;font-weight:700;color:var(--green)" id="fp_receive">$0.00</div>
          </div>
        </div>
      </div>

      <!-- Screenshot upload -->
      <div class="form-group">
        <label class="form-label">Payment Screenshot <span style="color:var(--text-3);font-weight:400">(optional but recommended)</span></label>
        <div class="upload-zone" id="ssZone" onclick="document.getElementById('ssInput').click()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          <div class="uz-title" id="ssTitle">Click to upload screenshot</div>
          <div class="uz-sub">JPG, PNG, WEBP &middot; Max 5MB</div>
        </div>
        <input type="file" name="screenshot" id="ssInput" accept="image/*" style="display:none">
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px 20px;font-size:15px">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Submit Deposit — Under Review
      </button>
    </form>
  </div>

</div><!-- /cryptoSection -->

<style>
/* Payment method cards */
.pm-card{background:var(--bg-2);border:2px solid var(--border);border-radius:var(--r);padding:18px 14px;cursor:pointer;transition:all .2s;text-align:center;position:relative;user-select:none;display:flex;flex-direction:column;align-items:center;gap:8px}
.pm-card:hover{border-color:rgba(255,200,0,.25);background:var(--bg-3)}
.pm-card.active{border-color:var(--yellow);background:var(--yellow-dim)}
.pm-card.active .pm-checkmark{opacity:1;background:var(--yellow);border-color:var(--yellow);color:#000}
.pm-locked{opacity:.5}
.pm-locked:hover{opacity:.7;border-color:var(--border)}
.pm-icon-wrap{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:var(--bg-3);margin-bottom:4px}
.pm-card.active .pm-icon-wrap{background:rgba(255,200,0,.08)}
.pm-name{font-size:13px;font-weight:700;color:var(--text)}
.pm-sub{font-size:11px;color:var(--text-3)}
.pm-checkmark{position:absolute;top:8px;right:8px;width:18px;height:18px;border-radius:50%;border:2px solid var(--border-2);display:flex;align-items:center;justify-content:center;color:transparent;opacity:0;transition:all .2s}
.pm-lock-icon{position:absolute;top:8px;right:8px;width:18px;height:18px;border-radius:50%;border:1px solid var(--border);background:var(--bg-3);display:flex;align-items:center;justify-content:center;color:var(--text-3)}

/* Crypto options */
.crypto-opt{display:flex;align-items:center;gap:14px;padding:16px 20px;background:var(--bg-2);border:2px solid var(--border);border-radius:var(--r);cursor:pointer;transition:all .2s;min-width:200px;user-select:none;position:relative}
.crypto-opt:hover{border-color:rgba(255,200,0,.3);background:var(--bg-3)}
.crypto-opt.active{border-color:var(--yellow);background:var(--yellow-dim)}
.crypto-opt.active .co-check{background:var(--yellow);border-color:var(--yellow);color:#000}
.co-logo{flex-shrink:0}
.co-info{flex:1}
.co-name{font-size:15px;font-weight:700}
.co-net{font-size:11.5px;color:var(--text-2)}
.co-check{width:22px;height:22px;border-radius:50%;border:2px solid var(--border-2);display:flex;align-items:center;justify-content:center;color:transparent;transition:all .15s;flex-shrink:0}

/* Wallet grid responsive */
@media(max-width:640px){.wallet-grid{grid-template-columns:1fr!important}}
</style>

<script>
const wallets = <?= json_encode($cryptoWallets) ?>;
let selectedNetwork = 'TRC20';

function selectPayment(type) {
  document.querySelectorAll('.pm-card').forEach(c => c.classList.remove('active'));
  document.getElementById('pm_' + type).classList.add('active');
  document.getElementById('restrictedNotice').style.display = 'none';
  document.getElementById('cryptoSection').style.display = 'block';
}

function showRestricted(el) {
  document.querySelectorAll('.pm-card').forEach(c => c.classList.remove('active'));
  document.getElementById('restrictedNotice').style.display = 'flex';
  document.getElementById('cryptoSection').style.display = 'none';
}

function selectCrypto(network) {
  selectedNetwork = network;
  document.querySelectorAll('.crypto-opt').forEach(c => c.classList.remove('active'));
  document.getElementById('co_' + network).classList.add('active');
  document.getElementById('selectedNetwork').value = network;
  updateWalletDisplay();
}

function updateWalletDisplay() {
  const w = wallets[selectedNetwork];
  if (!w) return;
  const names = { TRC20: 'USDT — TRC20 Network', BTC: 'Bitcoin — BTC Network' };
  document.getElementById('walletNetworkName').textContent = names[selectedNetwork] || selectedNetwork;
  document.getElementById('walletAddr').textContent        = w.address;
  document.getElementById('sendOnlyLabel').textContent     = selectedNetwork === 'TRC20' ? 'USDT via TRC20' : 'Bitcoin (BTC)';
  genQR(document.getElementById('qrContainer'), w.address);
}

function updateFeePreview() {
  const amt = parseFloat(document.getElementById('amountInput').value || 0);
  const fp  = document.getElementById('feePreview');
  if (amt >= 100) {
    const fee     = +(amt * 0.035).toFixed(2);
    const receive = +(amt - fee).toFixed(2);
    fp.style.display = 'block';
    document.getElementById('fp_send').textContent    = '$' + amt.toFixed(2);
    document.getElementById('fp_fee').textContent     = '-$' + fee.toFixed(2);
    document.getElementById('fp_receive').textContent = '$' + receive.toFixed(2);
  } else {
    fp.style.display = 'none';
  }
}

// Screenshot drag/drop
const ssInput = document.getElementById('ssInput');
const ssZone  = document.getElementById('ssZone');
ssInput.addEventListener('change', function() {
  if (this.files[0]) { ssZone.classList.add('has-file'); document.getElementById('ssTitle').textContent = this.files[0].name; }
});
ssZone.addEventListener('dragover',  e => { e.preventDefault(); ssZone.classList.add('drag-over'); });
ssZone.addEventListener('dragleave', () => ssZone.classList.remove('drag-over'));
ssZone.addEventListener('drop', e => {
  e.preventDefault(); ssZone.classList.remove('drag-over');
  const f = e.dataTransfer.files[0];
  if (f && f.type.startsWith('image/')) {
    const dt = new DataTransfer(); dt.items.add(f); ssInput.files = dt.files;
    ssZone.classList.add('has-file'); document.getElementById('ssTitle').textContent = f.name;
  }
});

// Init
updateWalletDisplay();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>