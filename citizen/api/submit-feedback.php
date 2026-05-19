<?php
require_once __DIR__ . '/../../auth/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user = requireLogin(['citizen']);
$pdo = getDB();

// Get citizen ID
$stmt = $pdo->prepare("SELECT id FROM citizens WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$citizen = $stmt->fetch();
$citizenId = $citizen['id'] ?? null;

if (!$citizenId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Citizen profile not found']);
    exit;
}

// Validate input
$projectId = $_POST['project_id'] ?? null;
$category = $_POST['category'] ?? '';
$priority = $_POST['priority'] ?? 'medium';
$message = $_POST['message'] ?? '';

$errors = [];
if (empty($category) || !in_array($category, ['complaint', 'suggestion', 'inquiry'])) {
    $errors[] = 'Invalid category';
}
if (empty($priority) || !in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
    $errors[] = 'Invalid priority';
}
if (empty($message) || strlen($message) < 10) {
    $errors[] = 'Message must be at least 10 characters';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO feedback (project_id, citizen_id, message, category, priority, status)
        VALUES (?, ?, ?, ?, ?, 'open')
    ");
    $stmt->execute([$projectId ?: null, $citizenId, $message, $category, $priority]);

    echo json_encode([
        'success' => true,
        'message' => 'Feedback submitted successfully',
        'id' => $pdo->lastInsertId()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error submitting feedback']);
}
