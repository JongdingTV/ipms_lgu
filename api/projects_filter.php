<?php
/**
 * Project Filtering & Search API
 * Allows citizens to filter projects by date, contractor, blacklist status, and credibility
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

// Only authenticated users
requireLogin();

try {
    $action = $_GET['action'] ?? 'search';
    
    // Get filter parameters
    $filters = [
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'contractor_id' => $_GET['contractor_id'] ?? null,
        'exclude_blacklisted' => $_GET['exclude_blacklisted'] === 'true' ? true : false,
        'min_credibility' => $_GET['min_credibility'] ?? 0,
        'status' => $_GET['status'] ?? null,
        'barangay' => $_GET['barangay'] ?? null,
        'search' => $_GET['search'] ?? null,
        'sort_by' => $_GET['sort_by'] ?? 'priority',
        'limit' => min((int)($_GET['limit'] ?? 20), 100),
        'offset' => (int)($_GET['offset'] ?? 0)
    ];
    
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    if ($action === 'search') {
        // Build query with filters
        $query = '
            SELECT 
                p.id,
                p.project_code,
                p.name,
                p.description,
                p.location,
                p.barangay,
                p.progress,
                p.status,
                p.budget,
                p.start_date,
                p.end_date,
                c.name as contractor_name,
                c.is_blacklisted,
                c.credibility_score,
                c.performance_score,
                pba.feasibility_status,
                pba.estimated_cost,
                pps.final_priority_score,
                pps.ranking,
                COUNT(DISTINCT cpv.id) as total_votes,
                AVG(cpv.urgency_score) as average_urgency
            FROM projects p
            LEFT JOIN contractors c ON p.contractor_id = c.id
            LEFT JOIN project_budget_assessment pba ON p.id = pba.project_id
            LEFT JOIN project_priority_scores pps ON p.id = pps.project_id
            LEFT JOIN citizen_project_votes cpv ON p.id = cpv.project_id
            WHERE p.approval_status = "approved_for_implementation"
        ';
        
        $params = [];
        
        // Apply filters
        if ($filters['date_from']) {
            $query .= ' AND p.created_at >= ?';
            $params[] = $filters['date_from'];
        }
        
        if ($filters['date_to']) {
            $query .= ' AND p.created_at <= ?';
            $params[] = $filters['date_to'];
        }
        
        if ($filters['contractor_id']) {
            $query .= ' AND p.contractor_id = ?';
            $params[] = $filters['contractor_id'];
        }
        
        if ($filters['exclude_blacklisted']) {
            $query .= ' AND (c.is_blacklisted = 0 OR c.is_blacklisted IS NULL)';
        }
        
        if ($filters['min_credibility']) {
            $query .= ' AND (c.credibility_score >= ? OR c.credibility_score IS NULL)';
            $params[] = $filters['min_credibility'];
        }
        
        if ($filters['status']) {
            $query .= ' AND p.status = ?';
            $params[] = $filters['status'];
        }
        
        if ($filters['barangay']) {
            $query .= ' AND p.barangay = ?';
            $params[] = $filters['barangay'];
        }
        
        if ($filters['search']) {
            $query .= ' AND (p.name LIKE ? OR p.description LIKE ? OR p.location LIKE ?)';
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // Group by project
        $query .= ' GROUP BY p.id';
        
        // Sorting
        switch ($filters['sort_by']) {
            case 'priority':
                $query .= ' ORDER BY pps.final_priority_score DESC, p.created_at DESC';
                break;
            case 'recent':
                $query .= ' ORDER BY p.created_at DESC';
                break;
            case 'progress':
                $query .= ' ORDER BY p.progress DESC';
                break;
            case 'votes':
                $query .= ' ORDER BY total_votes DESC';
                break;
            case 'budget':
                $query .= ' ORDER BY p.budget DESC';
                break;
            default:
                $query .= ' ORDER BY pps.final_priority_score DESC';
        }
        
        // Get total count first
        $count_query = 'SELECT COUNT(DISTINCT p.id) as total FROM (' . $query . ') as sub';
        $count_stmt = $pdo->prepare(str_replace('SELECT ', 'SELECT DISTINCT ', $query));
        $count_stmt->execute($params);
        $total = $count_stmt->rowCount();
        
        // Apply pagination
        $query .= ' LIMIT ? OFFSET ?';
        $params[] = $filters['limit'];
        $params[] = $filters['offset'];
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        authJsonResponse([
            'success' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'pagination' => [
                'limit' => $filters['limit'],
                'offset' => $filters['offset'],
                'total' => $total
            ]
        ]);
    } 
    elseif ($action === 'contractors') {
        // Get list of contractors for filtering
        $stmt = $pdo->prepare('
            SELECT 
                id, name, credibility_score, performance_score, is_blacklisted
            FROM contractors
            WHERE status = "active"
            ORDER BY credibility_score DESC
        ');
        $stmt->execute();
        
        authJsonResponse([
            'success' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }
    elseif ($action === 'barangays') {
        // Get list of barangays with projects
        $stmt = $pdo->prepare('
            SELECT DISTINCT barangay, COUNT(id) as project_count
            FROM projects
            WHERE approval_status = "approved_for_implementation"
            GROUP BY barangay
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

/**
 * Send JSON response
 */
function authJsonResponse($data) {
    http_response_code(200);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Send error response
 */
function authJsonError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
