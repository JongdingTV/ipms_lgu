<?php
/**
 * CLI maintenance task: delete citizen accounts that never confirmed their
 * email within the grace period (auth/session.php::purgeUnverifiedCitizenAccounts).
 *
 * The same purge also runs opportunistically on every login attempt, but that
 * only fires when someone actually logs in somewhere. Schedule this script
 * (Windows Task Scheduler / cron) to run daily for timely, reliable cleanup
 * regardless of login traffic.
 *
 * Usage: php scripts/purge-unverified-citizens.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script may only be run from the command line.');
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../auth/session.php';

$deleted = purgeUnverifiedCitizenAccounts();
echo date('Y-m-d H:i:s') . " - Purged {$deleted} unverified citizen account(s) older than " . UNVERIFIED_ACCOUNT_GRACE_HOURS . "h.\n";
