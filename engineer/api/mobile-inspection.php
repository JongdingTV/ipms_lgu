<?php
// ============================================================
// engineer/api/mobile-inspection.php — Mobile Inspection wizard API.
//
// Deliberately its own file, same precedent as engineer/api/urban-planning.php
// (one feature area = one file, separate from the general engineer/api/portal.php
// which already covers 8 unrelated concerns). This is NOT a parallel inspection
// system: every action here reads/writes the same native `inspections` table
// engineer/api/portal.php's legacy action=inspection already uses, just through
// a resumable, session-based flow instead of one static desktop form.
//
// Authority model: there is no reviewer above the engineer for site
// inspections anywhere in this codebase — action=inspection_submit is final,
// exactly like the legacy handler. `status` here is pure session/workflow
// state (in_progress vs submitted); the approve/return OUTCOME stays the job
// of the existing `recommendation` column, unchanged from the legacy design.
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/scope.php';
require_once __DIR__ . '/../../includes/workflow.php';
require_once __DIR__ . '/../../includes/Validator.php';
require_once __DIR__ . '/../../includes/Pagination.php';
require_once __DIR__ . '/../../includes/Notifications.php';
require_once __DIR__ . '/../../includes/InspectionChecklist.php';

apiHeaders();
requireAnyRole(['engineer']);
requireCsrfProtection();

$db = getDB();
$engineerId = engineerScopeCurrentId();
if ($engineerId === null) {
    respond(['error' => 'Engineer account is required.'], 403);
}

engineerScopeEnsureTables($db);
engineerInspectionEnsureSchema($db);
projectWorkflowEnsureProjectStatusSchema($db);
projectWorkflowEnsureRoleConnectionTables($db);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

/** Loads an inspection this engineer owns; 404 if not theirs, 409 if already submitted. */
function miOwnedInProgressInspection(PDO $db, int $id, int $engineerId): array
{
    $stmt = $db->prepare("SELECT * FROM inspections WHERE id = ? AND engineer_id = ? LIMIT 1");
    $stmt->execute([$id, $engineerId]);
    $row = $stmt->fetch();
    if (!$row) {
        respond(['error' => 'Inspection not found.'], 404);
    }
    if ($row['status'] !== 'in_progress') {
        respond(['error' => 'This inspection has already been submitted and can no longer be edited.'], 409);
    }

    return $row;
}

