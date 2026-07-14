<?php
require_once __DIR__ . '/../../auth/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user = requireLogin(['citizen']);
requireCsrfProtection();

$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$errors = [];
if ($currentPassword === '') {
    $errors[] = 'Please enter your current password';
}
if ($newPassword === '') {
    $errors[] = 'Please enter a new password';
} else {
    // Same complexity rules as registration (citizen/register.php)
    if (strlen($newPassword) < 8) $errors[] = 'New password must be at least 8 characters long';
    if (!preg_match('/[A-Z]/', $newPassword)) $errors[] = 'New password must contain at least one uppercase letter';
    if (!preg_match('/[a-z]/', $newPassword)) $errors[] = 'New password must contain at least one lowercase letter';
    if (!preg_match('/[0-9]/', $newPassword)) $errors[] = 'New password must contain at least one number';
    if (!preg_match('/[!@#$%^&*]/', $newPassword)) $errors[] = 'New password must contain at least one special character (!@#$%^&*)';
}
if ($newPassword !== '' && $newPassword !== $confirmPassword) {
    $errors[] = 'New passwords do not match';
}
if ($newPassword !== '' && $newPassword === $currentPassword) {
    $errors[] = 'New password must be different from your current password';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([(int) $user['user_id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Your current password is incorrect']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([password_hash($newPassword, PASSWORD_BCRYPT), (int) $user['user_id']]);

    logActivity((int) $user['user_id'], 'password_changed', 'Citizen changed their password');

    echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
} catch (Throwable $e) {
    error_log('Password change failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not update your password. Please try again.']);
}
