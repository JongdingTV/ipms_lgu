<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

$user = requireLogin(['citizen']);
$pdo = getDB();
projectWorkflowEnsureProjectStatusSchema($pdo);
projectRatingsEnsureSchema($pdo);
projectGalleryEnsureSchema($pdo);

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$all = $_GET['all'] ?? '';

// cover_photo prefers the admin-curated gallery's cover shot; falls back to
// the engineer's most recent field photo so a pin still has something to
// show even for projects nobody's set a cover photo for yet (map pin
// hover-preview design — see assets/js/project-map.js).
$query = "
    SELECT p.id, p.project_code, p.name, p.description, p.location, p.budget, p.start_date, p.end_date,
           p.progress, p.status, p.latitude, p.longitude, p.created_at,
           COALESCE(
               (SELECT file_path FROM project_gallery_photos WHERE project_id = p.id AND is_cover = 1 LIMIT 1),
               (SELECT file_path FROM engineer_progress_photos WHERE project_id = p.id ORDER BY created_at DESC LIMIT 1)
           ) AS cover_photo,
           r.rating_count, r.rating_average
    FROM projects p
    LEFT JOIN (
        SELECT project_id, COUNT(*) AS rating_count, ROUND(AVG(rating), 1) AS rating_average
        FROM project_ratings WHERE status = 'approved' GROUP BY project_id
    ) r ON r.project_id = p.id
    WHERE 1=1
";
$params = [];

if ($all !== '1') {
    $query .= " AND status IN ('approved','bidding','awarded','assigned','active','delayed','on_hold','completion_inspection','completed','turnover')";
}

if ($search) {
    $query .= " AND (name LIKE ? OR location LIKE ? OR description LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($status) {
    $query .= " AND status = ?";
    $params[] = $status;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$projects = $stmt->fetchAll();

echo json_encode(['projects' => $projects]);
