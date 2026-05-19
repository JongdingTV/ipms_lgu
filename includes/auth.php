<?php
require_once __DIR__ . '/../auth/session.php';

$authenticatedUser = requireLogin();

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
        'bac' => 3,
        'admin' => 4,
        'super_admin' => 5,
    ];

    return ($roleHierarchy[$user['role']] ?? 0) >= ($roleHierarchy[$requiredRole] ?? PHP_INT_MAX);
}

function requireAnyRole(array $allowedRoles): void
{
    $user = currentUser();
    if (!$user || !in_array($user['role'], $allowedRoles, true)) {
        if (authIsApiRequest()) {
            authJsonError('Access denied', 403);
        }

        header('Location: ' . roleDashboardPath($user['role'] ?? 'citizen'));
        exit;
    }
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
