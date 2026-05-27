<?php
/**
 * Project Approval Workflow API
 * Handles LGU management approval of projects
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ProjectApprovalManager.php';

requireLogin();

$user = currentUser();

// Only super admin and admin can approve projects
if (!in_array($user['role'], ['super_admin', 'admin'])) {
    authJsonError('Only administrators can manage project approvals', 403);
}

try {
    $action = $_GET['action'] ?? 'pending_approvals';
    $approvalManager = new ProjectApprovalManager();
    
    if ($action === 'pending_approvals') {
        // Get projects pending LGU approval
        $projects = $approvalManager->getPendingLGUApproval();
        
        authJsonResponse([
            'success' => true,
            'message' => 'Projects pending LGU management approval',
            'data' => $projects
        ]);
    }
    elseif ($action === 'approve') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('POST method required');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $project_id = $data['project_id'] ?? null;
        
        if (!$project_id) {
            throw new Exception('project_id required');
        }
        
        $result = $approvalManager->approveForImplementation($project_id, $user['id']);
        
        authJsonResponse([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result
        ]);
    }
    elseif ($action === 'reject') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('POST method required');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $project_id = $data['project_id'] ?? null;
        $reason = $data['reason'] ?? 'No reason provided';
        
        if (!$project_id) {
            throw new Exception('project_id required');
        }
        
        $result = $approvalManager->rejectProject($project_id, $user['id'], $reason);
        
        authJsonResponse([
            'success' => $result['success'],
            'message' => $result['message']
        ]);
    }
    elseif ($action === 'get_workflow') {
        $project_id = $_GET['project_id'] ?? null;
        
        if (!$project_id) {
            throw new Exception('project_id parameter required');
        }
        
        $workflow = $approvalManager->getApprovalWorkflow($project_id);
        
        authJsonResponse([
            'success' => true,
            'data' => $workflow
        ]);
    }
    elseif ($action === 'get_status') {
        $project_id = $_GET['project_id'] ?? null;
        
        if (!$project_id) {
            throw new Exception('project_id parameter required');
        }
        
        $status = $approvalManager->getApprovalStatus($project_id);
        
        authJsonResponse([
            'success' => true,
            'data' => $status
        ]);
    }
    elseif ($action === 'submit_approval') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('POST method required');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $project_id = $data['project_id'] ?? null;
        $stage = $data['stage'] ?? null;
        $status = $data['status'] ?? null;
        $remarks = $data['remarks'] ?? '';
        $rejection_reason = $data['rejection_reason'] ?? '';
        
        if (!$project_id || !$stage || !$status) {
            throw new Exception('Missing required fields');
        }
        
        $result = $approvalManager->submitApproval(
            $project_id,
            $stage,
            $status,
            $user['id'],
            $user['role'],
            $remarks,
            $rejection_reason
        );
        
        authJsonResponse([
            'success' => $result['success'],
            'message' => $result['message']
        ]);
    }
    elseif ($action === 'check_progression') {
        $project_id = $_GET['project_id'] ?? null;
        $stage = $_GET['stage'] ?? null;
        
        if (!$project_id || !$stage) {
            throw new Exception('Missing parameters');
        }
        
        $can_proceed = $approvalManager->canProceedToNextStage($project_id, $stage);
        
        authJsonResponse([
            'success' => true,
            'can_proceed' => $can_proceed,
            'project_id' => $project_id,
            'current_stage' => $stage
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
