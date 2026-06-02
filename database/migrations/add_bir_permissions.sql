-- BIR Module Permissions Migration
-- Adds BIR-related permissions to the system
-- Version: 1.0
-- Date: June 1, 2026

USE `tms_db`;

-- ============================================================
-- BIR MODULE PERMISSIONS
-- ============================================================

-- Add BIR permissions
INSERT INTO `permissions` (`permission_code`, `permission_name`, `module_name`, `parent_permission_id`, `menu_url`, `menu_icon`, `menu_order`, `is_menu_item`) VALUES
('BIR_ACCESS', 'BIR Module', 'BIR', NULL, '/admin/bir/', 'fa-building', 100, 1),
('BIR_SETTINGS', 'BIR Settings', 'BIR', NULL, '/admin/bir/settings/', 'fa-cog', 101, 1),
('BIR_OR_NUMBERS', 'OR Numbers', 'BIR', NULL, '/admin/bir/or-numbers/', 'fa-receipt', 102, 1),
('BIR_VAT', 'VAT Management', 'BIR', NULL, '/admin/bir/vat/', 'fa-percentage', 103, 1),
('BIR_REPORTS', 'BIR Reports', 'BIR', NULL, '/admin/bir/reports/', 'fa-chart-bar', 104, 1),
('BIR_MACHINES', 'POS Machines', 'BIR', NULL, '/admin/bir/machines/', 'fa-desktop', 105, 1),
('BIR_AUDIT_TRAIL', 'Audit Trail', 'BIR', NULL, '/admin/bir/audit-trail/', 'fa-history', 106, 1),
('BIR_BACKUPS', 'Backups', 'BIR', NULL, '/admin/bir/backups/', 'fa-database', 107, 1)
ON DUPLICATE KEY UPDATE 
    permission_name = VALUES(permission_name),
    module_name = VALUES(module_name),
    menu_url = VALUES(menu_url),
    menu_icon = VALUES(menu_icon),
    is_menu_item = VALUES(is_menu_item);

-- ============================================================
-- ASSIGN PERMISSIONS TO ROLES
-- ============================================================

-- SUPER_ADMIN: All BIR permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.role_id, p.permission_id
FROM user_roles r, permissions p
WHERE r.role_code = 'SUPER_ADMIN' 
AND p.permission_code LIKE 'BIR_%'
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.permission_id = p.permission_id
);

-- ADMIN: All BIR permissions except some critical ones
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.role_id, p.permission_id
FROM user_roles r, permissions p
WHERE r.role_code = 'ADMIN' 
AND p.permission_code LIKE 'BIR_%'
AND p.permission_code NOT IN ('BIR_BACKUPS_MANAGE') -- Admin can view but not manage backups
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.permission_id = p.permission_id
);

-- MANAGER: View and limited manage permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.role_id, p.permission_id
FROM user_roles r, permissions p
WHERE r.role_code = 'MANAGER' 
AND p.permission_code IN (
    'BIR_ACCESS',
    'BIR_OR_NUMBERS_VIEW',
    'BIR_OR_NUMBERS_MANAGE',
    'BIR_VAT_VIEW',
    'BIR_REPORTS_VIEW',
    'BIR_REPORTS_GENERATE',
    'BIR_MACHINES_VIEW',
    'BIR_AUDIT_VIEW'
)
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.permission_id = p.permission_id
);

-- ACCOUNTANT: View reports and VAT only
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.role_id, p.permission_id
FROM user_roles r, permissions p
WHERE r.role_code = 'ACCOUNTANT' 
AND p.permission_code IN (
    'BIR_ACCESS',
    'BIR_OR_NUMBERS_VIEW',
    'BIR_VAT_VIEW',
    'BIR_REPORTS_VIEW',
    'BIR_REPORTS_GENERATE',
    'BIR_AUDIT_VIEW'
)
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.permission_id = p.permission_id
);

-- CASHIER: Limited view access (mainly OR numbers for reference)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.role_id, p.permission_id
FROM user_roles r, permissions p
WHERE r.role_code = 'CASHIER' 
AND p.permission_code IN ('BIR_ACCESS', 'BIR_OR_NUMBERS_VIEW')
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp 
    WHERE rp.role_id = r.role_id AND rp.permission_id = p.permission_id
);

-- ============================================================
-- ADD BIR TO NAVBAR/SIDEBAR MENU
-- ============================================================

-- Note: Manual update required to add BIR menu to your navigation template
-- Add this to your admin sidebar/navigation:

/*
<li class="nav-item">
  <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/bir/">
    <span class="fas fa-building me-2"></span>BIR Module
  </a>
</li>
*/

-- ============================================================
-- END OF MIGRATION
-- ============================================================
