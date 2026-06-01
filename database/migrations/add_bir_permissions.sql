-- BIR Module Permissions Migration
-- Adds BIR-related permissions to the system
-- Version: 1.0
-- Date: June 1, 2026

USE `tms_db`;

-- ============================================================
-- BIR MODULE PERMISSIONS
-- ============================================================

-- Add BIR permissions
INSERT INTO `permissions` (`permission_code`, `permission_name`, `module_name`, `menu_url`, `menu_icon`, `menu_order`) VALUES
('BIR_ACCESS', 'Access BIR Module', 'BIR', '/admin/bir/', 'fa-building', 100),
('BIR_SETTINGS', 'Manage BIR Settings', 'BIR', '/admin/bir/settings/', 'fa-cog', 101),
('BIR_OR_NUMBERS_VIEW', 'View OR Numbers', 'BIR', '/admin/bir/or-numbers/', 'fa-receipt', 102),
('BIR_OR_NUMBERS_MANAGE', 'Manage OR Numbers', 'BIR', '/admin/bir/or-numbers/', 'fa-receipt', 103),
('BIR_VAT_VIEW', 'View VAT', 'BIR', '/admin/bir/vat/', 'fa-percentage', 104),
('BIR_REPORTS_VIEW', 'View BIR Reports', 'BIR', '/admin/bir/reports/', 'fa-chart-bar', 105),
('BIR_REPORTS_GENERATE', 'Generate BIR Reports', 'BIR', '/admin/bir/reports/', 'fa-chart-bar', 106),
('BIR_MACHINES_VIEW', 'View POS Machines', 'BIR', '/admin/bir/machines/', 'fa-desktop', 107),
('BIR_MACHINES_MANAGE', 'Manage POS Machines', 'BIR', '/admin/bir/machines/', 'fa-desktop', 108),
('BIR_AUDIT_VIEW', 'View Audit Trail', 'BIR', '/admin/bir/audit-trail/', 'fa-history', 109),
('BIR_BACKUPS_VIEW', 'View Backups', 'BIR', '/admin/bir/backups/', 'fa-database', 110),
('BIR_BACKUPS_MANAGE', 'Manage Backups', 'BIR', '/admin/bir/backups/', 'fa-database', 111)
ON DUPLICATE KEY UPDATE 
    permission_name = VALUES(permission_name),
    module_name = VALUES(module_name),
    menu_url = VALUES(menu_url),
    menu_icon = VALUES(menu_icon);

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
