<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

$user = requireLogin(['citizen']);
$pdo = getDB();
projectWorkflowEnsureProjectStatusSchema($pdo);

// Get citizen data
$stmt = $pdo->prepare("SELECT id FROM citizens WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$citizen = $stmt->fetch();
$citizenId = $citizen['id'] ?? null;

if (!$citizenId) {
    http_response_code(404);
    echo json_encode(['error' => 'Citizen profile not found']);
    exit;
}

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
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM feedback WHERE citizen_id = ?");
$stmt->execute([$citizenId]);
$feedbackStats = $stmt->fetch();

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
$stmt = $pdo->prepare("
    SELECT f.id, f.project_id, f.message, f.category, f.priority, f.status, f.created_at,
           p.name as project_name
    FROM feedback f
    LEFT JOIN projects p ON f.project_id = p.id
    WHERE f.citizen_id = ?
    ORDER BY f.created_at DESC
    LIMIT 5
");
$stmt->execute([$citizenId]);
$recentFeedback = $stmt->fetchAll();

echo json_encode([
    'stats' => [
        'active_projects' => (int)($projectStats['active_projects'] ?? 0),
        'completed_projects' => (int)($projectStats['completed_projects'] ?? 0),
        'delayed_projects' => (int)($projectStats['delayed_projects'] ?? 0),
        'my_submissions' => (int)($feedbackStats['count'] ?? 0)
    ],
    'recent_projects' => $recentProjects,
    'recent_feedback' => $recentFeedback
]);
