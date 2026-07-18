<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/scope.php';
require_once __DIR__ . '/../../includes/workflow.php';
require_once __DIR__ . '/../../includes/Validator.php';
require_once __DIR__ . '/../../includes/Pagination.php';
require_once __DIR__ . '/../../includes/Notifications.php';

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

const ENGINEER_PHOTO_MAX_SIZE = 8 * 1024 * 1024;
const ENGINEER_PHOTO_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

/** Same dynamic-row convention used by superadmin/bac/contractor: photos[N][title]/[caption] + photo_files[N]. */
function engineerCollectPhotoRows(array $textRows, array $filesField): array
{
    $indices = array_keys($textRows);
    foreach (array_keys($filesField['name'] ?? []) as $idx) {
        if (!in_array($idx, $indices, true)) {
            $indices[] = $idx;
        }
    }

    $rows = [];
    foreach ($indices as $idx) {
        $title = trim((string) ($textRows[$idx]['title'] ?? ''));
        $caption = trim((string) ($textRows[$idx]['caption'] ?? ''));
        $file = FileUpload::fromNestedFiles($filesField, (int) $idx);

        if ($title === '' && $file === null) {
            continue;
        }

        if ($title === '') {
            $error = 'Title is required when a photo is attached.';
        } else {
            $error = FileUpload::validate($file, [
                'required' => true,
                'max_size' => ENGINEER_PHOTO_MAX_SIZE,
                'extensions' => ENGINEER_PHOTO_EXTENSIONS,
                'sniff_pdf' => false,
            ]);
        }

        $rows[] = ['title' => $title, 'caption' => $caption, 'file' => $file, 'error' => $error];
    }

    return $rows;
}

function engineerCleanupFiles(array $relativePaths): void
{
    foreach ($relativePaths as $path) {
        $full = dirname(__DIR__, 2) . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
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
        // Optional search over code/name/location (used by the global
        // Ctrl+K search); the assigned list is small, so filter in PHP.
        $search = trim((string) ($_GET['search'] ?? ''));
        $rows = $projects;
        if ($search !== '') {
            $rows = array_values(array_filter($projects, static fn (array $p): bool =>
                mb_stripos(($p['project_code'] ?? '') . ' ' . ($p['name'] ?? '') . ' ' . ($p['location'] ?? ''), $search) !== false
            ));
        }
        respond(['data' => $rows]);
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
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 12)));
        $select = "
            SELECT ph.*, p.project_code, p.name AS project_name
            FROM engineer_progress_photos ph
            INNER JOIN projects p ON p.id = ph.project_id
            WHERE ph.engineer_id = ?
            ORDER BY ph.created_at DESC, ph.id DESC
        ";
        $count = "SELECT COUNT(*) FROM engineer_progress_photos ph WHERE ph.engineer_id = ?";
        respond(paginate($db, $select, $count, [$engineerId], $page, $perPage));
    }

    if ($action === 'delays') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));
        $select = "
            SELECT d.*, p.project_code, p.name AS project_name
            FROM engineer_delay_reports d
            INNER JOIN projects p ON p.id = d.project_id
            WHERE d.engineer_id = ?
            ORDER BY d.created_at DESC, d.id DESC
        ";
        $count = "SELECT COUNT(*) FROM engineer_delay_reports d WHERE d.engineer_id = ?";
        respond(paginate($db, $select, $count, [$engineerId], $page, $perPage));
    }

    if ($action === 'issues') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));
        $select = "
            SELECT i.*, p.project_code, p.name AS project_name
            FROM engineer_issue_reports i
            INNER JOIN projects p ON p.id = i.project_id
            WHERE i.engineer_id = ?
            ORDER BY FIELD(i.status, 'open', 'in_progress', 'resolved', 'closed'), i.created_at DESC
        ";
        $count = "SELECT COUNT(*) FROM engineer_issue_reports i WHERE i.engineer_id = ?";
        respond(paginate($db, $select, $count, [$engineerId], $page, $perPage));
    }

    if ($action === 'inspections') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));
        $select = "
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
        ";
        $count = "
            SELECT COUNT(*)
            FROM engineer_project_assignments a
            INNER JOIN projects p ON p.id = a.project_id
            INNER JOIN contractor_reports r ON r.project_id = p.id
            WHERE a.engineer_id = ?
              AND a.status = 'active'
        ";
        respond(paginate($db, $select, $count, [$engineerId], $page, $perPage));
    }

    if ($action === 'tracker') {
        respond(['data' => $projects, 'budget_watch' => engineerPortalBudgetWatch($projects)]);
    }

    /** Small, uncapped picker for the inspection form's dropdown — reports not yet inspected only, not the full history. */
    if ($action === 'pending_inspections') {
        $stmt = $db->prepare("
            SELECT r.id AS report_id, r.project_id, r.report_date, r.progress_percent,
                   p.project_code, p.name AS project_name
            FROM engineer_project_assignments a
            INNER JOIN projects p ON p.id = a.project_id
            INNER JOIN contractor_reports r ON r.project_id = p.id
            LEFT JOIN inspections i ON i.progress_report_id = r.id
            WHERE a.engineer_id = ?
              AND a.status = 'active'
              AND (i.id IS NULL OR r.status IN ('submitted', 'under_review'))
            ORDER BY r.report_date DESC, r.id DESC
            LIMIT 50
        ");
        $stmt->execute([$engineerId]);
        respond(['data' => $stmt->fetchAll()]);
    }

    respond(['error' => 'Unknown action.'], 404);
}

