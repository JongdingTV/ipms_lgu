<?php
// ============================================================
// api/public-facilities.php — Public Facilities Integration (Admin-only)
//
// This is NOT a core IPMS module. It is a read-only lens over IPMS's own
// `projects` table, filtered to a single barangay, standing in for the data
// IPMS would eventually synchronize to the separate "Public Facilities
// Management System" capstone project. IPMS is the source of truth; this
// endpoint only ever reads — there is no POST/PUT/DELETE handler here at
// all, so the read-only contract is enforced at the transport level, not
// just hidden in the UI.
//
// Future Ready: additional barangays can be supported later by widening
// PUBLIC_FACILITIES_BARANGAY_FILTER below into a list — nothing else in this
// file (or its caller) needs to change.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/Pagination.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respond(['error' => 'This integration is read-only.'], 405);
}

$db = getDB();
projectWorkflowEnsureProjectStatusSchema($db);

// The one thing to change to sync a different/additional barangay later.
const PUBLIC_FACILITIES_BARANGAY_FILTER = 'Culiat';

const PUBLIC_FACILITIES_VIEW_STATUSES = [
    'planned'   => ['approved', 'bidding', 'awarded', 'assigned'],
    'ongoing'   => ['active', 'delayed', 'on_hold', 'completion_inspection'],
    'completed' => ['completed', 'turnover'],
    'cancelled' => ['cancelled'],
];

/** Every query in this file starts from this same Culiat-only, read-only base. */
function publicFacilitiesBaseWhere(): array
{
    return ['p.location LIKE ?', ['%' . PUBLIC_FACILITIES_BARANGAY_FILTER . '%']];
}

