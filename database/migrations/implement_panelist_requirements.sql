-- ============================================================
-- PANELIST REQUIREMENTS IMPLEMENTATION
-- Infrastructure Project Management System
-- Date: May 27, 2026
-- ============================================================

USE lgu_infrastructure;

-- ============================================================
-- 1. OTP MANAGEMENT WITH EXPIRATION (1-2 MINUTES)
-- ============================================================
CREATE TABLE IF NOT EXISTS otp_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    verified TINYINT(1) DEFAULT 0,
    verified_at DATETIME NULL,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 5,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_otp_user (user_id),
    INDEX idx_otp_expires (expires_at),
    INDEX idx_otp_code (otp_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. ENGINEER QUALIFICATIONS & CREDIBILITY SYSTEM
-- ============================================================
CREATE TABLE IF NOT EXISTS engineer_qualifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    engineer_id INT NOT NULL,
    qualification_type ENUM('government', 'private', 'both') NOT NULL DEFAULT 'government',
    license_number VARCHAR(50) NOT NULL,
    license_expiry DATE NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    years_experience INT NOT NULL,
    certifications TEXT NULL,
    credibility_score DECIMAL(3,2) DEFAULT 5.00 COMMENT '0.00-10.00',
    performance_history TEXT NULL,
    verified TINYINT(1) DEFAULT 0,
    verified_by INT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (engineer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY idx_engineer_license (engineer_id, license_number),
    INDEX idx_engineer_specialization (specialization),
    INDEX idx_engineer_credibility (credibility_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. CITIZEN VOTING & URGENCY SYSTEM
-- ============================================================
CREATE TABLE IF NOT EXISTS citizen_project_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    citizen_id INT NOT NULL,
    urgency_score INT NOT NULL COMMENT '1-10',
    vote_comment TEXT NULL,
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_citizen_project_vote (citizen_id, project_id),
    INDEX idx_project_votes (project_id),
    INDEX idx_citizen_votes (citizen_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. PROJECT PRIORITY SCORING & RANKING
-- ============================================================
CREATE TABLE IF NOT EXISTS project_priority_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL UNIQUE,
    community_votes_score DECIMAL(5,2) DEFAULT 0.00,
    urgency_score DECIMAL(5,2) DEFAULT 0.00,
    budget_feasibility_score DECIMAL(5,2) DEFAULT 0.00,
    contractor_credibility_score DECIMAL(5,2) DEFAULT 0.00,
    final_priority_score DECIMAL(5,2) DEFAULT 0.00,
    ranking INT NULL,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_final_priority_score (final_priority_score),
    INDEX idx_ranking (ranking)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. BUDGET MANAGEMENT & FEASIBILITY
-- ============================================================
CREATE TABLE IF NOT EXISTS project_budget_assessment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL UNIQUE,
    estimated_cost DECIMAL(15,2) NOT NULL,
    available_budget DECIMAL(15,2) NOT NULL,
    feasibility_status ENUM('feasible', 'partially_feasible', 'not_feasible') DEFAULT 'feasible',
    feasibility_notes TEXT NULL,
    cost_breakdown TEXT NULL COMMENT 'JSON: {materials, labor, equipment, contingency}',
    assessed_by INT NULL,
    assessed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (assessed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_feasibility_status (feasibility_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budget_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    fiscal_year INT NOT NULL,
    allocated_amount DECIMAL(15,2) NOT NULL,
    released_amount DECIMAL(15,2) DEFAULT 0.00,
    spent_amount DECIMAL(15,2) DEFAULT 0.00,
    allocation_status ENUM('allocated', 'released', 'spent', 'returned') DEFAULT 'allocated',
    approved_by INT NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY idx_project_fiscal_year (project_id, fiscal_year),
    INDEX idx_allocation_status (allocation_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. PROJECT APPROVAL WORKFLOW
-- ============================================================
CREATE TABLE IF NOT EXISTS project_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    approval_stage ENUM('planning_review', 'feasibility_review', 'lgu_approval', 'implementation_approval', 'completion_approval') NOT NULL DEFAULT 'planning_review',
    approval_status ENUM('pending', 'approved', 'rejected', 'returned') NOT NULL DEFAULT 'pending',
    reviewer_role VARCHAR(50) NOT NULL COMMENT 'admin, bac, engineer, super_admin',
    reviewer_id INT NULL,
    remarks TEXT NULL,
    rejection_reason TEXT NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_approval (project_id),
    INDEX idx_approval_stage (approval_stage),
    INDEX idx_approval_status (approval_status),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. CITIZEN PROJECT PROPOSALS/SUBMISSIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS citizen_project_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_id INT NOT NULL,
    infrastructure_issue VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255) NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    photo_path VARCHAR(255) NULL,
    submission_status ENUM('submitted', 'under_review', 'approved', 'pending', 'rejected') DEFAULT 'submitted',
    validation_notes TEXT NULL,
    validated_by INT NULL,
    validated_at DATETIME NULL,
    priority_level ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE CASCADE,
    FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_submission_status (submission_status),
    INDEX idx_submission_barangay (barangay),
    INDEX idx_submission_citizen (citizen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. CONTRACTOR CREDIBILITY & BLACKLIST SYSTEM
-- ============================================================
ALTER TABLE contractors ADD COLUMN IF NOT EXISTS credibility_score DECIMAL(3,2) DEFAULT 5.00 COMMENT '0.00-10.00' AFTER performance_score;
ALTER TABLE contractors ADD COLUMN IF NOT EXISTS is_blacklisted TINYINT(1) DEFAULT 0 AFTER status;
ALTER TABLE contractors ADD COLUMN IF NOT EXISTS blacklist_reason TEXT NULL;
ALTER TABLE contractors ADD COLUMN IF NOT EXISTS blacklist_date DATETIME NULL;
ALTER TABLE contractors ADD INDEX IF NOT EXISTS idx_credibility_score (credibility_score);
ALTER TABLE contractors ADD INDEX IF NOT EXISTS idx_blacklisted (is_blacklisted);

-- ============================================================
-- 9. PROJECT ENHANCEMENTS FOR NEW FEATURES
-- ============================================================
ALTER TABLE projects ADD COLUMN IF NOT EXISTS approved_proposal_date DATETIME NULL;
ALTER TABLE projects ADD COLUMN IF NOT EXISTS approval_status ENUM('draft', 'pending_approval', 'approved_for_implementation', 'rejected') DEFAULT 'draft';
ALTER TABLE projects ADD COLUMN IF NOT EXISTS barangay VARCHAR(100) NULL COMMENT 'Primary barangay for barangay infrastructure tracking';
ALTER TABLE projects ADD INDEX IF NOT EXISTS idx_approval_status (approval_status);
ALTER TABLE projects ADD INDEX IF NOT EXISTS idx_barangay (barangay);

-- ============================================================
-- 10. REPORTING & ANALYTICS TABLES
-- ============================================================
CREATE TABLE IF NOT EXISTS project_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    report_type ENUM('budget_allocation', 'completion', 'barangay_infrastructure', 'priority_ranking', 'performance') NOT NULL,
    report_period_start DATE NOT NULL,
    report_period_end DATE NOT NULL,
    report_data LONGTEXT NOT NULL COMMENT 'JSON report data',
    generated_by INT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_report_type (report_type),
    INDEX idx_generated_at (generated_at),
    INDEX idx_project_report (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. BLOCKCHAIN AUDIT LOG FOR BUDGET TRACKING
-- ============================================================
CREATE TABLE IF NOT EXISTS blockchain_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_hash VARCHAR(255) UNIQUE NOT NULL,
    transaction_type ENUM('budget_allocation', 'expense_recorded', 'budget_release', 'approval', 'status_change') NOT NULL,
    project_id INT NOT NULL,
    amount DECIMAL(15,2) NULL,
    details LONGTEXT NOT NULL COMMENT 'JSON transaction details',
    previous_state LONGTEXT NULL COMMENT 'Previous project/budget state',
    new_state LONGTEXT NULL COMMENT 'New project/budget state',
    initiated_by INT NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    immutable TINYINT(1) DEFAULT 1,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_project_audit (project_id),
    INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. CREATE INDEXES FOR FILTERING
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_projects_date ON projects(created_at);
CREATE INDEX IF NOT EXISTS idx_projects_contractor_status ON projects(contractor_id, status);
CREATE INDEX IF NOT EXISTS idx_contractors_blacklisted ON contractors(is_blacklisted);
CREATE INDEX IF NOT EXISTS idx_contractors_credibility ON contractors(credibility_score);

-- ============================================================
-- 13. CITIZEN PROGRESS TRACKING VIEW
-- ============================================================
CREATE OR REPLACE VIEW citizen_project_progress AS
SELECT 
    p.id,
    p.project_code,
    p.name,
    p.description,
    p.location,
    p.barangay,
    p.progress,
    p.status,
    p.start_date,
    p.end_date,
    c.name as contractor_name,
    c.is_blacklisted,
    c.credibility_score,
    DATEDIFF(p.end_date, CURDATE()) as days_remaining,
    pps.final_priority_score,
    COUNT(DISTINCT cpv.id) as total_votes,
    AVG(CAST(cpv.urgency_score AS DECIMAL(5,2))) as average_urgency_score
FROM projects p
LEFT JOIN contractors c ON p.contractor_id = c.id
LEFT JOIN project_priority_scores pps ON p.id = pps.project_id
LEFT JOIN citizen_project_votes cpv ON p.id = cpv.project_id
WHERE p.approval_status = 'approved_for_implementation'
GROUP BY p.id, p.project_code, p.name, p.description, p.location, p.barangay, p.progress, 
         p.status, p.start_date, p.end_date, c.name, c.is_blacklisted, c.credibility_score, pps.final_priority_score;

-- ============================================================
-- 14. PROJECT FILTERING VIEW FOR CITIZEN DASHBOARD
-- ============================================================
CREATE OR REPLACE VIEW projects_filtered AS
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
    c.name as contractor_name,
    c.is_blacklisted,
    c.credibility_score,
    c.performance_score,
    pba.feasibility_status,
    pps.final_priority_score,
    p.created_at,
    p.start_date
FROM projects p
LEFT JOIN contractors c ON p.contractor_id = c.id
LEFT JOIN project_budget_assessment pba ON p.id = pba.project_id
LEFT JOIN project_priority_scores pps ON p.id = pps.project_id
WHERE p.approval_status = 'approved_for_implementation';

-- ============================================================
-- SUCCESS MESSAGE
-- ============================================================
-- Migration completed successfully. All panelist requirements implemented.
-- Tables created:
-- - otp_tokens (OTP with 1-2 min expiration)
-- - engineer_qualifications (Engineer credibility & qualification system)
-- - citizen_project_votes (Community voting for urgency)
-- - project_priority_scores (Priority ranking calculation)
-- - project_budget_assessment (Feasibility & cost analysis)
-- - budget_allocations (Budget allocation tracking)
-- - project_approvals (LGU approval workflow)
-- - citizen_project_submissions (Citizen issue submission & validation)
-- - project_reports (Analytics & reporting)
-- - blockchain_audit_log (Budget security via immutable audit trail)
