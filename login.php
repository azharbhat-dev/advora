<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/logo.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $users = readJson(USERS_FILE);
    $found = false;
    foreach ($users as $u) {
        if ($u['username'] === $username && password_verify($password, $u['password'])) {
            if (!empty($u['disabled'])) {
                $error = 'Account disabled. Contact support.';
                break;
            }
            $_SESSION['user_id'] = $u['id'];
            safeRedirect('/user/dashboard.php');
        }
    }
    if (!$error) $error = 'Invalid username or password';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Advora</title>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
        <div class="login-logo">
            <?= advoraLogoFullSvg(70) ?>
            <p style="margin-top: 12px;">Self-serve advertising platform</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                Sign In
            </button>
        </form>

        
    </div>
</div>
</body>
</html>
