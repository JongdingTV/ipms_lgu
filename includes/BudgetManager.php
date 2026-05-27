<?php
/**
 * Budget Management & Feasibility Assessment System
 * Handles budget allocation, feasibility analysis, and constraint tracking
 */

require_once __DIR__ . '/../includes/config.php';

class BudgetManager {
    private $db;
    
    public function __construct() {
        global $pdo;
        $this->db = $pdo;
    }
    
    /**
     * Assess project feasibility based on budget
     */
    public function assessFeasibility($project_id, $estimated_cost, $available_budget, $assessed_by, $notes = ''): array {
        try {
            $feasibility_status = 'feasible';
            
            if ($estimated_cost > $available_budget) {
                $feasibility_status = 'not_feasible';
            } elseif ($estimated_cost > ($available_budget * 0.8)) {
                $feasibility_status = 'partially_feasible';
            }
            
            $stmt = $this->db->prepare('
                INSERT INTO project_budget_assessment (
                    project_id, estimated_cost, available_budget,
                    feasibility_status, feasibility_notes, assessed_by, assessed_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    estimated_cost = VALUES(estimated_cost),
                    available_budget = VALUES(available_budget),
                    feasibility_status = VALUES(feasibility_status),
                    feasibility_notes = VALUES(feasibility_notes),
                    assessed_by = VALUES(assessed_by),
                    assessed_at = NOW()
            ');
            
            $stmt->execute([
                $project_id,
                $estimated_cost,
                $available_budget,
                $feasibility_status,
                $notes,
                $assessed_by
            ]);
            
            return [
                'success' => true,
                'feasibility_status' => $feasibility_status,
                'message' => 'Feasibility assessment completed'
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Assessment failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get budget assessment for project
     */
    public function getAssessment($project_id): ?array {
        try {
            $stmt = $this->db->prepare('
                SELECT * FROM project_budget_assessment WHERE project_id = ?
            ');
            
            $stmt->execute([$project_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Allocate budget to project
     */
    public function allocateBudget($project_id, $fiscal_year, $amount, $approved_by): array {
        try {
            // Check if budget already allocated for this fiscal year
            $check = $this->db->prepare('
                SELECT id, allocated_amount FROM budget_allocations
                WHERE project_id = ? AND fiscal_year = ?
            ');
            $check->execute([$project_id, $fiscal_year]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                return ['success' => false, 'message' => 'Budget already allocated for this fiscal year'];
            }
            
            $stmt = $this->db->prepare('
                INSERT INTO budget_allocations (
                    project_id, fiscal_year, allocated_amount,
                    allocation_status, approved_by, approved_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ');
            
            $stmt->execute([
                $project_id,
                $fiscal_year,
                $amount,
                'allocated',
                $approved_by
            ]);
            
            // Log in blockchain audit
            $this->logBlockchainTransaction(
                $project_id,
                'budget_allocation',
                $amount,
                $approved_by,
                ['allocated_amount' => $amount, 'fiscal_year' => $fiscal_year]
            );
            
            return [
                'success' => true,
                'message' => 'Budget allocated successfully',
                'allocation_id' => $this->db->lastInsertId()
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Budget allocation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Release allocated budget
     */
    public function releaseBudget($allocation_id, $released_amount): array {
        try {
            $stmt = $this->db->prepare('
                UPDATE budget_allocations
                SET released_amount = released_amount + ?,
                    allocation_status = "released"
                WHERE id = ?
            ');
            
            $stmt->execute([$released_amount, $allocation_id]);
            
            return ['success' => true, 'message' => 'Budget released successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Release failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Record budget expense
     */
    public function recordExpense($allocation_id, $amount): array {
        try {
            // Get allocation details
            $stmt = $this->db->prepare('
                SELECT * FROM budget_allocations WHERE id = ?
            ');
            $stmt->execute([$allocation_id]);
            $allocation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$allocation) {
                return ['success' => false, 'message' => 'Allocation not found'];
            }
            
            // Check if spending doesn't exceed released budget
            if (($allocation['spent_amount'] + $amount) > $allocation['released_amount']) {
                return [
                    'success' => false,
                    'message' => 'Expense exceeds released budget',
                    'available' => $allocation['released_amount'] - $allocation['spent_amount']
                ];
            }
            
            // Update spent amount
            $update = $this->db->prepare('
                UPDATE budget_allocations
                SET spent_amount = spent_amount + ?,
                    allocation_status = "spent"
                WHERE id = ?
            ');
            
            $update->execute([$amount, $allocation_id]);
            
            // Log blockchain transaction
            $this->logBlockchainTransaction(
                $allocation['project_id'],
                'expense_recorded',
                $amount,
                getCurrentUserId(),
                ['allocation_id' => $allocation_id, 'spent_amount' => $amount]
            );
            
            return ['success' => true, 'message' => 'Expense recorded successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to record expense: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get budget allocation report
     */
    public function getBudgetAllocationReport($fiscal_year = null): array {
        try {
            $query = '
                SELECT 
                    ba.id,
                    p.project_code,
                    p.name,
                    ba.fiscal_year,
                    ba.allocated_amount,
                    ba.released_amount,
                    ba.spent_amount,
                    (ba.allocated_amount - ba.spent_amount) as remaining,
                    ROUND((ba.spent_amount / ba.allocated_amount * 100), 2) as percentage_spent,
                    ba.allocation_status,
                    ba.approved_at
                FROM budget_allocations ba
                JOIN projects p ON ba.project_id = p.id
            ';
            
            if ($fiscal_year) {
                $query .= ' WHERE ba.fiscal_year = ?';
                $stmt = $this->db->prepare($query);
                $stmt->execute([$fiscal_year]);
            } else {
                $stmt = $this->db->prepare($query);
                $stmt->execute();
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Failed to get allocation report: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get project budget status
     */
    public function getProjectBudgetStatus($project_id): ?array {
        try {
            $stmt = $this->db->prepare('
                SELECT 
                    p.id,
                    p.project_code,
                    p.name,
                    p.budget,
                    pba.estimated_cost,
                    pba.available_budget,
                    pba.feasibility_status,
                    SUM(ba.allocated_amount) as total_allocated,
                    SUM(ba.released_amount) as total_released,
                    SUM(ba.spent_amount) as total_spent,
                    (SUM(ba.allocated_amount) - SUM(ba.spent_amount)) as balance
                FROM projects p
                LEFT JOIN project_budget_assessment pba ON p.id = pba.project_id
                LEFT JOIN budget_allocations ba ON p.id = ba.project_id
                WHERE p.id = ?
                GROUP BY p.id
            ');
            
            $stmt->execute([$project_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Log blockchain transaction for immutable audit trail
     */
    private function logBlockchainTransaction($project_id, $type, $amount = null, $user_id = null, $details = []): void {
        try {
            $hash = hash('sha256', json_encode(array_merge([
                'project_id' => $project_id,
                'type' => $type,
                'timestamp' => time()
            ], $details)));
            
            $stmt = $this->db->prepare('
                INSERT INTO blockchain_audit_log (
                    transaction_hash, transaction_type, project_id, amount,
                    details, initiated_by, immutable
                ) VALUES (?, ?, ?, ?, ?, ?, 1)
            ');
            
            $stmt->execute([
                $hash,
                $type,
                $project_id,
                $amount,
                json_encode($details),
                $user_id
            ]);
        } catch (Exception $e) {
            error_log('Failed to log blockchain transaction: ' . $e->getMessage());
        }
    }
    
    /**
     * Get blockchain audit trail for project
     */
    public function getBlockchainAuditTrail($project_id): array {
        try {
            $stmt = $this->db->prepare('
                SELECT 
                    transaction_hash,
                    transaction_type,
                    amount,
                    details,
                    initiated_by,
                    recorded_at
                FROM blockchain_audit_log
                WHERE project_id = ?
                ORDER BY recorded_at ASC
            ');
            
            $stmt->execute([$project_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Failed to get audit trail: ' . $e->getMessage());
            return [];
        }
    }
}

/**
 * Helper function to get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}
