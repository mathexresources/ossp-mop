-- =============================================================
--  Migration 001 — add status column to users table
--  Run this against an EXISTING database (sessions 1 & 2 schema).
--  For fresh installs the column is already present in init.sql.
-- =============================================================

ALTER TABLE `users`
    ADD COLUMN `status` ENUM('pending', 'approved', 'rejected')
        NOT NULL DEFAULT 'approved'
        AFTER `role`;

-- Pre-existing seed users are considered approved.
-- New registrations default to 'pending' (set explicitly in application code).
UPDATE `users` SET `status` = 'approved';
