<?php
/**
 * Email Template Management
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_generator.php';

require_login();

$db     = get_db();
$action = $_GET['action'] ?? 'list';

// ── POST handlers ──────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['delete_id'])) {
        $db->prepare('DELETE FROM email_templates WHERE id = ?')->execute([(int) $_POST['delete_id']]);
        flash('success', 'Template deleted.');
        header('Location: templates.php');
        exit;
    }

    if (isset($_POST['save_template'])) {
        $id        = (int) ($_POST['id'] ?? 0);
        $name      = trim($_POST['name']      ?? '');
        $subject   = trim($_POST['subject']   ?? '');
        $html_body = $_POST['html_body'] ?? '';
        $text_body = $_POST['text_body'] ?? '';

        if (!$name || !$subject || !$html_body) {
            flash('error', 'Name, subject, and HTML body are required.');
        } else {
            if ($id) {
                $db->prepare('UPDATE email_templates SET name=?,subject=?,html_body=?,text_body=? WHERE id=?')
                   ->execute([$name, $subject, $html_body, $text_body, $id]);
                flash('success', 'Template updated.');
            } else {
                $db->prepare('INSERT INTO email_templates (name,subject,html_body,text_body) VALUES (?,?,?,?)')
                   ->execute([$name, $subject, $html_body, $text_body]);
                flash('success', 'Template created.');
            }
        }
        header('Location: templates.php');
        exit;
    }
}

// Load for edit
$edit_tpl = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $edit_tpl = $db->prepare('SELECT * FROM email_templates WHERE id = ?');
    $edit_tpl->execute([(int) $_GET['id']]);
    $edit_tpl = $edit_tpl->fetch();
}

$templates  = $db->query('SELECT id, name, subject, created_at FROM email_templates ORDER BY id DESC')->fetchAll();
$ai_enabled = ai_is_enabled();

$page_title = 'Email Templates';
require __DIR__ . '/partials/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0"><i class="bi bi-file-richtext-fill me-2"></i>Email Templates</h3>
    <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Template</a>
</div>

<?php if ($action === 'create' || $action === 'edit'): ?>
<div class="card card-form p-4 mb-4">
    <h5 class="fw-bold mb-3"><?= $edit_tpl ? 'Edit Template' : 'New Template' ?></h5>

    <div class="alert alert-info alert-sm">
        <strong>Available variables:</strong>
        <code>{{first_name}}</code> <code>{{last_name}}</code> <code>{{full_name}}</code>
        <code>{{email}}</code> <code>{{platform}}</code> <code>{{amount}}</code> <code>{{date}}</code>
    </div>

    <!-- ── AI Generator Panel ───────────────────────────────────────────────── -->
    <div class="ai-panel card mb-4 border-0 <?= $ai_enabled ? 'ai-panel--enabled' : 'ai-panel--disabled' ?>">
        <div class="ai-panel__header d-flex align-items-center gap-2 p-3">
            <span class="ai-panel__icon"><i class="bi bi-robot"></i></span>
            <div>
                <strong class="ai-panel__title">AI Template Generator</strong>
                <span class="ai-panel__sub ms-2 small">
                    <?php if ($ai_enabled): ?>
                        Powered by OpenAI &mdash; generates professional fund-recovery emails (German by default)
                    <?php else: ?>
                        Add your <code>OPENAI_API_KEY</code> to <code>config.php</code> to enable
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($ai_enabled): ?>
            <button type="button" id="btn-ai-collapse" class="btn btn-sm btn-outline-light ms-auto"
                    data-bs-toggle="collapse" data-bs-target="#aiPanel" aria-expanded="true">
                <i class="bi bi-chevron-up"></i>
            </button>
            <?php endif; ?>
        </div>

        <?php if ($ai_enabled): ?>
        <div class="collapse show" id="aiPanel">
            <div class="ai-panel__body p-3">
                <div class="row g-3">
                    <!-- Language -->
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Language</label>
                        <select id="ai_language" class="form-select form-select-sm">
                            <option value="de" selected>&#127465;&#127466; Deutsch (German)</option>
                            <option value="en">&#127468;&#127463; English</option>
                        </select>
                    </div>
                    <!-- Scenario -->
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Email Scenario</label>
                        <select id="ai_scenario" class="form-select form-select-sm">
                            <option value="initial" selected>Initial Outreach</option>
                            <option value="followup">Follow-Up</option>
                            <option value="final">Final Notice</option>
                            <option value="success">Funds Recovered</option>
                        </select>
                    </div>
                    <!-- Tone -->
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Tone</label>
                        <select id="ai_tone" class="form-select form-select-sm">
                            <option value="formal" selected>Formal &amp; Authoritative</option>
                            <option value="empathetic">Empathetic &amp; Reassuring</option>
                            <option value="urgent">Urgent &amp; Time-Sensitive</option>
                        </select>
                    </div>
                    <!-- Company -->
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Company Name</label>
                        <input type="text" id="ai_company" class="form-control form-control-sm"
                               value="Kryptox" placeholder="Your company name">
                    </div>
                    <!-- Website -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Company Website</label>
                        <input type="url" id="ai_website" class="form-control form-control-sm"
                               value="https://kryptox.co.uk" placeholder="https://example.com">
                    </div>
                    <!-- Extra instructions -->
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Additional Instructions <small class="text-muted">(optional)</small></label>
                        <input type="text" id="ai_extra" class="form-control form-control-sm"
                               placeholder="e.g. mention a 14-day free case review, or specific exchanges like Binance, Coinbase">
                    </div>
                </div>

                <div class="mt-3 d-flex align-items-center gap-3 flex-wrap">
                    <button type="button" id="btn-ai-generate" class="btn btn-ai-generate">
                        <i class="bi bi-stars"></i>
                        <span id="ai-btn-text">Generate with AI</span>
                    </button>
                    <div id="ai-status" class="small" style="display:none">
                        <span class="spinner-border spinner-border-sm me-1"></span>
                        <span id="ai-status-text">Generating professional template&hellip;</span>
                    </div>
                    <div id="ai-error" class="alert alert-danger py-1 px-2 small mb-0" style="display:none"></div>
                </div>

                <div class="mt-2">
                    <small class="text-muted" style="color:rgba(255,255,255,.5)!important">
                        <i class="bi bi-info-circle me-1"></i>
                        The AI inserts your template variables automatically. Review the result before saving.
                    </small>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <!-- ── /AI Generator Panel ──────────────────────────────────────────────── -->

    <form method="post" id="template-form">
        <input type="hidden" name="id" value="<?= $edit_tpl['id'] ?? 0 ?>">

        <div class="mb-3">
            <label class="form-label">Template Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="tpl_name" class="form-control" value="<?= h($edit_tpl['name'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email Subject <span class="text-danger">*</span></label>
            <input type="text" name="subject" id="tpl_subject" class="form-control" value="<?= h($edit_tpl['subject'] ?? '') ?>"
                placeholder="e.g. Dear {{first_name}}, your account update" required>
        </div>
        <div class="mb-3">
            <label class="form-label">HTML Body <span class="text-danger">*</span></label>
            <textarea name="html_body" id="html_body" class="form-control html-editor" required><?= h($edit_tpl['html_body'] ?? get_default_template()) ?></textarea>
            <div class="mt-2">
                <button type="button" id="btn-preview-template" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-eye"></i> Preview HTML
                </button>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Plain-Text Body <small class="text-muted">(optional &ndash; auto-generated if blank)</small></label>
            <textarea name="text_body" class="form-control html-editor" style="min-height:100px"><?= h($edit_tpl['text_body'] ?? '') ?></textarea>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" name="save_template" value="1" class="btn btn-primary">
                <?= $edit_tpl ? 'Update Template' : 'Create Template' ?>
            </button>
            <a href="templates.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="table-wrapper">
    <table class="table table-hover">
        <thead>
            <tr><th>#</th><th>Name</th><th>Subject</th><th>Created</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (empty($templates)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No templates yet.</td></tr>
        <?php else: ?>
            <?php foreach ($templates as $t): ?>
            <tr>
                <td><?= $t['id'] ?></td>
                <td><?= h($t['name']) ?></td>
                <td><?= h($t['subject']) ?></td>
                <td><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                <td class="d-flex gap-1">
                    <a href="?action=edit&id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="delete_id" value="<?= $t['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this template?">
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

<?php
/**
 * Return a professional default HTML email template.
 */
