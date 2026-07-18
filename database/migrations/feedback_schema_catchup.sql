-- Catch-up migration: brings an older `feedback` table (category ENUM only
-- complaint/suggestion/inquiry, no district/barangay/photos/CIMMS columns)
-- up to the full shape defined in database.sql. Needed on any environment
-- where cimm_feedback_integration.sql (and the location/photos work before
-- it) was never actually run against the live database — that migration
-- assumes district/barangay/category-widening/feedback_photos already
-- exist, which was not true here. Every clause below is idempotent so it
-- is safe to run regardless of which pieces are already present.

USE lgu_infrastructure;

ALTER TABLE feedback
  MODIFY COLUMN category ENUM('complaint','road_damage','drainage_flooding','streetlight','sidewalk_accessibility','safety_hazard','project_delay','suggestion','inquiry','commendation') DEFAULT 'complaint' COMMENT 'Keep in sync with citizen/includes/feedback-categories.php';

ALTER TABLE feedback
  ADD COLUMN IF NOT EXISTS infrastructure_type VARCHAR(100) NULL COMMENT 'CIMMS maintenance reports: affected infrastructure (Roads, Street Lights, ...)' AFTER category,
  ADD COLUMN IF NOT EXISTS concern_type ENUM('project','maintenance') NOT NULL DEFAULT 'project' COMMENT 'maintenance concerns are forwarded to CIMMS' AFTER infrastructure_type,
  ADD COLUMN IF NOT EXISTS anonymous TINYINT(1) NOT NULL DEFAULT 0 AFTER concern_type,
  ADD COLUMN IF NOT EXISTS contact_name VARCHAR(120) NULL AFTER anonymous,
  ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(30) NULL AFTER contact_name,
  ADD COLUMN IF NOT EXISTS contact_email VARCHAR(180) NULL AFTER contact_phone,
  ADD COLUMN IF NOT EXISTS cimm_sync_status ENUM('none','pending','synced','failed') NOT NULL DEFAULT 'none' AFTER contact_email,
  ADD COLUMN IF NOT EXISTS cimm_request_id VARCHAR(64) NULL AFTER cimm_sync_status,
  ADD COLUMN IF NOT EXISTS cimm_reference VARCHAR(64) NULL AFTER cimm_request_id,
  ADD COLUMN IF NOT EXISTS cimm_synced_at DATETIME NULL AFTER cimm_reference,
  ADD COLUMN IF NOT EXISTS cimm_last_error TEXT NULL AFTER cimm_synced_at,
  ADD COLUMN IF NOT EXISTS district VARCHAR(20) NULL COMMENT 'QC congressional district, e.g. District 1' AFTER priority,
  ADD COLUMN IF NOT EXISTS barangay VARCHAR(100) NULL COMMENT 'QC barangay within the district' AFTER district,
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL COMMENT 'Exact pinned spot (optional)' AFTER barangay,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL COMMENT 'Exact pinned spot (optional)' AFTER latitude;

ALTER TABLE feedback
  ADD INDEX IF NOT EXISTS idx_feedback_concern_type (concern_type),
  ADD INDEX IF NOT EXISTS idx_feedback_cimm_sync (cimm_sync_status);

CREATE TABLE IF NOT EXISTS feedback_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  feedback_id INT NOT NULL,
  photo_path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_feedback_photos_feedback (feedback_id),
  CONSTRAINT fk_feedback_photos_feedback FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
