<?php
// ============================================================
// superadmin/api/portal.php - platform governance API
// User & role management, audit trail, login security, system health, settings.
// Stricter than every other role's portal.php: super_admin only.
//
// Account/contractor/engineer provisioning and document review live in the
// sibling superadmin/api/accounts.php (multipart form handling is a
// structurally different shape from this file's pure-JSON actions).
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Validator.php';
require_once __DIR__ . '/../../includes/Pagination.php';
require_once __DIR__ . '/../../includes/Settings.php';
require_once __DIR__ . '/../../includes/workflow.php';
require_once __DIR__ . '/../../citizen/includes/qc-locations.php';
apiHeaders();
requireAnyRole(['super_admin']);
requireCsrfProtection();

$db = getDB();
citizenAiVerificationEnsureSchema($db);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? 'summary';
$user = currentUser();
$actorId = (int) ($user['user_id'] ?? 0);

function superadminActiveSuperAdminCount(PDO $db, int $excludingUserId = 0): int
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND status = 'active' AND id != ?");
    $stmt->execute([$excludingUserId]);
    return (int) $stmt->fetchColumn();
}

/** Dashboard-tab payload only: stats, health, and a short activity preview. Lists live in the list_* actions below. */
function superadminSummary(PDO $db): array
{
    $roleCounts = $db->query("SELECT role, status, COUNT(*) AS total FROM users GROUP BY role, status")->fetchAll();

    $byRole = [];
    $totalUsers = 0;
    $activeUsers = 0;
    $inactiveUsers = 0;
    foreach ($roleCounts as $row) {
        $count = (int) $row['total'];
        $totalUsers += $count;
        if ($row['status'] === 'active') {
            $activeUsers += $count;
        } else {
            $inactiveUsers += $count;
        }
        $byRole[$row['role']] = ($byRole[$row['role']] ?? 0) + $count;
    }

    $pendingVerifications = (int) $db->query(
        "SELECT COUNT(*) FROM citizens WHERE verification_status = 'unverified'"
    )->fetchColumn();

    $failedLogins24h = (int) $db->query(
        "SELECT COUNT(*) FROM login_attempts WHERE successful = 0 AND attempted_at >= (NOW() - INTERVAL 1 DAY)"
    )->fetchColumn();

    $activityPreview = $db->query("
        SELECT al.id, al.action, al.details, al.ip_address, al.created_at, u.full_name AS actor_name, u.role AS actor_role
        FROM activity_logs al
        LEFT JOIN users u ON u.id = al.user_id
        ORDER BY al.created_at DESC, al.id DESC
        LIMIT 8
    ")->fetchAll();

    $dbOk = true;
    try {
        $db->query('SELECT 1');
    } catch (Throwable $e) {
        $dbOk = false;
    }

    $healthCounts = $db->query("
        SELECT
            (SELECT COUNT(*) FROM projects) AS projects,
            (SELECT COUNT(*) FROM users) AS users,
            (SELECT COUNT(*) FROM contractors) AS contractors,
            (SELECT COUNT(*) FROM feedback) AS feedback,
            (SELECT COUNT(*) FROM expenses) AS expenses
    ")->fetch();

    $pendingDocuments = (int) $db->query("SELECT COUNT(*) FROM supporting_documents WHERE status = 'pending'")->fetchColumn();

    $uploadsPath = dirname(__DIR__, 2) . '/uploads';
    $diskFree = is_dir($uploadsPath) ? @disk_free_space($uploadsPath) : false;

    // Dashboard chart: successful vs failed logins per day, last 7 days.
    $loginTrend = $db->query("
        SELECT DATE(attempted_at) AS d,
               SUM(successful = 1) AS success,
               SUM(successful = 0) AS failed
        FROM login_attempts
        WHERE attempted_at >= (CURDATE() - INTERVAL 6 DAY)
        GROUP BY d
        ORDER BY d ASC
    ")->fetchAll();

    return [
        'stats' => [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'inactive_users' => $inactiveUsers,
            'by_role' => $byRole,
            'pending_verifications' => $pendingVerifications,
            'failed_logins_24h' => $failedLogins24h,
        ],
        'activity' => $activityPreview,
        'login_trend' => $loginTrend,
        'health' => [
            'db_ok' => $dbOk,
            'counts' => $healthCounts,
            'pending_documents' => $pendingDocuments,
            'php_version' => PHP_VERSION,
            'disk_free_bytes' => $diskFree !== false ? (int) $diskFree : null,
            'lockout_threshold' => [
                'max_attempts' => LOGIN_MAX_ATTEMPTS,
                'window_minutes' => LOGIN_LOCKOUT_MINUTES,
            ],
        ],
    ];
}

function superadminListUsers(PDO $db, int $page, int $perPage, string $search, string $role, string $status): array
{
    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[] = '(full_name LIKE ? OR username LIKE ? OR email LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }
    if ($role !== '') {
        $where[] = 'role = ?';
        $params[] = $role;
    }
    if ($status !== '') {
        $where[] = 'status = ?';
        $params[] = $status;
    }

    $whereSql = implode(' AND ', $where);
    $select = "SELECT id, username, email, full_name, role, status, district, last_login, created_at
               FROM users WHERE $whereSql ORDER BY full_name ASC, username ASC";
    $count = "SELECT COUNT(*) FROM users WHERE $whereSql";

    return paginate($db, $select, $count, $params, $page, $perPage);
}

function superadminListPendingCitizens(PDO $db, int $page, int $perPage): array
{
    // id_photo_path + ai_* included so the review modal can finally show the
    // reviewer the actual ID (previously this list — and the decision modal
    // built from it — never carried the photo at all, so a super admin could
    // click Verify/Reject without ever seeing the document) plus what the
    // registration-time AI check already found, as a starting point for the
    // human's own judgment call, not a replacement for it.
    $select = "SELECT id, user_id, first_name, last_name, email, phone, address, barangay, city, province,
                      id_type, id_number, id_photo_path, verification_status, created_at,
                      ai_verification_passed, ai_extracted_name, ai_extracted_address, ai_id_type_guess, ai_verification_notes
               FROM citizens WHERE verification_status = 'unverified' ORDER BY created_at ASC";
    $count = "SELECT COUNT(*) FROM citizens WHERE verification_status = 'unverified'";
    return paginate($db, $select, $count, [], $page, $perPage);
}

// Good-enough heuristic parse, not a full UA-parser library: the Audit Trail
// just needs a readable Browser/Device label, not exhaustive device
// detection. Order matters (Edge/Chrome UAs also contain "Safari").
function superadminParseUserAgent(?string $ua): array
{
    $ua = (string) $ua;
    if ($ua === '') {
        return ['browser' => 'Unknown', 'device' => 'Unknown'];
    }

    if (stripos($ua, 'Edg/') !== false) {
        $browser = 'Edge';
    } elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) {
        $browser = 'Opera';
    } elseif (stripos($ua, 'Chrome/') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Firefox/') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($ua, 'Safari/') !== false) {
        $browser = 'Safari';
    } else {
        $browser = 'Other';
    }

    if (stripos($ua, 'iPad') !== false || stripos($ua, 'Tablet') !== false) {
        $device = 'Tablet';
    } elseif (stripos($ua, 'Mobi') !== false || stripos($ua, 'iPhone') !== false || stripos($ua, 'Android') !== false) {
        $device = 'Mobile';
    } elseif ($ua !== '') {
        $device = 'Desktop';
    } else {
        $device = 'Unknown';
    }

    return ['browser' => $browser, 'device' => $device];
}

function superadminAuditTrailWhere(string $search, string $module, string $role, string $status, string $dateFrom, string $dateTo): array
{
    $where = ['1=1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(al.action LIKE ? OR al.details LIKE ? OR u.full_name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }
    if ($module !== '') {
        $where[] = 'al.module = ?';
        $params[] = $module;
    }
    if ($role !== '') {
        $where[] = 'u.role = ?';
        $params[] = $role;
    }
    if ($status !== '') {
        $where[] = 'al.status = ?';
        $params[] = $status;
    }
    if ($dateFrom !== '') {
        $where[] = 'al.created_at >= ?';
        $params[] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $where[] = 'al.created_at <= ?';
        $params[] = $dateTo . ' 23:59:59';
    }
    return [implode(' AND ', $where), $params];
}

function superadminAuditTrailMapRow(array $row): array
{
    $ua = superadminParseUserAgent($row['user_agent'] ?? null);
    return [
        'id' => (int) $row['id'],
        'actor_name' => $row['actor_name'] ?: 'System',
        'actor_role' => $row['actor_role'] ?: '',
        'action' => $row['action'],
        'module' => $row['module'] ?: 'General',
        'record_id' => $row['record_id'],
        'details' => $row['details'],
        'created_at' => $row['created_at'],
        'ip_address' => $row['ip_address'] ?: 'Unknown',
        'browser' => $ua['browser'],
        'device' => $ua['device'],
        'status' => $row['status'],
    ];
}

// The single source of truth for the Audit Trail page: activity_logs
// carries everything now — IP/user-agent (always did), plus module/
// record_id/status (added for this feature; see auth/session.php's
// activityLogEnsureSchema()). Every auditLog() call site (governance
// actions) writes here too via logActivity() internally, so this one
// table already has full coverage without a second query against the
// legacy audit_logs table.
function superadminListAuditTrail(PDO $db, int $page, int $perPage, string $search, string $module, string $role, string $status, string $dateFrom, string $dateTo): array
{
    [$whereSql, $params] = superadminAuditTrailWhere($search, $module, $role, $status, $dateFrom, $dateTo);
    $select = "SELECT al.id, al.action, al.module, al.record_id, al.status, al.details, al.ip_address, al.user_agent, al.created_at,
                      u.full_name AS actor_name, u.role AS actor_role
               FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id
               WHERE $whereSql ORDER BY al.created_at DESC, al.id DESC";
    $count = "SELECT COUNT(*) FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id WHERE $whereSql";
    $result = paginate($db, $select, $count, $params, $page, $perPage);
    $result['data'] = array_map('superadminAuditTrailMapRow', $result['data']);
    return $result;
}

// No pagination — CSV export needs every matching row, not one page.
// Capped at 10k as a sanity bound, not a real-world limit for this app.
function superadminExportAuditTrail(PDO $db, string $search, string $module, string $role, string $status, string $dateFrom, string $dateTo): array
{
    [$whereSql, $params] = superadminAuditTrailWhere($search, $module, $role, $status, $dateFrom, $dateTo);
    $stmt = $db->prepare("
        SELECT al.id, al.action, al.module, al.record_id, al.status, al.details, al.ip_address, al.user_agent, al.created_at,
               u.full_name AS actor_name, u.role AS actor_role
        FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id
        WHERE $whereSql ORDER BY al.created_at DESC, al.id DESC LIMIT 10000
    ");
    $stmt->execute($params);
    return array_map('superadminAuditTrailMapRow', $stmt->fetchAll());
}

/** Distinct modules actually present in activity_logs — populates the Audit Trail page's module filter. */
function superadminListAuditTrailModules(PDO $db): array
{
    return $db->query("
        SELECT DISTINCT module FROM activity_logs WHERE module IS NOT NULL AND module <> '' ORDER BY module ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
}

function superadminListLogins(PDO $db, int $page, int $perPage, string $search, string $result): array
{
    $where = ['1=1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(identifier LIKE ? OR ip_address LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like);
    }
    if ($result === 'success') {
        $where[] = 'successful = 1';
    } elseif ($result === 'failed') {
        $where[] = 'successful = 0';
    }
    $whereSql = implode(' AND ', $where);
    $select = "SELECT id, identifier, ip_address, successful, attempted_at
               FROM login_attempts WHERE $whereSql ORDER BY attempted_at DESC, id DESC";
    $count = "SELECT COUNT(*) FROM login_attempts WHERE $whereSql";
    return paginate($db, $select, $count, $params, $page, $perPage);
}

/** "At risk" — any repeated failures within the lockout window, not necessarily locked out yet. */
function superadminListLoginRisk(PDO $db, int $page, int $perPage): array
{
    $select = "
        SELECT identifier, ip_address, COUNT(*) AS failed_count, MAX(attempted_at) AS last_attempt
        FROM login_attempts
        WHERE successful = 0 AND attempted_at >= (NOW() - INTERVAL ? MINUTE)
        GROUP BY identifier, ip_address
        HAVING failed_count > 0
        ORDER BY failed_count DESC, last_attempt DESC
    ";
    $count = "
        SELECT COUNT(*) FROM (
            SELECT identifier, ip_address
            FROM login_attempts
            WHERE successful = 0 AND attempted_at >= (NOW() - INTERVAL ? MINUTE)
            GROUP BY identifier, ip_address
            HAVING COUNT(*) > 0
        ) AS t
    ";
    return paginate($db, $select, $count, [LOGIN_LOCKOUT_MINUTES], $page, $perPage);
}

/** Genuinely locked out right now (matches isLoginBlocked()'s own threshold). */
function superadminListLoginLockouts(PDO $db, int $page, int $perPage): array
{
    $select = "
        SELECT identifier, ip_address, COUNT(*) AS failed_count, MAX(attempted_at) AS last_attempt
        FROM login_attempts
        WHERE successful = 0 AND attempted_at >= (NOW() - INTERVAL ? MINUTE)
        GROUP BY identifier, ip_address
        HAVING failed_count >= ?
        ORDER BY last_attempt DESC
    ";
    $count = "
        SELECT COUNT(*) FROM (
            SELECT identifier, ip_address
            FROM login_attempts
            WHERE successful = 0 AND attempted_at >= (NOW() - INTERVAL ? MINUTE)
            GROUP BY identifier, ip_address
            HAVING COUNT(*) >= ?
        ) AS t
    ";
    return paginate($db, $select, $count, [LOGIN_LOCKOUT_MINUTES, LOGIN_MAX_ATTEMPTS], $page, $perPage);
}

if ($method === 'GET') {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));
    $search = trim((string) ($_GET['search'] ?? ''));

    if ($action === 'summary') {
        respond(superadminSummary($db));
    }

    if ($action === 'list_users') {
        respond(superadminListUsers(
            $db,
            $page,
            $perPage,
            $search,
            trim((string) ($_GET['role'] ?? '')),
            trim((string) ($_GET['status'] ?? ''))
        ));
    }

    if ($action === 'list_pending_citizens') {
        respond(superadminListPendingCitizens($db, $page, $perPage));
    }

    if ($action === 'list_audit_trail') {
        respond(superadminListAuditTrail(
            $db, $page, $perPage, $search,
            trim((string) ($_GET['module'] ?? '')),
            trim((string) ($_GET['role'] ?? '')),
            trim((string) ($_GET['status'] ?? '')),
            trim((string) ($_GET['date_from'] ?? '')),
            trim((string) ($_GET['date_to'] ?? ''))
        ));
    }

    if ($action === 'list_audit_trail_modules') {
        respond(['data' => superadminListAuditTrailModules($db)]);
    }

    if ($action === 'export_audit_trail') {
        respond(['data' => superadminExportAuditTrail(
            $db, $search,
            trim((string) ($_GET['module'] ?? '')),
            trim((string) ($_GET['role'] ?? '')),
            trim((string) ($_GET['status'] ?? '')),
            trim((string) ($_GET['date_from'] ?? '')),
            trim((string) ($_GET['date_to'] ?? ''))
        )]);
    }

    if ($action === 'list_logins') {
        respond(superadminListLogins($db, $page, $perPage, $search, trim((string) ($_GET['result'] ?? ''))));
    }

    if ($action === 'list_login_risk') {
        respond(superadminListLoginRisk($db, $page, $perPage));
    }

    if ($action === 'list_login_lockouts') {
        respond(superadminListLoginLockouts($db, $page, $perPage));
    }

    if ($action === 'get_settings') {
        respond(['settings' => Settings::all()]);
    }

    respond(['error' => 'Unknown action.'], 404);
}

if ($method !== 'POST') {
    respond(['error' => 'Method not allowed.'], 405);
}

$body = requestBody();

if ($action === 'update_status') {
    $validated = Validator::make($body, [
        'user_id' => 'required|integer',
        'status' => 'required|in:active,inactive',
    ])->stopOnFailure();

    $targetId = (int) $validated['user_id'];
    $status = (string) $validated['status'];

    if ($targetId === $actorId) {
        respond(['error' => 'You cannot change your own account status.'], 422);
    }

    $stmt = $db->prepare('SELECT id, full_name, role, status FROM users WHERE id = ?');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();
    if (!$target) {
        respond(['error' => 'User not found.'], 404);
    }

    if ($target['role'] === 'super_admin' && $status === 'inactive' && $target['status'] === 'active') {
        if (superadminActiveSuperAdminCount($db, $targetId) < 1) {
            respond(['error' => 'At least one active Super Admin account must remain.'], 422);
        }
    }

    $db->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $targetId]);

    $details = $target['full_name'] . ' account set to ' . $status . '.';
    auditLog($db, $actorId, 'user_status_updated', 'users', $targetId, $details);

    respond(['success' => true]);
}

if ($action === 'update_role') {
    $validated = Validator::make($body, [
        'user_id' => 'required|integer',
        'role' => 'required|in:' . implode(',', APP_ROLES),
    ])->stopOnFailure();

    $targetId = (int) $validated['user_id'];
    $role = (string) $validated['role'];

    $stmt = $db->prepare('SELECT id, full_name, role, status FROM users WHERE id = ?');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();
    if (!$target) {
        respond(['error' => 'User not found.'], 404);
    }

    if ($targetId === $actorId && $target['role'] === 'super_admin' && $role !== 'super_admin') {
        respond(['error' => 'You cannot remove Super Admin from your own account.'], 422);
    }

    if ($target['role'] === 'super_admin' && $role !== 'super_admin' && $target['status'] === 'active') {
        if (superadminActiveSuperAdminCount($db, $targetId) < 1) {
            respond(['error' => 'At least one active Super Admin account must remain.'], 422);
        }
    }

    $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $targetId]);

    $details = $target['full_name'] . ' role changed from ' . $target['role'] . ' to ' . $role . '.';
    auditLog($db, $actorId, 'user_role_updated', 'users', $targetId, $details);

    respond(['success' => true]);
}

