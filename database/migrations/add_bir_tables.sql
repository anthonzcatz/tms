-- BIR Accredited System Database Schema
-- This migration adds all BIR-related tables and modifies existing tables for BIR compliance
-- Version: 1.0
-- Date: June 1, 2026

-- Select the database (change 'tms_db' to your actual database name if different)
USE `tms_db`;

-- ============================================================
-- NEW BIR TABLES
-- ============================================================

-- 1. BIR OR Numbers (Official Receipt Numbering System)
CREATE TABLE IF NOT EXISTS `bir_or_numbers` (
  `or_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) NOT NULL,
  `or_number` bigint(20) NOT NULL COMMENT 'Sequential number within series',
  `or_series` varchar(20) NOT NULL COMMENT 'Format: BRANCH-YEAR (e.g., 001-2024)',
  `or_full_number` varchar(50) NOT NULL COMMENT 'Full OR number (e.g., 001-2024-000001)',
  `status` enum('issued','void','cancelled') NOT NULL DEFAULT 'issued',
  `order_id` bigint(20) DEFAULT NULL COMMENT 'Reference to pos_orders',
  `issued_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint(20) DEFAULT NULL COMMENT 'User who voided the OR',
  `void_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`or_id`),
  UNIQUE KEY `uk_or_full_number` (`or_full_number`),
  KEY `idx_branch_id` (`branch_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_status` (`status`),
  KEY `idx_issued_at` (`issued_at`),
  CONSTRAINT `fk_or_branch` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_or_order` FOREIGN KEY (`order_id`) REFERENCES `pos_orders` (`order_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_or_voided_by` FOREIGN KEY (`voided_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. BIR OR Series Management (Per branch per year)
CREATE TABLE IF NOT EXISTS `bir_or_series` (
  `series_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) NOT NULL,
  `year` int(4) NOT NULL,
  `series_code` varchar(20) NOT NULL COMMENT 'Format: BRANCH-YEAR (e.g., 001-2024)',
  `start_number` bigint(20) NOT NULL DEFAULT 1,
  `current_number` bigint(20) NOT NULL DEFAULT 0,
  `end_number` bigint(20) NOT NULL DEFAULT 999999,
  `status` enum('active','closed','archived') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`series_id`),
  UNIQUE KEY `uk_branch_year` (`branch_id`, `year`),
  KEY `idx_series_code` (`series_code`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_series_branch` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_series_created_by` FOREIGN KEY (`created_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BIR VAT Transactions
CREATE TABLE IF NOT EXISTS `bir_vat_transactions` (
  `vat_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) NOT NULL,
  `vat_type` enum('12_percent','exempt','zero_rated') NOT NULL DEFAULT '12_percent',
  `vat_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `non_taxable_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `exemption_type` enum('senior_citizen','pwd','agricultural','export','educational','others') DEFAULT NULL,
  `exemption_id_number` varchar(50) DEFAULT NULL COMMENT 'OSCA ID for senior citizen, PWD ID, etc.',
  `exemption_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`vat_id`),
  UNIQUE KEY `uk_order_id` (`order_id`),
  KEY `idx_vat_type` (`vat_type`),
  KEY `idx_exemption_type` (`exemption_type`),
  CONSTRAINT `fk_vat_order` FOREIGN KEY (`order_id`) REFERENCES `pos_orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. BIR Reports
CREATE TABLE IF NOT EXISTS `bir_reports` (
  `report_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `report_type` enum('DSR','Monthly','SLS','Alphalist','2550M') NOT NULL,
  `report_date` date NOT NULL,
  `report_period_start` date DEFAULT NULL,
  `report_period_end` date DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL COMMENT 'NULL for all branches',
  `generated_by` bigint(20) NOT NULL,
  `data_json` longtext NOT NULL COMMENT 'Report data in JSON format',
  `file_path` varchar(255) DEFAULT NULL COMMENT 'Generated file path (PDF/Excel)',
  `status` enum('generated','exported','submitted') NOT NULL DEFAULT 'generated',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`report_id`),
  KEY `idx_report_type` (`report_type`),
  KEY `idx_report_date` (`report_date`),
  KEY `idx_branch_id` (`branch_id`),
  KEY `idx_generated_by` (`generated_by`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_report_branch` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_report_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BIR POS Machines (Accreditation Details)
CREATE TABLE IF NOT EXISTS `bir_pos_machines` (
  `machine_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) NOT NULL,
  `machine_name` varchar(100) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `accreditation_number` varchar(100) DEFAULT NULL,
  `accreditation_expiry` date DEFAULT NULL,
  `machine_type` enum('CAS','POS') NOT NULL DEFAULT 'POS',
  `min` varchar(50) DEFAULT NULL COMMENT 'Machine Identification Number',
  `permit_number` varchar(100) DEFAULT NULL,
  `validity_from` date DEFAULT NULL,
  `validity_to` date DEFAULT NULL,
  `status` enum('active','expired','suspended','decommissioned') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`machine_id`),
  UNIQUE KEY `uk_serial_number` (`serial_number`),
  KEY `idx_branch_id` (`branch_id`),
  KEY `idx_status` (`status`),
  KEY `idx_accreditation_expiry` (`accreditation_expiry`),
  CONSTRAINT `fk_machine_branch` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_machine_created_by` FOREIGN KEY (`created_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. BIR Audit Trail
CREATE TABLE IF NOT EXISTS `bir_audit_trail` (
  `audit_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) DEFAULT NULL,
  `user_id` bigint(20) NOT NULL,
  `action` enum('create','modify','void','cancel','refund','delete') NOT NULL,
  `table_name` varchar(50) NOT NULL COMMENT 'Table affected (pos_orders, bir_or_numbers, etc.)',
  `record_id` bigint(20) NOT NULL COMMENT 'ID of the affected record',
  `field_changed` varchar(100) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_table_name` (`table_name`),
  KEY `idx_record_id` (`record_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_audit_order` FOREIGN KEY (`order_id`) REFERENCES `pos_orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `user_accounts` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. BIR Transaction Modifications (Detailed tracking)
CREATE TABLE IF NOT EXISTS `bir_transaction_modifications` (
  `modification_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) NOT NULL,
  `modified_by` bigint(20) NOT NULL,
  `modification_type` enum('price_change','discount_change','quantity_change','payment_change','refund','void','cancel') NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `previous_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `modified_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`modification_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_modified_by` (`modified_by`),
  KEY `idx_modification_type` (`modification_type`),
  KEY `idx_modified_at` (`modified_at`),
  CONSTRAINT `fk_mod_order` FOREIGN KEY (`order_id`) REFERENCES `pos_orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mod_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. BIR Backups
CREATE TABLE IF NOT EXISTS `bir_backups` (
  `backup_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `backup_date` date NOT NULL,
  `backup_type` enum('full','incremental') NOT NULL DEFAULT 'full',
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `checksum` varchar(64) NOT NULL COMMENT 'MD5 checksum for integrity verification',
  `status` enum('success','failed','corrupted') NOT NULL DEFAULT 'success',
  `created_by` bigint(20) NOT NULL,
  `restored_at` timestamp NULL DEFAULT NULL,
  `restored_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`backup_id`),
  KEY `idx_backup_date` (`backup_date`),
  KEY `idx_backup_type` (`backup_type`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_backup_created_by` FOREIGN KEY (`created_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_backup_restored_by` FOREIGN KEY (`restored_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. BIR Submissions (For electronic submission to BIR)
CREATE TABLE IF NOT EXISTS `bir_submissions` (
  `submission_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) NOT NULL,
  `submission_type` enum('DSR','Monthly','SLS','Alphalist','2550M') NOT NULL,
  `submission_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `branch_id` bigint(20) DEFAULT NULL,
  `submitted_by` bigint(20) NOT NULL,
  `acknowledgment_number` varchar(100) DEFAULT NULL,
  `acknowledgment_date` date DEFAULT NULL,
  `status` enum('pending','submitted','acknowledged','rejected') NOT NULL DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`submission_id`),
  KEY `idx_report_id` (`report_id`),
  KEY `idx_submission_type` (`submission_type`),
  KEY `idx_branch_id` (`branch_id`),
  KEY `idx_status` (`status`),
  KEY `idx_acknowledgment_number` (`acknowledgment_number`),
  CONSTRAINT `fk_submission_report` FOREIGN KEY (`report_id`) REFERENCES `bir_reports` (`report_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submission_branch` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submission_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MODIFICATIONS TO EXISTING TABLES
-- ============================================================

-- Add BIR-related columns to pos_orders (only OR number reference, VAT data in separate table)
ALTER TABLE `pos_orders`
ADD COLUMN `or_number_id` bigint(20) DEFAULT NULL AFTER `order_id`,
ADD INDEX `idx_or_number_id` (`or_number_id`),
ADD CONSTRAINT `fk_order_or_number` FOREIGN KEY (`or_number_id`) REFERENCES `bir_or_numbers` (`or_id`) ON DELETE SET NULL;

-- Add BIR-related columns to system_settings
ALTER TABLE `system_settings`
ADD COLUMN `bir_accreditation_number` varchar(100) DEFAULT NULL AFTER `company_tin`,
ADD COLUMN `bir_accreditation_expiry` date DEFAULT NULL AFTER `bir_accreditation_number`,
ADD COLUMN `bir_permit_number` varchar(100) DEFAULT NULL AFTER `bir_accreditation_expiry`,
ADD COLUMN `bir_validity_from` date DEFAULT NULL AFTER `bir_permit_number`,
ADD COLUMN `bir_validity_to` date DEFAULT NULL AFTER `bir_validity_from`,
ADD COLUMN `bir_min` varchar(50) DEFAULT NULL AFTER `bir_validity_to`,
ADD COLUMN `bir_machine_serial` varchar(100) DEFAULT NULL AFTER `bir_min`,
ADD COLUMN `bir_auto_or_assignment` tinyint(1) NOT NULL DEFAULT 1 AFTER `bir_machine_serial`,
ADD COLUMN `bir_vat_rate` decimal(5,2) NOT NULL DEFAULT 12.00 AFTER `bir_auto_or_assignment`;

-- Note: VAT breakdown per item can be calculated from bir_vat_transactions if needed
-- No columns added to pos_order_items to avoid redundancy

-- ============================================================
-- TRIGGERS FOR AUTOMATIC AUDIT TRAIL
-- ============================================================

-- Trigger to log OR number issuance
DELIMITER //
CREATE TRIGGER `tr_bir_or_number_insert` AFTER INSERT ON `bir_or_numbers`
FOR EACH ROW
BEGIN
    INSERT INTO `bir_audit_trail` (
        `order_id`, `user_id`, `action`, `table_name`, 
        `record_id`, `field_changed`, `old_value`, `new_value`, 
        `reason`, `ip_address`
    ) VALUES (
        NEW.order_id, 
        (SELECT created_by FROM bir_or_series WHERE series_code = NEW.or_series LIMIT 1),
        'create',
        'bir_or_numbers',
        NEW.or_id,
        'or_full_number',
        NULL,
        NEW.or_full_number,
        'OR number issued',
        NULL
    );
END//
DELIMITER ;

-- Trigger to log OR void/cancel
DELIMITER //
CREATE TRIGGER `tr_bir_or_number_update` AFTER UPDATE ON `bir_or_numbers`
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status AND NEW.status IN ('void', 'cancelled') THEN
        INSERT INTO `bir_audit_trail` (
            `order_id`, `user_id`, `action`, `table_name`, 
            `record_id`, `field_changed`, `old_value`, `new_value`, 
            `reason`, `ip_address`
        ) VALUES (
            NEW.order_id,
            NEW.voided_by,
            NEW.status,
            'bir_or_numbers',
            NEW.or_id,
            'status',
            OLD.status,
            NEW.status,
            NEW.void_reason,
            NULL
        );
    END IF;
END//
DELIMITER ;

-- ============================================================
-- INITIAL DATA SEEDING
-- ============================================================

-- Insert default OR series for existing branches (current year)
INSERT INTO `bir_or_series` (`branch_id`, `year`, `series_code`, `start_number`, `current_number`, `end_number`, `status`, `created_by`)
SELECT 
    branch_id, 
    YEAR(CURDATE()) as year,
    CONCAT(LPAD(branch_id, 3, '0'), '-', YEAR(CURDATE())) as series_code,
    1 as start_number,
    0 as current_number,
    999999 as end_number,
    'active' as status,
    1 as created_by
FROM business_branches
WHERE status = 'active'
AND NOT EXISTS (
    SELECT 1 FROM bir_or_series 
    WHERE branch_id = business_branches.branch_id 
    AND year = YEAR(CURDATE())
);

-- ============================================================
-- END OF MIGRATION
-- ============================================================
