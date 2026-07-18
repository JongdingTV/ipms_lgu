<?php
// TEMPORARY dev helper for local screenshot testing — delete after use.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../auth/session.php';

$pdo = getDB();

// ?role=admin|super_admin|bac|engineer|contractor|hope logs in as that staff
// role's first active account and lands on its dashboard (screenshot testing
// for the staff portals). Default remains the citizen flow below.
$staffRole = $_GET['role'] ?? '';
if (in_array($staffRole, ['super_admin', 'admin', 'bac', 'engineer', 'contractor', 'hope'], true)) {
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, role FROM users WHERE role = ? AND status='active' ORDER BY id LIMIT 1");
    $stmt->execute([$staffRole]);
    $u = $stmt->fetch();
    if (!$u) exit('No ' . htmlspecialchars($staffRole) . ' user');
    establishUserSession($u);

    $staffDashboards = [
        'super_admin' => '/superadmin/dashboard.php',
        'admin' => '/admin/dashboard.php',
        'bac' => '/bac/dashboard.php',
        'engineer' => '/engineer/dashboard.php',
        'contractor' => '/contractor/dashboard.php',
        'hope' => '/hope/dashboard.php',
    ];
    $staffTarget = appUrl($staffDashboards[$staffRole]);

    // ?theme=dark seeds localStorage first (theme is client-side only).
    if (($_GET['theme'] ?? '') === 'dark') {
        $t = json_encode($staffTarget);
        echo "<script>try{localStorage.setItem('theme','dark')}catch(e){};location.replace($t);</script>";
        exit;
    }
    header('Location: ' . $staffTarget);
    exit;
}

// ?verified=1 picks a citizen whose account is verified (wizard access).
$sql = isset($_GET['verified'])
    ? "SELECT u.id, u.username, u.email, u.full_name, u.role FROM users u JOIN citizens c ON c.user_id = u.id WHERE u.role='citizen' AND u.status='active' AND c.verification_status='verified' ORDER BY u.id LIMIT 1"
    : "SELECT id, username, email, full_name, role FROM users WHERE role='citizen' AND status='active' ORDER BY id LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$u = $stmt->fetch();
if (!$u) exit('No citizen user');

establishUserSession($u);

$page = preg_replace('/[^a-z-]/', '', $_GET['page'] ?? '');
$target = appUrl('/citizen/dashboard.php') . ($page !== '' ? '#' . $page : '');

// ?theme=dark: seed localStorage before landing so headless screenshots can
// capture dark mode (the theme is client-side only).
if (($_GET['theme'] ?? '') === 'dark') {
    $t = json_encode($target);
    echo "<script>try{localStorage.setItem('theme','dark')}catch(e){};location.replace($t);</script>";
    exit;
}
header('Location: ' . $target);
exit;