/** Most recent workflow log line for a project — used as "Latest Update". */
function publicFacilitiesLatestUpdate(PDO $db, int $projectId): ?array
{
    $stmt = $db->prepare("
        SELECT action, details, created_at
        FROM bac_procurement_logs
        WHERE project_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$projectId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $view = (string) ($_GET['view'] ?? 'planned');
    if (!isset(PUBLIC_FACILITIES_VIEW_STATUSES[$view])) {
        respond(['error' => 'Unknown view.'], 422);
    }
    $statuses = PUBLIC_FACILITIES_VIEW_STATUSES[$view];

    [$locationWhere, $locationParams] = publicFacilitiesBaseWhere();
    $where = [$locationWhere, 'p.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')'];
    $params = array_merge($locationParams, $statuses);

    $search = trim((string) ($_GET['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(p.name LIKE ? OR p.project_code LIKE ? OR p.category LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }
    if (!empty($_GET['engineer'])) {
        $where[] = "EXISTS (SELECT 1 FROM engineer_project_assignments epa INNER JOIN users u ON u.id = epa.engineer_id WHERE epa.project_id = p.id AND epa.status = 'active' AND u.full_name LIKE ?)";
        $params[] = '%' . $_GET['engineer'] . '%';
    }
    if (!empty($_GET['contractor'])) {
        $where[] = 'c.name LIKE ?';
        $params[] = '%' . $_GET['contractor'] . '%';
    }
    if (!empty($_GET['year'])) {
        $where[] = 'YEAR(p.start_date) = ?';
        $params[] = (int) $_GET['year'];
    }
    if (is_numeric($_GET['min_budget'] ?? null)) {
        $where[] = 'p.budget >= ?';
        $params[] = (float) $_GET['min_budget'];
    }
    if (is_numeric($_GET['max_budget'] ?? null)) {
        $where[] = 'p.budget <= ?';
        $params[] = (float) $_GET['max_budget'];
    }

    $whereSql = implode(' AND ', $where);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 10)));

    $select = "
        SELECT p.id, p.project_code, p.name, p.category, p.location, p.budget,
               p.start_date, p.end_date, p.progress, p.status, p.description,
               p.approved_at, p.rejection_reason, p.approved_by,
               c.name AS contractor_name,
               (SELECT u.full_name FROM engineer_project_assignments epa
                INNER JOIN users u ON u.id = epa.engineer_id
                WHERE epa.project_id = p.id AND epa.status = 'active'
                ORDER BY epa.assigned_at DESC LIMIT 1) AS engineer_name,
               COALESCE((SELECT SUM(amount) FROM expenses WHERE project_id = p.id), 0) AS total_spent
        FROM projects p
        LEFT JOIN contractors c ON c.id = p.contractor_id
        WHERE $whereSql
        ORDER BY p.updated_at DESC
    ";
    $count = "SELECT COUNT(*) FROM projects p LEFT JOIN contractors c ON c.id = p.contractor_id WHERE $whereSql";

    $result = paginate($db, $select, $count, $params, $page, $perPage);

    // Per-row extras that only make sense for certain views, and that don't
    // fit cleanly into the single paginated SELECT above.
    foreach ($result['data'] as &$row) {
        $row['barangay'] = PUBLIC_FACILITIES_BARANGAY_FILTER;
        $update = publicFacilitiesLatestUpdate($db, (int) $row['id']);
        $row['latest_update'] = $update ? ($update['action'] . ($update['details'] ? ' — ' . $update['details'] : '')) : null;
        $row['latest_update_at'] = $update['created_at'] ?? null;

        if ($view === 'ongoing') {
            $milestone = $db->prepare("SELECT title, due_date FROM milestones WHERE project_id = ? AND completed = 0 ORDER BY due_date ASC LIMIT 1");
            $milestone->execute([$row['id']]);
            $row['current_milestone'] = $milestone->fetch() ?: null;

            $inspection = $db->prepare("SELECT recommendation, inspection_date FROM inspections WHERE project_id = ? ORDER BY inspection_date DESC LIMIT 1");
            $inspection->execute([$row['id']]);
            $row['inspection_status'] = $inspection->fetch() ?: null;

            $report = $db->prepare("SELECT progress_percent, accomplishments, report_date FROM contractor_reports WHERE project_id = ? ORDER BY report_date DESC LIMIT 1");
            $report->execute([$row['id']]);
            $row['latest_progress_report'] = $report->fetch() ?: null;

            $photos = $db->prepare("SELECT file_path, title, created_at FROM engineer_progress_photos WHERE project_id = ? ORDER BY created_at DESC LIMIT 3");
            $photos->execute([$row['id']]);
            $row['latest_photos'] = $photos->fetchAll();
        }

        if ($view === 'cancelled') {
            $prevStatus = null;
            if ($update && preg_match('/changed from (\w+) to \w+/', (string) $update['details'], $m)) {
                $prevStatus = $m[1];
            }
            $row['previous_status'] = $prevStatus;
            $row['cancelled_date'] = $row['approved_at'];
            $row['cancelled_by_id'] = $row['approved_by'];
        }
    }
    unset($row);

    respond($result);
}

if ($action === 'detail') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        respond(['error' => 'Project ID is required.'], 400);
    }

    [$locationWhere, $locationParams] = publicFacilitiesBaseWhere();
    $stmt = $db->prepare("
        SELECT p.*, c.name AS contractor_name, c.contact_person AS contractor_contact,
               c.performance_score, c.pcab_license_no, c.pcab_classification,
               u.full_name AS approved_by_name
        FROM projects p
        LEFT JOIN contractors c ON c.id = p.contractor_id
        LEFT JOIN users u ON u.id = p.approved_by
        WHERE p.id = ? AND $locationWhere
    ");
    $stmt->execute(array_merge([$id], $locationParams));
    $project = $stmt->fetch();
    if (!$project) {
        // Deliberately the same 404 whether the id doesn't exist or it just
        // isn't in Culiat — this integration must never confirm/deny the
        // existence of projects outside its scope.
        respond(['error' => 'Project not found in this integration.'], 404);
    }
    $project['barangay'] = PUBLIC_FACILITIES_BARANGAY_FILTER;

    $engineerStmt = $db->prepare("
        SELECT u.full_name, epa.assigned_at
        FROM engineer_project_assignments epa
        INNER JOIN users u ON u.id = epa.engineer_id
        WHERE epa.project_id = ? AND epa.status = 'active'
        ORDER BY epa.assigned_at DESC LIMIT 1
    ");
    $engineerStmt->execute([$id]);
    $project['engineer'] = $engineerStmt->fetch() ?: null;

    $milestonesStmt = $db->prepare("SELECT title, due_date, completed FROM milestones WHERE project_id = ? ORDER BY due_date ASC");
    $milestonesStmt->execute([$id]);
    $project['milestones'] = $milestonesStmt->fetchAll();

    $spentStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE project_id = ?");
    $spentStmt->execute([$id]);
    $project['total_spent'] = (float) $spentStmt->fetchColumn();

    $inspectionsStmt = $db->prepare("SELECT inspection_date, actual_progress_percent, findings, recommendation FROM inspections WHERE project_id = ? ORDER BY inspection_date DESC");
    $inspectionsStmt->execute([$id]);
    $project['inspection_history'] = $inspectionsStmt->fetchAll();

    $progressStmt = $db->prepare("SELECT report_date, progress_percent, accomplishments, issues, next_steps, status FROM contractor_reports WHERE project_id = ? ORDER BY report_date DESC");
    $progressStmt->execute([$id]);
    $project['progress_history'] = $progressStmt->fetchAll();

    $docsStmt = $db->prepare("
        SELECT document_type, title, original_name, file_path, created_at
        FROM supporting_documents
        WHERE owner_type = 'project' AND owner_id = ? AND is_current = 1
        ORDER BY created_at ASC
    ");
    $docsStmt->execute([$id]);
    $project['documents'] = $docsStmt->fetchAll();

    $photosStmt = $db->prepare("SELECT title, caption, file_path, created_at FROM engineer_progress_photos WHERE project_id = ? ORDER BY created_at ASC");
    $photosStmt->execute([$id]);
    $photos = $photosStmt->fetchAll();
    $project['photos'] = $photos;
    $project['before_photo'] = $photos[0] ?? null;
    $project['after_photo'] = $photos ? $photos[count($photos) - 1] : null;

    $feedbackStmt = $db->prepare("
        SELECT COUNT(*) AS total,
               SUM(CASE WHEN status IN ('open','in_progress') THEN 1 ELSE 0 END) AS open_count,
               SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count
        FROM feedback WHERE project_id = ?
    ");
    $feedbackStmt->execute([$id]);
    $project['feedback_summary'] = $feedbackStmt->fetch();

    $update = publicFacilitiesLatestUpdate($db, $id);
    $project['latest_update'] = $update;
    if ($update && preg_match('/changed from (\w+) to \w+/', (string) $update['details'], $m)) {
        $project['previous_status'] = $m[1];
    }

    $updatesStmt = $db->prepare("SELECT action, details, created_at FROM bac_procurement_logs WHERE project_id = ? ORDER BY created_at DESC LIMIT 20");
    $updatesStmt->execute([$id]);
    $project['update_history'] = $updatesStmt->fetchAll();

    respond($project);
}

respond(['error' => 'Unknown action.'], 404);
