<?php
/**
 * Citizen Voting API
 * Allows citizens to vote on project urgency and view voting results
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/CitizenVotingManager.php';

requireLogin();

$user = currentUser();

try {
    // Only citizens can vote
    if ($user['role'] !== 'citizen') {
        throw new Exception('Only citizens can vote on projects');
    }
    
    $action = $_GET['action'] ?? 'vote';
    $votingManager = new CitizenVotingManager();
    
    // Get citizen ID
    $pdo = $GLOBALS['pdo'];
    $citizen_stmt = $pdo->prepare('SELECT id FROM citizens WHERE user_id = ?');
    $citizen_stmt->execute([$user['id']]);
    $citizen = $citizen_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$citizen) {
        throw new Exception('Citizen profile not found');
    }
    
    if ($action === 'vote') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('POST method required');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $project_id = $data['project_id'] ?? null;
        $urgency_score = $data['urgency_score'] ?? null;
        $comment = $data['comment'] ?? '';
        
        if (!$project_id || $urgency_score === null) {
            throw new Exception('Missing required fields: project_id, urgency_score');
        }
        
        // Verify project exists and is approved
        $proj_check = $pdo->prepare('
            SELECT id FROM projects 
            WHERE id = ? AND approval_status = "approved_for_implementation"
        ');
        $proj_check->execute([$project_id]);
        if (!$proj_check->fetch()) {
            throw new Exception('Project not found or not approved');
        }
        
        $result = $votingManager->submitVote($project_id, $citizen['id'], $urgency_score, $comment);
        
        if ($result['success']) {
            authJsonResponse([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'project_id' => $project_id,
                    'urgency_score' => $urgency_score,
                    'voted_at' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            throw new Exception($result['message']);
        }
    }
    elseif ($action === 'get_vote') {
        $project_id = $_GET['project_id'] ?? null;
        
        if (!$project_id) {
            throw new Exception('project_id parameter required');
        }
        
        $vote = $votingManager->getCitizenVote($project_id, $citizen['id']);
        
        authJsonResponse([
            'success' => true,
            'data' => $vote ?? null
        ]);
    }
    elseif ($action === 'project_votes') {
        $project_id = $_GET['project_id'] ?? null;
        
        if (!$project_id) {
            throw new Exception('project_id parameter required');
        }
        
        $votes = $votingManager->getProjectVotes($project_id);
        
        authJsonResponse([
            'success' => true,
            'data' => $votes
        ]);
    }
    elseif ($action === 'priority_ranking') {
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        
        $ranking = $votingManager->getPriorityRankingList($limit);
        
        authJsonResponse([
            'success' => true,
            'data' => $ranking,
            'message' => 'Projects ranked by priority score (based on community votes, urgency, and budget feasibility)'
        ]);
    }
    elseif ($action === 'citizen_votes') {
        // Get all votes submitted by this citizen
        $stmt = $pdo->prepare('
            SELECT 
                cpv.id,
                p.id as project_id,
                p.project_code,
                p.name,
                cpv.urgency_score,
                cpv.vote_comment,
                cpv.voted_at,
                cpv.updated_at
            FROM citizen_project_votes cpv
            JOIN projects p ON cpv.project_id = p.id
            WHERE cpv.citizen_id = ?
            ORDER BY cpv.voted_at DESC
        ');
        
        $stmt->execute([$citizen['id']]);
        
        authJsonResponse([
            'success' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }
    else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    authJsonError('Error: ' . $e->getMessage(), 400);
}

function authJsonResponse($data) {
    http_response_code(200);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function authJsonError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
