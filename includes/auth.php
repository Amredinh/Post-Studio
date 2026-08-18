<?php
/**
 * Authentication + CSRF helpers.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

session_name(SESSION_NAME);
session_start();

/** True when an admin is logged in. */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

/** Require login; redirect to login page otherwise. */
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/** Require AJAX login; return JSON error otherwise. */
function require_login_ajax(): void {
    if (!is_logged_in()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }
}

/** Verify the configured Zernio API key is present. */
function require_api_key(): ?string {
    $key = get_setting('zernio_api_key', '');
    if ($key === '') {
        return null;
    }
    return $key;
}

/** CSRF token get/create. */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Verify submitted CSRF token. */
function verify_csrf(): void {
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

/** Number of users in DB (0 => first-run setup). */
function user_count(): int {
    try {
        return (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    } catch (Throwable $e) {
        return -1;
    }
}