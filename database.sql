-- ============================================================
-- LGU Infrastructure Project Management System
-- MySQL Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS lgu_infrastructure
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lgu_infrastructure;

-- ── Users ──────────────────────────────────────────────────
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'manager', 'staff', 'viewer') DEFAULT 'staff',
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user
-- Username: admin
-- Password: admin123
INSERT INTO users (username, password_hash, full_name, email, role, is_active) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@lgu.gov.ph', 'admin', TRUE);

-- Insert sample users for testing
INSERT INTO users (username, password_hash, full_name, email, role, is_active) VALUES
('manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Project Manager', 'manager@lgu.gov.ph', 'manager', TRUE),
('staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff Member', 'staff@lgu.gov.ph', 'staff', TRUE),
('viewer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Read Only User', 'viewer@lgu.gov.ph', 'viewer', TRUE);

-- ── Contractors ────────────────────────────────────────────
CREATE TABLE contractors (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(150) NOT NULL,
  contact_person VARCHAR(120),
  email         VARCHAR(180),
  phone         VARCHAR(30),
  address       TEXT,
  performance_score TINYINT UNSIGNED DEFAULT 0 COMMENT '0-100',
  status        ENUM('active','inactive','blacklisted') DEFAULT 'active',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Projects ───────────────────────────────────────────────
CREATE TABLE projects (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  project_code    VARCHAR(20) NOT NULL UNIQUE,
  name            VARCHAR(200) NOT NULL,
  description     TEXT,
  location        VARCHAR(255),
  contractor_id   INT,
  budget          DECIMAL(15,2) DEFAULT 0,
  start_date      DATE,
  end_date        DATE,
  progress        TINYINT UNSIGNED DEFAULT 0 COMMENT '0-100 percent',
  status          ENUM('planning','active','delayed','on_hold','completed','cancelled') DEFAULT 'planning',
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE SET NULL
);

-- ── Milestones ─────────────────────────────────────────────
CREATE TABLE milestones (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  project_id  INT NOT NULL,
  title       VARCHAR(200) NOT NULL,
  due_date    DATE,
  completed   TINYINT(1) DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- ── Budget / Expenses ──────────────────────────────────────
CREATE TABLE expenses (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  project_id    INT NOT NULL,
  category      VARCHAR(100),
  description   TEXT,
  amount        DECIMAL(15,2) NOT NULL,
  expense_date  DATE NOT NULL,
  flagged       TINYINT(1) DEFAULT 0 COMMENT '1 = anomaly flagged',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- ── Feedback & Complaints ──────────────────────────────────
CREATE TABLE feedback (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  project_id  INT,
  citizen_name VARCHAR(120),
  message     TEXT NOT NULL,
  category    ENUM('complaint','suggestion','inquiry') DEFAULT 'complaint',
  priority    ENUM('low','medium','high','urgent') DEFAULT 'medium',
  status      ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
);

-- ── Audit Logs ─────────────────────────────────────────────
CREATE TABLE audit_logs (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT,
  action      VARCHAR(100),
  table_name  VARCHAR(60),
  record_id   INT,
  details     TEXT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Indexes ────────────────────────────────────────────────
CREATE INDEX idx_projects_status    ON projects(status);
CREATE INDEX idx_projects_contractor ON projects(contractor_id);
CREATE INDEX idx_expenses_project   ON expenses(project_id);
CREATE INDEX idx_feedback_priority  ON feedback(priority);
CREATE INDEX idx_feedback_status    ON feedback(status);

-- ============================================================
-- Seed Data
-- ============================================================

-- Default admin (password: Admin@123 — change in production!)
INSERT INTO users (name, email, password, role) VALUES
('Administrator', 'admin@lgu.gov.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Contractors
INSERT INTO contractors (name, contact_person, email, phone, performance_score, status) VALUES
('JKL Builders',          'Jose Lim',        'jose@jklbuilders.ph',   '09171234567', 92, 'active'),
('ABC Construction',      'Ana Cruz',        'ana@abcconstruction.ph', '09181234567', 78, 'active'),
('XYZ Infrastructure',    'Mario Santos',    'mario@xyzinfra.ph',     '09191234567', 65, 'active'),
('Delta Civil Works',     'Diana Reyes',     'diana@deltaworks.ph',   '09171112222', 88, 'active'),
('Omega Builders Inc.',   'Oscar Mendoza',   'oscar@omegabldrs.ph',   '09172223333', 55, 'active');

-- Projects
INSERT INTO projects (project_code, name, description, location, contractor_id, budget, start_date, end_date, progress, status) VALUES
('PRJ-001', 'Road Rehabilitation',      'Rehabilitation of Main Ave pavement',       'Barangay 7, Main Avenue',          1, 2500000.00, '2024-01-10', '2024-06-30', 38, 'delayed'),
('PRJ-002', 'Drainage Improvement',     'Storm drain upgrade Zone 2',                'Zone 2 — Riverside District',      2, 1800000.00, '2024-02-01', '2024-07-15', 55, 'delayed'),
('PRJ-003', 'Municipal Hall Renovation','Full renovation of Municipal Hall',         'Poblacion, Town Center',           3, 4200000.00, '2024-03-01', '2024-12-31', 22, 'delayed'),
('PRJ-004', 'Brgy. Health Center',      'New health center construction',            'Barangay 3, Health District',      4, 800000.00,  '2024-01-15', '2024-05-30', 70, 'active'),
('PRJ-005', 'River Dike Project',       'Flood control dike along Pampanga River',   'Riverside, Barangay 9',            1, 3500000.00, '2024-01-20', '2024-09-30', 45, 'active'),
('PRJ-006', 'Public Market Upgrade',    'Renovation of Central Public Market',       'Central District',                 2, 2100000.00, '2024-04-01', '2024-10-31', 60, 'active'),
('PRJ-007', 'School Building Phase 2',  'Additional classrooms Elem. School',        'Barangay 5',                       4, 1600000.00, '2024-03-15', '2024-08-31', 80, 'active'),
('PRJ-008', 'Street Lighting Project',  'LED streetlight installation',              'All barangays',                    5, 950000.00,  '2024-02-15', '2024-05-15', 95, 'active');

-- Expenses
INSERT INTO expenses (project_id, category, description, amount, expense_date, flagged) VALUES
(1, 'Materials',  'Asphalt and gravel delivery',         450000.00, '2024-01-20', 0),
(1, 'Labor',      'Construction workers week 1-4',       320000.00, '2024-02-15', 0),
(1, 'Equipment',  'Heavy equipment rental',              180000.00, '2024-03-01', 0),
(2, 'Materials',  'PVC pipes and fittings',              280000.00, '2024-02-10', 0),
(2, 'Labor',      'Excavation and pipe laying',          210000.00, '2024-03-10', 0),
(3, 'Materials',  'Cement, steel, lumber',               190000.00, '2024-03-15', 0),
(4, 'Materials',  'Structural materials',                360000.00, '2024-01-25', 0),
(4, 'Misc',       'Unexpected scope: extra room',        320000.00, '2024-03-15', 1),  -- flagged anomaly
(5, 'Materials',  'Riprap boulders',                     580000.00, '2024-02-01', 0),
(5, 'Misc',       'Unusual materials purchase',          320000.00, '2024-03-15', 1);  -- flagged anomaly

-- Feedback
INSERT INTO feedback (project_id, citizen_name, message, category, priority, status) VALUES
(1, 'Juan dela Cruz',  'Potholes in Barangay 7 are getting worse after rains.',       'complaint',   'urgent', 'open'),
(2, 'Maria Santos',    'Flooding issue in Zone 2 not yet resolved after 2 weeks.',    'complaint',   'high',   'in_progress'),
(3, NULL,              'Municipal Hall renovation is very slow with no progress.',    'complaint',   'medium', 'resolved'),
(5, 'Pedro Reyes',     'River dike work stopped for 3 days with no explanation.',     'inquiry',     'high',   'open'),
(NULL, 'Ana Garcia',   'Suggestion: prioritize road works before rainy season.',      'suggestion',  'low',    'open');

-- Milestones
INSERT INTO milestones (project_id, title, due_date, completed) VALUES
(1, 'Site Clearing & Mobilization', '2024-01-20', 1),
(1, 'Base Layer Compaction',         '2024-02-28', 1),
(1, 'Asphalt Layer 1',               '2024-03-31', 0),
(1, 'Asphalt Layer 2 & Finishing',   '2024-05-31', 0),
(2, 'Excavation Complete',           '2024-02-20', 1),
(2, 'Pipe Installation 50%',         '2024-03-20', 1),
(2, 'Pipe Installation 100%',        '2024-04-30', 0),
(3, 'Structural Assessment',         '2024-03-10', 1),
(3, 'Demolition of Old Sections',    '2024-04-15', 0);
