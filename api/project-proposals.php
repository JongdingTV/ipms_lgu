<?php
// Project Proposal API. Proposals remain separate from official projects.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/Notifications.php';
require_once __DIR__ . '/../includes/RoadGeometry.php';
require_once __DIR__ . '/../includes/FileUpload.php';

apiHeaders();
$user = currentUser();
$role = (string) ($user['role'] ?? '');
requireAnyRole(['engineer', 'admin', 'super_admin', 'hope']);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    requireCsrfProtection();
}

$db = getDB();
feedbackEnsureSchema($db);
projectProposalEnsureSchema($db);
$userId = (int) ($user['user_id'] ?? $user['id'] ?? 0);

function proposalIsHeadOffice(string $role): bool
{
    return in_array($role, ['admin', 'super_admin'], true);
}

function proposalCanonicalDistrict(?string $district): ?string
{
    $value = trim((string) $district);
    if (preg_match('/^(?:district\s*)?([1-6])$/i', $value, $matches)) {
        return 'District ' . $matches[1];
    }
    return $value !== '' ? $value : null;
}

function proposalEngineerDistrict(PDO $db, int $userId): ?string
{
    $stmt = $db->prepare("SELECT district FROM users WHERE id = ? AND role = 'engineer'");
    $stmt->execute([$userId]);
    $district = $stmt->fetchColumn();
    return proposalCanonicalDistrict($district !== false ? (string) $district : null);
}

function proposalFeedbackWhere(array &$params, ?string $district, string $alias = 'f'): string
{
    $where = ["$alias.status NOT IN ('closed', 'resolved')"];
    if ($district !== null) {
        $where[] = "$alias.district = ?";
        $params[] = $district;
    }
    if (!empty($_GET['category'])) {
        $where[] = "$alias.category = ?";
        $params[] = (string) $_GET['category'];
    }
    if (!empty($_GET['priority'])) {
        $where[] = "$alias.priority = ?";
        $params[] = (string) $_GET['priority'];
    }
    if (!empty($_GET['search'])) {
        $term = '%' . trim((string) $_GET['search']) . '%';
        $where[] = "($alias.message LIKE ? OR $alias.citizen_name LIKE ? OR $alias.barangay LIKE ? OR $alias.district LIKE ?)";
        array_push($params, $term, $term, $term, $term);
    }
    return implode(' AND ', $where);
}

/** Proposals visible to Head Office are never engineer drafts. */
function proposalVisibilityWhere(string $role, int $userId, array &$params): array
{
    if ($role === 'engineer') {
        $params[] = $userId;
        return ['pp.engineer_id = ?'];
    }
    return ["pp.status <> 'draft'"];
}

function proposalListWhere(string $role, int $userId, array &$params, bool $includeRequestFilters = true): string
{
    $where = proposalVisibilityWhere($role, $userId, $params);
    if (!$includeRequestFilters) {
        return implode(' AND ', $where);
    }

    $status = trim((string) ($_GET['status'] ?? ''));
    if ($status !== '') {
        $where[] = 'pp.status = ?';
        $params[] = $status;
    }
    foreach (['category', 'infrastructure_type', 'district', 'barangay', 'priority'] as $field) {
        $value = trim((string) ($_GET[$field] ?? ''));
        if ($value !== '') {
            $where[] = "pp.$field = ?";
            $params[] = $value;
        }
    }
    $engineerId = (int) ($_GET['engineer_id'] ?? 0);
    if ($engineerId > 0 && proposalIsHeadOffice($role)) {
        $where[] = 'pp.engineer_id = ?';
        $params[] = $engineerId;
    }
    $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
    if ($dateFrom !== '') {
        $where[] = 'DATE(COALESCE(pp.submitted_at, pp.created_at)) >= ?';
        $params[] = $dateFrom;
    }
    $dateTo = trim((string) ($_GET['date_to'] ?? ''));
    if ($dateTo !== '') {
        $where[] = 'DATE(COALESCE(pp.submitted_at, pp.created_at)) <= ?';
        $params[] = $dateTo;
    }
    $search = trim((string) ($_GET['search'] ?? ''));
    if ($search !== '') {
        $term = '%' . mb_substr($search, 0, 120) . '%';
        $where[] = '(pp.proposal_code LIKE ? OR pp.title LIKE ? OR u.full_name LIKE ? OR pp.barangay LIKE ? OR pp.district LIKE ? OR pp.category LIKE ?)';
        array_push($params, $term, $term, $term, $term, $term, $term);
    }
    return implode(' AND ', $where);
}

