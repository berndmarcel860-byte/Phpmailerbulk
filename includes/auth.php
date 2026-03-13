<?php
/**
 * Authentication helpers
 */
function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function is_logged_in(): bool {
    start_session();
    return !empty($_SESSION['admin_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . base_url() . 'login.php');
        exit;
    }
}

function login_admin(int $id, string $username): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION['admin_id']   = $id;
    $_SESSION['admin_user'] = $username;
}

function logout_admin(): void {
    start_session();
    session_unset();
    session_destroy();
}

function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Walk up to the project root (two levels: admin/ and admin/something.php)
    $base = rtrim(dirname(dirname($script)), '/\\');
    return $scheme . '://' . $host . $base . '/';
}
