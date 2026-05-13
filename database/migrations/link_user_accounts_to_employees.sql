-- Link user_accounts table to employees table
-- Migration: Add emp_id foreign key to user_accounts and remove fullname
-- Date: 2026-05-12

-- Add emp_id column to user_accounts
ALTER TABLE `user_accounts` ADD COLUMN `emp_id` int(11) DEFAULT NULL AFTER `profile_image`;

-- Remove fullname column (will use employee name instead)
ALTER TABLE `user_accounts` DROP COLUMN `fullname`;

-- Add foreign key constraint
ALTER TABLE `user_accounts` ADD CONSTRAINT `fk_user_accounts_employee`
FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Add index for better performance
ALTER TABLE `user_accounts` ADD INDEX `idx_emp_id` (`emp_id`);
