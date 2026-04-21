<?php
session_start();
session_destroy();
safeRedirect('/login.php');
