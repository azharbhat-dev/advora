<?php
require_once __DIR__ . '/includes/config.php';
if (isAdmin()) { header('Location: /admin/index.php'); exit; }
if (isUser()) { header('Location: /user/dashboard.php'); exit; }
safeRedirect('/login.php');
