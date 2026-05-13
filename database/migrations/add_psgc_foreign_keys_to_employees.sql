-- Add PSGC foreign keys to employees table
-- Migration: Add PSGC constraints to employees (branch_id already exists)
-- Date: 2026-05-12

-- Add indexes for PSGC codes (ignore if already exist)
ALTER TABLE `employees` ADD INDEX `idx_emp_province_code` (`emp_province_code`);
ALTER TABLE `employees` ADD INDEX `idx_emp_city_code` (`emp_city_code`);
ALTER TABLE `employees` ADD INDEX `idx_emp_barangay_code` (`emp_barangay_code`);

-- Add foreign key constraint for branch_id (ignore if already exists)
ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_branch` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Add foreign key constraints for PSGC codes (ignore if already exist)
ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_province` FOREIGN KEY (`emp_province_code`) REFERENCES `psgc_provinces` (`province_code`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_city` FOREIGN KEY (`emp_city_code`) REFERENCES `psgc_cities_municipalities` (`city_municipality_code`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_barangay` FOREIGN KEY (`emp_barangay_code`) REFERENCES `psgc_barangays` (`barangay_code`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Add index for branch_id (ignore if already exists)
ALTER TABLE `employees` ADD INDEX `idx_branch_id` (`branch_id`);
