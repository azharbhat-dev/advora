<?php
// ============================================================
// Advora Installer — upload, visit /install.php once,
// then DELETE this file.
// ============================================================
require_once __DIR__ . '/includes/config.php';

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = db();
        $schemaPath = __DIR__ . '/schema.sql';
        if (!file_exists($schemaPath)) {
            throw new Exception('schema.sql not found in project root');
        }
        $sql = file_get_contents($schemaPath);

        // Strip single-line comments
        $sql = preg_replace('/^--.*$/m', '', $sql);

        // Execute as one batch (PDO supports multi-query when emulated off with mysqlnd it still works
        // via unbuffered query + exec splits — to be safe, split on ';' and run each)
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if ($stmt === '') continue;
            $pdo->exec($stmt);
        }

        // Ensure default admin exists if users table empty
        $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count === 0 && !empty($_POST['create_demo'])) {
            $stmt = $pdo->prepare(
                'INSERT INTO users
                 (id,username,password,email,full_name,doc_verified,balance,account_type,disabled,created_at)
                 VALUES (?,?,?,?,?,0,1000,"professional",0,?)'
            );
            $stmt->execute([
                'USR-' . strtoupper(substr(md5(uniqid('',true)),0,8)),
                'demo',
                password_hash('demo123', PASSWORD_DEFAULT),
                'demo@example.com',
                'Demo User',
                time()
            ]);
        }

        $msg = 'Database installed successfully! '
             . (($count === 0 && !empty($_POST['create_demo'])) ? 'Demo user created (demo / demo123). ' : '')
             . 'For security, delete /install.php now.';
    } catch (Exception $e) {
        $err = 'Install failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Advora Installer</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-box" style="max-width:520px">
    <h1 style="margin-bottom:6px;font-size:22px">Advora Installer</h1>
    <p style="color:var(--text-2);font-size:13px;margin-bottom:22px">
      Creates all database tables and seeds default countries + wallets.
    </p>

    <?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <p style="margin-top:14px">
      <a class="btn btn-primary" href="/admin/login.php">Admin Login &rarr;</a>
      <a class="btn btn-secondary" href="/login.php">User Login &rarr;</a>
    </p>
    <?php endif; ?>

    <?php if ($err): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <?php if (!$msg): ?>
    <div class="alert alert-info" style="font-size:12.5px;margin-bottom:18px">
      <strong>Before running:</strong><br>
      1. Upload all files to Hostinger.<br>
      2. Copy <code>db_config.example.php</code> to <code>includes/db_config.php</code> and fill in your DB details.<br>
      3. Click Install below.<br>
      4. Delete <code>install.php</code> + <code>schema.sql</code> for security.
    </div>

    <form method="POST">
      <div class="form-group">
        <label class="form-label" style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="create_demo" value="1" checked>
          Create demo user (demo / demo123) if users table is empty
        </label>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        Install Database
      </button>
    </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
