<?php
// ============================================================
// api/project-timeline.php — read-only project activity timeline
//
// Renders bac_procurement_logs (already populated by projectWorkflowLog()
// across the whole project lifecycle) scoped to a single project, newest
// first. No writes happen here — entries are never editable/deletable from
// the UI this feeds.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';
apiHeaders();

requireAnyRole(['super_admin', 'admin', 'bac', 'engineer', 'hope', 'contractor']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['error' => 'Method not allowed.'], 405);
}

$projectId = (int) ($_GET['project_id'] ?? 0);
if ($projectId <= 0) {
    respond(['error' => 'project_id is required.'], 422);
}

$db = getDB();
projectWorkflowEnsureBacTables($db);

$stmt = $db->prepare("
    SELECT l.id, l.action, l.details, l.created_at,
           COALESCE(u.full_name, 'System') AS actor_name,
           COALESCE(u.role, '') AS actor_role
    FROM bac_procurement_logs l
    LEFT JOIN users u ON u.id = l.actor_id
    WHERE l.project_id = ?
    ORDER BY l.created_at DESC
");
$stmt->execute([$projectId]);

respond(['events' => $stmt->fetchAll()]);
