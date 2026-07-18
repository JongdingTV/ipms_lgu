<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

$user = requireLogin(['citizen']);
$pdo = getDB();
projectWorkflowEnsureProjectStatusSchema($pdo);

// Get citizen data. A missing citizens row (e.g. the seeded demo account)
// only means "no personal submissions" — public project data still loads.
$stmt = $pdo->prepare("SELECT id FROM citizens WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$citizen = $stmt->fetch();
$citizenId = $citizen['id'] ?? null;

// Get statistics
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN status IN ('approved','bidding','awarded','assigned','active','on_hold') THEN 1 ELSE 0 END) as active_projects,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_projects,
        SUM(CASE WHEN status = 'delayed' THEN 1 ELSE 0 END) as delayed_projects
    FROM projects
");
$stmt->execute();
$projectStats = $stmt->fetch();

// Get citizen's feedback count
$feedbackStats = ['count' => 0];
if ($citizenId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM feedback WHERE citizen_id = ?");
    $stmt->execute([$citizenId]);
    $feedbackStats = $stmt->fetch();
}

// Get recent projects
$stmt = $pdo->prepare("
    SELECT id, project_code, name, description, location, budget, start_date, end_date, progress, status
    FROM projects
    WHERE status IN ('approved','bidding','awarded','assigned','active','delayed','on_hold','completed')
    ORDER BY created_at DESC
    LIMIT 6
");
$stmt->execute();
$recentProjects = $stmt->fetchAll();

// Get recent feedback
$recentFeedback = [];
if ($citizenId) {
    $stmt = $pdo->prepare("
        SELECT f.id, f.project_id, f.message, f.category, f.concern_type, f.priority,
               f.district, f.barangay, f.latitude, f.longitude, f.status, f.created_at,
               f.cimm_sync_status, f.cimm_reference, f.cimm_request_id,
               p.name as project_name
        FROM feedback f
        LEFT JOIN projects p ON f.project_id = p.id
        WHERE f.citizen_id = ?
        ORDER BY f.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$citizenId]);
    $recentFeedback = $stmt->fetchAll();
}

// Latest field activity across all public projects (engineer status updates,
// falling back gracefully when there are none yet). Read-only view of the
// staff side's work for the dashboard's "Latest Updates" feed.
$stmt = $pdo->prepare("
    SELECT u.progress_percent, u.status, u.notes, u.created_at,
           p.id AS project_id, p.name AS project_name
    FROM engineer_status_updates u
    INNER JOIN projects p ON p.id = u.project_id
    WHERE p.status IN ('approved','bidding','awarded','assigned','active','delayed','on_hold','completed')
    ORDER BY u.created_at DESC
    LIMIT 6
");
$stmt->execute();
$recentUpdates = $stmt->fetchAll();

// Attach proof photos in one query, grouped by feedback id
if ($recentFeedback) {
    $ids = array_column($recentFeedback, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $photoStmt = $pdo->prepare("SELECT feedback_id, photo_path FROM feedback_photos WHERE feedback_id IN ($placeholders) ORDER BY id");
    $photoStmt->execute($ids);

    $photosByFeedback = [];
    foreach ($photoStmt->fetchAll() as $row) {
        $photosByFeedback[$row['feedback_id']][] = $row['photo_path'];
    }
    foreach ($recentFeedback as &$item) {
        $item['photos'] = $photosByFeedback[$item['id']] ?? [];
    }
    unset($item);
}

echo json_encode([
    'stats' => [
        'active_projects' => (int)($projectStats['active_projects'] ?? 0),
        'completed_projects' => (int)($projectStats['completed_projects'] ?? 0),
        'delayed_projects' => (int)($projectStats['delayed_projects'] ?? 0),
        'my_submissions' => (int)($feedbackStats['count'] ?? 0)
    ],
    'recent_projects' => $recentProjects,
    'recent_feedback' => $recentFeedback,
    'recent_updates' => $recentUpdates
]);
