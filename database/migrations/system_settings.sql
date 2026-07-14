-- Migration: admin-editable system settings (site info, security policy display values).
-- Display/storage only for security-policy keys this phase — auth/session.php still
-- reads its own hardcoded constants; nothing here changes runtime enforcement yet.

USE lgu_infrastructure;

CREATE TABLE IF NOT EXISTS system_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  value_type ENUM('string','integer','boolean','json') NOT NULL DEFAULT 'string',
  updated_by INT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_system_settings_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value, value_type) VALUES
('site_name', 'LGU Infrastructure Project Management System', 'string'),
('support_email', 'ipms.systemlgu@gmail.com', 'string'),
('session_timeout_minutes', '30', 'integer'),
('login_max_attempts', '5', 'integer'),
('login_lockout_minutes', '15', 'integer'),
('maintenance_mode', '0', 'boolean')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
