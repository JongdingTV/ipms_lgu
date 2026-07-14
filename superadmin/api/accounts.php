<?php
// ============================================================
// superadmin/api/accounts.php - account provisioning + supporting-document review.
// Separate from portal.php because multipart/form-data handling ($_POST + $_FILES)
// is a structurally different shape from portal.php's pure-JSON actions.
// super_admin only, same guard as portal.php.
//
// Dynamic document rows convention (matches superadmin.js's row-cloner):
//   documents[N][document_type], documents[N][title]  -- normal nested $_POST
//   document_files[N]                                  -- flat file array, i.e.
//   $_FILES['document_files']['name'][N] / ['tmp_name'][N] / etc.
// Kept as a separate flat file field (not documents[N][file]) to avoid PHP's
// nested-array-of-files quirk and let FileUpload::fromNestedFiles() work directly.
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Validator.php';
require_once __DIR__ . '/../../includes/Notifications.php';
require_once __DIR__ . '/../../includes/Pagination.php';
require_once __DIR__ . '/../../includes/OTPManager.php';
apiHeaders();
// Widened from super_admin-only so admin can reach the new request_staff_account
// action below — every other (pre-existing) action re-asserts super_admin-only
// individually, so this widening changes nothing for them.
requireAnyRole(['super_admin', 'admin']);
requireCsrfProtection();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
$user = currentUser();
$actorId = (int) ($user['user_id'] ?? 0);

const SUPPORTING_DOC_MAX_SIZE = 10 * 1024 * 1024;
const SUPPORTING_DOC_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];

/**
 * Maker-checker for internal staff (engineer/BAC) accounts: when an admin
 * (not super_admin) wants to create one, it lands here pending super_admin
 * approval instead of being created immediately — self-healing table, same
 * pattern as OTPManager/Notifications since this repo has no migration runner.
 */
function ensureStaffAccountRequestsTable(PDO $db): void
{
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS staff_account_requests (
              id INT AUTO_INCREMENT PRIMARY KEY,
              requested_role ENUM('engineer','bac') NOT NULL,
              full_name VARCHAR(150) NOT NULL,
              username VARCHAR(60) NOT NULL,
              email VARCHAR(180) NOT NULL,
              requested_by INT NULL,
              status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
              reviewed_by INT NULL,
              reviewed_at DATETIME NULL,
              rejection_reason TEXT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_staff_req_status (status),
              CONSTRAINT fk_staff_req_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
              CONSTRAINT fk_staff_req_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
    }
}
ensureStaffAccountRequestsTable($db);

/**
 * Reads documents[N][document_type]/[title] + document_files[N] into a flat,
 * pre-validated list. Rows with neither a title nor a file are silently
 * skipped (an untouched trailing row from the dynamic-field UI).
 */
function superadminCollectDocumentRows(array $textRows, array $filesField): array
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
                'max_size' => SUPPORTING_DOC_MAX_SIZE,
                'extensions' => SUPPORTING_DOC_EXTENSIONS,
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

/** Stores each row's file + inserts its supporting_documents row. Throws FileUploadException on failure. */
function superadminStoreDocuments(PDO $db, array $rows, string $ownerType, int $ownerId, int $uploadedBy, array &$storedFiles): void
{
    $stmt = $db->prepare('
        INSERT INTO supporting_documents
            (owner_type, owner_id, document_type, title, original_name, file_path, file_size, mime_type, uploaded_by, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")
    ');

    foreach ($rows as $row) {
        $stored = FileUpload::store($row['file'], 'supporting-documents/' . $ownerType, [
            'max_size' => SUPPORTING_DOC_MAX_SIZE,
            'extensions' => SUPPORTING_DOC_EXTENSIONS,
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
            $uploadedBy,
        ]);
    }
}

/** Best-effort cleanup when a DB step fails after files were already moved (not covered by SQL rollback). */
function superadminCleanupFiles(array $relativePaths): void
{
    foreach ($relativePaths as $path) {
        $full = dirname(__DIR__, 2) . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

function superadminListDocuments(PDO $db, int $page, int $perPage, string $ownerType, string $status): array
{
    $where = ['1=1'];
    $params = [];
    if ($ownerType !== '') {
        $where[] = 'd.owner_type = ?';
        $params[] = $ownerType;
    }
    if ($status !== '') {
        $where[] = 'd.status = ?';
        $params[] = $status;
    }
    $whereSql = implode(' AND ', $where);

    $select = "SELECT d.id, d.owner_type, d.owner_id, d.document_type, d.title, d.original_name, d.file_path,
                      d.file_size, d.mime_type, d.status, d.remarks, d.created_at, u.full_name AS uploaded_by_name
               FROM supporting_documents d
               LEFT JOIN users u ON u.id = d.uploaded_by
               WHERE $whereSql ORDER BY d.created_at DESC, d.id DESC";
    $count = "SELECT COUNT(*) FROM supporting_documents d WHERE $whereSql";

    return paginate($db, $select, $count, $params, $page, $perPage);
}

if ($method === 'GET') {
    if ($action === 'list_documents') {
        requireAnyRole(['super_admin']);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));
        respond(superadminListDocuments(
            $db,
            $page,
            $perPage,
            trim((string) ($_GET['owner_type'] ?? '')),
            trim((string) ($_GET['status'] ?? ''))
        ));
    }

    if ($action === 'list_staff_requests') {
        requireAnyRole(['super_admin']);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));

        $select = "SELECT r.*, u.full_name AS requested_by_name
                   FROM staff_account_requests r
                   LEFT JOIN users u ON u.id = r.requested_by
                   WHERE r.status = 'pending'
                   ORDER BY r.created_at ASC";
        $count = "SELECT COUNT(*) FROM staff_account_requests r WHERE r.status = 'pending'";
        respond(paginate($db, $select, $count, [], $page, $perPage));
    }

    respond(['error' => 'Unknown action.'], 404);
}

