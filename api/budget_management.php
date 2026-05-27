<?php
/**
 * Budget Management API
 * Handles budget allocation, assessment, and tracking
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/BudgetManager.php';

requireLogin();

$user = currentUser();

// Only admins, super admins, and engineers can access budget features
if (!in_array($user['role'], ['super_admin', 'admin', 'engineer'])) {
    authJsonError('Access denied', 403);
}

try {
    $action = $_GET['action'] ?? 'get_status';
    $budgetManager = new BudgetManager();
    
    if ($action === 'assess_feasibility') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('POST method required');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $project_id = $data['project_id'] ?? null;
        $estimated_cost = $data['estimated_cost'] ?? null;
        $available_budget = $data['available_budget'] ?? null;
        $notes = $data['notes'] ?? '';
        
        if (!$project_id || !$estimated_cost || !$available_budget) {
            throw new Exception('Missing required fields');
        }
        
        $result = $budgetManager->assessFeasibility(
            $project_id,
            $estimated_cost,
            $available_budget,
            $user['id'],
            $notes
        );
        
        authJsonResponse([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result
        ]);
    }
    elseif ($action === 'allocate_budget') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('POST method required');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $project_id = $data['project_id'] ?? null;
        $fiscal_year = $data['fiscal_year'] ?? date('Y');
        $amount = $data['amount'] ?? null;
        
        if (!$project_id || !$amount) {
            throw new Exception('Missing required fields');
        }
        
        $result = $budgetManager->allocateBudget($project_id, $fiscal_year, $amount, $user['id']);
        
        authJsonResponse([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result
        ]);
    }
    elseif ($action === 'release_budget') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('POST method required');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $allocation_id = $data['allocation_id'] ?? null;
        $amount = $data['amount'] ?? null;
        
        if (!$allocation_id || !$amount) {
            throw new Exception('Missing required fields');
        }
        
        $result = $budgetManager->releaseBudget($allocation_id, $amount);
        
        authJsonResponse([
            'success' => $result['success'],
            'message' => $result['message']
        ]);
    }
    elseif ($action === 'record_expense') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('POST method required');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $allocation_id = $data['allocation_id'] ?? null;
        $amount = $data['amount'] ?? null;
        
        if (!$allocation_id || !$amount) {
            throw new Exception('Missing required fields');
        }
        
        $result = $budgetManager->recordExpense($allocation_id, $amount);
        
        authJsonResponse([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result
        ]);
    }
    elseif ($action === 'get_status') {
        $project_id = $_GET['project_id'] ?? null;
        
        if (!$project_id) {
            throw new Exception('project_id parameter required');
        }
        
        $status = $budgetManager->getProjectBudgetStatus($project_id);
        
        authJsonResponse([
            'success' => true,
            'data' => $status
        ]);
    }
    elseif ($action === 'allocation_report') {
        $fiscal_year = $_GET['fiscal_year'] ?? date('Y');
        
        $report = $budgetManager->getBudgetAllocationReport($fiscal_year);
        
        authJsonResponse([
            'success' => true,
            'data' => $report
        ]);
    }
    elseif ($action === 'assessment') {
        $project_id = $_GET['project_id'] ?? null;
        
        if (!$project_id) {
            throw new Exception('project_id parameter required');
        }
        
        $assessment = $budgetManager->getAssessment($project_id);
        
        authJsonResponse([
            'success' => true,
            'data' => $assessment
        ]);
    }
    elseif ($action === 'audit_trail') {
        $project_id = $_GET['project_id'] ?? null;
        
        if (!$project_id) {
            throw new Exception('project_id parameter required');
        }
        
        $trail = $budgetManager->getBlockchainAuditTrail($project_id);
        
        authJsonResponse([
            'success' => true,
            'message' => 'Immutable blockchain audit trail for budget tracking',
            'data' => $trail
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
