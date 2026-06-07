-- Migration: Create authentication tables and demo users

CREATE TABLE IF NOT EXISTS users (
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

-- Demo passwords: superadmin/admin/citizen = admin123, bac = bac123, engineer = engineer123, contractor = contractor123
INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES
('superadmin', 'superadmin@ipms.local', '$2y$10$EPJR1ZCkgGpwf0cAyBao..6qZ.NjFoOX0LrzzOTO8fxpuketSttwK', 'System Super Admin', 'super_admin', 'active'),
('admin', 'admin@ipms.local', '$2y$10$EPJR1ZCkgGpwf0cAyBao..6qZ.NjFoOX0LrzzOTO8fxpuketSttwK', 'Infrastructure Admin', 'admin', 'active'),
('bac', 'bac@ipms.local', '$2y$10$gsau.FpXkCWyPl4aLJRL.OCl9L31UJxF9opqo02CXNxGefX4buoCm', 'BAC Secretariat', 'bac', 'active'),
('engineer', 'engineer@ipms.local', '$2y$10$0YrZc94nbE.8tOJTnMhpg.EKaKWe8XAr.P9Zx7E4p.4y0Q/ejDoPa', 'Municipal Engineer', 'engineer', 'active'),
('contractor', 'contractor@ipms.local', '$2y$10$agsoq.Rb6H.46p12Yi9cTuG8c0so8mlUzWUt2O8Q8uu7E/E5NeEt6', 'Accredited Contractor', 'contractor', 'active'),
('citizen', 'citizen@ipms.local', '$2y$10$EPJR1ZCkgGpwf0cAyBao..6qZ.NjFoOX0LrzzOTO8fxpuketSttwK', 'Citizen Viewer', 'citizen', 'active');