function get_default_template(): string {
    return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Email</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;">
  <tr>
    <td align="center" style="padding:30px 10px;">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1);">
        <!-- Header -->
        <tr>
          <td style="background:#0d6efd;padding:30px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:26px;">Company Name</h1>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:35px 40px;">
            <h2 style="color:#333;margin-top:0;">Hello, {{first_name}}!</h2>
            <p style="color:#555;line-height:1.6;font-size:15px;">
              We have an important update regarding your account on <strong>{{platform}}</strong>.
            </p>
            <p style="color:#555;line-height:1.6;font-size:15px;">
              Your balance of <strong>${{amount}}</strong> is ready for processing as of <strong>{{date}}</strong>.
            </p>
            <div style="text-align:center;margin:30px 0;">
              <a href="#" style="background:#0d6efd;color:#fff;padding:14px 32px;border-radius:6px;text-decoration:none;font-size:16px;font-weight:bold;">
                Access Your Account
              </a>
            </div>
            <p style="color:#555;line-height:1.6;font-size:14px;">
              If you have any questions, please contact our support team.
            </p>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="background:#f8f9fa;padding:20px 40px;text-align:center;border-top:1px solid #eee;">
            <p style="color:#999;font-size:12px;margin:0;">
              You received this email because you are a registered user.<br>
              &copy; 2024 Company Name. All rights reserved.
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;
}

require __DIR__ . '/partials/footer.php';
?>
