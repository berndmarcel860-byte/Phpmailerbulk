<?php
require_once __DIR__ . '/vendor/autoload.php';

// Redirect to install if config is missing
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: admin/index.php');
} else {
    header('Location: login.php');
}
exit;
