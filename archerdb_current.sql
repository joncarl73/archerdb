-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               11.4.0-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             12.3.0.6589
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table archer_db.archer_profiles
CREATE TABLE IF NOT EXISTS `archer_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `gender` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `handedness` varchar(255) DEFAULT NULL,
  `para_archer` tinyint(1) NOT NULL DEFAULT 0,
  `uses_wheelchair` tinyint(1) NOT NULL DEFAULT 0,
  `club_affiliation` varchar(255) DEFAULT NULL,
  `us_archery_number` varchar(30) DEFAULT NULL,
  `country` char(2) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `archer_profiles_user_id_unique` (`user_id`),
  KEY `archer_profiles_country_index` (`country`),
  CONSTRAINT `archer_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.bow_types
CREATE TABLE IF NOT EXISTS `bow_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `label` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bow_types_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.cache
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.cache_locks
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.classes
CREATE TABLE IF NOT EXISTS `classes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `label` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classes_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.companies
CREATE TABLE IF NOT EXISTS `companies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `owner_user_id` bigint(20) unsigned DEFAULT NULL,
  `company_name` varchar(255) NOT NULL,
  `legal_name` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `support_email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state_region` varchar(255) DEFAULT NULL,
  `postal_code` varchar(255) DEFAULT NULL,
  `country` varchar(2) DEFAULT NULL,
  `industry` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `pricing_tier_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `companies_owner_user_id_foreign` (`owner_user_id`),
  KEY `companies_company_name_index` (`company_name`),
  KEY `companies_pricing_tier_id_foreign` (`pricing_tier_id`),
  CONSTRAINT `companies_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `companies_pricing_tier_id_foreign` FOREIGN KEY (`pricing_tier_id`) REFERENCES `pricing_tiers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.disciplines
CREATE TABLE IF NOT EXISTS `disciplines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `label` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `disciplines_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.divisions
CREATE TABLE IF NOT EXISTS `divisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `label` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `divisions_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.events
CREATE TABLE IF NOT EXISTS `events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `public_uuid` char(36) NOT NULL,
  `title` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `kind` varchar(255) NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `type` enum('open','closed') NOT NULL DEFAULT 'open',
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `lanes_count` smallint(5) unsigned NOT NULL DEFAULT 10,
  `lane_breakdown` varchar(16) DEFAULT NULL,
  `ends_per_session` smallint(5) unsigned DEFAULT NULL,
  `arrows_per_end` tinyint(3) unsigned DEFAULT NULL,
  `scoring_mode` enum('personal_device','tablet') NOT NULL DEFAULT 'personal_device',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `ruleset_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `events_public_uuid_unique` (`public_uuid`),
  KEY `events_company_id_foreign` (`company_id`),
  KEY `events_ruleset_id_foreign` (`ruleset_id`),
  CONSTRAINT `events_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_ruleset_id_foreign` FOREIGN KEY (`ruleset_id`) REFERENCES `rulesets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.event_checkins
CREATE TABLE IF NOT EXISTS `event_checkins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `event_line_time_id` bigint(20) unsigned DEFAULT NULL,
  `participant_id` bigint(20) unsigned DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `lane_number` smallint(5) unsigned DEFAULT NULL,
  `lane_slot` varchar(1) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_line_time_lane_slot` (`event_line_time_id`,`lane_number`,`lane_slot`),
  KEY `event_checkins_event_line_idx` (`event_id`,`event_line_time_id`),
  KEY `event_checkins_participant_id_foreign` (`participant_id`),
  CONSTRAINT `event_checkins_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_checkins_event_line_time_id_foreign` FOREIGN KEY (`event_line_time_id`) REFERENCES `event_line_times` (`id`) ON DELETE SET NULL,
  CONSTRAINT `event_checkins_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `event_participants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.event_kiosk_sessions
CREATE TABLE IF NOT EXISTS `event_kiosk_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `event_line_time_id` bigint(20) unsigned NOT NULL,
  `participants` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`participants`)),
  `lanes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`lanes`)),
  `token` varchar(80) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_kiosk_sessions_token_unique` (`token`),
  KEY `event_kiosk_sessions_event_line_time_id_foreign` (`event_line_time_id`),
  KEY `ekiosk_event_line_idx` (`event_id`,`event_line_time_id`),
  KEY `ekiosk_event_active_idx` (`event_id`,`is_active`),
  CONSTRAINT `event_kiosk_sessions_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_kiosk_sessions_event_line_time_id_foreign` FOREIGN KEY (`event_line_time_id`) REFERENCES `event_line_times` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.event_line_times
