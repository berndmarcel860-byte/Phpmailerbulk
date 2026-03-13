<?php
/**
 * Campaign Management
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$db     = get_db();
$action = $_GET['action'] ?? 'list';

// ── POST handlers ──────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['delete_id'])) {
        $db->prepare('DELETE FROM campaigns WHERE id = ?')->execute([(int) $_POST['delete_id']]);
        flash('success', 'Campaign deleted.');
        header('Location: campaigns.php');
        exit;
    }

    if (isset($_POST['save_campaign'])) {
        $id              = (int) ($_POST['id']              ?? 0);
        $name            = trim($_POST['name']              ?? '');
        $template_id     = (int) ($_POST['template_id']    ?? 0);
        $lead_group      = trim($_POST['lead_group']        ?? 'all');
        $emails_per_smtp = max(1, (int) ($_POST['emails_per_smtp'] ?? DEFAULT_EMAILS_PER_SMTP));
        $pause_seconds   = max(0, (int) ($_POST['pause_seconds']   ?? DEFAULT_PAUSE_SECONDS));

        if (!$name || !$template_id) {
            flash('error', 'Campaign name and template are required.');
        } else {
            if ($id) {
                $db->prepare('UPDATE campaigns SET name=?,template_id=?,lead_group=?,emails_per_smtp=?,pause_seconds=? WHERE id=?')
                   ->execute([$name, $template_id, $lead_group, $emails_per_smtp, $pause_seconds, $id]);
                flash('success', 'Campaign updated.');
            } else {
                $db->prepare('INSERT INTO campaigns (name,template_id,lead_group,emails_per_smtp,pause_seconds) VALUES (?,?,?,?,?)')
                   ->execute([$name, $template_id, $lead_group, $emails_per_smtp, $pause_seconds]);
                flash('success', 'Campaign created.');
            }
        }
        header('Location: campaigns.php');
        exit;
    }
}

// Load for edit
$edit_c = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $edit_c = $db->prepare('SELECT * FROM campaigns WHERE id = ?');
    $edit_c->execute([(int) $_GET['id']]);
    $edit_c = $edit_c->fetch();
}

$templates  = $db->query('SELECT id, name FROM email_templates ORDER BY name ASC')->fetchAll();
$platforms  = $db->query("SELECT DISTINCT platform FROM leads WHERE platform != '' ORDER BY platform ASC")->fetchAll(PDO::FETCH_COLUMN);

$campaigns = $db->query('
    SELECT c.*,
           t.name AS template_name,
           COUNT(DISTINCT cl.id) AS total_sent,
           SUM(CASE WHEN cl.status = "failed" THEN 1 ELSE 0 END) AS total_failed
    FROM campaigns c
    LEFT JOIN email_templates t ON t.id = c.template_id
    LEFT JOIN campaign_logs cl ON cl.campaign_id = c.id
    GROUP BY c.id
    ORDER BY c.id DESC
')->fetchAll();

$page_title = 'Campaigns';
require __DIR__ . '/partials/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0"><i class="bi bi-megaphone-fill me-2"></i>Campaigns</h3>
    <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Campaign</a>
</div>

<?php if ($action === 'create' || $action === 'edit'): ?>
<div class="card card-form p-4 mb-4">
    <h5 class="fw-bold mb-3"><?= $edit_c ? 'Edit Campaign' : 'New Campaign' ?></h5>
    <form method="post">
        <input type="hidden" name="id" value="<?= $edit_c['id'] ?? 0 ?>">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Campaign Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= h($edit_c['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email Template <span class="text-danger">*</span></label>
                <select name="template_id" class="form-select" required>
                    <option value="">— Select Template —</option>
                    <?php foreach ($templates as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= ($edit_c['template_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                            <?= h($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Lead Group</label>
                <select name="lead_group" class="form-select">
                    <option value="all" <?= (!$edit_c || $edit_c['lead_group'] === 'all') ? 'selected' : '' ?>>All Leads</option>
                    <?php foreach ($platforms as $p): ?>
                        <option value="<?= h($p) ?>" <?= ($edit_c['lead_group'] ?? '') === $p ? 'selected' : '' ?>>
                            Platform: <?= h($p) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Filter leads by platform, or send to all.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Emails per SMTP before rotating</label>
                <input type="number" name="emails_per_smtp" class="form-control" min="1" max="100"
                    value="<?= h($edit_c['emails_per_smtp'] ?? DEFAULT_EMAILS_PER_SMTP) ?>">
                <div class="form-text">Rotate to next SMTP account after this many emails.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Pause between batches (seconds)</label>
                <input type="number" name="pause_seconds" class="form-control" min="0" max="300"
                    value="<?= h($edit_c['pause_seconds'] ?? DEFAULT_PAUSE_SECONDS) ?>">
                <div class="form-text">Anti-spam delay between SMTP batches (0 = no pause).</div>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" name="save_campaign" value="1" class="btn btn-primary">
                <?= $edit_c ? 'Update Campaign' : 'Create Campaign' ?>
            </button>
            <a href="campaigns.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="table-wrapper">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Template</th>
                <th>Lead Group</th>
                <th>Sent</th>
                <th>Failed</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($campaigns)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No campaigns yet.</td></tr>
        <?php else: ?>
            <?php foreach ($campaigns as $c): ?>
            <tr>
                <td><?= $c['id'] ?></td>
                <td><?= h($c['name']) ?></td>
                <td><?= h($c['template_name'] ?? '—') ?></td>
                <td><?= h($c['lead_group'] === 'all' ? 'All Leads' : 'Platform: ' . $c['lead_group']) ?></td>
                <td><?= number_format($c['total_sent'] ?? 0) ?></td>
                <td><?= number_format($c['total_failed'] ?? 0) ?></td>
                <td>
                    <span class="badge badge-status-<?= h($c['status']) ?>">
                        <?= ucfirst(h($c['status'])) ?>
                    </span>
                </td>
                <td><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                <td>
                    <div class="d-flex gap-1">
                        <?php if (in_array($c['status'], ['pending', 'failed', 'completed'])): ?>
                        <a href="send.php?campaign_id=<?= $c['id'] ?>" class="btn btn-sm btn-success" title="Send Campaign">
                            <i class="bi bi-send-fill"></i>
                        </a>
                        <?php endif; ?>
                        <a href="stats.php?campaign_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-info" title="Stats">
                            <i class="bi bi-bar-chart-fill"></i>
                        </a>
                        <?php if ($c['status'] !== 'running'): ?>
                        <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                data-confirm="Delete this campaign and all its logs?">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
