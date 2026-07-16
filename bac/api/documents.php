<?php
// ============================================================
// bac/api/documents.php - procurement document uploads (Invitation to Bid,
// Approved Budget for the Contract, Abstract of Bids, Notice of Award,
// Board Resolution, etc.) and review.
// Separate from portal.php because multipart/form-data handling ($_POST +
// $_FILES) is a structurally different shape from portal.php's pure-JSON
// actions — mirrors superadmin/api/accounts.php's split for the same reason.
//
// Dynamic document rows convention (matches superadmin.js's row-cloner,
// reused as-is by bac.js): documents[N][document_type], documents[N][title]
// as normal nested $_POST, plus a flat document_files[N] file field.
//
// owner_type='project'  -> owner_id = projects.id      (announcement-stage docs)
// owner_type='bac_bid'  -> owner_id = bac_bid_submissions.id (award-stage docs)
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Validator.php';
require_once __DIR__ . '/../../includes/Pagination.php';
require_once __DIR__ . '/../../includes/Notifications.php';
apiHeaders();
requireAnyRole(['super_admin', 'admin', 'bac']);
requireCsrfProtection();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
$user = currentUser();
$actorId = (int) ($user['user_id'] ?? 0);

const BAC_DOC_MAX_SIZE = 10 * 1024 * 1024;
const BAC_DOC_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
// owner_type='contractor' -> owner_id = contractors.id (post-approval
// accreditation renewals, e.g. an updated business permit). Distinct from
// portal.php's list_contractor_applications, which is scoped to brand-new
// applicants only (application_status='pending') — this queue is for
// already-accredited contractors' subsequent document updates.
const BAC_DOC_OWNER_TYPES = ['project', 'bac_bid', 'contractor'];

/** Reused verbatim from superadmin/api/accounts.php's document-row convention. */
function bacCollectDocumentRows(array $textRows, array $filesField): array
{
    $indices = array_keys($textRows);
    foreach (array_keys($filesField['name'] ?? []) as $idx) {
        if (!in_array($idx, $indices, true)) {
            $indices[] = $idx;
        }
    }

    $rows = [];
    foreach ($indices as $idx) {
        $documentType = trim((string) ($textRows[$idx]['document_type'] ?? ''));
        $title = trim((string) ($textRows[$idx]['title'] ?? ''));
        $file = FileUpload::fromNestedFiles($filesField, (int) $idx);

        if ($title === '' && $file === null) {
            continue;
        }

        if ($title === '') {
            $error = 'Title is required when a file is attached.';
        } else {
            $error = FileUpload::validate($file, [
                'required' => true,
                'max_size' => BAC_DOC_MAX_SIZE,
                'extensions' => BAC_DOC_EXTENSIONS,
            ]);
        }

        $rows[] = [
            'document_type' => $documentType !== '' ? $documentType : 'General',
            'title' => $title,
            'file' => $file,
            'error' => $error,
        ];
    }

    return $rows;
}

function bacCleanupFiles(array $relativePaths): void
{
    foreach ($relativePaths as $path) {
        $full = dirname(__DIR__, 2) . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

function bacListDocuments(PDO $db, int $page, int $perPage, string $ownerType, int $ownerId, string $status): array
{
    $where = ["d.owner_type IN ('" . implode("', '", BAC_DOC_OWNER_TYPES) . "')"];
    $params = [];
    if ($ownerType !== '') {
        $where[] = 'd.owner_type = ?';
        $params[] = $ownerType;
    }
    if ($ownerId > 0) {
        $where[] = 'd.owner_id = ?';
        $params[] = $ownerId;
    }
    if ($status !== '') {
        $where[] = 'd.status = ?';
        $params[] = $status;
    }
    // owner_type='contractor' rows are post-approval updates only — a
    // brand-new application (application_status='pending') is still reviewed
    // through the dedicated list_contractor_applications/
    // review_contractor_application flow in portal.php, not here.
    $where[] = "(d.owner_type != 'contractor' OR EXISTS (
        SELECT 1 FROM contractors c WHERE c.id = d.owner_id AND c.application_status = 'approved'
    ))";
    $whereSql = implode(' AND ', $where);

    $select = "SELECT d.id, d.owner_type, d.owner_id, d.document_type, d.title, d.original_name, d.file_path,
                      d.file_size, d.mime_type, d.status, d.remarks, d.created_at, u.full_name AS uploaded_by_name,
                      p.project_code, p.name AS project_name, ct.name AS contractor_name
               FROM supporting_documents d
               LEFT JOIN users u ON u.id = d.uploaded_by
               LEFT JOIN projects p ON (d.owner_type = 'project' AND p.id = d.owner_id)
                   OR (d.owner_type = 'bac_bid' AND p.id = (SELECT project_id FROM bac_bid_submissions WHERE id = d.owner_id))
               LEFT JOIN contractors ct ON (d.owner_type = 'contractor' AND ct.id = d.owner_id)
               WHERE $whereSql ORDER BY d.created_at DESC, d.id DESC";
    $count = "SELECT COUNT(*) FROM supporting_documents d WHERE $whereSql";

    return paginate($db, $select, $count, $params, $page, $perPage);
}

