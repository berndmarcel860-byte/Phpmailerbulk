<?php
/**
 * AI Email Template Generator
 *
 * Calls the OpenAI Chat Completions API to generate professional
 * fund-recovery email templates, primarily in German, modelled after
 * the Kryptox style (https://kryptox.co.uk).
 */

/**
 * Returns true when the OpenAI key is configured and non-empty.
 */
function ai_is_enabled(): bool {
    return defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '';
}

/**
 * Generate an HTML email template via OpenAI.
 *
 * @param array $params {
 *   language  string  'de'|'en'            – output language
 *   scenario  string  'initial'|'followup'|'final'|'success'
 *   tone      string  'formal'|'urgent'|'empathetic'
 *   company   string  Sender company name (e.g. "Kryptox")
 *   website   string  Sender website URL  (e.g. "https://kryptox.co.uk")
 *   extra     string  Free-form additional instructions
 * }
 * @return array ['ok' => bool, 'subject' => string, 'html' => string, 'error' => string]
 */
function ai_generate_template(array $params): array {
    if (!ai_is_enabled()) {
        return ['ok' => false, 'subject' => '', 'html' => '', 'error' => 'OpenAI API key not configured.'];
    }

    $language = $params['language'] ?? 'de';
    $scenario = $params['scenario'] ?? 'initial';
    $tone     = $params['tone']     ?? 'formal';
    $company  = trim($params['company'] ?? 'Kryptox');
    $website  = trim($params['website'] ?? 'https://kryptox.co.uk');
    $extra    = trim($params['extra']   ?? '');

    $lang_label = $language === 'de' ? 'German (Deutsch)' : 'English';

    $scenario_desc = match ($scenario) {
        'followup' => 'a professional follow-up email (second contact) reminding the client their case is in progress',
        'final'    => 'a final notice email urging the client to act before the recovery window closes',
        'success'  => 'a success notification email informing the client that their funds have been recovered and are ready for withdrawal',
        default    => 'an initial outreach email making first contact with a potential client about recovering their lost cryptocurrency/funds',
    };

    $tone_desc = match ($tone) {
        'urgent'    => 'urgent and time-sensitive but still professional',
        'empathetic'=> 'empathetic and reassuring, acknowledging the client\'s loss and offering hope',
        default     => 'formal, authoritative and trust-inspiring',
    };

    $system_prompt = <<<PROMPT
You are an expert email copywriter for a professional cryptocurrency and investment fund recovery company called "{$company}" ({$website}).

The company helps victims of crypto scams, fraudulent investment platforms, and binary-options fraud recover their lost funds through legal and technical means.

Brand style:
- Dark, premium, professional look (dark navy/charcoal header with gold accent)
- Trustworthy, credible, legally-grounded language
- Clear calls-to-action
- GDPR-compliant tone

IMPORTANT RULES:
1. Write the entire email body in {$lang_label}.
2. Use ONLY inline CSS (no <style> blocks) for full email client compatibility.
3. Include a table-based layout (width 600px, centered) that renders in Gmail, Outlook, Apple Mail.
4. Use these exact placeholder variables where appropriate:
   - {{first_name}}  – recipient's first name
   - {{last_name}}   – recipient's last name
   - {{full_name}}   – recipient's full name
   - {{email}}       – recipient's email address
   - {{platform}}    – the platform/exchange they lost funds on
   - {{amount}}      – the amount lost (number only, add currency context in copy)
   - {{date}}        – relevant date
5. Output ONLY valid HTML (complete from <!DOCTYPE> to </html>).
6. After the HTML, on a new line, output: SUBJECT: followed by the email subject line in {$lang_label}.
PROMPT;

    $user_prompt = "Generate {$scenario_desc}. Tone: {$tone_desc}.";
    if ($extra) {
        $user_prompt .= " Additional instructions: {$extra}";
    }

    $model   = defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-4o-mini';
    $payload = json_encode([
        'model'       => $model,
        'temperature' => 0.7,
        'max_tokens'  => 2500,
        'messages'    => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user',   'content' => $user_prompt],
        ],
    ]);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        return ['ok' => false, 'subject' => '', 'html' => '', 'error' => 'cURL error: ' . $curl_err];
    }

    $data = json_decode($response, true);

    if ($http_code !== 200 || empty($data['choices'][0]['message']['content'])) {
        $api_err = $data['error']['message'] ?? ('HTTP ' . $http_code);
        return ['ok' => false, 'subject' => '', 'html' => '', 'error' => 'OpenAI error: ' . $api_err];
    }

    $content = trim($data['choices'][0]['message']['content']);

    // Extract subject line (last line starting with "SUBJECT:")
    $subject = '';
    $html    = $content;
    if (preg_match('/^SUBJECT:\s*(.+)$/mi', $content, $m)) {
        $subject = trim($m[1]);
        // Remove the SUBJECT line from the HTML
        $html = trim(preg_replace('/^SUBJECT:\s*.+$/mi', '', $content));
    }

    // Strip markdown code fences if the model wrapped it
    $html = preg_replace('/^```html?\s*/i', '', $html);
    $html = preg_replace('/\s*```\s*$/', '', $html);
    $html = trim($html);

    return ['ok' => true, 'subject' => $subject, 'html' => $html, 'error' => ''];
}
