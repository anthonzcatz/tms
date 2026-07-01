-- Migration: Add VIEW_NOTIFICATIONS permission
-- This adds the VIEW_NOTIFICATIONS permission to the permissions table
-- and assigns it to SUPER_ADMIN and ADMIN roles

-- Add VIEW_NOTIFICATIONS permission
INSERT IGNORE INTO permissions (
    permission_code,
    permission_name,
    module_name,
    parent_permission_id,
    menu_order,
    menu_icon,
    menu_url,
    is_menu_item
) VALUES (
    'VIEW_NOTIFICATIONS',
    'Notifications',
    'SYSTEM',
    NULL,
    8,
    'fa-bell',
    'admin/notifications',
    1
);

-- Assign VIEW_NOTIFICATIONS to SUPER_ADMIN role
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_code = 'SUPER_ADMIN'
AND p.permission_code = 'VIEW_NOTIFICATIONS';

-- Assign VIEW_NOTIFICATIONS to ADMIN role
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM user_roles r
CROSS JOIN permissions p
WHERE r.role_code = 'ADMIN'
AND p.permission_code = 'VIEW_NOTIFICATIONS';
