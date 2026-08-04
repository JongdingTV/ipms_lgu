<?php
// ============================================================
// citizen/api/project-timeline.php — public, read-only project
// activity timeline for the citizen portal.
//
// Reuses the same bac_procurement_logs data api/project-timeline.php
// serves to staff portals, but scoped down for a public audience:
//   - only for projects in the same publicly-visible status set as
//     citizen/api/project-details.php (no draft/endorsed/internal-review
//     stage projects)
//   - never returns individual staff names, only their role — this
//     portal doesn't name staff anywhere else on the project detail
//     page (contractor name is the only identity shown), so the
//     timeline keeps that same convention
// ============================================================
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

$user = requireLogin(['citizen']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$projectId = (int) ($_GET['project_id'] ?? 0);
if ($projectId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'project_id is required.']);
    exit;
}

$db = getDB();
projectWorkflowEnsureBacTables($db);

// Same visibility gate as citizen/api/project-details.php — a project not
// yet publicly approved has no business surfacing its internal timeline.
const CITIZEN_TIMELINE_VISIBLE_STATUSES = ['approved', 'bidding', 'awarded', 'assigned', 'active', 'delayed', 'on_hold', 'completion_inspection', 'completed', 'turnover'];

$placeholders = implode(',', array_fill(0, count(CITIZEN_TIMELINE_VISIBLE_STATUSES), '?'));
$check = $db->prepare("SELECT id FROM projects WHERE id = ? AND status IN ($placeholders)");
$check->execute(array_merge([$projectId], CITIZEN_TIMELINE_VISIBLE_STATUSES));
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Project not found.']);
    exit;
}

$stmt = $db->prepare("
    SELECT l.action, l.details, l.created_at,
           COALESCE(u.role, '') AS actor_role
    FROM bac_procurement_logs l
    LEFT JOIN users u ON u.id = l.actor_id
    WHERE l.project_id = ?
    ORDER BY l.created_at DESC
");
$stmt->execute([$projectId]);

echo json_encode(['events' => $stmt->fetchAll()]);
