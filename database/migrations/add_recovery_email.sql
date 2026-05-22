-- Add recovery email field to user_accounts
-- This allows users to set a separate recovery email for password reset
-- which can be verified for security

ALTER TABLE `user_accounts`
ADD COLUMN `recovery_email` VARCHAR(255) DEFAULT NULL AFTER `email`,
ADD COLUMN `recovery_email_verified_at` TIMESTAMP NULL DEFAULT NULL AFTER `recovery_email`,
ADD COLUMN `recovery_email_verification_token` VARCHAR(255) DEFAULT NULL AFTER `recovery_email_verified_at`,
ADD COLUMN `recovery_email_verification_expires_at` TIMESTAMP NULL DEFAULT NULL AFTER `recovery_email_verification_token`,
ADD INDEX `idx_recovery_email` (`recovery_email`);

-- Add verification tokens table for email verification
CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
  `token_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `token_type` ENUM('recovery_email', 'email_change') NOT NULL DEFAULT 'recovery_email',
  `expires_at` TIMESTAMP NOT NULL,
  `used_at` TIMESTAMP NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `user_accounts`(`user_id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_token` (`token`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
