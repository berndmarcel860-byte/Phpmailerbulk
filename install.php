<?php
/**
 * Installation wizard – creates the database schema and admin user.
 */
$root = __DIR__;

// Block if already installed
if (file_exists($root . '/config.php')) {
    exit('<h2>Already installed. Delete config.php to re-install.</h2>');
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host  = trim($_POST['db_host']  ?? 'localhost');
    $db_name  = trim($_POST['db_name']  ?? '');
    $db_user  = trim($_POST['db_user']  ?? '');
    $db_pass  = $_POST['db_pass']  ?? '';
    $adm_user = trim($_POST['adm_user'] ?? '');
    $adm_pass = $_POST['adm_pass'] ?? '';

    if (!$db_name)  $errors[] = 'Database name is required.';
    if (!$db_user)  $errors[] = 'Database user is required.';
    if (!$adm_user) $errors[] = 'Admin username is required.';
    if (strlen($adm_pass) < 6) $errors[] = 'Admin password must be at least 6 characters.';

    if (empty($errors)) {
        // Validate database name (only allow safe characters)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
            $errors[] = 'Database name may only contain letters, numbers, and underscores.';
        }
    }

    if (empty($errors)) {
        // Test DB connection
        try {
            $dsn = 'mysql:host=' . $db_host . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // Create database if not exists (name is validated above, safe to interpolate)
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $db_name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . $db_name . '`');

            // Create tables
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS admin_users (
                    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    username     VARCHAR(100) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS leads (
                    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    first_name  VARCHAR(150) NOT NULL DEFAULT '',
                    last_name   VARCHAR(150) NOT NULL DEFAULT '',
                    email       VARCHAR(255) NOT NULL,
                    platform    VARCHAR(150) NOT NULL DEFAULT '',
                    amount      VARCHAR(100) NOT NULL DEFAULT '',
                    date_field  VARCHAR(100) NOT NULL DEFAULT '',
                    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY  uq_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS smtp_accounts (
                    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name          VARCHAR(150) NOT NULL,
                    host          VARCHAR(255) NOT NULL,
                    port          SMALLINT UNSIGNED NOT NULL DEFAULT 587,
                    username      VARCHAR(255) NOT NULL,
                    password      VARCHAR(255) NOT NULL,
                    encryption    ENUM('tls','ssl') NOT NULL DEFAULT 'tls',
                    from_email    VARCHAR(255) NOT NULL,
                    from_name     VARCHAR(150) NOT NULL DEFAULT '',
                    emails_per_session SMALLINT UNSIGNED NOT NULL DEFAULT 3,
                    active        TINYINT(1) NOT NULL DEFAULT 1,
                    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS email_templates (
                    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name        VARCHAR(150) NOT NULL,
                    subject     VARCHAR(255) NOT NULL,
                    html_body   MEDIUMTEXT NOT NULL,
                    text_body   MEDIUMTEXT NOT NULL DEFAULT '',
                    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS campaigns (
                    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name            VARCHAR(150) NOT NULL,
                    template_id     INT UNSIGNED NOT NULL,
                    lead_group      VARCHAR(150) NOT NULL DEFAULT 'all',
                    status          ENUM('pending','running','paused','completed','failed') NOT NULL DEFAULT 'pending',
                    emails_per_smtp SMALLINT UNSIGNED NOT NULL DEFAULT 3,
                    pause_seconds   SMALLINT UNSIGNED NOT NULL DEFAULT 5,
                    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
                    started_at      DATETIME NULL,
                    completed_at    DATETIME NULL,
                    FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS campaign_logs (
                    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    campaign_id  INT UNSIGNED NOT NULL,
                    lead_id      INT UNSIGNED NOT NULL,
                    smtp_id      INT UNSIGNED NOT NULL,
                    status       ENUM('sent','failed') NOT NULL DEFAULT 'sent',
                    error_message TEXT NOT NULL DEFAULT '',
                    sent_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
                    opened_at    DATETIME NULL,
                    INDEX idx_campaign (campaign_id),
                    INDEX idx_lead    (lead_id),
                    FOREIGN KEY (campaign_id) REFERENCES campaigns(id)   ON DELETE CASCADE,
                    FOREIGN KEY (lead_id)     REFERENCES leads(id)       ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS antispam_rules (
                    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    bad_word     VARCHAR(255) NOT NULL,
                    replacement  VARCHAR(255) NOT NULL DEFAULT '',
                    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // Seed default anti-spam rules
            $pdo->exec("INSERT IGNORE INTO antispam_rules (bad_word, replacement) VALUES
                ('free money', 'exclusive offer'),
                ('click here', 'learn more'),
                ('act now', 'get started'),
                ('limited time', 'special period'),
                ('buy now', 'get yours'),
                ('make money', 'generate income'),
                ('100% free', 'complimentary'),
                ('guarantee', 'assurance'),
                ('winner', 'selected recipient'),
                ('urgent', 'important')
            ");

            // Create admin user
            $hash = password_hash($adm_pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
            $stmt->execute([$adm_user, $hash]);

            // Write config.php
            $secret  = bin2hex(random_bytes(24));
            $config  = "<?php\n";
            $config .= "define('DB_HOST',    " . var_export($db_host, true) . ");\n";
            $config .= "define('DB_NAME',    " . var_export($db_name, true) . ");\n";
            $config .= "define('DB_USER',    " . var_export($db_user, true) . ");\n";
            $config .= "define('DB_PASS',    " . var_export($db_pass, true) . ");\n";
            $config .= "define('DB_CHARSET', 'utf8mb4');\n";
            $config .= "define('SESSION_SECRET', " . var_export($secret, true) . ");\n";
            $config .= "define('DEFAULT_EMAILS_PER_SMTP', 3);\n";
            $config .= "define('DEFAULT_PAUSE_SECONDS',   5);\n";

            file_put_contents($root . '/config.php', $config);
            $success = true;

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Install – PhpMailerBulk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg,#1a1a2e,#16213e,#0f3460); min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .install-card { width: 520px; border-radius: 16px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,.4); }
    </style>
</head>
<body>
<div class="install-card card p-4">
    <div class="text-center mb-4">
        <div style="font-size:3rem;color:#0d6efd;">📧</div>
        <h2 class="fw-bold">PhpMailerBulk</h2>
        <p class="text-muted">Setup Wizard</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>Installation complete!</strong><br>
            Your bulk email system is ready.
            <a href="login.php" class="btn btn-primary mt-3 w-100">Go to Login</a>
        </div>
    <?php else: ?>

        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
        <?php endforeach; ?>

        <form method="post">
            <h5 class="mb-3">Database Settings</h5>
            <div class="mb-3">
                <label class="form-label">DB Host</label>
                <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost', ENT_QUOTES) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Database Name</label>
                <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($_POST['db_name'] ?? 'phpmailerbulk', ENT_QUOTES) ?>" required>
            </div>
            <div class="row">
                <div class="col mb-3">
                    <label class="form-label">DB User</label>
                    <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($_POST['db_user'] ?? '', ENT_QUOTES) ?>" required>
                </div>
                <div class="col mb-3">
                    <label class="form-label">DB Password</label>
                    <input type="password" name="db_pass" class="form-control">
                </div>
            </div>

            <hr>
            <h5 class="mb-3">Admin Account</h5>
            <div class="mb-3">
                <label class="form-label">Admin Username</label>
                <input type="text" name="adm_user" class="form-control" value="<?= htmlspecialchars($_POST['adm_user'] ?? 'admin', ENT_QUOTES) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Admin Password <small class="text-muted">(min 6 chars)</small></label>
                <input type="password" name="adm_pass" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-2">Install Now</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
