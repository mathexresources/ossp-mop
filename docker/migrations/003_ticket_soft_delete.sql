-- =============================================================
--  Migration 003 — Soft-delete support for tickets + images
--  MariaDB 11 (ADD COLUMN IF NOT EXISTS is supported)
-- =============================================================

-- tickets ----------------------------------------------------
ALTER TABLE `tickets`
    ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME DEFAULT NULL AFTER `updated_at`;

ALTER TABLE `tickets`
    ADD INDEX IF NOT EXISTS `idx_tickets_deleted_at` (`deleted_at`);

-- ticket_images ----------------------------------------------
ALTER TABLE `ticket_images`
    ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME DEFAULT NULL AFTER `path`;

ALTER TABLE `ticket_images`
    ADD INDEX IF NOT EXISTS `idx_ticket_images_deleted_at` (`deleted_at`);

-- ticket_damage_points ---------------------------------------
ALTER TABLE `ticket_damage_points`
    ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME DEFAULT NULL AFTER `description`;

ALTER TABLE `ticket_damage_points`
    ADD INDEX IF NOT EXISTS `idx_tdp_deleted_at` (`deleted_at`);
