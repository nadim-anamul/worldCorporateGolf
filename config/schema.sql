-- Database Schema for Multi-Tournament Registration & Payment System
-- 
-- Consolidates Tournaments, Golfer & Non-Golfer registrations, Tee times,
-- Arrival window settings, and App configurations.

-- 1. Tournaments Configuration table
CREATE TABLE IF NOT EXISTS `tournaments` (
    `id`                  INT           AUTO_INCREMENT PRIMARY KEY,
    `name`                VARCHAR(255)  NOT NULL,
    `date`                VARCHAR(100)  NOT NULL,
    `venue`               VARCHAR(255)  NOT NULL,
    `format`              VARCHAR(255)  NOT NULL,
    `logo_path`           VARCHAR(255)  DEFAULT NULL,
    `hero_background_path` VARCHAR(255)  DEFAULT NULL,
    `fee`                 DECIMAL(10,2) NOT NULL DEFAULT 2000.00,
    `early_bird_fee`      DECIMAL(10,2) DEFAULT NULL,
    `currency`            VARCHAR(10)   NOT NULL DEFAULT 'BDT',
    `deadline`            DATETIME      NOT NULL,
    `early_bird_deadline` DATETIME      DEFAULT NULL,
    `contact_phone_1`     VARCHAR(50)   DEFAULT NULL,
    `contact_phone_2`     VARCHAR(50)   DEFAULT NULL,
    `is_active`           TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tournaments_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Golfer registrations table
CREATE TABLE IF NOT EXISTS `registrations` (
    `id`              INT           AUTO_INCREMENT PRIMARY KEY,
    `tournament_id`   INT           NOT NULL DEFAULT 1,
    `unique_id`       VARCHAR(64)   NOT NULL,
    `tran_id`         VARCHAR(64)   NOT NULL,
    
    -- Participant details
    `full_name`       VARCHAR(255)  NOT NULL,
    `designation`     VARCHAR(255)  DEFAULT NULL,
    `organization`    VARCHAR(255)  DEFAULT NULL,
    `nationality`     VARCHAR(100)  DEFAULT NULL,
    `gender`          VARCHAR(20)   DEFAULT NULL,
    `profile_photo`   VARCHAR(255)  DEFAULT NULL,
    `name_on_polo`    VARCHAR(255)  DEFAULT NULL,
    `golf_set_brand`  VARCHAR(100)  DEFAULT NULL,
    `contact`         VARCHAR(50)   DEFAULT NULL,
    `email`           VARCHAR(255)  NOT NULL,
    `mailing_address` TEXT          DEFAULT NULL,
    `handicap`        VARCHAR(20)   DEFAULT NULL,
    `tshirt_size`     VARCHAR(50)   DEFAULT NULL,
    `home_club`       VARCHAR(255)  DEFAULT NULL,
    `schedule_group`  VARCHAR(64)   NOT NULL, -- references tee_time_options.id
    `player_category` VARCHAR(20)  NOT NULL DEFAULT 'N/A',
    
    -- Sponsor/Reference details
    `reference_name`    VARCHAR(255)  DEFAULT NULL,
    `reference_mission` VARCHAR(255)  DEFAULT NULL,
    `reference_contact` VARCHAR(50)   DEFAULT NULL,
    
    -- Payment tracking (SSLCommerz)
    `payment_status`  ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
    `ssl_session_key` VARCHAR(255)  DEFAULT NULL,
    `val_id`          VARCHAR(255)  DEFAULT NULL,
    `amount`          DECIMAL(10,2) NOT NULL DEFAULT 2000.00,
    `currency`        VARCHAR(10)   NOT NULL DEFAULT 'BDT',
    
    -- Timestamps
    `submitted_at`    DATETIME      NOT NULL,
    `paid_at`         DATETIME      DEFAULT NULL,
    `sms_sent_at`     DATETIME      DEFAULT NULL,
    `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `uq_unique_id`       (`unique_id`),
    UNIQUE KEY `uq_tran_id`         (`tran_id`),
    INDEX      `idx_tournament`     (`tournament_id`),
    INDEX      `idx_email`          (`email`),
    INDEX      `idx_payment_status` (`payment_status`),
    INDEX      `idx_reg_tournament_status` (`tournament_id`, `payment_status`),
    INDEX      `idx_reg_slot_capacity` (`schedule_group`, `tournament_id`, `payment_status`),
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Non-Golfer registrations table
CREATE TABLE IF NOT EXISTS `registrations_non_golfer` (
    `id`                       INT           AUTO_INCREMENT PRIMARY KEY,
    `tournament_id`            INT           NOT NULL DEFAULT 1,
    `unique_id`                VARCHAR(64)   NOT NULL,
    `tran_id`                  VARCHAR(64)   NOT NULL,
    
    -- Participant details
    `full_name`                VARCHAR(255)  NOT NULL,
    `designation`              VARCHAR(255)  DEFAULT NULL,
    `organization`             VARCHAR(255)  DEFAULT NULL,
    `nationality`              VARCHAR(100)  DEFAULT NULL,
    `gender`                   VARCHAR(20)   DEFAULT NULL,
    `profile_photo`            VARCHAR(255)  DEFAULT NULL,
    `name_on_polo`             VARCHAR(255)  DEFAULT NULL,
    `contact`                  VARCHAR(50)   DEFAULT NULL,
    `email`                    VARCHAR(255)  NOT NULL,
    `mailing_address`          TEXT          DEFAULT NULL,
    `tshirt_size`              VARCHAR(50)   DEFAULT NULL,
    `arrival_window`           VARCHAR(64)   NOT NULL, -- references arrival_window_options_non_golfer.id
    `putting_contest_interest` ENUM('Yes','No') NOT NULL,
    `player_category`          VARCHAR(20)   NOT NULL DEFAULT 'N/A',
    
    -- Sponsor/Reference details
    `reference_name`    VARCHAR(255)  DEFAULT NULL,
    `reference_mission` VARCHAR(255)  DEFAULT NULL,
    `reference_contact` VARCHAR(50)   DEFAULT NULL,
    
    -- Payment tracking (SSLCommerz)
    `payment_status`  ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
    `ssl_session_key` VARCHAR(255)  DEFAULT NULL,
    `val_id`          VARCHAR(255)  DEFAULT NULL,
    `amount`          DECIMAL(10,2) NOT NULL DEFAULT 2000.00,
    `currency`        VARCHAR(10)   NOT NULL DEFAULT 'BDT',
    
    -- Timestamps
    `submitted_at`    DATETIME      NOT NULL,
    `paid_at`         DATETIME      DEFAULT NULL,
    `sms_sent_at`     DATETIME      DEFAULT NULL,
    `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `uq_ng_unique_id`      (`unique_id`),
    UNIQUE KEY `uq_ng_tran_id`        (`tran_id`),
    INDEX      `idx_ng_tournament`    (`tournament_id`),
    INDEX      `idx_ng_email`         (`email`),
    INDEX      `idx_ng_payment_status`(`payment_status`),
    INDEX      `idx_ng_tournament_status` (`tournament_id`, `payment_status`),
    INDEX      `idx_ng_window_capacity` (`arrival_window`, `tournament_id`, `payment_status`),
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tee time options table (Golfers)
CREATE TABLE IF NOT EXISTS `tee_time_options` (
    `id`               INT           AUTO_INCREMENT PRIMARY KEY,
    `tournament_id`    INT           NOT NULL DEFAULT 1,
    `title`            VARCHAR(100)  NOT NULL,
    `reporting_time`   VARCHAR(50)   NOT NULL,
    `group_photo_time` VARCHAR(50)   NOT NULL,
    `tee_off_time`     VARCHAR(50)   NOT NULL,
    `slot_number`      INT           NOT NULL DEFAULT 36,
    `display_order`    INT           NOT NULL DEFAULT 0,
    `is_active`        TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tee_tournament` (`tournament_id`),
    INDEX `idx_tee_active_order` (`is_active`, `display_order`),
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Arrival window options table (Non-Golfers)
CREATE TABLE IF NOT EXISTS `arrival_window_options_non_golfer` (
    `id`               VARCHAR(32)   NOT NULL PRIMARY KEY,
    `tournament_id`    INT           NOT NULL DEFAULT 1,
    `title`            VARCHAR(100)  NOT NULL,
    `window_time`      VARCHAR(120)  NOT NULL,
    `group_photo_time` VARCHAR(50)   NOT NULL,
    `slot_number`      INT           NOT NULL DEFAULT 30,
    `display_order`    INT           NOT NULL DEFAULT 0,
    `is_active`        TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_arrival_tournament` (`tournament_id`),
    INDEX `idx_arrival_active_order` (`is_active`, `display_order`),
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. General Application Settings table
CREATE TABLE IF NOT EXISTS `app_settings` (
    `setting_key`   VARCHAR(100) NOT NULL PRIMARY KEY,
    `setting_value` TEXT         NOT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Seed Initial Settings, Tournaments & Options
-- --------------------------------------------------------------------------

-- Default capacity
INSERT INTO `app_settings` (`setting_key`, `setting_value`) 
VALUES ('max_slots', '72')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- First Tournament (Active by default)
INSERT INTO `tournaments` (`id`, `name`, `date`, `venue`, `format`, `fee`, `currency`, `deadline`, `contact_phone_1`, `contact_phone_2`, `is_active`)
VALUES (1, '2nd GolfHouse Diplomatic Cup 2026', 'Saturday, 02 May 2026', 'Jolshiri Golf Club, Dhaka', 'Best Ball Scramble (Shotgun Start)', 2000.00, 'BDT', '2026-04-29 23:59:59', '01610 801 081', '01842 324 232', 1)
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `date` = VALUES(`date`),
    `venue` = VALUES(`venue`),
    `format` = VALUES(`format`),
    `fee` = VALUES(`fee`),
    `currency` = VALUES(`currency`),
    `deadline` = VALUES(`deadline`),
    `contact_phone_1` = VALUES(`contact_phone_1`),
    `contact_phone_2` = VALUES(`contact_phone_2`),
    `is_active` = VALUES(`is_active`);

-- Default Tee Time Groups for Tournament ID 1
INSERT INTO `tee_time_options` (`id`, `tournament_id`, `title`, `reporting_time`, `group_photo_time`, `tee_off_time`, `slot_number`, `display_order`, `is_active`)
VALUES 
    (1, 1, 'Shotgun-1 (Early)', '07:00 AM', '07:15 AM', '07:30 AM', 36, 20, 1),
    (2, 1, 'Shotgun-2 (Late)', '09:30 AM', '09:45 AM', '10:00 AM', 36, 10, 1)
ON DUPLICATE KEY UPDATE 
    `title` = VALUES(`title`), 
    `reporting_time` = VALUES(`reporting_time`),
    `group_photo_time` = VALUES(`group_photo_time`),
    `tee_off_time` = VALUES(`tee_off_time`),
    `slot_number` = VALUES(`slot_number`),
    `display_order` = VALUES(`display_order`),
    `is_active` = VALUES(`is_active`);

-- Default Non-Golfer Windows for Tournament ID 1
INSERT INTO `arrival_window_options_non_golfer` (`id`, `tournament_id`, `title`, `window_time`, `group_photo_time`, `slot_number`, `display_order`, `is_active`)
VALUES
    ('window1', 1, 'Window-1', '8:00 AM - 10:30 AM', '09:45 AM', 30, 20, 1),
    ('window2', 1, 'Window-2', '10:00 AM - 12:00 PM', '09:45 AM', 30, 10, 1)
ON DUPLICATE KEY UPDATE
    `title` = VALUES(`title`),
    `window_time` = VALUES(`window_time`),
    `group_photo_time` = VALUES(`group_photo_time`),
    `slot_number` = VALUES(`slot_number`),
    `display_order` = VALUES(`display_order`),
    `is_active` = VALUES(`is_active`);