if ($action === 'update_district') {
    $validated = Validator::make($body, [
        'user_id' => 'required|integer',
        'district' => 'required|in:' . implode(',', array_keys(qcDistricts())),
    ])->stopOnFailure();

    $targetId = (int) $validated['user_id'];
    $district = (string) $validated['district'];

    $stmt = $db->prepare('SELECT id, full_name, role FROM users WHERE id = ?');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();
    if (!$target) {
        respond(['error' => 'User not found.'], 404);
    }
    if ($target['role'] !== 'engineer') {
        respond(['error' => 'District only applies to Engineer accounts.'], 422);
    }

    $db->prepare('UPDATE users SET district = ? WHERE id = ?')->execute([$district, $targetId]);

    $details = $target['full_name'] . ' district set to ' . $district . '.';
    auditLog($db, $actorId, 'user_district_updated', 'users', $targetId, $details);

    respond(['success' => true]);
}

if ($action === 'reset_user_password') {
    $validated = Validator::make($body, [
        'user_id' => 'required|integer',
    ])->stopOnFailure();

    $targetId = (int) $validated['user_id'];

    $stmt = $db->prepare('SELECT id, full_name FROM users WHERE id = ?');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();
    if (!$target) {
        respond(['error' => 'User not found.'], 404);
    }

    $tempPassword = bin2hex(random_bytes(6));
    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([
        password_hash($tempPassword, PASSWORD_BCRYPT),
        $targetId,
    ]);

    $details = $target['full_name'] . '\'s password was reset by an administrator.';
    auditLog($db, $actorId, 'user_password_reset', 'users', $targetId, $details);

    respond(['success' => true, 'temp_password' => $tempPassword]);
}

