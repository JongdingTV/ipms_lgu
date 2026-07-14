<?php
// ============================================================
// api/workflow.php - contracts, inspections, and payment reviews
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/Notifications.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin', 'engineer']);
requireCsrfProtection();

$db = getDB();
projectWorkflowEnsureRoleConnectionTables($db);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? 'summary';
$user = currentUser();
$userId = (int) ($user['user_id'] ?? $_SESSION['user_id'] ?? 0);
$role = (string) ($user['role'] ?? '');
$isEngineer = $role === 'engineer';
$reviewerRole = $isEngineer ? 'engineer' : 'admin';

function workflowEngineerProjectFilter(bool $isEngineer): string
{
    return $isEngineer
        ? "INNER JOIN engineer_project_assignments epa ON epa.project_id = p.id AND epa.engineer_id = ? AND epa.status = 'active'"
        : "";
}

function workflowEngineerParams(bool $isEngineer, int $userId): array
{
    return $isEngineer ? [$userId] : [];
}

if ($method === 'GET') {
    $engineerJoin = workflowEngineerProjectFilter($isEngineer);
    $engineerParams = workflowEngineerParams($isEngineer, $userId);

    if ($action === 'summary') {
        $contracts = $db->prepare("
            SELECT ct.*, p.project_code, p.name AS project_name, c.name AS contractor_name
            FROM contracts ct
            INNER JOIN projects p ON p.id = ct.project_id
            INNER JOIN contractors c ON c.id = ct.contractor_id
            $engineerJoin
            ORDER BY ct.created_at DESC, ct.id DESC
        ");
        $contracts->execute($engineerParams);

        $payments = $db->prepare("
            SELECT pr.*, p.project_code, p.name AS project_name, c.name AS contractor_name,
                   cr.report_date, cr.progress_percent,
                   (SELECT CONCAT(u.full_name, ' - ', rv.recommendation)
                    FROM payment_reviews rv
                    INNER JOIN users u ON u.id = rv.reviewed_by
                    WHERE rv.payment_request_id = pr.id
                    ORDER BY rv.reviewed_at DESC, rv.id DESC
                    LIMIT 1) AS latest_review
            FROM payment_requests pr
            INNER JOIN projects p ON p.id = pr.project_id
            INNER JOIN contractors c ON c.id = pr.contractor_id
            LEFT JOIN contractor_reports cr ON cr.id = pr.progress_report_id
            $engineerJoin
            ORDER BY FIELD(pr.status, 'submitted', 'under_review', 'approved', 'paid', 'rejected'), pr.submitted_at DESC, pr.id DESC
        ");
        $payments->execute($engineerParams);

        $inspections = $db->prepare("
            SELECT i.*, p.project_code, p.name AS project_name, u.full_name AS engineer_name,
                   cr.report_date, cr.progress_percent AS reported_progress
            FROM inspections i
            INNER JOIN projects p ON p.id = i.project_id
            INNER JOIN users u ON u.id = i.engineer_id
            LEFT JOIN contractor_reports cr ON cr.id = i.progress_report_id
            $engineerJoin
            ORDER BY i.created_at DESC, i.id DESC
            LIMIT 20
        ");
        $inspections->execute($engineerParams);

        respond([
            'contracts' => $contracts->fetchAll(),
            'payment_requests' => $payments->fetchAll(),
            'inspections' => $inspections->fetchAll(),
        ]);
    }

    respond(['error' => 'Unknown action.'], 404);
}

if ($method !== 'POST') {
    respond(['error' => 'Method not allowed.'], 405);
}

$body = requestBody();

if ($action === 'payment_review') {
    $paymentId = (int) ($body['payment_request_id'] ?? 0);
    $recommendation = (string) ($body['recommendation'] ?? '');
    $remarks = trim((string) ($body['remarks'] ?? ''));

    if ($paymentId <= 0 || !in_array($recommendation, ['approve', 'reject', 'return'], true)) {
        respond(['error' => 'Payment request and valid recommendation are required.'], 422);
    }

    $paymentSql = "
        SELECT pr.*, p.project_code, p.name AS project_name
        FROM payment_requests pr
        INNER JOIN projects p ON p.id = pr.project_id
    ";
    $paymentParams = [];
    if ($isEngineer) {
        $paymentSql .= "
            INNER JOIN engineer_project_assignments epa
                ON epa.project_id = p.id
               AND epa.engineer_id = ?
               AND epa.status = 'active'
        ";
        $paymentParams[] = $userId;
    }
    $paymentSql .= " WHERE pr.id = ? LIMIT 1";
    $paymentParams[] = $paymentId;

    $paymentStmt = $db->prepare($paymentSql);
    $paymentStmt->execute($paymentParams);
    $payment = $paymentStmt->fetch();
    if (!$payment) {
        respond(['error' => 'Payment request not found.'], 404);
    }

    $newStatus = 'under_review';
    if ($recommendation === 'reject') {
        $newStatus = 'rejected';
    } elseif (!$isEngineer && $recommendation === 'approve') {
        $newStatus = 'approved';
    }

    $db->beginTransaction();
    try {
        $review = $db->prepare("
            INSERT INTO payment_reviews
                (payment_request_id, reviewed_by, reviewer_role, remarks, recommendation)
            VALUES (?, ?, ?, ?, ?)
        ");
        $review->execute([
            $paymentId,
            $userId,
            $reviewerRole,
            $remarks !== '' ? $remarks : null,
            $recommendation,
        ]);

        $db->prepare("UPDATE payment_requests SET status = ? WHERE id = ?")
            ->execute([$newStatus, $paymentId]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        respond(['error' => 'Unable to save payment review.'], 500);
    }

    $cu = $db->prepare("SELECT user_id FROM contractors WHERE id = ?");
    $cu->execute([$payment['contractor_id']]);
    notifyUser(
        (int) ($cu->fetchColumn() ?: 0),
        'info',
        'Payment request ' . $newStatus,
        'Your payment request for ' . $payment['project_name'] . ' is now "' . $newStatus . '".'
    );

    respond(['success' => true, 'status' => $newStatus], 201);
}

if ($action === 'mark_paid') {
    if ($isEngineer) {
        respond(['error' => 'Only admin or super admin can finalize payments.'], 403);
    }

    $paymentId = (int) ($body['payment_request_id'] ?? 0);
    if ($paymentId <= 0) {
        respond(['error' => 'Payment request is required.'], 422);
    }

    $stmt = $db->prepare("
        SELECT pr.*, p.name AS project_name
        FROM payment_requests pr
        INNER JOIN projects p ON p.id = pr.project_id
        WHERE pr.id = ?
        LIMIT 1
    ");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
    if (!$payment) {
        respond(['error' => 'Payment request not found.'], 404);
    }
    if ($payment['status'] !== 'approved') {
        respond(['error' => 'Only approved payment requests can be marked as paid.'], 422);
    }

    $db->prepare("UPDATE payment_requests SET status = 'paid' WHERE id = ?")->execute([$paymentId]);

    $details = 'Payment request #' . $paymentId . ' for ' . $payment['project_name'] . ' marked as paid.';
    projectWorkflowLog($db, 'Payment marked as paid', (int) $payment['project_id'], $details, $userId);
    logActivity($userId, 'payment_marked_paid', $details);

    $cu = $db->prepare("SELECT user_id FROM contractors WHERE id = ?");
    $cu->execute([$payment['contractor_id']]);
    notifyUser(
        (int) ($cu->fetchColumn() ?: 0),
        'info',
        'Payment marked as paid',
        'Your payment request for ' . $payment['project_name'] . ' has been marked as paid.'
    );

    respond(['success' => true, 'status' => 'paid']);
}

respond(['error' => 'Unknown action.'], 404);
