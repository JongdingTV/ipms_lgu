<?php
/**
 * Shared project workflow helpers.
 *
 * These functions keep the portal handoffs on the same database states:
 * draft -> endorsed -> approved -> bidding -> awarded -> assigned -> active
 * -> completion_inspection -> completed -> turnover, with returned/cancelled
 * as exits back to Admin or out of the pipeline entirely at each review
 * gate. Fund availability is tracked as part of Admin's own Budget &
 * Resources module, not as a separate approval gate — there is no standalone
 * Budget Office in this system.
 */

function projectWorkflowStatuses(): array
{
    return [
        'draft',
        'endorsed',
        'returned',
        'planning',
        'approved',
        'bidding',
        'awarded',
        'assigned',
        'active',
        'delayed',
        'on_hold',
        'completion_inspection',
        'completed',
        'turnover',
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
        if ($type !== '' && strpos($type, "'endorsed'") === false) {
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
        if ($engineerType !== '' && strpos($engineerType, "'endorsed'") === false) {
            $db->exec('ALTER TABLE engineer_status_updates MODIFY status ' . projectWorkflowStatusEnumSql() . " NOT NULL DEFAULT 'active'");
        }
    } catch (Throwable $e) {
        // Keep older installations usable even when schema inspection is unavailable.
    }

    // Registration/approval tracking columns — self-healing since this repo has
    // no migration runner (see OTPManager::ensureTable() for the same pattern).
    try {
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS created_by INT NULL AFTER status");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS approved_by INT NULL AFTER created_by");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER approved_by");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL AFTER approved_at");

        // Phase 1 lifecycle expansion: Engineering Review, Notice to Proceed,
        // Completion Inspection, Turnover — each stamped with who/when/why so
        // the same audit-trail convention as approval covers these too.
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS engineering_reviewed_by INT NULL AFTER rejection_reason");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS engineering_reviewed_at DATETIME NULL AFTER engineering_reviewed_by");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS engineering_remarks TEXT NULL AFTER engineering_reviewed_at");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS ntp_issued_by INT NULL AFTER engineering_remarks");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS ntp_issued_at DATETIME NULL AFTER ntp_issued_by");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS ntp_notes TEXT NULL AFTER ntp_issued_at");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS completion_inspected_by INT NULL AFTER ntp_notes");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS completion_inspected_at DATETIME NULL AFTER completion_inspected_by");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS completion_remarks TEXT NULL AFTER completion_inspected_at");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS turnover_by INT NULL AFTER completion_remarks");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS turnover_at DATETIME NULL AFTER turnover_by");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS turnover_office VARCHAR(180) NULL AFTER turnover_at");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS turnover_notes TEXT NULL AFTER turnover_office");

        // A standalone Budget Office approval gate was tried and then folded
        // back into Admin's own Budget & Resources module — drop the columns
        // that only that removed gate ever wrote to, so nothing dead lingers.
        $db->exec("ALTER TABLE projects DROP COLUMN IF EXISTS budget_verified_by");
        $db->exec("ALTER TABLE projects DROP COLUMN IF EXISTS budget_verified_at");
        $db->exec("ALTER TABLE projects DROP COLUMN IF EXISTS budget_verification_remarks");
        $db->exec("ALTER TABLE projects DROP COLUMN IF EXISTS fund_source");
        $db->exec("ALTER TABLE projects DROP COLUMN IF EXISTS certificate_number");

        // GIS map coordinates — same DECIMAL(10,7) precision feedback already
        // uses for its own optional map pin.
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL AFTER turnover_notes");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL AFTER latitude");

        // Project classification, added to Project Registration — informational
        // only (no workflow gate reads these), unrelated to the old fund_source
        // column dropped above, which belonged to a removed approval step.
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS category " . projectCategoryEnumSql() . " NULL AFTER longitude");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS funding_source " . projectFundingSourceEnumSql() . " NULL AFTER category");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS implementing_office VARCHAR(150) NULL AFTER funding_source");
        $db->exec("ALTER TABLE projects ADD COLUMN IF NOT EXISTS physical_target VARCHAR(255) NULL AFTER implementing_office");
    } catch (Throwable $e) {
    }
}

