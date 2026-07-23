<?php
/**
 * SSO consumer: accepts a signed token from Main LGU (infragovservices.com hub)
 * and establishes a real session via establishUserSession() so it behaves
 * identically to a normal password login (auth/session.php:46).
 */
require_once __DIR__ . '/session.php';

function ssoReject(string $message): void
{
    http_response_code(403);
    exit('SSO error: ' . $message);
}

$token = $_GET['sso_token'] ?? '';
$parts = explode('.', $token, 2);
if (count($parts) !== 2) {
    ssoReject('malformed token');
}
[$payloadPart, $signaturePart] = $parts;

$expectedSig = rtrim(strtr(base64_encode(hash_hmac('sha256', $payloadPart, SSO_SHARED_SECRET, true)), '+/', '-_'), '=');
if (!hash_equals($expectedSig, $signaturePart)) {
    ssoReject('invalid signature');
}

$payload = json_decode(base64_decode(strtr($payloadPart, '-_', '+/')), true);
if (!is_array($payload)) {
    ssoReject('invalid payload');
}
if (($payload['target'] ?? '') !== 'ipms') {
    ssoReject('token not issued for this system');
}
if (!isset($payload['exp']) || time() > $payload['exp']) {
    ssoReject('token expired');
}

$db = getDB();
$db->exec("CREATE TABLE IF NOT EXISTS sso_used_tokens (
    nonce VARCHAR(64) PRIMARY KEY,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try {
    $db->prepare('INSERT INTO sso_used_tokens (nonce) VALUES (?)')->execute([$payload['nonce'] ?? '']);
} catch (PDOException $e) {
    ssoReject('token already used');
}

$email = $payload['email'] ?? '';
$fullName = $payload['full_name'] ?? 'Super Admin';

$stmt = $db->prepare('SELECT id, username, email, full_name, role FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    $username = 'sso_' . substr(md5($email), 0, 10);
    $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $insert = $db->prepare('INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, ?, ?)');
    $insert->execute([$username, $email, $passwordHash, $fullName, 'super_admin', 'active']);

    $user = [
        'id' => (int) $db->lastInsertId(),
        'username' => $username,
        'email' => $email,
        'full_name' => $fullName,
        'role' => 'super_admin',
    ];
}

establishUserSession($user);
$_SESSION['sso_from_mainlgu'] = true;

header('Location: ' . appUrl(ROLE_DASHBOARD_PATHS[$user['role']] ?? '/superadmin/dashboard.php'));
exit;
