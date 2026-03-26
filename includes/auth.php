<?php
// Auth middleware - include this at the top of protected pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Optional: Check if session has expired (30 minutes of inactivity)
$timeout_duration = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Function to check user role
function hasRole($required_role) {
    $role_hierarchy = ['viewer' => 1, 'staff' => 2, 'manager' => 3, 'admin' => 4];
    $user_level = $role_hierarchy[$_SESSION['role']] ?? 0;
    $required_level = $role_hierarchy[$required_role] ?? 999;
    return $user_level >= $required_level;
}

// Function to require specific role
function requireRole($required_role) {
    if (!hasRole($required_role)) {
        http_response_code(403);
        die('Access denied. Insufficient permissions.');
    }
}
