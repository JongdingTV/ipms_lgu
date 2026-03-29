<?php
require_once __DIR__ . '/../includes/role_page.php';

$user = requireLogin(['citizen']);

renderRolePortal(
    $user,
    'Citizen Portal',
    'Public-facing user area for viewing project information, updates, and feedback options.',
    [
        ['title' => 'Project Visibility', 'body' => 'Review high-level infrastructure progress and public service updates.'],
        ['title' => 'Feedback Channel', 'body' => 'Submit questions, suggestions, and complaints through an authenticated account.'],
        ['title' => 'Transparency', 'body' => 'Follow key project milestones and accountable updates published by the LGU.'],
    ]
);
