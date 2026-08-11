-- Migration: Add service cancellation and refund tracking tables
-- Date: 2026-07-20

CREATE TABLE IF NOT EXISTS `service_cancellations` (
  `service_cancellation_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `service_transaction_id` bigint(20) NOT NULL,
  `transaction_code` varchar(50) NOT NULL,
  `order_id` bigint(20) DEFAULT NULL,
  `passenger_id` bigint(20) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `cancellation_type` enum('full','partial') DEFAULT 'full',
  `refund_amount` decimal(12,2) DEFAULT 0.00,
  `charge_amount` decimal(12,2) DEFAULT 0.00,
  `cash_refund_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `requested_by` bigint(20) NOT NULL,
  `cashier_session_id` bigint(20) DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` bigint(20) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`service_cancellation_id`),
  KEY `idx_service_transaction_id` (`service_transaction_id`),
  KEY `idx_status` (`status`),
  KEY `idx_requested_by` (`requested_by`),
  KEY `idx_cashier_session_id` (`cashier_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `service_refunds` (
  `service_refund_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `service_transaction_id` bigint(20) NOT NULL,
  `transaction_code` varchar(50) NOT NULL,
  `service_cancellation_id` bigint(20) DEFAULT NULL,
  `passenger_id` bigint(20) DEFAULT NULL,
  `refund_amount` decimal(12,2) NOT NULL,
  `cash_amount` decimal(12,2) DEFAULT 0.00,
  `charge_reversal_amount` decimal(12,2) DEFAULT 0.00,
  `refund_method` varchar(50) DEFAULT 'cash',
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `requested_by` bigint(20) NOT NULL,
  `cashier_session_id` bigint(20) DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_by` bigint(20) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`service_refund_id`),
  KEY `idx_service_transaction_id` (`service_transaction_id`),
  KEY `idx_service_cancellation_id` (`service_cancellation_id`),
  KEY `idx_status` (`status`),
  KEY `idx_requested_by` (`requested_by`),
  KEY `idx_cashier_session_id` (`cashier_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
