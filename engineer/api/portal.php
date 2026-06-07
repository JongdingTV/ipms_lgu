<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/scope.php';
require_once __DIR__ . '/../../includes/workflow.php';

apiHeaders();
requireAnyRole(['engineer']);
requireCsrfProtection();

$db = getDB();
$engineerId = engineerScopeCurrentId();
if ($engineerId === null) {
    respond(['error' => 'Engineer account is required.'], 403);
}

engineerScopeEnsureTables($db);
projectWorkflowEnsureProjectStatusSchema($db);
projectWorkflowEnsureRoleConnectionTables($db);
engineerScopeSeedAssignments($db, $engineerId);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? 'summary';

function engineerPortalProject(PDO $db, int $projectId, int $engineerId): ?array
{
    $stmt = $db->prepare("
        SELECT p.*, c.name AS contractor_name,
               COALESCE((SELECT SUM(amount) FROM expenses WHERE project_id = p.id), 0) AS total_spent,
               COALESCE((SELECT COUNT(*) FROM milestones WHERE project_id = p.id), 0) AS milestone_count,
               COALESCE((SELECT SUM(completed = 1) FROM milestones WHERE project_id = p.id), 0) AS completed_milestones,
               COALESCE((SELECT COUNT(*) FROM engineer_issue_reports WHERE project_id = p.id AND status NOT IN ('resolved','closed')), 0) AS open_issues
        FROM projects p
        INNER JOIN engineer_project_assignments a ON a.project_id = p.id
        LEFT JOIN contractors c ON c.id = p.contractor_id
        WHERE p.id = ?
          AND a.engineer_id = ?
          AND a.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$projectId, $engineerId]);
    $project = $stmt->fetch();

    return $project ?: null;
}

