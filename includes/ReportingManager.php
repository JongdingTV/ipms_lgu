<?php
/**
 * Reporting & Analytics System
 * Generates budget allocation, completion, barangay infrastructure, and priority ranking reports
 */

require_once __DIR__ . '/../includes/config.php';

class ReportingManager {
    private $db;
    
    public function __construct() {
        global $pdo;
        $this->db = $pdo;
    }
    
    /**
     * Generate budget allocation report
     */
    public function generateBudgetAllocationReport($start_date, $end_date, $generated_by): array {
        try {
            $stmt = $this->db->prepare('
                SELECT 
                    ba.fiscal_year,
                    p.project_code,
                    p.name,
                    p.location,
                    ba.allocated_amount,
                    ba.released_amount,
                    ba.spent_amount,
                    (ba.allocated_amount - ba.spent_amount) as balance,
                    ROUND((ba.spent_amount / ba.allocated_amount * 100), 2) as percentage_spent,
                    ba.allocation_status
                FROM budget_allocations ba
                JOIN projects p ON ba.project_id = p.id
                WHERE ba.created_at >= ? AND ba.created_at <= ?
                ORDER BY ba.fiscal_year DESC, ba.allocated_amount DESC
            ');
            
            $stmt->execute([$start_date, $end_date]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate summaries
            $summary = [
                'total_allocated' => 0,
                'total_released' => 0,
                'total_spent' => 0,
                'total_balance' => 0,
                'projects_count' => count($data)
            ];
            
            foreach ($data as $row) {
                $summary['total_allocated'] += $row['allocated_amount'];
                $summary['total_released'] += $row['released_amount'];
                $summary['total_spent'] += $row['spent_amount'];
                $summary['total_balance'] += $row['balance'];
            }
            
            $report_data = [
                'report_type' => 'budget_allocation',
                'summary' => $summary,
                'details' => $data
            ];
            
            // Store report
            $this->storeReport(null, 'budget_allocation', $start_date, $end_date, $report_data, $generated_by);
            
            return ['success' => true, 'report' => $report_data];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Report generation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate project completion report
     */
    public function generateProjectCompletionReport($start_date, $end_date, $generated_by): array {
        try {
            $stmt = $this->db->prepare('
                SELECT 
                    p.project_code,
                    p.name,
                    p.location,
                    p.barangay,
                    p.status,
                    p.progress,
                    p.start_date,
                    p.end_date,
                    DATEDIFF(p.end_date, p.start_date) as planned_duration,
                    DATEDIFF(NOW(), p.start_date) as actual_duration,
                    c.name as contractor_name,
                    c.performance_score,
                    COUNT(DISTINCT cpv.id) as citizen_votes
                FROM projects p
                LEFT JOIN contractors c ON p.contractor_id = c.id
                LEFT JOIN citizen_project_votes cpv ON p.id = cpv.project_id
                WHERE p.created_at >= ? AND p.created_at <= ?
                GROUP BY p.id
                ORDER BY p.status, p.progress DESC
            ');
            
            $stmt->execute([$start_date, $end_date]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate summaries by status
            $summary = [
                'total_projects' => count($data),
                'completed' => 0,
                'active' => 0,
                'delayed' => 0,
                'on_hold' => 0,
                'average_completion' => 0,
                'total_citizen_votes' => 0
            ];
            
            $completion_sum = 0;
            foreach ($data as $row) {
                $summary['total_citizen_votes'] += $row['citizen_votes'];
                $completion_sum += $row['progress'];
                
                if ($row['status'] === 'completed') $summary['completed']++;
                elseif ($row['status'] === 'active') $summary['active']++;
                elseif ($row['status'] === 'delayed') $summary['delayed']++;
                elseif ($row['status'] === 'on_hold') $summary['on_hold']++;
            }
            
            $summary['average_completion'] = count($data) > 0 ? round($completion_sum / count($data), 2) : 0;
            
            $report_data = [
                'report_type' => 'completion',
                'summary' => $summary,
                'details' => $data
            ];
            
            // Store report
            $this->storeReport(null, 'completion', $start_date, $end_date, $report_data, $generated_by);
            
            return ['success' => true, 'report' => $report_data];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Report generation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate barangay infrastructure report
     */
    public function generateBarangayInfrastructureReport($barangay, $start_date, $end_date, $generated_by): array {
        try {
            $stmt = $this->db->prepare('
                SELECT 
                    p.project_code,
                    p.name,
                    p.description,
                    p.status,
                    p.progress,
                    p.budget,
                    p.start_date,
                    p.end_date,
                    c.name as contractor_name,
                    c.credibility_score,
                    pps.final_priority_score,
                    COUNT(DISTINCT cpv.id) as citizen_votes,
                    AVG(cpv.urgency_score) as average_urgency
                FROM projects p
                LEFT JOIN contractors c ON p.contractor_id = c.id
                LEFT JOIN project_priority_scores pps ON p.id = pps.project_id
                LEFT JOIN citizen_project_votes cpv ON p.id = cpv.project_id
                WHERE p.barangay = ? 
                    AND p.created_at >= ? 
                    AND p.created_at <= ?
                GROUP BY p.id
                ORDER BY pps.final_priority_score DESC, p.status
            ');
            
            $stmt->execute([$barangay, $start_date, $end_date]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate summaries
            $summary = [
                'barangay' => $barangay,
                'total_projects' => count($data),
                'total_budget' => 0,
                'projects_by_status' => [],
                'average_progress' => 0,
                'completed_projects' => 0
            ];
            
            $progress_sum = 0;
            foreach ($data as $row) {
                $summary['total_budget'] += $row['budget'];
                $progress_sum += $row['progress'];
                
                if ($row['status'] === 'completed') {
                    $summary['completed_projects']++;
                }
                
                if (!isset($summary['projects_by_status'][$row['status']])) {
                    $summary['projects_by_status'][$row['status']] = 0;
                }
                $summary['projects_by_status'][$row['status']]++;
            }
            
            $summary['average_progress'] = count($data) > 0 ? round($progress_sum / count($data), 2) : 0;
            
            $report_data = [
                'report_type' => 'barangay_infrastructure',
                'summary' => $summary,
                'details' => $data
            ];
            
            // Store report
            $this->storeReport(null, 'barangay_infrastructure', $start_date, $end_date, $report_data, $generated_by);
            
            return ['success' => true, 'report' => $report_data];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Report generation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate priority ranking report
     */
    public function generatePriorityRankingReport($start_date, $end_date, $generated_by): array {
        try {
            $stmt = $this->db->prepare('
                SELECT 
                    pps.ranking,
                    p.project_code,
                    p.name,
                    p.location,
                    p.barangay,
                    p.budget,
                    p.status,
                    pps.final_priority_score,
                    pps.community_votes_score,
                    pps.urgency_score,
                    pps.budget_feasibility_score,
                    pps.contractor_credibility_score,
                    COUNT(DISTINCT cpv.id) as total_votes,
                    c.credibility_score as contractor_credibility
                FROM project_priority_scores pps
                JOIN projects p ON pps.project_id = p.id
                LEFT JOIN citizen_project_votes cpv ON p.id = cpv.project_id
                LEFT JOIN contractors c ON p.contractor_id = c.id
                WHERE p.created_at >= ? AND p.created_at <= ?
                    AND p.approval_status = "approved_for_implementation"
                GROUP BY pps.id
                ORDER BY pps.ranking ASC
            ');
            
            $stmt->execute([$start_date, $end_date]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $report_data = [
                'report_type' => 'priority_ranking',
                'summary' => [
                    'total_projects' => count($data),
                    'generated_date' => date('Y-m-d H:i:s'),
                    'report_period' => "$start_date to $end_date"
                ],
                'details' => $data
            ];
            
            // Store report
            $this->storeReport(null, 'priority_ranking', $start_date, $end_date, $report_data, $generated_by);
            
            return ['success' => true, 'report' => $report_data];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Report generation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate performance report
     */
    public function generatePerformanceReport($start_date, $end_date, $generated_by): array {
        try {
            $stmt = $this->db->prepare('
                SELECT 
                    c.id,
                    c.name as contractor_name,
                    c.performance_score,
                    c.credibility_score,
                    c.is_blacklisted,
                    COUNT(DISTINCT p.id) as total_projects,
                    SUM(CASE WHEN p.status = "completed" THEN 1 ELSE 0 END) as completed_projects,
                    AVG(p.progress) as average_progress,
                    SUM(p.budget) as total_budget_handled
                FROM contractors c
                LEFT JOIN projects p ON c.id = p.contractor_id 
                    AND p.created_at >= ? AND p.created_at <= ?
                WHERE c.status = "active"
                GROUP BY c.id
                ORDER BY c.credibility_score DESC
            ');
            
            $stmt->execute([$start_date, $end_date]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $summary = [
                'total_contractors' => count($data),
                'blacklisted_count' => 0,
                'average_credibility' => 0,
                'high_performing' => 0
            ];
            
            $credibility_sum = 0;
            foreach ($data as $row) {
                if ($row['is_blacklisted']) $summary['blacklisted_count']++;
                if ($row['credibility_score'] >= 7) $summary['high_performing']++;
                $credibility_sum += $row['credibility_score'];
            }
            
            $summary['average_credibility'] = count($data) > 0 ? round($credibility_sum / count($data), 2) : 0;
            
            $report_data = [
                'report_type' => 'performance',
                'summary' => $summary,
                'details' => $data
            ];
            
            // Store report
            $this->storeReport(null, 'performance', $start_date, $end_date, $report_data, $generated_by);
            
            return ['success' => true, 'report' => $report_data];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Report generation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Store report in database
     */
    private function storeReport($project_id, $type, $start_date, $end_date, $data, $generated_by): void {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO project_reports (
                    project_id, report_type, report_period_start, report_period_end,
                    report_data, generated_by
                ) VALUES (?, ?, ?, ?, ?, ?)
            ');
            
            $stmt->execute([
                $project_id,
                $type,
                $start_date,
                $end_date,
                json_encode($data),
                $generated_by
            ]);
        } catch (Exception $e) {
            error_log('Failed to store report: ' . $e->getMessage());
        }
    }
    
    /**
     * Get stored reports
     */
    public function getStoredReports($type = null, $limit = 50): array {
        try {
            $query = 'SELECT * FROM project_reports';
            
            if ($type) {
                $query .= ' WHERE report_type = ?';
                $stmt = $this->db->prepare($query . ' ORDER BY generated_at DESC LIMIT ?');
                $stmt->execute([$type, $limit]);
            } else {
                $stmt = $this->db->prepare($query . ' ORDER BY generated_at DESC LIMIT ?');
                $stmt->execute([$limit]);
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Failed to get reports: ' . $e->getMessage());
            return [];
        }
    }
}
