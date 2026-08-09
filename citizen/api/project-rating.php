<?php
// ============================================================
// citizen/api/project-rating.php — citizen star rating + review, own-only.
//
// One rating per (project, citizen) — action=submit is an upsert (Facebook/
// Google-review UX: leaving a review always replaces your prior one on that
// same project). citizen_id is always resolved server-side from the
// session, never accepted from the client, and no rating `id` is ever read
// from the request anywhere in this file — ownership is structural, not
// just a WHERE clause a future edit could accidentally drop. Admin's
// counterpart (api/project-ratings.php) is read-only with no mutation route
// at all.
// ============================================================
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user = requireLogin(['citizen']);
requireCsrfProtection();
$pdo = getDB();
projectRatingsEnsureSchema($pdo);

$stmt = $pdo->prepare('SELECT id, verification_status FROM citizens WHERE user_id = ?');
$stmt->execute([$user['user_id']]);
$citizen = $stmt->fetch();
$citizenId = $citizen['id'] ?? null;

if (!$citizenId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Citizen profile not found.']);
    exit;
}

if (($citizen['verification_status'] ?? 'unverified') !== 'verified') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your account must be verified before you can rate a project. Upload a valid government ID in your Profile to get verified.']);
    exit;
}

$action = (string) ($_GET['action'] ?? '');
$projectId = (int) ($_POST['project_id'] ?? 0);

$visibleStatuses = ['approved', 'bidding', 'awarded', 'assigned', 'active', 'delayed', 'on_hold', 'completion_inspection', 'completed', 'turnover'];
$placeholders = implode(',', array_fill(0, count($visibleStatuses), '?'));
$check = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND status IN ($placeholders)");
$check->execute(array_merge([$projectId], $visibleStatuses));
if ($projectId <= 0 || !$check->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Project not found.']);
    exit;
}

if ($action === 'submit') {
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim((string) ($_POST['comment'] ?? ''));

    if ($rating < 1 || $rating > 5) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Please select a star rating from 1 to 5.']);
        exit;
    }
    if (mb_strlen($comment) > 1000) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Comment must be 1000 characters or fewer.']);
        exit;
    }

    $pdo->prepare('
        INSERT INTO project_ratings (project_id, citizen_id, rating, comment)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP
    ')->execute([$projectId, $citizenId, $rating, $comment !== '' ? $comment : null]);

    logActivity((int) $user['user_id'], 'project_rating_submitted', "Rated project #$projectId $rating star(s).", 'Project Ratings', $projectId);

    echo json_encode(['success' => true, 'message' => 'Thank you for your rating!']);
    exit;
}

if ($action === 'delete') {
    $stmt = $pdo->prepare('DELETE FROM project_ratings WHERE project_id = ? AND citizen_id = ?');
    $stmt->execute([$projectId, $citizenId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No rating found to delete.']);
        exit;
    }

    logActivity((int) $user['user_id'], 'project_rating_deleted', "Deleted rating for project #$projectId.", 'Project Ratings', $projectId);

    echo json_encode(['success' => true, 'message' => 'Your rating was deleted.']);
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
