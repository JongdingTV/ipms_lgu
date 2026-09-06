<?php
// Project Proposal API: engineer-owned proposals and read-only feedback basis.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';

apiHeaders();
$user = currentUser();
$role = (string) ($user['role'] ?? '');
requireAnyRole(['engineer', 'admin', 'super_admin']);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    requireCsrfProtection();
}

$db = getDB();
feedbackEnsureSchema($db);
projectProposalEnsureSchema($db);
$userId = (int) ($user['user_id'] ?? $user['id'] ?? 0);

function proposalEngineerDistrict(PDO $db, int $userId): ?string
{
    $stmt = $db->prepare("SELECT district FROM users WHERE id = ? AND role = 'engineer'");
    $stmt->execute([$userId]);
    $district = $stmt->fetchColumn();
    return $district !== false && $district !== null && trim((string) $district) !== '' ? (string) $district : null;
}

function proposalFeedbackWhere(array &$params, ?string $district, string $alias = 'f'): string
{
    $where = ["$alias.status NOT IN ('closed', 'resolved')"];
    if ($district !== null) {
        $where[] = "($alias.district = ? OR $alias.district IS NULL)";
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

function proposalResponse(PDO $db, array $row): array
{
    $feedback = $db->prepare("SELECT f.id, f.citizen_name, f.message, f.category, f.infrastructure_type, f.priority, f.status, f.district, f.barangay, f.created_at FROM feedback f INNER JOIN project_proposal_feedback ppf ON ppf.feedback_id = f.id WHERE ppf.proposal_id = ? ORDER BY f.created_at DESC");
    $feedback->execute([(int) $row['id']]);
    $row['feedback_basis'] = $feedback->fetchAll();
    return $row;
}

if ($method === 'GET') {
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
        $stmt = $db->prepare("SELECT f.id, f.citizen_name, f.message, f.category, f.infrastructure_type, f.concern_type, f.priority, f.status, f.district, f.barangay, f.latitude, f.longitude, f.created_at FROM feedback f WHERE $where ORDER BY FIELD(f.priority, 'urgent', 'high', 'medium', 'low'), f.created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        respond(['data' => $stmt->fetchAll(), 'district' => $district, 'page' => $page, 'last_page' => $lastPage, 'total' => $total]);
    }

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $params = [];
    $where = [];
    if ($role === 'engineer') {
        $where[] = 'pp.engineer_id = ?';
        $params[] = $userId;
    }
    if (!empty($_GET['status'])) {
        $where[] = 'pp.status = ?';
        $params[] = (string) $_GET['status'];
    }
    if ($id > 0) {
        $where[] = 'pp.id = ?';
        $params[] = $id;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $db->prepare("SELECT pp.*, u.full_name AS engineer_name FROM project_proposals pp INNER JOIN users u ON u.id = pp.engineer_id $whereSql ORDER BY pp.updated_at DESC, pp.id DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if ($id > 0) {
        if (!$rows) respond(['error' => 'Proposal not found'], 404);
        respond(['data' => proposalResponse($db, $rows[0])]);
    }
    $countStmt = $db->prepare("SELECT COUNT(*) FROM project_proposals pp $whereSql");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $limit = 8;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $lastPage = max(1, (int) ceil($total / $limit));
    $page = min($page, $lastPage);
    $offset = ($page - 1) * $limit;
    $stmt = $db->prepare("SELECT pp.*, u.full_name AS engineer_name FROM project_proposals pp INNER JOIN users u ON u.id = pp.engineer_id $whereSql ORDER BY pp.updated_at DESC, pp.id DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $count = $db->prepare('SELECT COUNT(*) FROM project_proposal_feedback WHERE proposal_id = ?');
        $count->execute([(int) $row['id']]);
        $row['feedback_count'] = (int) $count->fetchColumn();
    }
    unset($row);
    respond(['data' => $rows, 'page' => $page, 'last_page' => $lastPage, 'total' => $total]);
}

if ($method === 'POST' || $method === 'PUT') {
    if ($role !== 'engineer') {
        respond(['error' => 'Only Engineers can create or edit project proposals.'], 403);
    }
    $body = requestBody();
    $id = (int) ($body['id'] ?? $_GET['id'] ?? 0);
    $action = (string) ($body['action'] ?? $_GET['action'] ?? 'save_draft');
    $title = trim((string) ($body['title'] ?? ''));
    $category = trim((string) ($body['category'] ?? ''));
    $infrastructureType = trim((string) ($body['infrastructure_type'] ?? ''));
    $description = trim((string) ($body['description'] ?? ''));
    $justification = trim((string) ($body['justification'] ?? ''));
    $observedProblem = trim((string) ($body['observed_problem'] ?? ''));
    $proposedSolution = trim((string) ($body['proposed_solution'] ?? ''));
    $location = trim((string) ($body['location'] ?? ''));
    $district = trim((string) ($body['district'] ?? ''));
    $barangay = trim((string) ($body['barangay'] ?? ''));
    $priority = trim((string) ($body['priority'] ?? 'medium'));
    $feedbackIds = array_values(array_unique(array_filter(array_map('intval', (array) ($body['feedback_ids'] ?? [])))));

    if ($title === '' || $category === '' || $description === '' || $justification === '' || $location === '' || $district === '' || $barangay === '') {
        respond(['error' => 'Title, category, description, justification, location, district, and barangay are required.'], 422);
    }
    if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) respond(['error' => 'Invalid priority.'], 422);

    $engineerDistrict = proposalEngineerDistrict($db, $userId);
    if ($engineerDistrict !== null && $district !== $engineerDistrict) {
        respond(['error' => 'Proposal district must match your assigned district.'], 422);
    }

    $existing = null;
    if ($id > 0) {
        $stmt = $db->prepare('SELECT * FROM project_proposals WHERE id = ? AND engineer_id = ?');
        $stmt->execute([$id, $userId]);
        $existing = $stmt->fetch();
        if (!$existing) respond(['error' => 'Proposal not found'], 404);
        if ($existing['status'] !== 'draft') respond(['error' => 'Only draft proposals can be edited.'], 409);
    }

    $status = $action === 'submit' ? 'submitted' : 'draft';
    $db->beginTransaction();
    try {
        if ($existing) {
            $db->prepare("UPDATE project_proposals SET title = ?, category = ?, infrastructure_type = ?, description = ?, justification = ?, observed_problem = ?, proposed_solution = ?, location = ?, district = ?, barangay = ?, priority = ?, status = ?, submitted_at = CASE WHEN ? = 'submitted' THEN COALESCE(submitted_at, NOW()) ELSE NULL END, updated_at = NOW() WHERE id = ? AND engineer_id = ?")
                ->execute([$title, $category, $infrastructureType ?: null, $description, $justification, $observedProblem ?: null, $proposedSolution ?: null, $location, $district, $barangay, $priority, $status, $status, $id, $userId]);
        } else {
            $db->prepare("INSERT INTO project_proposals (proposal_code, title, category, infrastructure_type, description, justification, observed_problem, proposed_solution, location, district, barangay, priority, engineer_id, status, submitted_at) VALUES ('PENDING', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? = 'submitted' THEN NOW() ELSE NULL END)")
                ->execute([$title, $category, $infrastructureType ?: null, $description, $justification, $observedProblem ?: null, $proposedSolution ?: null, $location, $district, $barangay, $priority, $userId, $status, $status]);
            $id = (int) $db->lastInsertId();
            $code = 'PP-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            $db->prepare('UPDATE project_proposals SET proposal_code = ? WHERE id = ?')->execute([$code, $id]);
        }

        $db->prepare('DELETE FROM project_proposal_feedback WHERE proposal_id = ?')->execute([$id]);
        if ($feedbackIds) {
            $feedbackStmt = $db->prepare("SELECT id FROM feedback WHERE id IN (" . implode(',', array_fill(0, count($feedbackIds), '?')) . ") AND status NOT IN ('closed', 'resolved')" . ($engineerDistrict !== null ? ' AND (district = ? OR district IS NULL)' : ''));
            $feedbackParams = $feedbackIds;
            if ($engineerDistrict !== null) $feedbackParams[] = $engineerDistrict;
            $feedbackStmt->execute($feedbackParams);
            $allowedFeedbackIds = array_map('intval', $feedbackStmt->fetchAll(PDO::FETCH_COLUMN));
            $link = $db->prepare('INSERT INTO project_proposal_feedback (proposal_id, feedback_id) VALUES (?, ?)');
            foreach ($allowedFeedbackIds as $feedbackId) $link->execute([$id, $feedbackId]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        respond(['error' => 'Unable to save project proposal.'], 500);
    }

    respond(['success' => true, 'id' => $id, 'status' => $status], $existing ? 200 : 201);
}

respond(['error' => 'Unsupported request.'], 405);
