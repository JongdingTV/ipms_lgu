<?php
/**
 * Engineer Qualification & Management API
 * Handles engineer verification, qualifications, and credibility scoring
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/EngineerQualificationManager.php';

requireLogin();

$user = currentUser();

// Only admins and super admins can manage engineer qualifications
if (!in_array($user['role'], ['super_admin', 'admin'])) {
    authJsonError('Only administrators can manage engineer qualifications', 403);
}

try {
    $action = $_GET['action'] ?? 'list_verified';
    $engineerManager = new EngineerQualificationManager();
    
    if ($action === 'add_qualifications') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('POST method required');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $engineer_id = $data['engineer_id'] ?? null;
        
        if (!$engineer_id) {
            throw new Exception('engineer_id required');
        }
        
        $result = $engineerManager->setQualifications($engineer_id, $data);
        
        authJsonResponse([
            'success' => $result['success'],
            'message' => $result['message']
        ]);
    }
    elseif ($action === 'verify') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('POST method required');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $engineer_id = $data['engineer_id'] ?? null;
        $is_verified = $data['verified'] ?? true;
        
        if (!$engineer_id) {
            throw new Exception('engineer_id required');
        }
        
        $result = $engineerManager->verifyQualifications($engineer_id, $user['id'], $is_verified);
        
        authJsonResponse([
            'success' => $result['success'],
            'message' => $result['message']
        ]);
    }
    elseif ($action === 'update_credibility') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('POST method required');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $engineer_id = $data['engineer_id'] ?? null;
        $score = $data['score'] ?? null;
        
        if (!$engineer_id || $score === null) {
            throw new Exception('engineer_id and score required');
        }
        
        $result = $engineerManager->updateCredibilityScore($engineer_id, $score);
        
        authJsonResponse([
            'success' => $result['success'],
            'message' => $result['message'] ?? 'Credibility score updated',
            'data' => $result
        ]);
    }
    elseif ($action === 'get_qualifications') {
        $engineer_id = $_GET['engineer_id'] ?? null;
        
        if (!$engineer_id) {
            throw new Exception('engineer_id parameter required');
        }
        
        $qualifications = $engineerManager->getQualifications($engineer_id);
        
        authJsonResponse([
            'success' => true,
            'data' => $qualifications
        ]);
    }
    elseif ($action === 'list_verified') {
        $specialization = $_GET['specialization'] ?? null;
        
        $engineers = $engineerManager->getVerifiedEngineers($specialization);
        
        authJsonResponse([
            'success' => true,
            'specialization' => $specialization ?? 'All',
            'data' => $engineers,
            'count' => count($engineers)
        ]);
    }
    elseif ($action === 'performance_score') {
        $engineer_id = $_GET['engineer_id'] ?? null;
        
        if (!$engineer_id) {
            throw new Exception('engineer_id parameter required');
        }
        
        $performance = $engineerManager->calculatePerformanceScore($engineer_id);
        
        authJsonResponse([
            'success' => true,
            'engineer_id' => $engineer_id,
            'performance_score' => $performance,
            'message' => 'Performance score calculated based on project completion rates'
        ]);
    }
    elseif ($action === 'license_validity') {
        $engineer_id = $_GET['engineer_id'] ?? null;
        
        if (!$engineer_id) {
            throw new Exception('engineer_id parameter required');
        }
        
        $is_valid = $engineerManager->isLicenseValid($engineer_id);
        
        authJsonResponse([
            'success' => true,
            'engineer_id' => $engineer_id,
            'license_valid' => $is_valid
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
