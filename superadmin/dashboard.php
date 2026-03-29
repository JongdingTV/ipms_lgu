<?php
require_once __DIR__ . '/../includes/role_page.php';

$user = requireLogin(['super_admin']);

renderRolePortal(
    $user,
    'Super Admin Dashboard',
    'Platform-wide access for governance, security, configuration, and account administration.',
    [
        ['title' => 'Security Oversight', 'body' => 'Review authentication logs, failed logins, and system access patterns.'],
        ['title' => 'User Governance', 'body' => 'Manage role assignments, account activation, and organization-wide access.'],
        ['title' => 'System Health', 'body' => 'Track database integrity, API availability, and deployment readiness.'],
    ]
);
