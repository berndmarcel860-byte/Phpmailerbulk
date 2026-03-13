<?php
/**
 * AI Template Generation – AJAX endpoint
 *
 * POST  admin/ai_generate.php
 * Returns JSON: { ok, subject, html, error }
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_generator.php';

header('Content-Type: application/json; charset=utf-8');

// Must be logged in
if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check AI is configured
if (!ai_is_enabled()) {
    echo json_encode([
        'ok'    => false,
        'error' => 'OpenAI API key is not configured. Add OPENAI_API_KEY to your config.php.',
    ]);
    exit;
}

// Collect and sanitise parameters
$params = [
    'language' => in_array($_POST['language'] ?? '', ['de', 'en'], true) ? $_POST['language'] : 'de',
    'scenario' => in_array($_POST['scenario'] ?? '', ['initial', 'followup', 'final', 'success'], true) ? $_POST['scenario'] : 'initial',
    'tone'     => in_array($_POST['tone']     ?? '', ['formal', 'urgent', 'empathetic'], true)           ? $_POST['tone']     : 'formal',
    'company'  => substr(trim($_POST['company']  ?? 'Kryptox'), 0, 100),
    'website'  => substr(trim($_POST['website']  ?? 'https://kryptox.co.uk'), 0, 200),
    'extra'    => substr(trim($_POST['extra']    ?? ''), 0, 500),
];

$result = ai_generate_template($params);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
exit;
