<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

$user = requireLogin(['citizen']);
$pdo = getDB();
projectWorkflowEnsureProjectStatusSchema($pdo);

// Statuses citizens are allowed to see (same list as citizen/api/projects.php)
const CITIZEN_VISIBLE_STATUSES = ['approved', 'bidding', 'awarded', 'assigned', 'active', 'delayed', 'on_hold', 'completed'];

$projectId = (int) ($_GET['id'] ?? 0);
if ($projectId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing project id']);
    exit;
}

$placeholders = implode(',', array_fill(0, count(CITIZEN_VISIBLE_STATUSES), '?'));
$stmt = $pdo->prepare("
    SELECT p.id, p.project_code, p.name, p.description, p.location, p.budget,
           p.start_date, p.end_date, p.progress, p.status, p.created_at,
           c.name AS contractor_name
    FROM projects p
    LEFT JOIN contractors c ON c.id = p.contractor_id
    WHERE p.id = ? AND p.status IN ($placeholders)
");
$stmt->execute(array_merge([$projectId], CITIZEN_VISIBLE_STATUSES));
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Project not found']);
    exit;
}

// Milestones (admin-managed plan)
$stmt = $pdo->prepare("SELECT title, due_date, completed FROM milestones WHERE project_id = ? ORDER BY due_date IS NULL, due_date, id");
$stmt->execute([$projectId]);
$milestones = $stmt->fetchAll();

// Latest field updates from the assigned engineer
$stmt = $pdo->prepare("
    SELECT progress_percent, status, notes, created_at
    FROM engineer_status_updates
    WHERE project_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$projectId]);
$updates = $stmt->fetchAll();

// Progress photos uploaded by the engineer
$stmt = $pdo->prepare("
    SELECT title, caption, file_path, created_at
    FROM engineer_progress_photos
    WHERE project_id = ?
    ORDER BY created_at DESC
    LIMIT 8
");
$stmt->execute([$projectId]);
$photos = $stmt->fetchAll();

// Public procurement notice, if any
$stmt = $pdo->prepare("
    SELECT reference_no, published_at, deadline, status
    FROM bac_bid_announcements
    WHERE project_id = ? AND status <> 'draft'
    LIMIT 1
");
$stmt->execute([$projectId]);
$bidNotice = $stmt->fetch() ?: null;

// Spending summary (same data the transparency page exposes)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE project_id = ?");
$stmt->execute([$projectId]);
$totalExpenses = (float) $stmt->fetchColumn();

echo json_encode([
    'success' => true,
    'project' => $project,
    'milestones' => $milestones,
    'updates' => $updates,
    'photos' => $photos,
    'bid_notice' => $bidNotice,
    'total_expenses' => $totalExpenses,
]);
