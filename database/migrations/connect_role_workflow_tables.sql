-- Migration: connect Admin, BAC, Engineer, and Contractor workflow tables.
-- This adds the capstone relationship layer used by the existing portals:
-- BAC award -> contract -> contractor report -> engineer inspection -> payment request/review.

USE lgu_infrastructure;

CREATE TABLE IF NOT EXISTS contracts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  bid_submission_id INT NULL,
  contractor_id INT NOT NULL,
  contract_no VARCHAR(60) NOT NULL UNIQUE,
  contract_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  notice_to_proceed_date DATE NULL,
  contract_start_date DATE NULL,
  contract_end_date DATE NULL,
  status ENUM('active','completed','terminated') NOT NULL DEFAULT 'active',
  approved_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY idx_contracts_project (project_id),
  INDEX idx_contracts_contractor (contractor_id),
  INDEX idx_contracts_bid_submission (bid_submission_id),
  CONSTRAINT fk_contracts_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_contracts_bid_submission FOREIGN KEY (bid_submission_id) REFERENCES bac_bid_submissions(id) ON DELETE SET NULL,
  CONSTRAINT fk_contracts_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
  CONSTRAINT fk_contracts_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inspections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  progress_report_id INT NULL,
  engineer_id INT NOT NULL,
  inspection_date DATE NOT NULL,
  actual_progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  findings TEXT NOT NULL,
  recommendation ENUM('approved','needs_correction','for_reinspection') NOT NULL DEFAULT 'approved',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_inspections_project (project_id),
  INDEX idx_inspections_report (progress_report_id),
  INDEX idx_inspections_engineer (engineer_id),
  CONSTRAINT fk_inspections_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_inspections_report FOREIGN KEY (progress_report_id) REFERENCES contractor_reports(id) ON DELETE SET NULL,
  CONSTRAINT fk_inspections_engineer FOREIGN KEY (engineer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  contractor_id INT NOT NULL,
  progress_report_id INT NULL,
  requested_amount DECIMAL(15,2) NOT NULL,
  billing_no VARCHAR(60) NOT NULL UNIQUE,
  status ENUM('submitted','under_review','approved','rejected','paid') NOT NULL DEFAULT 'submitted',
  remarks TEXT NULL,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_payment_project (project_id),
  INDEX idx_payment_contractor (contractor_id),
  INDEX idx_payment_report (progress_report_id),
  INDEX idx_payment_status (status),
  CONSTRAINT fk_payment_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_report FOREIGN KEY (progress_report_id) REFERENCES contractor_reports(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payment_request_id INT NOT NULL,
  reviewed_by INT NOT NULL,
  reviewer_role ENUM('engineer','admin') NOT NULL,
  remarks TEXT NULL,
  recommendation ENUM('approve','reject','return') NOT NULL,
  reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_payment_review_request (payment_request_id),
  INDEX idx_payment_review_user (reviewed_by),
  CONSTRAINT fk_payment_review_request FOREIGN KEY (payment_request_id) REFERENCES payment_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_review_user FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO contracts
    (project_id, bid_submission_id, contractor_id, contract_no, contract_amount, contract_start_date, contract_end_date, status, approved_by)
SELECT p.id,
       r.bid_submission_id,
       r.contractor_id,
       CONCAT('CON-', p.project_code),
       r.award_amount,
       p.start_date,
       p.end_date,
       IF(p.status = 'completed', 'completed', 'active'),
       r.recommended_by
FROM bac_award_recommendations r
INNER JOIN projects p ON p.id = r.project_id
WHERE r.status IN ('sent_to_admin','approved','recommended')
ON DUPLICATE KEY UPDATE
    bid_submission_id = VALUES(bid_submission_id),
    contractor_id = VALUES(contractor_id),
    contract_amount = VALUES(contract_amount),
    contract_start_date = VALUES(contract_start_date),
    contract_end_date = VALUES(contract_end_date),
    status = VALUES(status),
    approved_by = VALUES(approved_by);