if ($method === 'GET') {
    if ($action === 'project_info') {
        $projectId = (int) ($_GET['project_id'] ?? 0);
        if (!engineerScopeHasAssignedProject($db, $engineerId, $projectId)) {
            respond(['error' => 'Project not found.'], 404);
        }

        $stmt = $db->prepare("
            SELECT p.id, p.project_code, p.name, p.category, p.description, p.location,
                   p.budget, p.status, p.progress, p.start_date, p.end_date,
                   p.latitude, p.longitude, c.name AS contractor_name, u.full_name AS engineer_name
            FROM projects p
            LEFT JOIN contractors c ON c.id = p.contractor_id
            LEFT JOIN engineer_project_assignments a ON a.project_id = p.id AND a.engineer_id = ? AND a.status = 'active'
            LEFT JOIN users u ON u.id = a.engineer_id
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([$engineerId, $projectId]);
        $project = $stmt->fetch();
        if (!$project) {
            respond(['error' => 'Project not found.'], 404);
        }
        respond(['data' => $project]);
    }

    if ($action === 'checklist') {
        $projectId = (int) ($_GET['project_id'] ?? 0);
        if (!engineerScopeHasAssignedProject($db, $engineerId, $projectId)) {
            respond(['error' => 'Project not found.'], 404);
        }
        $items = inspectionChecklistForProject($db, $projectId);
        if ($items === null) {
            respond(['error' => 'Project not found.'], 404);
        }
        respond(['items' => $items, 'grouped' => inspectionChecklistGroupByCategory($items)]);
    }

    // Not-yet-started candidates (same predicate as the legacy
    // action=pending_inspections / TaskCenter's inspection_pending block, so
    // "what still needs a first visit" means the same thing everywhere),
    // bucketed by a virtual target date — DATE_ADD(report_date, INTERVAL 2 DAY)
    // is the exact aging threshold taskCenterPriorityBucket() already uses for
    // this identical predicate — plus the engineer's own resumable sessions.
    if ($action === 'my_inspections') {
        $today = date('Y-m-d');

        $stmt = $db->prepare("
            SELECT r.id AS report_id, r.project_id, r.report_date,
                   p.project_code, p.name AS project_name, p.location, p.category,
                   DATE_ADD(r.report_date, INTERVAL 2 DAY) AS target_date
            FROM engineer_project_assignments a
            INNER JOIN projects p ON p.id = a.project_id
            INNER JOIN contractor_reports r ON r.project_id = p.id
            LEFT JOIN inspections i ON i.progress_report_id = r.id
            WHERE a.engineer_id = ?
              AND a.status = 'active'
              AND (i.id IS NULL OR (i.status = 'submitted' AND r.status IN ('submitted', 'under_review')))
            ORDER BY r.report_date ASC
        ");
        $stmt->execute([$engineerId]);

        $buckets = ['overdue' => [], 'today' => [], 'upcoming' => []];
        foreach ($stmt->fetchAll() as $row) {
            $bucketKey = $row['target_date'] < $today ? 'overdue' : ($row['target_date'] === $today ? 'today' : 'upcoming');
            $priority = $bucketKey === 'overdue' ? 'urgent' : ($bucketKey === 'today' ? 'due_today' : 'upcoming');
            $buckets[$bucketKey][] = [
                'progress_report_id' => (int) $row['report_id'],
                'project_id' => (int) $row['project_id'],
                'project_code' => $row['project_code'],
                'project_name' => $row['project_name'],
                'location' => $row['location'],
                'inspection_type' => 'progress',
                'report_date' => $row['report_date'],
                'target_date' => $row['target_date'],
                'priority' => $priority,
            ];
        }

        $inProgress = $db->prepare("
            SELECT i.id, i.project_id, i.inspection_type, i.progress_report_id, i.follow_up_of_inspection_id,
                   i.inspection_date, i.inspection_time, i.draft_saved_at, i.created_at,
                   p.project_code, p.name AS project_name, p.location
            FROM inspections i
            INNER JOIN projects p ON p.id = i.project_id
            WHERE i.engineer_id = ? AND i.status = 'in_progress'
            ORDER BY COALESCE(i.draft_saved_at, i.created_at) DESC
        ");
        $inProgress->execute([$engineerId]);

        respond(['buckets' => $buckets, 'in_progress' => $inProgress->fetchAll()]);
    }

    if ($action === 'detail') {
        $id = (int) ($_GET['id'] ?? 0);
        $stmt = $db->prepare("
            SELECT i.*, p.project_code, p.name AS project_name, p.location, p.category,
                   p.description, p.budget, p.latitude, p.longitude, p.status AS project_status,
                   p.progress AS project_progress, p.start_date, p.end_date,
                   c.name AS contractor_name
            FROM inspections i
            INNER JOIN projects p ON p.id = i.project_id
            LEFT JOIN contractors c ON c.id = p.contractor_id
            WHERE i.id = ? AND i.engineer_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $engineerId]);
        $row = $stmt->fetch();
        if (!$row) {
            respond(['error' => 'Inspection not found.'], 404);
        }

        $row['checklist_answers'] = $row['checklist_answers'] ? json_decode((string) $row['checklist_answers'], true) : [];
        $row['recommendation_notes'] = $row['recommendation_notes'] ? json_decode((string) $row['recommendation_notes'], true) : [];

        $findings = $db->prepare("SELECT * FROM inspection_findings WHERE inspection_id = ? ORDER BY created_at ASC");
        $findings->execute([$id]);
        $row['itemized_findings'] = $findings->fetchAll();

        $photos = $db->prepare("SELECT * FROM engineer_progress_photos WHERE inspection_id = ? ORDER BY created_at ASC");
        $photos->execute([$id]);
        $row['photos'] = $photos->fetchAll();

        respond(['data' => $row]);
    }

    // "Inspection Review" tab — submitted inspections that are NOT sitting in
    // the Returned queue (either already approved, or a needs-correction one
    // that already has a follow-up reinspection started). Mutually exclusive
    // with action=returned by design, so no row is ever shown in both tabs.
    if ($action === 'review') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));
        $where = "
            i.engineer_id = ? AND i.status = 'submitted'
            AND (i.recommendation = 'approved' OR EXISTS (SELECT 1 FROM inspections f WHERE f.follow_up_of_inspection_id = i.id))
        ";
        $select = "
            SELECT i.*, p.project_code, p.name AS project_name, p.location
            FROM inspections i
            INNER JOIN projects p ON p.id = i.project_id
            WHERE $where
            ORDER BY i.inspection_date DESC, i.id DESC
        ";
        $count = "SELECT COUNT(*) FROM inspections i WHERE $where";
        respond(paginate($db, $select, $count, [$engineerId], $page, $perPage));
    }

    // "Returned" tab — the actionable follow-up queue: site issues that need
    // a contractor correction and, eventually, a re-visit from the engineer.
    if ($action === 'returned') {
        $stmt = $db->prepare("
            SELECT i.id, i.project_id, i.inspection_date, i.recommendation, i.findings,
                   p.project_code, p.name AS project_name, p.location
            FROM inspections i
            INNER JOIN projects p ON p.id = i.project_id
            WHERE i.engineer_id = ?
              AND i.status = 'submitted'
              AND i.recommendation IN ('needs_correction', 'for_reinspection')
              AND NOT EXISTS (SELECT 1 FROM inspections f WHERE f.follow_up_of_inspection_id = i.id)
            ORDER BY i.inspection_date DESC
        ");
        $stmt->execute([$engineerId]);
        respond(['data' => $stmt->fetchAll()]);
    }

    // "History" tab — full read-only archive, superset of Review + Returned.
    if ($action === 'history') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));
        $select = "
            SELECT i.*, p.project_code, p.name AS project_name, p.location
            FROM inspections i
            INNER JOIN projects p ON p.id = i.project_id
            WHERE i.engineer_id = ? AND i.status = 'submitted'
            ORDER BY i.inspection_date DESC, i.id DESC
        ";
        $count = "SELECT COUNT(*) FROM inspections i WHERE i.engineer_id = ? AND i.status = 'submitted'";
        respond(paginate($db, $select, $count, [$engineerId], $page, $perPage));
    }

    respond(['error' => 'Unknown action.'], 404);
}

