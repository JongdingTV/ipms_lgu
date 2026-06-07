-- Migration: contractor portal account link, sample data, reports, and documents.

USE lgu_infrastructure;

ALTER TABLE contractors
    ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER id;

ALTER TABLE contractors
    ADD UNIQUE INDEX IF NOT EXISTS idx_contractors_user_id (user_id);

CREATE TABLE IF NOT EXISTS contractor_reports (
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

CREATE TABLE IF NOT EXISTS contractor_documents (
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

INSERT INTO users (username, email, password_hash, full_name, role, status)
VALUES ('contractor', 'contractor@ipms.local', '$2y$10$agsoq.Rb6H.46p12Yi9cTuG8c0so8mlUzWUt2O8Q8uu7E/E5NeEt6', 'Accredited Contractor', 'contractor', 'active')
ON DUPLICATE KEY UPDATE
    email = VALUES(email),
    password_hash = VALUES(password_hash),
    full_name = VALUES(full_name),
    role = VALUES(role),
    status = VALUES(status);

UPDATE contractors c
JOIN users u ON u.username = 'contractor'
SET c.user_id = u.id,
    c.email = u.email,
    c.contact_person = 'Contractor Account',
    c.status = 'active'
WHERE c.name = 'JKL Builders'
  AND (c.user_id IS NULL OR c.user_id = u.id);

INSERT INTO contractors (user_id, name, contact_person, email, phone, address, performance_score, status)
SELECT u.id, 'JKL Builders', 'Contractor Account', u.email, '09171234567', 'Main Avenue Field Office', 92, 'active'
FROM users u
WHERE u.username = 'contractor'
  AND NOT EXISTS (
      SELECT 1 FROM contractors c WHERE c.user_id = u.id
  );

INSERT INTO projects (project_code, name, description, location, contractor_id, budget, start_date, end_date, progress, status)
SELECT 'CTR-001',
       'Road Rehabilitation Package A',
       'Sample assigned project for contractor progress reporting and document submission.',
       'Barangay 7, Main Avenue',
       c.id,
       2500000.00,
       '2026-05-01',
       '2026-09-30',
       35,
       'active'
FROM contractors c
JOIN users u ON u.id = c.user_id
WHERE u.username = 'contractor'
  AND NOT EXISTS (
      SELECT 1 FROM projects p WHERE p.project_code = 'CTR-001'
  );

INSERT INTO milestones (project_id, title, due_date, completed)
SELECT p.id, 'Mobilization and Site Clearing', '2026-05-15', 1
FROM projects p
WHERE p.project_code = 'CTR-001'
  AND NOT EXISTS (
      SELECT 1 FROM milestones m WHERE m.project_id = p.id AND m.title = 'Mobilization and Site Clearing'
  );

INSERT INTO milestones (project_id, title, due_date, completed)
SELECT p.id, 'Base Preparation', '2026-06-15', 0
FROM projects p
WHERE p.project_code = 'CTR-001'
  AND NOT EXISTS (
      SELECT 1 FROM milestones m WHERE m.project_id = p.id AND m.title = 'Base Preparation'
  );

INSERT INTO milestones (project_id, title, due_date, completed)
SELECT p.id, 'Concrete Pouring and Finishing', '2026-08-15', 0
FROM projects p
WHERE p.project_code = 'CTR-001'
  AND NOT EXISTS (
      SELECT 1 FROM milestones m WHERE m.project_id = p.id AND m.title = 'Concrete Pouring and Finishing'
  );

INSERT INTO expenses (project_id, category, description, amount, expense_date, flagged)
SELECT p.id, 'Progress Billing', 'Initial mobilization release', 350000.00, '2026-05-02', 0
FROM projects p
WHERE p.project_code = 'CTR-001'
  AND NOT EXISTS (
      SELECT 1 FROM expenses e WHERE e.project_id = p.id AND e.description = 'Initial mobilization release'
  );