function proposalOrderBy(string $sort): string
{
    return match ($sort) {
        'oldest' => 'COALESCE(pp.submitted_at, pp.created_at) ASC, pp.id ASC',
        'priority' => "FIELD(pp.priority, 'urgent', 'high', 'medium', 'low'), COALESCE(pp.submitted_at, pp.created_at) DESC",
        'updated' => 'pp.updated_at DESC, pp.id DESC',
        'category' => 'pp.category ASC, COALESCE(pp.submitted_at, pp.created_at) DESC',
        'engineer' => 'u.full_name ASC, COALESCE(pp.submitted_at, pp.created_at) DESC',
        default => 'COALESCE(pp.submitted_at, pp.created_at) DESC, pp.id DESC',
    };
}

function proposalResponse(PDO $db, array $row): array
{
    $row['road_geometry'] = $row['road_geometry'] ? json_decode((string) $row['road_geometry'], true) : null;
    $feedback = $db->prepare("SELECT f.id, f.citizen_name, f.message, f.category, f.infrastructure_type, f.concern_type, f.priority, f.status, f.location, f.district, f.barangay, f.latitude, f.longitude, f.created_at FROM feedback f INNER JOIN project_proposal_feedback ppf ON ppf.feedback_id = f.id WHERE ppf.proposal_id = ? ORDER BY f.created_at DESC");
    $feedback->execute([(int) $row['id']]);
    $row['feedback_basis'] = $feedback->fetchAll();

    $documents = $db->prepare("SELECT d.id, d.document_type, d.title, d.original_name, d.file_path, d.file_size, d.mime_type, d.status, d.remarks, d.reviewed_by, d.reviewed_at, d.created_at, u.full_name AS submitted_by_name, reviewer.full_name AS reviewed_by_name FROM supporting_documents d LEFT JOIN users u ON u.id = d.uploaded_by LEFT JOIN users reviewer ON reviewer.id = d.reviewed_by WHERE d.owner_type = 'proposal' AND d.owner_id = ? AND d.is_current = 1 ORDER BY d.created_at DESC, d.id DESC");
    $documents->execute([(int) $row['id']]);
    $row['supporting_documents'] = $documents->fetchAll();
    $required = ['Feasibility Study', 'Site Assessment', 'Budget Justification'];
    $byType = [];
    foreach ($row['supporting_documents'] as $document) $byType[$document['document_type']][] = $document;
    $row['document_checklist'] = array_map(static function (string $type) use ($byType): array {
        $documents = $byType[$type] ?? [];
        $status = !$documents ? 'missing' : (count(array_filter($documents, static fn(array $d): bool => $d['status'] === 'rejected')) ? 'needs_revision' : (count(array_filter($documents, static fn(array $d): bool => $d['status'] === 'verified')) ? 'complete' : 'under_review'));
        return ['document_type' => $type, 'status' => $status, 'documents' => $documents];
    }, $required);
    $history = $db->prepare("SELECT a.action, a.details, a.created_at, u.full_name AS actor_name, u.role AS actor_role FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.table_name = 'project_proposals' AND a.record_id = ? ORDER BY a.created_at DESC, a.id DESC");
    $history->execute([(int) $row['id']]);
    $row['review_history'] = $history->fetchAll();
    return $row;
}

function proposalRequiredDocumentsComplete(array $documents): bool
{
    $required = ['Feasibility Study', 'Site Assessment', 'Budget Justification'];
    foreach ($required as $type) {
        $matches = array_filter($documents, static fn(array $document): bool => $document['document_type'] === $type && $document['status'] === 'verified');
        if (!$matches) return false;
    }
    return true;
}

