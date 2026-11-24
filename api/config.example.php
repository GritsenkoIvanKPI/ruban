<?php
// Copy this file to config.php (same folder) and paste your Resend API key.
// config.php holds a real secret and must never be committed to git.
return [
    'resend_api_key' => 're_PASTE_YOUR_KEY_HERE',
    'mail_to'        => 'ruban6467@gmail.com',
    // Works once rubanmarine.de is verified in Resend → Domains.
    'mail_from'      => 'Ruban Marine Website <anfrage@rubanmarine.de>',
    // Used automatically while the domain is not verified yet
    // (Resend only delivers it to the email of the Resend account).
    'fallback_from'  => 'Ruban Marine Website <onboarding@resend.dev>',
    'allowed_hosts'  => ['rubanmarine.de', 'www.rubanmarine.de'],
];
