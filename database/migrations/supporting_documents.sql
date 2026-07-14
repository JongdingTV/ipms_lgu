-- Migration: polymorphic supporting-document uploads for account onboarding
-- (contractor DTI/SEC/PhilGEPS, engineer PRC license, etc.) and other approvals
-- not already covered by the project-scoped contractor_documents table.
-- contractor_documents.project_id is NOT NULL, so it cannot hold a document
-- submitted before a project exists (e.g. onboarding a new contractor/engineer).

USE lgu_infrastructure;

CREATE TABLE IF NOT EXISTS supporting_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('user','contractor','engineer','project','bac_bid') NOT NULL,
  owner_id INT NOT NULL,
  document_type VARCHAR(80) NOT NULL DEFAULT 'General',
  title VARCHAR(180) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_size INT UNSIGNED NULL,
  mime_type VARCHAR(120) NULL,
  uploaded_by INT NULL,
  status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  remarks TEXT NULL,
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_supporting_documents_owner (owner_type, owner_id),
  INDEX idx_supporting_documents_status (status),
  CONSTRAINT fk_supporting_documents_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_supporting_documents_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
