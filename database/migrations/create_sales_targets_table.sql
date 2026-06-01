-- Sales Targets Table
-- Stores daily sales targets per branch
CREATE TABLE IF NOT EXISTS `sales_targets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT NOT NULL,
  `target_date` DATE NOT NULL,
  `target_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_branch_date` (`branch_id`, `target_date`),
  KEY `idx_target_date` (`target_date`),
  KEY `idx_branch_id` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comments
ALTER TABLE `sales_targets` COMMENT = 'Stores daily sales targets per branch';

-- Add notes column if table already exists
ALTER TABLE `sales_targets` ADD COLUMN IF NOT EXISTS `notes` TEXT NULL AFTER `target_amount`;
