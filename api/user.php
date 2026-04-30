<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

apiHeaders();
requireAnyRole(APP_ROLES);

requireCsrfProtection();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get current user profile
            $stmt = $pdo->prepare("
                SELECT id, username, full_name, email, role, last_login, created_at
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            echo json_encode(['data' => $user]);
            break;
            
        case 'PUT':
            // Update profile or password
            parse_str(file_get_contents('php://input'), $data);
            
            if (isset($data['current_password'])) {
                // Change password
                $current = $data['current_password'];
                $new = $data['new_password'];
                
                // Verify current password
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if (!password_verify($current, $user['password_hash'])) {
                    echo json_encode(['error' => 'Current password is incorrect']);
                    break;
                }
                
                // Update password
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$newHash, $_SESSION['user_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
                
            } else {
                // Update profile
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $data['full_name'],
                    $data['email'],
                    $_SESSION['user_id']
                ]);
                
                // Update session
                $_SESSION['full_name'] = $data['full_name'];
                $_SESSION['email'] = $data['email'];
                
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
