-- Migration: align local auth foundation with the approved role model.
-- Roles: super_admin, admin, bac, engineer, contractor, citizen

USE lgu_infrastructure;

CREATE TABLE IF NOT EXISTS login_attempts (
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

CREATE TABLE IF NOT EXISTS activity_logs (
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

ALTER TABLE users
    MODIFY role ENUM('super_admin','admin','bac','engineer','contractor','citizen','manager','staff','viewer') NOT NULL DEFAULT 'citizen';

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER role;

UPDATE users SET status = CASE WHEN COALESCE(is_active, 1) = 1 THEN 'active' ELSE 'inactive' END;

UPDATE users
SET username = 'engineer',
    email = 'engineer@ipms.local',
    full_name = 'Municipal Engineer',
    role = 'engineer',
    password_hash = '$2y$10$0YrZc94nbE.8tOJTnMhpg.EKaKWe8XAr.P9Zx7E4p.4y0Q/ejDoPa',
    status = 'active'
WHERE username = 'manager';

UPDATE users
SET username = 'contractor',
    email = 'contractor@ipms.local',
    full_name = 'Accredited Contractor',
    role = 'contractor',
    password_hash = '$2y$10$agsoq.Rb6H.46p12Yi9cTuG8c0so8mlUzWUt2O8Q8uu7E/E5NeEt6',
    status = 'active'
WHERE username = 'staff';

UPDATE users
SET username = 'citizen',
    email = 'citizen@ipms.local',
    full_name = 'Citizen Viewer',
    role = 'citizen',
    password_hash = '$2y$10$EPJR1ZCkgGpwf0cAyBao..6qZ.NjFoOX0LrzzOTO8fxpuketSttwK',
    status = 'active'
WHERE username = 'viewer';

INSERT INTO users (username, email, password_hash, full_name, role, status)
VALUES
('superadmin', 'superadmin@ipms.local', '$2y$10$EPJR1ZCkgGpwf0cAyBao..6qZ.NjFoOX0LrzzOTO8fxpuketSttwK', 'System Super Admin', 'super_admin', 'active'),
('admin', 'admin@ipms.local', '$2y$10$EPJR1ZCkgGpwf0cAyBao..6qZ.NjFoOX0LrzzOTO8fxpuketSttwK', 'Infrastructure Admin', 'admin', 'active'),
('bac', 'bac@ipms.local', '$2y$10$gsau.FpXkCWyPl4aLJRL.OCl9L31UJxF9opqo02CXNxGefX4buoCm', 'BAC Secretariat', 'bac', 'active'),
('engineer', 'engineer@ipms.local', '$2y$10$0YrZc94nbE.8tOJTnMhpg.EKaKWe8XAr.P9Zx7E4p.4y0Q/ejDoPa', 'Municipal Engineer', 'engineer', 'active'),
('contractor', 'contractor@ipms.local', '$2y$10$agsoq.Rb6H.46p12Yi9cTuG8c0so8mlUzWUt2O8Q8uu7E/E5NeEt6', 'Accredited Contractor', 'contractor', 'active'),
('citizen', 'citizen@ipms.local', '$2y$10$EPJR1ZCkgGpwf0cAyBao..6qZ.NjFoOX0LrzzOTO8fxpuketSttwK', 'Citizen Viewer', 'citizen', 'active')
ON DUPLICATE KEY UPDATE
    email = VALUES(email),
    password_hash = VALUES(password_hash),
    full_name = VALUES(full_name),
    role = VALUES(role),
    status = VALUES(status);

ALTER TABLE users
    MODIFY role ENUM('super_admin','admin','bac','engineer','contractor','citizen') NOT NULL DEFAULT 'citizen';
