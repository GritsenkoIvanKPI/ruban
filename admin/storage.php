<?php
declare(strict_types=1);

define('CONTENT_PATH', dirname(__DIR__) . '/content.json');

function empty_content(): array
{
    return ['text' => ['de' => [], 'en' => [], 'ru' => [], 'ar' => []], 'images' => [], 'gallery' => []];
}

function read_content(): array
{
    if (!file_exists(CONTENT_PATH)) {
        return empty_content();
    }
    $raw = file_get_contents(CONTENT_PATH);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : empty_content();
}

function write_content(array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Failed to encode content as JSON');
    }
    $tmp = CONTENT_PATH . '.tmp.' . uniqid('', true);
    $fp = fopen($tmp, 'w');
    if (!$fp) {
        throw new RuntimeException('Could not open temp file for writing');
    }
    flock($fp, LOCK_EX);
    fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    if (!rename($tmp, CONTENT_PATH)) {
        @unlink($tmp);
        throw new RuntimeException('Could not save content.json');
    }
}
