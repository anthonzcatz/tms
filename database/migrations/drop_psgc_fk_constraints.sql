-- Drop PSGC foreign key constraints from employees table (PSGC tables don't exist yet)
-- Migration: Remove PSGC constraints to fix error
-- Date: 2026-05-12

-- Drop PSGC foreign key constraints
ALTER TABLE `employees` DROP FOREIGN KEY IF EXISTS `fk_employees_province`;
ALTER TABLE `employees` DROP FOREIGN KEY IF EXISTS `fk_employees_city`;
ALTER TABLE `employees` DROP FOREIGN KEY IF EXISTS `fk_employees_barangay`;
