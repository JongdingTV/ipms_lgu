<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

apiHeaders();
requireAnyRole(APP_ROLES);

requireCsrfProtection();

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
$userId = (int) (currentUser()['user_id'] ?? 0);

try {
    switch ($method) {
        case 'GET':
            $stmt = $db->prepare("
                SELECT id, username, full_name, email, role, last_login, created_at
                FROM users
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            respond(['data' => $stmt->fetch()]);

        case 'PUT':
            parse_str(file_get_contents('php://input'), $data);

            if (isset($data['current_password'])) {
                $current = $data['current_password'];
                $new = $data['new_password'] ?? '';

                $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($current, $user['password_hash'])) {
                    respond(['error' => 'Current password is incorrect'], 422);
                }

                $newHash = password_hash($new, PASSWORD_BCRYPT);
                $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                   ->execute([$newHash, $userId]);

                respond(['success' => true, 'message' => 'Password updated successfully']);
            }

            $db->prepare("
                UPDATE users
                SET full_name = ?, email = ?
                WHERE id = ?
            ")->execute([
                $data['full_name'] ?? '',
                $data['email'] ?? '',
                $userId,
            ]);

            $_SESSION['auth_user']['full_name'] = $data['full_name'] ?? '';
            $_SESSION['auth_user']['email'] = $data['email'] ?? '';
            $_SESSION['full_name'] = $data['full_name'] ?? '';
            $_SESSION['email'] = $data['email'] ?? '';

            respond(['success' => true, 'message' => 'Profile updated successfully']);

        default:
            respond(['error' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    error_log('api/user.php error: ' . $e->getMessage());
    respond(['error' => 'Server error. Please try again later.'], 500);
}
