<?php
// Copy this file to config.php (same folder) and fill in real values.
// config.php holds real secrets and must never be committed to git.
//
// 1) Generate a password hash:
//      php -r "echo password_hash('your-password-here', PASSWORD_BCRYPT), PHP_EOL;"
// 2) Generate a session secret:
//      php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
//
// If you don't have shell/SSH access on your hosting, run those two lines
// locally (any computer with PHP installed) and paste the results below.

return [
    'password_hash' => '$2y$10$REPLACE_WITH_A_REAL_BCRYPT_HASH',
    'session_secret' => 'REPLACE_WITH_A_RANDOM_32BYTE_HEX_SECRET',
];