if ($action === 'verify_citizen') {
    $validated = Validator::make($body, [
        'citizen_id' => 'required|integer',
        'decision' => 'required|in:verified,rejected',
        'reason' => 'nullable|string|max:500',
    ])->stopOnFailure();

    $citizenId = (int) $validated['citizen_id'];
    $decision = (string) $validated['decision'];
    $reason = trim((string) ($validated['reason'] ?? ''));

    $stmt = $db->prepare('SELECT id, first_name, last_name FROM citizens WHERE id = ?');
    $stmt->execute([$citizenId]);
    $citizen = $stmt->fetch();
    if (!$citizen) {
        respond(['error' => 'Citizen record not found.'], 404);
    }

    $db->prepare('
        UPDATE citizens
        SET verification_status = ?, verified_by = ?, verified_at = NOW(), rejection_reason = ?
        WHERE id = ?
    ')->execute([$decision, $actorId, $decision === 'rejected' && $reason !== '' ? $reason : null, $citizenId]);

    $details = trim($citizen['first_name'] . ' ' . $citizen['last_name']) . ' ID verification ' . $decision . '.';
    auditLog($db, $actorId, 'citizen_verification_updated', 'citizens', $citizenId, $details);

    respond(['success' => true]);
}

if ($action === 'update_settings') {
    $validated = Validator::make($body, [
        'site_name' => 'required|string|max:150',
        'support_email' => 'required|email',
        'session_timeout_minutes' => 'required|integer|min:5|max:1440',
        'login_max_attempts' => 'required|integer|min:3|max:20',
        'login_lockout_minutes' => 'required|integer|min:1|max:1440',
        'maintenance_mode' => 'required|in:0,1',
        'require_staff_2fa' => 'required|in:0,1',
    ])->stopOnFailure();

    $intKeys = ['session_timeout_minutes', 'login_max_attempts', 'login_lockout_minutes'];
    $boolKeys = ['maintenance_mode', 'require_staff_2fa'];
    $before = Settings::all();
    $changed = [];

    $db->beginTransaction();
    try {
        foreach ($validated as $key => $value) {
            if (in_array($key, $boolKeys, true)) {
                $value = (bool) ((int) $value);
            } elseif (in_array($key, $intKeys, true)) {
                $value = (int) $value;
            }

            if (($before[$key] ?? null) !== $value) {
                $changed[] = $key;
            }
            Settings::set($key, $value, $actorId);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        respond(['error' => 'Unable to save settings.'], 500);
    }

    $details = $changed !== [] ? 'Updated: ' . implode(', ', $changed) . '.' : 'No changes.';
    auditLog($db, $actorId, 'settings_updated', 'system_settings', null, $details);

    respond(['success' => true, 'settings' => Settings::all()]);
}

if ($action === 'unlock_login') {
    $validated = Validator::make($body, [
        'identifier' => 'nullable|string|max:190',
        'ip_address' => 'nullable|string|max:64',
    ])->stopOnFailure();

    $identifier = trim((string) ($validated['identifier'] ?? ''));
    $ipAddress = trim((string) ($validated['ip_address'] ?? ''));

    if ($identifier === '' && $ipAddress === '') {
        respond(['error' => 'An identifier or IP address is required.'], 422);
    }

    $stmt = $db->prepare('
        DELETE FROM login_attempts
        WHERE successful = 0
          AND attempted_at >= (NOW() - INTERVAL ? MINUTE)
          AND (identifier = ? OR ip_address = ?)
    ');
    $stmt->execute([LOGIN_LOCKOUT_MINUTES, $identifier, $ipAddress]);
    $cleared = $stmt->rowCount();

    $label = trim($identifier . ($ipAddress !== '' ? ' (' . $ipAddress . ')' : ''));
    $details = ($label !== '' ? $label : 'Unknown') . ' unlocked, ' . $cleared . ' failed attempt(s) cleared.';
    auditLog($db, $actorId, 'login_unlocked', 'login_attempts', null, $details);

    respond(['success' => true, 'cleared' => $cleared]);
}

respond(['error' => 'Unknown action.'], 404);
