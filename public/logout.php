<?php

require_once __DIR__ . '/../lib/auth.php';

logoutAdmin();

header('Location: admin_login.php');
exit;