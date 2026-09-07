CREATE TABLE IF NOT EXISTS `ticket_stock_access_assignments` (
    `assignment_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) NOT NULL,
    `branch_id` bigint(20) NOT NULL,
    `access_type` enum('APPROVER','RECEIVER') NOT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_by` bigint(20) DEFAULT NULL,
    `updated_by` bigint(20) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
    PRIMARY KEY (`assignment_id`),
    UNIQUE KEY `uq_ticket_stock_access_user_branch_type` (`user_id`, `branch_id`, `access_type`),
    KEY `idx_ticket_stock_access_branch_type` (`branch_id`, `access_type`, `is_active`),
    KEY `idx_ticket_stock_access_user` (`user_id`, `is_active`),
    CONSTRAINT `fk_ticket_stock_access_user` FOREIGN KEY (`user_id`) REFERENCES `user_accounts` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ticket_stock_access_branch` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ticket_stock_access_created_by` FOREIGN KEY (`created_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ticket_stock_access_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (
    `permission_code`, `permission_name`, `module_name`, `parent_permission_id`,
    `menu_order`, `menu_icon`, `menu_url`, `menu_level`, `is_menu_item`, `is_active`
)
SELECT
    'MANAGE_TICKET_STOCK_ACCESS', 'Ticket Stock Access', 'TICKET_STOCK', p.`permission_id`,
    6, 'fas fa-user-shield', 'admin/ticket-stock/access', 2, 1, 1
FROM `permissions` p
WHERE p.`permission_code` = 'VIEW_TICKET_STOCK'
  AND NOT EXISTS (
      SELECT 1 FROM `permissions` existing
      WHERE existing.`permission_code` = 'MANAGE_TICKET_STOCK_ACCESS'
  );

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`role_id`, p.`permission_id`
FROM `user_roles` r
JOIN `permissions` p ON p.`permission_code` = 'MANAGE_TICKET_STOCK_ACCESS'
WHERE r.`role_code` IN ('SUPER_ADMIN', 'ADMIN');
