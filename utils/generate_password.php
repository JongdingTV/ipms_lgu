<?php
/**
 * Password Hash Generator
 * 
 * Use this script to generate password hashes for creating users manually
 * Access via: http://yoursite.com/utils/generate_password.php?password=yourpassword
 * 
 * For security, DELETE THIS FILE after generating your password hashes
 */

// Uncomment the line below to disable this script
// die('Password generator is disabled. Delete this line to enable.');

$password = $_GET['password'] ?? '';

if (empty($password)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Password Hash Generator</title>
        <style>
            body { font-family: system-ui; max-width: 600px; margin: 50px auto; padding: 20px; }
            input, button { padding: 10px; font-size: 16px; }
            input { width: 300px; }
            button { background: #667eea; color: white; border: none; cursor: pointer; }
            .hash { background: #f0f0f0; padding: 15px; margin-top: 20px; word-break: break-all; }
            .warning { background: #fee; padding: 15px; margin-top: 20px; border-left: 4px solid #f00; }
        </style>
    </head>
    <body>
        <h1>Password Hash Generator</h1>
        <p>Enter a password to generate its hash for the database:</p>
        <form method="get">
            <input type="text" name="password" placeholder="Enter password" required>
            <button type="submit">Generate Hash</button>
        </form>
        
        <div class="warning">
            <strong>⚠️ SECURITY WARNING:</strong><br>
            For production use, DELETE this file after generating your password hashes.
            This tool should never be accessible on a live server.
        </div>
        
        <h3>Sample Users (password: admin123)</h3>
        <p>All the following users have the password: <code>admin123</code></p>
        <ul>
            <li><strong>admin</strong> - Full system access</li>
            <li><strong>manager</strong> - Project management access</li>
            <li><strong>staff</strong> - Standard user access</li>
            <li><strong>viewer</strong> - Read-only access</li>
        </ul>
    </body>
    </html>
    <?php
} else {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Password Hash Generated</title>
        <style>
            body { font-family: system-ui; max-width: 600px; margin: 50px auto; padding: 20px; }
            .hash { background: #f0f0f0; padding: 15px; margin-top: 20px; word-break: break-all; font-family: monospace; }
            .success { background: #efe; padding: 15px; margin-top: 20px; border-left: 4px solid #0f0; }
            button { padding: 10px 20px; background: #667eea; color: white; border: none; cursor: pointer; margin-top: 10px; }
        </style>
    </head>
    <body>
        <h1>Password Hash Generated</h1>
        
        <div class="success">
            <strong>✓ Hash generated successfully!</strong>
        </div>
        
        <p><strong>Password:</strong> <?= htmlspecialchars($password) ?></p>
        
        <div class="hash">
            <strong>Hash (copy this to your database):</strong><br><br>
            <span id="hash"><?= $hash ?></span>
        </div>
        
        <button onclick="copyHash()">Copy to Clipboard</button>
        <button onclick="window.location='?'">Generate Another</button>
        
        <h3>SQL Example:</h3>
        <div class="hash">
            INSERT INTO users (username, password_hash, full_name, email, role)<br>
            VALUES ('myuser', '<?= $hash ?>', 'Full Name', 'email@example.com', 'staff');
        </div>
        
        <script>
            function copyHash() {
                const text = document.getElementById('hash').textContent;
                navigator.clipboard.writeText(text).then(() => {
                    alert('Hash copied to clipboard!');
                });
            }
        </script>
    </body>
    </html>
    <?php
}
