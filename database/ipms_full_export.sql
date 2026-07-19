-- ============================================================
-- IPMS (Infrastructure Project Management System) — Complete Database
-- ============================================================
-- What this is: a single, self-contained SQL file that creates every table
-- the application uses, in its current/correct shape — including the CIMM
-- feedback integration columns (feedback.district, feedback.barangay,
-- feedback.latitude, feedback.longitude, feedback.infrastructure_type, the
-- widened feedback.category ENUM, and the feedback_photos table).
--
-- Why this exists: this repo has no migration runner. Schema changes are
-- normally applied by the app itself on first request ("self-healing"
-- ADD COLUMN IF NOT EXISTS calls), or by hand-running one of the .sql files
-- under database/migrations/. On a fresh database those migrations —
-- especially database/migrations/cimm_feedback_integration.sql and
-- database/migrations/feedback_schema_catchup.sql — are easy to miss, and a
-- missing one causes real API failures (an "Unknown column" SQL error
-- wherever that column is queried). This file is a snapshot of the fully
-- migrated schema, so importing it once gets you the complete, correct
-- structure with nothing left to self-heal or catch up on later.
--
-- How to use this in phpMyAdmin:
--   1. Create an empty database (or select an existing empty one).
--   2. Import tab -> choose this file -> Go.
--   3. Point this project's .env at that database (DB_NAME/DB_USER/DB_PASS)
--      per .env.example.
--
-- Default seed accounts (all created with the same password — change every
-- one of these immediately after import, before this ever goes anywhere
-- public):
--   Password for all seven: admin123
--   super_admin | superadmin | superadmin@ipms.local
--   admin       | admin      | admin@ipms.local
--   bac         | bac        | bac@ipms.local
--   engineer    | engineer   | engineer@ipms.local
--   contractor  | contractor | contractor@ipms.local  (linked to "JKL Builders" below)
--   hope        | hope       | hope@ipms.local
--   citizen     | citizen    | citizen@ipms.local
--
-- Also seeds 4 additional contractor directory entries (no login accounts)
-- so BAC/Admin contractor-assignment screens aren't empty on first login.
-- No project/feedback/expense demo data is included — that's real content
-- meant to come from actually using the app.
-- ============================================================

