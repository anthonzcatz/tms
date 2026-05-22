-- Migration: add_time_restriction_logs
-- Tracks every login attempt that was denied due to time restrictions.
-- Run once against the TMS database.

CREATE TABLE IF NOT EXISTS `time_restriction_logs` (
    `log_id`        BIGINT(20)      NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT(20)      NOT NULL,
    `attempted_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address`    VARCHAR(45)     NOT NULL DEFAULT '',
    `denial_reason` VARCHAR(255)    NOT NULL DEFAULT '',
    PRIMARY KEY (`log_id`),
    KEY `idx_trl_user_id`      (`user_id`),
    KEY `idx_trl_attempted_at` (`attempted_at`),
    CONSTRAINT `fk_trl_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `user_accounts` (`user_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit log: login attempts blocked by time restrictions';
