<?php
/**
 * CLI maintenance task: force a reminders sweep for every active staff user
 * plus one escalation sweep, bypassing the normal ~20 minute throttle.
 *
 * This is optional — Automated Reminders already deliver themselves as a
 * side effect of the notification bell's existing 45s poll
 * (api/sidebar-badges.php), so nothing needs this script to function during
 * normal use. It exists purely so reminders can also be delivered off-hours
 * (nobody logged in) if this gets wired into Windows Task Scheduler / cron —
 * same optional pattern as scripts/purge-unverified-citizens.php.
 *
 * Usage: php scripts/send-reminders.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script may only be run from the command line.');
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/Reminders.php';

$db = getDB();
remindersEnsureSchema($db);

$staffRoles = ['engineer', 'admin', 'bac', 'contractor', 'hope', 'super_admin'];
$placeholders = implode(',', array_fill(0, count($staffRoles), '?'));
$stmt = $db->prepare("SELECT id, role FROM users WHERE role IN ($placeholders) AND status = 'active'");
$stmt->execute($staffRoles);

$totalGenerated = 0;
$usersSwept = 0;
foreach ($stmt->fetchAll() as $row) {
    $totalGenerated += remindersSweep($db, (int) $row['id'], (string) $row['role'], force: true);
    $usersSwept++;
}

$escalated = remindersEscalationSweep($db, force: true);

echo date('Y-m-d H:i:s') . " - Swept {$usersSwept} active staff user(s), generated {$totalGenerated} reminder(s), escalated {$escalated} overdue item(s).\n";
