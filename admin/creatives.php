<?php
require_once __DIR__ . '/../includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['creative_id'] ?? '';
    $creatives = readJson(CREATIVES_FILE);
    if ($action === 'approve') {
        foreach ($creatives as &$cr) { if ($cr['id']===$id) { $cr['status']='approved'; break; } }
        writeJson(CREATIVES_FILE, $creatives);
        flash('Creative approved', 'success');
    } elseif ($action === 'reject') {
        foreach ($creatives as &$cr) { if ($cr['id']===$id) { $cr['status']='rejected'; break; } }
        writeJson(CREATIVES_FILE, $creatives);
        flash('Creative rejected', 'success');
    } elseif ($action === 'delete') {
        foreach ($creatives as $cr) {
            if ($cr['id']===$id && !empty($cr['stored_file'])) {
                $path = DATA_PATH.'/creatives_files/'.$cr['stored_file'];
                if (file_exists($path)) unlink($path);
                break;
            }
        }
        $creatives = array_values(array_filter($creatives, fn($c) => $c['id'] !== $id));
        writeJson(CREATIVES_FILE, $creatives);
        flash('Creative deleted', 'success');
    }
    safeRedirect('/admin/creatives.php');
}

// Preview endpoint
if (isset($_GET['preview'])) {
    $id = $_GET['preview'];
    foreach (readJson(CREATIVES_FILE) as $cr) {
        if ($cr['id'] === $id) {
            $path = DATA_PATH.'/creatives_files/'.$cr['stored_file'];
            if (file_exists($path)) {
                header('Content-Type: text/html; charset=utf-8');
                header('X-Frame-Options: SAMEORIGIN');
                readfile($path); exit;
            }
        }
    }
    http_response_code(404); exit;
}

if (isset($_GET['download'])) {
    $id = $_GET['download'];
    foreach (readJson(CREATIVES_FILE) as $cr) {
        if ($cr['id']===$id) {
            $path = DATA_PATH.'/creatives_files/'.$cr['stored_file'];
            if (file_exists($path)) {
                header('Content-Type: text/html');
                header('Content-Disposition: attachment; filename="'.$cr['filename'].'"');
                header('Content-Length: '.filesize($path));
                readfile($path); exit;
            }
        }
    }
    http_response_code(404); exit;
}

$creatives = readJson(CREATIVES_FILE);
$users = readJson(USERS_FILE);
$userMap = [];
foreach ($users as $u) $userMap[$u['id']] = $u['username'];
$filter = $_GET['filter'] ?? 'all';
if ($filter !== 'all') $creatives = array_filter($creatives, fn($c) => $c['status']===$filter);
$creatives = array_reverse($creatives);
?>

<div class="page-header">
  <div>
    <div class="page-title">Creatives Review</div>
    <div class="page-subtitle">Approve or reject user-uploaded HTML creatives</div>
  </div>
</div>

<div class="card">
  <div class="filter-bar">
    <select class="form-control" onchange="location.href='?filter='+this.value">
      <option value="all" <?= $filter==='all'?'selected':'' ?>>All</option>
      <option value="pending" <?= $filter==='pending'?'selected':'' ?>>Pending</option>
      <option value="approved" <?= $filter==='approved'?'selected':'' ?>>Approved</option>
      <option value="rejected" <?= $filter==='rejected'?'selected':'' ?>>Rejected</option>
    </select>
  </div>
  <?php if (empty($creatives)): ?>
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
    <h3>No creatives found</h3>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>ID</th><th>User</th><th>Name</th><th>File</th><th>Size</th><th>URL Track</th><th>Status</th><th>Uploaded</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($creatives as $cr):
        $sc = ['pending'=>'badge-pending','approved'=>'badge-success','rejected'=>'badge-danger'][$cr['status']]??'badge-muted';
      ?>
        <tr>
          <td><code style="color:var(--yellow);font-size:11px"><?= $cr['id'] ?></code></td>
          <td><?= htmlspecialchars($userMap[$cr['user_id']]??'Unknown') ?></td>
          <td><strong><?= htmlspecialchars($cr['name']) ?></strong></td>
          <td style="font-size:12px;color:var(--text-2)"><?= htmlspecialchars($cr['filename']) ?></td>
          <td><?= number_format($cr['file_size']/1024,1) ?> KB</td>
          <td><?= !empty($cr['track_url']) ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-muted">No</span>' ?></td>
          <td><span class="badge <?= $sc ?>" data-live-badge="cr:<?= $cr['id'] ?>:status" data-current-status="<?= $cr['status'] ?>"><?= $cr['status'] ?></span></td>
          <td style="font-size:12px;color:var(--text-2)"><?= timeAgo($cr['uploaded_at']) ?></td>
          <td>
            <div style="display:flex;gap:4px;flex-wrap:wrap">
              <button class="btn btn-secondary btn-sm" onclick="previewCreative('<?= htmlspecialchars($cr['id']) ?>', '<?= htmlspecialchars($cr['name']) ?>')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View
              </button>
              <a href="?download=<?= $cr['id'] ?>" class="btn btn-secondary btn-sm">Download</a>
              <?php if ($cr['status']==='pending'): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="creative_id" value="<?= $cr['id'] ?>">
                <button type="submit" class="btn btn-success btn-sm">Approve</button>
              </form>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="creative_id" value="<?= $cr['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Reject</button>
              </form>
              <?php endif; ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete creative?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="creative_id" value="<?= $cr['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Del</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
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
function previewCreative(id, name) {
  const url = '/admin/creatives.php?preview=' + encodeURIComponent(id);
  document.getElementById('previewTitle').textContent = name;
  document.getElementById('previewFrame').src = url;
  document.getElementById('previewOpenBtn').href = url;
  document.getElementById('previewUrlBar').textContent = name + '.html';
  openModal('previewModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
