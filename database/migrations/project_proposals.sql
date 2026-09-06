-- Project Proposal phase: separate engineer proposals from official projects.
CREATE TABLE IF NOT EXISTS project_proposals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_code VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    infrastructure_type VARCHAR(100) NULL,
    description TEXT NOT NULL,
    justification TEXT NOT NULL,
    observed_problem TEXT NULL,
    proposed_solution TEXT NULL,
    location VARCHAR(255) NOT NULL,
    district VARCHAR(100) NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    engineer_id INT NOT NULL,
    status ENUM('draft','submitted','under_review') NOT NULL DEFAULT 'draft',
    submitted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_project_proposals_engineer (engineer_id),
    INDEX idx_project_proposals_status (status),
    CONSTRAINT fk_project_proposals_engineer FOREIGN KEY (engineer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_proposal_feedback (
    proposal_id INT NOT NULL,
    feedback_id INT NOT NULL,
    PRIMARY KEY (proposal_id, feedback_id),
    CONSTRAINT fk_proposal_feedback_proposal FOREIGN KEY (proposal_id) REFERENCES project_proposals(id) ON DELETE CASCADE,
    CONSTRAINT fk_proposal_feedback_feedback FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