SET NAMES utf8mb4;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- ------------------------------------------------------------
-- Schema — every table, current and complete
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_action` (`action`),
  KEY `idx_activity_created_at` (`created_at`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `table_name` varchar(60) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bac_award_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bac_award_recommendations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `bid_submission_id` int(11) DEFAULT NULL,
  `contractor_id` int(11) NOT NULL,
  `award_amount` decimal(15,2) NOT NULL,
  `basis` text DEFAULT NULL,
  `status` enum('recommended','sent_to_admin','approved','returned','rejected') NOT NULL DEFAULT 'sent_to_admin',
  `recommended_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `hope_remarks` text DEFAULT NULL,
  `decided_by` int(11) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_bac_award_project` (`project_id`),
  KEY `idx_bac_award_contractor` (`contractor_id`),
  KEY `idx_bac_award_status` (`status`),
  KEY `fk_bac_award_bid` (`bid_submission_id`),
  KEY `fk_bac_award_user` (`recommended_by`),
  CONSTRAINT `fk_bac_award_bid` FOREIGN KEY (`bid_submission_id`) REFERENCES `bac_bid_submissions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bac_award_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bac_award_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bac_award_user` FOREIGN KEY (`recommended_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bac_bid_announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bac_bid_announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `reference_no` varchar(40) NOT NULL,
  `published_at` date NOT NULL,
  `deadline` date DEFAULT NULL,
  `status` enum('draft','posted','pre_bid','open','closed') NOT NULL DEFAULT 'posted',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_no` (`reference_no`),
  UNIQUE KEY `idx_bac_announcement_project` (`project_id`),
  KEY `idx_bac_announcement_status` (`status`),
  KEY `fk_bac_announcement_user` (`created_by`),
  CONSTRAINT `fk_bac_announcement_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bac_announcement_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bac_bid_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bac_bid_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL,
  `bid_amount` decimal(15,2) NOT NULL,
  `technical_score` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `delivery_days` int(10) unsigned DEFAULT NULL,
  `status` enum('submitted','for_review','recommended','rejected') NOT NULL DEFAULT 'submitted',
  `submitted_at` date NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source` enum('bac_recorded','contractor') NOT NULL DEFAULT 'bac_recorded',
  `submitted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_bac_bid_unique` (`project_id`,`contractor_id`),
  KEY `idx_bac_bid_project` (`project_id`),
  KEY `idx_bac_bid_contractor` (`contractor_id`),
  KEY `idx_bac_bid_status` (`status`),
  CONSTRAINT `fk_bac_bid_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bac_bid_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bac_procurement_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bac_procurement_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bac_log_project` (`project_id`),
  KEY `idx_bac_log_action` (`action`),
  KEY `fk_bac_log_actor` (`actor_id`),
  CONSTRAINT `fk_bac_log_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bac_log_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `citizens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `citizens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `email` varchar(180) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `civil_status` enum('Single','Married','Divorced','Widowed','Separated') NOT NULL,
  `address` text NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `id_type` varchar(50) NOT NULL COMMENT 'National ID, Passport, Driver License, etc.',
  `id_number` varchar(100) NOT NULL,
  `id_photo_path` varchar(255) DEFAULT NULL,
  `verification_status` enum('unverified','verified','rejected') DEFAULT 'unverified',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `id_number` (`id_number`),
  KEY `idx_citizens_user` (`user_id`),
  KEY `idx_citizens_verification` (`verification_status`),
  KEY `fk_citizens_verified_by` (`verified_by`),
  CONSTRAINT `fk_citizens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_citizens_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractor_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contractor_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `document_type` varchar(80) NOT NULL DEFAULT 'General',
  `title` varchar(180) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('uploaded','under_review','accepted','returned') NOT NULL DEFAULT 'uploaded',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contractor_documents_project` (`project_id`),
  KEY `idx_contractor_documents_contractor` (`contractor_id`),
  KEY `fk_contractor_documents_user` (`uploaded_by`),
  CONSTRAINT `fk_contractor_documents_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contractor_documents_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contractor_documents_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractor_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contractor_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `report_date` date NOT NULL,
  `progress_percent` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `accomplishments` text NOT NULL,
  `issues` text DEFAULT NULL,
  `next_steps` text DEFAULT NULL,
  `status` enum('submitted','under_review','accepted','returned') NOT NULL DEFAULT 'submitted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contractor_reports_project` (`project_id`),
  KEY `idx_contractor_reports_contractor` (`contractor_id`),
  KEY `fk_contractor_reports_user` (`submitted_by`),
  CONSTRAINT `fk_contractor_reports_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contractor_reports_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contractor_reports_user` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contractors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(120) DEFAULT NULL,
  `email` varchar(180) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `pcab_license_no` varchar(50) DEFAULT NULL,
  `pcab_classification` enum('Small B','Small A','Medium B','Medium A','Large B','Large A') DEFAULT NULL,
  `performance_score` tinyint(3) unsigned DEFAULT 0 COMMENT '0-100',
  `credibility_score` decimal(3,2) NOT NULL DEFAULT 5.00,
  `status` enum('active','inactive','blacklisted') DEFAULT 'active',
  `is_blacklisted` tinyint(1) NOT NULL DEFAULT 0,
  `blacklist_reason` text DEFAULT NULL,
  `blacklist_date` datetime DEFAULT NULL,
  `application_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `application_reviewed_by` int(11) DEFAULT NULL,
  `application_reviewed_at` datetime DEFAULT NULL,
  `application_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_contractors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `bid_submission_id` int(11) DEFAULT NULL,
  `contractor_id` int(11) NOT NULL,
  `contract_no` varchar(60) NOT NULL,
  `contract_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notice_to_proceed_date` date DEFAULT NULL,
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `status` enum('active','completed','terminated') NOT NULL DEFAULT 'active',
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `contract_no` (`contract_no`),
  UNIQUE KEY `idx_contracts_project` (`project_id`),
  KEY `idx_contracts_contractor` (`contractor_id`),
  KEY `idx_contracts_bid_submission` (`bid_submission_id`),
  KEY `fk_contracts_approved_by` (`approved_by`),
  CONSTRAINT `fk_contracts_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contracts_bid_submission` FOREIGN KEY (`bid_submission_id`) REFERENCES `bac_bid_submissions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contracts_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contracts_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `engineer_delay_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `engineer_delay_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `engineer_id` int(11) NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `impact_days` int(10) unsigned NOT NULL DEFAULT 0,
  `cause` text NOT NULL,
  `mitigation_plan` text DEFAULT NULL,
  `status` enum('submitted','under_review','resolved') NOT NULL DEFAULT 'submitted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_engineer_delays_project` (`project_id`),
  KEY `idx_engineer_delays_engineer` (`engineer_id`),
  CONSTRAINT `fk_engineer_delays_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_engineer_delays_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `engineer_issue_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `engineer_issue_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `engineer_id` int(11) NOT NULL,
  `issue_type` varchar(80) NOT NULL DEFAULT 'Site Issue',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `description` text NOT NULL,
  `recommended_action` text DEFAULT NULL,
  `status` enum('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_engineer_issues_project` (`project_id`),
  KEY `idx_engineer_issues_engineer` (`engineer_id`),
  CONSTRAINT `fk_engineer_issues_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_engineer_issues_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `engineer_milestone_updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `engineer_milestone_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `milestone_id` int(11) NOT NULL,
  `engineer_id` int(11) NOT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_engineer_milestone_project` (`project_id`),
  KEY `idx_engineer_milestone_engineer` (`engineer_id`),
  KEY `fk_engineer_milestone_milestone` (`milestone_id`),
  CONSTRAINT `fk_engineer_milestone_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_engineer_milestone_milestone` FOREIGN KEY (`milestone_id`) REFERENCES `milestones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_engineer_milestone_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `engineer_progress_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `engineer_progress_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `engineer_id` int(11) NOT NULL,
  `title` varchar(180) NOT NULL,
  `caption` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_engineer_photos_project` (`project_id`),
  KEY `idx_engineer_photos_engineer` (`engineer_id`),
  CONSTRAINT `fk_engineer_photos_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_engineer_photos_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `engineer_project_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `engineer_project_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `engineer_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assignment_notes` text DEFAULT NULL,
  `status` enum('active','closed') NOT NULL DEFAULT 'active',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_engineer_project_unique` (`engineer_id`,`project_id`),
  KEY `idx_engineer_assignments_engineer` (`engineer_id`),
  KEY `idx_engineer_assignments_project` (`project_id`),
  KEY `fk_engineer_assignments_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_engineer_assignments_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_engineer_assignments_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_engineer_assignments_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `engineer_status_updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `engineer_status_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `engineer_id` int(11) NOT NULL,
  `progress_percent` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `status` enum('draft','endorsed','returned','planning','approved','bidding','awarded','assigned','active','delayed','on_hold','completion_inspection','completed','turnover','cancelled') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_engineer_status_project` (`project_id`),
  KEY `idx_engineer_status_engineer` (`engineer_id`),
  CONSTRAINT `fk_engineer_status_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_engineer_status_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `expense_date` date NOT NULL,
  `flagged` tinyint(1) DEFAULT 0 COMMENT '1 = anomaly flagged',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_expenses_project` (`project_id`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `citizen_id` int(11) DEFAULT NULL,
  `citizen_name` varchar(120) DEFAULT NULL,
  `message` text NOT NULL,
  `category` enum('complaint','road_damage','drainage_flooding','streetlight','sidewalk_accessibility','safety_hazard','project_delay','suggestion','inquiry','commendation') DEFAULT 'complaint' COMMENT 'Keep in sync with citizen/includes/feedback-categories.php',
  `infrastructure_type` varchar(100) DEFAULT NULL COMMENT 'CIMMS maintenance reports: affected infrastructure (Roads, Street Lights, ...)',
  `concern_type` enum('project','maintenance') NOT NULL DEFAULT 'project' COMMENT 'maintenance concerns are forwarded to CIMMS',
  `anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `contact_name` varchar(120) DEFAULT NULL,
  `contact_phone` varchar(30) DEFAULT NULL,
  `contact_email` varchar(180) DEFAULT NULL,
  `cimm_sync_status` enum('none','pending','synced','failed') NOT NULL DEFAULT 'none',
  `cimm_request_id` varchar(64) DEFAULT NULL,
  `cimm_reference` varchar(64) DEFAULT NULL,
  `cimm_synced_at` datetime DEFAULT NULL,
  `cimm_last_error` text DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `district` varchar(20) DEFAULT NULL COMMENT 'QC congressional district, e.g. District 1',
  `barangay` varchar(100) DEFAULT NULL COMMENT 'QC barangay within the district',
  `latitude` decimal(10,7) DEFAULT NULL COMMENT 'Exact pinned spot (optional)',
  `longitude` decimal(10,7) DEFAULT NULL COMMENT 'Exact pinned spot (optional)',
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `idx_feedback_priority` (`priority`),
  KEY `idx_feedback_status` (`status`),
  KEY `idx_feedback_citizen` (`citizen_id`),
  KEY `idx_feedback_concern_type` (`concern_type`),
  KEY `idx_feedback_cimm_sync` (`cimm_sync_status`),
  CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`citizen_id`) REFERENCES `citizens` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feedback_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feedback_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feedback_id` int(11) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_feedback_photos_feedback` (`feedback_id`),
  CONSTRAINT `fk_feedback_photos_feedback` FOREIGN KEY (`feedback_id`) REFERENCES `feedback` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inspections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inspections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `progress_report_id` int(11) DEFAULT NULL,
  `engineer_id` int(11) NOT NULL,
  `inspection_date` date NOT NULL,
  `actual_progress_percent` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `findings` text NOT NULL,
  `recommendation` enum('approved','needs_correction','for_reinspection') NOT NULL DEFAULT 'approved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inspections_project` (`project_id`),
  KEY `idx_inspections_report` (`progress_report_id`),
  KEY `idx_inspections_engineer` (`engineer_id`),
  CONSTRAINT `fk_inspections_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inspections_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inspections_report` FOREIGN KEY (`progress_report_id`) REFERENCES `contractor_reports` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `successful` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_login_identifier` (`identifier`),
  KEY `idx_login_ip` (`ip_address`),
  KEY `idx_login_attempted_at` (`attempted_at`),
  KEY `fk_login_attempt_user` (`user_id`),
  CONSTRAINT `fk_login_attempt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `milestones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `milestones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `due_date` date DEFAULT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `milestones_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(40) NOT NULL DEFAULT 'general',
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`,`is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `otp_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `otp_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `purpose` varchar(30) NOT NULL DEFAULT 'general',
  `otp_code` varchar(10) NOT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `max_attempts` int(10) unsigned NOT NULL DEFAULT 3,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otp_user` (`user_id`),
  KEY `idx_otp_expires` (`expires_at`),
  KEY `idx_otp_user_purpose` (`user_id`,`purpose`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL,
  `progress_report_id` int(11) DEFAULT NULL,
  `requested_amount` decimal(15,2) NOT NULL,
  `billing_no` varchar(60) NOT NULL,
  `status` enum('submitted','under_review','approved','rejected','paid') NOT NULL DEFAULT 'submitted',
  `remarks` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_no` (`billing_no`),
  KEY `idx_payment_project` (`project_id`),
  KEY `idx_payment_contractor` (`contractor_id`),
  KEY `idx_payment_report` (`progress_report_id`),
  KEY `idx_payment_status` (`status`),
  CONSTRAINT `fk_payment_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_report` FOREIGN KEY (`progress_report_id`) REFERENCES `contractor_reports` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_request_id` int(11) NOT NULL,
  `reviewed_by` int(11) NOT NULL,
  `reviewer_role` enum('engineer','admin') NOT NULL,
  `remarks` text DEFAULT NULL,
  `recommendation` enum('approve','reject','return') NOT NULL,
  `reviewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payment_review_request` (`payment_request_id`),
  KEY `idx_payment_review_user` (`reviewed_by`),
  CONSTRAINT `fk_payment_review_request` FOREIGN KEY (`payment_request_id`) REFERENCES `payment_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_review_user` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `project_deletion_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_deletion_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `project_code` varchar(20) NOT NULL,
  `project_name` varchar(200) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_by` int(11) DEFAULT NULL,
  `decided_by` int(11) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decision_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pdr_status` (`status`),
  KEY `idx_pdr_project` (`project_id`),
  KEY `fk_pdr_requested_by` (`requested_by`),
  KEY `fk_pdr_decided_by` (`decided_by`),
  CONSTRAINT `fk_pdr_decided_by` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pdr_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pdr_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_code` varchar(20) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `contractor_id` int(11) DEFAULT NULL,
  `budget` decimal(15,2) DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `progress` tinyint(3) unsigned DEFAULT 0 COMMENT '0-100 percent',
  `status` enum('draft','endorsed','returned','planning','approved','bidding','awarded','assigned','active','delayed','on_hold','completion_inspection','completed','turnover','cancelled') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `engineering_reviewed_by` int(11) DEFAULT NULL,
  `engineering_reviewed_at` datetime DEFAULT NULL,
  `engineering_remarks` text DEFAULT NULL,
  `ntp_issued_by` int(11) DEFAULT NULL,
  `ntp_issued_at` datetime DEFAULT NULL,
  `ntp_notes` text DEFAULT NULL,
  `completion_inspected_by` int(11) DEFAULT NULL,
  `completion_inspected_at` datetime DEFAULT NULL,
  `completion_remarks` text DEFAULT NULL,
  `turnover_by` int(11) DEFAULT NULL,
  `turnover_at` datetime DEFAULT NULL,
  `turnover_office` varchar(180) DEFAULT NULL,
  `turnover_notes` text DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `category` enum('Roads and Bridges','Drainage and Flood Control','Water Supply','Public Buildings and Facilities','Street Lighting','Parks and Recreation','Other') DEFAULT NULL,
  `funding_source` enum('LGU General Fund','20% Development Fund','National Government Fund','Grant/Donor Fund','Special Education Fund','Other') DEFAULT NULL,
  `implementing_office` varchar(150) DEFAULT NULL,
  `physical_target` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_code` (`project_code`),
  KEY `idx_projects_status` (`status`),
  KEY `idx_projects_contractor` (`contractor_id`),
  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sidebar_badge_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sidebar_badge_views` (
  `user_id` int(11) NOT NULL,
  `badge_key` varchar(60) NOT NULL,
  `last_viewed_at` datetime NOT NULL,
  PRIMARY KEY (`user_id`,`badge_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staff_account_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_account_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requested_role` enum('engineer','bac') NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `username` varchar(60) NOT NULL,
  `email` varchar(180) NOT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_req_status` (`status`),
  KEY `fk_staff_req_requested_by` (`requested_by`),
  KEY `fk_staff_req_reviewed_by` (`reviewed_by`),
  CONSTRAINT `fk_staff_req_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_staff_req_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `supporting_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supporting_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_type` enum('user','contractor','engineer','project','bac_bid') NOT NULL,
  `owner_id` int(11) NOT NULL,
  `document_type` varchar(80) NOT NULL DEFAULT 'General',
  `title` varchar(180) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `root_document_id` int(11) DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 1,
  `superseded_at` datetime DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_supporting_documents_owner` (`owner_type`,`owner_id`),
  KEY `idx_supporting_documents_status` (`status`),
  KEY `fk_supporting_documents_uploader` (`uploaded_by`),
  KEY `fk_supporting_documents_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_supporting_documents_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_supporting_documents_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `value_type` enum('string','integer','boolean','json') NOT NULL DEFAULT 'string',
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `fk_system_settings_updater` (`updated_by`),
  CONSTRAINT `fk_system_settings_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `urban_planning_inspection_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `urban_planning_inspection_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inspection_id` int(11) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_up_photos_inspection` (`inspection_id`),
  CONSTRAINT `fk_up_photos_inspection` FOREIGN KEY (`inspection_id`) REFERENCES `urban_planning_inspections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `urban_planning_inspections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `urban_planning_inspections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `road_id` varchar(40) NOT NULL,
  `road_name` varchar(200) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `district` varchar(20) NOT NULL,
  `road_type` varchar(80) DEFAULT NULL,
  `road_length` decimal(8,2) DEFAULT NULL COMMENT 'kilometers',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `requested_by` varchar(150) DEFAULT NULL COMMENT 'requester name/office from the Urban Planning System, not one of our users',
  `request_date` date NOT NULL,
  `road_latitude` decimal(10,7) DEFAULT NULL,
  `road_longitude` decimal(10,7) DEFAULT NULL,
  `external_reference` varchar(64) DEFAULT NULL COMMENT 'Urban Planning System''s own record id, if it provides one',
  `status` enum('pending','assigned','in_progress','completed','returned') NOT NULL DEFAULT 'pending',
  `engineer_id` int(11) DEFAULT NULL,
  `inspection_date` date DEFAULT NULL,
  `road_condition` enum('Excellent','Good','Fair','Poor','Critical') DEFAULT NULL,
  `surface_condition` enum('Excellent','Good','Fair','Poor','Critical') DEFAULT NULL,
  `drainage_condition` enum('Excellent','Good','Fair','Poor','Critical') DEFAULT NULL,
  `sidewalk_condition` enum('Excellent','Good','Fair','Poor','Critical') DEFAULT NULL,
  `streetlight_condition` enum('Excellent','Good','Fair','Poor','Critical') DEFAULT NULL,
  `traffic_sign_condition` enum('Excellent','Good','Fair','Poor','Critical') DEFAULT NULL,
  `overall_condition` enum('Excellent','Good','Fair','Poor','Critical') DEFAULT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT NULL,
  `recommendation` enum('Routine Maintenance','Repair','Rehabilitation','Road Reconstruction','Further Investigation','No Action Needed') DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `inspection_latitude` decimal(10,7) DEFAULT NULL,
  `inspection_longitude` decimal(10,7) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `synced_to_urban_planning_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_urban_planning_status` (`status`),
  KEY `idx_urban_planning_engineer` (`engineer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `role` enum('super_admin','admin','bac','engineer','contractor','citizen','hope') NOT NULL DEFAULT 'citizen',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_username` (`username`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- ------------------------------------------------------------
-- Seed data — one default account per role (password: admin123 for all),
-- plus a small contractor directory so assignment screens aren't empty.
-- ------------------------------------------------------------

INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role`, `status`) VALUES
("superadmin", "superadmin@ipms.local", "$2y$10$ngBQoqYhY7AJI0cqkade3uTQQzKJoX6AzgRh10.dQIbn/wfI8HUnO", "System Super Admin", "super_admin", "active"),
("admin", "admin@ipms.local", "$2y$10$ngBQoqYhY7AJI0cqkade3uTQQzKJoX6AzgRh10.dQIbn/wfI8HUnO", "Infrastructure Admin", "admin", "active"),
("bac", "bac@ipms.local", "$2y$10$ngBQoqYhY7AJI0cqkade3uTQQzKJoX6AzgRh10.dQIbn/wfI8HUnO", "BAC Secretariat", "bac", "active"),
("engineer", "engineer@ipms.local", "$2y$10$ngBQoqYhY7AJI0cqkade3uTQQzKJoX6AzgRh10.dQIbn/wfI8HUnO", "Municipal Engineer", "engineer", "active"),
("contractor", "contractor@ipms.local", "$2y$10$ngBQoqYhY7AJI0cqkade3uTQQzKJoX6AzgRh10.dQIbn/wfI8HUnO", "Accredited Contractor", "contractor", "active"),
("hope", "hope@ipms.local", "$2y$10$ngBQoqYhY7AJI0cqkade3uTQQzKJoX6AzgRh10.dQIbn/wfI8HUnO", "Head of Procuring Entity", "hope", "active"),
("citizen", "citizen@ipms.local", "$2y$10$ngBQoqYhY7AJI0cqkade3uTQQzKJoX6AzgRh10.dQIbn/wfI8HUnO", "Citizen Viewer", "citizen", "active");

-- "JKL Builders" is linked to the demo contractor login above; the rest are
-- reference-only directory entries with no portal account of their own.
INSERT INTO `contractors` (`user_id`, `name`, `contact_person`, `email`, `phone`, `performance_score`, `credibility_score`, `status`, `application_status`) VALUES
((SELECT id FROM `users` WHERE username = "contractor"), "JKL Builders", "Contractor Account", "contractor@ipms.local", "09171234567", 55, 5.00, "active", "approved"),
(NULL, "ABC Construction", "Ana Cruz", "ana@abcconstruction.ph", "09181234567", 63, 5.00, "active", "approved"),
(NULL, "XYZ Infrastructure", "Mario Santos", "mario@xyzinfra.ph", "09191234567", 65, 5.00, "active", "approved"),
(NULL, "Delta Civil Works", "Diana Reyes", "diana@deltaworks.ph", "09171112222", 58, 5.00, "active", "approved"),
(NULL, "Omega Builders Inc.", "Oscar Mendoza", "oscar@omegabldrs.ph", "09172223333", 65, 5.00, "active", "approved");

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
