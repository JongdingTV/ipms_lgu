<?php
/**
 * Citizen Voting & Project Priority Scoring System
 * Handles community votes for urgency and automatic priority score calculation
 */

require_once __DIR__ . '/../includes/config.php';

class CitizenVotingManager {
    private $db;
    
    public function __construct() {
        global $pdo;
        $this->db = $pdo;
    }
    
    /**
     * Submit vote for project urgency
     */
    public function submitVote($project_id, $citizen_id, $urgency_score, $comment = ''): array {
        try {
            // Validate urgency score (1-10)
            if ($urgency_score < 1 || $urgency_score > 10) {
                return ['success' => false, 'message' => 'Urgency score must be between 1 and 10'];
            }
            
            $stmt = $this->db->prepare('
                INSERT INTO citizen_project_votes (project_id, citizen_id, urgency_score, vote_comment)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    urgency_score = VALUES(urgency_score),
                    vote_comment = VALUES(vote_comment),
                    updated_at = NOW()
            ');
            
            $stmt->execute([$project_id, $citizen_id, $urgency_score, $comment]);
            
            // Recalculate project priority score
            $this->recalculatePriorityScore($project_id);
            
            return ['success' => true, 'message' => 'Vote submitted successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to submit vote: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get votes for a project
     */
    public function getProjectVotes($project_id): array {
        try {
            $stmt = $this->db->prepare('
                SELECT 
                    COUNT(*) as total_votes,
                    AVG(urgency_score) as average_urgency,
                    MIN(urgency_score) as min_score,
                    MAX(urgency_score) as max_score,
                    STDDEV(urgency_score) as score_stddev
                FROM citizen_project_votes
                WHERE project_id = ?
            ');
            
            $stmt->execute([$project_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Failed to get project votes: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get citizen vote for project
     */
    public function getCitizenVote($project_id, $citizen_id): ?array {
        try {
            $stmt = $this->db->prepare('
                SELECT * FROM citizen_project_votes
                WHERE project_id = ? AND citizen_id = ?
            ');
            
            $stmt->execute([$project_id, $citizen_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Recalculate project priority score
     */
    public function recalculatePriorityScore($project_id): array {
        try {
            // Get project details
            $proj_stmt = $this->db->prepare('
                SELECT p.*, c.credibility_score as contractor_credibility
                FROM projects p
                LEFT JOIN contractors c ON p.contractor_id = c.id
                WHERE p.id = ?
            ');
            $proj_stmt->execute([$project_id]);
            $project = $proj_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$project) {
                return ['success' => false, 'message' => 'Project not found'];
            }
            
            // Get budget assessment
            $budget_stmt = $this->db->prepare('
                SELECT * FROM project_budget_assessment WHERE project_id = ?
            ');
            $budget_stmt->execute([$project_id]);
            $budget = $budget_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate scores (0-10 scale)
            
            // 1. Community votes score (0-10)
            $votes = $this->getProjectVotes($project_id);
            $community_votes_score = $votes['average_urgency'] ?? 5;
            
            // 2. Urgency score based on status (0-10)
            $urgency_score = $this->calculateUrgencyScore($project['status']);
            
            // 3. Budget feasibility score (0-10)
            $budget_feasibility_score = $this->calculateBudgetFeasibility($budget);
            
            // 4. Contractor credibility score (0-10)
            $contractor_credibility_score = $project['contractor_credibility'] ?? 5;
            
            // Calculate final priority score (weighted average)
            $weights = [
                'community_votes' => 0.30,      // 30%
                'urgency' => 0.25,              // 25%
                'budget_feasibility' => 0.25,   // 25%
                'contractor_credibility' => 0.20 // 20%
            ];
            
            $final_priority_score = (
                ($community_votes_score * $weights['community_votes']) +
                ($urgency_score * $weights['urgency']) +
                ($budget_feasibility_score * $weights['budget_feasibility']) +
                ($contractor_credibility_score * $weights['contractor_credibility'])
            );
            
            // Store or update priority scores
            $score_stmt = $this->db->prepare('
                INSERT INTO project_priority_scores (
                    project_id, community_votes_score, urgency_score,
                    budget_feasibility_score, contractor_credibility_score,
                    final_priority_score
                ) VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    community_votes_score = VALUES(community_votes_score),
                    urgency_score = VALUES(urgency_score),
                    budget_feasibility_score = VALUES(budget_feasibility_score),
                    contractor_credibility_score = VALUES(contractor_credibility_score),
                    final_priority_score = VALUES(final_priority_score),
                    calculated_at = NOW()
            ');
            
            $score_stmt->execute([
                $project_id,
                round($community_votes_score, 2),
                round($urgency_score, 2),
                round($budget_feasibility_score, 2),
                round($contractor_credibility_score, 2),
                round($final_priority_score, 2)
            ]);
            
            // Update ranking
            $this->updateProjectRankings();
            
            return [
                'success' => true,
                'priority_score' => round($final_priority_score, 2),
                'components' => [
                    'community_votes' => round($community_votes_score, 2),
                    'urgency' => round($urgency_score, 2),
                    'budget_feasibility' => round($budget_feasibility_score, 2),
                    'contractor_credibility' => round($contractor_credibility_score, 2)
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to recalculate priority: ' . $e->getMessage()];
        }
    }
    
    /**
     * Calculate urgency score based on project status
     */
    private function calculateUrgencyScore($status): float {
        $urgency_map = [
            'draft' => 2,
            'planning' => 3,
            'approved' => 5,
            'bidding' => 6,
            'awarded' => 7,
            'assigned' => 7,
            'active' => 8,
            'delayed' => 9,
            'on_hold' => 4,
            'completed' => 1,
            'cancelled' => 1
        ];
        
        return $urgency_map[$status] ?? 5;
    }
    
    /**
     * Calculate budget feasibility score
     */
    private function calculateBudgetFeasibility($budget): float {
        if (!$budget) {
            return 5; // Default middle score
        }
        
        $feasibility_map = [
            'feasible' => 9,
            'partially_feasible' => 5,
            'not_feasible' => 2
        ];
        
        return $feasibility_map[$budget['feasibility_status']] ?? 5;
    }
    
    /**
     * Update project rankings based on priority scores
     */
    private function updateProjectRankings(): void {
        try {
            // Get all projects sorted by priority score
            $stmt = $this->db->prepare('
                SELECT id FROM project_priority_scores
                ORDER BY final_priority_score DESC, calculated_at DESC
            ');
            
            $stmt->execute();
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Assign rankings
            foreach ($projects as $rank => $project) {
                $update = $this->db->prepare('
                    UPDATE project_priority_scores
                    SET ranking = ?
                    WHERE id = ?
                ');
                $update->execute([$rank + 1, $project['id']]);
            }
        } catch (Exception $e) {
            error_log('Failed to update rankings: ' . $e->getMessage());
        }
    }
    
    /**
     * Get priority ranking list
     */
    public function getPriorityRankingList($limit = 20): array {
        try {
            $stmt = $this->db->prepare('
                SELECT 
                    pps.ranking,
                    p.id,
                    p.project_code,
                    p.name,
                    p.location,
                    p.barangay,
                    p.budget,
                    p.progress,
                    p.status,
                    pps.final_priority_score,
                    pps.community_votes_score,
                    pps.urgency_score,
                    COUNT(DISTINCT cpv.id) as total_votes
                FROM project_priority_scores pps
                JOIN projects p ON pps.project_id = p.id
                LEFT JOIN citizen_project_votes cpv ON p.id = cpv.project_id
                WHERE p.approval_status = "approved_for_implementation"
                GROUP BY pps.id, p.id
                ORDER BY pps.ranking ASC
                LIMIT ?
            ');
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Failed to get ranking list: ' . $e->getMessage());
            return [];
        }
    }
}
