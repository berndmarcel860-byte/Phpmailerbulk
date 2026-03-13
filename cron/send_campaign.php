#!/usr/bin/env php
<?php
/**
 * Cron script – runs pending campaigns in the background.
 *
 * Usage (cron):
 *   * * * * * php /path/to/cron/send_campaign.php >> /var/log/phpmailerbulk.log 2>&1
 *
 * Or run manually:
 *   php cron/send_campaign.php [campaign_id]
 */

define('CRON_MODE', true);

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

if (!file_exists($root . '/config.php')) {
    die("Error: config.php not found. Run install.php first.\n");
}

require_once $root . '/config.php';
require_once $root . '/includes/db.php';
require_once $root . '/includes/auth.php';
require_once $root . '/includes/functions.php';
require_once $root . '/includes/mailer.php';

set_time_limit(0);
ini_set('memory_limit', '256M');

$db = get_db();

// Optionally pass a specific campaign ID
$campaign_id = isset($argv[1]) ? (int) $argv[1] : 0;

if ($campaign_id) {
    $campaigns = $db->prepare('SELECT id, name FROM campaigns WHERE id = ? AND status IN ("pending", "failed")');
    $campaigns->execute([$campaign_id]);
    $campaigns = $campaigns->fetchAll();
} else {
    // Process all pending campaigns (one at a time)
    $campaigns = $db->query('SELECT id, name FROM campaigns WHERE status = "pending" ORDER BY id ASC LIMIT 1')->fetchAll();
}

if (empty($campaigns)) {
    echo date('Y-m-d H:i:s') . " – No pending campaigns.\n";
    exit(0);
}

foreach ($campaigns as $camp) {
    echo date('Y-m-d H:i:s') . " – Starting campaign #{$camp['id']}: {$camp['name']}\n";

    run_campaign($camp['id'], function ($sent, $total, $status) use ($camp) {
        if ($sent % 10 === 0 || $status === 'failed') {
            echo date('H:i:s') . " – [{$camp['id']}] Sent:{$sent}/{$total} ({$status})\n";
        }
    });

    echo date('Y-m-d H:i:s') . " – Campaign #{$camp['id']} finished.\n";
}