function proposalDocumentUpload(PDO $db, int $proposalId, int $userId): void
{
    $names = $_POST['document_titles'] ?? [];
    $types = $_POST['document_types'] ?? [];
    $files = $_FILES['proposal_documents'] ?? [];
    foreach (array_keys($files['name'] ?? []) as $index) {
        $file = FileUpload::fromNestedFiles($files, (int) $index);
        if (!$file) continue;
        $error = FileUpload::validate($file, ['required' => true, 'max_size' => 10 * 1024 * 1024, 'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg']]);
        if ($error) throw new FileUploadException($error);
        $stored = FileUpload::store($file, 'supporting-documents/proposal', ['max_size' => 10 * 1024 * 1024, 'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg']]);
        $db->prepare("INSERT INTO supporting_documents (owner_type, owner_id, document_type, title, original_name, file_path, file_size, mime_type, uploaded_by, status) VALUES ('proposal', ?, ?, ?, ?, ?, ?, ?, ?, 'pending')")->execute([$proposalId, $types[$index] ?? 'Other', trim((string) ($names[$index] ?? $file['name'])), $stored['original_name'], $stored['stored_path'], $stored['file_size'], $stored['mime_type'], $userId]);
    }
}

function proposalFilterOptions(PDO $db, string $role, int $userId): array
{
    $params = [];
    $where = proposalListWhere($role, $userId, $params, false);
    $sql = " WHERE $where";
    $values = static function (string $field) use ($db, $sql, $params): array {
        $stmt = $db->prepare("SELECT DISTINCT pp.$field AS value FROM project_proposals pp INNER JOIN users u ON u.id = pp.engineer_id $sql AND pp.$field IS NOT NULL AND pp.$field <> '' ORDER BY value ASC");
        $stmt->execute($params);
        return array_values(array_filter(array_map(static fn($row) => $row['value'] ?? null, $stmt->fetchAll())));
    };
    $engineers = $db->prepare("SELECT DISTINCT u.id, u.full_name FROM project_proposals pp INNER JOIN users u ON u.id = pp.engineer_id $sql ORDER BY u.full_name ASC");
    $engineers->execute($params);

    return [
        'categories' => $values('category'),
        'infrastructure_types' => $values('infrastructure_type'),
        'districts' => $values('district'),
        'barangays' => $values('barangay'),
        'engineers' => $engineers->fetchAll(),
    ];
}

