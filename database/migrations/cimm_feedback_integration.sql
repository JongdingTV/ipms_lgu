USE lgu_infrastructure;

ALTER TABLE feedback
  ADD COLUMN concern_type ENUM('project','maintenance') NOT NULL DEFAULT 'project'
    COMMENT 'project = IPMS only; maintenance = also push to CIMMS'
    AFTER category,
  ADD COLUMN anonymous TINYINT(1) NOT NULL DEFAULT 0 AFTER concern_type,
  ADD COLUMN contact_name VARCHAR(120) NULL AFTER anonymous,
  ADD COLUMN contact_phone VARCHAR(30) NULL AFTER contact_name,
  ADD COLUMN contact_email VARCHAR(180) NULL AFTER contact_phone,
  ADD COLUMN cimm_sync_status ENUM('none','pending','synced','failed') NOT NULL DEFAULT 'none'
    AFTER contact_email,
  ADD COLUMN cimm_request_id VARCHAR(64) NULL AFTER cimm_sync_status,
  ADD COLUMN cimm_reference VARCHAR(64) NULL AFTER cimm_request_id,
  ADD COLUMN cimm_synced_at DATETIME NULL AFTER cimm_reference,
  ADD COLUMN cimm_last_error TEXT NULL AFTER cimm_synced_at;

CREATE INDEX idx_feedback_concern_type ON feedback(concern_type);
CREATE INDEX idx_feedback_cimm_sync ON feedback(cimm_sync_status);