CREATE TABLE IF NOT EXISTS `event_line_times` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `line_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `capacity` smallint(5) unsigned NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_line_times_event_id_line_date_start_time_unique` (`event_id`,`line_date`,`start_time`),
  KEY `event_line_times_event_id_line_date_index` (`event_id`,`line_date`),
  CONSTRAINT `event_line_times_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.event_participants
CREATE TABLE IF NOT EXISTS `event_participants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `membership_id` varchar(64) DEFAULT NULL,
  `club` varchar(128) DEFAULT NULL,
  `division` varchar(64) DEFAULT NULL,
  `bow_type` varchar(32) DEFAULT NULL,
  `gender` varchar(16) DEFAULT NULL,
  `is_para` tinyint(1) NOT NULL DEFAULT 0,
  `uses_wheelchair` tinyint(1) NOT NULL DEFAULT 0,
  `classification` varchar(64) DEFAULT NULL,
  `age_class` varchar(32) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_participants_user_id_foreign` (`user_id`),
  KEY `event_participants_event_id_last_name_first_name_index` (`event_id`,`last_name`,`first_name`),
  KEY `event_participants_event_id_division_bow_type_index` (`event_id`,`division`,`bow_type`),
  CONSTRAINT `event_participants_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_participants_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.event_ruleset_overrides
CREATE TABLE IF NOT EXISTS `event_ruleset_overrides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `overrides` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`overrides`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_ruleset_overrides_event_id_foreign` (`event_id`),
  CONSTRAINT `event_ruleset_overrides_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.event_users
CREATE TABLE IF NOT EXISTS `event_users` (
  `event_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`event_id`,`user_id`),
  KEY `event_users_user_id_index` (`user_id`),
  CONSTRAINT `event_users_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.failed_jobs
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.jobs
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.job_batches
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.kiosk_sessions
CREATE TABLE IF NOT EXISTS `kiosk_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `league_id` bigint(20) unsigned NOT NULL,
  `week_number` int(10) unsigned NOT NULL,
  `participants` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`participants`)),
  `lanes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`lanes`)),
  `token` varchar(64) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kiosk_sessions_token_unique` (`token`),
  KEY `kiosk_sessions_league_id_foreign` (`league_id`),
  KEY `kiosk_sessions_created_by_foreign` (`created_by`),
  CONSTRAINT `kiosk_sessions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kiosk_sessions_league_id_foreign` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.leagues
CREATE TABLE IF NOT EXISTS `leagues` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_uuid` char(36) NOT NULL,
  `owner_id` bigint(20) unsigned NOT NULL,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `length_weeks` smallint(5) unsigned NOT NULL,
  `day_of_week` tinyint(3) unsigned NOT NULL,
  `start_date` date NOT NULL,
  `registration_start_date` date DEFAULT NULL,
  `registration_end_date` date DEFAULT NULL,
  `type` enum('open','closed') NOT NULL DEFAULT 'open',
  `x_ring_value` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `scoring_mode` enum('personal_device','tablet') NOT NULL DEFAULT 'personal_device',
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `lanes_count` smallint(5) unsigned NOT NULL DEFAULT 10,
  `lane_breakdown` varchar(8) NOT NULL DEFAULT 'single',
  `ends_per_day` smallint(5) unsigned NOT NULL DEFAULT 10,
  `arrows_per_end` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `price_cents` int(10) unsigned DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `stripe_account_id` varchar(255) DEFAULT NULL,
  `stripe_product_id` varchar(255) DEFAULT NULL,
  `stripe_price_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leagues_public_uuid_unique` (`public_uuid`),
  KEY `leagues_owner_id_type_is_published_index` (`owner_id`,`type`,`is_published`),
  KEY `leagues_company_id_index` (`company_id`),
  CONSTRAINT `leagues_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leagues_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.league_checkins
