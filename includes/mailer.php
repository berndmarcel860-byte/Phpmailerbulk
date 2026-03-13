<?php
/**
 * Bulk Mailer Engine
 *
 * Handles SMTP rotation, anti-spam delays, and email sending.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Send a single email through a given SMTP account.
 *
 * @param array  $smtp   SMTP account row from DB
 * @param string $to     Recipient email
 * @param string $toName Recipient name
 * @param string $subject Email subject
 * @param string $html   HTML body
 * @param string $text   Plain text body (optional)
 * @return array ['ok' => bool, 'error' => string]
 */
function send_single_email(array $smtp, string $to, string $toName, string $subject, string $html, string $text = ''): array {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host        = $smtp['host'];
        $mail->SMTPAuth    = true;
        $mail->Username    = $smtp['username'];
        $mail->Password    = $smtp['password'];
        $mail->SMTPSecure  = $smtp['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port        = (int) $smtp['port'];

        $mail->setFrom($smtp['from_email'], $smtp['from_name']);
        $mail->addAddress($to, $toName);
        $mail->addReplyTo($smtp['from_email'], $smtp['from_name']);

        // Anti-spam headers
        $mail->addCustomHeader('X-Mailer', 'PHP/' . PHP_VERSION);
        $mail->addCustomHeader('X-Priority', '3');
        $mail->addCustomHeader('MIME-Version', '1.0');
        $mail->MessageID = '<' . uniqid('msg', true) . '@' . parse_url('http://' . $smtp['host'], PHP_URL_HOST) . '>';

        $mail->isHTML(true);
        $mail->Subject  = $subject;
        $mail->Body     = $html;
        $mail->AltBody  = $text ?: strip_tags($html);
        $mail->CharSet  = 'UTF-8';

        $mail->send();
        return ['ok' => true, 'error' => ''];
    } catch (PHPMailerException $e) {
        return ['ok' => false, 'error' => $mail->ErrorInfo];
    } catch (\Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Run the bulk send for a campaign.
 *
 * Reads campaign settings from DB, rotates SMTP accounts, applies anti-spam
 * delays, and logs results.
 *
 * @param int $campaign_id
 * @param callable|null $progress_callback  function(int $sent, int $total, string $status)
 */
function run_campaign(int $campaign_id, ?callable $progress_callback = null): void {
    $db = get_db();

    // Load campaign
    $campaign = $db->prepare('SELECT c.*, t.subject, t.html_body, t.text_body
        FROM campaigns c
        JOIN email_templates t ON t.id = c.template_id
        WHERE c.id = ?');
    $campaign->execute([$campaign_id]);
    $campaign = $campaign->fetch();

    if (!$campaign) {
        return;
    }

    // Mark campaign as running
    $db->prepare('UPDATE campaigns SET status = ?, started_at = NOW() WHERE id = ?')
       ->execute(['running', $campaign_id]);

    // Load active SMTP accounts
    $smtps = $db->query('SELECT * FROM smtp_accounts WHERE active = 1 ORDER BY id ASC')->fetchAll();
    if (empty($smtps)) {
        $db->prepare('UPDATE campaigns SET status = ? WHERE id = ?')->execute(['failed', $campaign_id]);
        return;
    }

    // Load anti-spam rules
    $antispam_rules = $db->query('SELECT * FROM antispam_rules')->fetchAll();

    // Load leads for this campaign
    $lead_group = $campaign['lead_group'] ?? 'all';
    if ($lead_group === 'all') {
        $leads = $db->query('SELECT * FROM leads ORDER BY id ASC')->fetchAll();
    } else {
        $leads = $db->prepare('SELECT * FROM leads WHERE platform = ? ORDER BY id ASC');
        $leads->execute([$lead_group]);
        $leads = $leads->fetchAll();
    }

    $emails_per_smtp  = (int) ($campaign['emails_per_smtp'] ?? DEFAULT_EMAILS_PER_SMTP);
    $pause_seconds    = (int) ($campaign['pause_seconds']   ?? DEFAULT_PAUSE_SECONDS);
    $total            = count($leads);
    $sent             = 0;
    $smtp_index       = 0;
    $smtp_count       = count($smtps);
    $batch_count      = 0;

    foreach ($leads as $lead) {
        // Check if already sent in this campaign
        $already = $db->prepare('SELECT id FROM campaign_logs WHERE campaign_id = ? AND lead_id = ? AND status = ?');
        $already->execute([$campaign_id, $lead['id'], 'sent']);
        if ($already->fetch()) {
            continue;
        }

        $smtp = $smtps[$smtp_index % $smtp_count];

        // Render template
        $subject = render_template($campaign['subject'],  $lead);
        $html    = render_template($campaign['html_body'], $lead);
        $text    = render_template($campaign['text_body'] ?? '', $lead);

        // Apply anti-spam word filters
        $subject = apply_antispam($subject, $antispam_rules);
        $html    = apply_antispam($html,    $antispam_rules);

        $to_name = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));

        // Send email
        $result = send_single_email($smtp, $lead['email'], $to_name, $subject, $html, $text);

        // Log result
        $db->prepare('INSERT INTO campaign_logs (campaign_id, lead_id, smtp_id, status, error_message, sent_at)
            VALUES (?, ?, ?, ?, ?, NOW())')
           ->execute([
               $campaign_id,
               $lead['id'],
               $smtp['id'],
               $result['ok'] ? 'sent' : 'failed',
               $result['error'],
           ]);

        if ($result['ok']) {
            $sent++;
        }

        $batch_count++;

        // Rotate SMTP after N emails
        if ($batch_count >= $emails_per_smtp) {
            $smtp_index++;
            $batch_count = 0;
            // Pause between batches (anti-spam)
            if ($pause_seconds > 0) {
                sleep($pause_seconds);
            }
        }

        if ($progress_callback) {
            $progress_callback($sent, $total, $result['ok'] ? 'sent' : 'failed');
        }
    }

    // Mark campaign completed
    $db->prepare('UPDATE campaigns SET status = ?, completed_at = NOW() WHERE id = ?')
       ->execute(['completed', $campaign_id]);
}
