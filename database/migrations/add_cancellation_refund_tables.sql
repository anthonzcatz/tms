-- Migration: Add cancellation and refund tracking tables
-- Date: 2026-05-15

-- Table for tracking ticket cancellations
CREATE TABLE IF NOT EXISTS `ticket_cancellations` (
  `cancellation_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint(20) NOT NULL,
  `transaction_code` varchar(50) NOT NULL,
  `passenger_id` bigint(20) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `cancellation_type` enum('full','partial') DEFAULT 'full',
  `refund_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `requested_by` bigint(20) NOT NULL,
  `cashier_session_id` bigint(20) DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` bigint(20) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `wallet_impact_applied` tinyint(1) DEFAULT 0 COMMENT 'Whether the wallet balance has been adjusted',
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`cancellation_id`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_status` (`status`),
  KEY `idx_requested_by` (`requested_by`),
  KEY `idx_cashier_session_id` (`cashier_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for tracking ticket refunds
CREATE TABLE IF NOT EXISTS `ticket_refunds` (
  `refund_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint(20) NOT NULL,
  `transaction_code` varchar(50) NOT NULL,
  `cancellation_id` bigint(20) DEFAULT NULL,
  `passenger_id` bigint(20) DEFAULT NULL,
  `refund_amount` decimal(12,2) NOT NULL,
  `refund_method` varchar(50) DEFAULT NULL COMMENT 'cash, bank_transfer, wallet, etc.',
  `refund_reference` varchar(100) DEFAULT NULL COMMENT 'Reference number for the refund',
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `requested_by` bigint(20) NOT NULL,
  `cashier_session_id` bigint(20) DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_by` bigint(20) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `wallet_impact_applied` tinyint(1) DEFAULT 0 COMMENT 'Whether the wallet balance has been adjusted',
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`refund_id`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_cancellation_id` (`cancellation_id`),
  KEY `idx_status` (`status`),
  KEY `idx_requested_by` (`requested_by`),
  KEY `idx_cashier_session_id` (`cashier_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
