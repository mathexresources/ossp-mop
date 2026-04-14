-- =============================================================
--  OSSP MOP — database initialisation script
--  Charset: utf8mb4 / utf8mb4_unicode_ci
--  Engine:  InnoDB
-- =============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- -------------------------------------------------------------
--  locations
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `locations` (
    `id`   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120)    NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_locations_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  item_types
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `item_types` (
    `id`   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(80)     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_item_types_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  users
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `first_name`    VARCHAR(80)     NOT NULL,
    `last_name`     VARCHAR(80)     NOT NULL,
    `email`         VARCHAR(180)    NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL,
    `phone`         VARCHAR(30)             DEFAULT NULL,
    `birth_date`    DATE                    DEFAULT NULL,
    `street`        VARCHAR(180)            DEFAULT NULL,
    `city`          VARCHAR(100)            DEFAULT NULL,
    `role`          ENUM('guest','admin','employee','support') NOT NULL DEFAULT 'guest',
    `status`        ENUM('pending','approved','rejected')      NOT NULL DEFAULT 'pending',
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  items
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `items` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(120)    NOT NULL,
    `item_type_id` INT UNSIGNED    NOT NULL,
    `location_id`  INT UNSIGNED    NOT NULL,
    `description`  TEXT                    DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_items_item_type` (`item_type_id`),
    KEY `idx_items_location`  (`location_id`),
    CONSTRAINT `fk_items_item_type` FOREIGN KEY (`item_type_id`) REFERENCES `item_types` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_items_location`  FOREIGN KEY (`location_id`)  REFERENCES `locations`  (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  service_history
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_history` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `item_id`     INT UNSIGNED    NOT NULL,
    `description` TEXT            NOT NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by`  INT UNSIGNED    NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_service_history_item`       (`item_id`),
    KEY `idx_service_history_created_by` (`created_by`),
    CONSTRAINT `fk_service_history_item`       FOREIGN KEY (`item_id`)    REFERENCES `items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_service_history_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  tickets
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tickets` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(200)    NOT NULL,
    `description` TEXT            NOT NULL,
    `item_id`     INT UNSIGNED    NOT NULL,
    `created_by`  INT UNSIGNED    NOT NULL,
    `assigned_to` INT UNSIGNED            DEFAULT NULL,
    `status`      ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME                DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_tickets_item`        (`item_id`),
    KEY `idx_tickets_created_by`  (`created_by`),
    KEY `idx_tickets_assigned_to` (`assigned_to`),
    KEY `idx_tickets_status`      (`status`),
    CONSTRAINT `fk_tickets_item`        FOREIGN KEY (`item_id`)     REFERENCES `items` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_tickets_created_by`  FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_tickets_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  ticket_damage_points
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ticket_damage_points` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `ticket_id`   INT UNSIGNED    NOT NULL,
    `position_x`  DECIMAL(8,4)    NOT NULL,
    `position_y`  DECIMAL(8,4)    NOT NULL,
    `description` VARCHAR(500)            DEFAULT NULL,
    `deleted_at`  DATETIME                DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_tdp_ticket` (`ticket_id`),
    CONSTRAINT `fk_tdp_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  ticket_images
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ticket_images` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `ticket_id`  INT UNSIGNED    NOT NULL,
    `path`       VARCHAR(500)    NOT NULL,
    `deleted_at` DATETIME                DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ticket_images_ticket` (`ticket_id`),
    CONSTRAINT `fk_ticket_images_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  notifications
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED    NOT NULL,
    `type`       VARCHAR(60)     NOT NULL,
    `message`    TEXT            NOT NULL,
    `link_url`   VARCHAR(255)    NULL DEFAULT NULL,
    `is_read`    TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user`    (`user_id`),
    KEY `idx_notifications_is_read` (`is_read`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- =============================================================
--  SEED DATA
-- =============================================================

-- -------------------------------------------------------------
--  Locations
-- -------------------------------------------------------------
INSERT INTO `locations` (`name`) VALUES
    ('Building A – Ground Floor'),
    ('Building A – 1st Floor'),
    ('Building B – Server Room'),
    ('Building B – Meeting Room 1'),
    ('Warehouse');

-- -------------------------------------------------------------
--  Item types
-- -------------------------------------------------------------
INSERT INTO `item_types` (`name`) VALUES
    ('Laptop'),
    ('Desktop PC'),
    ('Printer'),
    ('Network Switch'),
    ('Projector'),
    ('UPS'),
    ('Monitor');

-- -------------------------------------------------------------
--  Users
--  Passwords (all hashed with PASSWORD_BCRYPT, cost 10):
--    admin@example.com  → Admin123!
--    jan.novak@example.com  → Employee1!
--    petra.kova@example.com → Employee2!
--    support@example.com    → Support1!
-- -------------------------------------------------------------
INSERT INTO `users`
    (`first_name`, `last_name`, `email`, `password_hash`, `phone`, `birth_date`, `street`, `city`, `role`, `status`, `created_at`)
VALUES
    (
        'Adam', 'Admin',
        'admin@example.com',
        '$2y$10$qEi09KIKhXVGWvD6smdm/OL/7EUYOvOwO1hoYo4t8PcGyqjaSHSPe',
        '+420 601 000 001',
        '1985-03-15',
        'Náměstí Míru 1',
        'Praha',
        'admin', 'approved',
        '2024-01-01 08:00:00'
    ),
    (
        'Jan', 'Novák',
        'jan.novak@example.com',
        '$2y$10$L1OAFlj631OEyWpbZJFIQuu/4tyeS.TN2aCWgVd1pFPiTvKxnxdOu',
        '+420 602 000 002',
        '1990-07-22',
        'Dlouhá 12',
        'Brno',
        'employee', 'approved',
        '2024-01-15 09:00:00'
    ),
    (
        'Petra', 'Kovářová',
        'petra.kovarkova@example.com',
        '$2y$10$XgBmk6V5mjIn597pxB3INuBkiEoZx4eC2T/6bOG1JzcLPWgx9FoiW',
        '+420 603 000 003',
        '1993-11-08',
        'Krátká 5',
        'Ostrava',
        'employee', 'approved',
        '2024-02-01 09:00:00'
    ),
    (
        'Simona', 'Supportová',
        'support@example.com',
        '$2y$10$TNrR.zEbS1KR6XT8W8HLku92sGpzkzVGkBqDye3BOFvOGxHbFTRnq',
        '+420 604 000 004',
        '1995-05-30',
        'Podpůrná 7',
        'Plzeň',
        'support', 'approved',
        '2024-02-10 09:00:00'
    );

-- -------------------------------------------------------------
--  Items
-- -------------------------------------------------------------
INSERT INTO `items` (`name`, `item_type_id`, `location_id`, `description`) VALUES
    ('ThinkPad X1 Carbon #001', 1, 1, 'Intel Core i7-1165G7, 16 GB RAM, 512 GB SSD'),
    ('ThinkPad X1 Carbon #002', 1, 2, 'Intel Core i5-1135G7, 16 GB RAM, 256 GB SSD'),
    ('Dell OptiPlex 7090 #001', 2, 1, 'Intel Core i5-10500, 8 GB RAM, 256 GB SSD'),
    ('HP LaserJet Pro M404dn',  3, 2, 'Monochrome A4 laser printer, duplex, network'),
    ('Cisco SG350-28',          4, 3, '28-port Gigabit Managed Switch'),
    ('Epson EB-X41 Projector',  5, 4, '3600 lumens, XGA, HDMI'),
    ('APC Smart-UPS 1500VA',    6, 3, '1500 VA / 1000 W, Tower form factor'),
    ('Dell UltraSharp 27"',     7, 1, '4K IPS, USB-C, DisplayPort');

-- -------------------------------------------------------------
--  Service history
-- -------------------------------------------------------------
INSERT INTO `service_history` (`item_id`, `description`, `created_by`, `created_at`) VALUES
    (1, 'Initial setup, joined to domain, installed standard software suite.', 2, '2024-01-20 10:00:00'),
    (1, 'RAM upgraded from 8 GB to 16 GB.', 2, '2024-03-05 14:30:00'),
    (3, 'Cleaned internally, reapplied thermal paste, replaced CMOS battery.', 3, '2024-04-10 11:00:00'),
    (4, 'Replaced toner cartridge (page count: 12 450).', 4, '2024-05-01 09:15:00'),
    (5, 'Firmware updated to 2.5.5.68.', 2, '2024-05-15 16:00:00');

-- -------------------------------------------------------------
--  Tickets
-- -------------------------------------------------------------
INSERT INTO `tickets`
    (`title`, `description`, `item_id`, `created_by`, `assigned_to`, `status`, `created_at`, `updated_at`)
VALUES
    (
        'Laptop screen flickering',
        'Screen flickers intermittently when on battery power. Reproducible by unplugging AC adapter.',
        1, 4, 2, 'in_progress',
        '2024-06-01 08:30:00', '2024-06-02 09:00:00'
    ),
    (
        'Printer paper jam – repeated',
        'Printer gets jammed after every 20–30 pages. Already cleared manually three times this week.',
        4, 4, 3, 'open',
        '2024-06-05 11:00:00', '2024-06-05 11:00:00'
    ),
    (
        'Projector no signal on HDMI',
        'Projector shows "No signal" when connected via HDMI from laptop. VGA adapter works fine.',
        6, 2, NULL, 'open',
        '2024-06-10 13:45:00', '2024-06-10 13:45:00'
    ),
    (
        'Switch port 14 not responding',
        'Port 14 on the Cisco switch shows as down in the management console. Cable and NIC tested OK.',
        5, 3, 2, 'closed',
        '2024-05-20 09:00:00', '2024-05-22 15:30:00'
    );

-- -------------------------------------------------------------
--  Ticket damage points
-- -------------------------------------------------------------
INSERT INTO `ticket_damage_points` (`ticket_id`, `position_x`, `position_y`, `description`) VALUES
    (1, 50.0000, 12.5000, 'Top-left corner of the screen – visible flickering origin'),
    (1, 48.5000, 50.0000, 'Centre band – horizontal line appears here');

-- -------------------------------------------------------------
--  Ticket images
-- -------------------------------------------------------------
INSERT INTO `ticket_images` (`ticket_id`, `path`) VALUES
    (1, 'uploads/tickets/1/flickering_video_frame.jpg'),
    (2, 'uploads/tickets/2/jammed_paper.jpg');

-- -------------------------------------------------------------
--  Notifications
-- -------------------------------------------------------------
INSERT INTO `notifications` (`user_id`, `type`, `message`, `is_read`, `created_at`) VALUES
    (2, 'ticket_assigned',  'Ticket #1 "Laptop screen flickering" has been assigned to you.',        0, '2024-06-02 09:00:00'),
    (3, 'ticket_assigned',  'Ticket #2 "Printer paper jam – repeated" has been assigned to you.',    0, '2024-06-05 11:05:00'),
    (4, 'ticket_updated',   'Ticket #1 status changed to In Progress.',                              1, '2024-06-02 09:01:00'),
    (2, 'ticket_resolved',  'Ticket #4 "Switch port 14 not responding" has been closed.',            1, '2024-05-22 15:31:00'),
    (1, 'new_ticket',       'New ticket #3 "Projector no signal on HDMI" submitted by Jan Novák.',   0, '2024-06-10 13:46:00');
