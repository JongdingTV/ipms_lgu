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

/**
 * Records a governance/administrative action. Writes to the legacy
 * audit_logs table (kept for continuity with any historical rows already
 * there) and — via logActivity() — to activity_logs, which is the single
 * table the Audit Trail page (superadmin/api/portal.php's list_audit_trail)
 * actually reads: it's the one with IP/user-agent, and now also carries
 * module (=$targetType) and record_id (=$targetId), so every auditLog()
 * call site gets full Audit Trail coverage without having to also call
 * logActivity() itself. Never throws — a logging failure must not block
 * the action it's recording.
 */
function auditLog(PDO $db, ?int $actorId, string $action, ?string $targetType = null, ?int $targetId = null, string $details = ''): void
{
    try {
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, details, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$actorId, $action, $targetType, $targetId, $details !== '' ? $details : null]);
    } catch (Throwable $e) {
    }

    logActivity($actorId, $action, $details, auditLogModuleLabel($targetType), $targetId);
}

// audit_logs.table_name is a raw DB table name ('users', 'supporting_documents',
// ...) — accurate for that table's own purpose, but not something an admin
// reading the Audit Trail page should have to decode. This is display-only:
// activity_logs.module gets the friendly label, audit_logs.table_name above
// keeps the literal table name it always has.
function auditLogModuleLabel(?string $targetType): string
{
    $labels = [
        'users' => 'User Management',
        'citizens' => 'User Management',
        'contractors' => 'Contractors',
        'system_settings' => 'System Settings',
        'login_attempts' => 'Login Security',
        'supporting_documents' => 'Documents',
    ];

    return $labels[$targetType ?? ''] ?? (string) ($targetType ?? '');
}
