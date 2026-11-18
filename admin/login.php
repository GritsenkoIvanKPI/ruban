<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

// Support two submission paths:
//  - fetch() from app.js sends JSON (Content-Type: application/json) — respond with JSON.
//  - a native <form method="post"> fallback (used if JS fails to load/attach) sends
//    regular form-encoded data — respond with a redirect instead, since there's no
//    JS on that path to read a JSON body.
$isJsonRequest = isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json');
$password = '';
if ($isJsonRequest) {
    $input = json_decode((string)file_get_contents('php://input'), true) ?? [];
    $password = (string)($input['password'] ?? '');
} else {
    $password = (string)($_POST['password'] ?? '');
}

function respond_error(string $error, int $code, bool $isJsonRequest): void
{
    http_response_code($code);
    if ($isJsonRequest) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $error]);
    } else {
        header('Location: index.php?login_error=' . urlencode($error));
    }
    exit;
}

// Naive brute-force throttle: after 5 failed attempts, lock out for 60s.
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
$_SESSION['login_last'] = $_SESSION['login_last'] ?? 0;
if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['login_last']) < 60) {
    respond_error('too_many_attempts', 429, $isJsonRequest);
}

if ($password !== '' && password_verify($password, $CONFIG['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION['admin_authed'] = true;
    $_SESSION['login_attempts'] = 0;
    if ($isJsonRequest) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'csrf' => csrf_token()]);
    } else {
        header('Location: index.php');
    }
} else {
    $_SESSION['login_attempts']++;
    $_SESSION['login_last'] = time();
    respond_error('invalid_password', 401, $isJsonRequest);
}
