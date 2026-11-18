<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

$_SESSION = [];
session_destroy();

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
