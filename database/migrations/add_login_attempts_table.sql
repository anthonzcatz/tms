-- Migration: IP-based rate limit table for login brute-force protection
-- Replaces session-based rate limiting which attackers can bypass by clearing cookies

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `attempt_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address`   VARCHAR(45)     NOT NULL,
    `attempt_key`  VARCHAR(100)    NOT NULL,
    `attempted_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`attempt_id`),
    INDEX `idx_ip_key_time` (`ip_address`, `attempt_key`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
