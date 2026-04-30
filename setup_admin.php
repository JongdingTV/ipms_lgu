<?php
/**
 * Local setup helper to fix the admin password hash
 * Visit this at: http://localhost/setup_admin.php
 */

require_once 'includes/db.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? 'admin123';
    $username = $_POST['username'] ?? 'admin';
    
    // Generate correct password hash
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    
    try {
        // Update the admin user
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password_hash = ?, username = ?, role = 'admin', status = 'active'
            WHERE id = 1
        ");
        $stmt->execute([$password_hash, $username]);
        
        $message = "✓ Admin credentials updated successfully!";
        $success = true;
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Setup</title>
    <style>
        body { font-family: Arial; margin: 50px; }
        .container { max-width: 400px; margin: 0 auto; }
        input { width: 100%; padding: 8px; margin: 10px 0; box-sizing: border-box; }
        button { padding: 10px; background: #667eea; color: white; border: none; cursor: pointer; width: 100%; }
        .message { margin: 20px 0; padding: 10px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Admin Password Reset</h2>
        <?php if ($message): ?>
            <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="admin" required>
            
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" value="admin123" required>
            
            <button type="submit">Update Admin Credentials</button>
        </form>
        
        <?php if ($success): ?>
            <p><strong>Now try logging in with:</strong></p>
            <p>Username: <code>admin</code></p>
            <p>Password: <code>admin123</code></p>
            <p>Then delete this file or keep it unavailable outside localhost.</p>
        <?php endif; ?>
    </div>
</body>
</html>
