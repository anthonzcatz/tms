-- Add PSGC foreign key constraints to employees table (indexes already exist)
-- Migration: Add PSGC foreign key constraints only
-- Date: 2026-05-12

-- Add foreign key constraint for branch_id (ignore if already exists)
ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_branch` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Add foreign key constraints for PSGC codes (ignore if already exist)
ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_province` FOREIGN KEY (`emp_province_code`) REFERENCES `psgc_provinces` (`province_code`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_city` FOREIGN KEY (`emp_city_code`) REFERENCES `psgc_cities_municipalities` (`city_municipality_code`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_barangay` FOREIGN KEY (`emp_barangay_code`) REFERENCES `psgc_barangays` (`barangay_code`) ON DELETE SET NULL ON UPDATE CASCADE;
