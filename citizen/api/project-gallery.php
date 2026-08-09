<?php
// ============================================================
// citizen/api/project-gallery.php — dashboard slideshow data.
//
// A new, small dedicated read endpoint rather than an extension of
// projects.php/project-status.php — this needs an INNER JOIN on
// is_cover=1 that only returns projects that actually have a cover photo,
// a fundamentally different shape from those generic list endpoints. A
// project with no gallery photos simply contributes no slide; its other
// gallery photos are still shown inside the project detail modal via
// citizen/api/project-details.php.
// ============================================================
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

$user = requireLogin(['citizen']);
$pdo = getDB();
projectGalleryEnsureSchema($pdo);

// Same visibility gate as citizen/api/project-details.php and citizen/api/projects.php
$visibleStatuses = ['approved', 'bidding', 'awarded', 'assigned', 'active', 'delayed', 'on_hold', 'completion_inspection', 'completed', 'turnover'];
$placeholders = implode(',', array_fill(0, count($visibleStatuses), '?'));

$stmt = $pdo->prepare("
    SELECT p.id, p.project_code, p.name, p.status, p.progress, p.location,
           g.file_path AS cover_photo, g.title AS cover_title
    FROM projects p
    INNER JOIN project_gallery_photos g ON g.project_id = p.id AND g.is_cover = 1
    WHERE p.status IN ($placeholders)
    ORDER BY p.created_at DESC
    LIMIT 20
");
$stmt->execute($visibleStatuses);

echo json_encode(['success' => true, 'projects' => $stmt->fetchAll()]);
