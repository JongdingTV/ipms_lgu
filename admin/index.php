<?php
/**
 * Root Index File
 * Redirects users to either login page or dashboard based on authentication status
 */

session_start();

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // User is logged in, redirect to dashboard
    header('Location: pages/dashboard.php');
} else {
    // User is not logged in, redirect to login
    header('Location: login.php');
}
exit;
