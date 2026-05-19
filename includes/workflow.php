<?php
/**
 * Shared project workflow helpers.
 *
 * These functions keep the portal handoffs on the same database states:
 * draft -> approved -> bidding -> awarded -> assigned -> active/delayed/completed.
 */

function projectWorkflowStatuses(): array
{
    return [
        'draft',
        'returned',
        'planning',
        'approved',
        'bidding',
        'awarded',
        'assigned',
        'active',
        'delayed',
        'on_hold',
        'completed',
        'cancelled',
    ];
}

function projectWorkflowStatusEnumSql(): string
{
    return "ENUM('" . implode("','", projectWorkflowStatuses()) . "')";
}

function projectWorkflowEnsureProjectStatusSchema(PDO $db): void
{
    try {
        $stmt = $db->query("
            SELECT COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'projects'
              AND COLUMN_NAME = 'status'
            LIMIT 1
        ");
        $type = (string) ($stmt->fetchColumn() ?: '');
        if ($type !== '' && strpos($type, "'approved'") === false) {
            $db->exec('ALTER TABLE projects MODIFY status ' . projectWorkflowStatusEnumSql() . " DEFAULT 'draft'");
        }

        $stmt = $db->query("
            SELECT COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'engineer_status_updates'
              AND COLUMN_NAME = 'status'
            LIMIT 1
        ");
        $engineerType = (string) ($stmt->fetchColumn() ?: '');
        if ($engineerType !== '' && strpos($engineerType, "'assigned'") === false) {
            $db->exec('ALTER TABLE engineer_status_updates MODIFY status ' . projectWorkflowStatusEnumSql() . " NOT NULL DEFAULT 'active'");
        }
    } catch (Throwable $e) {
        // Keep older installations usable even when schema inspection is unavailable.
    }
}

function projectWorkflowEnsureBacTables(PDO $db): void
{
    projectWorkflowEnsureProjectStatusSchema($db);

    $db->exec("
        CREATE TABLE IF NOT EXISTS bac_bid_announcements (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS bac_bid_submissions (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS bac_award_recommendations (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS bac_procurement_logs (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function projectWorkflowLog(PDO $db, string $action, ?int $projectId = null, string $details = '', ?int $actorId = null): void
{
    try {
        projectWorkflowEnsureBacTables($db);
        $stmt = $db->prepare("
            INSERT INTO bac_procurement_logs (project_id, actor_id, action, details)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$projectId, $actorId, $action, $details !== '' ? $details : null]);
    } catch (Throwable $e) {
    }
}
