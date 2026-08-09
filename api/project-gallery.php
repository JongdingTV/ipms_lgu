<?php
// ============================================================
// api/project-gallery.php — Project Blueprint/Gallery Photos (Admin)
//
// Admin-authored project imagery (blueprints, renders, site photos) that
// powers the citizen dashboard's slideshow + the project detail modal's
// gallery section. Deliberately separate from engineer_progress_photos
// (engineer-owned field photos) — Admin manages all projects regardless of
// assignment and holds the official design documents. Mirrors
// api/announcements.php's exact shape (action+method dispatch, not
// REST-verb dispatch, since multipart uploads don't work cleanly through
// PUT) plus FileUpload for the photo, the same pattern announcements' poster
// upload uses.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/FileUpload.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin']);
requireCsrfProtection();

$db = getDB();
projectGalleryEnsureSchema($db);
$user = currentUser();

const PROJECT_GALLERY_TYPES = ['blueprint', 'render', 'photo'];
const PROJECT_GALLERY_MAX_SIZE = 8 * 1024 * 1024;
const PROJECT_GALLERY_EXTENSIONS = ['png', 'jpg', 'jpeg'];

/** Best-effort cleanup when a gallery photo is deleted or its upload fails after the file was moved. */
function projectGalleryDeleteFile(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }
    $full = dirname(__DIR__) . '/' . $relativePath;
    if (is_file($full)) {
        @unlink($full);
    }
}

function projectGalleryHandleList(PDO $db): void
{
    $where = ['1=1'];
    $params = [];

    if (!empty($_GET['project_id'])) {
        $where[] = 'g.project_id = ?';
        $params[] = (int) $_GET['project_id'];
    }

    $whereSQL = implode(' AND ', $where);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 12;
    $offset = ($page - 1) * $limit;

    $total = $db->prepare("SELECT COUNT(*) FROM project_gallery_photos g WHERE $whereSQL");
    $total->execute($params);
    $totalRows = (int) $total->fetchColumn();

    $stmt = $db->prepare("
        SELECT g.*, p.project_code, p.name AS project_name
        FROM project_gallery_photos g
        INNER JOIN projects p ON p.id = g.project_id
        WHERE $whereSQL
        ORDER BY g.is_cover DESC, g.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);

    respond([
        'success' => true,
        'data' => $stmt->fetchAll(),
        'total' => $totalRows,
        'page' => $page,
        'last_page' => (int) ceil($totalRows / $limit),
    ]);
}

function projectGalleryHandleDetail(PDO $db, int $id): void
{
    $stmt = $db->prepare("
        SELECT g.*, p.project_code, p.name AS project_name
        FROM project_gallery_photos g
        INNER JOIN projects p ON p.id = g.project_id
        WHERE g.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        respond(['success' => false, 'message' => 'Photo not found'], 404);
    }
    respond(['success' => true, 'photo' => $row]);
}

function projectGalleryHandleUpload(PDO $db, ?array $user): void
{
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $caption = trim((string) ($_POST['caption'] ?? ''));
    $photoType = (string) ($_POST['photo_type'] ?? 'blueprint');

    if ($projectId <= 0) {
        respond(['success' => false, 'message' => 'A project must be selected.'], 422);
    }
    if ($title === '') {
        respond(['success' => false, 'message' => 'Title is required.'], 422);
    }
    if (!in_array($photoType, PROJECT_GALLERY_TYPES, true)) {
        respond(['success' => false, 'message' => 'Invalid photo type.'], 422);
    }

    $check = $db->prepare('SELECT id FROM projects WHERE id = ?');
    $check->execute([$projectId]);
    if (!$check->fetchColumn()) {
        respond(['success' => false, 'message' => 'Project not found.'], 422);
    }

    try {
        $stored = FileUpload::store($_FILES['photo'] ?? null, 'project-gallery', [
            'required' => true,
            'max_size' => PROJECT_GALLERY_MAX_SIZE,
            'extensions' => PROJECT_GALLERY_EXTENSIONS,
        ]);
    } catch (FileUploadException $e) {
        respond(['success' => false, 'message' => $e->getMessage()], 422);
    }

    $countStmt = $db->prepare('SELECT COUNT(*) FROM project_gallery_photos WHERE project_id = ?');
    $countStmt->execute([$projectId]);
    $isFirstPhoto = ((int) $countStmt->fetchColumn()) === 0;
    $makeCover = !empty($_POST['is_cover']) || $isFirstPhoto;

    $uploadedBy = (int) ($user['user_id'] ?? 0) ?: null;

    try {
        $db->beginTransaction();

        if ($makeCover) {
            $db->prepare("UPDATE project_gallery_photos SET is_cover = 0 WHERE project_id = ? AND is_cover = 1")
                ->execute([$projectId]);
        }

        $stmt = $db->prepare('
            INSERT INTO project_gallery_photos
                (project_id, title, caption, file_path, original_name, file_size, mime_type, photo_type, is_cover, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $projectId, $title, $caption !== '' ? $caption : null,
            $stored['stored_path'], $stored['original_name'], $stored['file_size'], $stored['mime_type'],
            $photoType, $makeCover ? 1 : 0, $uploadedBy,
        ]);
        $newId = (int) $db->lastInsertId();

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        projectGalleryDeleteFile($stored['stored_path']);
        respond(['success' => false, 'message' => 'Unable to save the photo. Please try again.'], 500);
    }

    logActivity($uploadedBy, 'project_gallery_uploaded', $title . ' was added to the project gallery.', 'Project Gallery', $newId);

    respond(['success' => true, 'id' => $newId], 201);
}

