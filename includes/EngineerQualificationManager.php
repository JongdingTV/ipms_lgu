<?php
/**
 * Engineer Qualification & Credibility Management System
 * Handles verification, specialization, and credibility scoring
 */

require_once __DIR__ . '/../includes/config.php';

class EngineerQualificationManager {
    private $db;
    
    public function __construct() {
        global $pdo;
        $this->db = $pdo;
    }
    
    /**
     * Add or update engineer qualifications
     */
    public function setQualifications($engineer_id, array $data): array {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO engineer_qualifications (
                    engineer_id, qualification_type, license_number, license_expiry,
                    specialization, years_experience, certifications
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    qualification_type = VALUES(qualification_type),
                    license_number = VALUES(license_number),
                    license_expiry = VALUES(license_expiry),
                    specialization = VALUES(specialization),
                    years_experience = VALUES(years_experience),
                    certifications = VALUES(certifications),
                    updated_at = NOW()
            ');
            
            $stmt->execute([
                $engineer_id,
                $data['qualification_type'] ?? 'government',
                $data['license_number'] ?? '',
                $data['license_expiry'] ?? null,
                $data['specialization'] ?? 'General',
                $data['years_experience'] ?? 0,
                $data['certifications'] ?? null
            ]);
            
            return ['success' => true, 'message' => 'Qualifications updated'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to update qualifications: ' . $e->getMessage()];
        }
    }
    
    /**
     * Verify engineer qualifications by admin
     */
    public function verifyQualifications($engineer_id, $verified_by, $is_verified = true): array {
        try {
            $stmt = $this->db->prepare('
                UPDATE engineer_qualifications
                SET verified = ?, verified_by = ?, verified_at = NOW()
                WHERE engineer_id = ?
            ');
            
            $stmt->execute([$is_verified ? 1 : 0, $verified_by, $engineer_id]);
            
            return [
                'success' => true,
                'message' => $is_verified ? 'Engineer verified' : 'Engineer verification rejected'
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Verification failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update engineer credibility score
     */
    public function updateCredibilityScore($engineer_id, $score): array {
        try {
            // Validate score (0.00-10.00)
            $score = max(0, min(10, (float)$score));
            
            $stmt = $this->db->prepare('
                UPDATE engineer_qualifications
                SET credibility_score = ?
                WHERE engineer_id = ?
            ');
            
            $stmt->execute([$score, $engineer_id]);
            
            return ['success' => true, 'score' => $score];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to update score: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get engineer qualifications
     */
    public function getQualifications($engineer_id): ?array {
        try {
            $stmt = $this->db->prepare('
                SELECT * FROM engineer_qualifications
                WHERE engineer_id = ?
                LIMIT 1
            ');
            
            $stmt->execute([$engineer_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Failed to get qualifications: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get verified engineers by specialization
     */
    public function getVerifiedEngineers($specialization = null): array {
        try {
            $query = '
                SELECT 
                    u.id, u.full_name, u.email,
                    eq.qualification_type, eq.specialization, eq.years_experience,
                    eq.credibility_score, eq.verified
                FROM engineer_qualifications eq
                JOIN users u ON eq.engineer_id = u.id
                WHERE eq.verified = 1 AND u.status = "active"
            ';
            
            if ($specialization) {
                $query .= ' AND eq.specialization = ?';
                $stmt = $this->db->prepare($query);
                $stmt->execute([$specialization]);
            } else {
                $stmt = $this->db->prepare($query);
                $stmt->execute();
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Failed to get verified engineers: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate engineer performance score based on project completion
     */
    public function calculatePerformanceScore($engineer_id): float {
        try {
            $stmt = $this->db->prepare('
                SELECT 
                    COUNT(*) as total_projects,
                    SUM(CASE WHEN p.status = "completed" THEN 1 ELSE 0 END) as completed_projects,
                    AVG(CASE WHEN p.status = "completed" THEN 100 ELSE p.progress END) as avg_progress
                FROM engineer_project_assignments epa
                JOIN projects p ON epa.project_id = p.id
                WHERE epa.engineer_id = ? AND epa.status = "active"
            ');
            
            $stmt->execute([$engineer_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['total_projects'] == 0) {
                return 5.0; // Default middle score
            }
            
            $completion_rate = ($result['completed_projects'] / $result['total_projects']) * 10;
            $progress_rate = ($result['avg_progress'] / 100) * 5;
            
            return round($completion_rate + $progress_rate, 2);
        } catch (Exception $e) {
            error_log('Failed to calculate performance: ' . $e->getMessage());
            return 5.0;
        }
    }
    
    /**
     * Check if engineer license is valid
     */
    public function isLicenseValid($engineer_id): bool {
        try {
            $stmt = $this->db->prepare('
                SELECT license_expiry FROM engineer_qualifications
                WHERE engineer_id = ?
            ');
            
            $stmt->execute([$engineer_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return false;
            }
            
            return strtotime($result['license_expiry']) > time();
        } catch (Exception $e) {
            return false;
        }
    }
}
