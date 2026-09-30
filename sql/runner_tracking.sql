-- Version: 1.0
-- Runner Live Location & Travel History — MySQL / MariaDB DDL
-- Charset: utf8mb4 | Engine: InnoDB
-- Safe to re-run: uses IF NOT EXISTS / conditional patterns where practical.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------------
-- 1) System settings (key/value) — master Google share account for logistics
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key`   VARCHAR(64)  NOT NULL,
  `setting_value` TEXT         NULL,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by`    VARCHAR(64)  NULL DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_by`)
VALUES ('master_tracking_email', 'admin.logistics@company.com', 'schema')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- ---------------------------------------------------------------------------
-- 2) Users / team members — tracking columns
--    If your identity table is named differently, rename `users` accordingly.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`                  VARCHAR(64)  NULL,
  `name`                  VARCHAR(160) NOT NULL,
  `email`                 VARCHAR(190) NULL,
  `phone`                 VARCHAR(32)  NULL,
  `designation`           VARCHAR(120) NULL,
  `designation_id`        VARCHAR(64)  NULL,
  `department`            VARCHAR(120) NULL,
  `is_active`             TINYINT(1)   NOT NULL DEFAULT 1,
  `is_tracking_enabled`   TINYINT(1)   NOT NULL DEFAULT 0,
  `master_share_verified` TINYINT(1)   NOT NULL DEFAULT 0,
  `last_known_lat`        DECIMAL(10,8) NULL DEFAULT NULL,
  `last_known_lng`        DECIMAL(11,8) NULL DEFAULT NULL,
  `last_location_update`  DATETIME     NULL DEFAULT NULL,
  `last_speed_kmh`        DECIMAL(6,2) NULL DEFAULT NULL,
  `last_battery_pct`      TINYINT UNSIGNED NULL DEFAULT NULL,
  `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_slug` (`slug`),
  KEY `idx_users_designation` (`designation`),
  KEY `idx_users_tracking` (`is_tracking_enabled`, `last_location_update`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotent column adds for existing `users` tables (MySQL 8.0.29+ / MariaDB 10.5.2+)
-- If your server rejects IF NOT EXISTS on ALTER, run each ADD COLUMN once manually.

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `is_tracking_enabled`   TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`,
  ADD COLUMN IF NOT EXISTS `master_share_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_tracking_enabled`,
  ADD COLUMN IF NOT EXISTS `last_known_lat`        DECIMAL(10,8) NULL DEFAULT NULL AFTER `master_share_verified`,
  ADD COLUMN IF NOT EXISTS `last_known_lng`        DECIMAL(11,8) NULL DEFAULT NULL AFTER `last_known_lat`,
  ADD COLUMN IF NOT EXISTS `last_location_update`  DATETIME NULL DEFAULT NULL AFTER `last_known_lng`,
  ADD COLUMN IF NOT EXISTS `last_speed_kmh`        DECIMAL(6,2) NULL DEFAULT NULL AFTER `last_location_update`,
  ADD COLUMN IF NOT EXISTS `last_battery_pct`      TINYINT UNSIGNED NULL DEFAULT NULL AFTER `last_speed_kmh`;

-- ---------------------------------------------------------------------------
-- 3) GPS breadcrumbs
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `runner_location_logs` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `runner_id`          BIGINT UNSIGNED NOT NULL,
  `latitude`           DECIMAL(10,8)   NOT NULL,
  `longitude`          DECIMAL(11,8)   NOT NULL,
  `speed_kmh`          DECIMAL(6,2)    NULL DEFAULT NULL,
  `battery_percentage` TINYINT UNSIGNED NULL DEFAULT NULL,
  `accuracy_m`         DECIMAL(8,2)    NULL DEFAULT NULL,
  `heading_deg`        DECIMAL(5,2)    NULL DEFAULT NULL,
  `recorded_at`        DATETIME        NOT NULL,
  `received_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_runner_time` (`runner_id`, `recorded_at`),
  KEY `idx_recorded_at` (`recorded_at`),
  CONSTRAINT `fk_runner_logs_user`
    FOREIGN KEY (`runner_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4) Daily aggregates for audit / export
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `runner_daily_summaries` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `runner_id`           BIGINT UNSIGNED NOT NULL,
  `summary_date`        DATE            NOT NULL,
  `total_distance_km`   DECIMAL(10,3)   NOT NULL DEFAULT 0.000,
  `total_active_seconds` INT UNSIGNED   NOT NULL DEFAULT 0,
  `total_idle_seconds`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `start_time`          DATETIME        NULL DEFAULT NULL,
  `end_time`            DATETIME        NULL DEFAULT NULL,
  `point_count`         INT UNSIGNED    NOT NULL DEFAULT 0,
  `route_polyline`      MEDIUMTEXT      NULL,
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_runner_day` (`runner_id`, `summary_date`),
  KEY `idx_summary_date` (`summary_date`),
  CONSTRAINT `fk_runner_summary_user`
    FOREIGN KEY (`runner_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 5) Optional seed: mark designation Office Runner for testing
-- ---------------------------------------------------------------------------
-- UPDATE users SET is_tracking_enabled = 1
-- WHERE LOWER(TRIM(designation)) = 'office runner';
