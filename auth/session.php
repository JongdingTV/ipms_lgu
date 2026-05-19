<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

secureSessionStart();

function authIsApiRequest(): bool
{
    return strpos($_SERVER['PHP_SELF'] ?? '', '/api/') !== false;
}

function authJsonError(string $message, int $status = 401): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    exit;
}

function redirectToLogin(array $query = []): void
{
    $location = APP_LOGIN_PATH;
    if ($query !== []) {
        $location .= '?' . http_build_query($query);
    }

    if (authIsApiRequest()) {
        authJsonError($query['error'] ?? 'Authentication required', 401);
    }

    header('Location: ' . $location);
    exit;
}

function currentUser(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function isLoggedIn(): bool
{
    $role = $_SESSION['auth_user']['role'] ?? '';
    return !empty($_SESSION['auth_user']['user_id']) && is_string($role) && isValidRole($role);
}

function logoutCurrentUser(bool $logActivity = true): void
{
    if ($logActivity && !empty($_SESSION['auth_user']['user_id'])) {
        logActivity((int) $_SESSION['auth_user']['user_id'], 'logout', 'User logged out');
    }

    $_SESSION = [];

    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    session_destroy();
}

function requireLogin(array $roles = []): array
{
    if (!isLoggedIn()) {
        redirectToLogin(['error' => 'Please log in to continue.']);
    }

    $user = currentUser();
    if (!$user || !isValidRole((string) ($user['role'] ?? ''))) {
        logoutCurrentUser(false);
        redirectToLogin(['error' => 'Your session role is invalid. Please log in again.']);
    }

    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    if ($lastActivity > 0 && (time() - $lastActivity) > SESSION_TIMEOUT_SECONDS) {
        logoutCurrentUser(false);
        redirectToLogin(['timeout' => 1]);
    }

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (!empty($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $userAgent) {
        logoutCurrentUser(false);
        redirectToLogin(['error' => 'Session validation failed.']);
    }

    $_SESSION['last_activity'] = time();

    if ($roles !== [] && !in_array($user['role'], $roles, true)) {
        logActivity((int) $user['user_id'], 'unauthorized_access', 'Denied access to ' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));

        if (authIsApiRequest()) {
            authJsonError('Access denied', 403);
        }

        header('Location: ' . roleDashboardPath($user['role']));
        exit;
    }

    return $user;
}

function requireCsrfProtection(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null;
    if (!verifyCsrfToken($token)) {
        if (authIsApiRequest()) {
            authJsonError('Invalid CSRF token', 419);
        }

        redirectToLogin(['error' => 'Your session token is invalid.']);
    }
}

function logActivity(?int $userId, string $action, string $details = ''): void
{
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (Throwable $e) {
    }
}

function isLoginBlocked(string $identifier, string $ipAddress): bool
{
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE successful = 0
              AND attempted_at >= (NOW() - INTERVAL ? MINUTE)
              AND (identifier = ? OR ip_address = ?)
        ");
        $stmt->execute([LOGIN_LOCKOUT_MINUTES, $identifier, $ipAddress]);
        return (int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
    } catch (Throwable $e) {
        return false;
    }
}

function recordLoginAttempt(string $identifier, string $ipAddress, bool $successful, ?int $userId = null): void
{
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (identifier, user_id, ip_address, successful, attempted_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$identifier, $userId, $ipAddress, $successful ? 1 : 0]);
    } catch (Throwable $e) {
    }
}

function pruneOldLoginAttempts(): void
{
    try {
        getDB()->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
    } catch (Throwable $e) {
    }
}

function authenticateUser(string $identifier, string $password, ?string $selectedRole = null): array
{
    pruneOldLoginAttempts();

    $selectedRole = $selectedRole !== null ? trim($selectedRole) : null;
    if ($selectedRole !== null && $selectedRole !== '' && !isValidRole($selectedRole)) {
        return ['success' => false, 'message' => 'Please choose a valid portal.'];
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (isLoginBlocked($identifier, $ipAddress)) {
        return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT id, username, email, password_hash, full_name, role, status, last_login
        FROM users
        WHERE username = ? OR email = ?
        LIMIT 1
    ");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if ($user && !isValidRole((string) $user['role'])) {
        recordLoginAttempt($identifier, $ipAddress, false, (int) $user['id']);
        logActivity((int) $user['id'], 'login_blocked', 'Invalid account role');
        return ['success' => false, 'message' => 'Your account role is not allowed.'];
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        recordLoginAttempt($identifier, $ipAddress, false, $user ? (int) $user['id'] : null);
        logActivity($user ? (int) $user['id'] : null, 'login_failed', 'Invalid login attempt for ' . $identifier);
        return ['success' => false, 'message' => 'Invalid username/email or password.'];
    }

    if (($user['status'] ?? 'inactive') !== 'active') {
        recordLoginAttempt($identifier, $ipAddress, false, (int) $user['id']);
        logActivity((int) $user['id'], 'login_blocked', 'Inactive account login attempt');
        return ['success' => false, 'message' => 'Your account is inactive.'];
    }

    if ($selectedRole !== null && $selectedRole !== '' && $user['role'] !== $selectedRole) {
        logActivity((int) $user['id'], 'login_blocked', 'Portal role mismatch: selected ' . $selectedRole);
        return ['success' => false, 'message' => 'The selected portal does not match this account. Please choose the role assigned to your account.'];
    }

    session_regenerate_id(true);
    $_SESSION['auth_user'] = [
        'user_id' => (int) $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
    ];
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([(int) $user['id']]);

    recordLoginAttempt($identifier, $ipAddress, true, (int) $user['id']);
    logActivity((int) $user['id'], 'login_success', 'User logged in successfully');

    return ['success' => true, 'user' => $_SESSION['auth_user']];
}

function redirectToRoleDashboard(?string $role = null): void
{
    $effectiveRole = $role ?? (currentUser()['role'] ?? null);
    header('Location: ' . roleDashboardPath((string) $effectiveRole));
    exit;
}
