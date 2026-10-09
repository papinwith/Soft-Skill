<?php
// SMTP settings for sending real email (e.g. Gmail with an App Password).
// Set these as environment variables rather than editing this file directly:
//   MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION, MAIL_FROM, MAIL_FROM_NAME
// Gmail: host smtp.gmail.com, port 587, encryption tls, username = your Gmail address,
// password = a 16-character App Password (not your normal Gmail password) from
// https://myaccount.google.com/apppasswords (requires 2-Step Verification to be enabled).
$config = [
    'host'       => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
    'port'       => (int)(getenv('MAIL_PORT') ?: 587),
    'username'   => getenv('MAIL_USERNAME') ?: '',
    'password'   => getenv('MAIL_PASSWORD') ?: '',
    'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
    'from_email' => getenv('MAIL_FROM') ?: '',
    'from_name'  => getenv('MAIL_FROM_NAME') ?: 'ระบบประเมินและวิเคราะห์ Soft Skill',
];

// Local-only override file (gitignored) — see mail.local.php.example. This exists because
// on Windows/XAMPP, `setx` environment variables frequently never reach the Apache process
// the Control Panel started, so a plain PHP file is the more reliable way to set a real
// password for local development.
$localFile = __DIR__ . '/mail.local.php';
if (file_exists($localFile)) {
    $config = array_merge($config, require $localFile);
}

if ($config['from_email'] === '') {
    $config['from_email'] = $config['username'] ?: 'no-reply@localhost';
}

return $config;
