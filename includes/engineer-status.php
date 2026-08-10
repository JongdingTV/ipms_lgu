<?php
// ============================================================
// includes/engineer-status.php — shared schema + query helpers for the
// Engineer Live Status widget (floating panel on Admin/Head Office) and
// the scoped-down "Assigned Engineer" block on the Contractor portal.
//
// Two independent axes, per the feature spec:
//   - presence: derived automatically from users.last_seen_at, a heartbeat
//     column pinged every 45s by assets/js/engineer-heartbeat.js while an
//     engineer session is open (see includes/topbar.php).
//   - work_status: set manually by the engineer (assets/js/engineer.js'
//     "My Field Status" control in the topbar user-menu) — there is no
//     GPS/automatic derivation, and the inspections table has no
//     in-progress state to derive it from.
// ============================================================

const ENGINEER_STATUS_WORK_STATUSES = ['available', 'on_site', 'on_inspection', 'field_assignment', 'busy', 'off_duty'];
const ENGINEER_STATUS_PROJECT_REQUIRED = ['on_site', 'on_inspection', 'field_assignment'];

if (!function_exists('engineerStatusEnsureSchema')) {
    function engineerStatusEnsureSchema(PDO $db): void
    {
        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen_at DATETIME NULL AFTER last_login");

        $db->exec("
            CREATE TABLE IF NOT EXISTS engineer_work_status (
              engineer_id INT NOT NULL PRIMARY KEY,
              work_status ENUM('available','on_site','on_inspection','field_assignment','busy','off_duty') NOT NULL DEFAULT 'available',
              project_id INT NULL,
              activity VARCHAR(150) NULL,
              started_at DATETIME NULL,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              CONSTRAINT fk_eng_work_status_engineer FOREIGN KEY (engineer_id) REFERENCES users(id) ON DELETE CASCADE,
              CONSTRAINT fk_eng_work_status_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

// Online window (90s) gives a 2x-poll-interval buffer over the 45s heartbeat.
// Away window (3min) matches assets/js/session-guard.js's staff idle-logout
// threshold — past that point the session dies anyway, so "offline" is the
// only honest state left.
if (!function_exists('engineerStatusPresence')) {
    function engineerStatusPresence(?string $lastSeenAt): string
    {
        if (!$lastSeenAt) {
            return 'offline';
        }
        $ageSeconds = time() - strtotime($lastSeenAt);
        if ($ageSeconds <= 90) {
            return 'online';
        }
        if ($ageSeconds <= 180) {
            return 'away';
        }
        return 'offline';
    }
}

/**
 * Full engineer roster for the Admin/Head Office widget — system-wide, not
 * district-filtered (admin/hope have citywide oversight). Each engineer's
 * "project" is whichever one they explicitly attached to their current
 * work_status (engineer_work_status.project_id), not just their most recent
 * assignment — an engineer can be actively assigned to a project without
 * that being what they're doing *right now*.
 */
if (!function_exists('engineerStatusFetchList')) {
    function engineerStatusFetchList(PDO $db): array
    {
        $stmt = $db->query("
            SELECT
                u.id, u.full_name, u.last_seen_at,
                COALESCE(ws.work_status, 'available') AS work_status,
                ws.activity, ws.started_at, ws.updated_at AS status_updated_at,
                p.id AS project_id, p.project_code, p.name AS project_name,
                p.district AS project_district, p.location AS project_location,
                p.category AS project_category,
                (SELECT MAX(a.assigned_at) FROM engineer_project_assignments a
                    WHERE a.engineer_id = u.id AND a.status = 'active'
                      AND a.assigned_at > (NOW() - INTERVAL 24 HOUR)) AS recent_assignment_at,
                (SELECT COUNT(*) FROM engineer_delay_reports d
                    WHERE d.engineer_id = u.id AND d.status = 'submitted'
                      AND d.created_at > (NOW() - INTERVAL 24 HOUR)) AS recent_delay_reports
            FROM users u
            LEFT JOIN engineer_work_status ws ON ws.engineer_id = u.id
            LEFT JOIN projects p ON p.id = ws.project_id
            WHERE u.role = 'engineer'
            ORDER BY u.full_name ASC
        ");

        $rows = $stmt->fetchAll();
        $engineers = [];
        foreach ($rows as $row) {
            $engineers[] = engineerStatusFormatRow($row);
        }

        return $engineers;
    }
}

if (!function_exists('engineerStatusFetchOne')) {
    function engineerStatusFetchOne(PDO $db, int $engineerId): ?array
    {
        $stmt = $db->prepare("
            SELECT
                u.id, u.full_name, u.last_seen_at,
                COALESCE(ws.work_status, 'available') AS work_status,
                ws.activity, ws.started_at, ws.updated_at AS status_updated_at,
                p.id AS project_id, p.project_code, p.name AS project_name,
                p.district AS project_district, p.location AS project_location,
                p.category AS project_category,
                NULL AS recent_assignment_at, 0 AS recent_delay_reports
            FROM users u
            LEFT JOIN engineer_work_status ws ON ws.engineer_id = u.id
            LEFT JOIN projects p ON p.id = ws.project_id
            WHERE u.id = ? AND u.role = 'engineer'
            LIMIT 1
        ");
        $stmt->execute([$engineerId]);
        $row = $stmt->fetch();

        return $row ? engineerStatusFormatRow($row) : null;
    }
}

if (!function_exists('engineerStatusFormatRow')) {
    function engineerStatusFormatRow(array $row): array
    {
        $reasons = [];
        if (!empty($row['recent_assignment_at'])) {
            $reasons[] = 'New assignment';
        }
        if (in_array($row['work_status'], ['busy', 'off_duty'], true)
            && !empty($row['status_updated_at'])
            && (time() - strtotime($row['status_updated_at'])) <= 7200
        ) {
            $reasons[] = 'Marked unavailable';
        }
        if ((int) ($row['recent_delay_reports'] ?? 0) > 0) {
            $reasons[] = 'Delay reported';
        }

        return [
            'id' => (int) $row['id'],
            'name' => $row['full_name'],
            'initial' => strtoupper(substr((string) $row['full_name'], 0, 1)),
            'presence' => engineerStatusPresence($row['last_seen_at']),
            'work_status' => $row['work_status'],
            'activity' => $row['activity'],
            'started_at' => $row['started_at'],
            'project' => $row['project_id'] ? [
                'id' => (int) $row['project_id'],
                'code' => $row['project_code'],
                'name' => $row['project_name'],
                'district' => $row['project_district'],
                'location' => $row['project_location'],
                'category' => $row['project_category'],
            ] : null,
            'needs_attention' => $reasons !== [],
            'attention_reasons' => $reasons,
        ];
    }
}

/** Summary counts across both axes, for the widget panel header. */
if (!function_exists('engineerStatusSummarize')) {
    function engineerStatusSummarize(array $engineers): array
    {
        $summary = [
            'online' => 0, 'away' => 0, 'offline' => 0,
            'available' => 0, 'on_site' => 0, 'on_inspection' => 0,
            'field_assignment' => 0, 'busy' => 0, 'off_duty' => 0,
        ];
        foreach ($engineers as $e) {
            $summary[$e['presence']]++;
            $summary[$e['work_status']]++;
        }
        return $summary;
    }
}
