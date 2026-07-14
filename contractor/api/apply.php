<?php
// ============================================================
// contractor/api/apply.php — public (no login) contractor application intake.
// Mirrors superadmin/api/accounts.php's create_contractor document-row
// convention: documents[N][document_type]/[title] + flat document_files[N].
// Creates a contractors row with application_status='pending', user_id=NULL —
// this exact "business record with no login yet" shape already worked before
// this feature (an optional branch in create_contractor); it's just now the
// only path new contractors ever enter through.
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/Validator.php';
require_once __DIR__ . '/../../includes/workflow.php';
apiHeaders();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    respond(['error' => 'Method not allowed.'], 405);
}

requireCsrfProtection();

$db = getDB();
contractorsEnsureApplicationSchema($db);

const CONTRACTOR_APP_DOC_MAX_SIZE = 10 * 1024 * 1024;
const CONTRACTOR_APP_DOC_EXTENSIONS = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];

function contractorAppCollectDocumentRows(array $textRows, array $filesField): array
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
                'max_size' => CONTRACTOR_APP_DOC_MAX_SIZE,
                'extensions' => CONTRACTOR_APP_DOC_EXTENSIONS,
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

function contractorAppStoreDocuments(PDO $db, array $rows, int $contractorId, array &$storedFiles): void
{
    $stmt = $db->prepare('
        INSERT INTO supporting_documents
            (owner_type, owner_id, document_type, title, original_name, file_path, file_size, mime_type, uploaded_by, status)
        VALUES ("contractor", ?, ?, ?, ?, ?, ?, ?, NULL, "pending")
    ');

    foreach ($rows as $row) {
        $stored = FileUpload::store($row['file'], 'supporting-documents/contractor', [
            'max_size' => CONTRACTOR_APP_DOC_MAX_SIZE,
            'extensions' => CONTRACTOR_APP_DOC_EXTENSIONS,
        ]);
        $storedFiles[] = $stored['stored_path'];

        $stmt->execute([
            $contractorId,
            $row['document_type'],
            $row['title'],
            $stored['original_name'],
            $stored['stored_path'],
            $stored['file_size'],
            $stored['mime_type'],
        ]);
    }
}

function contractorAppCleanupFiles(array $relativePaths): void
{
    foreach ($relativePaths as $path) {
        $full = dirname(__DIR__, 2) . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

$validated = Validator::make($_POST, [
    'name' => 'required|string|max:150',
    'contact_person' => 'nullable|string|max:120',
    'email' => 'required|email|max:180',
    'phone' => 'nullable|string|max:30',
    'address' => 'nullable|string|max:1000',
    'pcab_license_no' => 'required|string|max:50',
    'pcab_classification' => 'required|in:Small B,Small A,Medium B,Medium A,Large B,Large A',
])->stopOnFailure();

$documentRows = contractorAppCollectDocumentRows($_POST['documents'] ?? [], $_FILES['document_files'] ?? []);
if ($documentRows === []) {
    respond(['error' => 'At least one supporting document (business permit, tax clearance, etc.) is required.'], 422);
}
foreach ($documentRows as $i => $row) {
    if ($row['error'] !== null) {
        respond(['error' => 'Document row ' . ($i + 1) . ': ' . $row['error']], 422);
    }
}

$storedFiles = [];
$newId = null;

$db->beginTransaction();
try {
    $stmt = $db->prepare('
        INSERT INTO contractors (name, contact_person, email, phone, address, pcab_license_no, pcab_classification, status, application_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, "active", "pending")
    ');
    $stmt->execute([
        $validated['name'],
        $validated['contact_person'] ?? null,
        $validated['email'],
        $validated['phone'] ?? null,
        $validated['address'] ?? null,
        $validated['pcab_license_no'],
        $validated['pcab_classification'],
    ]);

    $newId = (int) $db->lastInsertId();
    contractorAppStoreDocuments($db, $documentRows, $newId, $storedFiles);

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    contractorAppCleanupFiles($storedFiles);

    if ($e instanceof PDOException && $e->getCode() === '23000') {
        respond(['error' => 'An application with this email may already be on file.'], 422);
    }
    respond(['error' => 'Unable to submit application. Please try again later.'], 422);
}

respond(['success' => true, 'id' => $newId], 201);
