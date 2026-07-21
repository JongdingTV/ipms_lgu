-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: ipms_infra
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=714 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,NULL,'login_failed','Invalid login attempt for caviterawen5@gmail.com','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-12 20:27:11'),(2,NULL,'login_failed','Invalid login attempt for caviterawen5@gmail.com','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-12 20:27:18'),(3,NULL,'login_failed','Invalid login attempt for caviterawen5@gmail.com','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-12 20:28:13'),(4,NULL,'login_failed','Invalid login attempt for caviterawen5@gmail.com','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-12 20:28:22'),(5,NULL,'login_failed','Invalid login attempt for caviterawen5@gmail.com','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-12 20:28:54'),(6,1,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-12 21:17:28'),(7,1,'user_status_updated','Citizen Viewer account set to inactive.','::1','curl/8.17.0','2026-07-12 21:19:16'),(8,1,'user_status_updated','Citizen Viewer account set to active.','::1','curl/8.17.0','2026-07-12 21:19:16'),(9,3,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-12 21:20:23'),(10,3,'unauthorized_access','Denied access to /ipms.lgu/superadmin/dashboard.php','::1','curl/8.17.0','2026-07-12 21:20:23'),(11,1,'login_failed','Invalid login attempt for superadmin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-12 21:22:37'),(12,1,'login_failed','Invalid login attempt for superadmin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-12 21:22:48'),(13,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-12 21:23:16'),(14,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-12 21:23:23'),(15,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-12 21:24:31'),(16,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-12 21:26:21'),(17,3,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-12 21:41:11'),(18,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-12 21:41:33'),(19,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-12 21:42:42'),(20,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-12 21:43:42'),(21,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-12 21:43:49'),(22,6,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-12 22:08:29'),(23,4,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-12 22:08:46'),(24,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-12 22:10:22'),(25,1,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-13 22:01:13'),(26,1,'settings_updated','Updated: site_name, support_email, session_timeout_minutes.','::1','curl/8.17.0','2026-07-13 22:01:56'),(27,1,'user_created','Test Engineer Account (engineer) account created.','::1','curl/8.17.0','2026-07-13 22:05:39'),(28,1,'contractor_created','Test Builders Co contractor profile created.','::1','curl/8.17.0','2026-07-13 22:07:12'),(29,1,'document_reviewed','\"DTI Cert 2026\" (contractor #6) verified.','::1','curl/8.17.0','2026-07-13 22:07:34'),(30,NULL,'login_failed','Invalid login attempt for test_eng_1','::1','curl/8.17.0','2026-07-13 22:14:47'),(31,NULL,'login_failed','Invalid login attempt for test_eng_1','::1','curl/8.17.0','2026-07-13 22:14:47'),(32,NULL,'login_failed','Invalid login attempt for test_eng_1','::1','curl/8.17.0','2026-07-13 22:14:48'),(33,NULL,'login_failed','Invalid login attempt for test_eng_1','::1','curl/8.17.0','2026-07-13 22:14:48'),(34,NULL,'login_failed','Invalid login attempt for test_eng_1','::1','curl/8.17.0','2026-07-13 22:14:48'),(35,1,'login_unlocked','test_eng_1 (::1) unlocked, 5 failed attempt(s) cleared.','::1','curl/8.17.0','2026-07-13 22:15:14'),(36,NULL,'login_failed','Invalid login attempt for test_eng_1','::1','curl/8.17.0','2026-07-13 22:15:15'),(37,NULL,'login_failed','Invalid login attempt for caviterawen5@gmail.com','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 22:16:45'),(38,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 22:17:24'),(39,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 22:18:06'),(40,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 22:18:15'),(41,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 22:19:31'),(42,1,'procurement_document_uploaded','1 document(s) attached to Road Rehabilitation (project #1).','::1','curl/8.17.0','2026-07-13 22:25:56'),(43,1,'procurement_document_reviewed','\"ITB - Road Rehabilitation\" (project #1) verified.','::1','curl/8.17.0','2026-07-13 22:26:11'),(44,1,'procurement_document_uploaded','2 document(s) attached to Road Rehabilitation (bac_bid #1).','::1','curl/8.17.0','2026-07-13 22:33:03'),(45,1,'unauthorized_access','Denied access to /ipms.lgu/bac/dashboard.php','::1','curl/8.17.0','2026-07-13 22:43:43'),(46,3,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-13 22:43:58'),(47,3,'procurement_document_uploaded','1 document(s) attached to Road Rehabilitation (bac_bid #2).','::1','curl/8.17.0','2026-07-13 22:44:48'),(48,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 22:46:11'),(49,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 22:47:07'),(50,5,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-13 22:50:17'),(51,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 22:58:31'),(52,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 22:59:46'),(53,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 22:59:52'),(54,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 22:59:59'),(55,5,'login_failed','Invalid login attempt for contractor','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 23:00:39'),(56,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 23:00:44'),(57,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 23:01:28'),(58,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 23:01:40'),(59,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 23:02:35'),(60,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 23:03:42'),(61,4,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-13 23:04:34'),(62,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 23:13:00'),(63,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 23:13:09'),(64,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-13 23:13:49'),(65,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 10:30:47'),(66,1,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 10:57:59'),(67,1,'settings_updated','Updated: require_staff_2fa.','::1','curl/8.17.0','2026-07-14 10:58:10'),(68,1,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 10:58:25'),(69,1,'otp_challenge_sent','Staff 2FA code generated (dev preview, super_admin)','::1','curl/8.17.0','2026-07-14 10:58:26'),(70,1,'otp_failed','Incorrect code. 2 attempt(s) remaining.','::1','curl/8.17.0','2026-07-14 10:58:42'),(71,1,'otp_failed','Incorrect code. 1 attempt(s) remaining.','::1','curl/8.17.0','2026-07-14 10:58:55'),(72,1,'otp_verified','2FA code verified for staff login','::1','curl/8.17.0','2026-07-14 10:59:53'),(73,1,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 11:00:24'),(74,1,'otp_challenge_sent','Staff 2FA code generated (dev preview, super_admin)','::1','curl/8.17.0','2026-07-14 11:00:25'),(75,1,'otp_challenge_sent','Resent 2FA code (dev preview, super_admin)','::1','curl/8.17.0','2026-07-14 11:01:36'),(76,1,'otp_failed','Incorrect code. 2 attempt(s) remaining.','::1','curl/8.17.0','2026-07-14 11:01:37'),(77,4,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 11:01:52'),(78,5,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 11:01:52'),(79,3,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 11:01:53'),(80,3,'otp_challenge_sent','Staff 2FA code generated (dev preview, bac)','::1','curl/8.17.0','2026-07-14 11:01:53'),(81,6,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 11:02:15'),(82,1,'settings_updated','Updated: require_staff_2fa.','::1','curl/8.17.0','2026-07-14 11:02:31'),(83,1,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 11:02:32'),(84,6,'password_reset_requested','Password reset OTP sent','::1','curl/8.17.0','2026-07-14 11:40:16'),(85,6,'password_reset_completed','Password reset via forgot-password flow','::1','curl/8.17.0','2026-07-14 11:40:46'),(86,6,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 11:40:59'),(88,3,'password_reset_requested','Password reset OTP sent','::1','curl/8.17.0','2026-07-14 11:43:18'),(89,3,'password_reset_completed','Password reset via forgot-password flow','::1','curl/8.17.0','2026-07-14 11:43:32'),(90,3,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 11:43:33'),(91,1,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 11:44:31'),(92,1,'user_password_reset','Reset Test Admin Target\'s password was reset by an administrator.','::1','curl/8.17.0','2026-07-14 11:45:02'),(94,1,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 11:46:15'),(95,1,'otp_challenge_sent','Staff 2FA code sent (super_admin)','::1','curl/8.17.0','2026-07-14 11:46:20'),(96,1,'otp_verified','2FA code verified for staff login','::1','curl/8.17.0','2026-07-14 11:46:21'),(97,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 12:05:33'),(98,12,'password_reset_blocked_inactive','Reset requested for inactive account','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 12:14:25'),(99,12,'password_reset_blocked_inactive','Reset requested for inactive account','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 12:18:47'),(102,12,'password_reset_blocked_inactive','Reset requested for inactive account','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 12:25:41'),(104,12,'password_reset_blocked_inactive','Reset requested for inactive account','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 12:33:36'),(105,12,'login_failed','Invalid login attempt for jongding','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 12:39:04'),(106,12,'login_blocked','Inactive account login attempt','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 12:39:12'),(107,12,'login_blocked','Inactive account login attempt','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 12:39:17'),(108,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 12:39:55'),(109,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 12:40:07'),(112,1,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 12:43:12'),(113,1,'logout','User logged out','::1','curl/8.17.0','2026-07-14 12:43:12'),(115,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 12:43:58'),(116,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 12:44:03'),(117,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 14:28:05'),(118,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 14:28:55'),(119,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 14:54:58'),(120,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 16:27:28'),(121,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 16:48:00'),(122,5,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 16:49:03'),(124,6,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 16:51:27'),(125,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:21:25'),(126,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:22:19'),(127,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:22:29'),(128,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:23:30'),(129,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:23:37'),(130,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:23:45'),(131,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:23:52'),(132,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:24:33'),(133,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:24:45'),(134,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:25:07'),(135,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:25:41'),(136,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:26:52'),(137,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:27:04'),(138,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:27:11'),(139,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:27:16'),(140,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:29:23'),(141,1,'login_failed','Invalid login attempt for superadmin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:29:30'),(142,1,'login_failed','Invalid login attempt for superadmin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:29:36'),(143,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 17:29:41'),(144,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 18:05:33'),(145,1,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 18:06:31'),(147,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 18:13:16'),(148,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 18:13:20'),(149,3,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 18:20:18'),(155,4,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 18:50:40'),(156,5,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 18:50:41'),(157,6,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 18:50:41'),(158,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 18:55:32'),(159,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 18:56:02'),(160,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 18:56:36'),(161,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 18:56:42'),(162,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 18:57:27'),(163,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 18:57:32'),(164,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 18:58:36'),(165,3,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 18:58:40'),(166,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 18:58:42'),(167,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:01:22'),(168,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:01:35'),(169,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:03:17'),(170,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:03:24'),(171,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:03:32'),(172,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:03:37'),(173,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:04:40'),(174,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:04:51'),(175,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:04:56'),(176,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:04:59'),(177,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:06:22'),(178,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:06:42'),(179,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:07:47'),(180,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:07:52'),(181,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:10:06'),(182,5,'login_failed','Invalid login attempt for contractor','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:10:13'),(183,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:10:18'),(184,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:10:30'),(185,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:16:11'),(186,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:16:34'),(187,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:16:40'),(188,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 19:17:07'),(189,3,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 19:50:16'),(190,3,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 20:08:06'),(191,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 20:08:53'),(192,1,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 20:09:05'),(193,6,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 20:09:25'),(194,4,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 20:09:53'),(195,5,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 20:09:53'),(196,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:12:21'),(197,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:12:55'),(198,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:13:00'),(199,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:13:07'),(200,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:13:21'),(201,1,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 20:15:43'),(202,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:23:48'),(203,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:23:51'),(204,2,'staff_account_requested','Sheesh (engineer) account request submitted for Super Admin approval.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:24:45'),(205,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:24:49'),(206,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:24:56'),(207,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:25:01'),(208,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:25:07'),(209,1,'staff_account_approved','Sheesh (engineer) account approved and created.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:25:33'),(210,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:26:29'),(211,NULL,'login_failed','Invalid login attempt for Sheesh','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:27:00'),(212,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:27:05'),(213,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:27:26'),(214,5,'login_failed','Invalid login attempt for contractor','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:27:33'),(215,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:27:37'),(216,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:27:46'),(217,3,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 20:37:56'),(218,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:40:02'),(219,1,'citizen_verification_updated','Rawen Cavite ID verification verified.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:40:10'),(220,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 20:46:08'),(221,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 21:04:10'),(222,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 21:04:44'),(223,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 21:05:02'),(224,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 21:05:12'),(225,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 21:05:17'),(226,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 21:05:28'),(227,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 21:05:34'),(228,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 21:05:47'),(229,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 21:07:46'),(230,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 21:08:04'),(231,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 21:08:07'),(232,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 21:10:39'),(233,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-14 21:13:42'),(234,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-14 21:16:59'),(235,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 19:59:07'),(236,3,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-15 20:00:57'),(237,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 20:03:43'),(238,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:12:45'),(239,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:24:22'),(240,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:24:42'),(241,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:26:52'),(242,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:26:55'),(243,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:27:27'),(244,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:30:32'),(245,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:31:03'),(246,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:31:12'),(247,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:31:42'),(248,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:31:52'),(249,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:35:14'),(250,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:35:17'),(251,2,'staff_account_requested','Sheesh (engineer) account request submitted for Super Admin approval.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:35:36'),(252,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:35:49'),(253,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:35:55'),(254,1,'staff_account_rejected','Sheesh\'s engineer account request was rejected — gmail is already in used.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:38:25'),(255,1,'user_password_reset','Sheesh\'s password was reset by an administrator.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:40:15'),(256,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:40:39'),(257,NULL,'login_failed','Invalid login attempt for Sheesh','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:40:46'),(258,19,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:40:51'),(259,19,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:43:58'),(260,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:47:11'),(261,1,'document_reviewed','\"Audited Financial Statement\" (contractor #11) verified.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:49:22'),(262,1,'document_reviewed','\"PCAB License\" (contractor #11) verified.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:49:25'),(263,1,'document_reviewed','\"Tax Clearance Certificate\" (contractor #11) verified.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:49:27'),(264,1,'document_reviewed','\"Mayor\'s / Business Permit\" (contractor #11) verified.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:49:30'),(265,1,'document_reviewed','\"DTI or SEC Registration\" (contractor #11) verified.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:49:32'),(266,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:49:42'),(267,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:49:45'),(268,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:50:49'),(269,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:50:55'),(270,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:54:37'),(271,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 21:54:44'),(272,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:03:55'),(273,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:06:26'),(274,1,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-15 22:06:56'),(275,3,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-15 22:07:20'),(276,3,'contractor_application_rejected','Law Test Contractor\'s contractor application was rejected — Test cleanup - not a real applicant.','::1','curl/8.17.0','2026-07-15 22:07:20'),(277,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:09:31'),(278,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:10:02'),(279,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:12:18'),(280,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:12:20'),(281,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:31:47'),(282,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:31:53'),(283,1,'document_reviewed','\"dasda\" (project #11) verified.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:32:10'),(284,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:32:13'),(285,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:32:16'),(286,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:32:40'),(287,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:32:45'),(288,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:33:41'),(289,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:33:50'),(290,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:34:06'),(291,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:34:09'),(292,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:34:33'),(293,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:34:39'),(294,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:34:58'),(295,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:35:03'),(296,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:36:14'),(297,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:36:30'),(298,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:53:14'),(299,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:53:30'),(300,3,'contractor_application_approved','asdas\'s contractor application was approved; portal account created.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:54:01'),(301,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:54:22'),(302,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:54:28'),(303,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:55:02'),(304,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:55:09'),(305,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:55:44'),(306,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-15 22:56:26'),(307,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 10:54:37'),(308,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 11:00:05'),(309,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 11:00:10'),(310,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 11:07:30'),(311,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 11:07:36'),(312,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 11:10:26'),(313,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 11:10:31'),(314,1,'document_reviewed','\"asws\" (project #12) verified.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 11:10:47'),(315,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 11:10:51'),(316,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 11:10:56'),(317,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 11:11:18'),(318,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 11:11:22'),(319,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 11:11:48'),(320,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 11:11:52'),(321,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 11:12:04'),(322,23,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 11:18:30'),(323,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 11:19:04'),(324,1,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 11:19:27'),(327,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 12:09:27'),(328,4,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 12:09:47'),(330,23,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 12:10:00'),(338,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:18:09'),(339,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:19:17'),(340,2,'login_blocked','Portal role mismatch: selected hope','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:19:23'),(341,23,'login_failed','Invalid login attempt for hope','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:19:44'),(342,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:19:50'),(343,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:20:00'),(344,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:20:06'),(345,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:20:16'),(346,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:20:18'),(347,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:20:31'),(348,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:20:38'),(349,4,'project_status_endorsed','sample3 was endorsed by Engineering Review.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:20:50'),(350,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:21:02'),(351,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:21:07'),(352,23,'project_status_approved','sample3 was approved.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:21:16'),(353,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:21:18'),(354,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:21:21'),(355,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:22:25'),(356,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:22:30'),(357,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:22:50'),(358,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:22:59'),(359,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:23:09'),(360,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:23:19'),(361,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:23:28'),(362,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:23:30'),(363,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:23:41'),(364,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:23:47'),(365,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:24:03'),(366,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:24:13'),(367,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:25:40'),(368,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 12:25:44'),(369,NULL,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 12:41:28'),(370,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 12:41:43'),(371,4,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 12:41:46'),(373,23,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 12:42:05'),(377,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:00:06'),(378,NULL,'login_failed','Invalid login attempt for budgetoffice','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:00:17'),(379,NULL,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:00:24'),(380,NULL,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:00:31'),(381,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:01:20'),(382,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:01:35'),(383,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:01:39'),(384,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:01:44'),(385,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:11:16'),(386,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 13:23:00'),(387,4,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 13:23:01'),(389,23,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 13:23:28'),(391,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 13:27:41'),(392,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:28:42'),(393,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:28:48'),(394,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:46:04'),(395,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:46:08'),(396,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:46:15'),(397,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:46:20'),(398,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:46:30'),(399,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:46:36'),(400,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:47:02'),(401,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:47:11'),(402,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:47:22'),(403,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:47:25'),(404,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 13:47:29'),(405,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 14:17:39'),(406,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 14:22:37'),(407,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 14:22:58'),(408,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 14:24:30'),(409,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 14:24:32'),(410,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:27:48'),(411,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:27:52'),(412,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:27:59'),(413,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:28:08'),(414,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:28:24'),(415,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:28:26'),(416,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:29:24'),(417,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:29:31'),(418,4,'project_status_endorsed','eyy was endorsed by Engineering Review.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:30:02'),(419,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:30:04'),(420,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:30:10'),(421,3,'procurement_document_reviewed','\"dass\" (project #17) verified.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:30:20'),(422,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:30:27'),(423,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:30:30'),(424,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:30:41'),(425,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:30:44'),(426,23,'project_status_approved','eyy was approved.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:30:53'),(427,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:30:55'),(428,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:31:01'),(429,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:32:22'),(430,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:32:30'),(431,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:33:13'),(432,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:33:18'),(433,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:38:26'),(434,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 16:38:35'),(435,5,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 17:16:55'),(436,3,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 17:19:59'),(437,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 17:50:50'),(438,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 17:51:15'),(439,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 17:51:36'),(440,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 17:51:38'),(441,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 17:51:59'),(442,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 17:52:07'),(443,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 17:52:21'),(444,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 17:52:27'),(445,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 18:14:44'),(446,5,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 18:19:25'),(447,3,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 18:19:25'),(448,23,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 18:19:26'),(449,23,'contract_award_rejected','JKL Builders\'s award recommendation for sample3 was rejected — Bid amount seems unrealistically low for scope of work..','::1','curl/8.17.0','2026-07-16 18:26:55'),(450,23,'contract_award_approved','ABC Construction\'s award recommendation for sample3 was approved.','::1','curl/8.17.0','2026-07-16 18:27:45'),(451,6,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 19:11:18'),(452,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:07:13'),(453,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:26:49'),(454,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:27:18'),(455,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:27:42'),(456,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:27:48'),(457,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:28:09'),(458,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:28:12'),(459,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:28:54'),(460,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:28:56'),(461,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:38:34'),(462,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:38:44'),(463,4,'project_status_endorsed','Sample* was endorsed by Engineering Review — Okay siya ha.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:39:22'),(464,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:39:31'),(465,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:39:36'),(466,3,'procurement_document_reviewed','\"Docu\" (project #18) verified.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:40:29'),(467,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:40:41'),(468,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:40:55'),(469,23,'project_status_approved','Sample* was approved — sige okay pala yan eh.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:42:14'),(470,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:42:40'),(471,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:42:52'),(472,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:43:38'),(473,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:43:43'),(474,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:43:52'),(475,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:44:00'),(476,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:44:10'),(477,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:44:22'),(478,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:45:21'),(479,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:45:28'),(480,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:52:50'),(481,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-16 21:53:17'),(482,6,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 22:15:38'),(483,6,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-16 22:19:49'),(484,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 11:39:08'),(485,6,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-18 11:58:13'),(486,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:20:28'),(487,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:20:40'),(488,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:20:51'),(489,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:20:53'),(490,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:21:07'),(491,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:21:16'),(492,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:21:30'),(493,23,'login_failed','Invalid login attempt for hope','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:21:35'),(494,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:21:39'),(495,23,'contract_award_approved','JKL Builders\'s award recommendation for Sample* was approved.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:21:47'),(496,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:21:53'),(497,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:22:01'),(498,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:22:36'),(499,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:22:44'),(500,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:23:03'),(501,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:23:09'),(502,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:23:17'),(503,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:23:20'),(504,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:23:39'),(505,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:23:46'),(506,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:24:29'),(507,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 12:24:31'),(508,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-18 12:55:40'),(509,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 14:07:07'),(510,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 14:07:14'),(511,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 14:10:55'),(512,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 14:11:25'),(513,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 14:11:31'),(514,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 14:11:39'),(515,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 14:13:08'),(516,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 14:13:10'),(517,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-18 14:30:48'),(518,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 15:22:21'),(519,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 15:22:27'),(520,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 15:22:39'),(521,3,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-18 15:44:02'),(522,4,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-18 15:44:04'),(523,5,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-18 15:44:04'),(524,23,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-18 15:44:05'),(525,1,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-18 15:44:24'),(526,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-18 15:45:23'),(527,6,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-18 15:46:15'),(528,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 16:02:07'),(529,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 16:02:12'),(530,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 16:02:17'),(531,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 16:02:42'),(532,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 16:02:51'),(533,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 16:02:58'),(534,23,'login_failed','Invalid login attempt for hope','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 16:03:06'),(535,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 16:03:09'),(536,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-18 16:03:40'),(537,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 11:10:32'),(538,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 11:14:59'),(539,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 11:15:38'),(540,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 11:26:56'),(541,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 12:37:55'),(542,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 12:45:04'),(543,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-19 12:46:13'),(544,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 12:51:31'),(545,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 12:58:47'),(546,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 13:02:57'),(547,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 13:06:48'),(548,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 13:07:13'),(549,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 13:13:28'),(550,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 13:19:29'),(551,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 13:25:21'),(552,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 13:25:31'),(553,4,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-19 13:52:19'),(554,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 13:55:17'),(555,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 13:57:41'),(556,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 14:17:09'),(557,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 14:17:13'),(558,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 14:21:16'),(559,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 14:21:25'),(560,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 14:22:06'),(561,12,'login_failed','Invalid login attempt for jongding','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 19:24:58'),(562,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 19:25:36'),(563,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 19:26:48'),(564,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 19:26:57'),(565,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-19 19:35:01'),(566,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-19 20:18:46'),(567,23,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-19 20:18:46'),(568,23,'project_deletion_rejected','Deletion Flow Test Project (PRJ-012) deletion request was rejected — Keep this project, still needed for reference..','::1','curl/8.17.0','2026-07-19 20:21:16'),(569,23,'project_deletion_approved','Deletion Flow Test Project (PRJ-012) deletion request was approved — Confirmed, safe to remove..','::1','curl/8.17.0','2026-07-19 20:21:45'),(570,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 20:51:19'),(571,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 20:51:25'),(572,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 20:51:45'),(573,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 20:51:53'),(574,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 20:52:01'),(575,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 20:52:10'),(576,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 20:52:25'),(577,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 20:52:30'),(578,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 20:52:53'),(579,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 22:04:00'),(580,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 22:06:58'),(581,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 22:07:17'),(582,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 22:07:28'),(583,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-19 22:16:40'),(584,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-19 22:49:39'),(585,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 22:50:11'),(586,12,'login_failed','Invalid login attempt for jongding','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 22:50:26'),(587,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-19 22:50:31'),(588,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:06:46'),(589,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:09:39'),(590,1,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:09:49'),(591,1,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:13:47'),(592,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:13:55'),(593,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:18:21'),(594,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:18:32'),(595,4,'project_status_endorsed','Samples was endorsed by Engineering Review — Shet sarap.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:18:50'),(596,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:18:56'),(597,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:19:08'),(598,23,'project_status_approved','Samples was approved — nyak.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:19:39'),(599,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:19:44'),(600,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:19:59'),(601,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:20:44'),(602,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:21:01'),(603,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:21:55'),(604,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:22:08'),(605,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:22:47'),(606,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:22:53'),(607,23,'contract_award_approved','JKL Builders\'s award recommendation for Samples was approved.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:23:15'),(608,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:23:21'),(609,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:23:27'),(610,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:23:54'),(611,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:24:04'),(612,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:24:31'),(613,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:24:43'),(614,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:25:14'),(615,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:25:26'),(616,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:25:55'),(617,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:26:03'),(618,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:26:56'),(619,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:27:00'),(620,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:28:43'),(621,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:31:20'),(622,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:33:07'),(623,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:33:12'),(624,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:35:00'),(625,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 20:35:14'),(626,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:02:48'),(627,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:10:29'),(628,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:11:08'),(629,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:11:14'),(630,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:11:37'),(631,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:11:41'),(632,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:20:38'),(633,12,'login_failed','Invalid login attempt for jongding','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:20:50'),(634,12,'login_failed','Invalid login attempt for jongding','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:20:54'),(635,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:21:03'),(636,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:30:45'),(637,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:30:59'),(638,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-20 21:45:33'),(639,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:46:07'),(640,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:47:29'),(641,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:51:07'),(642,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:51:16'),(643,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 21:51:23'),(644,NULL,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-20 22:07:35'),(645,12,'login_failed','Invalid login attempt for jongding','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 22:27:40'),(646,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-20 22:27:45'),(647,NULL,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-20 22:41:53'),(648,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:15:26'),(649,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-21 10:24:13'),(650,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-21 10:50:46'),(651,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:57:05'),(652,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:57:17'),(653,4,'project_status_endorsed','Project Test Integration - IPMS was endorsed by Engineering Review.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:57:22'),(654,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:57:26'),(655,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:57:39'),(656,23,'project_status_approved','Project Test Integration - IPMS was approved.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:57:44'),(657,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:57:47'),(658,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:57:54'),(659,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:58:08'),(660,5,'login_failed','Invalid login attempt for contractor','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:58:17'),(661,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:58:21'),(662,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:58:42'),(663,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:58:50'),(664,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:59:04'),(665,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:59:11'),(666,23,'contract_award_approved','JKL Builders\'s award recommendation for Project Test Integration - IPMS was approved.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:59:23'),(667,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:59:26'),(668,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:59:29'),(669,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:59:44'),(670,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 10:59:54'),(671,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 11:00:15'),(672,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 11:00:18'),(673,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 11:00:28'),(674,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 11:00:35'),(675,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 11:01:03'),(676,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 11:01:06'),(677,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 11:06:03'),(678,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 11:55:43'),(679,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 11:57:46'),(680,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 11:57:52'),(681,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 11:58:43'),(682,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 11:58:46'),(683,2,'project_status_turnover','Samples was turned over to DPWH.','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 12:05:59'),(684,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-21 12:07:09'),(685,3,'login_failed','Invalid login attempt for bac','::1','curl/8.17.0','2026-07-21 12:12:13'),(686,NULL,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-21 12:12:51'),(687,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 12:14:16'),(688,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 12:14:24'),(689,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 12:14:30'),(690,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 12:23:12'),(691,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 12:25:47'),(692,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 12:25:57'),(693,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 12:29:00'),(694,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 12:33:43'),(695,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 13:00:44'),(696,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 13:10:00'),(697,2,'login_success','User logged in successfully','::1','curl/8.17.0','2026-07-21 13:15:55'),(698,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 19:06:02'),(699,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 19:12:25'),(700,3,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 19:13:19'),(701,3,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 19:18:18'),(702,4,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 19:18:33'),(703,4,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 19:22:11'),(704,5,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 19:22:27'),(705,5,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 19:28:15'),(706,23,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 19:28:24'),(707,23,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 19:30:41'),(708,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 19:30:54'),(709,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 19:31:16'),(710,12,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 20:09:51'),(711,12,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 20:11:59'),(712,2,'login_success','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 20:12:07'),(713,2,'logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 20:18:23');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'user_status_updated','users',6,'Citizen Viewer account set to inactive.','2026-07-12 13:19:16'),(2,1,'user_status_updated','users',6,'Citizen Viewer account set to active.','2026-07-12 13:19:16'),(3,1,'settings_updated','system_settings',NULL,'Updated: site_name, support_email, session_timeout_minutes.','2026-07-13 14:01:56'),(4,1,'user_created','users',8,'Test Engineer Account (engineer) account created.','2026-07-13 14:05:39'),(5,1,'contractor_created','contractors',6,'Test Builders Co contractor profile created.','2026-07-13 14:07:12'),(6,1,'document_reviewed','supporting_documents',1,'\"DTI Cert 2026\" (contractor #6) verified.','2026-07-13 14:07:34'),(7,1,'login_unlocked','login_attempts',NULL,'test_eng_1 (::1) unlocked, 5 failed attempt(s) cleared.','2026-07-13 14:15:14'),(8,1,'procurement_document_uploaded','supporting_documents',2,'1 document(s) attached to Road Rehabilitation (project #1).','2026-07-13 14:25:56'),(9,1,'procurement_document_reviewed','supporting_documents',2,'\"ITB - Road Rehabilitation\" (project #1) verified.','2026-07-13 14:26:11'),(10,1,'procurement_document_uploaded','supporting_documents',3,'2 document(s) attached to Road Rehabilitation (bac_bid #1).','2026-07-13 14:33:03'),(11,3,'procurement_document_uploaded','supporting_documents',5,'1 document(s) attached to Road Rehabilitation (bac_bid #2).','2026-07-13 14:44:48'),(12,1,'settings_updated','system_settings',NULL,'Updated: require_staff_2fa.','2026-07-14 02:58:10'),(13,1,'settings_updated','system_settings',NULL,'Updated: require_staff_2fa.','2026-07-14 03:02:31'),(18,1,'engineer_created','users',18,'Direct SA Hire engineer account created.','2026-07-14 10:30:31'),(19,1,'staff_account_approved','users',19,'Sheesh (engineer) account approved and created.','2026-07-14 12:25:33'),(20,1,'citizen_verification_updated','citizens',2,'Rawen Cavite ID verification verified.','2026-07-14 12:40:10'),(21,1,'user_password_reset','users',19,'Sheesh\'s password was reset by an administrator.','2026-07-15 13:40:14'),(22,1,'document_reviewed','supporting_documents',19,'\"Audited Financial Statement\" (contractor #11) verified.','2026-07-15 13:49:22'),(23,1,'document_reviewed','supporting_documents',18,'\"PCAB License\" (contractor #11) verified.','2026-07-15 13:49:24'),(24,1,'document_reviewed','supporting_documents',17,'\"Tax Clearance Certificate\" (contractor #11) verified.','2026-07-15 13:49:27'),(25,1,'document_reviewed','supporting_documents',16,'\"Mayor\'s / Business Permit\" (contractor #11) verified.','2026-07-15 13:49:30'),(26,1,'document_reviewed','supporting_documents',15,'\"DTI or SEC Registration\" (contractor #11) verified.','2026-07-15 13:49:31'),(27,3,'contractor_application_rejected','contractors',12,'Law Test Contractor\'s contractor application was rejected — Test cleanup - not a real applicant.','2026-07-15 14:07:20'),(28,1,'document_reviewed','supporting_documents',21,'\"dasda\" (project #11) verified.','2026-07-15 14:32:10'),(29,3,'contractor_application_approved','contractors',11,'asdas\'s contractor application was approved; portal account created.','2026-07-15 14:54:01'),(30,1,'document_reviewed','supporting_documents',22,'\"asws\" (project #12) verified.','2026-07-16 03:10:47'),(31,3,'procurement_document_reviewed','supporting_documents',28,'\"dass\" (project #17) verified.','2026-07-16 08:30:20'),(32,3,'procurement_document_reviewed','supporting_documents',30,'\"Docu\" (project #18) verified.','2026-07-16 13:40:29');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bac_award_recommendations`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bac_award_recommendations`
--

LOCK TABLES `bac_award_recommendations` WRITE;
/*!40000 ALTER TABLE `bac_award_recommendations` DISABLE KEYS */;
/*!40000 ALTER TABLE `bac_award_recommendations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bac_bid_announcements`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bac_bid_announcements`
--

LOCK TABLES `bac_bid_announcements` WRITE;
/*!40000 ALTER TABLE `bac_bid_announcements` DISABLE KEYS */;
/*!40000 ALTER TABLE `bac_bid_announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bac_bid_submissions`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bac_bid_submissions`
--

LOCK TABLES `bac_bid_submissions` WRITE;
/*!40000 ALTER TABLE `bac_bid_submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `bac_bid_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bac_procurement_logs`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bac_procurement_logs`
--

LOCK TABLES `bac_procurement_logs` WRITE;
/*!40000 ALTER TABLE `bac_procurement_logs` DISABLE KEYS */;
INSERT INTO `bac_procurement_logs` VALUES (1,NULL,2,'Project status updated','Road Rehabilitation changed from delayed to approved.','2026-07-13 14:17:46'),(2,NULL,2,'Project status updated','Road Rehabilitation changed from approved to cancelled.','2026-07-13 14:17:51'),(3,NULL,1,'Bidding notice posted','Road Rehabilitation was posted for BAC bidding.','2026-07-13 14:30:31'),(4,NULL,1,'Contractor bid recorded','ABC Construction submitted a BAC bid.','2026-07-13 14:30:48'),(5,NULL,1,'Contractor bid recorded','ABC Construction submitted a BAC bid.','2026-07-13 14:31:37'),(6,NULL,1,'Contractor bid recorded','ABC Construction submitted a BAC bid.','2026-07-13 14:32:33'),(7,NULL,1,'Award recommendation sent','ABC Construction was recommended for contractor assignment.','2026-07-13 14:32:51'),(8,NULL,3,'Bidding notice posted','Road Rehabilitation was posted for BAC bidding.','2026-07-13 14:44:31'),(9,NULL,3,'Contractor bid recorded','ABC Construction submitted a BAC bid.','2026-07-13 14:44:31'),(10,NULL,3,'Award recommendation sent','ABC Construction was recommended for contractor assignment.','2026-07-13 14:44:48'),(14,NULL,2,'Project registered','Ganorn was created with status draft.','2026-07-14 09:26:41'),(15,NULL,2,'Project status updated','Ganorn changed from draft to approved.','2026-07-14 09:26:47'),(18,NULL,2,'Project status updated','Ganorn changed from approved to returned.','2026-07-14 10:55:11'),(19,NULL,2,'Project status updated','Ganorn changed from returned to cancelled.','2026-07-14 10:55:19'),(20,NULL,2,'Project status updated','Ganorn changed from cancelled to returned.','2026-07-14 10:55:21'),(21,NULL,2,'Project registered','sample2 was registered with status draft and 1 supporting document(s).','2026-07-15 14:26:43'),(22,NULL,2,'Project registered','sample3 was registered with status draft and 1 supporting document(s).','2026-07-16 03:10:23'),(23,NULL,2,'Project registered','HOPE Test Project was registered with status draft and 1 supporting document(s).','2026-07-16 03:19:04'),(24,NULL,23,'Project approved','HOPE Test Project was approved.','2026-07-16 03:19:42'),(25,NULL,23,'Project rejected','HOPE Test Project was rejected — Test cleanup - not a real project.','2026-07-16 03:20:24'),(38,NULL,4,'Engineering review: endorsed','sample3 was endorsed by Engineering Review.','2026-07-16 04:20:50'),(39,NULL,23,'Project approved','sample3 was approved.','2026-07-16 04:21:16'),(40,NULL,3,'Bidding notice posted','sample3 was posted for BAC bidding.','2026-07-16 04:22:42'),(52,NULL,2,'Project registered','eyy was registered with status draft and 1 supporting document(s).','2026-07-16 08:29:18'),(53,NULL,4,'Engineering review: endorsed','eyy was endorsed by Engineering Review.','2026-07-16 08:30:02'),(54,NULL,23,'Project approved','eyy was approved.','2026-07-16 08:30:53'),(55,NULL,3,'Bidding notice posted','eyy was posted for BAC bidding.','2026-07-16 08:31:10'),(56,NULL,3,'Contractor bid recorded','asdas submitted a BAC bid.','2026-07-16 08:33:39'),(57,NULL,3,'Bid technical score set','JKL Builders\'s bid for eyy was scored 88/100.','2026-07-16 09:20:42'),(58,NULL,3,'Award recommendation sent','JKL Builders was recommended for contractor assignment.','2026-07-16 09:20:43'),(59,NULL,3,'Award recommendation sent','JKL Builders was recommended for contractor assignment.','2026-07-16 09:51:32'),(60,NULL,2,'Project status updated','eyy changed from awarded to assigned.','2026-07-16 09:51:49'),(61,NULL,2,'Engineer assignment updated','eyy was assigned for field monitoring.','2026-07-16 09:51:49'),(62,NULL,3,'Contractor bid recorded','ABC Construction submitted a BAC bid.','2026-07-16 10:22:25'),(63,NULL,3,'Award recommendation sent','JKL Builders was recommended for contractor assignment, pending HOPE approval.','2026-07-16 10:22:37'),(64,NULL,23,'Contract award rejected','JKL Builders\'s award recommendation for sample3 was rejected — Bid amount seems unrealistically low for scope of work..','2026-07-16 10:26:55'),(65,NULL,3,'Award recommendation sent','ABC Construction was recommended for contractor assignment, pending HOPE approval.','2026-07-16 10:27:13'),(66,NULL,23,'Contract award approved','ABC Construction\'s award recommendation for sample3 was approved.','2026-07-16 10:27:45'),(67,NULL,2,'Project registered','Sample* was registered with status draft and 1 supporting document(s).','2026-07-16 13:36:40'),(68,NULL,4,'Engineering review: endorsed','Sample* was endorsed by Engineering Review — Okay siya ha.','2026-07-16 13:39:21'),(69,NULL,23,'Project approved','Sample* was approved — sige okay pala yan eh.','2026-07-16 13:42:14'),(70,NULL,3,'Bidding notice posted','Sample* was posted for BAC bidding.','2026-07-16 13:43:22'),(71,NULL,3,'Award recommendation sent','JKL Builders was recommended for contractor assignment, pending HOPE approval.','2026-07-18 04:21:24'),(72,NULL,23,'Contract award approved','JKL Builders\'s award recommendation for Sample* was approved.','2026-07-18 04:21:47'),(73,NULL,3,'Award recommendation sent','JKL Builders was recommended for contractor assignment, pending HOPE approval.','2026-07-18 04:22:54'),(74,NULL,2,'Project status updated','Sample* changed from awarded to assigned.','2026-07-18 04:23:33'),(75,NULL,2,'Engineer assignment updated','Sample* was assigned for field monitoring.','2026-07-18 04:23:33'),(76,NULL,2,'Project registered','EHKK was registered with status draft and 1 supporting document(s).','2026-07-18 07:22:17'),(77,NULL,2,'Project registered','Test Location Picker Project was registered with status draft and 1 supporting document(s).','2026-07-19 05:18:46'),(78,NULL,2,'Project registered','Test Doc Limit 3docs valid was registered with status draft and 3 supporting document(s).','2026-07-19 11:55:17'),(84,NULL,2,'Project deletion requested','eyy (PRJ-010) — EYYY','2026-07-19 12:43:51'),(85,NULL,2,'Project registered','Samples was registered with status draft and 1 supporting document(s).','2026-07-20 12:18:10'),(86,NULL,4,'Engineering review: endorsed','Samples was endorsed by Engineering Review — Shet sarap.','2026-07-20 12:18:50'),(87,NULL,23,'Project approved','Samples was approved — nyak.','2026-07-20 12:19:39'),(88,NULL,3,'Bidding notice posted','Samples was posted for BAC bidding.','2026-07-20 12:20:32'),(89,NULL,3,'Award recommendation sent','JKL Builders was recommended for contractor assignment, pending HOPE approval.','2026-07-20 12:22:42'),(90,NULL,23,'Contract award approved','JKL Builders\'s award recommendation for Samples was approved.','2026-07-20 12:23:15'),(91,NULL,2,'Project status updated','Samples changed from awarded to assigned.','2026-07-20 12:23:47'),(92,NULL,2,'Engineer assignment updated','Samples was assigned for field monitoring.','2026-07-20 12:23:47'),(94,NULL,2,'Project registered','Project Test Integration - IPMS was registered with status draft and 1 supporting document(s).','2026-07-21 02:57:00'),(95,NULL,4,'Engineering review: endorsed','Project Test Integration - IPMS was endorsed by Engineering Review.','2026-07-21 02:57:22'),(96,NULL,23,'Project approved','Project Test Integration - IPMS was approved.','2026-07-21 02:57:44'),(97,NULL,3,'Bidding notice posted','Project Test Integration - IPMS was posted for BAC bidding.','2026-07-21 02:58:05'),(98,NULL,3,'Award recommendation sent','JKL Builders was recommended for contractor assignment, pending HOPE approval.','2026-07-21 02:58:58'),(99,NULL,23,'Contract award approved','JKL Builders\'s award recommendation for Project Test Integration - IPMS was approved.','2026-07-21 02:59:23'),(100,NULL,2,'Project status updated','Project Test Integration - IPMS changed from awarded to assigned.','2026-07-21 02:59:39'),(101,NULL,2,'Engineer assignment updated','Project Test Integration - IPMS was assigned for field monitoring.','2026-07-21 02:59:39'),(102,NULL,2,'Project turned over','Samples was turned over to DPWH.','2026-07-21 04:05:59'),(103,NULL,2,'Project registered','Roads Testing was registered with status draft and 1 supporting document(s).','2026-07-21 04:40:11'),(104,NULL,2,'Project registered','Road Geometry Test - With Geometry was registered with status draft and 1 supporting document(s).','2026-07-21 05:16:34'),(105,NULL,2,'Project registered','Non-Roads Category Test was registered with status draft and 1 supporting document(s).','2026-07-21 05:17:13');
/*!40000 ALTER TABLE `bac_procurement_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `citizens`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `citizens`
--

LOCK TABLES `citizens` WRITE;
/*!40000 ALTER TABLE `citizens` DISABLE KEYS */;
INSERT INTO `citizens` VALUES (2,12,'Rawen','Cavite','Nalix','caviterawen5@gmail.com','09507045629','2004-01-28','Male','Single','phs 1','1265','Caloocan City','city','1428','National ID','22222222','/assets/img/citizen-ids/citizen_id_1784002421_3e87cc1b.jpg','verified',1,'2026-07-14 20:40:10',NULL,'2026-07-14 04:13:41','2026-07-14 12:40:10');
/*!40000 ALTER TABLE `citizens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contractor_documents`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contractor_documents`
--

LOCK TABLES `contractor_documents` WRITE;
/*!40000 ALTER TABLE `contractor_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `contractor_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contractor_reports`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contractor_reports`
--

LOCK TABLES `contractor_reports` WRITE;
/*!40000 ALTER TABLE `contractor_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `contractor_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contractors`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contractors`
--

LOCK TABLES `contractors` WRITE;
/*!40000 ALTER TABLE `contractors` DISABLE KEYS */;
INSERT INTO `contractors` VALUES (1,5,'JKL Builders','Contractor Account','contractor@ipms.local','09171234567',NULL,NULL,NULL,65,5.00,'active',0,NULL,NULL,'approved',NULL,NULL,NULL,'2026-06-08 10:59:37'),(2,NULL,'ABC Construction','Ana Cruz','ana@abcconstruction.ph','09181234567',NULL,NULL,NULL,65,5.00,'active',0,NULL,NULL,'approved',NULL,NULL,NULL,'2026-06-08 10:59:37'),(3,NULL,'XYZ Infrastructure','Mario Santos','mario@xyzinfra.ph','09191234567',NULL,NULL,NULL,65,5.00,'active',0,NULL,NULL,'approved',NULL,NULL,NULL,'2026-06-08 10:59:37'),(4,NULL,'Delta Civil Works','Diana Reyes','diana@deltaworks.ph','09171112222',NULL,NULL,NULL,65,5.00,'active',0,NULL,NULL,'approved',NULL,NULL,NULL,'2026-06-08 10:59:37'),(5,NULL,'Omega Builders Inc.','Oscar Mendoza','oscar@omegabldrs.ph','09172223333',NULL,NULL,NULL,65,5.00,'active',0,NULL,NULL,'approved',NULL,NULL,NULL,'2026-06-08 10:59:37'),(11,22,'asdas','ASD','asd@gmail.com','4545454','asdasdas','4986352274','Small B',65,5.00,'active',0,NULL,NULL,'approved',3,'2026-07-15 22:54:01',NULL,'2026-07-15 13:46:53'),(13,NULL,'Alrie Construction Services',NULL,NULL,NULL,NULL,NULL,NULL,65,5.00,'active',0,NULL,NULL,'approved',NULL,NULL,NULL,'2026-07-21 12:06:20'),(14,32,'Dhicerv Contracting Services','Dhicerv','dhicerv+contractor@gmail.com',NULL,NULL,NULL,NULL,0,5.00,'active',0,NULL,NULL,'approved',NULL,NULL,NULL,'2026-07-21 12:41:44'),(15,38,'Evebrasileno Contracting Services','Evebrasileno','evebrasileno+contractor@gmail.com',NULL,NULL,NULL,NULL,0,5.00,'active',0,NULL,NULL,'approved',NULL,NULL,NULL,'2026-07-21 12:41:44'),(16,44,'Stevennicole30 Contracting Services','Stevennicole30','stevennicole30+contractor@gmail.com',NULL,NULL,NULL,NULL,0,5.00,'active',0,NULL,NULL,'approved',NULL,NULL,NULL,'2026-07-21 12:41:45'),(17,50,'Jaysonmagrimbao Contracting Services','Jaysonmagrimbao','jaysonmagrimbao+contractor@gmail.com',NULL,NULL,NULL,NULL,0,5.00,'active',0,NULL,NULL,'approved',NULL,NULL,NULL,'2026-07-21 12:41:45'),(18,56,'Caviterawen5 Contracting Services','Caviterawen5','caviterawen5+contractor@gmail.com',NULL,NULL,NULL,NULL,0,5.00,'active',0,NULL,NULL,'approved',NULL,NULL,NULL,'2026-07-21 12:41:45');
/*!40000 ALTER TABLE `contractors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contracts`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=1704 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contracts`
--

LOCK TABLES `contracts` WRITE;
/*!40000 ALTER TABLE `contracts` DISABLE KEYS */;
/*!40000 ALTER TABLE `contracts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `engineer_delay_reports`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `engineer_delay_reports`
--

LOCK TABLES `engineer_delay_reports` WRITE;
/*!40000 ALTER TABLE `engineer_delay_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `engineer_delay_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `engineer_issue_reports`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `engineer_issue_reports`
--

LOCK TABLES `engineer_issue_reports` WRITE;
/*!40000 ALTER TABLE `engineer_issue_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `engineer_issue_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `engineer_milestone_updates`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `engineer_milestone_updates`
--

LOCK TABLES `engineer_milestone_updates` WRITE;
/*!40000 ALTER TABLE `engineer_milestone_updates` DISABLE KEYS */;
/*!40000 ALTER TABLE `engineer_milestone_updates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `engineer_progress_photos`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `engineer_progress_photos`
--

LOCK TABLES `engineer_progress_photos` WRITE;
/*!40000 ALTER TABLE `engineer_progress_photos` DISABLE KEYS */;
/*!40000 ALTER TABLE `engineer_progress_photos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `engineer_project_assignments`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `engineer_project_assignments`
--

LOCK TABLES `engineer_project_assignments` WRITE;
/*!40000 ALTER TABLE `engineer_project_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `engineer_project_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `engineer_status_updates`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `engineer_status_updates`
--

LOCK TABLES `engineer_status_updates` WRITE;
/*!40000 ALTER TABLE `engineer_status_updates` DISABLE KEYS */;
/*!40000 ALTER TABLE `engineer_status_updates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `feedback`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `feedback`
--

LOCK TABLES `feedback` WRITE;
/*!40000 ALTER TABLE `feedback` DISABLE KEYS */;
INSERT INTO `feedback` VALUES (1,NULL,NULL,'Juan dela Cruz','Potholes in Barangay 7 are getting worse after rains.','complaint',NULL,'project',0,NULL,NULL,NULL,'none',NULL,NULL,NULL,NULL,'urgent',NULL,NULL,NULL,NULL,'resolved','2026-06-08 10:59:38'),(2,NULL,NULL,'Maria Santos','Flooding issue in Zone 2 not yet resolved after 2 weeks.','complaint',NULL,'project',0,NULL,NULL,NULL,'none',NULL,NULL,NULL,NULL,'high',NULL,NULL,NULL,NULL,'resolved','2026-06-08 10:59:38'),(3,NULL,NULL,NULL,'Municipal Hall renovation is very slow with no progress.','complaint',NULL,'project',0,NULL,NULL,NULL,'none',NULL,NULL,NULL,NULL,'medium',NULL,NULL,NULL,NULL,'resolved','2026-06-08 10:59:38'),(4,NULL,NULL,'Pedro Reyes','River dike work stopped for 3 days with no explanation.','inquiry',NULL,'project',0,NULL,NULL,NULL,'none',NULL,NULL,NULL,NULL,'high',NULL,NULL,NULL,NULL,'closed','2026-06-08 10:59:38'),(5,NULL,NULL,'Ana Garcia','Suggestion: prioritize road works before rainy season.','suggestion',NULL,'project',0,NULL,NULL,NULL,'none',NULL,NULL,NULL,NULL,'low',NULL,NULL,NULL,NULL,'open','2026-06-08 10:59:38'),(8,NULL,2,'Rawen Cavite','IPMS Integration Test','road_damage',NULL,'maintenance',0,'Rawen Cavite','09507045629','caviterawen5@gmail.com','failed',NULL,NULL,NULL,'CIMMS integration is not configured on this server','medium','District 1','Paltok',14.6397632,121.0202262,'in_progress','2026-07-18 04:00:20'),(11,NULL,2,'Rawen Cavite','asdasdasasdasda','streetlight','Street Lights','maintenance',0,'Rawen Cavite','09507045629','caviterawen5@gmail.com','synced','134','RPT-134','2026-07-20 22:28:24',NULL,'medium',NULL,'Jalaur Street, NIA Village, Tandang Sora, 6th District, Quezon City, Eastern Manila District, Metro ',14.6919155,121.0516548,'open','2026-07-20 14:28:22'),(12,NULL,2,'Rawen Cavite','asdaasdaasdasd','streetlight','Street Lights','maintenance',0,'Rawen Cavite','09507045629','caviterawen5@gmail.com','synced','135','RPT-135','2026-07-20 22:35:17',NULL,'medium',NULL,'Faldo Street, Golfhill Terraces, Matandang Balara, 3rd District, Quezon City, Eastern Manila Distric',14.6670068,121.0839262,'open','2026-07-20 14:35:15');
/*!40000 ALTER TABLE `feedback` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `feedback_photos`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `feedback_photos`
--

LOCK TABLES `feedback_photos` WRITE;
/*!40000 ALTER TABLE `feedback_photos` DISABLE KEYS */;
INSERT INTO `feedback_photos` VALUES (1,8,'/assets/img/feedback-photos/feedback_8_1784347220_1222376a9df2.jpg','2026-07-18 04:00:20');
/*!40000 ALTER TABLE `feedback_photos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inspections`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inspections`
--

LOCK TABLES `inspections` WRITE;
/*!40000 ALTER TABLE `inspections` DISABLE KEYS */;
/*!40000 ALTER TABLE `inspections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=381 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (316,'admin',2,'::1',1,'2026-07-20 20:13:54'),(317,'engineer',4,'::1',1,'2026-07-20 20:18:32'),(318,'hope',23,'::1',1,'2026-07-20 20:19:08'),(319,'bac',3,'::1',1,'2026-07-20 20:19:59'),(320,'contractor',5,'::1',1,'2026-07-20 20:21:01'),(321,'bac',3,'::1',1,'2026-07-20 20:22:08'),(322,'hope',23,'::1',1,'2026-07-20 20:22:53'),(323,'admin',2,'::1',1,'2026-07-20 20:23:27'),(324,'contractor',5,'::1',1,'2026-07-20 20:24:04'),(325,'engineer',4,'::1',1,'2026-07-20 20:24:43'),(326,'contractor',5,'::1',1,'2026-07-20 20:25:26'),(327,'engineer',4,'::1',1,'2026-07-20 20:26:03'),(328,'admin',2,'::1',1,'2026-07-20 20:27:00'),(329,'engineer',4,'::1',1,'2026-07-20 20:31:20'),(330,'admin',2,'::1',1,'2026-07-20 20:33:11'),(331,'jongding',12,'::1',1,'2026-07-20 20:35:14'),(332,'admin',2,'::1',1,'2026-07-20 21:10:29'),(333,'hope',23,'::1',1,'2026-07-20 21:11:14'),(334,'admin',2,'::1',1,'2026-07-20 21:11:41'),(335,'jongding',12,'::1',0,'2026-07-20 21:20:50'),(336,'jongding',12,'::1',0,'2026-07-20 21:20:54'),(337,'jongding',12,'::1',1,'2026-07-20 21:21:03'),(338,'admin',2,'::1',1,'2026-07-20 21:30:59'),(339,'admin',2,'::1',1,'2026-07-20 21:45:33'),(340,'admin',2,'::1',1,'2026-07-20 21:47:29'),(341,'engineer',4,'::1',1,'2026-07-20 21:51:16'),(342,'pf_test_citizen',NULL,'::1',1,'2026-07-20 22:07:35'),(343,'jongding',12,'::1',0,'2026-07-20 22:27:40'),(344,'jongding',12,'::1',1,'2026-07-20 22:27:45'),(345,'pf_test_citizen2',NULL,'::1',1,'2026-07-20 22:41:53'),(346,'admin',2,'::1',1,'2026-07-21 10:14:43'),(347,'admin',2,'::1',1,'2026-07-21 10:24:13'),(348,'admin',2,'::1',1,'2026-07-21 10:50:46'),(349,'engineer',4,'::1',1,'2026-07-21 10:57:17'),(350,'hope',23,'::1',1,'2026-07-21 10:57:39'),(351,'bac',3,'::1',1,'2026-07-21 10:57:54'),(352,'contractor',5,'::1',0,'2026-07-21 10:58:17'),(353,'contractor',5,'::1',1,'2026-07-21 10:58:21'),(354,'bac',3,'::1',1,'2026-07-21 10:58:50'),(355,'hope',23,'::1',1,'2026-07-21 10:59:11'),(356,'admin',2,'::1',1,'2026-07-21 10:59:29'),(357,'contractor',5,'::1',1,'2026-07-21 10:59:54'),(358,'admin',2,'::1',1,'2026-07-21 11:00:18'),(359,'engineer',4,'::1',1,'2026-07-21 11:00:35'),(360,'admin',2,'::1',1,'2026-07-21 11:01:06'),(361,'admin',2,'::1',1,'2026-07-21 11:55:43'),(362,'hope',23,'::1',1,'2026-07-21 11:57:51'),(363,'admin',2,'::1',1,'2026-07-21 11:58:46'),(364,'admin',2,'::1',1,'2026-07-21 12:07:09'),(365,'bac',3,'::1',0,'2026-07-21 12:12:13'),(366,'bac_test_check',NULL,'::1',1,'2026-07-21 12:12:50'),(367,'hope',23,'::1',1,'2026-07-21 12:14:24'),(368,'jongding',12,'::1',1,'2026-07-21 12:23:12'),(369,'admin',2,'::1',1,'2026-07-21 12:25:57'),(370,'admin',2,'::1',1,'2026-07-21 12:33:43'),(371,'admin',2,'::1',1,'2026-07-21 13:10:00'),(372,'admin',2,'::1',1,'2026-07-21 13:15:55'),(373,'admin',2,'::1',1,'2026-07-21 19:06:02'),(374,'bac',3,'::1',1,'2026-07-21 19:13:19'),(375,'engineer',4,'::1',1,'2026-07-21 19:18:33'),(376,'contractor',5,'::1',1,'2026-07-21 19:22:27'),(377,'hope',23,'::1',1,'2026-07-21 19:28:24'),(378,'jongding',12,'::1',1,'2026-07-21 19:30:54'),(379,'jongding',12,'::1',1,'2026-07-21 20:09:51'),(380,'admin',2,'::1',1,'2026-07-21 20:12:07');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `milestones`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `milestones`
--

LOCK TABLES `milestones` WRITE;
/*!40000 ALTER TABLE `milestones` DISABLE KEYS */;
/*!40000 ALTER TABLE `milestones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (9,2,'info','Staff account request approved','Sheesh (engineer) account approved and created.',NULL,1,'2026-07-14 12:25:33'),(10,2,'info','Staff account request rejected','Sheesh\'s engineer account request was rejected — gmail is already in used.',NULL,1,'2026-07-15 13:38:26'),(11,2,'info','Document verified','\"dasda\" was verified.',NULL,1,'2026-07-15 14:32:10'),(12,22,'info','Application approved','Your contractor application has been approved. Check your email to set up portal access.',NULL,0,'2026-07-15 14:54:01'),(13,2,'info','Document verified','\"asws\" was verified.',NULL,1,'2026-07-16 03:10:48'),(15,2,'info','Project rejected','HOPE Test Project was rejected — Test cleanup - not a real project.',NULL,1,'2026-07-16 03:20:24'),(23,2,'info','Engineering review: endorsed','sample3 was endorsed by Engineering Review.',NULL,1,'2026-07-16 04:20:50'),(24,2,'info','Project approved','sample3 was approved.',NULL,1,'2026-07-16 04:21:16'),(33,2,'info','Engineering review: endorsed','eyy was endorsed by Engineering Review.',NULL,1,'2026-07-16 08:30:02'),(34,2,'info','Document verified','\"dass\" was verified.',NULL,1,'2026-07-16 08:30:20'),(35,2,'info','Project approved','eyy was approved.',NULL,1,'2026-07-16 08:30:53'),(36,5,'info','Bid awarded','Your bid for eyy has been recommended for award.',NULL,1,'2026-07-16 09:20:43'),(37,5,'info','Bid awarded','Your bid for eyy has been recommended for award.',NULL,0,'2026-07-16 09:51:32'),(38,5,'info','Project status updated','eyy is now \"assigned\".',NULL,0,'2026-07-16 09:51:49'),(39,19,'info','Project status updated','eyy is now \"assigned\".',NULL,0,'2026-07-16 09:51:49'),(40,19,'info','New project assignment','You have been assigned to eyy for field monitoring.',NULL,0,'2026-07-16 09:51:49'),(41,3,'warning','Contract award rejected','JKL Builders\'s award recommendation for sample3 was rejected — Bid amount seems unrealistically low for scope of work..',NULL,1,'2026-07-16 10:26:55'),(42,2,'info','Engineering review: endorsed','Sample* was endorsed by Engineering Review — Okay siya ha.',NULL,1,'2026-07-16 13:39:22'),(43,2,'info','Document verified','\"Docu\" was verified.',NULL,1,'2026-07-16 13:40:29'),(44,2,'info','Project approved','Sample* was approved — sige okay pala yan eh.',NULL,1,'2026-07-16 13:42:14'),(45,5,'info','Bid awarded','Your bid for Sample* has been approved by HOPE.',NULL,0,'2026-07-18 04:21:46'),(46,5,'info','Project status updated','Sample* is now \"assigned\".',NULL,0,'2026-07-18 04:23:33'),(47,4,'info','Project status updated','Sample* is now \"assigned\".',NULL,1,'2026-07-18 04:23:33'),(48,4,'info','New project assignment','You have been assigned to Sample* for field monitoring.',NULL,1,'2026-07-18 04:23:34'),(49,12,'info','Feedback update','Your submitted feedback is now \"in_progress\".',NULL,1,'2026-07-18 05:12:03'),(54,23,'info','Project deletion requested','eyy (PRJ-010) has a pending deletion request awaiting your review.',NULL,0,'2026-07-19 12:43:52'),(55,2,'info','Engineering review: endorsed','Samples was endorsed by Engineering Review — Shet sarap.',NULL,1,'2026-07-20 12:18:50'),(56,2,'info','Project approved','Samples was approved — nyak.',NULL,1,'2026-07-20 12:19:39'),(57,5,'info','Bid awarded','Your bid for Samples has been approved by HOPE.',NULL,0,'2026-07-20 12:23:15'),(58,5,'info','Project status updated','Samples is now \"assigned\".',NULL,0,'2026-07-20 12:23:47'),(59,4,'info','Project status updated','Samples is now \"assigned\".',NULL,1,'2026-07-20 12:23:47'),(60,4,'info','New project assignment','You have been assigned to Samples for field monitoring.',NULL,1,'2026-07-20 12:23:47'),(61,2,'info','Engineering review: endorsed','Project Test Integration - IPMS was endorsed by Engineering Review.',NULL,1,'2026-07-21 02:57:22'),(62,2,'info','Project approved','Project Test Integration - IPMS was approved.',NULL,1,'2026-07-21 02:57:44'),(63,5,'info','Bid awarded','Your bid for Project Test Integration - IPMS has been approved by HOPE.',NULL,0,'2026-07-21 02:59:23'),(64,5,'info','Project status updated','Project Test Integration - IPMS is now \"assigned\".',NULL,0,'2026-07-21 02:59:39'),(65,4,'info','Project status updated','Project Test Integration - IPMS is now \"assigned\".',NULL,0,'2026-07-21 02:59:39'),(66,4,'info','New project assignment','You have been assigned to Project Test Integration - IPMS for field monitoring.',NULL,0,'2026-07-21 02:59:39'),(67,3,'info','Test notification','Checking if the BAC badge updates',NULL,0,'2026-07-21 04:12:02');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `otp_tokens`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `otp_tokens`
--

LOCK TABLES `otp_tokens` WRITE;
/*!40000 ALTER TABLE `otp_tokens` DISABLE KEYS */;
INSERT INTO `otp_tokens` VALUES (7,12,'citizen_verification','149086',1,0,3,'2026-07-14 12:41:12','2026-07-14 12:39:29','2026-07-14 04:39:12');
/*!40000 ALTER TABLE `otp_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_requests`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_requests`
--

LOCK TABLES `payment_requests` WRITE;
/*!40000 ALTER TABLE `payment_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_reviews`
--

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

--
-- Dumping data for table `payment_reviews`
--

LOCK TABLES `payment_reviews` WRITE;
/*!40000 ALTER TABLE `payment_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_deletion_requests`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_deletion_requests`
--

LOCK TABLES `project_deletion_requests` WRITE;
/*!40000 ALTER TABLE `project_deletion_requests` DISABLE KEYS */;
INSERT INTO `project_deletion_requests` VALUES (3,NULL,'PRJ-010','eyy','EYYY','pending',2,NULL,NULL,NULL,'2026-07-19 12:43:51','2026-07-19 12:43:51');
/*!40000 ALTER TABLE `project_deletion_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_road_geometry`
--

DROP TABLE IF EXISTS `project_road_geometry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_road_geometry` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `road_name` varchar(200) NOT NULL,
  `road_type` varchar(50) DEFAULT NULL,
  `road_status` varchar(50) DEFAULT NULL,
  `start_latitude` decimal(10,7) DEFAULT NULL,
  `start_longitude` decimal(10,7) DEFAULT NULL,
  `start_address` varchar(255) DEFAULT NULL,
  `start_barangay` varchar(100) DEFAULT NULL,
  `start_district` varchar(100) DEFAULT NULL,
  `end_latitude` decimal(10,7) DEFAULT NULL,
  `end_longitude` decimal(10,7) DEFAULT NULL,
  `end_address` varchar(255) DEFAULT NULL,
  `end_barangay` varchar(100) DEFAULT NULL,
  `end_district` varchar(100) DEFAULT NULL,
  `polyline_coordinates` text DEFAULT NULL COMMENT 'JSON array of [lat,lng] pairs',
  `estimated_length_meters` decimal(10,2) DEFAULT NULL,
  `num_segments` int(11) DEFAULT NULL,
  `bounding_box` text DEFAULT NULL COMMENT 'JSON {min_lat,min_lng,max_lat,max_lng}',
  `barangays_covered` text DEFAULT NULL COMMENT 'JSON array of barangay names',
  `districts_covered` text DEFAULT NULL COMMENT 'JSON array of district names',
  `road_width` decimal(6,2) DEFAULT NULL,
  `num_lanes` int(11) DEFAULT NULL,
  `road_surface` varchar(30) DEFAULT NULL,
  `bridge_included` tinyint(1) NOT NULL DEFAULT 0,
  `drainage_included` tinyint(1) NOT NULL DEFAULT 0,
  `bike_lane` tinyint(1) NOT NULL DEFAULT 0,
  `sidewalk` tinyint(1) NOT NULL DEFAULT 0,
  `streetlights` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_road_geometry_project` (`project_id`),
  CONSTRAINT `fk_road_geometry_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_road_geometry`
--

LOCK TABLES `project_road_geometry` WRITE;
/*!40000 ALTER TABLE `project_road_geometry` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_road_geometry` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
INSERT INTO `projects` VALUES (1,'PRJ-001','Elevated Landscape Promenade – Quezon Memorial Circle to Ninoy Aquino Parks and Wildlife','A landscaped, pedestrian- and bike-friendly elevated overpass built above Elliptical Road, linking Quezon Memorial Circle to the Ninoy Aquino Parks and Wildlife Center. Built by the Quezon City Engineering Department (QCDE) and the Parks Development and Administration Department (PDAD) in partnership with the DENR, featuring bike ramps, seating, ambient lighting, and a sculptural pergola. Construction began April 2024 and the promenade was inaugurated by Mayor Joy Belmonte on November 11, 2025.','Elliptical Road, Barangay Central, District 4, Quezon City',NULL,180000000.00,'2024-04-01','2025-11-11',100,'turnover',2,23,'2024-03-01 00:00:00',NULL,4,'2024-02-15 00:00:00',NULL,4,'2024-04-01 00:00:00',NULL,4,'2025-11-05 00:00:00',NULL,2,'2025-11-11 00:00:00','Parks Development and Administration Department (PDAD)',NULL,14.6512000,121.0494000,'Parks and Recreation','LGU General Fund','Quezon City Engineering Department (QCDE) / Parks Development and Administration Department (PDAD)','1 elevated pedestrian and bike overpass, approx. 350 linear meters','2026-07-21 12:06:20','2026-07-21 12:06:20'),(2,'PRJ-002','Barangay Santa Cruz Evacuation Center','A new evacuation center for Barangay Santa Cruz residents, completed as part of the 167 city-funded infrastructure projects Quezon City finished in 2025 (total value ₱5.6 billion citywide).','Barangay Santa Cruz, District 1, Quezon City',NULL,45000000.00,'2024-09-01','2025-08-15',100,'turnover',2,23,'2024-08-05 00:00:00',NULL,4,'2024-07-20 00:00:00',NULL,4,'2024-09-01 00:00:00',NULL,4,'2025-08-10 00:00:00',NULL,2,'2025-08-15 00:00:00','District 1 Office / Quezon City DRRMO',NULL,14.6474000,121.0089000,'Public Buildings and Facilities','20% Development Fund','Quezon City Engineering Department (QCDE)','1 multi-story barangay evacuation center','2026-07-21 12:06:20','2026-07-21 12:06:20'),(3,'PRJ-003','Quezon City Schools Division Office Building','New office building for the DepEd Quezon City Schools Division, completed and turned over among the 167 infrastructure projects the city government finished in 2025.','Barangay Ramon Magsaysay, District 1, Quezon City',NULL,120000000.00,'2024-01-15','2025-10-01',100,'turnover',2,23,'2023-12-10 00:00:00',NULL,4,'2023-11-20 00:00:00',NULL,4,'2024-01-15 00:00:00',NULL,4,'2025-09-20 00:00:00',NULL,2,'2025-10-01 00:00:00','DepEd Quezon City Schools Division',NULL,14.6620000,121.0210000,'Public Buildings and Facilities','Special Education Fund','Quezon City Schools Division Office / Quezon City Engineering Department (QCDE)','1 new DepEd Schools Division Office building','2026-07-21 12:06:20','2026-07-21 12:06:20'),(4,'PRJ-004','Amoranto Sports Complex Swimming Pool Rehabilitation','Rehabilitation of the swimming pool at the Amoranto Sports Complex, completed as part of the city\'s 2025 infrastructure program.','Barangay N.S. Amoranto (Gintong Silahis), District 1, Quezon City',NULL,35000000.00,'2025-01-15','2025-09-30',100,'turnover',2,23,'2024-12-10 00:00:00',NULL,4,'2024-11-25 00:00:00',NULL,4,'2025-01-15 00:00:00',NULL,4,'2025-09-22 00:00:00',NULL,2,'2025-09-30 00:00:00','Parks Development and Administration Department (PDAD)',NULL,14.6390000,120.9960000,'Parks and Recreation','LGU General Fund','Parks Development and Administration Department (PDAD)','1 rehabilitated competition swimming pool and deck','2026-07-21 12:06:20','2026-07-21 12:06:20'),(5,'PRJ-005','Social Services Development Department Dry Storage Warehouse','A dry storage warehouse for the Social Services Development Department (SSDD) in Payatas, completed in 2025 to support relief-goods and supplies storage for the district.','Barangay Payatas, District 2, Quezon City',NULL,28000000.00,'2025-02-01','2025-08-20',100,'turnover',2,23,'2025-01-10 00:00:00',NULL,4,'2024-12-15 00:00:00',NULL,4,'2025-02-01 00:00:00',NULL,4,'2025-08-12 00:00:00',NULL,2,'2025-08-20 00:00:00','Social Services Development Department (SSDD)',NULL,14.7160000,121.1050000,'Public Buildings and Facilities','LGU General Fund','Social Services Development Department (SSDD)','1 dry storage warehouse facility','2026-07-21 12:06:20','2026-07-21 12:06:20'),(6,'PRJ-006','Krus na Ligas Health Center Retrofit','Retrofitting and rehabilitation of the Krus na Ligas Health Center, serving residents of Barangay Krus na Ligas, U.P. Campus, and Teachers\' Village West (approx. 73,647 residents). Turned over to the city government after a 13-week rehabilitation.','Barangay Krus na Ligas, District 4, Quezon City',NULL,12000000.00,'2025-05-01','2025-08-03',100,'turnover',2,23,'2025-04-10 00:00:00',NULL,4,'2025-03-25 00:00:00',NULL,4,'2025-05-01 00:00:00',NULL,4,'2025-07-28 00:00:00',NULL,2,'2025-08-03 00:00:00','Quezon City Health Department',NULL,14.6550000,121.0670000,'Public Buildings and Facilities','20% Development Fund','Quezon City Health Department / Quezon City Engineering Department (QCDE)','1 retrofitted barangay health center','2026-07-21 12:06:20','2026-07-21 12:06:20'),(7,'PRJ-007','Elliptical Road Sidewalk and Bike Lane Rehabilitation','Rehabilitation of sidewalks and bike lanes along Elliptical Road around Quezon Memorial Circle, improving pedestrian and cyclist safety. Completed as part of the city\'s 2025 infrastructure program.','Elliptical Road, Barangay Bagong Pag-asa, District 1, Quezon City',NULL,60000000.00,'2024-11-01','2025-06-30',100,'turnover',2,23,'2024-10-05 00:00:00',NULL,4,'2024-09-20 00:00:00',NULL,4,'2024-11-01 00:00:00',NULL,4,'2025-06-22 00:00:00',NULL,2,'2025-06-30 00:00:00','Quezon City Engineering Department (QCDE)',NULL,14.6530000,121.0455000,'Roads and Bridges','LGU General Fund','Quezon City Engineering Department (QCDE)','Sidewalk and bike lane rehabilitation, full Elliptical Road loop','2026-07-21 12:06:20','2026-07-21 12:06:20'),(8,'PRJ-008','G. Araneta Avenue Flood Mitigation Structure (San Juan River)','Expansion of the drainage system along G. Araneta Avenue, replacing the previous 36-inch drainage pipe with two 1.5-meter pipes, plus a new pumping station. DPWH, San Miguel Corporation, and the MMDA are jointly dredging the adjoining San Juan River. Originally targeted for completion in February 2026; the pumping station component is behind schedule.','G. Araneta Avenue, Barangay Santo Domingo (Matalahib), District 1, Quezon City',NULL,320000000.00,'2025-03-01','2026-10-31',68,'delayed',2,23,'2025-02-05 00:00:00',NULL,4,'2025-01-15 00:00:00',NULL,4,'2025-03-01 00:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,14.6270000,121.0100000,'Drainage and Flood Control','National Government Fund','Department of Public Works and Highways (DPWH) - NCR / Metro Manila Development Authority (MMDA)','2 x 1.5-meter box drainage lines + 1 pumping station','2026-07-21 12:06:20','2026-07-21 12:06:20'),(9,'PRJ-009','Kabayani Street–Matandang Balara Bridge','A bridge across the Marikina River linking Barangay Matandang Balara, Quezon City to Barangay Malanday, Marikina City — part of a 3-bridge package across the Marikina River funded by a $175.1-million ADB loan. Construction has run well past its original 2026 target completion date.','Barangay Matandang Balara, District 3, Quezon City (river crossing to Brgy. Malanday, Marikina City)',NULL,3200000000.00,'2022-06-01','2027-06-30',60,'delayed',2,23,'2022-04-15 00:00:00',NULL,4,'2022-03-01 00:00:00',NULL,4,'2022-06-01 00:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,14.6650000,121.0850000,'Roads and Bridges','Grant/Donor Fund','Department of Public Works and Highways (DPWH) - ADB-Assisted Bridges Program','1 river-crossing bridge with approach roads','2026-07-21 12:06:20','2026-07-21 12:06:20'),(10,'PRJ-010','Quezon City Solarization Program – City Hall, QC General Hospital & Public Schools','Citywide solarization program by the Quezon City Department of Engineering (QCDE), installing rooftop solar across Quezon City Hall, Quezon City General Hospital, and 35 public school buildings. Of the 3.9 MWp target, 2.9 MWp has been energized so far, projected to save at least ₱40 million annually.','Quezon City Hall, Barangay Central, District 4, Quezon City',NULL,220000000.00,'2024-06-01','2026-12-31',74,'active',2,23,'2024-05-01 00:00:00',NULL,4,'2024-04-10 00:00:00',NULL,4,'2024-06-01 00:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,14.6488000,121.0509000,'Other','LGU General Fund','Quezon City Department of Engineering (QCDE) / General Services Department','Rooftop solar PV, 3.9 MWp target across City Hall, QC General Hospital, and 35 schools','2026-07-21 12:06:20','2026-07-21 12:06:20'),(11,'PRJ-011','Elevated Evacuation Center – Barangay Tatalon','One of three multi-level evacuation centers Quezon City is building in flood-prone barangays (Tatalon, Santo Domingo, Silangan), with a rooftop basketball court and capacity for informal-settler families, part-funded through the DBM Informal Settlers Fund.','Barangay Tatalon, District 4, Quezon City',NULL,65000000.00,'2025-06-01','2026-12-31',45,'active',2,23,'2025-05-05 00:00:00',NULL,4,'2025-04-10 00:00:00',NULL,4,'2025-06-01 00:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,14.6270000,121.0330000,'Public Buildings and Facilities','National Government Fund','Quezon City Engineering Department (QCDE) / DSWD Quezon City','1 multi-level evacuation center, rooftop basketball court','2026-07-21 12:06:20','2026-07-21 12:06:20'),(12,'PRJ-012','Elevated Evacuation Center – Barangay Santo Domingo','One of three multi-level evacuation centers Quezon City is building in flood-prone barangays (Tatalon, Santo Domingo, Silangan), with a rooftop basketball court and capacity for informal-settler families, part-funded through the DBM Informal Settlers Fund.','Barangay Santo Domingo (Matalahib), District 1, Quezon City',NULL,65000000.00,'2025-07-01','2027-01-31',38,'active',2,23,'2025-06-01 00:00:00',NULL,4,'2025-05-10 00:00:00',NULL,4,'2025-07-01 00:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,14.6300000,121.0120000,'Public Buildings and Facilities','National Government Fund','Quezon City Engineering Department (QCDE) / DSWD Quezon City','1 multi-level evacuation center, rooftop basketball court','2026-07-21 12:06:20','2026-07-21 12:06:20'),(13,'PRJ-013','DPWH 4-Storey Evacuation Center – Barangay Fairview','A completed 4-storey evacuation center built by the DPWH in Barangay Fairview, adding permanent disaster-response shelter capacity to the Novaliches district.','Barangay Fairview, District 5, Quezon City',NULL,55000000.00,'2023-02-01','2024-06-01',100,'turnover',2,23,'2023-01-10 00:00:00',NULL,4,'2022-12-05 00:00:00',NULL,4,'2023-02-01 00:00:00',NULL,4,'2024-05-20 00:00:00',NULL,2,'2024-06-01 00:00:00','Quezon City DRRMO',NULL,14.7333000,121.0500000,'Public Buildings and Facilities','National Government Fund','Department of Public Works and Highways (DPWH)','1 four-storey evacuation center','2026-07-21 12:06:20','2026-07-21 12:06:20'),(14,'PRJ-014','Pumping Station – Boundary of Barangay Santa Cruz and Barangay Mariblo','One of two identical DPWH-funded pumping stations (₱282 million each) proposed for the boundary of Barangay Santa Cruz and Barangay Mariblo, part of the 331 DPWH flood-control projects implemented in Quezon City since 2022 (~₱17 billion total). Flagged and returned by the Quezon City Engineering Department for non-compliance with the city\'s drainage master plan — as the department noted, pumping floodwater from one location to another does not resolve flooding in a non-coastal area, it simply displaces the problem. No certificate of coordination was issued by the city government.','Boundary of Barangay Santa Cruz and Barangay Mariblo, District 1, Quezon City',13,282000000.00,'2024-01-01',NULL,10,'returned',2,NULL,NULL,NULL,4,'2025-09-10 00:00:00','Non-compliant with the City\'s drainage master plan; no certificate of coordination issued. Pumping floodwater from one location to another does not resolve flooding in a non-coastal area, it merely displaces the problem elsewhere.',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,14.6480000,121.0093000,'Drainage and Flood Control','National Government Fund','Department of Public Works and Highways (DPWH) - NCR','1 flood pumping station','2026-07-21 12:06:20','2026-07-21 12:06:20'),(15,'PRJ-015','Quezon City Intermodal Transport Terminal and Depot','A planned intermodal rail-city bus terminal and depot for Quezon City, discussed by the MMDA, DOTr, GSIS, and the Quezon City government (coordination meeting held October 15, 2024), targeted for completion by 2027.','Barangay Bagong Pag-asa, District 1, Quezon City',NULL,3500000000.00,NULL,'2027-12-31',5,'planning',2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,14.6570000,121.0330000,'Other','National Government Fund','Quezon City Government / MMDA / DOTr (Joint Coordination)','1 intermodal rail-city bus terminal and depot','2026-07-21 12:06:20','2026-07-21 12:06:20');
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sidebar_badge_views`
--

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

--
-- Dumping data for table `sidebar_badge_views`
--

LOCK TABLES `sidebar_badge_views` WRITE;
/*!40000 ALTER TABLE `sidebar_badge_views` DISABLE KEYS */;
INSERT INTO `sidebar_badge_views` VALUES (1,'audit-trail','2026-07-20 20:10:36'),(1,'login-security','2026-07-20 20:11:34'),(2,'ai-risk-insights','2026-07-21 20:13:01'),(2,'budget-monitoring','2026-07-19 11:15:57'),(2,'cancelled-projects','2026-07-19 11:16:47'),(2,'citizen-feedback','2026-07-21 12:05:29'),(2,'completed-projects','2026-07-21 12:11:03'),(2,'dashboard','2026-07-21 13:21:43'),(2,'milestone-overview','2026-07-21 20:13:19'),(2,'project-approval','2026-07-21 20:12:31'),(2,'project-registration','2026-07-21 20:12:21'),(3,'contractor-evaluation','2026-07-21 10:58:52'),(4,'assigned-projects','2026-07-21 11:00:55'),(4,'engineering-review','2026-07-21 10:57:19'),(4,'inspection-review','2026-07-21 11:00:38'),(4,'milestone-update','2026-07-21 11:00:52'),(5,'assigned-projects','2026-07-21 10:59:57'),(5,'bid-results','2026-07-21 10:59:55'),(5,'open-biddings','2026-07-21 10:58:23'),(12,'dashboard','2026-07-21 19:31:01'),(12,'track-feedback','2026-07-21 20:10:16'),(23,'award-approvals','2026-07-21 10:59:13'),(23,'deletion-requests','2026-07-19 20:52:42'),(23,'notifications','2026-07-21 19:29:55'),(23,'project-approvals','2026-07-21 10:57:41');
/*!40000 ALTER TABLE `sidebar_badge_views` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_account_requests`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_account_requests`
--

LOCK TABLES `staff_account_requests` WRITE;
/*!40000 ALTER TABLE `staff_account_requests` DISABLE KEYS */;
INSERT INTO `staff_account_requests` VALUES (2,'engineer','Sheesh','ediwow','caviterawen@gmail.com',2,'approved',1,'2026-07-14 20:25:33',NULL,'2026-07-14 12:24:45'),(3,'engineer','Sheesh','ediwow','caviterawen@gmail.com',2,'rejected',1,'2026-07-15 21:38:25','gmail is already in used','2026-07-15 13:35:36');
/*!40000 ALTER TABLE `staff_account_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supporting_documents`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supporting_documents`
--

LOCK TABLES `supporting_documents` WRITE;
/*!40000 ALTER TABLE `supporting_documents` DISABLE KEYS */;
INSERT INTO `supporting_documents` VALUES (15,'contractor',11,'DTI/SEC Registration','DTI or SEC Registration','CPE112-Content-Module1v (1).pdf','uploads/supporting-documents/contractor/2026/1784123213-2fa2a18b-CPE112-Content-Module1v-1.pdf',604950,'application/pdf',1,15,1,NULL,NULL,'verified',NULL,1,'2026-07-15 21:49:31','2026-07-15 13:46:53'),(16,'contractor',11,'Business Permit','Mayor\'s / Business Permit','rizumi.pdf','uploads/supporting-documents/contractor/2026/1784123213-905de47a-rizumi.pdf',77415,'application/pdf',1,16,1,NULL,NULL,'verified',NULL,1,'2026-07-15 21:49:30','2026-07-15 13:46:53'),(17,'contractor',11,'Tax Clearance','Tax Clearance Certificate','RESUME.pdf','uploads/supporting-documents/contractor/2026/1784123213-fa527704-RESUME.pdf',176487,'application/pdf',1,17,1,NULL,NULL,'verified',NULL,1,'2026-07-15 21:49:27','2026-07-15 13:46:53'),(18,'contractor',11,'PCAB License','PCAB License','OFFICERS PROFILING TEMPLATE.docx','uploads/supporting-documents/contractor/2026/1784123213-ad4ad7d8-OFFICERS-PROFILING-TEMPLATE.docx',326464,'application/vnd.openxmlformats-officedocument.wordprocessingml.document',1,18,1,NULL,NULL,'verified',NULL,1,'2026-07-15 21:49:24','2026-07-15 13:46:53'),(19,'contractor',11,'Audited Financial Statement','Audited Financial Statement','Untitled Diagram.drawio.png','uploads/supporting-documents/contractor/2026/1784123213-d8ae4a78-Untitled-Diagram.drawio.png',35111,'image/png',1,19,1,NULL,NULL,'verified',NULL,1,'2026-07-15 21:49:22','2026-07-15 13:46:53'),(21,'project',11,'Feasibility Study','dasda','RESUME.pdf','uploads/supporting-documents/project/2026/1784125603-940dcebf-RESUME.pdf',176487,'application/pdf',1,21,1,NULL,2,'verified',NULL,1,'2026-07-15 22:32:10','2026-07-15 14:26:43'),(22,'project',12,'Feasibility Study','asws','RESUME.pdf','uploads/supporting-documents/project/2026/1784171423-95c0106d-RESUME.pdf',176487,'application/pdf',1,22,1,NULL,2,'verified','alrighty',1,'2026-07-16 11:10:47','2026-07-16 03:10:23'),(28,'project',17,'Feasibility Study','dass','RESUME.pdf','uploads/supporting-documents/project/2026/1784190558-30d4265d-RESUME.pdf',176487,'application/pdf',1,28,1,NULL,2,'verified',NULL,3,'2026-07-16 16:30:20','2026-07-16 08:29:18'),(30,'project',18,'Feasibility Study','Docu','aez2.png','uploads/supporting-documents/project/2026/1784209000-c78d1349-aez2.png',12156,'image/png',1,30,1,NULL,2,'verified','okay ka ha',3,'2026-07-16 21:40:29','2026-07-16 13:36:40'),(31,'project',19,'Feasibility Study','dswd','Quezon-City-improves-its-cycling-infrastructure-1.jpg','uploads/supporting-documents/project/2026/1784359337-7ecb67b6-Quezon-City-improves-its-cycling-infrastructure-1.jpg',261435,'image/jpeg',1,31,1,NULL,2,'pending',NULL,NULL,NULL,'2026-07-18 07:22:17'),(37,'project',23,'Feasibility Study','pwd','flood-control-qc.jpg','uploads/supporting-documents/project/2026/1784549889-e12a5be2-flood-control-qc.jpg',76821,'image/jpeg',1,37,1,NULL,2,'pending',NULL,NULL,NULL,'2026-07-20 12:18:09'),(38,'project',32,'Feasibility Study','TEST','Quezon-City-improves-its-cycling-infrastructure-1.jpg','uploads/supporting-documents/project/2026/1784602619-3ed09087-Quezon-City-improves-its-cycling-infrastructure-1.jpg',261435,'image/jpeg',1,38,1,NULL,2,'pending',NULL,NULL,NULL,'2026-07-21 02:56:59'),(39,'project',35,'Feasibility Study','ssss','qc-elevated-promenade-1-1712648532.jpeg','uploads/supporting-documents/project/2026/1784608810-5a07f97e-qc-elevated-promenade-1-1712648532.jpeg',381789,'image/jpeg',1,39,1,NULL,2,'pending',NULL,NULL,NULL,'2026-07-21 04:40:10');
/*!40000 ALTER TABLE `supporting_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'site_name','LGU Infrastructure Project Management System','string',1,'2026-07-13 14:02:38'),(2,'support_email','ipms.systemlgu@gmail.com','string',1,'2026-07-13 14:02:38'),(3,'session_timeout_minutes','30','integer',1,'2026-07-13 14:02:38'),(4,'login_max_attempts','5','integer',1,'2026-07-13 14:01:56'),(5,'login_lockout_minutes','15','integer',1,'2026-07-13 14:01:56'),(6,'maintenance_mode','0','boolean',1,'2026-07-13 14:01:56'),(19,'require_staff_2fa','0','boolean',1,'2026-07-14 03:46:29');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `urban_planning_inspection_photos`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `urban_planning_inspection_photos`
--

LOCK TABLES `urban_planning_inspection_photos` WRITE;
/*!40000 ALTER TABLE `urban_planning_inspection_photos` DISABLE KEYS */;
INSERT INTO `urban_planning_inspection_photos` VALUES (1,1,'uploads/urban-planning-inspections/2026/1784440380-2b7906c0-test.png',NULL,'2026-07-19 05:53:00');
/*!40000 ALTER TABLE `urban_planning_inspection_photos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `urban_planning_inspections`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `urban_planning_inspections`
--

LOCK TABLES `urban_planning_inspections` WRITE;
/*!40000 ALTER TABLE `urban_planning_inspections` DISABLE KEYS */;
INSERT INTO `urban_planning_inspections` VALUES (1,'RD-2026-014','Commonwealth Avenue Service Road','Commonwealth','District 5','Arterial Road',2.40,'high','Urban Planning Office - Field Survey Team','2026-07-15',14.6989000,121.0855000,'UPS-REQ-3391','completed',4,'2026-07-19','Fair','Poor','Good','Fair','Good','Excellent','Fair','medium','Repair','Potholes observed along the service road, moderate depth.',14.6990000,121.0856000,'2026-07-19 13:53:00','2026-07-19 13:55:37','2026-07-19 05:46:51','2026-07-19 05:55:37'),(2,'RD-2026-021','Katipunan Extension','Loyola Heights','District 3','Collector Road',1.10,'urgent','Urban Planning Office - Complaints Desk','2026-07-17',14.6390000,121.0750000,'UPS-REQ-3402','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-19 05:47:04','2026-07-19 05:47:04'),(3,'RD-2026-009','Batasan Road','Batasan Hills','District 2','Barangay Road',0.80,'medium','Urban Planning Office - Annual Survey','2026-07-10',14.6960000,121.1030000,'UPS-REQ-3355','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-19 05:47:04','2026-07-19 05:47:04'),(4,'RD-2025-188','Visayas Avenue','Vasra','District 1','Arterial Road',3.20,'low','Urban Planning Office - Routine Monitoring','2026-06-28',14.6720000,121.0480000,'UPS-REQ-3201','pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-19 05:47:04','2026-07-19 05:47:04');
/*!40000 ALTER TABLE `urban_planning_inspections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'superadmin','superadmin@ipms.local','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','System Super Admin','super_admin','active','2026-07-20 20:09:49','2026-06-08 10:59:37','2026-07-20 12:09:49'),(2,'admin','admin@ipms.local','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Infrastructure Admin','admin','active','2026-07-21 20:12:06','2026-06-08 10:59:37','2026-07-21 12:12:06'),(3,'bac','bac@ipms.local','$2y$10$k/wVFSi.lo5U7AGEWxFD5u/ElTpqg37.aWnkmN5VZ3E4E2rM.CH5.','BAC Secretariat','bac','active','2026-07-21 19:13:19','2026-06-08 10:59:37','2026-07-21 11:13:19'),(4,'engineer','engineer@ipms.local','$2y$10$VdpOg0pCQbBgBvy/a9JEnepahLOkR7Oy//W6nGfrZKR0XbHNIsAEW','Municipal Engineer','engineer','active','2026-07-21 19:18:33','2026-06-08 10:59:37','2026-07-21 11:18:33'),(5,'contractor','contractor@ipms.local','$2y$10$j1aSpoztS.H6zIHCag.J4O7oQFBh/I3FWa1JysczZaoSWqqz1cwDu','Accredited Contractor','contractor','active','2026-07-21 19:22:27','2026-06-08 10:59:37','2026-07-21 11:22:27'),(6,'citizen','citizen@ipms.local','$2y$10$YZfZSOnrNsMNN8EtNIuP3OaE6RX6p/FZ9rtKr7oMqDO7IkqfUV5h2','Citizen Viewer','citizen','active','2026-07-18 15:46:15','2026-06-08 10:59:37','2026-07-18 07:46:15'),(12,'jongding','caviterawen5@gmail.com','$2y$10$WiPu94aituuPU54w705cvuFAJ1xBCeU3kUbStzvPWJD9UK.LzBEWK','Rawen Cavite','citizen','active','2026-07-21 20:09:51','2026-07-14 04:13:41','2026-07-21 12:09:51'),(19,'ediwow','caviterawen@gmail.com','$2y$10$0ztRA82SpV9BwQ1z3qmDr.ivAd4CqRTXzKPYk3aodE.uIntnO0St6','Sheesh','engineer','active','2026-07-15 21:40:51','2026-07-14 12:25:33','2026-07-15 13:40:51'),(22,'contractor11_a78b92','asd@gmail.com','$2y$10$6liPTxBI5IJ/XFsUnvh.puEDb/Fce37eyXz/jGKRrSRShu0p00.Kq','asdas','contractor','active',NULL,'2026-07-15 14:54:01','2026-07-15 14:54:01'),(23,'hope','hope@ipms.local','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Head of Procuring Entity','hope','active','2026-07-21 19:28:24','2026-07-16 03:18:30','2026-07-21 11:28:24'),(28,'dhicerv_superadmin','dhicerv+superadmin@gmail.com','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Dhicerv','super_admin','active',NULL,'2026-07-21 12:41:44','2026-07-21 12:41:44'),(29,'dhicerv_admin','dhicerv+admin@gmail.com','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Dhicerv','admin','active',NULL,'2026-07-21 12:41:44','2026-07-21 12:41:44'),(30,'dhicerv_bac','dhicerv+bac@gmail.com','$2y$10$k/wVFSi.lo5U7AGEWxFD5u/ElTpqg37.aWnkmN5VZ3E4E2rM.CH5.','Dhicerv','bac','active',NULL,'2026-07-21 12:41:44','2026-07-21 12:41:44'),(31,'dhicerv_engineer','dhicerv+engineer@gmail.com','$2y$10$VdpOg0pCQbBgBvy/a9JEnepahLOkR7Oy//W6nGfrZKR0XbHNIsAEW','Dhicerv','engineer','active',NULL,'2026-07-21 12:41:44','2026-07-21 12:41:44'),(32,'dhicerv_contractor','dhicerv+contractor@gmail.com','$2y$10$j1aSpoztS.H6zIHCag.J4O7oQFBh/I3FWa1JysczZaoSWqqz1cwDu','Dhicerv','contractor','active',NULL,'2026-07-21 12:41:44','2026-07-21 12:41:44'),(33,'dhicerv_hope','dhicerv+hope@gmail.com','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Dhicerv','hope','active',NULL,'2026-07-21 12:41:44','2026-07-21 12:41:44'),(34,'evebrasileno_superadmin','evebrasileno+superadmin@gmail.com','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Evebrasileno','super_admin','active',NULL,'2026-07-21 12:41:44','2026-07-21 12:41:44'),(35,'evebrasileno_admin','evebrasileno+admin@gmail.com','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Evebrasileno','admin','active',NULL,'2026-07-21 12:41:44','2026-07-21 12:41:44'),(36,'evebrasileno_bac','evebrasileno+bac@gmail.com','$2y$10$k/wVFSi.lo5U7AGEWxFD5u/ElTpqg37.aWnkmN5VZ3E4E2rM.CH5.','Evebrasileno','bac','active',NULL,'2026-07-21 12:41:44','2026-07-21 12:41:44'),(37,'evebrasileno_engineer','evebrasileno+engineer@gmail.com','$2y$10$VdpOg0pCQbBgBvy/a9JEnepahLOkR7Oy//W6nGfrZKR0XbHNIsAEW','Evebrasileno','engineer','active',NULL,'2026-07-21 12:41:44','2026-07-21 12:41:44'),(38,'evebrasileno_contractor','evebrasileno+contractor@gmail.com','$2y$10$j1aSpoztS.H6zIHCag.J4O7oQFBh/I3FWa1JysczZaoSWqqz1cwDu','Evebrasileno','contractor','active',NULL,'2026-07-21 12:41:44','2026-07-21 12:41:44'),(39,'evebrasileno_hope','evebrasileno+hope@gmail.com','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Evebrasileno','hope','active',NULL,'2026-07-21 12:41:44','2026-07-21 12:41:44'),(40,'stevennicole30_superadmin','stevennicole30+superadmin@gmail.com','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Stevennicole30','super_admin','active',NULL,'2026-07-21 12:41:44','2026-07-21 12:41:44'),(41,'stevennicole30_admin','stevennicole30+admin@gmail.com','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Stevennicole30','admin','active',NULL,'2026-07-21 12:41:44','2026-07-21 12:41:44'),(42,'stevennicole30_bac','stevennicole30+bac@gmail.com','$2y$10$k/wVFSi.lo5U7AGEWxFD5u/ElTpqg37.aWnkmN5VZ3E4E2rM.CH5.','Stevennicole30','bac','active',NULL,'2026-07-21 12:41:44','2026-07-21 12:41:44'),(43,'stevennicole30_engineer','stevennicole30+engineer@gmail.com','$2y$10$VdpOg0pCQbBgBvy/a9JEnepahLOkR7Oy//W6nGfrZKR0XbHNIsAEW','Stevennicole30','engineer','active',NULL,'2026-07-21 12:41:45','2026-07-21 12:41:45'),(44,'stevennicole30_contractor','stevennicole30+contractor@gmail.com','$2y$10$j1aSpoztS.H6zIHCag.J4O7oQFBh/I3FWa1JysczZaoSWqqz1cwDu','Stevennicole30','contractor','active',NULL,'2026-07-21 12:41:45','2026-07-21 12:41:45'),(45,'stevennicole30_hope','stevennicole30+hope@gmail.com','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Stevennicole30','hope','active',NULL,'2026-07-21 12:41:45','2026-07-21 12:41:45'),(46,'jaysonmagrimbao_superadmin','jaysonmagrimbao+superadmin@gmail.com','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Jaysonmagrimbao','super_admin','active',NULL,'2026-07-21 12:41:45','2026-07-21 12:41:45'),(47,'jaysonmagrimbao_admin','jaysonmagrimbao+admin@gmail.com','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Jaysonmagrimbao','admin','active',NULL,'2026-07-21 12:41:45','2026-07-21 12:41:45'),(48,'jaysonmagrimbao_bac','jaysonmagrimbao+bac@gmail.com','$2y$10$k/wVFSi.lo5U7AGEWxFD5u/ElTpqg37.aWnkmN5VZ3E4E2rM.CH5.','Jaysonmagrimbao','bac','active',NULL,'2026-07-21 12:41:45','2026-07-21 12:41:45'),(49,'jaysonmagrimbao_engineer','jaysonmagrimbao+engineer@gmail.com','$2y$10$VdpOg0pCQbBgBvy/a9JEnepahLOkR7Oy//W6nGfrZKR0XbHNIsAEW','Jaysonmagrimbao','engineer','active',NULL,'2026-07-21 12:41:45','2026-07-21 12:41:45'),(50,'jaysonmagrimbao_contractor','jaysonmagrimbao+contractor@gmail.com','$2y$10$j1aSpoztS.H6zIHCag.J4O7oQFBh/I3FWa1JysczZaoSWqqz1cwDu','Jaysonmagrimbao','contractor','active',NULL,'2026-07-21 12:41:45','2026-07-21 12:41:45'),(51,'jaysonmagrimbao_hope','jaysonmagrimbao+hope@gmail.com','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Jaysonmagrimbao','hope','active',NULL,'2026-07-21 12:41:45','2026-07-21 12:41:45'),(52,'caviterawen5_superadmin','caviterawen5+superadmin@gmail.com','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Caviterawen5','super_admin','active',NULL,'2026-07-21 12:41:45','2026-07-21 12:41:45'),(53,'caviterawen5_admin','caviterawen5+admin@gmail.com','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Caviterawen5','admin','active',NULL,'2026-07-21 12:41:45','2026-07-21 12:41:45'),(54,'caviterawen5_bac','caviterawen5+bac@gmail.com','$2y$10$k/wVFSi.lo5U7AGEWxFD5u/ElTpqg37.aWnkmN5VZ3E4E2rM.CH5.','Caviterawen5','bac','active',NULL,'2026-07-21 12:41:45','2026-07-21 12:41:45'),(55,'caviterawen5_engineer','caviterawen5+engineer@gmail.com','$2y$10$VdpOg0pCQbBgBvy/a9JEnepahLOkR7Oy//W6nGfrZKR0XbHNIsAEW','Caviterawen5','engineer','active',NULL,'2026-07-21 12:41:45','2026-07-21 12:41:45'),(56,'caviterawen5_contractor','caviterawen5+contractor@gmail.com','$2y$10$j1aSpoztS.H6zIHCag.J4O7oQFBh/I3FWa1JysczZaoSWqqz1cwDu','Caviterawen5','contractor','active',NULL,'2026-07-21 12:41:45','2026-07-21 12:41:45'),(57,'caviterawen5_hope','caviterawen5+hope@gmail.com','$2y$10$2TKc4G0kzPoHoaxpuxtiLuxJexEHos62W5/98pjMxEXaAyrxZ8PWS','Caviterawen5','hope','active',NULL,'2026-07-21 12:41:45','2026-07-21 12:41:45');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'ipms_infra'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-21 20:46:04
