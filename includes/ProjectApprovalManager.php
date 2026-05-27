<?php
/**
 * Project Approval Workflow System
 * Manages the approval stages for project implementation
 */

require_once __DIR__ . '/../includes/config.php';

class ProjectApprovalManager {
    private $db;
    
    public function __construct() {
        global $pdo;
        $this->db = $pdo;
    }
    
    /**
     * Create approval workflow for new project
     */
    public function initializeApprovalWorkflow($project_id): array {
        try {
            // Define approval stages
            $stages = [
                'planning_review',
                'feasibility_review',
                'lgu_approval',
                'implementation_approval',
                'completion_approval'
            ];
            
            foreach ($stages as $stage) {
                $stmt = $this->db->prepare('
                    INSERT INTO project_approvals (
                        project_id, approval_stage, approval_status
                    ) VALUES (?, ?, ?)
                ');
                
                $stmt->execute([$project_id, $stage, 'pending']);
            }
            
            return ['success' => true, 'message' => 'Approval workflow initialized'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Initialization failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Submit approval/rejection for a stage
     */
    public function submitApproval($project_id, $stage, $status, $reviewer_id, $reviewer_role, $remarks = '', $rejection_reason = ''): array {
        try {
            if (!in_array($status, ['approved', 'rejected', 'returned'])) {
                return ['success' => false, 'message' => 'Invalid approval status'];
            }
            
            // Update approval record
            $stmt = $this->db->prepare('
                UPDATE project_approvals
                SET approval_status = ?,
                    reviewer_id = ?,
                    remarks = ?,
                    rejection_reason = ?,
                    reviewed_at = NOW()
                WHERE project_id = ? AND approval_stage = ?
            ');
            
            $stmt->execute([
                $status,
                $reviewer_id,
                $remarks,
                $rejection_reason,
                $project_id,
                $stage
            ]);
            
            // If approved at LGU approval stage, update project status
            if ($stage === 'lgu_approval' && $status === 'approved') {
                $project_update = $this->db->prepare('
                    UPDATE projects
                    SET approval_status = ?, approved_proposal_date = NOW()
                    WHERE id = ?
                ');
                $project_update->execute(['approved_for_implementation', $project_id]);
            }
            
            // If rejected at any stage, update project status
            if ($status === 'rejected') {
                $project_update = $this->db->prepare('
                    UPDATE projects
                    SET approval_status = ?
                    WHERE id = ?
                ');
                $project_update->execute(['rejected', $project_id]);
            }
            
            return ['success' => true, 'message' => 'Approval submitted successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Submission failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get approval workflow for project
     */
    public function getApprovalWorkflow($project_id): array {
        try {
            $stmt = $this->db->prepare('
                SELECT 
                    pa.*,
                    u.full_name as reviewer_name,
                    u.email as reviewer_email
                FROM project_approvals pa
                LEFT JOIN users u ON pa.reviewer_id = u.id
                WHERE pa.project_id = ?
                ORDER BY 
                    CASE pa.approval_stage
                        WHEN "planning_review" THEN 1
                        WHEN "feasibility_review" THEN 2
                        WHEN "lgu_approval" THEN 3
                        WHEN "implementation_approval" THEN 4
                        WHEN "completion_approval" THEN 5
                    END
            ');
            
            $stmt->execute([$project_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Failed to get workflow: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get approval status for project
     */
    public function getApprovalStatus($project_id): ?array {
        try {
            $stmt = $this->db->prepare('
                SELECT 
                    p.id,
                    p.project_code,
                    p.name,
                    p.approval_status,
                    COUNT(CASE WHEN pa.approval_status = "approved" THEN 1 END) as approved_stages,
                    COUNT(CASE WHEN pa.approval_status = "pending" THEN 1 END) as pending_stages,
                    COUNT(CASE WHEN pa.approval_status = "rejected" THEN 1 END) as rejected_stages,
                    GROUP_CONCAT(DISTINCT pa.approval_stage) as pending_stages_list
                FROM projects p
                LEFT JOIN project_approvals pa ON p.id = pa.project_id AND pa.approval_status = "pending"
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
     * Check if project can proceed to next stage
     */
    public function canProceedToNextStage($project_id, $current_stage): bool {
        try {
            // Get project details
            $proj_stmt = $this->db->prepare('
                SELECT * FROM projects WHERE id = ?
            ');
            $proj_stmt->execute([$project_id]);
            $project = $proj_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$project) {
                return false;
            }
            
            // Check requirements based on stage
            switch ($current_stage) {
                case 'planning_review':
                    // Check if project has proper description and location
                    return !empty($project['description']) && !empty($project['location']);
                    
                case 'feasibility_review':
                    // Check if budget assessment exists
                    $budget_stmt = $this->db->prepare('
                        SELECT id FROM project_budget_assessment WHERE project_id = ?
                    ');
                    $budget_stmt->execute([$project_id]);
                    return $budget_stmt->rowCount() > 0;
                    
                case 'lgu_approval':
                    // Check if contractor is assigned and has good credibility
                    if ($project['contractor_id']) {
                        $contractor_stmt = $this->db->prepare('
                            SELECT credibility_score, is_blacklisted FROM contractors WHERE id = ?
                        ');
                        $contractor_stmt->execute([$project['contractor_id']]);
                        $contractor = $contractor_stmt->fetch(PDO::FETCH_ASSOC);
                        return $contractor && !$contractor['is_blacklisted'] && $contractor['credibility_score'] >= 5;
                    }
                    return true;
                    
                case 'implementation_approval':
                    // Check if engineer assigned
                    $eng_stmt = $this->db->prepare('
                        SELECT id FROM engineer_project_assignments WHERE project_id = ? AND status = "active"
                    ');
                    $eng_stmt->execute([$project_id]);
                    return $eng_stmt->rowCount() > 0;
                    
                default:
                    return true;
            }
        } catch (Exception $e) {
            error_log('Failed to check progression: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get projects pending LGU management approval
     */
    public function getPendingLGUApproval(): array {
        try {
            $stmt = $this->db->prepare('
                SELECT 
                    p.id,
                    p.project_code,
                    p.name,
                    p.description,
                    p.location,
                    p.budget,
                    p.created_at,
                    c.name as contractor_name,
                    c.credibility_score,
                    pba.feasibility_status,
                    pps.final_priority_score
                FROM projects p
                LEFT JOIN contractors c ON p.contractor_id = c.id
                LEFT JOIN project_budget_assessment pba ON p.id = pba.project_id
                LEFT JOIN project_priority_scores pps ON p.id = pps.project_id
                JOIN project_approvals pa ON p.id = pa.project_id
                WHERE pa.approval_stage = "lgu_approval" 
                    AND pa.approval_status = "pending"
                    AND p.approval_status = "pending_approval"
                ORDER BY pps.final_priority_score DESC, p.created_at ASC
            ');
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Failed to get pending approvals: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Approve project for implementation
     */
    public function approveForImplementation($project_id, $reviewer_id): array {
        try {
            // Submit LGU approval
            $approval_result = $this->submitApproval(
                $project_id,
                'lgu_approval',
                'approved',
                $reviewer_id,
                'super_admin',
                'Approved for implementation by LGU management'
            );
            
            if (!$approval_result['success']) {
                return $approval_result;
            }
            
            return [
                'success' => true,
                'message' => 'Project approved for implementation',
                'project_id' => $project_id
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Approval failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Reject project
     */
    public function rejectProject($project_id, $reviewer_id, $reason): array {
        try {
            $reject_result = $this->submitApproval(
                $project_id,
                'lgu_approval',
                'rejected',
                $reviewer_id,
                'super_admin',
                'Project rejected',
                $reason
            );
            
            return $reject_result;
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Rejection failed: ' . $e->getMessage()];
        }
    }
}
