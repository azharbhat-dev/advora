<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/logo.php';

$error = '';
$ADMIN_USER = 'admin';
$ADMIN_PASS = 'admin123';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === $ADMIN_USER && $password === $ADMIN_PASS) {
        $_SESSION['admin'] = true;
        safeRedirect('/admin/index.php');
    }
    $error = 'Invalid admin credentials';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | Advora</title>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
        <div class="login-logo">
            <?= advoraLogoFullSvg(70) ?>
            <p style="margin-top: 12px; color: var(--red);">Admin Panel &mdash; Restricted Access</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Admin Username</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Admin Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                Access Admin Panel
            </button>
        </form>

        <div style="text-align: center; margin-top: 24px;">
            <a href="/login.php" style="font-size: 12px;">&larr; Back to user login</a>
        </div>

       
    </div>
</div>
</body>
</html>
