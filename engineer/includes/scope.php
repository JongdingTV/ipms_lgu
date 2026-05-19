<?php

if (!function_exists('engineerScopeCurrentId')) {
    function engineerScopeCurrentId(): ?int
    {
        $user = currentUser();
        if (!$user || ($user['role'] ?? '') !== 'engineer') {
            return null;
        }

        return (int) $user['user_id'];
    }
}

if (!function_exists('engineerScopeEnsureTables')) {
    function engineerScopeEnsureTables(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS engineer_project_assignments (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS engineer_milestone_updates (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS engineer_progress_photos (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS engineer_delay_reports (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS engineer_issue_reports (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS engineer_status_updates (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

if (!function_exists('engineerScopeSeedAssignments')) {
    function engineerScopeSeedAssignments(PDO $db, int $engineerId): void
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM engineer_project_assignments WHERE engineer_id = ?");
        $stmt->execute([$engineerId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $seed = $db->prepare("
            INSERT IGNORE INTO engineer_project_assignments (engineer_id, project_id, assignment_notes, status)
            SELECT ?, p.id, 'Initial field inspection assignment', 'active'
            FROM projects p
            WHERE p.status IN ('delayed','active','assigned','awarded','approved','planning')
            ORDER BY FIELD(p.status, 'delayed', 'active', 'assigned', 'awarded', 'approved', 'planning'), p.id
            LIMIT 5
        ");
        $seed->execute([$engineerId]);
    }
}

if (!function_exists('engineerScopeHasAssignedProject')) {
    function engineerScopeHasAssignedProject(PDO $db, int $engineerId, int $projectId): bool
    {
        $stmt = $db->prepare("
            SELECT 1
            FROM engineer_project_assignments
            WHERE engineer_id = ?
              AND project_id = ?
              AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$engineerId, $projectId]);

        return (bool) $stmt->fetchColumn();
    }
}