if ($method === 'POST') {
    if ($action === 'milestone') {
        $validated = Validator::make(requestBody(), [
            'project_id' => 'required|integer',
            'milestone_id' => 'required|integer',
            'completed' => 'nullable',
            'remarks' => 'nullable|string|max:2000',
        ])->stopOnFailure();

        $projectId = (int) $validated['project_id'];
        $milestoneId = (int) $validated['milestone_id'];
        $completed = !empty($validated['completed']) ? 1 : 0;
        $remarks = trim((string) ($validated['remarks'] ?? ''));

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

        if ($projectId <= 0) {
            respond(['error' => 'Project is required.'], 422);
        }
        if (!engineerScopeHasAssignedProject($db, $engineerId, $projectId)) {
            respond(['error' => 'Project not found.'], 404);
        }

        $photoRows = engineerCollectPhotoRows($_POST['photos'] ?? [], $_FILES['photo_files'] ?? []);
        if ($photoRows === []) {
            respond(['error' => 'At least one photo (title + file) is required.'], 422);
        }
        foreach ($photoRows as $i => $row) {
            if ($row['error'] !== null) {
                respond(['error' => 'Photo row ' . ($i + 1) . ': ' . $row['error']], 422);
            }
        }

        $storedFiles = [];
        $insertedIds = [];

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO engineer_progress_photos
                    (project_id, engineer_id, title, caption, file_path, original_name, file_size, mime_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($photoRows as $row) {
                $stored = FileUpload::store($row['file'], 'engineer-progress', [
                    'max_size' => ENGINEER_PHOTO_MAX_SIZE,
                    'extensions' => ENGINEER_PHOTO_EXTENSIONS,
                    'sniff_pdf' => false,
                ]);
                $storedFiles[] = $stored['stored_path'];

                $stmt->execute([
                    $projectId,
                    $engineerId,
                    $row['title'],
                    $row['caption'] !== '' ? $row['caption'] : null,
                    $stored['stored_path'],
                    $stored['original_name'],
                    $stored['file_size'],
                    $stored['mime_type'],
                ]);
                $insertedIds[] = (int) $db->lastInsertId();
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            engineerCleanupFiles($storedFiles);
            respond(['error' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to upload photos.'], 422);
        }

        respond(['success' => true, 'ids' => $insertedIds], 201);
    }

    if ($action === 'delay') {
        $validated = Validator::make(requestBody(), [
            'project_id' => 'required|integer',
            'severity' => 'nullable|in:low,medium,high,critical',
            'impact_days' => 'nullable|integer|min:0',
            'cause' => 'required|string|min:3',
            'mitigation_plan' => 'nullable|string|max:2000',
        ])->stopOnFailure();

        $projectId = (int) $validated['project_id'];
        $severity = (string) ($validated['severity'] ?? 'medium');
        $impactDays = max(0, (int) ($validated['impact_days'] ?? 0));
        $cause = trim((string) $validated['cause']);
        $mitigation = trim((string) ($validated['mitigation_plan'] ?? ''));

        if (!engineerScopeHasAssignedProject($db, $engineerId, $projectId)) {
            respond(['error' => 'Project not found.'], 404);
        }

        $db->prepare("
            INSERT INTO engineer_delay_reports
                (project_id, engineer_id, severity, impact_days, cause, mitigation_plan)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$projectId, $engineerId, $severity, $impactDays, $cause, $mitigation !== '' ? $mitigation : null]);
        $newDelayId = (int) $db->lastInsertId();

        $db->prepare("UPDATE projects SET status = 'delayed' WHERE id = ? AND status NOT IN ('completed','cancelled')")
            ->execute([$projectId]);

        $projectRow = $db->prepare("SELECT name, created_by FROM projects WHERE id = ?");
        $projectRow->execute([$projectId]);
        $projectInfo = $projectRow->fetch();
        if ($projectInfo && !empty($projectInfo['created_by'])) {
            notifyUser(
                (int) $projectInfo['created_by'],
                'warning',
                'Project delayed',
                $projectInfo['name'] . ' was flagged as delayed (' . $severity . ', ' . $impactDays . ' day(s) impact) — ' . $cause
            );
        }

        respond(['success' => true, 'id' => $newDelayId], 201);
    }

    if ($action === 'issue') {
        $validated = Validator::make(requestBody(), [
            'project_id' => 'required|integer',
            'issue_type' => 'nullable|string|max:80',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'description' => 'required|string|min:3',
            'recommended_action' => 'nullable|string|max:2000',
        ])->stopOnFailure();

        $projectId = (int) $validated['project_id'];
        $issueType = trim((string) ($validated['issue_type'] ?? 'Site Issue'));
        $priority = (string) ($validated['priority'] ?? 'medium');
        $description = trim((string) $validated['description']);
        $recommendedAction = trim((string) ($validated['recommended_action'] ?? ''));

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
        $allowedStatuses = projectWorkflowStatuses();
        $validated = Validator::make(requestBody(), [
            'project_id' => 'required|integer',
            'progress_percent' => 'required|integer|min:0|max:100',
            'status' => 'required|in:' . implode(',', $allowedStatuses),
            'notes' => 'nullable|string|max:2000',
        ])->stopOnFailure();

        $projectId = (int) $validated['project_id'];
        $progress = max(0, min(100, (int) $validated['progress_percent']));
        $status = (string) $validated['status'];
        $notes = trim((string) ($validated['notes'] ?? ''));

        if (!engineerScopeHasAssignedProject($db, $engineerId, $projectId)) {
            respond(['error' => 'Project not found.'], 404);
        }

        $db->prepare("
            INSERT INTO engineer_status_updates
                (project_id, engineer_id, progress_percent, status, notes)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$projectId, $engineerId, $progress, $status, $notes !== '' ? $notes : null]);
        $newStatusUpdateId = (int) $db->lastInsertId();

        $db->prepare("UPDATE projects SET progress = ?, status = ? WHERE id = ?")
            ->execute([$progress, $status, $projectId]);

        respond(['success' => true, 'id' => $newStatusUpdateId], 201);
    }

    if ($action === 'inspection') {
        $validated = Validator::make(requestBody(), [
            'progress_report_id' => 'required|integer',
            'actual_progress_percent' => 'required|integer|min:0|max:100',
            'recommendation' => 'nullable|in:approved,needs_correction,for_reinspection',
            'findings' => 'required|string|min:3',
            'inspection_date' => 'nullable|date',
        ])->stopOnFailure();

        $reportId = (int) $validated['progress_report_id'];
        $actualProgress = max(0, min(100, (int) $validated['actual_progress_percent']));
        $recommendation = (string) ($validated['recommendation'] ?? 'approved');
        $findings = trim((string) $validated['findings']);
        $inspectionDate = ($validated['inspection_date'] ?? '') !== '' ? $validated['inspection_date'] : date('Y-m-d');

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
            $newInspectionId = (int) $db->lastInsertId();

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

        respond(['success' => true, 'id' => $newInspectionId], 201);
    }

    respond(['error' => 'Unknown action.'], 404);
}

respond(['error' => 'Method not allowed.'], 405);