CREATE TABLE IF NOT EXISTS `league_checkins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `league_id` bigint(20) unsigned NOT NULL,
  `participant_id` bigint(20) unsigned DEFAULT NULL,
  `participant_name` varchar(160) NOT NULL,
  `participant_email` varchar(190) DEFAULT NULL,
  `week_number` smallint(5) unsigned NOT NULL,
  `lane_number` smallint(5) unsigned NOT NULL,
  `lane_slot` varchar(10) NOT NULL DEFAULT 'single',
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `league_checkins_league_id_foreign` (`league_id`),
  KEY `league_checkins_participant_id_foreign` (`participant_id`),
  CONSTRAINT `league_checkins_league_id_foreign` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `league_checkins_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `league_participants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.league_infos
CREATE TABLE IF NOT EXISTS `league_infos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `league_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `registration_url` varchar(255) DEFAULT NULL,
  `banner_path` varchar(255) DEFAULT NULL,
  `content_html` longtext DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `league_infos_league_id_unique` (`league_id`),
  CONSTRAINT `league_infos_league_id_foreign` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.league_participants
CREATE TABLE IF NOT EXISTS `league_participants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `league_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `checked_in` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `league_participants_league_id_email_unique` (`league_id`,`email`),
  KEY `league_participants_user_id_foreign` (`user_id`),
  CONSTRAINT `league_participants_league_id_foreign` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `league_participants_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.league_users
CREATE TABLE IF NOT EXISTS `league_users` (
  `league_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`league_id`,`user_id`),
  KEY `league_users_user_id_index` (`user_id`),
  CONSTRAINT `league_users_league_id_foreign` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `league_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.league_weeks
CREATE TABLE IF NOT EXISTS `league_weeks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `league_id` bigint(20) unsigned NOT NULL,
  `week_number` smallint(5) unsigned NOT NULL,
  `date` date NOT NULL,
  `ends` smallint(5) unsigned NOT NULL DEFAULT 10,
  `arrows_per_end` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `is_canceled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `league_weeks_league_id_week_number_unique` (`league_id`,`week_number`),
  CONSTRAINT `league_weeks_league_id_foreign` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.league_week_ends
CREATE TABLE IF NOT EXISTS `league_week_ends` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `league_week_score_id` bigint(20) unsigned NOT NULL,
  `end_number` smallint(5) unsigned NOT NULL,
  `scores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`scores`)),
  `end_score` int(10) unsigned NOT NULL DEFAULT 0,
  `x_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `league_week_ends_league_week_score_id_end_number_unique` (`league_week_score_id`,`end_number`),
  CONSTRAINT `league_week_ends_league_week_score_id_foreign` FOREIGN KEY (`league_week_score_id`) REFERENCES `league_week_scores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.league_week_scores
CREATE TABLE IF NOT EXISTS `league_week_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `league_id` bigint(20) unsigned NOT NULL,
  `league_week_id` bigint(20) unsigned NOT NULL,
  `league_participant_id` bigint(20) unsigned NOT NULL,
  `arrows_per_end` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `ends_planned` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `max_score` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `x_value` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `total_score` int(10) unsigned NOT NULL DEFAULT 0,
  `x_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_week_participant` (`league_week_id`,`league_participant_id`),
  KEY `league_week_scores_league_id_foreign` (`league_id`),
  KEY `league_week_scores_league_participant_id_foreign` (`league_participant_id`),
  CONSTRAINT `league_week_scores_league_id_foreign` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `league_week_scores_league_participant_id_foreign` FOREIGN KEY (`league_participant_id`) REFERENCES `league_participants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `league_week_scores_league_week_id_foreign` FOREIGN KEY (`league_week_id`) REFERENCES `league_weeks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.loadouts
CREATE TABLE IF NOT EXISTS `loadouts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `bow_type` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loadouts_user_id_name_unique` (`user_id`,`name`),
  CONSTRAINT `loadouts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.loadout_items
CREATE TABLE IF NOT EXISTS `loadout_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `loadout_id` bigint(20) unsigned NOT NULL,
  `category` varchar(255) NOT NULL,
  `manufacturer_id` bigint(20) unsigned DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `specs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specs`)),
  `position` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loadout_items_manufacturer_id_foreign` (`manufacturer_id`),
  KEY `loadout_items_loadout_id_category_index` (`loadout_id`,`category`),
  CONSTRAINT `loadout_items_loadout_id_foreign` FOREIGN KEY (`loadout_id`) REFERENCES `loadouts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `loadout_items_manufacturer_id_foreign` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.manufacturers
