<?php
// ============================================================
// api/announcements.php — Citizen Announcements (Admin)
//
// Admin-authored public bulletin board content — events, new-project
// spotlights, official notices/posters, general updates — consumed
// read-only by citizen/api/announcements.php (published only). Mirrors
// api/calendar.php's create/delete shape (another Admin-authored,
// citizen-facing content type) plus FileUpload for the optional poster
// image, the same pattern api/projects.php's document upload uses.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/FileUpload.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin']);
requireCsrfProtection();

$db = getDB();
announcementsEnsureSchema($db);
$user = currentUser();

const ANNOUNCEMENT_CATEGORIES = ['event', 'new_project', 'notice', 'general'];
const ANNOUNCEMENT_STATUSES = ['draft', 'published', 'archived'];
const ANNOUNCEMENT_POSTER_MAX_SIZE = 5 * 1024 * 1024;
const ANNOUNCEMENT_POSTER_EXTENSIONS = ['png', 'jpg', 'jpeg'];

/** Best-effort cleanup when a poster is replaced or the announcement is deleted. */
function announcementDeletePosterFile(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }
    $full = dirname(__DIR__) . '/' . $relativePath;
    if (is_file($full)) {
        @unlink($full);
    }
}

function announcementValidateFields(array $b, ?string &$error): ?array
{
    $title = trim((string) ($b['title'] ?? ''));
    $body = trim((string) ($b['body'] ?? ''));
    if ($title === '') {
        $error = 'Title is required.';
        return null;
    }
    if ($body === '') {
        $error = 'Body is required.';
        return null;
    }

    $category = (string) ($b['category'] ?? 'general');
    if (!in_array($category, ANNOUNCEMENT_CATEGORIES, true)) {
        $error = 'Invalid category.';
        return null;
    }

    $status = (string) ($b['status'] ?? 'published');
    if (!in_array($status, ANNOUNCEMENT_STATUSES, true)) {
        $error = 'Invalid status.';
        return null;
    }

    return [
        'title' => $title,
        'body' => $body,
        'category' => $category,
        'status' => $status,
        'project_id' => !empty($b['project_id']) ? (int) $b['project_id'] : null,
        'event_date' => !empty($b['event_date']) ? $b['event_date'] : null,
        'event_time' => !empty($b['event_time']) ? $b['event_time'] : null,
        'event_location' => !empty($b['event_location']) ? trim((string) $b['event_location']) : null,
        'is_pinned' => !empty($b['is_pinned']) ? 1 : 0,
    ];
}

