<?php
require_once __DIR__ . '/includes/config.php';
session_destroy();
safeRedirect('/login.php');