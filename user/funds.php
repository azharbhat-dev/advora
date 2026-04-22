<?php
require_once __DIR__ . '/../includes/user_header.php';

$settings = getSettings();

// All supported networks with addresses from settings
$allNetworks = [
    'USDT_TRC20' => ['label'=>'USDT — TRC20',  'coin'=>'USDT', 'network'=>'Tron TRC20',     'address'=> $settings['wallets']['TRC20']['address'] ?? 'TXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'],
    'USDT_ERC20' => ['label'=>'USDT — ERC20',  'coin'=>'USDT', 'network'=>'Ethereum ERC20',  'address'=> $settings['wallets']['ERC20']['address'] ?? '0xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'],
    'USDT_BEP20' => ['label'=>'USDT — BEP20',  'coin'=>'USDT', 'network'=>'BSC BEP20',       'address'=> $settings['wallets']['BEP20']['address'] ?? '0xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'],
    'BTC'        => ['label'=>'Bitcoin',        'coin'=>'BTC',  'network'=>'Bitcoin Network', 'address'=> $settings['wallets']['BTC']['address']   ?? 'bc1qxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method  = $_POST['method']  ?? '';
    $network = $_POST['network'] ?? '';
    $desired = (float)($_POST['desired_amount'] ?? 0);   // amount user wants credited
    $txid    = trim($_POST['txid'] ?? '');

    if ($method !== 'crypto') {
        flash('This payment method is restricted. Please contact your manager.', 'error');
        safeRedirect('/user/funds.php');
    }
    if ($desired < 100) {
        flash('Minimum deposit is $100.00', 'error');
        safeRedirect('/user/funds.php');
    }
    if (!isset($allNetworks[$network])) {
        flash('Invalid network selected', 'error');
        safeRedirect('/user/funds.php');
    }
    if (!$txid) {
        flash('Transaction hash is required', 'error');
        safeRedirect('/user/funds.php');
    }

    // Screenshot is mandatory
    $screenshotFile = null;
    if (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
        flash('Payment screenshot is required', 'error');
        safeRedirect('/user/funds.php');
    }
    $file = $_FILES['screenshot'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
        flash('Screenshot must be an image file (JPG, PNG, WEBP)', 'error');
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

    // Fee logic: user wants $desired credited → they must send desired / (1 - 0.035)
    // i.e. to receive $100, send $103.63
    $amountToSend = round($desired / (1 - 0.035), 2);
    $fee          = round($amountToSend - $desired, 2);

    $topups   = readJson(TOPUPS_FILE);
    $topups[] = [
        'id'               => 'TX-' . strtoupper(substr(md5(uniqid(mt_rand(),true)),0,8)),
        'user_id'          => $user['id'],
        'username'         => $user['username'],
        'network'          => $network,
        'network_label'    => $allNetworks[$network]['label'],
        'address'          => $allNetworks[$network]['address'],
        'amount'           => $amountToSend,
        'fee'              => $fee,
        'amount_after_fee' => $desired,
        'txid'             => $txid,
        'screenshot'       => $screenshotFile,
        'status'           => 'pending',
        'created_at'       => time()
    ];
    writeJson(TOPUPS_FILE, $topups);
    addAdminNotification($user['id'], $user['username'], 'deposit_submitted',
        'Deposit Submitted',
        $user['username'] . ' submitted a deposit of $' . number_format($amountToSend, 2) . ' via ' . $allNetworks[$network]['label'] . ' — TX: ' . substr($txid, 0, 20) . '...'
    );
    addNotification($user['id'], 'topup_approved', 'Deposit Submitted',
        'Your deposit of $'.number_format($amountToSend,2).' via '.$allNetworks[$network]['label'].' is under review.');
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
  Minimum deposit: <strong>$100.00</strong> &nbsp;&middot;&nbsp; Platform fee: <strong>3.5%</strong> &nbsp;&middot;&nbsp; Payment screenshot is <strong>required</strong>
</div>

<!-- Payment method selector -->
<div class="card">
  <div class="card-title" style="margin-bottom:20px">Select Payment Method</div>
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px">

    <div class="pm-card active" id="pm_crypto" onclick="selectPayment('crypto')">
      <div class="pm-icon-wrap">
        <svg width="28" height="28" viewBox="0 0 36 36" fill="none"><circle cx="18" cy="18" r="18" fill="#F7931A"/><path d="M25.3 15.7c.3-2.3-1.4-3.6-3.9-4.4l.8-3.1-1.9-.5-.8 3c-.5-.1-1-.2-1.5-.4l.8-3-1.9-.5-.8 3.1c-.4-.1-.8-.2-1.2-.3l-2.6-.7-.5 2s1.4.3 1.3.3c.7.2.9.7.9 1.1l-2.1 8.6c-.1.3-.4.6-.9.5 0 0-1.3-.3-1.3-.3l-.9 2.2 2.4.6 1.3.3-.8 3.1 1.9.5.8-3.1 1.5.4-.8 3 1.9.5.8-3.1c3.2.6 5.6-.2 6.6-3.2.8-2.3-.1-3.6-1.7-4.4 1.2-.3 2.1-1.1 2.4-2.8z" fill="#fff"/></svg>
      </div>
      <div class="pm-name">Crypto</div>
      <div class="pm-sub">USDT · BTC</div>
      <div class="pm-checkmark"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
    </div>

    <?php foreach (['debit'=>['Debit Card','Visa · MC','#1A1F71'],'credit'=>['Credit Card','Visa · MC','#252525'],'capitalist'=>['Capitalist','E-wallet','#0055CC'],'wire'=>['Wire Transfer','Bank wire','#1B4332']] as $k=>[$n,$s,$bg]): ?>
    <div class="pm-card pm-locked" onclick="showRestricted()">
      <div class="pm-icon-wrap"><div style="width:28px;height:28px;border-radius:6px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff"><?= strtoupper(substr($k,0,1)) ?></div></div>
      <div class="pm-name"><?= $n ?></div>
      <div class="pm-sub"><?= $s ?></div>
      <div class="pm-lock-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
    </div>
    <?php endforeach; ?>

  </div>
  <div id="restrictedNotice" style="display:none;margin-top:14px" class="alert alert-warning">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    This payment method is restricted. Contact your manager to unlock it.
  </div>
</div>

<!-- Crypto section -->
<div id="cryptoSection">

  <!-- Network selector -->
  <div class="card">
    <div class="card-title" style="margin-bottom:18px">Select Network</div>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <?php foreach ($allNetworks as $netKey => $net): ?>
      <div class="crypto-opt <?= $netKey==='USDT_TRC20'?'active':'' ?>"
           id="co_<?= $netKey ?>" data-network="<?= $netKey ?>" onclick="selectCrypto('<?= $netKey ?>')">
        <div class="co-logo">
          <?php if ($net['coin']==='USDT'): ?>
          <svg width="32" height="32" viewBox="0 0 36 36" fill="none"><circle cx="18" cy="18" r="18" fill="#26A17B"/><path d="M20.3 16.6v-2.6h5.2v-3.1H10.5V14h5.2v2.6C11.5 17 8.1 18 8.1 19.2s3.4 2.2 7.6 2.5v8.7h4.5v-8.7c4.3-.3 7.6-1.3 7.6-2.5s-3.3-2.2-7.5-2.6z" fill="#fff"/></svg>
          <?php else: ?>
          <svg width="32" height="32" viewBox="0 0 36 36" fill="none"><circle cx="18" cy="18" r="18" fill="#F7931A"/><path d="M25.3 15.7c.3-2.3-1.4-3.6-3.9-4.4l.8-3.1-1.9-.5-.8 3c-.5-.1-1-.2-1.5-.4l.8-3-1.9-.5-.8 3.1c-.4-.1-.8-.2-1.2-.3l-2.6-.7-.5 2s1.4.3 1.3.3c.7.2.9.7.9 1.1l-2.1 8.6c-.1.3-.4.6-.9.5 0 0-1.3-.3-1.3-.3l-.9 2.2 2.4.6 1.3.3-.8 3.1 1.9.5.8-3.1 1.5.4-.8 3 1.9.5.8-3.1c3.2.6 5.6-.2 6.6-3.2.8-2.3-.1-3.6-1.7-4.4 1.2-.3 2.1-1.1 2.4-2.8z" fill="#fff"/></svg>
          <?php endif; ?>
        </div>
        <div class="co-info">
          <div class="co-name"><?= $net['coin'] ?></div>
          <div class="co-net"><?= $net['network'] ?></div>
        </div>
        <div class="co-check"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Wallet address -->
  <div class="card">
    <div class="card-title" style="margin-bottom:18px">Send To This Address</div>
    <div style="display:grid;grid-template-columns:180px 1fr;gap:28px;align-items:start" class="wallet-grid">
      <div style="text-align:center">
        <div style="width:160px;height:160px;background:#fff;border-radius:var(--r-sm);padding:8px;margin:0 auto 8px;display:flex;align-items:center;justify-content:center" id="qrContainer"></div>
        <div style="font-size:11px;color:var(--text-3)">Scan with wallet app</div>
      </div>
      <div>
        <div style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Network</div>
        <div style="font-size:16px;font-weight:700;margin-bottom:16px" id="walletNetworkName">USDT — TRC20 (Tron)</div>
        <div style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Deposit Address</div>
        <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:var(--r-sm);padding:12px 14px;font-family:'Courier New',monospace;font-size:12px;word-break:break-all;line-height:1.6;margin-bottom:10px" id="walletAddr"></div>
        <button class="copy-btn" onclick="copyText(document.getElementById('walletAddr').textContent.trim(), this)">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          Copy Address
        </button>
        <div class="alert alert-warning" style="margin-top:14px;font-size:12.5px">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Only send <strong id="sendOnlyLabel">USDT via TRC20</strong> to this address. Wrong network = permanent loss.
        </div>
      </div>
    </div>
  </div>

  <!-- Deposit form -->
  <div class="card">
    <div class="card-title" style="margin-bottom:20px">Confirm Your Deposit</div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="method"  value="crypto">
      <input type="hidden" name="network" id="selectedNetwork" value="USDT_TRC20">

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Amount to Receive (USD) *</label>
          <div style="position:relative">
            <span style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-2);font-weight:700">$</span>
            <input type="number" name="desired_amount" id="desiredInput" class="form-control"
              style="padding-left:26px" required min="100" step="0.01"
              placeholder="100.00" oninput="updateFeePreview()">
          </div>
          <div class="form-hint">Enter how much you want credited to your account. Minimum $100.00.</div>
        </div>
        <div class="form-group">
          <label class="form-label">Transaction Hash *</label>
          <input type="text" name="txid" class="form-control" required
            placeholder="Paste TX hash / ID here"
            style="font-family:'Courier New',monospace;font-size:12px">
          <div class="form-hint">The transaction ID from your wallet after sending</div>
        </div>
      </div>

      <!-- Fee breakdown -->
      <div id="feePreview" style="display:none;margin-bottom:20px">
        <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:var(--r-sm);overflow:hidden">
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;text-align:center">
            <div style="padding:16px;border-right:1px solid var(--border)">
              <div style="font-size:10.5px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">You Send</div>
              <div style="font-size:20px;font-weight:800;color:var(--yellow)" id="fp_send">$0.00</div>
            </div>
            <div style="padding:16px;border-right:1px solid var(--border)">
              <div style="font-size:10.5px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Platform Fee (3.5%)</div>
              <div style="font-size:20px;font-weight:800;color:var(--orange)" id="fp_fee">$0.00</div>
            </div>
            <div style="padding:16px">
              <div style="font-size:10.5px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Credited to Account</div>
              <div style="font-size:20px;font-weight:800;color:var(--green)" id="fp_receive">$0.00</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Screenshot upload — MANDATORY -->
      <div class="form-group">
        <label class="form-label">
          Payment Screenshot *
          <span style="background:rgba(255,68,102,.1);color:var(--red);font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px;margin-left:6px">Required</span>
        </label>
        <div class="upload-zone" id="ssZone" onclick="document.getElementById('ssInput').click()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          <div class="uz-title" id="ssTitle">Click to upload or drag &amp; drop</div>
          <div class="uz-sub">JPG, PNG, WEBP &middot; Max 5MB &middot; <strong style="color:var(--red)">Mandatory</strong></div>
        </div>
        <input type="file" name="screenshot" id="ssInput" accept="image/*" required style="display:none">
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px 20px;font-size:15px">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Submit Deposit
      </button>
    </form>
  </div>

</div>

<style>
.pm-card{background:var(--bg-2);border:2px solid var(--border);border-radius:var(--r);padding:16px 12px;cursor:pointer;transition:all .2s;text-align:center;position:relative;user-select:none;display:flex;flex-direction:column;align-items:center;gap:6px}
.pm-card:hover{border-color:rgba(255,200,0,.25);background:var(--bg-3)}
.pm-card.active{border-color:var(--yellow);background:var(--yellow-dim)}
.pm-card.active .pm-checkmark{opacity:1;background:var(--yellow);border-color:var(--yellow);color:#000}
.pm-locked{opacity:.5;cursor:default}
.pm-locked:hover{border-color:var(--border)!important;background:var(--bg-2)!important}
.pm-icon-wrap{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:var(--bg-3)}
.pm-name{font-size:12.5px;font-weight:700;color:var(--text)}
.pm-sub{font-size:10.5px;color:var(--text-3)}
.pm-checkmark{position:absolute;top:7px;right:7px;width:17px;height:17px;border-radius:50%;border:2px solid var(--border-2);display:flex;align-items:center;justify-content:center;color:transparent;opacity:0;transition:all .2s}
.pm-lock-icon{position:absolute;top:7px;right:7px;width:17px;height:17px;border-radius:50%;border:1px solid var(--border);background:var(--bg-3);display:flex;align-items:center;justify-content:center;color:var(--text-3)}
.crypto-opt{display:flex;align-items:center;gap:12px;padding:14px 18px;background:var(--bg-2);border:2px solid var(--border);border-radius:var(--r);cursor:pointer;transition:all .2s;min-width:170px;user-select:none;position:relative}
.crypto-opt:hover{border-color:rgba(255,200,0,.3);background:var(--bg-3)}
.crypto-opt.active{border-color:var(--yellow);background:var(--yellow-dim)}
.crypto-opt.active .co-check{background:var(--yellow);border-color:var(--yellow);color:#000}
.co-name{font-size:14px;font-weight:700}
.co-net{font-size:11px;color:var(--text-2)}
.co-check{width:20px;height:20px;border-radius:50%;border:2px solid var(--border-2);display:flex;align-items:center;justify-content:center;color:transparent;transition:all .15s;flex-shrink:0}
@media(max-width:640px){.wallet-grid{grid-template-columns:1fr!important}}
</style>

<script>
const allNetworks = <?= json_encode($allNetworks) ?>;
let selectedNetwork = 'USDT_TRC20';

function selectPayment(type) {
  document.getElementById('restrictedNotice').style.display = 'none';
  document.getElementById('cryptoSection').style.display = 'block';
}
function showRestricted() {
  document.querySelectorAll('.pm-card').forEach(c => c.classList.remove('active'));
  document.getElementById('pm_crypto').classList.remove('active');
  document.getElementById('restrictedNotice').style.display = 'flex';
}

function selectCrypto(key) {
  selectedNetwork = key;
  document.querySelectorAll('.crypto-opt').forEach(c => c.classList.remove('active'));
  document.getElementById('co_' + key).classList.add('active');
  document.getElementById('selectedNetwork').value = key;
  updateWalletDisplay();
}

function updateWalletDisplay() {
  const net = allNetworks[selectedNetwork];
  if (!net) return;
  document.getElementById('walletNetworkName').textContent = net.label + ' (' + net.network + ')';
  document.getElementById('walletAddr').textContent        = net.address;
  document.getElementById('sendOnlyLabel').textContent     = net.coin + ' via ' + net.network;
  genQR(document.getElementById('qrContainer'), net.address);
}

function updateFeePreview() {
  const desired = parseFloat(document.getElementById('desiredInput').value || 0);
  const fp = document.getElementById('feePreview');
  if (desired >= 100) {
    // User wants 'desired' credited → they must send desired / 0.965
    const toSend = (desired / 0.965).toFixed(2);
    const fee    = (parseFloat(toSend) - desired).toFixed(2);
    fp.style.display = 'block';
    document.getElementById('fp_send').textContent    = '$' + toSend;
    document.getElementById('fp_fee').textContent     = '$' + fee;
    document.getElementById('fp_receive').textContent = '$' + desired.toFixed(2);
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