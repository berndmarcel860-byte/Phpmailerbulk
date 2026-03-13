<?php
/**
 * Lead Management – list, add manually, import CSV
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$db     = get_db();
$action = $_GET['action'] ?? 'list';

// ── Handle POST ────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Delete single lead
    if (isset($_POST['delete_id'])) {
        $db->prepare('DELETE FROM leads WHERE id = ?')->execute([(int) $_POST['delete_id']]);
        flash('success', 'Lead deleted.');
        header('Location: leads.php');
        exit;
    }

    // Add single lead
    if (isset($_POST['add_lead'])) {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name']  ?? '');
        $email      = trim($_POST['email']      ?? '');
        $platform   = trim($_POST['platform']   ?? '');
        $amount     = trim($_POST['amount']     ?? '');
        $date_field = trim($_POST['date_field'] ?? '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'A valid email is required.');
        } else {
            try {
                $db->prepare('INSERT INTO leads (first_name, last_name, email, platform, amount, date_field) VALUES (?,?,?,?,?,?)')
                   ->execute([$first_name, $last_name, $email, $platform, $amount, $date_field]);
                flash('success', 'Lead added successfully.');
            } catch (PDOException $e) {
                flash('error', 'Email already exists: ' . $email);
            }
        }
        header('Location: leads.php');
        exit;
    }

    // Import CSV
    if (isset($_POST['import_csv'])) {
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $rows   = parse_csv_file($_FILES['csv_file']['tmp_name']);
            $header = array_shift($rows);
            $map    = detect_lead_columns($header);

            if ($map['email'] === null) {
                flash('error', 'CSV must have an "email" column.');
                header('Location: leads.php?action=import');
                exit;
            }

            $inserted = 0;
            $skipped  = 0;

            foreach ($rows as $row) {
                $email = isset($map['email']) ? trim($row[$map['email']] ?? '') : '';
                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $skipped++;
                    continue;
                }
                $first_name = isset($map['first_name']) ? trim($row[$map['first_name']] ?? '') : '';
                $last_name  = isset($map['last_name'])  ? trim($row[$map['last_name']]  ?? '') : '';
                $platform   = isset($map['platform'])   ? trim($row[$map['platform']]   ?? '') : '';
                $amount     = isset($map['amount'])     ? trim($row[$map['amount']]     ?? '') : '';
                $date_field = isset($map['date_field']) ? trim($row[$map['date_field']] ?? '') : '';

                try {
                    $db->prepare('INSERT IGNORE INTO leads (first_name, last_name, email, platform, amount, date_field) VALUES (?,?,?,?,?,?)')
                       ->execute([$first_name, $last_name, $email, $platform, $amount, $date_field]);
                    if ($db->lastInsertId()) $inserted++;
                    else $skipped++;
                } catch (PDOException $e) {
                    $skipped++;
                }
            }

            flash('success', "Import complete: {$inserted} added, {$skipped} skipped.");
        } else {
            flash('error', 'Please upload a CSV file.');
        }
        header('Location: leads.php');
        exit;
    }

    // Delete all leads
    if (isset($_POST['delete_all'])) {
        $db->exec('DELETE FROM leads');
        flash('success', 'All leads deleted.');
        header('Location: leads.php');
        exit;
    }
}

// ── List ───────────────────────────────────────────────────────────────────────
$per_page = 50;
$page     = max(1, (int) ($_GET['page'] ?? 1));
$search   = trim($_GET['search'] ?? '');

$where  = '';
$params = [];
if ($search) {
    $where    = 'WHERE email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR platform LIKE ?';
    $like     = '%' . $search . '%';
    $params   = [$like, $like, $like, $like];
}

$total  = $db->prepare("SELECT COUNT(*) FROM leads {$where}");
$total->execute($params);
$total  = (int) $total->fetchColumn();

$offset = ($page - 1) * $per_page;
$stmt   = $db->prepare("SELECT * FROM leads {$where} ORDER BY id DESC LIMIT :limit OFFSET :offset");
foreach ($params as $i => $val) {
    $stmt->bindValue($i + 1, $val);
}
$stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
$stmt->execute();
$leads  = $stmt->fetchAll();

$page_title = 'Leads';
require __DIR__ . '/partials/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0"><i class="bi bi-people-fill me-2"></i>Leads <span class="badge bg-secondary"><?= number_format($total) ?></span></h3>
    <div class="d-flex gap-2">
        <a href="?action=add"    class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Lead</a>
        <a href="?action=import" class="btn btn-outline-primary"><i class="bi bi-upload"></i> Import CSV</a>
    </div>
</div>

<?php if ($action === 'add'): ?>
<!-- Add Lead Form -->
<div class="card card-form p-4 mb-4">
    <h5 class="fw-bold mb-3">Add Single Lead</h5>
    <form method="post">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Platform</label>
                <input type="text" name="platform" class="form-control" placeholder="e.g. Binance">
            </div>
            <div class="col-md-4">
                <label class="form-label">Amount</label>
                <input type="text" name="amount" class="form-control" placeholder="e.g. 5000.00">
            </div>
            <div class="col-md-4">
                <label class="form-label">Date</label>
                <input type="text" name="date_field" class="form-control" placeholder="e.g. 2024-01-15">
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" name="add_lead" value="1" class="btn btn-primary">Add Lead</button>
            <a href="leads.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($action === 'import'): ?>
<!-- Import CSV Form -->
<div class="card card-form p-4 mb-4">
    <h5 class="fw-bold mb-3"><i class="bi bi-upload me-2"></i>Import Leads from CSV</h5>
    <div class="alert alert-info">
        <strong>Supported formats:</strong><br>
        • <code>name, email</code><br>
        • <code>first_name, last_name, email</code><br>
        • <code>first_name, last_name, email, platform, amount, date</code><br>
        <small class="text-muted">CSV must have a header row. Duplicate emails are skipped automatically.</small>
    </div>
    <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">CSV File</label>
            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv,.txt" required>
            <div id="csv-hint" class="form-text text-success" style="display:none">
                <i class="bi bi-check-circle"></i> File selected – columns will be auto-detected from the header row.
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" name="import_csv" value="1" class="btn btn-primary"><i class="bi bi-upload"></i> Import</button>
            <a href="leads.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Search -->
<form method="get" class="mb-3 d-flex gap-2">
    <input type="text" name="search" class="form-control" placeholder="Search by name, email, platform…" value="<?= h($search) ?>">
    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
    <?php if ($search): ?><a href="leads.php" class="btn btn-outline-danger"><i class="bi bi-x"></i></a><?php endif; ?>
</form>

<!-- Leads Table -->
<div class="table-wrapper">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Platform</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Added</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($leads)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No leads found.</td></tr>
        <?php else: ?>
            <?php foreach ($leads as $lead): ?>
            <tr>
                <td><?= $lead['id'] ?></td>
                <td><?= h(trim($lead['first_name'] . ' ' . $lead['last_name'])) ?></td>
                <td><?= h($lead['email']) ?></td>
                <td><?= h($lead['platform']) ?></td>
                <td><?= h($lead['amount']) ?></td>
                <td><?= h($lead['date_field']) ?></td>
                <td><?= date('M j, Y', strtotime($lead['created_at'])) ?></td>
                <td>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="delete_id" value="<?= $lead['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                            data-confirm="Delete this lead?"
                            ><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="mt-3 d-flex justify-content-between align-items-center">
    <?= pagination($total, $page, $per_page, 'leads.php?page=%d' . ($search ? '&search=' . urlencode($search) : '')) ?>
    <?php if ($total > 0): ?>
    <form method="post" class="d-inline">
        <button type="submit" name="delete_all" value="1" class="btn btn-sm btn-outline-danger"
            data-confirm="Delete ALL leads? This cannot be undone.">
            <i class="bi bi-trash-fill"></i> Delete All Leads
        </button>
    </form>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
