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

/**
 * OpenAI API key for AI-powered email template generation.
 * Get your key at https://platform.openai.com/api-keys
 * Leave empty to disable the AI generator.
 */
define('OPENAI_API_KEY', '');

/** OpenAI model to use (gpt-4o-mini is recommended for cost/quality balance) */
define('OPENAI_MODEL', 'gpt-4o-mini');
