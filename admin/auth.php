<?php
declare(strict_types=1);

$__config_path = __DIR__ . '/config.php';
if (!file_exists($__config_path)) {
    http_response_code(500);
    die(
        'Admin panel is not configured yet. Copy admin/config.example.php to ' .
        'admin/config.php and fill in a password hash + session secret ' .
        '(see admin/README.md).'
    );
}
$CONFIG = require $__config_path;

session_name('ruban_admin_session');
session_set_cookie_params([
    'lifetime' => 60 * 60 * 12, // 12 hours
    'path' => '/admin/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_start();

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_authed']) && $_SESSION['admin_authed'] === true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'not_authenticated']);
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$sent)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'bad_csrf']);
        exit;
    }
}