function engineerPortalProjects(PDO $db, int $engineerId): array
{
    $stmt = $db->prepare("
        SELECT p.*, c.name AS contractor_name,
               COALESCE((SELECT SUM(amount) FROM expenses WHERE project_id = p.id), 0) AS total_spent,
               COALESCE((SELECT COUNT(*) FROM milestones WHERE project_id = p.id), 0) AS milestone_count,
               COALESCE((SELECT SUM(completed = 1) FROM milestones WHERE project_id = p.id), 0) AS completed_milestones,
               COALESCE((SELECT COUNT(*) FROM engineer_issue_reports WHERE project_id = p.id AND status NOT IN ('resolved','closed')), 0) AS open_issues,
               (SELECT MAX(created_at) FROM engineer_status_updates WHERE project_id = p.id AND engineer_id = ?) AS latest_status_update
        FROM engineer_project_assignments a
        INNER JOIN projects p ON p.id = a.project_id
        LEFT JOIN contractors c ON c.id = p.contractor_id
        WHERE a.engineer_id = ?
          AND a.status = 'active'
        ORDER BY FIELD(p.status, 'delayed', 'active', 'assigned', 'awarded', 'approved', 'planning', 'on_hold', 'completed', 'cancelled'), p.updated_at DESC, p.id DESC
    ");
    $stmt->execute([$engineerId, $engineerId]);

    return $stmt->fetchAll();
}

function engineerPortalBudgetWatch(array $projects): array
{
    return array_map(static function (array $project): array {
        $budget = (float) ($project['budget'] ?? 0);
        $spent = (float) ($project['total_spent'] ?? 0);

        return [
            'project_id' => (int) $project['id'],
            'project_code' => $project['project_code'],
            'name' => $project['name'],
            'budget' => $budget,
            'spent' => $spent,
            'remaining' => max(0, $budget - $spent),
            'spent_percent' => $budget > 0 ? round(($spent / $budget) * 100, 1) : 0,
        ];
    }, $projects);
}

function engineerPortalProjectExtras(PDO $db, array $project, int $engineerId): array
{
    $projectId = (int) $project['id'];

    $milestones = $db->prepare("SELECT * FROM milestones WHERE project_id = ? ORDER BY due_date ASC, id ASC");
    $milestones->execute([$projectId]);
    $project['milestones'] = $milestones->fetchAll();

    $expenses = $db->prepare("SELECT id, category, description, amount, expense_date, flagged FROM expenses WHERE project_id = ? ORDER BY expense_date DESC, id DESC LIMIT 20");
    $expenses->execute([$projectId]);
    $project['budget_records'] = $expenses->fetchAll();

    $issues = $db->prepare("SELECT * FROM engineer_issue_reports WHERE project_id = ? AND engineer_id = ? ORDER BY created_at DESC LIMIT 10");
    $issues->execute([$projectId, $engineerId]);
    $project['issues'] = $issues->fetchAll();

    $photos = $db->prepare("SELECT * FROM engineer_progress_photos WHERE project_id = ? AND engineer_id = ? ORDER BY created_at DESC LIMIT 10");
    $photos->execute([$projectId, $engineerId]);
    $project['photos'] = $photos->fetchAll();

    $reports = $db->prepare("
        SELECT r.*, c.name AS contractor_name,
               i.id AS inspection_id,
               i.actual_progress_percent,
               i.recommendation AS inspection_recommendation,
               i.inspection_date
        FROM contractor_reports r
        INNER JOIN contractors c ON c.id = r.contractor_id
        LEFT JOIN inspections i ON i.progress_report_id = r.id
        WHERE r.project_id = ?
        ORDER BY r.report_date DESC, r.id DESC
        LIMIT 10
    ");
    $reports->execute([$projectId]);
    $project['contractor_reports'] = $reports->fetchAll();

    return $project;
}

function engineerPortalSafeFileName(string $name): string
{
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
    $name = trim((string) $name, '.-');

    return $name !== '' ? $name : 'progress-photo';
}

if ($method === 'GET') {
    $projects = engineerPortalProjects($db, $engineerId);

    if ($action === 'summary') {
        $issueCount = $db->prepare("SELECT COUNT(*) FROM engineer_issue_reports WHERE engineer_id = ? AND status NOT IN ('resolved','closed')");
        $issueCount->execute([$engineerId]);

        $photoCount = $db->prepare("SELECT COUNT(*) FROM engineer_progress_photos WHERE engineer_id = ?");
        $photoCount->execute([$engineerId]);

        $averageProgress = count($projects)
            ? (int) round(array_sum(array_map(static fn (array $p): int => (int) $p['progress'], $projects)) / count($projects))
            : 0;

        $milestones = $db->prepare("
            SELECT m.*, p.project_code, p.name AS project_name
            FROM engineer_project_assignments a
            INNER JOIN projects p ON p.id = a.project_id
            INNER JOIN milestones m ON m.project_id = p.id
            WHERE a.engineer_id = ?
              AND a.status = 'active'
            ORDER BY m.completed ASC, m.due_date ASC, m.id ASC
            LIMIT 6
        ");
        $milestones->execute([$engineerId]);

        $recentIssues = $db->prepare("
            SELECT i.*, p.project_code, p.name AS project_name
            FROM engineer_issue_reports i
            INNER JOIN projects p ON p.id = i.project_id
            WHERE i.engineer_id = ?
            ORDER BY i.created_at DESC
            LIMIT 5
        ");
        $recentIssues->execute([$engineerId]);

        $recentPhotos = $db->prepare("
            SELECT ph.*, p.project_code, p.name AS project_name
            FROM engineer_progress_photos ph
            INNER JOIN projects p ON p.id = ph.project_id
            WHERE ph.engineer_id = ?
            ORDER BY ph.created_at DESC
            LIMIT 5
        ");
        $recentPhotos->execute([$engineerId]);

        respond([
            'stats' => [
                'assigned_projects' => count($projects),
                'active_projects' => count(array_filter($projects, static fn (array $p): bool => in_array($p['status'], ['assigned', 'active', 'planning', 'approved', 'bidding', 'awarded'], true))),
                'delayed_projects' => count(array_filter($projects, static fn (array $p): bool => $p['status'] === 'delayed')),
                'average_progress' => $averageProgress,
                'open_issues' => (int) $issueCount->fetchColumn(),
                'photos_uploaded' => (int) $photoCount->fetchColumn(),
                'budget_total' => array_sum(array_map(static fn (array $p): float => (float) $p['budget'], $projects)),
                'budget_spent' => array_sum(array_map(static fn (array $p): float => (float) $p['total_spent'], $projects)),
            ],
            'recent_milestones' => $milestones->fetchAll(),
            'recent_issues' => $recentIssues->fetchAll(),
            'recent_photos' => $recentPhotos->fetchAll(),
            'budget_watch' => engineerPortalBudgetWatch($projects),
        ]);
    }

    if ($action === 'projects') {
        respond(['data' => $projects]);
    }

    if ($action === 'project') {
        $projectId = (int) ($_GET['id'] ?? 0);
        if ($projectId <= 0) {
            respond(['error' => 'Project ID is required.'], 422);
        }

        $project = engineerPortalProject($db, $projectId, $engineerId);
        if (!$project) {
            respond(['error' => 'Project not found.'], 404);
        }

        respond(['data' => engineerPortalProjectExtras($db, $project, $engineerId)]);
    }

    if ($action === 'milestones') {
        $stmt = $db->prepare("
            SELECT m.*, p.project_code, p.name AS project_name,
                   (SELECT created_at FROM engineer_milestone_updates u WHERE u.milestone_id = m.id AND u.engineer_id = ? ORDER BY u.created_at DESC LIMIT 1) AS latest_update
            FROM engineer_project_assignments a
            INNER JOIN projects p ON p.id = a.project_id
            INNER JOIN milestones m ON m.project_id = p.id
            WHERE a.engineer_id = ?
              AND a.status = 'active'
            ORDER BY m.completed ASC, m.due_date ASC, m.id ASC
        ");
        $stmt->execute([$engineerId, $engineerId]);
        respond(['data' => $stmt->fetchAll()]);
    }

    if ($action === 'photos') {
        $stmt = $db->prepare("
            SELECT ph.*, p.project_code, p.name AS project_name
            FROM engineer_progress_photos ph
            INNER JOIN projects p ON p.id = ph.project_id
            WHERE ph.engineer_id = ?
            ORDER BY ph.created_at DESC, ph.id DESC
        ");
        $stmt->execute([$engineerId]);
        respond(['data' => $stmt->fetchAll()]);
    }

    if ($action === 'delays') {
        $stmt = $db->prepare("
            SELECT d.*, p.project_code, p.name AS project_name
            FROM engineer_delay_reports d
            INNER JOIN projects p ON p.id = d.project_id
            WHERE d.engineer_id = ?
            ORDER BY d.created_at DESC, d.id DESC
        ");
        $stmt->execute([$engineerId]);
        respond(['data' => $stmt->fetchAll()]);
    }

    if ($action === 'issues') {
        $stmt = $db->prepare("
            SELECT i.*, p.project_code, p.name AS project_name
            FROM engineer_issue_reports i
            INNER JOIN projects p ON p.id = i.project_id
            WHERE i.engineer_id = ?
            ORDER BY FIELD(i.status, 'open', 'in_progress', 'resolved', 'closed'), i.created_at DESC
        ");
        $stmt->execute([$engineerId]);
        respond(['data' => $stmt->fetchAll()]);
    }

    if ($action === 'inspections') {
        $stmt = $db->prepare("
            SELECT r.id AS report_id,
                   r.project_id,
                   r.contractor_id,
                   r.report_date,
                   r.progress_percent,
                   r.accomplishments,
                   r.issues,
                   r.status AS report_status,
                   p.project_code,
                   p.name AS project_name,
                   c.name AS contractor_name,
                   i.id AS inspection_id,
                   i.inspection_date,
                   i.actual_progress_percent,
                   i.findings,
                   i.recommendation
            FROM engineer_project_assignments a
            INNER JOIN projects p ON p.id = a.project_id
            INNER JOIN contractor_reports r ON r.project_id = p.id
            INNER JOIN contractors c ON c.id = r.contractor_id
            LEFT JOIN inspections i ON i.progress_report_id = r.id
            WHERE a.engineer_id = ?
              AND a.status = 'active'
            ORDER BY r.report_date DESC, r.id DESC
        ");
        $stmt->execute([$engineerId]);
        respond(['data' => $stmt->fetchAll()]);
    }

    if ($action === 'tracker') {
        respond(['data' => $projects, 'budget_watch' => engineerPortalBudgetWatch($projects)]);
    }

    respond(['error' => 'Unknown action.'], 404);
}

if ($method === 'POST') {
    if ($action === 'milestone') {
        $body = requestBody();
        $projectId = (int) ($body['project_id'] ?? 0);
        $milestoneId = (int) ($body['milestone_id'] ?? 0);
        $completed = !empty($body['completed']) ? 1 : 0;
        $remarks = trim((string) ($body['remarks'] ?? ''));

        if ($projectId <= 0 || $milestoneId <= 0) {
            respond(['error' => 'Project and milestone are required.'], 422);
        }
        if (!engineerScopeHasAssignedProject($db, $engineerId, $projectId)) {
            respond(['error' => 'Project not found.'], 404);
        }

        $check = $db->prepare("SELECT id FROM milestones WHERE id = ? AND project_id = ?");
        $check->execute([$milestoneId, $projectId]);
        if (!$check->fetchColumn()) {
            respond(['error' => 'Milestone not found.'], 404);
        }

        $db->prepare("UPDATE milestones SET completed = ? WHERE id = ? AND project_id = ?")
            ->execute([$completed, $milestoneId, $projectId]);

        $db->prepare("
            INSERT INTO engineer_milestone_updates (project_id, milestone_id, engineer_id, completed, remarks)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$projectId, $milestoneId, $engineerId, $completed, $remarks !== '' ? $remarks : null]);

        $progress = $db->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(completed = 1), 0) AS done FROM milestones WHERE project_id = ?");
        $progress->execute([$projectId]);
        $row = $progress->fetch();
        if ($row && (int) $row['total'] > 0) {
            $newProgress = (int) round(((int) $row['done'] / (int) $row['total']) * 100);
            $newStatus = $newProgress >= 100 ? 'completed' : 'active';
            $db->prepare("UPDATE projects SET progress = GREATEST(progress, ?), status = IF(status IN ('draft','planning','approved','bidding','awarded','assigned') OR ? = 'completed', ?, status) WHERE id = ?")
                ->execute([$newProgress, $newStatus, $newStatus, $projectId]);
        }

        respond(['success' => true], 201);
    }

    if ($action === 'photo') {
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $caption = trim((string) ($_POST['caption'] ?? ''));

        if ($projectId <= 0 || $title === '' || empty($_FILES['photo'])) {
            respond(['error' => 'Project, title, and photo are required.'], 422);
        }
        if (!engineerScopeHasAssignedProject($db, $engineerId, $projectId)) {
            respond(['error' => 'Project not found.'], 404);
        }

        $file = $_FILES['photo'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            respond(['error' => 'Upload failed. Please choose a valid photo.'], 422);
        }
        if ((int) $file['size'] > 8 * 1024 * 1024) {
            respond(['error' => 'Photo size must be 8MB or smaller.'], 422);
        }

        $originalName = (string) $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp'];
        if (!in_array($extension, $allowedExtensions, true)) {
            respond(['error' => 'Allowed photos: PNG, JPG, JPEG, WEBP.'], 422);
        }

        $uploadRoot = dirname(__DIR__, 2) . '/uploads/engineer-progress/' . date('Y');
        if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0775, true) && !is_dir($uploadRoot)) {
            respond(['error' => 'Unable to prepare upload folder.'], 500);
        }

        $safeName = engineerPortalSafeFileName(pathinfo($originalName, PATHINFO_FILENAME));
        $storedName = $projectId . '-' . time() . '-' . bin2hex(random_bytes(4)) . '-' . $safeName . '.' . $extension;
        $destination = $uploadRoot . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            respond(['error' => 'Unable to save uploaded photo.'], 500);
        }

        $relativePath = 'uploads/engineer-progress/' . date('Y') . '/' . $storedName;
        $mimeType = function_exists('mime_content_type') ? mime_content_type($destination) : ($file['type'] ?? null);

        $stmt = $db->prepare("
            INSERT INTO engineer_progress_photos
                (project_id, engineer_id, title, caption, file_path, original_name, file_size, mime_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $projectId,
            $engineerId,
            $title,
            $caption !== '' ? $caption : null,
            $relativePath,
            $originalName,
            (int) $file['size'],
            $mimeType,
        ]);

        respond(['success' => true, 'id' => (int) $db->lastInsertId(), 'file_path' => $relativePath], 201);
    }

    if ($action === 'delay') {
        $body = requestBody();
        $projectId = (int) ($body['project_id'] ?? 0);
        $severity = (string) ($body['severity'] ?? 'medium');
        $impactDays = max(0, (int) ($body['impact_days'] ?? 0));
        $cause = trim((string) ($body['cause'] ?? ''));
        $mitigation = trim((string) ($body['mitigation_plan'] ?? ''));

        if ($projectId <= 0 || $cause === '') {
            respond(['error' => 'Project and delay cause are required.'], 422);
        }
        if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            $severity = 'medium';
        }
        if (!engineerScopeHasAssignedProject($db, $engineerId, $projectId)) {
            respond(['error' => 'Project not found.'], 404);
        }

        $db->prepare("
            INSERT INTO engineer_delay_reports
                (project_id, engineer_id, severity, impact_days, cause, mitigation_plan)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$projectId, $engineerId, $severity, $impactDays, $cause, $mitigation !== '' ? $mitigation : null]);

        $db->prepare("UPDATE projects SET status = 'delayed' WHERE id = ? AND status NOT IN ('completed','cancelled')")
            ->execute([$projectId]);

        respond(['success' => true, 'id' => (int) $db->lastInsertId()], 201);
    }

    if ($action === 'issue') {
        $body = requestBody();
        $projectId = (int) ($body['project_id'] ?? 0);
        $issueType = trim((string) ($body['issue_type'] ?? 'Site Issue'));
        $priority = (string) ($body['priority'] ?? 'medium');
        $description = trim((string) ($body['description'] ?? ''));
        $recommendedAction = trim((string) ($body['recommended_action'] ?? ''));

        if ($projectId <= 0 || $description === '') {
            respond(['error' => 'Project and issue description are required.'], 422);
        }
        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $priority = 'medium';
        }
        if (!engineerScopeHasAssignedProject($db, $engineerId, $projectId)) {
            respond(['error' => 'Project not found.'], 404);
        }

        $db->prepare("
            INSERT INTO engineer_issue_reports
                (project_id, engineer_id, issue_type, priority, description, recommended_action)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $projectId,
            $engineerId,
            $issueType !== '' ? $issueType : 'Site Issue',
            $priority,
            $description,
            $recommendedAction !== '' ? $recommendedAction : null,
        ]);

        respond(['success' => true, 'id' => (int) $db->lastInsertId()], 201);
    }

    if ($action === 'status') {
        $body = requestBody();
        $projectId = (int) ($body['project_id'] ?? 0);
        $progress = max(0, min(100, (int) ($body['progress_percent'] ?? 0)));
        $status = (string) ($body['status'] ?? 'active');
        $notes = trim((string) ($body['notes'] ?? ''));
        $allowedStatuses = projectWorkflowStatuses();

        if ($projectId <= 0) {
            respond(['error' => 'Project is required.'], 422);
        }
        if (!in_array($status, $allowedStatuses, true)) {
            respond(['error' => 'Invalid project status.'], 422);
        }
        if (!engineerScopeHasAssignedProject($db, $engineerId, $projectId)) {
            respond(['error' => 'Project not found.'], 404);
        }

        $db->prepare("
            INSERT INTO engineer_status_updates
                (project_id, engineer_id, progress_percent, status, notes)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$projectId, $engineerId, $progress, $status, $notes !== '' ? $notes : null]);

        $db->prepare("UPDATE projects SET progress = ?, status = ? WHERE id = ?")
            ->execute([$progress, $status, $projectId]);

        respond(['success' => true, 'id' => (int) $db->lastInsertId()], 201);
    }

    if ($action === 'inspection') {
        $body = requestBody();
        $reportId = (int) ($body['progress_report_id'] ?? 0);
        $actualProgress = max(0, min(100, (int) ($body['actual_progress_percent'] ?? 0)));
        $recommendation = (string) ($body['recommendation'] ?? 'approved');
        $findings = trim((string) ($body['findings'] ?? ''));
        $inspectionDate = trim((string) ($body['inspection_date'] ?? date('Y-m-d')));

        if ($reportId <= 0 || $findings === '') {
            respond(['error' => 'Progress report and findings are required.'], 422);
        }
        if (!in_array($recommendation, ['approved', 'needs_correction', 'for_reinspection'], true)) {
            $recommendation = 'approved';
        }

        $report = $db->prepare("
            SELECT r.*, p.id AS project_id
            FROM contractor_reports r
            INNER JOIN projects p ON p.id = r.project_id
            INNER JOIN engineer_project_assignments a ON a.project_id = p.id
            WHERE r.id = ?
              AND a.engineer_id = ?
              AND a.status = 'active'
            LIMIT 1
        ");
        $report->execute([$reportId, $engineerId]);
        $reportRow = $report->fetch();
        if (!$reportRow) {
            respond(['error' => 'Progress report not found.'], 404);
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO inspections
                    (project_id, progress_report_id, engineer_id, inspection_date, actual_progress_percent, findings, recommendation)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                (int) $reportRow['project_id'],
                $reportId,
                $engineerId,
                $inspectionDate !== '' ? $inspectionDate : date('Y-m-d'),
                $actualProgress,
                $findings,
                $recommendation,
            ]);

            $reportStatus = $recommendation === 'approved' ? 'accepted' : 'returned';
            $db->prepare("UPDATE contractor_reports SET status = ? WHERE id = ?")
                ->execute([$reportStatus, $reportId]);

            if ($recommendation === 'approved') {
                $db->prepare("UPDATE projects SET progress = GREATEST(progress, ?), status = IF(status IN ('assigned','awarded'), 'active', status) WHERE id = ?")
                    ->execute([$actualProgress, (int) $reportRow['project_id']]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            respond(['error' => 'Unable to save inspection.'], 500);
        }

        respond(['success' => true, 'id' => (int) $db->lastInsertId()], 201);
    }

    respond(['error' => 'Unknown action.'], 404);
}

respond(['error' => 'Method not allowed.'], 405);
