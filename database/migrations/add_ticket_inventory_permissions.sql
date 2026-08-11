-- Migration: Add Ticket Inventory and Fulfillment permissions (SQL version)
-- Date: 2026-07-16
-- Purpose: Insert the Ticket Stock sidebar menu and action permissions,
--          then assign them to SUPER_ADMIN, ADMIN, MANAGER, and CASHIER roles.
-- Run: mysql -u root -p tms_db < database/migrations/add_ticket_inventory_permissions.sql

-- ============================================================
-- TICKET STOCK PARENT MENU
-- ============================================================
INSERT INTO `permissions` (
    `permission_code`, `permission_name`, `module_name`, `parent_permission_id`,
    `menu_order`, `menu_icon`, `menu_url`, `menu_level`, `is_menu_item`, `is_active`
) VALUES (
    'VIEW_TICKET_STOCK', 'Ticket Stock', 'TICKET_STOCK', NULL,
    6, 'fas fa-boxes', NULL, 1, 1, 1
)
ON DUPLICATE KEY UPDATE
    `permission_name` = VALUES(`permission_name`),
    `module_name` = VALUES(`module_name`),
    `menu_order` = VALUES(`menu_order`),
    `menu_icon` = VALUES(`menu_icon`),
    `menu_url` = VALUES(`menu_url`),
    `menu_level` = VALUES(`menu_level`),
    `is_menu_item` = VALUES(`is_menu_item`),
    `is_active` = VALUES(`is_active`),
    `permission_id` = LAST_INSERT_ID(`permission_id`);

SET @ticket_stock_parent_id = LAST_INSERT_ID();

-- ============================================================
-- SIDEBAR MENU ITEMS
-- ============================================================
INSERT INTO `permissions` (
    `permission_code`, `permission_name`, `module_name`, `parent_permission_id`,
    `menu_order`, `menu_icon`, `menu_url`, `menu_level`, `is_menu_item`, `is_active`
) VALUES
('MANAGE_TICKET_VARIANTS', 'Variants', 'TICKET_STOCK', @ticket_stock_parent_id, 1, 'fas fa-palette', 'admin/ticket-stock/variants', 2, 1, 1),
('VIEW_TICKET_STOCK_BALANCES', 'Balances', 'TICKET_STOCK', @ticket_stock_parent_id, 2, 'fas fa-warehouse', 'admin/ticket-stock/balances', 2, 1, 1),
('VIEW_TICKET_STOCK_REQUESTS', 'Stock Requests', 'TICKET_STOCK', @ticket_stock_parent_id, 3, 'fas fa-file-alt', 'admin/ticket-stock/requests', 2, 1, 1),
('VIEW_TICKET_STOCK_MOVEMENTS', 'Movements', 'TICKET_STOCK', @ticket_stock_parent_id, 4, 'fas fa-exchange-alt', 'admin/ticket-stock/movements', 2, 1, 1),
('VIEW_TICKET_STOCK_DISCREPANCIES', 'Discrepancies', 'TICKET_STOCK', @ticket_stock_parent_id, 5, 'fas fa-exclamation-triangle', 'admin/ticket-stock/discrepancies', 2, 1, 1)
ON DUPLICATE KEY UPDATE
    `permission_name` = VALUES(`permission_name`),
    `parent_permission_id` = VALUES(`parent_permission_id`),
    `menu_order` = VALUES(`menu_order`),
    `menu_icon` = VALUES(`menu_icon`),
    `menu_url` = VALUES(`menu_url`),
    `menu_level` = VALUES(`menu_level`),
    `is_menu_item` = VALUES(`is_menu_item`),
    `is_active` = VALUES(`is_active`);

