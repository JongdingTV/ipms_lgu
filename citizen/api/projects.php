<?php
require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/workflow.php';

header('Content-Type: application/json');

$user = requireLogin(['citizen']);
$pdo = getDB();
projectWorkflowEnsureProjectStatusSchema($pdo);

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$all = $_GET['all'] ?? '';

$query = "SELECT id, project_code, name, description, location, budget, start_date, end_date, progress, status, created_at FROM projects WHERE 1=1";
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
