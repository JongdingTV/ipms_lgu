<?php
/**
 * CLI check for SMTP configuration: sends a real test email through the same
 * OTPManager sender used by registration, login 2FA, and password reset.
 *
 * Usage: php scripts/test-mail.php recipient@example.com
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script may only be run from the command line.');
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/OTPManager.php';

$to = $argv[1] ?? '';
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    exit("Usage: php scripts/test-mail.php recipient@example.com\n");
}

if (MAIL_PASSWORD === '') {
    exit("MAIL_PASSWORD is empty in .env — set the Gmail app password first.\n");
}

echo "Sending test email to {$to} via " . MAIL_HOST . ':' . MAIL_PORT . " as " . MAIL_USERNAME . "...\n";

$otp = new OTPManager();
$result = $otp->sendPlainEmail(
    $to,
    'IPMS mail configuration test',
    '<p>This is a test email from the IPMS system. If you are reading this, SMTP is configured correctly.</p>'
);

echo ($result['success'] ? 'SUCCESS: ' : 'FAILED: ') . $result['message'] . "\n";
if (!$result['success']) {
    echo "Check c:\\xampp\\php\\logs or Apache error log for the SMTP error detail.\n";
}