-- ============================================================
-- ACTION PERMISSIONS (non-menu)
-- ============================================================
INSERT INTO `permissions` (
    `permission_code`, `permission_name`, `module_name`, `parent_permission_id`,
    `menu_order`, `menu_icon`, `menu_url`, `menu_level`, `is_menu_item`, `is_active`
) VALUES
('CREATE_TICKET_STOCK_REQUEST', 'Create Stock Request', 'TICKET_STOCK', @ticket_stock_parent_id, 10, NULL, NULL, 2, 0, 1),
('APPROVE_TICKET_STOCK_REQUEST', 'Approve Stock Request', 'TICKET_STOCK', @ticket_stock_parent_id, 11, NULL, NULL, 2, 0, 1),
('DISPATCH_TICKET_STOCK', 'Dispatch Stock', 'TICKET_STOCK', @ticket_stock_parent_id, 12, NULL, NULL, 2, 0, 1),
('RECEIVE_TICKET_STOCK', 'Receive Stock', 'TICKET_STOCK', @ticket_stock_parent_id, 13, NULL, NULL, 2, 0, 1),
('ADJUST_TICKET_STOCK', 'Adjust Stock', 'TICKET_STOCK', @ticket_stock_parent_id, 14, NULL, NULL, 2, 0, 1),
('RESOLVE_TICKET_STOCK_DISCREPANCY', 'Resolve Discrepancy', 'TICKET_STOCK', @ticket_stock_parent_id, 15, NULL, NULL, 2, 0, 1),
('VIEW_ALL_TICKET_STOCK', 'View All Ticket Stock (cross-branch)', 'TICKET_STOCK', @ticket_stock_parent_id, 16, NULL, NULL, 2, 0, 1)
ON DUPLICATE KEY UPDATE
    `permission_name` = VALUES(`permission_name`),
    `parent_permission_id` = VALUES(`parent_permission_id`),
    `menu_order` = VALUES(`menu_order`),
    `menu_level` = VALUES(`menu_level`),
    `is_active` = VALUES(`is_active`);

-- ============================================================
-- ASSIGN PERMISSIONS TO ROLES
-- ============================================================

-- SUPER_ADMIN: all TICKET_STOCK module permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`role_id`, p.`permission_id`
FROM (SELECT `role_id` FROM `user_roles` WHERE `role_code` = 'SUPER_ADMIN') r
CROSS JOIN (SELECT `permission_id` FROM `permissions` WHERE `module_name` = 'TICKET_STOCK') p
WHERE NOT EXISTS (
    SELECT 1 FROM `role_permissions` rp
    WHERE rp.`role_id` = r.`role_id` AND rp.`permission_id` = p.`permission_id`
);

-- ADMIN: all TICKET_STOCK module permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`role_id`, p.`permission_id`
FROM (SELECT `role_id` FROM `user_roles` WHERE `role_code` = 'ADMIN') r
CROSS JOIN (SELECT `permission_id` FROM `permissions` WHERE `module_name` = 'TICKET_STOCK') p
WHERE NOT EXISTS (
    SELECT 1 FROM `role_permissions` rp
    WHERE rp.`role_id` = r.`role_id` AND rp.`permission_id` = p.`permission_id`
);

-- MANAGER: all TICKET_STOCK permissions except cross-branch view
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`role_id`, p.`permission_id`
FROM (SELECT `role_id` FROM `user_roles` WHERE `role_code` = 'MANAGER') r
CROSS JOIN (SELECT `permission_id` FROM `permissions` WHERE `module_name` = 'TICKET_STOCK' AND `permission_code` <> 'VIEW_ALL_TICKET_STOCK') p
WHERE NOT EXISTS (
    SELECT 1 FROM `role_permissions` rp
    WHERE rp.`role_id` = r.`role_id` AND rp.`permission_id` = p.`permission_id`
);

-- CASHIER: limited view and request/receive permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`role_id`, p.`permission_id`
FROM (SELECT `role_id` FROM `user_roles` WHERE `role_code` = 'CASHIER') r
CROSS JOIN (
    SELECT `permission_id` FROM `permissions`
    WHERE `module_name` = 'TICKET_STOCK'
    AND `permission_code` IN (
        'VIEW_TICKET_STOCK',
        'VIEW_TICKET_STOCK_BALANCES',
        'VIEW_TICKET_STOCK_REQUESTS',
        'CREATE_TICKET_STOCK_REQUEST',
        'RECEIVE_TICKET_STOCK'
    )
) p
WHERE NOT EXISTS (
    SELECT 1 FROM `role_permissions` rp
    WHERE rp.`role_id` = r.`role_id` AND rp.`permission_id` = p.`permission_id`
);
