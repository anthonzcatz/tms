-- Migration: Add email-settings permission
-- Description: Add permission for email settings module

INSERT IGNORE INTO permissions (
    permission_code,
    permission_name,
    module_name,
    parent_permission_id,
    menu_order,
    menu_icon,
    menu_url,
    menu_level,
    is_menu_item
)
SELECT 
    'MANAGE_EMAIL_SETTINGS',
    'Email Settings',
    'SETTINGS',
    (SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_SETTINGS'),
    6,
    'fas fa-envelope',
    'admin/settings/email-settings',
    2,
    1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE permission_code = 'MANAGE_EMAIL_SETTINGS');

-- Grant permission to SUPER_ADMIN and ADMIN roles
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_code IN ('SUPER_ADMIN', 'ADMIN')
AND p.permission_code = 'MANAGE_EMAIL_SETTINGS';
