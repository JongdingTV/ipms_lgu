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
projectRatingsEnsureSchema($pdo);

// Same visibility gate as citizen/api/project-details.php and citizen/api/projects.php
$visibleStatuses = ['approved', 'bidding', 'awarded', 'assigned', 'active', 'delayed', 'on_hold', 'completion_inspection', 'completed', 'turnover'];
$placeholders = implode(',', array_fill(0, count($visibleStatuses), '?'));

$stmt = $pdo->prepare("
    SELECT p.id, p.project_code, p.name, p.status, p.progress, p.location,
           g.file_path AS cover_photo, g.title AS cover_title,
           r.rating_count, r.rating_average
    FROM projects p
    INNER JOIN project_gallery_photos g ON g.project_id = p.id AND g.is_cover = 1
    LEFT JOIN (
        SELECT project_id, COUNT(*) AS rating_count, AVG(rating) AS rating_average
        FROM project_ratings
        GROUP BY project_id
    ) r ON r.project_id = p.id
    WHERE p.status IN ($placeholders)
    ORDER BY p.created_at DESC
    LIMIT 20
");
$stmt->execute($visibleStatuses);

$projects = array_map(function (array $row): array {
    $row['rating_count'] = (int) ($row['rating_count'] ?? 0);
    $row['rating_average'] = $row['rating_count'] > 0 ? round((float) $row['rating_average'], 1) : null;
    return $row;
}, $stmt->fetchAll());

echo json_encode(['success' => true, 'projects' => $projects]);