function projectGalleryHandleUpdate(PDO $db, ?array $user, int $id): void
{
    $existingStmt = $db->prepare('SELECT id, title FROM project_gallery_photos WHERE id = ?');
    $existingStmt->execute([$id]);
    if (!$existingStmt->fetch()) {
        respond(['success' => false, 'message' => 'Photo not found'], 404);
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $caption = trim((string) ($_POST['caption'] ?? ''));
    $photoType = (string) ($_POST['photo_type'] ?? 'blueprint');

    if ($title === '') {
        respond(['success' => false, 'message' => 'Title is required.'], 422);
    }
    if (!in_array($photoType, PROJECT_GALLERY_TYPES, true)) {
        respond(['success' => false, 'message' => 'Invalid photo type.'], 422);
    }

    $db->prepare('UPDATE project_gallery_photos SET title = ?, caption = ?, photo_type = ? WHERE id = ?')
        ->execute([$title, $caption !== '' ? $caption : null, $photoType, $id]);

    logActivity((int) ($user['user_id'] ?? 0) ?: null, 'project_gallery_updated', $title . ' was updated.', 'Project Gallery', $id);

    respond(['success' => true]);
}

function projectGalleryHandleSetCover(PDO $db, ?array $user, int $id): void
{
    $stmt = $db->prepare('SELECT id, project_id, title FROM project_gallery_photos WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        respond(['success' => false, 'message' => 'Photo not found'], 404);
    }

    projectGallerySetCover($db, (int) $row['project_id'], $id);

    logActivity((int) ($user['user_id'] ?? 0) ?: null, 'project_gallery_cover_set', $row['title'] . ' was set as the cover photo.', 'Project Gallery', $id);

    respond(['success' => true]);
}

function projectGalleryHandleDelete(PDO $db, ?array $user, int $id): void
{
    $stmt = $db->prepare('SELECT title, file_path FROM project_gallery_photos WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        respond(['success' => false, 'message' => 'Photo not found'], 404);
    }

    $db->prepare('DELETE FROM project_gallery_photos WHERE id = ?')->execute([$id]);
    projectGalleryDeleteFile($row['file_path']);

    logActivity((int) ($user['user_id'] ?? 0) ?: null, 'project_gallery_deleted', $row['title'] . ' was deleted.', 'Project Gallery', $id);

    respond(['success' => true]);
}

// ── Dispatch ────────────────────────────────────────────────────────────
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = (string) ($_GET['action'] ?? '');
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($method === 'GET' && $action === 'list') {
        projectGalleryHandleList($db);
    } elseif ($method === 'GET' && $action === 'detail') {
        if ($id <= 0) respond(['success' => false, 'message' => 'A valid id is required.'], 422);
        projectGalleryHandleDetail($db, $id);
    } elseif ($method === 'POST' && $action === 'upload') {
        projectGalleryHandleUpload($db, $user);
    } elseif ($method === 'POST' && $action === 'update') {
        if ($id <= 0) respond(['success' => false, 'message' => 'A valid id is required.'], 422);
        projectGalleryHandleUpdate($db, $user, $id);
    } elseif ($method === 'POST' && $action === 'set_cover') {
        if ($id <= 0) respond(['success' => false, 'message' => 'A valid id is required.'], 422);
        projectGalleryHandleSetCover($db, $user, $id);
    } elseif ($method === 'POST' && $action === 'delete') {
        if ($id <= 0) respond(['success' => false, 'message' => 'A valid id is required.'], 422);
        projectGalleryHandleDelete($db, $user, $id);
    } else {
        respond(['success' => false, 'message' => 'Unknown action.'], 404);
    }
} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
}
