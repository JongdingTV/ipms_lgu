<?php
require_once __DIR__ . '/../includes/role_page.php';

$user = requireLogin(['contractor']);

renderRolePortal(
    $user,
    'Contractor Dashboard',
    'Protected view for contractor project assignments, schedules, and required submissions.',
    [
        ['title' => 'Assigned Work', 'body' => 'Track the projects linked to your organization and current delivery status.'],
        ['title' => 'Compliance', 'body' => 'Monitor deadlines, variations, and cost entries that require follow-up.'],
        ['title' => 'Communication', 'body' => 'Stay aligned with LGU administrators and engineers on project execution.'],
    ]
);
