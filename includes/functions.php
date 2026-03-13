<?php
/**
 * General helper functions
 */

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function flash(string $type, string $message): void {
    start_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    start_session();
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function render_flash(): void {
    $f = get_flash();
    if ($f) {
        $type = $f['type'] === 'error' ? 'danger' : h($f['type']);
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
        echo h($f['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

/**
 * Replace template variables with lead data.
 * Supports: {{first_name}}, {{last_name}}, {{email}}, {{platform}}, {{amount}}, {{date}}
 */
function render_template(string $template, array $lead): string {
    $vars = [
        '{{first_name}}' => $lead['first_name'] ?? '',
        '{{last_name}}'  => $lead['last_name']  ?? '',
        '{{full_name}}'  => trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? '')),
        '{{email}}'      => $lead['email']       ?? '',
        '{{platform}}'   => $lead['platform']    ?? '',
        '{{amount}}'     => $lead['amount']      ?? '',
        '{{date}}'       => $lead['date_field']  ?? '',
    ];
    return str_replace(array_keys($vars), array_values($vars), $template);
}

/**
 * Apply anti-spam word replacements to a string.
 * Returns modified string.
 */
function apply_antispam(string $text, array $rules): string {
    foreach ($rules as $rule) {
        $text = str_ireplace($rule['bad_word'], $rule['replacement'], $text);
    }
    return $text;
}

/**
 * Parse a CSV line that may be quoted.
 */
function parse_csv_file(string $filepath): array {
    $rows = [];
    if (($handle = fopen($filepath, 'r')) !== false) {
        // Auto-detect delimiter
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiters = [',', ';', "\t", '|'];
        $counts     = array_map(fn($d) => substr_count($firstLine, $d), $delimiters);
        $delimiter  = $delimiters[array_search(max($counts), $counts)];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = array_map('trim', $row);
        }
        fclose($handle);
    }
    return $rows;
}

/**
 * Detect lead columns from CSV header row.
 * Returns a mapping of column positions.
 */
function detect_lead_columns(array $header): array {
    $map = [
        'first_name' => null,
        'last_name'  => null,
        'email'      => null,
        'platform'   => null,
        'amount'     => null,
        'date_field' => null,
    ];
    foreach ($header as $idx => $col) {
        $col = strtolower(trim($col));
        if (in_array($col, ['first_name', 'firstname', 'first', 'name', 'nombre']))    $map['first_name'] = $idx;
        if (in_array($col, ['last_name', 'lastname', 'last', 'surname', 'apellido']))  $map['last_name']  = $idx;
        if (in_array($col, ['email', 'e-mail', 'correo', 'mail']))                      $map['email']      = $idx;
        if (in_array($col, ['platform', 'plataforma', 'source']))                       $map['platform']   = $idx;
        if (in_array($col, ['amount', 'cantidad', 'value', 'importe']))                 $map['amount']     = $idx;
        if (in_array($col, ['date', 'fecha', 'date_field', 'datetime']))                $map['date_field'] = $idx;
    }
    return $map;
}

function pagination(int $total, int $page, int $per_page, string $url_pattern): string {
    $pages = (int) ceil($total / $per_page);
    if ($pages <= 1) return '';
    $html = '<nav><ul class="pagination pagination-sm">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = $i === $page ? ' active' : '';
        $url    = sprintf($url_pattern, $i);
        $html  .= "<li class=\"page-item{$active}\"><a class=\"page-link\" href=\"{$url}\">{$i}</a></li>";
    }
    $html .= '</ul></nav>';
    return $html;
}
