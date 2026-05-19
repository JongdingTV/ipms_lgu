-- ============================================================
-- Infrastructure Project Management System
-- Database Schema + Seed Data
-- ============================================================

CREATE DATABASE IF NOT EXISTS lgu_infrastructure
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lgu_infrastructure;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    role ENUM('super_admin', 'admin', 'bac', 'engineer', 'contractor', 'citizen') NOT NULL DEFAULT 'citizen',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_username (username),
    INDEX idx_users_email (email),
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(100) NOT NULL,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    successful TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL,
    INDEX idx_login_identifier (identifier),
    INDEX idx_login_ip (ip_address),
    INDEX idx_login_attempted_at (attempted_at),
    CONSTRAINT fk_login_attempt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_activity_user (user_id),
    INDEX idx_activity_action (action),
    INDEX idx_activity_created_at (created_at),
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  contact_person VARCHAR(120),
  email VARCHAR(180),
  phone VARCHAR(30),
  address TEXT,
  performance_score TINYINT UNSIGNED DEFAULT 0 COMMENT '0-100',
  status ENUM('active','inactive','blacklisted') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_contractors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(200) NOT NULL,
  description TEXT,
  location VARCHAR(255),
  contractor_id INT,
  budget DECIMAL(15,2) DEFAULT 0,
  start_date DATE,
  end_date DATE,
  progress TINYINT UNSIGNED DEFAULT 0 COMMENT '0-100 percent',
  status ENUM('draft','returned','planning','approved','bidding','awarded','assigned','active','delayed','on_hold','completed','cancelled') DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE milestones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  due_date DATE,
  completed TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  category VARCHAR(100),
  description TEXT,
  amount DECIMAL(15,2) NOT NULL,
  expense_date DATE NOT NULL,
  flagged TINYINT(1) DEFAULT 0 COMMENT '1 = anomaly flagged',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bac_bid_announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  reference_no VARCHAR(40) NOT NULL UNIQUE,
  published_at DATE NOT NULL,
  deadline DATE NULL,
  status ENUM('draft','posted','pre_bid','open','closed') NOT NULL DEFAULT 'posted',
  notes TEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY idx_bac_announcement_project (project_id),
  INDEX idx_bac_announcement_status (status),
  CONSTRAINT fk_bac_announcement_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_bac_announcement_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bac_bid_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  contractor_id INT NOT NULL,
  bid_amount DECIMAL(15,2) NOT NULL,
  technical_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  delivery_days INT UNSIGNED NULL,
  status ENUM('submitted','for_review','recommended','rejected') NOT NULL DEFAULT 'submitted',
  submitted_at DATE NOT NULL,
  remarks TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_bac_bid_project (project_id),
  INDEX idx_bac_bid_contractor (contractor_id),
  INDEX idx_bac_bid_status (status),
  CONSTRAINT fk_bac_bid_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_bac_bid_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bac_award_recommendations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  bid_submission_id INT NULL,
  contractor_id INT NOT NULL,
  award_amount DECIMAL(15,2) NOT NULL,
  basis TEXT NULL,
  status ENUM('recommended','sent_to_admin','approved','returned') NOT NULL DEFAULT 'sent_to_admin',
  recommended_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY idx_bac_award_project (project_id),
  INDEX idx_bac_award_contractor (contractor_id),
  INDEX idx_bac_award_status (status),
  CONSTRAINT fk_bac_award_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_bac_award_bid FOREIGN KEY (bid_submission_id) REFERENCES bac_bid_submissions(id) ON DELETE SET NULL,
  CONSTRAINT fk_bac_award_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
  CONSTRAINT fk_bac_award_user FOREIGN KEY (recommended_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bac_procurement_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NULL,
  actor_id INT NULL,
  action VARCHAR(100) NOT NULL,
  details TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_bac_log_project (project_id),
  INDEX idx_bac_log_action (action),
  CONSTRAINT fk_bac_log_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_bac_log_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE engineer_project_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  engineer_id INT NOT NULL,
  project_id INT NOT NULL,
  assigned_by INT NULL,
  assignment_notes TEXT NULL,
  status ENUM('active','closed') NOT NULL DEFAULT 'active',
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY idx_engineer_project_unique (engineer_id, project_id),
  INDEX idx_engineer_assignments_engineer (engineer_id),
  INDEX idx_engineer_assignments_project (project_id),
  CONSTRAINT fk_engineer_assignments_engineer FOREIGN KEY (engineer_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_engineer_assignments_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_engineer_assignments_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE engineer_milestone_updates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  milestone_id INT NOT NULL,
  engineer_id INT NOT NULL,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  remarks TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_engineer_milestone_project (project_id),
  INDEX idx_engineer_milestone_engineer (engineer_id),
  CONSTRAINT fk_engineer_milestone_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_engineer_milestone_milestone FOREIGN KEY (milestone_id) REFERENCES milestones(id) ON DELETE CASCADE,
  CONSTRAINT fk_engineer_milestone_engineer FOREIGN KEY (engineer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE engineer_progress_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  engineer_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  caption TEXT NULL,
  file_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  file_size INT UNSIGNED NULL,
  mime_type VARCHAR(120) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_engineer_photos_project (project_id),
  INDEX idx_engineer_photos_engineer (engineer_id),
  CONSTRAINT fk_engineer_photos_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_engineer_photos_engineer FOREIGN KEY (engineer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE engineer_delay_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  engineer_id INT NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  impact_days INT UNSIGNED NOT NULL DEFAULT 0,
  cause TEXT NOT NULL,
  mitigation_plan TEXT NULL,
  status ENUM('submitted','under_review','resolved') NOT NULL DEFAULT 'submitted',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_engineer_delays_project (project_id),
  INDEX idx_engineer_delays_engineer (engineer_id),
  CONSTRAINT fk_engineer_delays_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_engineer_delays_engineer FOREIGN KEY (engineer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE engineer_issue_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  engineer_id INT NOT NULL,
  issue_type VARCHAR(80) NOT NULL DEFAULT 'Site Issue',
  priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  description TEXT NOT NULL,
  recommended_action TEXT NULL,
  status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_engineer_issues_project (project_id),
  INDEX idx_engineer_issues_engineer (engineer_id),
  CONSTRAINT fk_engineer_issues_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_engineer_issues_engineer FOREIGN KEY (engineer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE engineer_status_updates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  engineer_id INT NOT NULL,
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('draft','returned','planning','approved','bidding','awarded','assigned','active','delayed','on_hold','completed','cancelled') NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_engineer_status_project (project_id),
  INDEX idx_engineer_status_engineer (engineer_id),
  CONSTRAINT fk_engineer_status_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_engineer_status_engineer FOREIGN KEY (engineer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractor_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  contractor_id INT NOT NULL,
  submitted_by INT NULL,
  report_date DATE NOT NULL,
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  accomplishments TEXT NOT NULL,
  issues TEXT NULL,
  next_steps TEXT NULL,
  status ENUM('submitted','under_review','accepted','returned') NOT NULL DEFAULT 'submitted',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_contractor_reports_project (project_id),
  INDEX idx_contractor_reports_contractor (contractor_id),
  CONSTRAINT fk_contractor_reports_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_contractor_reports_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
  CONSTRAINT fk_contractor_reports_user FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractor_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  contractor_id INT NOT NULL,
  uploaded_by INT NULL,
  document_type VARCHAR(80) NOT NULL DEFAULT 'General',
  title VARCHAR(180) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_size INT UNSIGNED NULL,
  mime_type VARCHAR(120) NULL,
  remarks TEXT NULL,
  status ENUM('uploaded','under_review','accepted','returned') NOT NULL DEFAULT 'uploaded',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_contractor_documents_project (project_id),
  INDEX idx_contractor_documents_contractor (contractor_id),
  CONSTRAINT fk_contractor_documents_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_contractor_documents_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
  CONSTRAINT fk_contractor_documents_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE citizens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNIQUE NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100),
  email VARCHAR(180) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  date_of_birth DATE NOT NULL,
  gender ENUM('Male', 'Female', 'Other') NOT NULL,
  civil_status ENUM('Single', 'Married', 'Divorced', 'Widowed', 'Separated') NOT NULL,
  address TEXT NOT NULL,
  barangay VARCHAR(100) NOT NULL,
  city VARCHAR(100) NOT NULL,
  province VARCHAR(100) NOT NULL,
  postal_code VARCHAR(10),
  id_type VARCHAR(50) NOT NULL COMMENT 'National ID, Passport, Driver License, etc.',
  id_number VARCHAR(100) NOT NULL UNIQUE,
  id_photo_path VARCHAR(255),
  verification_status ENUM('unverified', 'verified', 'rejected') DEFAULT 'unverified',
  verified_by INT NULL,
  verified_at DATETIME NULL,
  rejection_reason TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_citizens_user (user_id),
  INDEX idx_citizens_verification (verification_status),
  CONSTRAINT fk_citizens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_citizens_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT,
  citizen_id INT,
  citizen_name VARCHAR(120),
  message TEXT NOT NULL,
  category ENUM('complaint','suggestion','inquiry') DEFAULT 'complaint',
  priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
  status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(100),
  table_name VARCHAR(60),
  record_id INT,
  details TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_projects_status ON projects(status);
CREATE INDEX idx_projects_contractor ON projects(contractor_id);
CREATE INDEX idx_expenses_project ON expenses(project_id);
CREATE INDEX idx_feedback_priority ON feedback(priority);
CREATE INDEX idx_feedback_status ON feedback(status);
CREATE INDEX idx_feedback_citizen ON feedback(citizen_id);

-- Demo passwords: superadmin/admin/citizen = admin123, bac = bac123, engineer = engineer123, contractor = contractor123
INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES
('superadmin', 'superadmin@ipms.local', '$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS', 'System Super Admin', 'super_admin', 'active'),
('admin', 'admin@ipms.local', '$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS', 'Infrastructure Admin', 'admin', 'active'),
('bac', 'bac@ipms.local', '$2y$10$7zGMOurLkrd1k9Kkj4w4NeL5402YVTeYO4c.L1zve6aCHG.G4FVjm', 'BAC Secretariat', 'bac', 'active'),
('engineer', 'engineer@ipms.local', '$2y$10$VdpOg0pCQbBgBvy/a9JEnepahLOkR7Oy//W6nGfrZKR0XbHNIsAEW', 'Municipal Engineer', 'engineer', 'active'),
('contractor', 'contractor@ipms.local', '$2y$10$j1aSpoztS.H6zIHCag.J4O7oQFBh/I3FWa1JysczZaoSWqqz1cwDu', 'Accredited Contractor', 'contractor', 'active'),
('citizen', 'citizen@ipms.local', '$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS', 'Citizen Viewer', 'citizen', 'active');

INSERT INTO contractors (user_id, name, contact_person, email, phone, performance_score, status) VALUES
((SELECT id FROM users WHERE username = 'contractor'), 'JKL Builders', 'Contractor Account', 'contractor@ipms.local', '09171234567', 92, 'active'),
(NULL, 'ABC Construction', 'Ana Cruz', 'ana@abcconstruction.ph', '09181234567', 78, 'active'),
(NULL, 'XYZ Infrastructure', 'Mario Santos', 'mario@xyzinfra.ph', '09191234567', 65, 'active'),
(NULL, 'Delta Civil Works', 'Diana Reyes', 'diana@deltaworks.ph', '09171112222', 88, 'active'),
(NULL, 'Omega Builders Inc.', 'Oscar Mendoza', 'oscar@omegabldrs.ph', '09172223333', 55, 'active');

INSERT INTO projects (project_code, name, description, location, contractor_id, budget, start_date, end_date, progress, status) VALUES
('PRJ-001', 'Road Rehabilitation', 'Rehabilitation of Main Ave pavement', 'Barangay 7, Main Avenue', 1, 2500000.00, '2024-01-10', '2024-06-30', 38, 'delayed'),
('PRJ-002', 'Drainage Improvement', 'Storm drain upgrade Zone 2', 'Zone 2 - Riverside District', 2, 1800000.00, '2024-02-01', '2024-07-15', 55, 'delayed'),
('PRJ-003', 'Municipal Hall Renovation', 'Full renovation of Municipal Hall', 'Poblacion, Town Center', 3, 4200000.00, '2024-03-01', '2024-12-31', 22, 'delayed'),
('PRJ-004', 'Brgy. Health Center', 'New health center construction', 'Barangay 3, Health District', 4, 800000.00, '2024-01-15', '2024-05-30', 70, 'active'),
('PRJ-005', 'River Dike Project', 'Flood control dike along Pampanga River', 'Riverside, Barangay 9', 1, 3500000.00, '2024-01-20', '2024-09-30', 45, 'active'),
('PRJ-006', 'Public Market Upgrade', 'Renovation of Central Public Market', 'Central District', 2, 2100000.00, '2024-04-01', '2024-10-31', 60, 'active'),
('PRJ-007', 'School Building Phase 2', 'Additional classrooms Elem. School', 'Barangay 5', 4, 1600000.00, '2024-03-15', '2024-08-31', 80, 'active'),
('PRJ-008', 'Street Lighting Project', 'LED streetlight installation', 'All barangays', 5, 950000.00, '2024-02-15', '2024-05-15', 95, 'active');

INSERT INTO engineer_project_assignments (engineer_id, project_id, assignment_notes, status)
SELECT u.id, p.id, 'Sample field inspection assignment', 'active'
FROM users u
JOIN projects p ON p.project_code IN ('PRJ-001', 'PRJ-002', 'PRJ-004', 'PRJ-005', 'PRJ-007')
WHERE u.username = 'engineer';

INSERT INTO expenses (project_id, category, description, amount, expense_date, flagged) VALUES
(1, 'Materials', 'Asphalt and gravel delivery', 450000.00, '2024-01-20', 0),
(1, 'Labor', 'Construction workers week 1-4', 320000.00, '2024-02-15', 0),
(1, 'Equipment', 'Heavy equipment rental', 180000.00, '2024-03-01', 0),
(2, 'Materials', 'PVC pipes and fittings', 280000.00, '2024-02-10', 0),
(2, 'Labor', 'Excavation and pipe laying', 210000.00, '2024-03-10', 0),
(3, 'Materials', 'Cement, steel, lumber', 190000.00, '2024-03-15', 0),
(4, 'Materials', 'Structural materials', 360000.00, '2024-01-25', 0),
(4, 'Misc', 'Unexpected scope: extra room', 320000.00, '2024-03-15', 1),
(5, 'Materials', 'Riprap boulders', 580000.00, '2024-02-01', 0),
(5, 'Misc', 'Unusual materials purchase', 320000.00, '2024-03-15', 1);

INSERT INTO feedback (project_id, citizen_name, message, category, priority, status) VALUES
(1, 'Juan dela Cruz', 'Potholes in Barangay 7 are getting worse after rains.', 'complaint', 'urgent', 'open'),
(2, 'Maria Santos', 'Flooding issue in Zone 2 not yet resolved after 2 weeks.', 'complaint', 'high', 'in_progress'),
(3, NULL, 'Municipal Hall renovation is very slow with no progress.', 'complaint', 'medium', 'resolved'),
(5, 'Pedro Reyes', 'River dike work stopped for 3 days with no explanation.', 'inquiry', 'high', 'open'),
(NULL, 'Ana Garcia', 'Suggestion: prioritize road works before rainy season.', 'suggestion', 'low', 'open');

INSERT INTO milestones (project_id, title, due_date, completed) VALUES
(1, 'Site Clearing & Mobilization', '2024-01-20', 1),
(1, 'Base Layer Compaction', '2024-02-28', 1),
(1, 'Asphalt Layer 1', '2024-03-31', 0),
(1, 'Asphalt Layer 2 & Finishing', '2024-05-31', 0),
(2, 'Excavation Complete', '2024-02-20', 1),
(2, 'Pipe Installation 50%', '2024-03-20', 1),
(2, 'Pipe Installation 100%', '2024-04-30', 0),
(3, 'Structural Assessment', '2024-03-10', 1),
(3, 'Demolition of Old Sections', '2024-04-15', 0);
