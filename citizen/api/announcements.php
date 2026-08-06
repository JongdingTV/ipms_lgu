<?php
// ============================================================
// citizen/api/announcements.php — Announcements (read-only)
//
// Mirrors citizen/api/project-status.php's shape: one GET, no filters
// server-side (the citizen-side list-control filters/searches/paginates
// client-side, same as Projects/Track Feedback). Only ever returns
// status='published' rows — drafts and archived announcements are never
// exposed here, only via api/announcements.php's Admin-gated actions.
// ============================================================
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

$user = requireLogin(['citizen']);
$pdo = getDB();
announcementsEnsureSchema($pdo);

$stmt = $pdo->prepare("
    SELECT a.id, a.title, a.body, a.category, a.project_id, a.event_date, a.event_time, a.event_location,
           a.poster_path, a.is_pinned, a.published_at, a.created_at,
           p.name AS project_name, p.project_code
    FROM announcements a
    LEFT JOIN projects p ON p.id = a.project_id
    WHERE a.status = 'published'
    ORDER BY a.is_pinned DESC, COALESCE(a.published_at, a.created_at) DESC
");
$stmt->execute();

echo json_encode(['announcements' => $stmt->fetchAll()]);
