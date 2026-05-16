-- Migration: Add email_settings table
-- Description: Store email configuration for password reset and system notifications

CREATE TABLE IF NOT EXISTS `email_settings` (
  `setting_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `email_method` enum('smtp','gmail_api') DEFAULT 'smtp',
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT 587,
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_password` text DEFAULT NULL,
  `smtp_encryption` enum('tls','ssl','none') DEFAULT 'tls',
  `sender_name` varchar(255) DEFAULT NULL,
  `sender_email` varchar(255) DEFAULT NULL,
  `gmail_client_id` varchar(255) DEFAULT NULL,
  `gmail_client_secret` text DEFAULT NULL,
  `gmail_refresh_token` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO `email_settings` (`setting_id`, `email_method`, `smtp_host`, `smtp_port`, `smtp_encryption`, `sender_name`, `sender_email`)
VALUES (1, 'smtp', 'smtp.gmail.com', 587, 'tls', 'Ticketing Services Inc.', 'noreply@ticketingservices.com')
ON DUPLICATE KEY UPDATE `setting_id`=`setting_id`;
