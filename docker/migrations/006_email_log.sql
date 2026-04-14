-- Migration 006: email_log table
-- Stores every outgoing email attempt for admin visibility and debugging.

CREATE TABLE IF NOT EXISTS email_log (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient     VARCHAR(255) NOT NULL,
    subject       VARCHAR(500) NOT NULL,
    type          VARCHAR(80)  NOT NULL,
    status        ENUM('sent','failed') NOT NULL DEFAULT 'sent',
    error_message TEXT         NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_email_log_recipient (recipient),
    INDEX idx_email_log_type     (type),
    INDEX idx_email_log_status   (status),
    INDEX idx_email_log_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
