<?php
/**
 * Migration: add Reports menu permissions.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

try {
    // Insert parent Reports category menu if not exists
    Database::execute(
        "INSERT INTO `permissions` (`permission_code`, `permission_name`, `module_name`, `parent_permission_id`, `menu_order`, `menu_icon`, `menu_url`, `menu_level`, `is_menu_item`, `is_active`)
        VALUES ('VIEW_REPORTS', 'Reports', 'REPORTS', NULL, 20, 'fas fa-chart-line', 'admin/reports', 1, 1, 1)
        ON DUPLICATE KEY UPDATE `permission_name` = 'Reports', `menu_icon` = 'fas fa-chart-line', `menu_url` = 'admin/reports', `is_menu_item` = 1"
    );

    $parentId = Database::fetch("SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_REPORTS'")['permission_id'] ?? null;

    if ($parentId) {
        Database::execute(
            "INSERT INTO `permissions` (`permission_code`, `permission_name`, `module_name`, `parent_permission_id`, `menu_order`, `menu_icon`, `menu_url`, `menu_level`, `is_menu_item`, `is_active`)
            VALUES ('VIEW_FINANCIAL_REPORTS', 'Financial Reports', 'REPORTS', :parent_id, 1, 'fas fa-file-invoice-dollar', 'admin/reports/financial/', 2, 1, 1)
            ON DUPLICATE KEY UPDATE `parent_permission_id` = VALUES(`parent_permission_id`), `menu_url` = 'admin/reports/financial/', `is_menu_item` = 1",
            ['parent_id' => $parentId]
        );
    }

    echo "Reports permissions migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
