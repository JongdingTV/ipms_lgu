<?php
require_once __DIR__ . '/../auth/session.php';

requireLogin(['super_admin', 'admin']);

function hasRole(string $requiredRole): bool
{
    $user = currentUser();
    if (!$user) {
        return false;
    }

    $roleHierarchy = [
        'citizen' => 1,
        'contractor' => 2,
        'engineer' => 3,
        'admin' => 4,
        'super_admin' => 5,
    ];

    return ($roleHierarchy[$user['role']] ?? 0) >= ($roleHierarchy[$requiredRole] ?? PHP_INT_MAX);
}

function requireRole(string $requiredRole): void
{
    if (!hasRole($requiredRole)) {
        if (authIsApiRequest()) {
            authJsonError('Access denied', 403);
        }

        header('Location: ' . roleDashboardPath(currentUser()['role'] ?? 'citizen'));
        exit;
    }
}
