<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

$user = requireLogin(['citizen']);
$pdo = getDB();
projectWorkflowEnsureProjectStatusSchema($pdo);

$stmt = $pdo->prepare("
    SELECT 
        p.id, p.project_code, p.name, p.description, p.location, 
        p.budget, p.start_date, p.end_date, p.progress, p.status,
        (SELECT COUNT(*) FROM milestones m WHERE m.project_id = p.id) as total_milestones,
        (SELECT COUNT(*) FROM milestones m WHERE m.project_id = p.id AND m.completed = 1) as completed_milestones,
        (SELECT COALESCE(SUM(e.amount), 0) FROM expenses e WHERE e.project_id = p.id) as total_expenses,
        (SELECT COUNT(*) FROM engineer_delay_reports d WHERE d.project_id = p.id AND d.status <> 'resolved') as delay_reports,
        (SELECT ph.file_path FROM engineer_progress_photos ph WHERE ph.project_id = p.id ORDER BY ph.created_at DESC LIMIT 1) as latest_photo_path,
        (SELECT ph.title FROM engineer_progress_photos ph WHERE ph.project_id = p.id ORDER BY ph.created_at DESC LIMIT 1) as latest_photo_title
    FROM projects p
    WHERE p.status IN ('approved','bidding','awarded','assigned','active','delayed','on_hold','completion_inspection','completed','turnover')
    ORDER BY p.created_at DESC
");
$stmt->execute();
$projects = $stmt->fetchAll();

echo json_encode(['projects' => $projects]);
