-- Add status columns to position, sub_department, and employment_status tables
-- for consistent active/inactive management across HR settings.

ALTER TABLE `position` ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) NOT NULL DEFAULT 'active' AFTER `pos_code`;
ALTER TABLE `sub_department` ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) NOT NULL DEFAULT 'active' AFTER `main_department_id`;
ALTER TABLE `employment_status` ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) NOT NULL DEFAULT 'active' AFTER `emp_stat_name`;
