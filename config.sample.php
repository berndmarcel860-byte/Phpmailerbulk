<?php
/**
 * Database configuration
 * Copy config.sample.php to config.php and fill in your details.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'phpmailerbulk');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/** Admin session secret */
define('SESSION_SECRET', 'change_this_secret_key_' . md5(__FILE__));

/** Default emails to send per SMTP account before rotating */
define('DEFAULT_EMAILS_PER_SMTP', 3);

/** Default pause (seconds) between email batches */
define('DEFAULT_PAUSE_SECONDS', 5);
