<?php
require_once __DIR__ . '/../includes/user_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    $name = trim($_POST['creative_name'] ?? '');
    $trackUrl = isset($_POST['track_url']) && $_POST['track_url'] === '1';

    if (!$name) {
        flash('Creative name is required', 'error');
    } elseif (!isset($_FILES['html_file']) || $_FILES['html_file']['error'] !== UPLOAD_ERR_OK) {
        flash('Please upload an HTML file', 'error');
    } else {
        $file = $_FILES['html_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'html' && $ext !== 'htm') {
            flash('Only HTML files are allowed', 'error');
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            flash('File too large. Maximum 5MB', 'error');
        } else {
            $creativeId = 'CR-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            $filename = $creativeId . '.html';
            $destPath = DATA_PATH . '/creatives_files/' . $filename;
            if (!is_dir(dirname($destPath))) mkdir(dirname($destPath), 0755, true);
            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $creatives = readJson(CREATIVES_FILE);
                $creatives[] = [
                    'id' => $creativeId,
                    'user_id' => $user['id'],
                    'name' => $name,
                    'filename' => $file['name'],
                    'stored_file' => $filename,
                    'file_size' => $file['size'],
                    'track_url' => $trackUrl,
                    'status' => 'pending',
                    'uploaded_at' => time()
                ];
                writeJson(CREATIVES_FILE, $creatives);
                flash('Creative uploaded successfully. Pending admin approval.', 'success');
            } else {
                flash('Upload failed. Try again.', 'error');
            }
        }
    }
    safeRedirect('/user/creatives.php');
}

// Serve the HTML file for preview
if (isset($_GET['preview'])) {
    $previewId = $_GET['preview'];
    $creatives = readJson(CREATIVES_FILE);
    foreach ($creatives as $cr) {
        if ($cr['id'] === $previewId && $cr['user_id'] === $user['id']) {
            $path = DATA_PATH . '/creatives_files/' . $cr['stored_file'];
            if (file_exists($path)) {
                header('Content-Type: text/html; charset=utf-8');
                header('X-Frame-Options: SAMEORIGIN');
                readfile($path);
                exit;
            }
        }
    }
    http_response_code(404); exit;
}

$creatives = readJson(CREATIVES_FILE);
$userCreatives = array_reverse(array_values(array_filter($creatives, fn($c) => $c['user_id'] === $user['id'])));
?>

<div class="page-header">
  <div>
    <div class="page-title">Creatives</div>
    <div class="page-subtitle">Upload and manage your HTML ad creatives</div>
  </div>
  <button class="btn btn-primary" onclick="openModal('uploadModal')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
    Upload Creative
  </button>
</div>

