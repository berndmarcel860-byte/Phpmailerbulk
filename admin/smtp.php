<?php
/**
 * SMTP Account Management
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
        $db->prepare('DELETE FROM smtp_accounts WHERE id = ?')->execute([(int) $_POST['delete_id']]);
        flash('success', 'SMTP account deleted.');
        header('Location: smtp.php');
        exit;
    }

    if (isset($_POST['toggle_id'])) {
        $db->prepare('UPDATE smtp_accounts SET active = NOT active WHERE id = ?')->execute([(int) $_POST['toggle_id']]);
        flash('success', 'SMTP account updated.');
        header('Location: smtp.php');
        exit;
    }

    if (isset($_POST['save_smtp'])) {
        $id         = (int) ($_POST['id'] ?? 0);
        $name       = trim($_POST['name']       ?? '');
        $host       = trim($_POST['host']       ?? '');
        $port       = (int) ($_POST['port']     ?? 587);
        $username   = trim($_POST['username']   ?? '');
        $password   = $_POST['password']        ?? '';
        $encryption = $_POST['encryption']      === 'ssl' ? 'ssl' : 'tls';
        $from_email = trim($_POST['from_email'] ?? '');
        $from_name  = trim($_POST['from_name']  ?? '');
        $eps        = max(1, (int) ($_POST['emails_per_session'] ?? 3));

        if (!$name || !$host || !$username || !$from_email) {
            flash('error', 'Name, host, username, and from email are required.');
        } else {
            if ($id) {
                // Update – keep old password if blank
                if ($password) {
                    $db->prepare('UPDATE smtp_accounts SET name=?,host=?,port=?,username=?,password=?,encryption=?,from_email=?,from_name=?,emails_per_session=? WHERE id=?')
                       ->execute([$name, $host, $port, $username, $password, $encryption, $from_email, $from_name, $eps, $id]);
                } else {
                    $db->prepare('UPDATE smtp_accounts SET name=?,host=?,port=?,username=?,encryption=?,from_email=?,from_name=?,emails_per_session=? WHERE id=?')
                       ->execute([$name, $host, $port, $username, $encryption, $from_email, $from_name, $eps, $id]);
                }
                flash('success', 'SMTP account updated.');
            } else {
                if (!$password) {
                    flash('error', 'Password is required for new accounts.');
                    header('Location: smtp.php?action=create');
                    exit;
                }
                $db->prepare('INSERT INTO smtp_accounts (name,host,port,username,password,encryption,from_email,from_name,emails_per_session) VALUES (?,?,?,?,?,?,?,?,?)')
                   ->execute([$name, $host, $port, $username, $password, $encryption, $from_email, $from_name, $eps]);
                flash('success', 'SMTP account added.');
            }
        }
        header('Location: smtp.php');
        exit;
    }
}

// Load for edit
$edit_smtp = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $edit_smtp = $db->prepare('SELECT * FROM smtp_accounts WHERE id = ?');
    $edit_smtp->execute([(int) $_GET['id']]);
    $edit_smtp = $edit_smtp->fetch();
}

$smtps = $db->query('SELECT * FROM smtp_accounts ORDER BY id DESC')->fetchAll();

$page_title = 'SMTP Accounts';
require __DIR__ . '/partials/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0"><i class="bi bi-server me-2"></i>SMTP Accounts</h3>
    <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add SMTP</a>
</div>

<?php if ($action === 'create' || $action === 'edit'): ?>
<div class="card card-form p-4 mb-4">
    <h5 class="fw-bold mb-3"><?= $edit_smtp ? 'Edit SMTP Account' : 'Add SMTP Account' ?></h5>
    <form method="post">
        <input type="hidden" name="id" value="<?= $edit_smtp['id'] ?? 0 ?>">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Name / Label <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= h($edit_smtp['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">SMTP Host <span class="text-danger">*</span></label>
                <input type="text" name="host" class="form-control" value="<?= h($edit_smtp['host'] ?? '') ?>" placeholder="smtp.gmail.com" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Port</label>
                <input type="number" name="port" class="form-control" value="<?= h($edit_smtp['port'] ?? 587) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Encryption</label>
                <select name="encryption" class="form-select">
                    <option value="tls" <?= (!$edit_smtp || $edit_smtp['encryption'] === 'tls') ? 'selected' : '' ?>>TLS (STARTTLS)</option>
                    <option value="ssl" <?= ($edit_smtp && $edit_smtp['encryption'] === 'ssl') ? 'selected' : '' ?>>SSL</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control" value="<?= h($edit_smtp['username'] ?? '') ?>" autocomplete="off" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Password <?= $edit_smtp ? '<small class="text-muted">(leave blank to keep current)</small>' : '<span class="text-danger">*</span>' ?></label>
                <div class="input-group">
                    <input type="password" id="smtp_password" name="password" class="form-control" autocomplete="off"
                        <?= !$edit_smtp ? 'required' : '' ?>>
                    <button type="button" class="btn btn-outline-secondary btn-toggle-password" data-target="smtp_password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">From Email <span class="text-danger">*</span></label>
                <input type="email" name="from_email" class="form-control" value="<?= h($edit_smtp['from_email'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">From Name</label>
                <input type="text" name="from_name" class="form-control" value="<?= h($edit_smtp['from_name'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Emails / Session</label>
                <input type="number" name="emails_per_session" class="form-control" value="<?= h($edit_smtp['emails_per_session'] ?? 3) ?>" min="1" max="100">
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" name="save_smtp" value="1" class="btn btn-primary">
                <?= $edit_smtp ? 'Update' : 'Add SMTP' ?>
            </button>
            <a href="smtp.php" class="btn btn-secondary">Cancel</a>
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
                <th>Host : Port</th>
                <th>From Email</th>
                <th>Emails/Session</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($smtps)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No SMTP accounts yet.</td></tr>
        <?php else: ?>
            <?php foreach ($smtps as $s): ?>
            <tr>
                <td><?= $s['id'] ?></td>
                <td><?= h($s['name']) ?></td>
                <td><?= h($s['host']) ?>:<strong><?= $s['port'] ?></strong>
                    <span class="badge bg-secondary"><?= strtoupper(h($s['encryption'])) ?></span></td>
                <td><?= h($s['from_email']) ?></td>
                <td><?= $s['emails_per_session'] ?></td>
                <td>
                    <span class="badge <?= $s['active'] ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $s['active'] ? 'Active' : 'Disabled' ?>
                    </span>
                </td>
                <td class="d-flex gap-1">
                    <a href="?action=edit&id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="toggle_id" value="<?= $s['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-<?= $s['active'] ? 'warning' : 'success' ?>">
                            <i class="bi bi-<?= $s['active'] ? 'pause' : 'play' ?>-fill"></i>
                        </button>
                    </form>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="delete_id" value="<?= $s['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this SMTP account?">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
