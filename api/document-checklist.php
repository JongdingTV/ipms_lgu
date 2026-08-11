<?php
// ============================================================
// api/document-checklist.php — Document Checklist.
//
// Read-only view over includes/DocumentChecklist.php's live computation —
// no new document table, no new upload/review actions. Scoped for this
// pass to admin/hope/engineer/super_admin (the three portals with a real
// project-detail view today, plus super_admin for oversight parity with
// how it already reaches admin/hope/bac elsewhere in this app).
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/DocumentChecklist.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin', 'hope', 'engineer']);
requireCsrfProtection();

$db = getDB();
$user = currentUser();
$userId = (int) ($user['user_id'] ?? 0);
$role = (string) ($user['role'] ?? '');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');

if ($method === 'GET' && $action === 'get') {
    $projectId = (int) ($_GET['project_id'] ?? 0);
    if ($projectId <= 0) {
        respond(['success' => false, 'message' => 'project_id is required.'], 422);
    }

    // Engineers only see projects they're actively assigned to — same scoping
    // includes/TaskCenter.php's taskCenterForEngineer() already enforces.
    // Admin/Super Admin/HOPE keep their existing unrestricted project access.
    if ($role === 'engineer') {
        $stmt = $db->prepare("
            SELECT 1 FROM engineer_project_assignments
            WHERE project_id = ? AND engineer_id = ? AND status = 'active'
        ");
        $stmt->execute([$projectId, $userId]);
        if (!$stmt->fetchColumn()) {
            respond(['success' => false, 'message' => 'You are not assigned to this project.'], 403);
        }
    }

    $items = documentChecklistForProject($db, $projectId);
    if (!$items) {
        respond(['success' => false, 'message' => 'Project not found.'], 404);
    }

    respond([
        'success' => true,
        'items' => $items,
        'summary' => documentChecklistSummary($items),
        'missing_required' => documentChecklistMissingRequired($items),
    ]);
}

respond(['success' => false, 'message' => 'Unknown action.'], 404);