<div class="card">
  <div class="card-header"><div class="card-title">All Creatives</div></div>
  <?php if (empty($userCreatives)): ?>
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    <h3>No creatives uploaded</h3>
    <p>Upload your first HTML creative to use in campaigns</p>
    <button class="btn btn-primary" onclick="openModal('uploadModal')" style="margin-top:14px">Upload Creative</button>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Creative ID</th><th>Name</th><th>File</th><th>Size</th><th>URL Tracking</th><th>Status</th><th>Uploaded</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($userCreatives as $cr):
        $sc = ['pending'=>'badge-pending','approved'=>'badge-success','rejected'=>'badge-danger'][$cr['status']]??'badge-muted';
      ?>
        <tr>
          <td><code style="color:var(--yellow);font-size:11px"><?= htmlspecialchars($cr['id']) ?></code></td>
          <td><strong><?= htmlspecialchars($cr['name']) ?></strong></td>
          <td style="font-size:12px;color:var(--text-2)"><?= htmlspecialchars($cr['filename']) ?></td>
          <td><?= number_format($cr['file_size']/1024,1) ?> KB</td>
          <td>
            <?php if (!empty($cr['track_url'])): ?>
              <span class="badge badge-success">Enabled</span>
            <?php else: ?>
              <span class="badge badge-muted">Disabled</span>
            <?php endif; ?>
          </td>
          <td><span class="badge <?= $sc ?>" data-live-badge="cr:<?= $cr['id'] ?>:status" data-current-status="<?= $cr['status'] ?>"><?= $cr['status'] ?></span></td>
          <td style="font-size:12px;color:var(--text-2)"><?= timeAgo($cr['uploaded_at']) ?></td>
          <td>
            <button class="btn btn-secondary btn-sm" onclick="previewCreative('<?= htmlspecialchars($cr['id']) ?>', '<?= htmlspecialchars($cr['name']) ?>')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              View
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Upload Modal -->
<div class="modal" id="uploadModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">Upload New Creative</div>
      <div class="modal-close" onclick="closeModal('uploadModal')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </div>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="upload" value="1">
      <div class="form-group">
        <label class="form-label">Creative Name *</label>
        <input type="text" name="creative_name" class="form-control" required placeholder="e.g. Banner 728x90 Campaign A">
      </div>
      <div class="form-group">
        <label class="form-label">HTML File *</label>
        <div class="upload-zone" id="uploadZone" onclick="document.getElementById('htmlFileInput').click()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <div class="uz-title" id="uzTitle">Click to upload or drag &amp; drop</div>
          <div class="uz-sub">HTML files only &middot; Max 5MB</div>
        </div>
        <input type="file" name="html_file" id="htmlFileInput" accept=".html,.htm" required style="display:none">
      </div>
      <div class="form-group" style="background:var(--bg-3);border:1px solid var(--border-2);border-radius:var(--r-sm);padding:14px">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13.5px;font-weight:600">
          <input type="checkbox" name="track_url" id="trackUrlCheck" value="1" style="width:16px;height:16px;accent-color:var(--yellow)">
          Enable URL click tracking for this creative
        </label>
        <div style="font-size:12px;color:var(--text-3);margin-top:5px;margin-left:26px">Tracks clicks and conversions when this creative is served</div>
      </div>
      <div class="alert alert-info" style="font-size:12.5px">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        Creative will be reviewed by admin before being available in campaigns.
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Upload &amp; Submit</button>
      </div>
    </form>
  </div>
</div>

<!-- Preview Modal -->
<div class="modal" id="previewModal">
  <div class="modal-box" style="max-width:900px;width:95vw">
    <div class="modal-header">
      <div class="modal-title" id="previewTitle">Creative Preview</div>
      <div style="display:flex;gap:8px;align-items:center">
        <a id="previewOpenBtn" href="#" target="_blank" class="btn btn-secondary btn-sm">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          Open in Tab
        </a>
        <div class="modal-close" onclick="closeModal('previewModal')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </div>
      </div>
    </div>
    <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--r-sm);overflow:hidden">
      <div style="background:var(--bg-3);padding:8px 12px;display:flex;gap:6px;align-items:center;border-bottom:1px solid var(--border)">
        <div style="width:10px;height:10px;border-radius:50%;background:var(--red);opacity:.7"></div>
        <div style="width:10px;height:10px;border-radius:50%;background:var(--orange);opacity:.7"></div>
        <div style="width:10px;height:10px;border-radius:50%;background:var(--green);opacity:.7"></div>
        <div style="flex:1;background:var(--bg-4);border-radius:4px;padding:3px 10px;font-size:11px;color:var(--text-3);margin-left:6px" id="previewUrlBar">Preview</div>
      </div>
      <iframe id="previewFrame" style="width:100%;height:500px;border:none;background:#fff" sandbox="allow-scripts allow-same-origin"></iframe>
    </div>
  </div>
</div>

<script>
const fileInput = document.getElementById('htmlFileInput');
const zone = document.getElementById('uploadZone');
const uzTitle = document.getElementById('uzTitle');

fileInput.addEventListener('change', function() {
  if (this.files[0]) { zone.classList.add('has-file'); uzTitle.textContent = this.files[0].name; }
});
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
  e.preventDefault(); zone.classList.remove('drag-over');
  const f = e.dataTransfer.files[0];
  if (f && (f.name.endsWith('.html') || f.name.endsWith('.htm'))) {
    const dt = new DataTransfer(); dt.items.add(f); fileInput.files = dt.files;
    zone.classList.add('has-file'); uzTitle.textContent = f.name;
  }
});

function previewCreative(id, name) {
  const url = '/user/creatives.php?preview=' + encodeURIComponent(id);
  document.getElementById('previewTitle').textContent = name;
  document.getElementById('previewFrame').src = url;
  document.getElementById('previewOpenBtn').href = url;
  document.getElementById('previewUrlBar').textContent = name + '.html';
  openModal('previewModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