function announcementHandleList(PDO $db): void
{
    $where = ['1=1'];
    $params = [];

    if (!empty($_GET['status'])) {
        $where[] = 'a.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['category'])) {
        $where[] = 'a.category = ?';
        $params[] = $_GET['category'];
    }
    if (!empty($_GET['search'])) {
        $where[] = '(a.title LIKE ? OR a.body LIKE ?)';
        $s = '%' . $_GET['search'] . '%';
        array_push($params, $s, $s);
    }

    $whereSQL = implode(' AND ', $where);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $total = $db->prepare("SELECT COUNT(*) FROM announcements a WHERE $whereSQL");
    $total->execute($params);
    $totalRows = (int) $total->fetchColumn();

    $stmt = $db->prepare("
        SELECT a.*, p.name AS project_name, u.full_name AS created_by_name
        FROM announcements a
        LEFT JOIN projects p ON p.id = a.project_id
        LEFT JOIN users u ON u.id = a.created_by
        WHERE $whereSQL
        ORDER BY a.is_pinned DESC, a.created_at DESC
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

function announcementHandleDetail(PDO $db, int $id): void
{
    $stmt = $db->prepare("SELECT a.*, p.name AS project_name FROM announcements a LEFT JOIN projects p ON p.id = a.project_id WHERE a.id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        respond(['success' => false, 'message' => 'Announcement not found'], 404);
    }
    respond(['success' => true, 'announcement' => $row]);
}

function announcementHandleCreate(PDO $db, ?array $user): void
{
    $error = null;
    $fields = announcementValidateFields($_POST, $error);
    if ($fields === null) {
        respond(['success' => false, 'message' => $error], 422);
    }

    if ($fields['project_id'] !== null) {
        $check = $db->prepare('SELECT id FROM projects WHERE id = ?');
        $check->execute([$fields['project_id']]);
        if (!$check->fetchColumn()) {
            respond(['success' => false, 'message' => 'Project not found.'], 422);
        }
    }

    $posterPath = null;
    $posterOriginalName = null;
    if (!empty($_FILES['poster']['name'])) {
        try {
            $stored = FileUpload::store($_FILES['poster'], 'announcements', [
                'required' => false,
                'max_size' => ANNOUNCEMENT_POSTER_MAX_SIZE,
                'extensions' => ANNOUNCEMENT_POSTER_EXTENSIONS,
            ]);
            $posterPath = $stored['stored_path'];
            $posterOriginalName = $stored['original_name'];
        } catch (FileUploadException $e) {
            respond(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    $publishedAt = $fields['status'] === 'published' ? date('Y-m-d H:i:s') : null;
    $createdBy = (int) ($user['user_id'] ?? 0) ?: null;

    $stmt = $db->prepare('
        INSERT INTO announcements
            (title, body, category, project_id, event_date, event_time, event_location,
             poster_path, poster_original_name, is_pinned, status, published_at, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $fields['title'], $fields['body'], $fields['category'], $fields['project_id'],
        $fields['event_date'], $fields['event_time'], $fields['event_location'],
        $posterPath, $posterOriginalName, $fields['is_pinned'], $fields['status'], $publishedAt, $createdBy,
    ]);

    $newId = (int) $db->lastInsertId();
    logActivity($createdBy, 'announcement_created', $fields['title'] . ' was posted.', 'Announcements', $newId);

    respond(['success' => true, 'id' => $newId], 201);
}

function announcementHandleUpdate(PDO $db, ?array $user, int $id): void
{
    $existingStmt = $db->prepare('SELECT * FROM announcements WHERE id = ?');
    $existingStmt->execute([$id]);
    $existing = $existingStmt->fetch();
    if (!$existing) {
        respond(['success' => false, 'message' => 'Announcement not found'], 404);
    }

    $error = null;
    $fields = announcementValidateFields($_POST, $error);
    if ($fields === null) {
        respond(['success' => false, 'message' => $error], 422);
    }

    if ($fields['project_id'] !== null) {
        $check = $db->prepare('SELECT id FROM projects WHERE id = ?');
        $check->execute([$fields['project_id']]);
        if (!$check->fetchColumn()) {
            respond(['success' => false, 'message' => 'Project not found.'], 422);
        }
    }

    $posterPath = $existing['poster_path'];
    $posterOriginalName = $existing['poster_original_name'];
    $oldPosterToDelete = null;
    if (!empty($_FILES['poster']['name'])) {
        try {
            $stored = FileUpload::store($_FILES['poster'], 'announcements', [
                'required' => false,
                'max_size' => ANNOUNCEMENT_POSTER_MAX_SIZE,
                'extensions' => ANNOUNCEMENT_POSTER_EXTENSIONS,
            ]);
            $oldPosterToDelete = $posterPath;
            $posterPath = $stored['stored_path'];
            $posterOriginalName = $stored['original_name'];
        } catch (FileUploadException $e) {
            respond(['success' => false, 'message' => $e->getMessage()], 422);
        }
    } elseif (!empty($_POST['remove_poster'])) {
        $oldPosterToDelete = $posterPath;
        $posterPath = null;
        $posterOriginalName = null;
    }

    // Only stamp published_at the first time an announcement goes live —
    // re-saving an already-published one (or archiving/unarchiving it) must
    // not keep resetting its original publish date.
    $publishedAt = $existing['published_at'];
    if ($fields['status'] === 'published' && $publishedAt === null) {
        $publishedAt = date('Y-m-d H:i:s');
    }

    $db->prepare('
        UPDATE announcements SET
            title = ?, body = ?, category = ?, project_id = ?, event_date = ?, event_time = ?, event_location = ?,
            poster_path = ?, poster_original_name = ?, is_pinned = ?, status = ?, published_at = ?
        WHERE id = ?
    ')->execute([
        $fields['title'], $fields['body'], $fields['category'], $fields['project_id'],
        $fields['event_date'], $fields['event_time'], $fields['event_location'],
        $posterPath, $posterOriginalName, $fields['is_pinned'], $fields['status'], $publishedAt, $id,
    ]);

    if ($oldPosterToDelete !== null) {
        announcementDeletePosterFile($oldPosterToDelete);
    }

    logActivity((int) ($user['user_id'] ?? 0) ?: null, 'announcement_updated', $fields['title'] . ' was updated.', 'Announcements', $id);

    respond(['success' => true]);
}

function announcementHandleDelete(PDO $db, ?array $user, int $id): void
{
    $stmt = $db->prepare('SELECT title, poster_path FROM announcements WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        respond(['success' => false, 'message' => 'Announcement not found'], 404);
    }

    $db->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
    announcementDeletePosterFile($row['poster_path']);

    logActivity((int) ($user['user_id'] ?? 0) ?: null, 'announcement_deleted', $row['title'] . ' was deleted.', 'Announcements', $id);

    respond(['success' => true]);
}

// ── Dispatch ────────────────────────────────────────────────────────────
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = (string) ($_GET['action'] ?? '');
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($method === 'GET' && $action === 'list') {
        announcementHandleList($db);
    } elseif ($method === 'GET' && $action === 'detail') {
        if ($id <= 0) respond(['success' => false, 'message' => 'A valid id is required.'], 422);
        announcementHandleDetail($db, $id);
    } elseif ($method === 'POST' && $action === 'create') {
        announcementHandleCreate($db, $user);
    } elseif ($method === 'POST' && $action === 'update') {
        if ($id <= 0) respond(['success' => false, 'message' => 'A valid id is required.'], 422);
        announcementHandleUpdate($db, $user, $id);
    } elseif ($method === 'POST' && $action === 'delete') {
        if ($id <= 0) respond(['success' => false, 'message' => 'A valid id is required.'], 422);
        announcementHandleDelete($db, $user, $id);
    } else {
        respond(['success' => false, 'message' => 'Unknown action.'], 404);
    }
} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
}