// The feedback table's category ENUM widening, infrastructure_type column,
// and district/barangay/latitude/longitude columns were all added directly
// on the dev database (when CIMMS integration and the QC map picker were
// built) and only ever captured in database/migrations/feedback_schema_
// catchup.sql — a hand-run .sql file nobody actually ran against the live
// database. A fresh/production database never gets any of it, so every
// query touching these (submit-feedback.php, my-feedback.php, citizen/api/
// dashboard.php) fatals with an unknown-column or truncated-enum SQL error
// instead of returning JSON. Self-healing this closes that gap for good,
// consistent with how every other schema drift in this app is handled.
function feedbackEnsureSchema(PDO $db): void
{
    try {
        $stmt = $db->query("
            SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'feedback' AND COLUMN_NAME = 'category'
        ");
        $columnType = (string) $stmt->fetchColumn();
        if (strpos($columnType, "'road_damage'") === false) {
            $db->exec("ALTER TABLE feedback MODIFY COLUMN category ENUM('complaint','road_damage','drainage_flooding','streetlight','sidewalk_accessibility','safety_hazard','project_delay','suggestion','inquiry','commendation') DEFAULT 'complaint'");
        }

        $db->exec("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS infrastructure_type VARCHAR(100) NULL AFTER category");
        $db->exec("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS district VARCHAR(20) NULL AFTER contact_email");
        $db->exec("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS barangay VARCHAR(100) NULL AFTER district");
        $db->exec("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL AFTER barangay");
        $db->exec("ALTER TABLE feedback ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL AFTER latitude");
        $db->exec("ALTER TABLE feedback ADD INDEX IF NOT EXISTS idx_feedback_concern_type (concern_type)");
        $db->exec("ALTER TABLE feedback ADD INDEX IF NOT EXISTS idx_feedback_cimm_sync (cimm_sync_status)");

        $db->exec("
            CREATE TABLE IF NOT EXISTS feedback_photos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                feedback_id INT NOT NULL,
                photo_path VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_feedback_photos_feedback (feedback_id),
                CONSTRAINT fk_feedback_photos_feedback FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
    }
}

function projectCategoryEnumSql(): string
{
    return "ENUM('Roads and Bridges','Drainage and Flood Control','Water Supply','Public Buildings and Facilities','Street Lighting','Parks and Recreation','Other')";
}

function projectFundingSourceEnumSql(): string
{
    return "ENUM('LGU General Fund','20% Development Fund','National Government Fund','Grant/Donor Fund','Special Education Fund','Other')";
}

/**
 * Self-healing version-history columns for supporting_documents. A re-upload
 * against an existing document creates a new row (version = old + 1,
 * root_document_id shared across the whole chain, is_current flips to the
 * newest row) rather than overwriting the file in place, so every prior
 * version stays on disk and queryable.
 */
function documentsEnsureVersioningSchema(PDO $db): void
{
    try {
        $db->exec("ALTER TABLE supporting_documents ADD COLUMN IF NOT EXISTS version INT UNSIGNED NOT NULL DEFAULT 1 AFTER mime_type");
        $db->exec("ALTER TABLE supporting_documents ADD COLUMN IF NOT EXISTS root_document_id INT NULL AFTER version");
        $db->exec("ALTER TABLE supporting_documents ADD COLUMN IF NOT EXISTS is_current TINYINT(1) NOT NULL DEFAULT 1 AFTER root_document_id");
        $db->exec("ALTER TABLE supporting_documents ADD COLUMN IF NOT EXISTS superseded_at DATETIME NULL AFTER is_current");
        $db->exec("UPDATE supporting_documents SET root_document_id = id WHERE root_document_id IS NULL");
    } catch (Throwable $e) {
    }
}

/**
 * Uploads a new version of an existing supporting_documents row: inserts a
 * new row carrying the same owner/type/title with the incremented version
 * number, marks the prior row superseded, and returns the new row's id.
 * Throws on any failure — caller is expected to already be inside its own
 * try/transaction (matches how project document uploads already work).
 */
function documentsCreateNewVersion(PDO $db, int $existingDocId, array $storedFile, int $uploadedBy): int
{
    $stmt = $db->prepare("SELECT * FROM supporting_documents WHERE id = ? AND is_current = 1");
    $stmt->execute([$existingDocId]);
    $current = $stmt->fetch();
    if (!$current) {
        throw new RuntimeException('Document not found or not the current version.');
    }

    $rootId = (int) ($current['root_document_id'] ?: $current['id']);
    $nextVersion = (int) $current['version'] + 1;

    $db->prepare("
        INSERT INTO supporting_documents
            (owner_type, owner_id, document_type, title, original_name, file_path, file_size, mime_type, version, root_document_id, is_current, uploaded_by, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 'pending')
    ")->execute([
        $current['owner_type'],
        $current['owner_id'],
        $current['document_type'],
        $current['title'],
        $storedFile['original_name'],
        $storedFile['stored_path'],
        $storedFile['file_size'],
        $storedFile['mime_type'],
        $nextVersion,
        $rootId,
        $uploadedBy,
    ]);
    $newId = (int) $db->lastInsertId();

    $db->prepare("UPDATE supporting_documents SET is_current = 0, superseded_at = NOW() WHERE id = ?")
        ->execute([$existingDocId]);

    return $newId;
}

/** Self-healing 'application_status' column so an unreviewed contractor application is distinct from an approved business record. */
function contractorsEnsureApplicationSchema(PDO $db): void
{
    try {
        $stmt = $db->query("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contractors' AND COLUMN_NAME = 'application_status'
        ");
        $alreadyExists = (int) $stmt->fetchColumn() > 0;

        if (!$alreadyExists) {
            $db->exec("ALTER TABLE contractors ADD COLUMN application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER status");
            $db->exec("ALTER TABLE contractors ADD COLUMN application_reviewed_by INT NULL AFTER application_status");
            $db->exec("ALTER TABLE contractors ADD COLUMN application_reviewed_at DATETIME NULL AFTER application_reviewed_by");
            $db->exec("ALTER TABLE contractors ADD COLUMN application_remarks TEXT NULL AFTER application_reviewed_at");

            // One-time backfill only: pre-existing contractor rows (created before
            // this feature existed) are already legitimate, vetted business
            // records — mark them approved so they don't vanish from assignment
            // lists. This branch only ever runs the moment the column is first
            // added, never again, so applications submitted afterward keep their
            // real 'pending' status.
            $db->exec("UPDATE contractors SET application_status = 'approved'");
        }

        // PCAB (Philippine Contractors Accreditation Board) license is the actual
        // eligibility check BAC needs against a project's budget/scope — without it
        // this was buried inside an unstructured uploaded file, unqueryable.
        $db->exec("ALTER TABLE contractors ADD COLUMN IF NOT EXISTS pcab_license_no VARCHAR(50) NULL AFTER address");
        $db->exec("ALTER TABLE contractors ADD COLUMN IF NOT EXISTS pcab_classification ENUM('Small B','Small A','Medium B','Medium A','Large B','Large A') NULL AFTER pcab_license_no");

        // These four also exist in database/migrations/implement_panelist_requirements.sql,
        // but that file isn't guaranteed to have been run against every
        // deployment of this database — self-heal them here too, the same way
        // every other schema addition in this file works, rather than relying
        // on a standalone migration script having been executed by hand.
        $db->exec("ALTER TABLE contractors ADD COLUMN IF NOT EXISTS credibility_score DECIMAL(3,2) NOT NULL DEFAULT 5.00 AFTER performance_score");
        $db->exec("ALTER TABLE contractors ADD COLUMN IF NOT EXISTS is_blacklisted TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        $db->exec("ALTER TABLE contractors ADD COLUMN IF NOT EXISTS blacklist_reason TEXT NULL AFTER is_blacklisted");
        $db->exec("ALTER TABLE contractors ADD COLUMN IF NOT EXISTS blacklist_date DATETIME NULL AFTER blacklist_reason");
    } catch (Throwable $e) {
    }
}

/**
 * Self-healing schema + seed for the 'hope' role (Head of Procuring Entity),
 * the one lifecycle role added beyond the original six. Widens the
 * users.role ENUM the first time it's missing 'hope', then seeds one demo
 * account the same way database.sql seeds the other portal roles (shared
 * "admin123" hash). A standalone Budget Office role was tried and then
 * folded back into Admin's own Budget & Resources module — see git history
 * if that path is ever revisited.
 */
function usersEnsureLifecycleRoles(PDO $db): void
{
    try {
        $stmt = $db->query("
            SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'
        ");
        $columnType = (string) $stmt->fetchColumn();

        if (strpos($columnType, "'hope'") === false) {
            $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','admin','bac','engineer','contractor','citizen','hope') NOT NULL DEFAULT 'citizen'");
        }

        $seedHash = '$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS';
        $seedAccounts = [
            'hope' => ['hope', 'hope@ipms.local', 'Head of Procuring Entity'],
        ];
        foreach ($seedAccounts as $role => [$username, $email, $fullName]) {
            $countStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
            $countStmt->execute([$role]);
            $exists = (int) $countStmt->fetchColumn();
            if ($exists === 0) {
                $db->prepare("
                    INSERT INTO users (username, email, password_hash, full_name, role, status)
                    VALUES (?, ?, ?, ?, ?, 'active')
                ")->execute([$username, $email, $seedHash, $fullName, $role]);
            }
        }
    } catch (Throwable $e) {
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

    // Lets a contractor submit their own bid (source='contractor') instead of
    // only BAC ever recording one on their behalf (source='bac_recorded').
    // Wrapped separately from the CREATE TABLEs above: this ALTER can fail on
    // dirty pre-existing duplicate (project_id, contractor_id) rows, and this
    // function runs on every contractor/BAC request, so a failure here must
    // degrade to "constraint didn't attach" rather than a site-wide 500.
    try {
        $db->exec("ALTER TABLE bac_bid_submissions ADD COLUMN IF NOT EXISTS source ENUM('bac_recorded','contractor') NOT NULL DEFAULT 'bac_recorded'");
        $db->exec("ALTER TABLE bac_bid_submissions ADD COLUMN IF NOT EXISTS submitted_by INT NULL");
        $db->exec("ALTER TABLE bac_bid_submissions ADD UNIQUE INDEX IF NOT EXISTS idx_bac_bid_unique (project_id, contractor_id)");
    } catch (Throwable $e) {
    }

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

    // HOPE's Contract Award Approval needs a 'rejected' outcome distinct from
    // 'returned' (send back for reconsideration vs. this bid is out), plus a
    // place to record HOPE's own required comment and who/when decided —
    // same self-healing widen pattern usersEnsureLifecycleRoles() uses for
    // users.role.
    try {
        $stmt = $db->query("
            SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bac_award_recommendations' AND COLUMN_NAME = 'status'
        ");
        $columnType = (string) $stmt->fetchColumn();
        if (strpos($columnType, "'rejected'") === false) {
            $db->exec("ALTER TABLE bac_award_recommendations MODIFY COLUMN status ENUM('recommended','sent_to_admin','approved','returned','rejected') NOT NULL DEFAULT 'sent_to_admin'");
        }
        $db->exec("ALTER TABLE bac_award_recommendations ADD COLUMN IF NOT EXISTS hope_remarks TEXT NULL");
        $db->exec("ALTER TABLE bac_award_recommendations ADD COLUMN IF NOT EXISTS decided_by INT NULL");
        $db->exec("ALTER TABLE bac_award_recommendations ADD COLUMN IF NOT EXISTS decided_at DATETIME NULL");
    } catch (Throwable $e) {
    }

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

function projectWorkflowEnsureRoleConnectionTables(PDO $db): void
{
    projectWorkflowEnsureBacTables($db);

    $db->exec("
        CREATE TABLE IF NOT EXISTS contracts (
          id INT AUTO_INCREMENT PRIMARY KEY,
          project_id INT NOT NULL,
          bid_submission_id INT NULL,
          contractor_id INT NOT NULL,
          contract_no VARCHAR(60) NOT NULL UNIQUE,
          contract_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
          notice_to_proceed_date DATE NULL,
          contract_start_date DATE NULL,
          contract_end_date DATE NULL,
          status ENUM('active','completed','terminated') NOT NULL DEFAULT 'active',
          approved_by INT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY idx_contracts_project (project_id),
          INDEX idx_contracts_contractor (contractor_id),
          INDEX idx_contracts_bid_submission (bid_submission_id),
          CONSTRAINT fk_contracts_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
          CONSTRAINT fk_contracts_bid_submission FOREIGN KEY (bid_submission_id) REFERENCES bac_bid_submissions(id) ON DELETE SET NULL,
          CONSTRAINT fk_contracts_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
          CONSTRAINT fk_contracts_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS inspections (
          id INT AUTO_INCREMENT PRIMARY KEY,
          project_id INT NOT NULL,
          progress_report_id INT NULL,
          engineer_id INT NOT NULL,
          inspection_date DATE NOT NULL,
          actual_progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
          findings TEXT NOT NULL,
          recommendation ENUM('approved','needs_correction','for_reinspection') NOT NULL DEFAULT 'approved',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_inspections_project (project_id),
          INDEX idx_inspections_report (progress_report_id),
          INDEX idx_inspections_engineer (engineer_id),
          CONSTRAINT fk_inspections_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
          CONSTRAINT fk_inspections_report FOREIGN KEY (progress_report_id) REFERENCES contractor_reports(id) ON DELETE SET NULL,
          CONSTRAINT fk_inspections_engineer FOREIGN KEY (engineer_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS payment_requests (
          id INT AUTO_INCREMENT PRIMARY KEY,
          project_id INT NOT NULL,
          contractor_id INT NOT NULL,
          progress_report_id INT NULL,
          requested_amount DECIMAL(15,2) NOT NULL,
          billing_no VARCHAR(60) NOT NULL UNIQUE,
          status ENUM('submitted','under_review','approved','rejected','paid') NOT NULL DEFAULT 'submitted',
          remarks TEXT NULL,
          submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_payment_project (project_id),
          INDEX idx_payment_contractor (contractor_id),
          INDEX idx_payment_report (progress_report_id),
          INDEX idx_payment_status (status),
          CONSTRAINT fk_payment_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
          CONSTRAINT fk_payment_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE CASCADE,
          CONSTRAINT fk_payment_report FOREIGN KEY (progress_report_id) REFERENCES contractor_reports(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS payment_reviews (
          id INT AUTO_INCREMENT PRIMARY KEY,
          payment_request_id INT NOT NULL,
          reviewed_by INT NOT NULL,
          reviewer_role ENUM('engineer','admin') NOT NULL,
          remarks TEXT NULL,
          recommendation ENUM('approve','reject','return') NOT NULL,
          reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_payment_review_request (payment_request_id),
          INDEX idx_payment_review_user (reviewed_by),
          CONSTRAINT fk_payment_review_request FOREIGN KEY (payment_request_id) REFERENCES payment_requests(id) ON DELETE CASCADE,
          CONSTRAINT fk_payment_review_user FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Only backfill a contract once HOPE has actually approved the award
    // (bac_award_recommendations.status = 'approved') — this used to also
    // match 'sent_to_admin'/'recommended', which silently auto-activated a
    // contract for a recommendation still awaiting HOPE's decision, on
    // nearly every page load in the system. That's the exact single-signature
    // gap the HOPE Contract Award Approval flow exists to close, so this
    // WHERE clause must stay this narrow.
    $db->exec("
        INSERT INTO contracts
            (project_id, bid_submission_id, contractor_id, contract_no, contract_amount, contract_start_date, contract_end_date, status, approved_by)
        SELECT p.id,
               r.bid_submission_id,
               r.contractor_id,
               CONCAT('CON-', p.project_code),
               r.award_amount,
               p.start_date,
               p.end_date,
               IF(p.status = 'completed', 'completed', 'active'),
               r.recommended_by
        FROM bac_award_recommendations r
        INNER JOIN projects p ON p.id = r.project_id
        WHERE r.status = 'approved'
        ON DUPLICATE KEY UPDATE
            bid_submission_id = VALUES(bid_submission_id),
            contractor_id = VALUES(contractor_id),
            contract_amount = VALUES(contract_amount),
            contract_start_date = VALUES(contract_start_date),
            contract_end_date = VALUES(contract_end_date),
            status = VALUES(status),
            approved_by = VALUES(approved_by)
    ");
}

function projectWorkflowContractNo(array $project): string
{
    $code = preg_replace('/[^A-Za-z0-9-]+/', '-', (string) ($project['project_code'] ?? 'PROJECT'));
    $code = trim((string) $code, '-');

    return 'CON-' . ($code !== '' ? $code : (string) ($project['id'] ?? time()));
}

function projectWorkflowPaymentNo(int $projectId, int $contractorId): string
{
    return 'BILL-' . date('Ymd-His') . '-' . $projectId . '-' . $contractorId;
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

// Maker-checker gate for permanent project deletion: Admin (api/projects.php's
// request_deletion) submits a reason, HOPE (hope/api/portal.php's
// decide_deletion) approves or rejects it, and only an approval actually runs
// DELETE FROM projects. project_code/project_name are snapshotted here so the
// request row still reads meaningfully after the project itself is gone
// (project_id then goes NULL via the FK below, same ON DELETE SET NULL
// pattern bac_procurement_logs already uses for the same reason).
function projectDeletionEnsureSchema(PDO $db): void
{
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS project_deletion_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NULL,
                project_code VARCHAR(20) NOT NULL,
                project_name VARCHAR(200) NOT NULL,
                reason TEXT NOT NULL,
                status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                requested_by INT NULL,
                decided_by INT NULL,
                decided_at DATETIME NULL,
                decision_remarks TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_pdr_status (status),
                INDEX idx_pdr_project (project_id),
                CONSTRAINT fk_pdr_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
                CONSTRAINT fk_pdr_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_pdr_decided_by FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
    }
}
