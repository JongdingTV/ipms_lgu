<?php
/**
 * Reporting & Analytics API
 * Generates comprehensive reports for projects, budgets, and performance
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ReportingManager.php';

requireLogin();

$user = currentUser();

// Only admins, super admins, and engineers can access reports
if (!in_array($user['role'], ['super_admin', 'admin', 'engineer'])) {
    authJsonError('Access denied. Only administrators can generate reports.', 403);
}

try {
    $action = $_GET['action'] ?? 'list';
    $reportingManager = new ReportingManager();
    
    if ($action === 'budget_allocation') {
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        
        $result = $reportingManager->generateBudgetAllocationReport(
            $start_date,
            $end_date,
            $user['id']
        );
        
        authJsonResponse([
            'success' => $result['success'],
            'report_type' => 'Budget Allocation Report',
            'data' => $result['report'] ?? null,
            'message' => $result['message'] ?? 'Report generated successfully'
        ]);
    }
    elseif ($action === 'project_completion') {
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        
        $result = $reportingManager->generateProjectCompletionReport(
            $start_date,
            $end_date,
            $user['id']
        );
        
        authJsonResponse([
            'success' => $result['success'],
            'report_type' => 'Project Completion Report',
            'data' => $result['report'] ?? null,
            'message' => $result['message'] ?? 'Report generated successfully'
        ]);
    }
    elseif ($action === 'barangay_infrastructure') {
        $barangay = $_GET['barangay'] ?? null;
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        
        if (!$barangay) {
            throw new Exception('barangay parameter required');
        }
        
        $result = $reportingManager->generateBarangayInfrastructureReport(
            $barangay,
            $start_date,
            $end_date,
            $user['id']
        );
        
        authJsonResponse([
            'success' => $result['success'],
            'report_type' => 'Barangay Infrastructure Report',
            'barangay' => $barangay,
            'data' => $result['report'] ?? null,
            'message' => $result['message'] ?? 'Report generated successfully'
        ]);
    }
    elseif ($action === 'priority_ranking') {
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        
        $result = $reportingManager->generatePriorityRankingReport(
            $start_date,
            $end_date,
            $user['id']
        );
        
        authJsonResponse([
            'success' => $result['success'],
            'report_type' => 'Priority Ranking Report',
            'data' => $result['report'] ?? null,
            'message' => $result['message'] ?? 'Report generated successfully'
        ]);
    }
    elseif ($action === 'performance') {
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        
        $result = $reportingManager->generatePerformanceReport(
            $start_date,
            $end_date,
            $user['id']
        );
        
        authJsonResponse([
            'success' => $result['success'],
            'report_type' => 'Contractor Performance Report',
            'data' => $result['report'] ?? null,
            'message' => $result['message'] ?? 'Report generated successfully'
        ]);
    }
    elseif ($action === 'list') {
        $type = $_GET['type'] ?? null;
        $limit = min((int)($_GET['limit'] ?? 50), 100);
        
        $reports = $reportingManager->getStoredReports($type, $limit);
        
        authJsonResponse([
            'success' => true,
            'report_type' => 'Stored Reports List',
            'data' => $reports
        ]);
    }
    elseif ($action === 'available_barangays') {
        // Get available barangays for report generation
        $pdo = $GLOBALS['pdo'];
        $stmt = $pdo->prepare('
            SELECT DISTINCT barangay
            FROM projects
            WHERE approval_status = "approved_for_implementation"
            ORDER BY barangay ASC
        ');
        $stmt->execute();
        
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