if ($method === 'POST') {
    // Creates (or resumes) an inspection session. Race-safe via the unique
    // keys added in includes/workflow.php: idx_inspections_one_per_report for
    // 'progress' type, idx_inspections_follow_up for 'reinspection' — a
    // second Start click/tab collides into the SAME existing row instead of
    // creating a duplicate. 'special' (ad-hoc) has no natural unique key, so
    // it gets a best-effort pre-check instead (the client also disables the
    // Start button synchronously on click, covering the common double-click case).
    if ($action === 'inspection_start') {
        $body = requestBody();
        $reportId = !empty($body['progress_report_id']) ? (int) $body['progress_report_id'] : null;
        $followUpId = !empty($body['follow_up_of_inspection_id']) ? (int) $body['follow_up_of_inspection_id'] : null;
        $adHocProjectId = !empty($body['project_id']) ? (int) $body['project_id'] : null;

        if ($reportId !== null) {
            $type = 'progress';
            $report = $db->prepare("
                SELECT r.id, r.project_id
                FROM contractor_reports r
                INNER JOIN engineer_project_assignments a ON a.project_id = r.project_id
                WHERE r.id = ? AND a.engineer_id = ? AND a.status = 'active'
                LIMIT 1
            ");
            $report->execute([$reportId, $engineerId]);
            $reportRow = $report->fetch();
            if (!$reportRow) {
                respond(['error' => 'Progress report not found.'], 404);
            }
            $projectId = (int) $reportRow['project_id'];
        } elseif ($followUpId !== null) {
            $type = 'reinspection';
            $prior = $db->prepare("SELECT id, project_id, recommendation, status FROM inspections WHERE id = ? AND engineer_id = ? LIMIT 1");
            $prior->execute([$followUpId, $engineerId]);
            $priorRow = $prior->fetch();
            if (!$priorRow) {
                respond(['error' => 'Inspection not found.'], 404);
            }
            if ($priorRow['status'] !== 'submitted' || !in_array($priorRow['recommendation'], ['needs_correction', 'for_reinspection'], true)) {
                respond(['error' => 'This inspection is not awaiting reinspection.'], 422);
            }
            $projectId = (int) $priorRow['project_id'];
        } elseif ($adHocProjectId !== null) {
            $type = 'special';
            if (!engineerScopeHasAssignedProject($db, $engineerId, $adHocProjectId)) {
                respond(['error' => 'Project not found.'], 404);
            }
            $projectId = $adHocProjectId;

            $dupe = $db->prepare("
                SELECT id, status FROM inspections
                WHERE engineer_id = ? AND project_id = ? AND inspection_type = 'special' AND status = 'in_progress'
                LIMIT 1
            ");
            $dupe->execute([$engineerId, $projectId]);
            if ($existing = $dupe->fetch()) {
                respond(['success' => true, 'id' => (int) $existing['id'], 'status' => $existing['status']]);
            }
        } else {
            respond(['error' => 'A progress report, a prior inspection to follow up on, or a project is required.'], 422);
        }

        $stmt = $db->prepare("
            INSERT INTO inspections
                (project_id, progress_report_id, engineer_id, inspection_type, follow_up_of_inspection_id,
                 inspection_date, inspection_time, status, findings)
            VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME(), 'in_progress', '')
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
        ");
        $stmt->execute([$projectId, $reportId, $engineerId, $type, $followUpId]);
        $newId = (int) $db->lastInsertId();

        $statusStmt = $db->prepare("SELECT status FROM inspections WHERE id = ?");
        $statusStmt->execute([$newId]);
        $status = $statusStmt->fetchColumn();

        if ($status === 'in_progress') {
            logActivity($engineerId, 'inspection_started', 'Field inspection session started for project #' . $projectId . '.', 'Inspections', $newId);
        }

        respond(['success' => true, 'id' => $newId, 'status' => $status]);
    }

    // One generic partial-update endpoint — every field below is a scalar or
    // JSON-blob column on the same inspections row, so a client autosave (on
    // step change / explicit Save Draft) sends only what changed.
    if ($action === 'inspection_draft') {
        $body = requestBody();
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) {
            respond(['error' => 'Inspection id is required.'], 422);
        }
        miOwnedInProgressInspection($db, $id, $engineerId);

        $sets = [];
        $params = [];

        if (array_key_exists('checklist_answers', $body)) {
            $sets[] = 'checklist_answers = ?';
            $params[] = json_encode($body['checklist_answers'], JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('findings', $body)) {
            $sets[] = 'findings = ?';
            $params[] = trim((string) $body['findings']);
        }
        if (array_key_exists('recommendation_notes', $body)) {
            $notes = array_values(array_filter(array_map(
                static fn($n): string => trim((string) $n),
                (array) $body['recommendation_notes']
            ), static fn($n): bool => $n !== ''));
            $sets[] = 'recommendation_notes = ?';
            $params[] = json_encode($notes, JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('actual_progress_percent', $body)) {
            $sets[] = 'actual_progress_percent = ?';
            $params[] = max(0, min(100, (int) $body['actual_progress_percent']));
        }
        if (array_key_exists('inspection_date', $body) && $body['inspection_date'] !== '') {
            $sets[] = 'inspection_date = ?';
            $params[] = $body['inspection_date'];
        }
        if (array_key_exists('inspection_time', $body)) {
            $sets[] = 'inspection_time = ?';
            $params[] = $body['inspection_time'] !== '' ? $body['inspection_time'] : null;
        }
        if (array_key_exists('gps_latitude', $body) && array_key_exists('gps_longitude', $body)) {
            $sets[] = 'gps_latitude = ?';
            $params[] = $body['gps_latitude'] !== null ? (float) $body['gps_latitude'] : null;
            $sets[] = 'gps_longitude = ?';
            $params[] = $body['gps_longitude'] !== null ? (float) $body['gps_longitude'] : null;
            $sets[] = 'gps_accuracy_meters = ?';
            $params[] = isset($body['gps_accuracy_meters']) ? (float) $body['gps_accuracy_meters'] : null;
            $sets[] = 'gps_captured_at = NOW()';
        }

        if ($sets === []) {
            respond(['error' => 'Nothing to save.'], 422);
        }

        $sets[] = 'draft_saved_at = NOW()';
        $params[] = $id;
        $db->prepare('UPDATE inspections SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);

        logActivity($engineerId, 'inspection_draft_saved', 'Draft saved for inspection #' . $id . '.', 'Inspections', $id);

        respond(['success' => true, 'draft_saved_at' => date('Y-m-d H:i:s')]);
    }

    if ($action === 'inspection_finding_add') {
        $body = requestBody();
        $inspectionId = (int) ($body['inspection_id'] ?? 0);
        miOwnedInProgressInspection($db, $inspectionId, $engineerId);

        $validated = Validator::make($body, [
            'finding_type' => 'nullable|string|max:80',
            'description' => 'required|string|min:3',
            'severity' => 'nullable|in:low,medium,high,critical',
            'affected_area' => 'nullable|string|max:200',
            'recommended_action' => 'nullable|string|max:2000',
        ])->stopOnFailure();

        $stmt = $db->prepare("
            INSERT INTO inspection_findings (inspection_id, finding_type, description, severity, affected_area, recommended_action)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $inspectionId,
            trim((string) ($validated['finding_type'] ?? '')) ?: 'Other',
            trim((string) $validated['description']),
            (string) ($validated['severity'] ?? 'medium'),
            trim((string) ($validated['affected_area'] ?? '')) ?: null,
            trim((string) ($validated['recommended_action'] ?? '')) ?: null,
        ]);
        $newId = (int) $db->lastInsertId();

        logActivity($engineerId, 'inspection_finding_added', 'Finding added to inspection #' . $inspectionId . '.', 'Inspections', $inspectionId);

        respond(['success' => true, 'id' => $newId], 201);
    }

    if ($action === 'inspection_finding_delete') {
        $body = requestBody();
        $findingId = (int) ($body['finding_id'] ?? 0);

        $row = $db->prepare("
            SELECT f.id, f.inspection_id, i.engineer_id, i.status
            FROM inspection_findings f
            INNER JOIN inspections i ON i.id = f.inspection_id
            WHERE f.id = ?
        ");
        $row->execute([$findingId]);
        $finding = $row->fetch();
        if (!$finding || (int) $finding['engineer_id'] !== $engineerId) {
            respond(['error' => 'Finding not found.'], 404);
        }
        if ($finding['status'] !== 'in_progress') {
            respond(['error' => 'This inspection has already been submitted and can no longer be edited.'], 409);
        }

        $db->prepare("DELETE FROM inspection_findings WHERE id = ?")->execute([$findingId]);

        logActivity($engineerId, 'inspection_finding_removed', 'Finding removed from inspection #' . $finding['inspection_id'] . '.', 'Inspections', (int) $finding['inspection_id']);

        respond(['success' => true]);
    }

    // multipart/form-data (not JSON) — same photos[N][title]/[caption] +
    // photo_files[N] convention as engineer/api/portal.php's action=photo,
    // uploaded immediately per-row rather than batched until final submit:
    // deliberate, so a weak field connection loses at most one photo, not
    // the whole inspection's worth taken so far.
    if ($action === 'inspection_photo') {
        $inspectionId = (int) ($_POST['inspection_id'] ?? 0);
        $inspection = miOwnedInProgressInspection($db, $inspectionId, $engineerId);
        $projectId = (int) $inspection['project_id'];

        $photoRows = engineerCollectPhotoRows($_POST['photos'] ?? [], $_FILES['photo_files'] ?? []);
        if ($photoRows === []) {
            respond(['error' => 'At least one photo (title + file) is required.'], 422);
        }
        foreach ($photoRows as $i => $row) {
            if ($row['error'] !== null) {
                respond(['error' => 'Photo row ' . ($i + 1) . ': ' . $row['error']], 422);
            }
        }

        $storedFiles = [];
        $insertedIds = [];

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO engineer_progress_photos
                    (project_id, engineer_id, inspection_id, title, caption, file_path, original_name, file_size, mime_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($photoRows as $row) {
                $stored = FileUpload::store($row['file'], 'engineer-progress', [
                    'max_size' => ENGINEER_PHOTO_MAX_SIZE,
                    'extensions' => ENGINEER_PHOTO_EXTENSIONS,
                    'sniff_pdf' => false,
                ]);
                $storedFiles[] = $stored['stored_path'];

                $stmt->execute([
                    $projectId, $engineerId, $inspectionId,
                    $row['title'], $row['caption'] !== '' ? $row['caption'] : null,
                    $stored['stored_path'], $stored['original_name'], $stored['file_size'], $stored['mime_type'],
                ]);
                $insertedIds[] = (int) $db->lastInsertId();
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            engineerCleanupFiles($storedFiles);
            respond(['error' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to upload photos.'], 422);
        }

        logActivity($engineerId, 'inspection_photo_uploaded', count($insertedIds) . ' photo(s) uploaded for inspection #' . $inspectionId . '.', 'Inspections', $inspectionId);

        respond(['success' => true, 'ids' => $insertedIds], 201);
    }

    // Final submit — same side effects the legacy action=inspection handler
    // already has (contractor_reports flip, projects progress bump,
    // projectWorkflowLog, logActivity), plus closes a confirmed real gap: the
    // contractor is notified of the outcome, which never happened before.
    if ($action === 'inspection_submit') {
        $body = requestBody();
        $id = (int) ($body['id'] ?? 0);
        $inspection = miOwnedInProgressInspection($db, $id, $engineerId);

        $validated = Validator::make($body, [
            'actual_progress_percent' => 'required|integer|min:0|max:100',
            'recommendation' => 'required|in:approved,needs_correction,for_reinspection',
        ])->stopOnFailure();

        $findings = trim((string) ($body['findings'] ?? $inspection['findings'] ?? ''));
        if (mb_strlen($findings) < 3) {
            respond(['error' => 'A remarks / findings summary is required before submitting (at least 3 characters).'], 422);
        }

        $actualProgress = max(0, min(100, (int) $validated['actual_progress_percent']));
        $recommendation = (string) $validated['recommendation'];
        $projectId = (int) $inspection['project_id'];

        $db->beginTransaction();
        try {
            $db->prepare("
                UPDATE inspections
                SET actual_progress_percent = ?, findings = ?, recommendation = ?, status = 'submitted', submitted_at = NOW()
                WHERE id = ?
            ")->execute([$actualProgress, $findings, $recommendation, $id]);

            if ($inspection['inspection_type'] === 'progress' && $inspection['progress_report_id']) {
                $reportStatus = $recommendation === 'approved' ? 'accepted' : 'returned';
                $db->prepare("UPDATE contractor_reports SET status = ? WHERE id = ?")
                    ->execute([$reportStatus, (int) $inspection['progress_report_id']]);
            }

            if ($recommendation === 'approved') {
                $db->prepare("UPDATE projects SET progress = GREATEST(progress, ?), status = IF(status IN ('assigned','awarded'), 'active', status) WHERE id = ?")
                    ->execute([$actualProgress, $projectId]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            respond(['error' => 'Unable to submit inspection.'], 500);
        }

        $inspectionDetails = 'Site inspection recorded — ' . str_replace('_', ' ', $recommendation) . '.';
        projectWorkflowLog($db, 'Inspection recorded', $projectId, $inspectionDetails, $engineerId);
        logActivity($engineerId, 'inspection_submitted', $inspectionDetails, 'Inspections', $id);

        if ($inspection['progress_report_id']) {
            $reportRow = $db->prepare("SELECT submitted_by FROM contractor_reports WHERE id = ?");
            $reportRow->execute([(int) $inspection['progress_report_id']]);
            $submittedBy = $reportRow->fetchColumn();
            if ($submittedBy) {
                notifyUser(
                    (int) $submittedBy,
                    $recommendation === 'approved' ? 'info' : 'warning',
                    $recommendation === 'approved' ? 'Progress report accepted' : 'Progress report returned',
                    $recommendation === 'approved'
                        ? 'Your progress report was reviewed and accepted by the site engineer.'
                        : 'Your progress report was reviewed and returned — ' . $findings
                );
            }
        }

        respond(['success' => true, 'id' => $id]);
    }

    respond(['error' => 'Unknown action.'], 404);
}

respond(['error' => 'Method not allowed.'], 405);
