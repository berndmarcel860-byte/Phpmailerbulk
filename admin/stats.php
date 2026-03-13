<?php
/**
 * Campaign Statistics
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$db          = get_db();
$campaign_id = (int) ($_GET['campaign_id'] ?? 0);

$campaigns = $db->query('SELECT id, name, status, created_at FROM campaigns ORDER BY id DESC')->fetchAll();

$selected_campaign = null;
$stats             = [];
$logs              = [];

if ($campaign_id) {
    $stmt = $db->prepare('
        SELECT c.*, t.name AS template_name
        FROM campaigns c
        LEFT JOIN email_templates t ON t.id = c.template_id
        WHERE c.id = ?
    ');
    $stmt->execute([$campaign_id]);
    $selected_campaign = $stmt->fetch();

    if ($selected_campaign) {
        // Overall stats
        $s2 = $db->prepare("
            SELECT
                COUNT(*)                                                        AS total,
                SUM(CASE WHEN status = 'sent'   THEN 1 ELSE 0 END)             AS sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END)             AS failed,
                COUNT(DISTINCT smtp_id)                                         AS smtp_used,
                MIN(sent_at)                                                    AS first_sent,
                MAX(sent_at)                                                    AS last_sent
            FROM campaign_logs
            WHERE campaign_id = ?
        ");
        $s2->execute([$campaign_id]);
        $stats = $s2->fetch();

        // Per-SMTP breakdown
        $s3 = $db->prepare("
            SELECT s.name AS smtp_name, s.from_email,
                   SUM(CASE WHEN cl.status = 'sent'   THEN 1 ELSE 0 END) AS sent,
                   SUM(CASE WHEN cl.status = 'failed' THEN 1 ELSE 0 END) AS failed
            FROM campaign_logs cl
            JOIN smtp_accounts s ON s.id = cl.smtp_id
            WHERE cl.campaign_id = ?
            GROUP BY cl.smtp_id
            ORDER BY sent DESC
        ");
        $s3->execute([$campaign_id]);
        $smtp_breakdown = $s3->fetchAll();

        // Recent logs
        $per_page = 50;
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $offset   = ($page - 1) * $per_page;

        $s4 = $db->prepare('SELECT COUNT(*) FROM campaign_logs WHERE campaign_id = ?');
        $s4->execute([$campaign_id]);
        $total_logs = (int) $s4->fetchColumn();

        $s5 = $db->prepare("
            SELECT cl.id, cl.status, cl.error_message, cl.sent_at,
                   l.first_name, l.last_name, l.email,
                   s.name AS smtp_name
            FROM campaign_logs cl
            JOIN leads l ON l.id = cl.lead_id
            JOIN smtp_accounts s ON s.id = cl.smtp_id
            WHERE cl.campaign_id = ?
            ORDER BY cl.id DESC
            LIMIT :lim OFFSET :off
        ");
        $s5->bindValue(1, $campaign_id, PDO::PARAM_INT);
        $s5->bindValue(':lim', $per_page, PDO::PARAM_INT);
        $s5->bindValue(':off', $offset,   PDO::PARAM_INT);
        $s5->execute();
        $logs = $s5->fetchAll();
    }
}

$page_title = 'Statistics';
require __DIR__ . '/partials/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0"><i class="bi bi-bar-chart-fill me-2"></i>Campaign Statistics</h3>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-form p-3">
            <label class="form-label fw-bold">Select Campaign</label>
            <select class="form-select" onchange="location='stats.php?campaign_id='+this.value">
                <option value="">— Choose Campaign —</option>
                <?php foreach ($campaigns as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $campaign_id === (int)$c['id'] ? 'selected' : '' ?>>
                        #<?= $c['id'] ?> – <?= h($c['name']) ?> (<?= ucfirst($c['status']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<?php if ($selected_campaign): ?>

<!-- Campaign header -->
<div class="card card-form p-3 mb-4">
    <div class="row">
        <div class="col-md-6">
            <h5 class="fw-bold"><?= h($selected_campaign['name']) ?></h5>
            <p class="text-muted mb-1">Template: <?= h($selected_campaign['template_name'] ?? '—') ?></p>
            <p class="text-muted mb-0">Lead Group: <?= h($selected_campaign['lead_group'] === 'all' ? 'All Leads' : 'Platform: ' . $selected_campaign['lead_group']) ?></p>
        </div>
        <div class="col-md-6 text-md-end">
            <span class="badge badge-status-<?= h($selected_campaign['status']) ?> fs-6">
                <?= ucfirst(h($selected_campaign['status'])) ?>
            </span><br>
            <small class="text-muted">
                Started: <?= $stats['first_sent'] ? date('M j Y, H:i', strtotime($stats['first_sent'])) : '—' ?><br>
                Last sent: <?= $stats['last_sent'] ? date('M j Y, H:i', strtotime($stats['last_sent'])) : '—' ?>
            </small>
        </div>
    </div>
</div>

<!-- Stat cards -->
<?php
$total  = (int) ($stats['total']  ?? 0);
$sent   = (int) ($stats['sent']   ?? 0);
$failed = (int) ($stats['failed'] ?? 0);
$rate   = $total > 0 ? round($sent / $total * 100, 1) : 0;
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card text-white bg-primary p-3">
            <div class="stat-icon"><i class="bi bi-envelope-fill"></i></div>
            <div class="stat-number"><?= number_format($total) ?></div>
            <div class="stat-label">Total Processed</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card text-white bg-success p-3">
            <div class="stat-icon"><i class="bi bi-envelope-check-fill"></i></div>
            <div class="stat-number"><?= number_format($sent) ?></div>
            <div class="stat-label">Sent Successfully</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card text-white bg-danger p-3">
            <div class="stat-icon"><i class="bi bi-envelope-x-fill"></i></div>
            <div class="stat-number"><?= number_format($failed) ?></div>
            <div class="stat-label">Failed</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card text-white bg-info p-3">
            <div class="stat-icon"><i class="bi bi-percent"></i></div>
            <div class="stat-number"><?= $rate ?>%</div>
            <div class="stat-label">Success Rate</div>
        </div>
    </div>
</div>

<!-- Progress bar -->
<?php if ($total > 0): ?>
<div class="mb-4">
    <div class="d-flex justify-content-between small text-muted mb-1">
        <span>Success rate</span><span><?= $rate ?>%</span>
    </div>
    <div class="progress" style="height:12px;border-radius:6px;">
        <div class="progress-bar bg-success" style="width:<?= $rate ?>%"></div>
        <div class="progress-bar bg-danger" style="width:<?= round($failed/$total*100,1) ?>%"></div>
    </div>
</div>
<?php endif; ?>

<!-- SMTP Breakdown -->
<?php if (!empty($smtp_breakdown)): ?>
<div class="card card-form p-3 mb-4">
    <h6 class="fw-bold mb-3"><i class="bi bi-server me-2"></i>Per-SMTP Breakdown</h6>
    <table class="table table-sm table-hover">
        <thead>
            <tr><th>SMTP</th><th>From Email</th><th>Sent</th><th>Failed</th></tr>
        </thead>
        <tbody>
        <?php foreach ($smtp_breakdown as $sb): ?>
            <tr>
                <td><?= h($sb['smtp_name']) ?></td>
                <td><?= h($sb['from_email']) ?></td>
                <td><span class="text-success fw-bold"><?= number_format($sb['sent']) ?></span></td>
                <td><span class="text-danger"><?= number_format($sb['failed']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Log table -->
<div class="table-wrapper">
    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">Send Log</h6>
        <a href="stats.php?campaign_id=<?= $campaign_id ?>&export=csv" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download"></i> Export CSV
        </a>
    </div>
    <table class="table table-hover table-sm">
        <thead>
            <tr>
                <th>Email</th>
                <th>Name</th>
                <th>SMTP</th>
                <th>Status</th>
                <th>Time</th>
                <th>Error</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($logs)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No logs yet.</td></tr>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= h($log['email']) ?></td>
                <td><?= h(trim($log['first_name'] . ' ' . $log['last_name'])) ?></td>
                <td><?= h($log['smtp_name']) ?></td>
                <td>
                    <span class="badge badge-status-<?= h($log['status']) ?>">
                        <?= ucfirst(h($log['status'])) ?>
                    </span>
                </td>
                <td><?= $log['sent_at'] ? date('H:i:s', strtotime($log['sent_at'])) : '—' ?></td>
                <td class="text-danger small"><?= h($log['error_message']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?= pagination($total_logs ?? 0, $page ?? 1, 50, "stats.php?campaign_id={$campaign_id}&page=%d") ?>

<?php else: ?>
    <div class="alert alert-info">Select a campaign above to view its statistics.</div>
<?php endif; ?>

<?php
// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $campaign_id && $selected_campaign) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="campaign_' . $campaign_id . '_stats.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Email', 'First Name', 'Last Name', 'SMTP', 'Status', 'Time', 'Error']);
    $exp = $db->prepare("
        SELECT l.email, l.first_name, l.last_name, s.name AS smtp_name,
               cl.status, cl.sent_at, cl.error_message
        FROM campaign_logs cl
        JOIN leads l ON l.id = cl.lead_id
        JOIN smtp_accounts s ON s.id = cl.smtp_id
        WHERE cl.campaign_id = ?
        ORDER BY cl.id ASC
    ");
    $exp->execute([$campaign_id]);
    $all_logs = $exp->fetchAll();
    foreach ($all_logs as $row) {
        fputcsv($out, [$row['email'], $row['first_name'], $row['last_name'], $row['smtp_name'], $row['status'], $row['sent_at'], $row['error_message']]);
    }
    fclose($out);
    exit;
}

require __DIR__ . '/partials/footer.php';
?>
