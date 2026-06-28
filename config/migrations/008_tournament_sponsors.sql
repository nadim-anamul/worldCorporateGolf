CREATE TABLE IF NOT EXISTS `tournament_sponsors` (
    `id`             INT           AUTO_INCREMENT PRIMARY KEY,
    `tournament_id`  INT           NOT NULL,
    `name`           VARCHAR(255)  NOT NULL,
    `website_url`    VARCHAR(512)  NOT NULL,
    `logo_path`      VARCHAR(255)  NOT NULL,
    `display_order`  INT           NOT NULL DEFAULT 0,
    `is_active`      TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_sponsor_tournament` (`tournament_id`, `is_active`, `display_order`),
    FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tournament_sponsors` (`tournament_id`, `name`, `website_url`, `logo_path`, `display_order`, `is_active`)
SELECT 1, 'GolfHouse', 'https://golfhouse.com.bd', 'assets/images/golfhouse-logo.png', 30, 1
WHERE EXISTS (SELECT 1 FROM `tournaments` WHERE `id` = 1)
  AND NOT EXISTS (SELECT 1 FROM `tournament_sponsors` WHERE `tournament_id` = 1 AND `name` = 'GolfHouse');

INSERT INTO `tournament_sponsors` (`tournament_id`, `name`, `website_url`, `logo_path`, `display_order`, `is_active`)
SELECT 1, 'Corporate Tour', 'https://worldcorporategolftour.com', 'assets/images/corporate-tour-logo.png', 20, 1
WHERE EXISTS (SELECT 1 FROM `tournaments` WHERE `id` = 1)
  AND NOT EXISTS (SELECT 1 FROM `tournament_sponsors` WHERE `tournament_id` = 1 AND `name` = 'Corporate Tour');

INSERT INTO `tournament_sponsors` (`tournament_id`, `name`, `website_url`, `logo_path`, `display_order`, `is_active`)
SELECT 1, 'Jolshiri Golf Club', 'https://jolshirigolfclub.com', 'assets/images/jolshiri-golf-club-logo.png', 10, 1
WHERE EXISTS (SELECT 1 FROM `tournaments` WHERE `id` = 1)
  AND NOT EXISTS (SELECT 1 FROM `tournament_sponsors` WHERE `tournament_id` = 1 AND `name` = 'Jolshiri Golf Club');
