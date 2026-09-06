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
    implementing_office VARCHAR(180) NULL,
    physical_target VARCHAR(255) NULL,
    funding_source VARCHAR(100) NULL,
    budget_estimate DECIMAL(15,2) NULL,
    target_start_date DATE NULL,
    target_end_date DATE NULL,
    supporting_information TEXT NULL,
    location VARCHAR(255) NOT NULL,
    district VARCHAR(100) NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    road_geometry LONGTEXT NULL,
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    engineer_id INT NOT NULL,
    status ENUM('draft','submitted','under_review','returned','verified_by_head_office','for_mayor_validation','mayor_validated','mayor_returned') NOT NULL DEFAULT 'draft',
    submitted_at DATETIME NULL,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    return_notes TEXT NULL,
    head_office_review_notes TEXT NULL,
    head_office_verified_by INT NULL,
    head_office_verified_at DATETIME NULL,
    mayor_validated_by INT NULL,
    mayor_validated_at DATETIME NULL,
    mayor_validation_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_project_proposals_engineer (engineer_id),
    INDEX idx_project_proposals_status (status),
    INDEX idx_project_proposals_submitted_at (submitted_at),
    CONSTRAINT fk_project_proposals_engineer FOREIGN KEY (engineer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_proposal_feedback (
    proposal_id INT NOT NULL,
    feedback_id INT NOT NULL,
    PRIMARY KEY (proposal_id, feedback_id),
    CONSTRAINT fk_proposal_feedback_proposal FOREIGN KEY (proposal_id) REFERENCES project_proposals(id) ON DELETE CASCADE,
    CONSTRAINT fk_proposal_feedback_feedback FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE supporting_documents MODIFY owner_type ENUM('user','contractor','engineer','project','proposal','bac_bid') NOT NULL;
