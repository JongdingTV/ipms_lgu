<?php
require_once __DIR__ . '/../../auth/session.php';

header('Content-Type: application/json');

$user = requireLogin(['citizen']);
$pdo = getDB();

// Get citizen ID
$stmt = $pdo->prepare("SELECT id FROM citizens WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$citizen = $stmt->fetch();
$citizenId = $citizen['id'] ?? null;

if (!$citizenId) {
    echo json_encode(['feedback' => []]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT f.id, f.project_id, f.message, f.category, f.priority, f.district, f.barangay,
           f.latitude, f.longitude, f.status, f.created_at,
           p.name as project_name
    FROM feedback f
    LEFT JOIN projects p ON f.project_id = p.id
    WHERE f.citizen_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$citizenId]);
$feedback = $stmt->fetchAll();

// Attach proof photos in one query, grouped by feedback id
if ($feedback) {
    $ids = array_column($feedback, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $photoStmt = $pdo->prepare("SELECT feedback_id, photo_path FROM feedback_photos WHERE feedback_id IN ($placeholders) ORDER BY id");
    $photoStmt->execute($ids);

    $photosByFeedback = [];
    foreach ($photoStmt->fetchAll() as $row) {
        $photosByFeedback[$row['feedback_id']][] = $row['photo_path'];
    }
    foreach ($feedback as &$item) {
        $item['photos'] = $photosByFeedback[$item['id']] ?? [];
    }
    unset($item);
}

echo json_encode(['feedback' => $feedback]);