CREATE TABLE IF NOT EXISTS `manufacturers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`categories`)),
  `website` varchar(255) DEFAULT NULL,
  `country` char(2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `manufacturers_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.migrations
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.orders
CREATE TABLE IF NOT EXISTS `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `seller_id` bigint(20) unsigned NOT NULL,
  `buyer_id` bigint(20) unsigned DEFAULT NULL,
  `buyer_email` varchar(255) DEFAULT NULL,
  `currency` varchar(3) NOT NULL,
  `subtotal_cents` int(10) unsigned NOT NULL,
  `application_fee_cents` int(10) unsigned NOT NULL DEFAULT 0,
  `total_cents` int(10) unsigned NOT NULL,
  `status` enum('initiated','paid','canceled','failed','refunded') NOT NULL DEFAULT 'initiated',
  `stripe_checkout_session_id` varchar(255) DEFAULT NULL,
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `stripe_charge_id` varchar(255) DEFAULT NULL,
  `stripe_transfer_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orders_seller_id_foreign` (`seller_id`),
  KEY `orders_buyer_id_foreign` (`buyer_id`),
  KEY `orders_stripe_checkout_session_id_index` (`stripe_checkout_session_id`),
  KEY `orders_stripe_payment_intent_id_index` (`stripe_payment_intent_id`),
  KEY `orders_stripe_charge_id_index` (`stripe_charge_id`),
  KEY `orders_stripe_transfer_id_index` (`stripe_transfer_id`),
  CONSTRAINT `orders_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.order_items
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `unit_price_cents` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `line_total_cents` int(10) unsigned NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  KEY `order_items_product_id_foreign` (`product_id`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.participant_imports
CREATE TABLE IF NOT EXISTS `participant_imports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_uuid` char(36) NOT NULL,
  `league_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `row_count` int(10) unsigned NOT NULL,
  `unit_price_cents` int(10) unsigned NOT NULL DEFAULT 200,
  `amount_cents` bigint(20) unsigned NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'usd',
  `status` enum('pending_payment','paid','processing','completed','canceled','failed') NOT NULL DEFAULT 'pending_payment',
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `stripe_checkout_session_id` varchar(255) DEFAULT NULL,
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `error_text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `event_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `participant_imports_public_uuid_unique` (`public_uuid`),
  KEY `participant_imports_user_id_foreign` (`user_id`),
  KEY `participant_imports_order_id_foreign` (`order_id`),
  KEY `participant_imports_event_id_foreign` (`event_id`),
  KEY `participant_imports_league_id_event_id_index` (`league_id`,`event_id`),
  CONSTRAINT `participant_imports_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `participant_imports_league_id_foreign` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `participant_imports_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `participant_imports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.password_reset_tokens
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.personal_access_tokens
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.pricing_tiers
CREATE TABLE IF NOT EXISTS `pricing_tiers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `league_participant_fee_cents` int(10) unsigned NOT NULL DEFAULT 200,
  `competition_participant_fee_cents` int(10) unsigned NOT NULL DEFAULT 200,
  `currency` varchar(3) NOT NULL DEFAULT 'usd',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pricing_tiers_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.products
CREATE TABLE IF NOT EXISTS `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `seller_id` bigint(20) unsigned NOT NULL,
  `productable_type` varchar(255) NOT NULL,
  `productable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `price_cents` int(10) unsigned NOT NULL,
  `platform_fee_bps` int(10) unsigned DEFAULT NULL,
  `platform_fee_cents` int(11) DEFAULT NULL,
  `settlement_mode` enum('open','closed') NOT NULL DEFAULT 'open',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `products_seller_id_foreign` (`seller_id`),
  KEY `products_productable_type_productable_id_index` (`productable_type`,`productable_id`),
  CONSTRAINT `products_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.refunds
CREATE TABLE IF NOT EXISTS `refunds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `amount_cents` int(10) unsigned NOT NULL,
  `stripe_refund_id` varchar(255) DEFAULT NULL,
  `application_fee_refunded` tinyint(1) NOT NULL DEFAULT 0,
  `transfer_reversed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `refunds_order_id_foreign` (`order_id`),
  KEY `refunds_stripe_refund_id_index` (`stripe_refund_id`),
  CONSTRAINT `refunds_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.rulesets
CREATE TABLE IF NOT EXISTS `rulesets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `org` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `ends_per_session` smallint(5) unsigned NOT NULL DEFAULT 10,
  `arrows_per_end` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `lane_breakdown` varchar(16) NOT NULL DEFAULT 'single',
  `scoring_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`scoring_values`)),
  `x_value` smallint(5) unsigned DEFAULT NULL,
  `distances_m` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`distances_m`)),
  `schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`schema`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rulesets_slug_unique` (`slug`),
  KEY `rulesets_company_id_index` (`company_id`),
  CONSTRAINT `rulesets_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.ruleset_bow_type
CREATE TABLE IF NOT EXISTS `ruleset_bow_type` (
  `ruleset_id` bigint(20) unsigned NOT NULL,
  `bow_type_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`ruleset_id`,`bow_type_id`),
  KEY `ruleset_bow_type_bow_type_id_foreign` (`bow_type_id`),
  CONSTRAINT `ruleset_bow_type_bow_type_id_foreign` FOREIGN KEY (`bow_type_id`) REFERENCES `bow_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ruleset_bow_type_ruleset_id_foreign` FOREIGN KEY (`ruleset_id`) REFERENCES `rulesets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.ruleset_class
CREATE TABLE IF NOT EXISTS `ruleset_class` (
  `ruleset_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`ruleset_id`,`class_id`),
  KEY `ruleset_class_class_id_foreign` (`class_id`),
  CONSTRAINT `ruleset_class_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ruleset_class_ruleset_id_foreign` FOREIGN KEY (`ruleset_id`) REFERENCES `rulesets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.ruleset_discipline
CREATE TABLE IF NOT EXISTS `ruleset_discipline` (
  `ruleset_id` bigint(20) unsigned NOT NULL,
  `discipline_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`ruleset_id`,`discipline_id`),
  KEY `ruleset_discipline_discipline_id_foreign` (`discipline_id`),
  CONSTRAINT `ruleset_discipline_discipline_id_foreign` FOREIGN KEY (`discipline_id`) REFERENCES `disciplines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ruleset_discipline_ruleset_id_foreign` FOREIGN KEY (`ruleset_id`) REFERENCES `rulesets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.ruleset_division
CREATE TABLE IF NOT EXISTS `ruleset_division` (
  `ruleset_id` bigint(20) unsigned NOT NULL,
  `division_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`ruleset_id`,`division_id`),
  KEY `ruleset_division_division_id_foreign` (`division_id`),
  CONSTRAINT `ruleset_division_division_id_foreign` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ruleset_division_ruleset_id_foreign` FOREIGN KEY (`ruleset_id`) REFERENCES `rulesets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.ruleset_target_face
CREATE TABLE IF NOT EXISTS `ruleset_target_face` (
  `ruleset_id` bigint(20) unsigned NOT NULL,
  `target_face_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`ruleset_id`,`target_face_id`),
  KEY `ruleset_target_face_target_face_id_foreign` (`target_face_id`),
  CONSTRAINT `ruleset_target_face_ruleset_id_foreign` FOREIGN KEY (`ruleset_id`) REFERENCES `rulesets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ruleset_target_face_target_face_id_foreign` FOREIGN KEY (`target_face_id`) REFERENCES `target_faces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.sellers
CREATE TABLE IF NOT EXISTS `sellers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `stripe_account_id` varchar(255) DEFAULT NULL,
  `default_platform_fee_bps` int(10) unsigned NOT NULL DEFAULT 500,
  `default_platform_fee_cents` int(11) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sellers_owner_id_foreign` (`owner_id`),
  KEY `sellers_stripe_account_id_index` (`stripe_account_id`),
  CONSTRAINT `sellers_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.sessions
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.target_faces
CREATE TABLE IF NOT EXISTS `target_faces` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `label` varchar(255) NOT NULL,
  `kind` varchar(255) NOT NULL DEFAULT 'wa_target',
  `diameter_cm` smallint(5) unsigned DEFAULT NULL,
  `zones` varchar(255) NOT NULL DEFAULT '10',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `target_faces_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.training_ends
CREATE TABLE IF NOT EXISTS `training_ends` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `training_session_id` bigint(20) unsigned NOT NULL,
  `end_number` smallint(5) unsigned NOT NULL,
  `scores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`scores`)),
  `end_score` smallint(5) unsigned NOT NULL DEFAULT 0,
  `x_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `training_ends_training_session_id_end_number_unique` (`training_session_id`,`end_number`),
  CONSTRAINT `training_ends_training_session_id_foreign` FOREIGN KEY (`training_session_id`) REFERENCES `training_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.training_sessions
CREATE TABLE IF NOT EXISTS `training_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `loadout_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(120) DEFAULT NULL,
  `session_at` datetime DEFAULT NULL,
  `location` varchar(120) DEFAULT NULL,
  `distance_m` smallint(5) unsigned DEFAULT NULL,
  `round_type` varchar(40) DEFAULT NULL,
  `arrows_per_end` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `max_score` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `x_value` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `ends_planned` smallint(5) unsigned DEFAULT NULL,
  `ends_completed` smallint(5) unsigned NOT NULL DEFAULT 0,
  `total_score` smallint(5) unsigned NOT NULL DEFAULT 0,
  `x_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `duration_minutes` smallint(5) unsigned DEFAULT NULL,
  `rpe` tinyint(3) unsigned DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `weather` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`weather`)),
  `notes` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_sessions_loadout_id_foreign` (`loadout_id`),
  KEY `training_sessions_user_id_session_at_index` (`user_id`,`session_at`),
  CONSTRAINT `training_sessions_loadout_id_foreign` FOREIGN KEY (`loadout_id`) REFERENCES `loadouts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `training_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table archer_db.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(32) NOT NULL DEFAULT 'standard',
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `stripe_subscription_id` varchar(255) DEFAULT NULL,
  `is_pro` tinyint(1) NOT NULL DEFAULT 0,
  `pro_expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_is_active_index` (`role`,`is_active`),
  KEY `users_stripe_customer_id_index` (`stripe_customer_id`),
  KEY `users_stripe_subscription_id_index` (`stripe_subscription_id`),
  KEY `users_company_id_role_index` (`company_id`,`role`),
  CONSTRAINT `users_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
