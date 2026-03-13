<?php
/**
 * Campaign Sender
 *
 * GET  ?campaign_id=N         – show the send confirmation/progress page
 * GET  ?action=progress&id=N  – return JSON progress (polled by JS)
 * POST ?campaign_id=N         – start sending (runs in same request; use cron for large lists)
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

require_login();

$db          = get_db();
$campaign_id = (int) ($_GET['campaign_id'] ?? $_POST['campaign_id'] ?? 0);

// ── JSON progress endpoint ────────────────────────────────────────────────────

if ($_GET['action'] ?? '' === 'progress') {
    $cid  = (int) ($_GET['id'] ?? 0);
    $camp = $db->prepare('SELECT status FROM campaigns WHERE id = ?');
    $camp->execute([$cid]);
    $camp = $camp->fetch();

    $s1 = $db->prepare("SELECT COUNT(*) FROM campaign_logs WHERE campaign_id = ? AND status = 'sent'");
    $s1->execute([$cid]);
    $sent = (int) $s1->fetchColumn();

    $s2 = $db->prepare("SELECT COUNT(*) FROM campaign_logs WHERE campaign_id = ? AND status = 'failed'");
    $s2->execute([$cid]);
    $failed = (int) $s2->fetchColumn();

    $s3 = $db->prepare('SELECT lead_group FROM campaigns WHERE id = ?');
    $s3->execute([$cid]);
    $lead_group = $s3->fetchColumn();

    if ($lead_group === 'all') {
        $total = (int) $db->query('SELECT COUNT(*) FROM leads')->fetchColumn();
    } else {
        $s4 = $db->prepare('SELECT COUNT(*) FROM leads WHERE platform = ?');
        $s4->execute([$lead_group]);
        $total = (int) $s4->fetchColumn();
    }

    header('Content-Type: application/json');
    echo json_encode([
        'status' => $camp['status'] ?? 'unknown',
        'sent'   => $sent,
        'failed' => $failed,
        'total'  => $total,
    ]);
    exit;
}

// ── Start sending ─────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $campaign_id) {
    // Validate campaign exists and is not already running
    $camp = $db->prepare('SELECT * FROM campaigns WHERE id = ?');
    $camp->execute([$campaign_id]);
    $camp = $camp->fetch();

    if (!$camp) {
        flash('error', 'Campaign not found.');
        header('Location: campaigns.php');
        exit;
    }

    if ($camp['status'] === 'running') {
        flash('error', 'Campaign is already running.');
        header('Location: campaigns.php');
        exit;
    }

    // Check SMTP accounts available
    $smtp_count = (int) $db->query('SELECT COUNT(*) FROM smtp_accounts WHERE active = 1')->fetchColumn();
    if ($smtp_count === 0) {
        flash('error', 'No active SMTP accounts. Please add at least one.');
        header('Location: smtp.php');
        exit;
    }

    // Run synchronously (for small lists) – for large lists use cron/send_campaign.php
    set_time_limit(0);
    run_campaign($campaign_id);

    flash('success', 'Campaign sending complete. Check stats for results.');
    header('Location: stats.php?campaign_id=' . $campaign_id);
    exit;
}

// ── Show confirmation page ────────────────────────────────────────────────────

if (!$campaign_id) {
    header('Location: campaigns.php');
    exit;
}

$campaign = $db->prepare('
    SELECT c.*, t.name AS template_name
    FROM campaigns c
    LEFT JOIN email_templates t ON t.id = c.template_id
    WHERE c.id = ?
');
$campaign->execute([$campaign_id]);
$campaign = $campaign->fetch();

if (!$campaign) {
    flash('error', 'Campaign not found.');
    header('Location: campaigns.php');
    exit;
}

// Lead count
$lead_group = $campaign['lead_group'];
if ($lead_group === 'all') {
    $lead_count = (int) $db->query('SELECT COUNT(*) FROM leads')->fetchColumn();
} else {
    $stmt = $db->prepare('SELECT COUNT(*) FROM leads WHERE platform = ?');
    $stmt->execute([$lead_group]);
    $lead_count = (int) $stmt->fetchColumn();
}

$smtp_count = (int) $db->query('SELECT COUNT(*) FROM smtp_accounts WHERE active = 1')->fetchColumn();

// Already sent in this campaign
$as = $db->prepare("SELECT COUNT(*) FROM campaign_logs WHERE campaign_id = ? AND status = 'sent'");
$as->execute([$campaign_id]);
$already_sent = (int) $as->fetchColumn();

$page_title = 'Send Campaign';
require __DIR__ . '/partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <h3 class="fw-bold mb-4"><i class="bi bi-send-fill me-2"></i>Send Campaign</h3>

        <div class="card card-form p-4">
            <h5 class="mb-3"><?= h($campaign['name']) ?></h5>

            <table class="table table-sm">
                <tr><th>Template</th><td><?= h($campaign['template_name'] ?? '—') ?></td></tr>
                <tr><th>Lead Group</th><td><?= h($lead_group === 'all' ? 'All Leads' : 'Platform: ' . $lead_group) ?></td></tr>
                <tr><th>Total Leads</th><td><?= number_format($lead_count) ?></td></tr>
                <tr><th>Active SMTP Accounts</th><td><?= $smtp_count ?></td></tr>
                <tr><th>Emails per SMTP</th><td><?= $campaign['emails_per_smtp'] ?></td></tr>
                <tr><th>Pause between batches</th><td><?= $campaign['pause_seconds'] ?>s</td></tr>
                <tr><th>Already sent</th><td><?= number_format($already_sent) ?></td></tr>
                <tr><th>Remaining</th><td><?= number_format(max(0, $lead_count - $already_sent)) ?></td></tr>
            </table>

            <?php if ($smtp_count === 0): ?>
                <div class="alert alert-danger">
                    <strong>No active SMTP accounts!</strong> Please <a href="smtp.php">add SMTP accounts</a> before sending.
                </div>
            <?php elseif ($lead_count === 0): ?>
                <div class="alert alert-warning">
                    No leads match this campaign. <a href="leads.php?action=import">Import leads</a> first.
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Sending will run in this browser tab. For very large lists, use the cron script instead.
                    <br><strong>Do not close this tab</strong> while sending is in progress.
                </div>

                <form method="post">
                    <input type="hidden" name="campaign_id" value="<?= $campaign_id ?>">
                    <button type="submit" class="btn btn-success btn-lg w-100"
                        data-confirm="Start sending <?= number_format(max(0, $lead_count - $already_sent)) ?> emails?">
                        <i class="bi bi-send-fill me-2"></i>
                        Start Sending <?= number_format(max(0, $lead_count - $already_sent)) ?> Emails
                    </button>
                </form>
            <?php endif; ?>

            <div class="mt-3">
                <a href="campaigns.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Campaigns
                </a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