if ($method === 'GET') {
    if ($action === 'list') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));
        respond(bacListDocuments(
            $db,
            $page,
            $perPage,
            trim((string) ($_GET['owner_type'] ?? '')),
            (int) ($_GET['owner_id'] ?? 0),
            trim((string) ($_GET['status'] ?? ''))
        ));
    }

    respond(['error' => 'Unknown action.'], 404);
}

if ($method !== 'POST') {
    respond(['error' => 'Method not allowed.'], 405);
}

if ($action === 'upload') {
    $validated = Validator::make($_POST, [
        'owner_type' => 'required|in:' . implode(',', BAC_DOC_OWNER_TYPES),
        'owner_id' => 'required|integer',
    ])->stopOnFailure();

    $ownerType = (string) $validated['owner_type'];
    $ownerId = (int) $validated['owner_id'];

    if ($ownerType === 'project') {
        $exists = $db->prepare('SELECT id, name FROM projects WHERE id = ?');
    } else {
        $exists = $db->prepare("
            SELECT b.id, p.name FROM bac_bid_submissions b
            INNER JOIN projects p ON p.id = b.project_id
            WHERE b.id = ?
        ");
    }
    $exists->execute([$ownerId]);
    $owner = $exists->fetch();
    if (!$owner) {
        respond(['error' => $ownerType === 'project' ? 'Project not found.' : 'Bid submission not found.'], 404);
    }

    $documentRows = bacCollectDocumentRows($_POST['documents'] ?? [], $_FILES['document_files'] ?? []);
    if ($documentRows === []) {
        respond(['error' => 'At least one document (title + file) is required.'], 422);
    }
    foreach ($documentRows as $i => $row) {
        if ($row['error'] !== null) {
            respond(['error' => 'Document row ' . ($i + 1) . ': ' . $row['error']], 422);
        }
    }

    $storedFiles = [];
    $insertedIds = [];

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('
            INSERT INTO supporting_documents
                (owner_type, owner_id, document_type, title, original_name, file_path, file_size, mime_type, uploaded_by, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")
        ');

        foreach ($documentRows as $row) {
            $stored = FileUpload::store($row['file'], 'bac-documents/' . $ownerType, [
                'max_size' => BAC_DOC_MAX_SIZE,
                'extensions' => BAC_DOC_EXTENSIONS,
            ]);
            $storedFiles[] = $stored['stored_path'];

            $stmt->execute([
                $ownerType,
                $ownerId,
                $row['document_type'],
                $row['title'],
                $stored['original_name'],
                $stored['stored_path'],
                $stored['file_size'],
                $stored['mime_type'],
                $actorId,
            ]);
            $insertedIds[] = (int) $db->lastInsertId();
        }

        $details = count($documentRows) . ' document(s) attached to ' . $owner['name'] . ' (' . $ownerType . ' #' . $ownerId . ').';
        auditLog($db, $actorId, 'procurement_document_uploaded', 'supporting_documents', $insertedIds[0] ?? null, $details);
        logActivity($actorId, 'procurement_document_uploaded', $details);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        bacCleanupFiles($storedFiles);
        respond(['error' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to upload documents.'], 422);
    }

    respond(['success' => true, 'ids' => $insertedIds], 201);
}

if ($action === 'review') {
    $validated = Validator::make(requestBody(), [
        'document_id' => 'required|integer',
        'decision' => 'required|in:verified,rejected',
        'remarks' => 'nullable|string|max:500',
    ])->stopOnFailure();

    $documentId = (int) $validated['document_id'];
    $decision = (string) $validated['decision'];
    $remarks = trim((string) ($validated['remarks'] ?? ''));

    $stmt = $db->prepare("SELECT id, title, owner_type, owner_id, uploaded_by FROM supporting_documents WHERE id = ? AND owner_type IN ('" . implode("', '", BAC_DOC_OWNER_TYPES) . "')");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch();
    if (!$document) {
        respond(['error' => 'Document not found.'], 404);
    }

    $db->prepare('
        UPDATE supporting_documents
        SET status = ?, remarks = ?, reviewed_by = ?, reviewed_at = NOW()
        WHERE id = ?
    ')->execute([$decision, $remarks !== '' ? $remarks : null, $actorId, $documentId]);

    $details = '"' . $document['title'] . '" (' . $document['owner_type'] . ' #' . $document['owner_id'] . ') ' . $decision . '.';
    auditLog($db, $actorId, 'procurement_document_reviewed', 'supporting_documents', $documentId, $details);
    logActivity($actorId, 'procurement_document_reviewed', $details);

    if (!empty($document['uploaded_by']) && (int) $document['uploaded_by'] !== $actorId) {
        notifyUser(
            (int) $document['uploaded_by'],
            'info',
            'Document ' . $decision,
            '"' . $document['title'] . '" was ' . $decision . '.'
        );
    }

    respond(['success' => true]);
}

respond(['error' => 'Unknown action.'], 404);
