<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
require __DIR__ . '/storage.php';

require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// --- Read-only state (used to hydrate the editor on load / after a save) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'state') {
    $metaPath = __DIR__ . '/field-meta.json';
    $meta = file_exists($metaPath) ? json_decode((string)file_get_contents($metaPath), true) : null;
    echo json_encode([
        'ok' => true,
        'csrf' => csrf_token(),
        'content' => read_content(),
        'fieldMeta' => $meta,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

// Everything below mutates state — require a valid CSRF token.
verify_csrf();

// --- Persist the full content document ---
if ($action === 'save_content') {
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input) || !isset($input['text'], $input['images'], $input['gallery'])) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_payload']);
        exit;
    }
    foreach (['de', 'en', 'ru', 'ar'] as $lang) {
        if (!isset($input['text'][$lang]) || !is_array($input['text'][$lang])) {
            http_response_code(400);
            echo json_encode(['error' => "missing_language_$lang"]);
            exit;
        }
    }
    if (!is_array($input['images']) || !is_array($input['gallery'])) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_images_or_gallery']);
        exit;
    }
    try {
        write_content([
            'text' => $input['text'],
            'images' => $input['images'],
            'gallery' => array_values($input['gallery']),
        ]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'save_failed']);
    }
    exit;
}

// --- Upload an image file, return its public URL (does not touch content.json) ---
if ($action === 'upload') {
    if (empty($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'no_file']);
        exit;
    }
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'upload_error']);
        exit;
    }
    $maxBytes = 10 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        http_response_code(400);
        echo json_encode(['error' => 'file_too_large']);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        http_response_code(400);
        echo json_encode(['error' => 'unsupported_type', 'mime' => $mime]);
        exit;
    }
    $ext = $allowed[$mime];

    $slot = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_POST['slot'] ?? 'upload'));
    $name = $slot . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;

    $uploadsDir = dirname(__DIR__) . '/Ruban/web/uploads';
    if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
        http_response_code(500);
        echo json_encode(['error' => 'uploads_dir_failed']);
        exit;
    }

    $dest = $uploadsDir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['error' => 'save_failed']);
        exit;
    }

    echo json_encode(['ok' => true, 'url' => 'Ruban/web/uploads/' . $name]);
    exit;
}

// --- Change the admin password (rewrites config.php) ---
if ($action === 'change_password') {
    $input = json_decode((string)file_get_contents('php://input'), true) ?? [];
    $current = (string)($input['current'] ?? '');
    $new = (string)($input['new'] ?? '');

    if (!password_verify($current, $CONFIG['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'wrong_current_password']);
        exit;
    }
    if (strlen($new) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'new_password_too_short']);
        exit;
    }

    $newHash = password_hash($new, PASSWORD_BCRYPT);
    $configPath = __DIR__ . '/config.php';
    $php = "<?php\n" .
        "// Managed by the admin panel's \"change password\" action.\n" .
        "return [\n" .
        "    'password_hash' => " . var_export($newHash, true) . ",\n" .
        "    'session_secret' => " . var_export($CONFIG['session_secret'], true) . ",\n" .
        "];\n";
    if (file_put_contents($configPath, $php, LOCK_EX) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'write_failed']);
        exit;
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'unknown_action']);
