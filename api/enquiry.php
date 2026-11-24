<?php
// Enquiry form → email via Resend (https://resend.com/docs/api-reference/emails/send-email)
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function reply(int $code, array $data): void { http_response_code($code); echo json_encode($data); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(405, ['ok' => false, 'error' => 'method']);

$cfgFile = __DIR__ . '/config.php';
if (!is_file($cfgFile)) reply(500, ['ok' => false, 'error' => 'config']);
$cfg = require $cfgFile;
if (empty($cfg['resend_api_key']) || strpos($cfg['resend_api_key'], 'PASTE') !== false) reply(500, ['ok' => false, 'error' => 'config']);

// Same-site check: block other websites from posting to this endpoint.
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
$host = $origin ? (string)parse_url($origin, PHP_URL_HOST) : '';
$allowed = array_merge($cfg['allowed_hosts'] ?? [], [preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '')]);
if ($host === '' || !in_array(strtolower($host), array_map('strtolower', $allowed), true)) reply(403, ['ok' => false, 'error' => 'origin']);

$in = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($in)) reply(400, ['ok' => false, 'error' => 'input']);

// Spam traps: hidden field must stay empty, form must be open for at least 3 s.
if (!empty($in['website'])) reply(200, ['ok' => true]);
$elapsed = (int)($in['elapsed'] ?? 0);
if ($elapsed > 0 && $elapsed < 3000) reply(200, ['ok' => true]);

$clean = function ($v, int $max): string {
    $v = is_string($v) ? trim($v) : '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
};
$name    = $clean($in['name'] ?? '', 120);
$email   = $clean($in['email'] ?? '', 200);
$phone   = $clean($in['phone'] ?? '', 60);
$model   = $clean($in['model'] ?? '', 120);
$use     = $clean($in['use'] ?? '', 120);
$message = $clean($in['message'] ?? '', 5000);
$lang    = strtoupper($clean($in['lang'] ?? 'de', 5));
$page    = $clean($in['page'] ?? '', 60);

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($in['consent'])) reply(422, ['ok' => false, 'error' => 'fields']);
foreach ([$name, $email, $phone, $model, $use] as $one) if (preg_match('/[\r\n]/', $one)) reply(422, ['ok' => false, 'error' => 'fields']);

// Rate limit: max 5 enquiries per IP per 10 minutes.
$dir = sys_get_temp_dir() . '/ruban-enquiry';
@mkdir($dir, 0700, true);
$ipFile = $dir . '/' . hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'ruban');
$now = time();
$hits = array_filter(array_map('intval', @file($ipFile, FILE_IGNORE_NEW_LINES) ?: []), fn($t) => $t > $now - 600);
if (count($hits) >= 5) reply(429, ['ok' => false, 'error' => 'rate']);
$hits[] = $now;
@file_put_contents($ipFile, implode("\n", $hits), LOCK_EX);

$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$rows = [
    'Name' => $name, 'E-Mail' => $email, 'Telefon' => $phone ?: '–',
    'Modell' => $model ?: '–', 'Nutzung' => $use ?: '–', 'Sprache' => $lang, 'Seite' => $page ?: '–',
];
$text = '';
$htmlRows = '';
foreach ($rows as $k => $v) {
    $text .= "$k: $v\n";
    $val = $k === 'E-Mail' ? '<a href="mailto:' . $h($v) . '">' . $h($v) . '</a>'
         : ($k === 'Telefon' && $v !== '–' ? '<a href="tel:' . $h(preg_replace('/[^\d+]/', '', $v)) . '">' . $h($v) . '</a>' : $h($v));
    $htmlRows .= '<tr><td style="padding:8px 16px 8px 0;color:#6b7280;white-space:nowrap;vertical-align:top">' . $k . '</td><td style="padding:8px 0;color:#111827">' . $val . '</td></tr>';
}
$text .= "\nNachricht:\n" . ($message ?: '–');
$html = '<div style="font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.5;max-width:600px">'
      . '<h2 style="margin:0 0 16px;font-size:20px;color:#111827">Neue Anfrage über die Website</h2>'
      . '<table style="border-collapse:collapse">' . $htmlRows . '</table>'
      . '<h3 style="margin:24px 0 8px;font-size:15px;color:#111827">Nachricht</h3>'
      . '<p style="margin:0;white-space:pre-wrap;color:#111827">' . ($message !== '' ? nl2br($h($message)) : '–') . '</p>'
      . '<p style="margin:24px 0 0;font-size:13px;color:#6b7280">Antworten Sie einfach auf diese E-Mail — die Antwort geht direkt an ' . $h($email) . '.</p></div>';

$subject = 'Anfrage ' . ($model ?: 'RUBAN MARINE') . ' — ' . $name . ' [' . $lang . ']';

function send(array $cfg, string $from, array $payload): array {
    $payload['from'] = $from;
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $cfg['resend_api_key'], 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return [$code, (string)$body];
}

$payload = ['to' => [$cfg['mail_to']], 'reply_to' => $email, 'subject' => $subject, 'html' => $html, 'text' => $text];
[$code, $body] = send($cfg, $cfg['mail_from'], $payload);
// Domain not verified yet → retry with Resend's test sender.
if (($code === 403 || $code === 422) && !empty($cfg['fallback_from'])) {
    [$code, $body] = send($cfg, $cfg['fallback_from'], $payload);
}
if ($code >= 200 && $code < 300) reply(200, ['ok' => true]);
error_log('Resend error ' . $code . ': ' . $body);
reply(502, ['ok' => false, 'error' => 'send']);
