-- =============================================================
--  Migration 005 — add link_url to notifications
--  Run once against a running database:
--    docker compose exec database mariadb -u app -papp app \
--      < docker/migrations/005_notifications_link_url.sql
-- =============================================================

ALTER TABLE `notifications`
    ADD COLUMN `link_url` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Optional URL to navigate to when the notification is clicked'
        AFTER `message`;