if ($method !== 'POST') {
    respond(['error' => 'Method not allowed.'], 405);
}

if ($action === 'create_user') {
    requireAnyRole(['super_admin']);
    $validated = Validator::make($_POST, [
        'full_name' => 'required|string|max:150',
        'username' => 'required|string|min:3|max:60',
        'email' => 'required|email',
        'role' => 'required|in:' . implode(',', APP_ROLES),
        'status' => 'nullable|in:active,inactive',
    ])->stopOnFailure();

    $documentRows = superadminCollectDocumentRows($_POST['documents'] ?? [], $_FILES['document_files'] ?? []);
    foreach ($documentRows as $i => $row) {
        if ($row['error'] !== null) {
            respond(['error' => 'Document row ' . ($i + 1) . ': ' . $row['error']], 422);
        }
    }

    $tempPassword = bin2hex(random_bytes(6));
    $newUserId = null;
    $storedFiles = [];

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $validated['username'],
            $validated['email'],
            password_hash($tempPassword, PASSWORD_BCRYPT),
            $validated['full_name'],
            $validated['role'],
            $validated['status'] ?? 'active',
        ]);
        $newUserId = (int) $db->lastInsertId();

        superadminStoreDocuments($db, $documentRows, 'user', $newUserId, $actorId, $storedFiles);

        $details = $validated['full_name'] . ' (' . $validated['role'] . ') account created.';
        auditLog($db, $actorId, 'user_created', 'users', $newUserId, $details);
        logActivity($actorId, 'user_created', $details);

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        superadminCleanupFiles($storedFiles);
        if ($e->getCode() === '23000') {
            respond(['error' => 'Username or email is already in use.', 'errors' => ['username' => 'Already in use.']], 422);
        }
        respond(['error' => 'Unable to create user.'], 500);
    } catch (Throwable $e) {
        $db->rollBack();
        superadminCleanupFiles($storedFiles);
        respond(['error' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to create user.'], 422);
    }

    respond(['success' => true, 'user_id' => $newUserId, 'temp_password' => $tempPassword], 201);
}

if ($action === 'create_contractor') {
    requireAnyRole(['super_admin']);
    $validated = Validator::make($_POST, [
        'name' => 'required|string|max:180',
        'contact_person' => 'nullable|string|max:150',
        'email' => 'nullable|email',
        'phone' => 'nullable|string|max:30',
        'address' => 'nullable|string|max:255',
        'status' => 'nullable|in:active,inactive,blacklisted',
        'create_login' => 'nullable|in:0,1',
        'username' => 'nullable|string|min:3|max:60',
        'password' => 'nullable|string|min:8',
    ])->stopOnFailure();

    $createLogin = ($validated['create_login'] ?? '0') === '1';
    if ($createLogin && (($validated['username'] ?? '') === '' || ($validated['password'] ?? '') === '')) {
        respond(['error' => 'Username and password are required to create a portal login.', 'errors' => ['username' => 'Required when creating a login.']], 422);
    }

    $documentRows = superadminCollectDocumentRows($_POST['documents'] ?? [], $_FILES['document_files'] ?? []);
    foreach ($documentRows as $i => $row) {
        if ($row['error'] !== null) {
            respond(['error' => 'Document row ' . ($i + 1) . ': ' . $row['error']], 422);
        }
    }

    $loginUserId = null;
    $contractorId = null;
    $storedFiles = [];

    $db->beginTransaction();
    try {
        if ($createLogin) {
            $userStmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, 'contractor', 'active')");
            $userStmt->execute([
                $validated['username'],
                ($validated['email'] ?? '') !== '' ? $validated['email'] : ($validated['username'] . '@contractor.local'),
                password_hash((string) $validated['password'], PASSWORD_BCRYPT),
                $validated['name'],
            ]);
            $loginUserId = (int) $db->lastInsertId();
        }

        $contractorStmt = $db->prepare('
            INSERT INTO contractors (user_id, name, contact_person, email, phone, address, performance_score, status)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?)
        ');
        $contractorStmt->execute([
            $loginUserId,
            $validated['name'],
            $validated['contact_person'] ?? null,
            $validated['email'] ?? null,
            $validated['phone'] ?? null,
            $validated['address'] ?? null,
            $validated['status'] ?? 'active',
        ]);
        $contractorId = (int) $db->lastInsertId();

        superadminStoreDocuments($db, $documentRows, 'contractor', $contractorId, $actorId, $storedFiles);

        $details = $validated['name'] . ' contractor profile created' . ($createLogin ? ' with portal login.' : '.');
        auditLog($db, $actorId, 'contractor_created', 'contractors', $contractorId, $details);
        logActivity($actorId, 'contractor_created', $details);

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        superadminCleanupFiles($storedFiles);
        if ($e->getCode() === '23000') {
            respond(['error' => 'Username or email is already in use.', 'errors' => ['username' => 'Already in use.']], 422);
        }
        respond(['error' => 'Unable to create contractor.'], 500);
    } catch (Throwable $e) {
        $db->rollBack();
        superadminCleanupFiles($storedFiles);
        respond(['error' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to create contractor.'], 422);
    }

    respond(['success' => true, 'contractor_id' => $contractorId, 'user_id' => $loginUserId], 201);
}

if ($action === 'create_engineer') {
    requireAnyRole(['super_admin']);
    $validated = Validator::make($_POST, [
        'full_name' => 'required|string|max:150',
        'username' => 'required|string|min:3|max:60',
        'email' => 'required|email',
        'status' => 'nullable|in:active,inactive',
    ])->stopOnFailure();

    $documentRows = superadminCollectDocumentRows($_POST['documents'] ?? [], $_FILES['document_files'] ?? []);
    foreach ($documentRows as $i => $row) {
        if ($row['error'] !== null) {
            respond(['error' => 'Document row ' . ($i + 1) . ': ' . $row['error']], 422);
        }
    }

    $tempPassword = bin2hex(random_bytes(6));
    $newUserId = null;
    $storedFiles = [];

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, 'engineer', ?)");
        $stmt->execute([
            $validated['username'],
            $validated['email'],
            password_hash($tempPassword, PASSWORD_BCRYPT),
            $validated['full_name'],
            $validated['status'] ?? 'active',
        ]);
        $newUserId = (int) $db->lastInsertId();

        superadminStoreDocuments($db, $documentRows, 'user', $newUserId, $actorId, $storedFiles);

        $details = $validated['full_name'] . ' engineer account created.';
        auditLog($db, $actorId, 'engineer_created', 'users', $newUserId, $details);
        logActivity($actorId, 'engineer_created', $details);

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        superadminCleanupFiles($storedFiles);
        if ($e->getCode() === '23000') {
            respond(['error' => 'Username or email is already in use.', 'errors' => ['username' => 'Already in use.']], 422);
        }
        respond(['error' => 'Unable to create engineer.'], 500);
    } catch (Throwable $e) {
        $db->rollBack();
        superadminCleanupFiles($storedFiles);
        respond(['error' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to create engineer.'], 422);
    }

    respond(['success' => true, 'user_id' => $newUserId, 'temp_password' => $tempPassword], 201);
}

if ($action === 'review_document') {
    requireAnyRole(['super_admin']);
    $validated = Validator::make(requestBody(), [
        'document_id' => 'required|integer',
        'decision' => 'required|in:verified,rejected',
        'remarks' => 'nullable|string|max:500',
    ])->stopOnFailure();

    $documentId = (int) $validated['document_id'];
    $decision = (string) $validated['decision'];
    $remarks = trim((string) ($validated['remarks'] ?? ''));

    $stmt = $db->prepare('SELECT id, title, owner_type, owner_id, uploaded_by FROM supporting_documents WHERE id = ?');
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
    auditLog($db, $actorId, 'document_reviewed', 'supporting_documents', $documentId, $details);
    logActivity($actorId, 'document_reviewed', $details);

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

if ($action === 'request_staff_account') {
    // The maker side of the maker-checker: admin submits a request, but only
    // super_admin's decide_staff_request action below actually creates the
    // login. (super_admin itself keeps using create_user/create_engineer
    // directly above — this queue exists specifically for admin-initiated
    // requests.)
    requireAnyRole(['admin']);

    $validated = Validator::make(requestBody(), [
        'requested_role' => 'required|in:engineer,bac',
        'full_name' => 'required|string|max:150',
        'username' => 'required|string|min:3|max:60',
        'email' => 'required|email',
    ])->stopOnFailure();

    $stmt = $db->prepare('
        INSERT INTO staff_account_requests (requested_role, full_name, username, email, requested_by, status)
        VALUES (?, ?, ?, ?, ?, "pending")
    ');
    $stmt->execute([
        $validated['requested_role'],
        $validated['full_name'],
        $validated['username'],
        $validated['email'],
        $actorId,
    ]);
    $requestId = (int) $db->lastInsertId();

    $details = $validated['full_name'] . ' (' . $validated['requested_role'] . ') account request submitted for Super Admin approval.';
    logActivity($actorId, 'staff_account_requested', $details);

    respond(['success' => true, 'request_id' => $requestId], 201);
}

if ($action === 'decide_staff_request') {
    requireAnyRole(['super_admin']);

    $validated = Validator::make(requestBody(), [
        'request_id' => 'required|integer',
        'decision' => 'required|in:approve,reject',
        'reason' => 'nullable|string|max:500',
    ])->stopOnFailure();

    $requestId = (int) $validated['request_id'];
    $decision = (string) $validated['decision'];
    $reason = trim((string) ($validated['reason'] ?? ''));

    if ($decision === 'reject' && $reason === '') {
        respond(['error' => 'A reason is required to reject a request.'], 422);
    }

    $stmt = $db->prepare('SELECT * FROM staff_account_requests WHERE id = ?');
    $stmt->execute([$requestId]);
    $reqRow = $stmt->fetch();
    if (!$reqRow) {
        respond(['error' => 'Request not found.'], 404);
    }
    if ($reqRow['status'] !== 'pending') {
        respond(['error' => 'This request has already been reviewed.'], 422);
    }

    if ($decision === 'reject') {
        $db->prepare("
            UPDATE staff_account_requests
            SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ?
            WHERE id = ?
        ")->execute([$actorId, $reason !== '' ? $reason : null, $requestId]);

        $details = $reqRow['full_name'] . '\'s ' . $reqRow['requested_role'] . ' account request was rejected — ' . $reason . '.';
        logActivity($actorId, 'staff_account_rejected', $details);
        if (!empty($reqRow['requested_by'])) {
            notifyUser((int) $reqRow['requested_by'], 'info', 'Staff account request rejected', $details);
        }

        respond(['success' => true, 'status' => 'rejected']);
    }

    $tempPassword = bin2hex(random_bytes(6));
    $newUserId = null;

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->execute([
            $reqRow['username'],
            $reqRow['email'],
            password_hash($tempPassword, PASSWORD_BCRYPT),
            $reqRow['full_name'],
            $reqRow['requested_role'],
        ]);
        $newUserId = (int) $db->lastInsertId();

        $db->prepare("UPDATE staff_account_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
           ->execute([$actorId, $requestId]);

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        if ($e->getCode() === '23000') {
            respond(['error' => 'Username or email is already in use.'], 422);
        }
        respond(['error' => 'Unable to create account.'], 500);
    } catch (Throwable $e) {
        $db->rollBack();
        respond(['error' => 'Unable to create account.'], 500);
    }

    $details = $reqRow['full_name'] . ' (' . $reqRow['requested_role'] . ') account approved and created.';
    auditLog($db, $actorId, 'staff_account_approved', 'users', $newUserId, $details);
    logActivity($actorId, 'staff_account_approved', $details);
    if (!empty($reqRow['requested_by'])) {
        notifyUser((int) $reqRow['requested_by'], 'info', 'Staff account request approved', $details);
    }

    $otp = new OTPManager();
    $loginUrl = appUrl('/auth/forgot-password.php?from=staff');
    $emailBody = '
        <p>Hello <strong>' . htmlspecialchars($reqRow['full_name'], ENT_QUOTES, 'UTF-8') . '</strong>,</p>
        <p>An account has been created for you on the LGU Infrastructure Project Management System.</p>
        <p>To access your portal, set your password here:</p>
        <p><a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '</a></p>
        <p>Your username is: <strong>' . htmlspecialchars($reqRow['username'], ENT_QUOTES, 'UTF-8') . '</strong></p>
    ';
    $otp->sendPlainEmail($reqRow['email'], 'Your IPMS Account Has Been Created', $emailBody);

    respond(['success' => true, 'status' => 'approved', 'temp_password' => $tempPassword]);
}

respond(['error' => 'Unknown action.'], 404);
