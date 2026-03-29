<?php
require_once __DIR__ . '/../includes/role_page.php';

$user = requireLogin(['engineer']);

renderRolePortal(
    $user,
    'Engineer Dashboard',
    'Protected workspace for engineering updates, field coordination, and progress review.',
    [
        ['title' => 'Assigned Projects', 'body' => 'View active technical assignments, milestones, and delivery timelines.'],
        ['title' => 'Progress Reporting', 'body' => 'Submit engineering updates, validate completion percentages, and flag delays.'],
        ['title' => 'Documentation', 'body' => 'Coordinate site notes, inspections, and technical compliance records.'],
    ]
);
