<?php
/**
 * Anti-Spam Word Filter Management
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$db = get_db();

// ── POST handlers ──────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['delete_id'])) {
        $db->prepare('DELETE FROM antispam_rules WHERE id = ?')->execute([(int) $_POST['delete_id']]);
        flash('success', 'Rule deleted.');
        header('Location: antispam.php');
        exit;
    }

    if (isset($_POST['add_rule'])) {
        $bad_word    = trim($_POST['bad_word']    ?? '');
        $replacement = trim($_POST['replacement'] ?? '');

        if (!$bad_word) {
            flash('error', 'Spam word/phrase is required.');
        } else {
            $db->prepare('INSERT INTO antispam_rules (bad_word, replacement) VALUES (?,?)')
               ->execute([$bad_word, $replacement]);
            flash('success', 'Anti-spam rule added.');
        }
        header('Location: antispam.php');
        exit;
    }

    if (isset($_POST['update_rule'])) {
        $id          = (int) ($_POST['id']          ?? 0);
        $bad_word    = trim($_POST['bad_word']    ?? '');
        $replacement = trim($_POST['replacement'] ?? '');

        if (!$bad_word || !$id) {
            flash('error', 'Word and ID are required.');
        } else {
            $db->prepare('UPDATE antispam_rules SET bad_word=?,replacement=? WHERE id=?')
               ->execute([$bad_word, $replacement, $id]);
            flash('success', 'Rule updated.');
        }
        header('Location: antispam.php');
        exit;
    }

    if (isset($_POST['delete_all'])) {
        $db->exec('DELETE FROM antispam_rules');
        flash('success', 'All rules deleted.');
        header('Location: antispam.php');
        exit;
    }
}

$rules = $db->query('SELECT * FROM antispam_rules ORDER BY id ASC')->fetchAll();

$page_title = 'Anti-Spam Rules';
require __DIR__ . '/partials/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0"><i class="bi bi-shield-check me-2"></i>Anti-Spam Word Filters</h3>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    These rules automatically replace spam-trigger words/phrases in your email subjects and HTML bodies before sending.
    Leave <em>Replacement</em> blank to remove the word entirely.
</div>

<!-- Add Rule Form -->
<div class="card card-form p-4 mb-4">
    <h5 class="fw-bold mb-3">Add New Rule</h5>
    <form method="post" class="row g-3">
        <div class="col-md-5">
            <label class="form-label">Spam Word / Phrase <span class="text-danger">*</span></label>
            <input type="text" name="bad_word" class="form-control" placeholder="e.g. free money" required>
        </div>
        <div class="col-md-5">
            <label class="form-label">Replacement <small class="text-muted">(blank = remove)</small></label>
            <input type="text" name="replacement" class="form-control" placeholder="e.g. exclusive offer">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" name="add_rule" value="1" class="btn btn-primary w-100">Add Rule</button>
        </div>
    </form>
</div>

<!-- Rules Table -->
<div class="table-wrapper">
    <table class="table table-hover">
        <thead>
            <tr><th>#</th><th>Spam Word / Phrase</th><th>Replacement</th><th>Added</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (empty($rules)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No rules yet.</td></tr>
        <?php else: ?>
            <?php foreach ($rules as $rule): ?>
            <tr>
                <form method="post">
                    <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                    <td><?= $rule['id'] ?></td>
                    <td><input type="text" name="bad_word" class="form-control form-control-sm" value="<?= h($rule['bad_word']) ?>"></td>
                    <td><input type="text" name="replacement" class="form-control form-control-sm" value="<?= h($rule['replacement']) ?>"></td>
                    <td><?= date('M j, Y', strtotime($rule['created_at'])) ?></td>
                    <td class="d-flex gap-1">
                        <button type="submit" name="update_rule" value="1" class="btn btn-sm btn-outline-primary"><i class="bi bi-check-lg"></i></button>
                        <button type="submit" name="delete_id" value="<?= $rule['id'] ?>" class="btn btn-sm btn-outline-danger"
                            data-confirm="Delete this rule?"><i class="bi bi-trash"></i></button>
                    </td>
                </form>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (!empty($rules)): ?>
<div class="mt-3">
    <form method="post" class="d-inline">
        <button type="submit" name="delete_all" value="1" class="btn btn-sm btn-outline-danger"
            data-confirm="Delete ALL anti-spam rules?">
            <i class="bi bi-trash-fill"></i> Delete All Rules
        </button>
    </form>
</div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
