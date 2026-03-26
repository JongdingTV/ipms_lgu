<?php
session_start();

// Check if logout confirmation is confirmed
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    // Show confirmation dialog
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirm Logout</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
            }
            .modal {
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                padding: 2rem;
                max-width: 400px;
                width: 100%;
            }
            .modal-header {
                font-size: 1.5rem;
                font-weight: 700;
                color: #1e293b;
                margin-bottom: 1rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .modal-icon {
                font-size: 1.8rem;
            }
            .modal-message {
                color: #64748b;
                margin-bottom: 1.5rem;
                font-size: 0.95rem;
            }
            .modal-actions {
                display: flex;
                gap: 1rem;
            }
            .btn {
                flex: 1;
                padding: 0.75rem;
                border: none;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
                font-size: 0.95rem;
            }
            .btn-logout {
                background: #ef4444;
                color: white;
            }
            .btn-logout:hover {
                background: #dc2626;
                transform: translateY(-2px);
            }
            .btn-cancel {
                background: #e2e8f0;
                color: #334155;
            }
            .btn-cancel:hover {
                background: #cbd5e1;
                transform: translateY(-2px);
            }
        </style>
    </head>
    <body>
        <div class="modal">
            <div class="modal-header">
                <span class="modal-icon">⚠️</span>
                Confirm Logout
            </div>
            <div class="modal-message">
                Are you sure you want to log out? You will need to log in again to access the dashboard.
            </div>
            <div class="modal-actions">
                <a href="logout.php?confirm=yes" class="btn btn-logout">Yes, Log Out</a>
                <a href="index.php" class="btn btn-cancel">Cancel</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Clear all session data
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: ../admin/login.php');
exit;