function proposalNotifyAdmins(PDO $db, int $proposalId, string $engineerName, string $title, bool $resubmitted = false): void
{
    $admins = $db->query("SELECT id FROM users WHERE role IN ('admin', 'super_admin') AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    $notificationTitle = $resubmitted ? 'Project Proposal Resubmitted' : 'New Project Proposal';
    $message = $engineerName . ($resubmitted ? ' resubmitted ' : ' submitted ') . 'the Project Proposal “' . $title . '”.';
    $link = appUrl('/admin/dashboard.php?proposal_id=' . $proposalId);
    foreach ($admins as $adminId) {
        notifyUser((int) $adminId, 'info', $notificationTitle, $message, $link);
    }
}

if ($method === 'GET') {
    if (($_GET['resource'] ?? '') === 'ongoing_projects') {
        $statuses = ['approved', 'bidding', 'awarded', 'assigned', 'active', 'delayed', 'on_hold', 'completion_inspection'];
        $stmt = $db->query("SELECT p.id, p.project_code, p.name, p.location, p.status, p.progress, p.budget, p.latitude, p.longitude FROM projects p WHERE p.status IN ('" . implode("','", $statuses) . "') AND p.latitude IS NOT NULL AND p.longitude IS NOT NULL AND p.latitude BETWEEN 14.55 AND 14.82 AND p.longitude BETWEEN 120.96 AND 121.16 ORDER BY p.updated_at DESC, p.id DESC");
        respond(['data' => $stmt->fetchAll()]);
    }

    if (($_GET['resource'] ?? '') === 'feedback') {
        $district = $role === 'engineer' ? proposalEngineerDistrict($db, $userId) : null;
        $params = [];
        $where = proposalFeedbackWhere($params, $district);
        if (!empty($_GET['id'])) {
            $where .= ' AND f.id = ?';
            $params[] = (int) $_GET['id'];
        }
        $limit = min(10, max(1, (int) ($_GET['limit'] ?? 8)));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $countStmt = $db->prepare("SELECT COUNT(*) FROM feedback f WHERE $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $lastPage = max(1, (int) ceil($total / $limit));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $limit;
        $stmt = $db->prepare("SELECT f.id, f.citizen_name, f.message, f.category, f.infrastructure_type, f.concern_type, f.priority, f.status, f.location, f.district, f.barangay, f.latitude, f.longitude, f.created_at FROM feedback f WHERE $where ORDER BY FIELD(f.priority, 'urgent', 'high', 'medium', 'low'), f.created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        respond(['data' => $stmt->fetchAll(), 'district' => $district, 'page' => $page, 'last_page' => $lastPage, 'total' => $total]);
    }

    $params = [];
    $where = proposalListWhere($role, $userId, $params);
    $select = 'SELECT pp.*, u.full_name AS engineer_name, u.username AS engineer_username, u.email AS engineer_email, reviewer.full_name AS reviewer_name FROM project_proposals pp INNER JOIN users u ON u.id = pp.engineer_id LEFT JOIN users reviewer ON reviewer.id = pp.reviewed_by';
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id > 0) {
        $detailParams = $params;
        $detailParams[] = $id;
        $stmt = $db->prepare("$select WHERE $where AND pp.id = ? LIMIT 1");
        $stmt->execute($detailParams);
        $row = $stmt->fetch();
        if (!$row) {
            respond(['error' => 'Proposal not found'], 404);
        }
        if (proposalIsHeadOffice($role) || $role === 'hope') {
            auditLog($db, $userId, 'project_proposal_viewed', 'project_proposals', (int) $row['id'], 'Viewed ' . $row['proposal_code'] . '.');
        }
        respond(['data' => proposalResponse($db, $row)]);
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM project_proposals pp INNER JOIN users u ON u.id = pp.engineer_id WHERE $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $limit = min(50, max(1, (int) ($_GET['per_page'] ?? 10)));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $lastPage = max(1, (int) ceil($total / $limit));
    $page = min($page, $lastPage);
    $offset = ($page - 1) * $limit;
    $order = proposalOrderBy((string) ($_GET['sort'] ?? 'newest'));
    $stmt = $db->prepare("$select WHERE $where ORDER BY $order LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $feedbackCount = $db->prepare('SELECT COUNT(*) FROM project_proposal_feedback WHERE proposal_id = ?');
        $feedbackCount->execute([(int) $row['id']]);
        $row['feedback_count'] = (int) $feedbackCount->fetchColumn();
    }
    unset($row);

    $stats = null;
    $filters = null;
    if (proposalIsHeadOffice($role)) {
        $summaryParams = [];
        $summaryWhere = proposalListWhere($role, $userId, $summaryParams, false);
        $summary = $db->prepare("SELECT COUNT(*) AS total, SUM(pp.status = 'submitted') AS pending_review, SUM(pp.status = 'under_review') AS under_review, SUM(pp.status = 'returned') AS returned_count FROM project_proposals pp WHERE $summaryWhere");
        $summary->execute($summaryParams);
        $stats = array_map('intval', $summary->fetch() ?: []);
        $filters = proposalFilterOptions($db, $role, $userId);
    }

    respond(['data' => $rows, 'page' => $page, 'per_page' => $limit, 'last_page' => $lastPage, 'total' => $total, 'stats' => $stats, 'filters' => $filters]);
}

if ($method === 'POST' || $method === 'PUT') {
    $body = $_POST !== [] ? $_POST : requestBody();
    $action = (string) ($body['action'] ?? $_GET['action'] ?? 'save_draft');

    if (in_array($action, ['set_status', 'review_action'], true)) {
        if (!proposalIsHeadOffice($role)) {
            respond(['error' => 'Only Head Office can update a proposal review status.'], 403);
        }
        $proposalId = (int) ($body['id'] ?? $_GET['id'] ?? 0);
        $reviewAction = (string) ($body['review_action'] ?? $body['status'] ?? '');
        $reviewNotes = trim((string) ($body['review_notes'] ?? $body['return_notes'] ?? ''));
        $nextStatus = ['start_review' => 'under_review', 'return_revision' => 'returned', 'verify' => 'verified_by_head_office', 'send_mayor' => 'for_mayor_validation'][$reviewAction] ?? $reviewAction;
        if ($proposalId <= 0 || !in_array($nextStatus, ['under_review', 'returned', 'verified_by_head_office', 'for_mayor_validation'], true)) {
            respond(['error' => 'Invalid proposal review status.'], 422);
        }
        if (in_array($nextStatus, ['returned', 'verified_by_head_office'], true) && $reviewNotes === '') {
            respond(['error' => 'A review note is required for this action.'], 422);
        }
        $find = $db->prepare("SELECT pp.*, u.full_name AS engineer_name FROM project_proposals pp INNER JOIN users u ON u.id = pp.engineer_id WHERE pp.id = ?");
        $find->execute([$proposalId]);
        $proposal = $find->fetch();
        if (!$proposal) {
            respond(['error' => 'Proposal not found.'], 404);
        }
        $allowed = ['under_review' => ['submitted', 'mayor_returned'], 'returned' => ['submitted', 'under_review', 'mayor_returned'], 'verified_by_head_office' => ['under_review'], 'for_mayor_validation' => ['verified_by_head_office']];
        if (!in_array($proposal['status'], $allowed[$nextStatus], true)) respond(['error' => 'This proposal is not at the required stage for that action.'], 409);
        $documents = proposalResponse($db, $proposal)['supporting_documents'];
        if ($nextStatus === 'verified_by_head_office' && !proposalRequiredDocumentsComplete($documents)) respond(['error' => 'All required supporting documents must be verified before Head Office verification.'], 422);
        if ($nextStatus === 'for_mayor_validation' && !proposalRequiredDocumentsComplete($documents)) respond(['error' => 'Verify all required supporting documents before sending this proposal to the Mayor.'], 422);
        $db->prepare("UPDATE project_proposals SET status = ?, reviewed_by = ?, reviewed_at = NOW(), return_notes = ?, head_office_review_notes = ?, head_office_verified_by = CASE WHEN ? = 'verified_by_head_office' THEN ? ELSE head_office_verified_by END, head_office_verified_at = CASE WHEN ? = 'verified_by_head_office' THEN NOW() ELSE head_office_verified_at END, updated_at = NOW() WHERE id = ?")
            ->execute([$nextStatus, $userId, $nextStatus === 'returned' ? $reviewNotes : null, $reviewNotes ?: null, $nextStatus, $userId, $nextStatus, $proposalId]);
        $actionName = ['under_review' => 'project_proposal_review_started', 'returned' => 'project_proposal_returned', 'verified_by_head_office' => 'project_proposal_head_office_verified', 'for_mayor_validation' => 'project_proposal_sent_to_mayor'][$nextStatus];
        $details = $proposal['proposal_code'] . ' moved to ' . $nextStatus . ($reviewNotes !== '' ? ': ' . $reviewNotes : '.');
        auditLog($db, $userId, $actionName, 'project_proposals', $proposalId, $details);
        if (in_array($nextStatus, ['returned', 'under_review'], true)) notifyUser((int) $proposal['engineer_id'], $nextStatus === 'returned' ? 'warning' : 'info', $nextStatus === 'returned' ? 'Project Proposal Returned' : 'Project Proposal Under Review', $nextStatus === 'returned' ? 'Your proposal “' . $proposal['title'] . '” was returned with notes from Head Office.' : 'Head Office has started reviewing your proposal “' . $proposal['title'] . '”.', appUrl('/engineer/dashboard.php'));
        if ($nextStatus === 'for_mayor_validation') {
            $mayors = $db->query("SELECT id FROM users WHERE role = 'hope' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($mayors as $mayorId) notifyUser((int) $mayorId, 'info', 'Project Proposal Ready for Validation', $proposal['proposal_code'] . ' is ready for your project-need validation.', appUrl('/hope/dashboard.php'));
        }
        respond(['success' => true, 'status' => $nextStatus]);
    }

    if ($action === 'verify_document') {
        if (!proposalIsHeadOffice($role)) respond(['error' => 'Only Head Office can verify proposal documents.'], 403);
        $documentId = (int) ($body['document_id'] ?? 0);
        $documentStatus = (string) ($body['document_status'] ?? '');
        $remarks = trim((string) ($body['remarks'] ?? ''));
        if (!in_array($documentStatus, ['verified', 'rejected'], true) || $documentId <= 0) respond(['error' => 'Invalid document review.'], 422);
        $stmt = $db->prepare("UPDATE supporting_documents d INNER JOIN project_proposals pp ON pp.id = d.owner_id AND d.owner_type = 'proposal' SET d.status = ?, d.remarks = ?, d.reviewed_by = ?, d.reviewed_at = NOW() WHERE d.id = ? AND pp.status IN ('submitted','under_review','mayor_returned')");
        $stmt->execute([$documentStatus, $remarks ?: null, $userId, $documentId]);
        if ($stmt->rowCount() !== 1) respond(['error' => 'Proposal document not found.'], 404);
        auditLog($db, $userId, 'project_proposal_document_checked', 'project_proposals', $documentId, 'Document ' . $documentStatus . ($remarks !== '' ? ': ' . $remarks : '.'));
        respond(['success' => true, 'status' => $documentStatus]);
    }

    if ($action === 'mayor_decision') {
        if ($role !== 'hope') respond(['error' => 'Only the Mayor can validate project need.'], 403);
        $proposalId = (int) ($body['id'] ?? 0);
        $decision = (string) ($body['decision'] ?? '');
        $notes = trim((string) ($body['notes'] ?? ''));
        if (!in_array($decision, ['validate', 'return'], true) || $notes === '') respond(['error' => 'A validation or return comment is required.'], 422);
        $find = $db->prepare('SELECT * FROM project_proposals WHERE id = ? AND status = \'for_mayor_validation\'');
        $find->execute([$proposalId]);
        $proposal = $find->fetch();
        if (!$proposal) respond(['error' => 'Only proposals sent for Mayor validation can be decided.'], 409);
        $nextStatus = $decision === 'validate' ? 'mayor_validated' : 'mayor_returned';
        $db->prepare('UPDATE project_proposals SET status = ?, mayor_validated_by = ?, mayor_validated_at = NOW(), mayor_validation_notes = ?, updated_at = NOW() WHERE id = ? AND status = \'for_mayor_validation\'')->execute([$nextStatus, $userId, $notes, $proposalId]);
        auditLog($db, $userId, $decision === 'validate' ? 'project_proposal_mayor_validated' : 'project_proposal_mayor_returned', 'project_proposals', $proposalId, $proposal['proposal_code'] . ': ' . $notes);
        notifyUser((int) $proposal['reviewed_by'], $decision === 'validate' ? 'success' : 'warning', $decision === 'validate' ? 'Project Proposal Validated' : 'Project Proposal Returned by Mayor', $proposal['proposal_code'] . ' was ' . ($decision === 'validate' ? 'validated for the next stage.' : 'returned with Mayor comments.'), appUrl('/admin/dashboard.php?proposal_id=' . $proposalId));
        notifyUser((int) $proposal['engineer_id'], $decision === 'validate' ? 'success' : 'warning', $decision === 'validate' ? 'Project Proposal Validated' : 'Project Proposal Returned by Mayor', $proposal['proposal_code'] . ' received a Mayor decision: ' . $notes, appUrl('/engineer/dashboard.php'));
        respond(['success' => true, 'status' => $nextStatus]);
    }

    if ($action === 'upload_document') {
        if ($role !== 'engineer') respond(['error' => 'Only Engineers can upload proposal documents.'], 403);
        $proposalId = (int) ($body['id'] ?? 0);
        $stmt = $db->prepare("SELECT status FROM project_proposals WHERE id = ? AND engineer_id = ?");
        $stmt->execute([$proposalId, $userId]);
        $proposalStatus = $stmt->fetchColumn();
        if (!in_array($proposalStatus, ['draft', 'returned'], true)) respond(['error' => 'Documents can only be added to a draft or returned proposal.'], 409);
        try {
            proposalDocumentUpload($db, $proposalId, $userId);
        } catch (Throwable $e) {
            respond(['error' => $e->getMessage() ?: 'Unable to upload proposal document.'], 422);
        }
        auditLog($db, $userId, 'project_proposal_document_submitted', 'project_proposals', $proposalId, 'Supporting document uploaded.');
        respond(['success' => true]);
    }

    if ($role !== 'engineer') {
        respond(['error' => 'Only Engineers can create or edit project proposals.'], 403);
    }
    if (!in_array($action, ['save_draft', 'submit'], true)) {
        respond(['error' => 'Invalid proposal action.'], 422);
    }

    $id = (int) ($body['id'] ?? $_GET['id'] ?? 0);
    $title = trim((string) ($body['title'] ?? ''));
    $category = trim((string) ($body['category'] ?? ''));
    $infrastructureType = trim((string) ($body['infrastructure_type'] ?? ''));
    $description = trim((string) ($body['description'] ?? ''));
    $justification = trim((string) ($body['justification'] ?? ''));
    $observedProblem = trim((string) ($body['observed_problem'] ?? ''));
    $proposedSolution = trim((string) ($body['proposed_solution'] ?? ''));
    $implementingOffice = trim((string) ($body['implementing_office'] ?? ''));
    $physicalTarget = trim((string) ($body['physical_target'] ?? ''));
    $fundingSource = trim((string) ($body['funding_source'] ?? ''));
    $budgetEstimate = ($body['budget_estimate'] ?? '') !== '' ? (float) $body['budget_estimate'] : null;
    $targetStartDate = trim((string) ($body['target_start_date'] ?? '')) ?: null;
    $targetEndDate = trim((string) ($body['target_end_date'] ?? '')) ?: null;
    $supportingInformation = trim((string) ($body['supporting_information'] ?? ''));
    $location = trim((string) ($body['location'] ?? ''));
    $district = proposalCanonicalDistrict((string) ($body['district'] ?? '')) ?? '';
    $barangay = trim((string) ($body['barangay'] ?? ''));
    $latitude = ($body['latitude'] ?? '') !== '' ? (float) $body['latitude'] : null;
    $longitude = ($body['longitude'] ?? '') !== '' ? (float) $body['longitude'] : null;
    $roadGeometry = null;
    $priority = trim((string) ($body['priority'] ?? 'medium'));
    $feedbackIds = array_values(array_unique(array_filter(array_map('intval', (array) ($body['feedback_ids'] ?? [])))));

    if ($title === '' || $category === '' || $description === '' || $justification === '' || $location === '' || $district === '' || $barangay === '') {
        respond(['error' => 'Title, category, description, justification, location, district, and barangay are required.'], 422);
    }
    if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
        respond(['error' => 'Invalid priority.'], 422);
    }
    if ($budgetEstimate !== null && $budgetEstimate < 0) {
        respond(['error' => 'Budget estimate cannot be negative.'], 422);
    }
    if ($targetStartDate !== null && $targetEndDate !== null && $targetEndDate < $targetStartDate) {
        respond(['error' => 'Target end date cannot be before the target start date.'], 422);
    }
    if (!preg_match('/^District [1-6]$/', $district)) {
        respond(['error' => 'District must be a number from 1 to 6.'], 422);
    }
    if (($latitude !== null && ($latitude < -90 || $latitude > 90)) || ($longitude !== null && ($longitude < -180 || $longitude > 180))) {
        respond(['error' => 'Invalid map coordinates.'], 422);
    }
    if ($category === 'Roads and Bridges') {
        $roadGeometryError = null;
        $roadGeometry = projectValidateRoadGeometry((string) ($body['road_geometry'] ?? ''), $roadGeometryError);
        if ($roadGeometry === null) {
            respond(['error' => $roadGeometryError ?: 'Road Geometry is required for Roads and Bridges proposals.'], 422);
        }
    }

    $engineerDistrict = proposalEngineerDistrict($db, $userId);
    if ($engineerDistrict !== null && $district !== $engineerDistrict) {
        respond(['error' => 'Proposal district must match your assigned district.'], 422);
    }

    $existing = null;
    if ($id > 0) {
        $stmt = $db->prepare('SELECT * FROM project_proposals WHERE id = ? AND engineer_id = ?');
        $stmt->execute([$id, $userId]);
        $existing = $stmt->fetch();
        if (!$existing) {
            respond(['error' => 'Proposal not found'], 404);
        }
        if (!in_array($existing['status'], ['draft', 'returned'], true)) {
            respond(['error' => 'Only draft or returned proposals can be edited.'], 409);
        }
    }

    $status = $action === 'submit' ? 'submitted' : 'draft';
    $wasReturned = $existing && $existing['status'] === 'returned';
    $db->beginTransaction();
    try {
        if ($existing) {
            $db->prepare("UPDATE project_proposals SET title = ?, category = ?, infrastructure_type = ?, description = ?, justification = ?, observed_problem = ?, proposed_solution = ?, implementing_office = ?, physical_target = ?, funding_source = ?, budget_estimate = ?, target_start_date = ?, target_end_date = ?, supporting_information = ?, location = ?, district = ?, barangay = ?, latitude = ?, longitude = ?, road_geometry = ?, priority = ?, status = ?, submitted_at = CASE WHEN ? = 'submitted' THEN CASE WHEN status = 'returned' THEN NOW() ELSE COALESCE(submitted_at, NOW()) END ELSE NULL END, return_notes = CASE WHEN ? = 'submitted' THEN NULL ELSE return_notes END, updated_at = NOW() WHERE id = ? AND engineer_id = ?")
                ->execute([$title, $category, $infrastructureType ?: null, $description, $justification, $observedProblem ?: null, $proposedSolution ?: null, $implementingOffice ?: null, $physicalTarget ?: null, $fundingSource ?: null, $budgetEstimate, $targetStartDate, $targetEndDate, $supportingInformation ?: null, $location, $district, $barangay, $latitude, $longitude, $roadGeometry ? json_encode($roadGeometry, JSON_UNESCAPED_UNICODE) : null, $priority, $status, $status, $status, $id, $userId]);
        } else {
            $db->prepare("INSERT INTO project_proposals (proposal_code, title, category, infrastructure_type, description, justification, observed_problem, proposed_solution, implementing_office, physical_target, funding_source, budget_estimate, target_start_date, target_end_date, supporting_information, location, district, barangay, latitude, longitude, road_geometry, priority, engineer_id, status, submitted_at) VALUES ('PENDING', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? = 'submitted' THEN NOW() ELSE NULL END)")
                ->execute([$title, $category, $infrastructureType ?: null, $description, $justification, $observedProblem ?: null, $proposedSolution ?: null, $implementingOffice ?: null, $physicalTarget ?: null, $fundingSource ?: null, $budgetEstimate, $targetStartDate, $targetEndDate, $supportingInformation ?: null, $location, $district, $barangay, $latitude, $longitude, $roadGeometry ? json_encode($roadGeometry, JSON_UNESCAPED_UNICODE) : null, $priority, $userId, $status, $status]);
            $id = (int) $db->lastInsertId();
            $code = 'PP-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            $db->prepare('UPDATE project_proposals SET proposal_code = ? WHERE id = ?')->execute([$code, $id]);
        }

        $db->prepare('DELETE FROM project_proposal_feedback WHERE proposal_id = ?')->execute([$id]);
        if ($feedbackIds) {
            $feedbackStmt = $db->prepare("SELECT id FROM feedback WHERE id IN (" . implode(',', array_fill(0, count($feedbackIds), '?')) . ") AND status NOT IN ('closed', 'resolved')" . ($engineerDistrict !== null ? ' AND district = ?' : ''));
            $feedbackParams = $feedbackIds;
            if ($engineerDistrict !== null) {
                $feedbackParams[] = $engineerDistrict;
            }
            $feedbackStmt->execute($feedbackParams);
            $allowedFeedbackIds = array_map('intval', $feedbackStmt->fetchAll(PDO::FETCH_COLUMN));
            $link = $db->prepare('INSERT INTO project_proposal_feedback (proposal_id, feedback_id) VALUES (?, ?)');
            foreach ($allowedFeedbackIds as $feedbackId) {
                $link->execute([$id, $feedbackId]);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        respond(['error' => 'Unable to save project proposal.'], 500);
    }

    if (!empty($_FILES['proposal_documents']['name'] ?? [])) {
        try {
            proposalDocumentUpload($db, $id, $userId);
        } catch (Throwable $e) {
            respond(['error' => $e->getMessage() ?: 'Unable to upload proposal document.'], 422);
        }
        auditLog($db, $userId, 'project_proposal_document_submitted', 'project_proposals', $id, 'Supporting document uploaded with proposal submission.');
    }

    $proposalCode = $existing['proposal_code'] ?? ('PP-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT));
    auditLog($db, $userId, $action === 'submit' ? 'project_proposal_submitted' : 'project_proposal_draft_saved', 'project_proposals', $id, ($action === 'submit' ? 'Submitted ' : 'Saved draft ') . $proposalCode . '.');
    if ($action === 'submit') {
        $nameStmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
        $nameStmt->execute([$userId]);
        proposalNotifyAdmins($db, $id, (string) ($nameStmt->fetchColumn() ?: 'An Engineer'), $title, (bool) $wasReturned);
    }

    respond(['success' => true, 'id' => $id, 'status' => $status], $existing ? 200 : 201);
}

respond(['error' => 'Unsupported request.'], 405);
