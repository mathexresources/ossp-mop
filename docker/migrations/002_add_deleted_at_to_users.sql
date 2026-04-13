-- =============================================================
--  Migration 002 — Soft-delete support for users
--  MariaDB 11 (ADD COLUMN IF NOT EXISTS is supported)
-- =============================================================

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME DEFAULT NULL AFTER `created_at`;

-- Index so "WHERE deleted_at IS NULL" stays fast on large tables.
ALTER TABLE `users`
    ADD INDEX IF NOT EXISTS `idx_users_deleted_at` (`deleted_at`);
