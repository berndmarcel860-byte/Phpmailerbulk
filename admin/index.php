<?php
/**
 * Admin Dashboard
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$db = get_db();

$stats = [
    'leads'     => $db->query('SELECT COUNT(*) FROM leads')->fetchColumn(),
    'smtp'      => $db->query('SELECT COUNT(*) FROM smtp_accounts WHERE active = 1')->fetchColumn(),
    'templates' => $db->query('SELECT COUNT(*) FROM email_templates')->fetchColumn(),
    'campaigns' => $db->query('SELECT COUNT(*) FROM campaigns')->fetchColumn(),
    'sent'      => $db->query("SELECT COUNT(*) FROM campaign_logs WHERE status = 'sent'")->fetchColumn(),
    'failed'    => $db->query("SELECT COUNT(*) FROM campaign_logs WHERE status = 'failed'")->fetchColumn(),
];

// Recent campaigns
$recent_campaigns = $db->query('
    SELECT c.id, c.name, c.status, c.created_at,
           COUNT(DISTINCT cl.id) AS total_sent
    FROM campaigns c
    LEFT JOIN campaign_logs cl ON cl.campaign_id = c.id AND cl.status = "sent"
    GROUP BY c.id
    ORDER BY c.created_at DESC LIMIT 5
')->fetchAll();

// Emails sent last 7 days
$chart_data = $db->query('
    SELECT DATE(sent_at) AS day, COUNT(*) AS cnt
    FROM campaign_logs
    WHERE status = "sent" AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(sent_at)
    ORDER BY day ASC
')->fetchAll();

$page_title = 'Dashboard';
require __DIR__ . '/partials/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h3 class="fw-bold"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h3>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card text-white bg-primary h-100 p-3">
            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
            <div class="stat-number"><?= number_format($stats['leads']) ?></div>
            <div class="stat-label">Total Leads</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card text-white bg-success h-100 p-3">
            <div class="stat-icon"><i class="bi bi-envelope-check-fill"></i></div>
            <div class="stat-number"><?= number_format($stats['sent']) ?></div>
            <div class="stat-label">Emails Sent</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card text-white bg-danger h-100 p-3">
            <div class="stat-icon"><i class="bi bi-envelope-x-fill"></i></div>
            <div class="stat-number"><?= number_format($stats['failed']) ?></div>
            <div class="stat-label">Failed</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card text-white bg-info h-100 p-3">
            <div class="stat-icon"><i class="bi bi-server"></i></div>
            <div class="stat-number"><?= $stats['smtp'] ?></div>
            <div class="stat-label">SMTP Active</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card text-white bg-warning h-100 p-3">
            <div class="stat-icon"><i class="bi bi-file-richtext-fill"></i></div>
            <div class="stat-number"><?= $stats['templates'] ?></div>
            <div class="stat-label">Templates</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card text-white bg-secondary h-100 p-3">
            <div class="stat-icon"><i class="bi bi-megaphone-fill"></i></div>
            <div class="stat-number"><?= $stats['campaigns'] ?></div>
            <div class="stat-label">Campaigns</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Campaigns -->
    <div class="col-lg-7">
        <div class="table-wrapper p-3">
            <h5 class="fw-bold mb-3"><i class="bi bi-megaphone-fill me-2"></i>Recent Campaigns</h5>
            <?php if (empty($recent_campaigns)): ?>
                <p class="text-muted">No campaigns yet. <a href="campaigns.php">Create one</a>.</p>
            <?php else: ?>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent_campaigns as $c): ?>
                    <tr>
                        <td><?= h($c['name']) ?></td>
                        <td><span class="badge badge-status-<?= h($c['status']) ?>"><?= h(ucfirst($c['status'])) ?></span></td>
                        <td><?= number_format($c['total_sent']) ?></td>
                        <td><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                        <td><a href="stats.php?campaign_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">Stats</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="col-lg-5">
        <div class="card card-form p-3">
            <h5 class="fw-bold mb-3"><i class="bi bi-lightning-fill me-2"></i>Quick Actions</h5>
            <div class="d-grid gap-2">
                <a href="leads.php?action=import" class="btn btn-outline-primary"><i class="bi bi-upload me-2"></i>Import Leads</a>
                <a href="campaigns.php?action=create" class="btn btn-outline-success"><i class="bi bi-plus-circle me-2"></i>New Campaign</a>
                <a href="smtp.php?action=create" class="btn btn-outline-info"><i class="bi bi-plus-circle me-2"></i>Add SMTP Account</a>
                <a href="templates.php?action=create" class="btn btn-outline-warning"><i class="bi bi-plus-circle me-2"></i>New Template</a>
            </div>
        </div>

        <?php if (!empty($chart_data)): ?>
        <div class="card card-form p-3 mt-3">
            <h5 class="fw-bold mb-3"><i class="bi bi-bar-chart-fill me-2"></i>Emails Sent (Last 7 Days)</h5>
            <table class="table table-sm">
                <thead><tr><th>Date</th><th>Sent</th></tr></thead>
                <tbody>
                <?php foreach ($chart_data as $row): ?>
                    <tr>
                        <td><?= h($row['day']) ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1 progress-thin">
                                    <div class="progress-bar bg-primary" style="width:<?= min(100, $row['cnt']) ?>%"></div>
                                </div>
                                <?= number_format($row['cnt']) ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
