<?php
// ============================================================
// api/notifications.php — per-user notification feed, shared across all
// 6 roles (no role restriction beyond being logged in — everyone gets
// their own notifications).
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/Notifications.php';
require_once __DIR__ . '/../includes/Pagination.php';

apiHeaders();
requireCsrfProtection();

$db = getDB();
Notifications::ensureTable();

$userId = (int) (currentUser()['user_id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $unreadOnly = !empty($_GET['unread_only']);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 10)));

    $where = 'user_id = ?';
    $params = [$userId];
    if ($unreadOnly) {
        $where .= ' AND is_read = 0';
    }

    $result = paginate(
        $db,
        "SELECT id, type, title, message, link, is_read, created_at FROM notifications WHERE $where ORDER BY created_at DESC",
        "SELECT COUNT(*) FROM notifications WHERE $where",
        $params,
        $page,
        $perPage
    );

    $result['unread_count'] = Notifications::unreadCount($userId);

    respond($result);
}

if ($method !== 'POST') {
    respond(['error' => 'Method not allowed.'], 405);
}

$action = $_GET['action'] ?? '';
$body = requestBody();

if ($action === 'mark_read') {
    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        respond(['error' => 'Notification id is required.'], 422);
    }

    $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);

    respond(['success' => true, 'unread_count' => Notifications::unreadCount($userId)]);
}

if ($action === 'mark_all_read') {
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$userId]);
    respond(['success' => true, 'unread_count' => 0]);
}

respond(['error' => 'Unknown action.'], 404);
