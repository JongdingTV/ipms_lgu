<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

$user = requireLogin(['citizen']);
$pdo = getDB();
projectWorkflowEnsureProjectStatusSchema($pdo);

// Statuses citizens are allowed to see (same list as citizen/api/projects.php)
const CITIZEN_VISIBLE_STATUSES = ['approved', 'bidding', 'awarded', 'assigned', 'active', 'delayed', 'on_hold', 'completion_inspection', 'completed', 'turnover'];

$projectId = (int) ($_GET['id'] ?? 0);
if ($projectId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing project id']);
    exit;
}

$placeholders = implode(',', array_fill(0, count(CITIZEN_VISIBLE_STATUSES), '?'));
$stmt = $pdo->prepare("
    SELECT p.id, p.project_code, p.name, p.description, p.location, p.budget,
           p.start_date, p.end_date, p.progress, p.status, p.created_at,
           c.name AS contractor_name
    FROM projects p
    LEFT JOIN contractors c ON c.id = p.contractor_id
    WHERE p.id = ? AND p.status IN ($placeholders)
");
$stmt->execute(array_merge([$projectId], CITIZEN_VISIBLE_STATUSES));
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Project not found']);
    exit;
}

// Milestones (admin-managed plan)
$stmt = $pdo->prepare("SELECT title, due_date, completed FROM milestones WHERE project_id = ? ORDER BY due_date IS NULL, due_date, id");
$stmt->execute([$projectId]);
$milestones = $stmt->fetchAll();

// Latest field updates from the assigned engineer
$stmt = $pdo->prepare("
    SELECT progress_percent, status, notes, created_at
    FROM engineer_status_updates
    WHERE project_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$projectId]);
$updates = $stmt->fetchAll();

// Progress photos uploaded by the engineer
$stmt = $pdo->prepare("
    SELECT title, caption, file_path, created_at
    FROM engineer_progress_photos
    WHERE project_id = ?
    ORDER BY created_at DESC
    LIMIT 8
");
$stmt->execute([$projectId]);
$photos = $stmt->fetchAll();

// Admin-uploaded blueprint/gallery photos (separate from the engineer's
// progress photos above)
projectGalleryEnsureSchema($pdo);
$stmt = $pdo->prepare("
    SELECT id, title, caption, file_path, photo_type, is_cover, created_at
    FROM project_gallery_photos
    WHERE project_id = ?
    ORDER BY is_cover DESC, created_at DESC
");
$stmt->execute([$projectId]);
$galleryPhotos = $stmt->fetchAll();

// Star ratings: aggregate, distribution, other citizens' reviews, and the
// requesting citizen's own rating (so the frontend knows Submit vs
// Edit/Delete) — only APPROVED reviews count toward anything public; the
// citizen's own row is always visible to them regardless of its moderation
// status.
projectRatingsEnsureSchema($pdo);
$stmt = $pdo->prepare("SELECT COUNT(*) AS count, COALESCE(AVG(rating), 0) AS average FROM project_ratings WHERE project_id = ? AND status = 'approved'");
$stmt->execute([$projectId]);
$ratingRow = $stmt->fetch();
$ratingSummary = ['count' => (int) $ratingRow['count'], 'average' => round((float) $ratingRow['average'], 1)];

$distStmt = $pdo->prepare("SELECT rating, COUNT(*) AS c FROM project_ratings WHERE project_id = ? AND status = 'approved' GROUP BY rating");
$distStmt->execute([$projectId]);
$distRaw = $distStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$ratingDistribution = [];
for ($star = 5; $star >= 1; $star--) {
    $c = (int) ($distRaw[$star] ?? 0);
    $ratingDistribution[] = [
        'star' => $star,
        'count' => $c,
        'percent' => $ratingSummary['count'] > 0 ? (int) round($c / $ratingSummary['count'] * 100) : 0,
    ];
}

$ratingEligible = in_array($project['status'], projectRatingEligibleStatuses(), true);

$stmt = $pdo->prepare('SELECT id, verification_status FROM citizens WHERE user_id = ?');
$stmt->execute([$user['user_id']]);
$citizenRow = $stmt->fetch();
$citizenId = $citizenRow['id'] ?? null;
$citizenVerified = ($citizenRow['verification_status'] ?? null) === 'verified';

$ownRating = null;
if ($citizenId) {
    $ownStmt = $pdo->prepare('SELECT id, rating, comment, is_anonymous, status, decision_remarks, created_at, updated_at FROM project_ratings WHERE project_id = ? AND citizen_id = ?');
    $ownStmt->execute([$projectId, $citizenId]);
    $ownRating = $ownStmt->fetch() ?: null;
}

// Other citizens shown as "Juan D." (first name + last initial) — privacy-
// conscious, consistent with the timeline widget's role-only convention
// elsewhere on this page — unless the citizen chose to post anonymously, in
// which case the real name never leaves the server at all. Excludes the
// requester's own rating, which is already surfaced separately via
// $ownRating above.
$stmt = $pdo->prepare("
    SELECT r.id, r.rating, r.comment, r.is_anonymous, r.created_at,
           CASE WHEN r.is_anonymous = 1 THEN 'Anonymous' ELSE CONCAT(c.first_name, ' ', LEFT(c.last_name, 1), '.') END AS citizen_name
    FROM project_ratings r
    INNER JOIN citizens c ON c.id = r.citizen_id
    WHERE r.project_id = ? AND r.status = 'approved' AND r.citizen_id " . ($citizenId ? '!= ?' : 'IS NOT NULL') . "
    ORDER BY r.created_at DESC
    LIMIT 20
");
$stmt->execute($citizenId ? [$projectId, $citizenId] : [$projectId]);
$ratings = $stmt->fetchAll();

// Public procurement notice, if any
$stmt = $pdo->prepare("
    SELECT reference_no, published_at, deadline, status
    FROM bac_bid_announcements
    WHERE project_id = ? AND status <> 'draft'
    LIMIT 1
");
$stmt->execute([$projectId]);
$bidNotice = $stmt->fetch() ?: null;

// Spending summary (same data the transparency page exposes)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE project_id = ?");
$stmt->execute([$projectId]);
$totalExpenses = (float) $stmt->fetchColumn();

echo json_encode([
    'success' => true,
    'project' => $project,
    'milestones' => $milestones,
    'updates' => $updates,
    'photos' => $photos,
    'gallery_photos' => $galleryPhotos,
    'rating_summary' => $ratingSummary,
    'rating_distribution' => $ratingDistribution,
    'rating_eligible' => $ratingEligible,
    'ratings' => $ratings,
    'own_rating' => $ownRating,
    'citizen_verified' => $citizenVerified,
    'bid_notice' => $bidNotice,
    'total_expenses' => $totalExpenses,
]);
