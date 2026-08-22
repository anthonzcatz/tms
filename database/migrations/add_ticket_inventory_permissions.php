<?php
/**
 * Migration: Add Ticket Inventory and Fulfillment permissions
 * Adds the parent menu and all action permissions for ticket variants,
 * stock balances, stock requests, movements, and discrepancies.
 *
 * Run: http://localhost/TMS/database/migrations/add_ticket_inventory_permissions.php
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

echo "<pre>\n";
echo "=== Ticket Inventory Permissions Migration ===\n\n";

Database::execute(
    "ALTER TABLE permissions
     ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_menu_item"
);

try {
    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------
    function insertPermission($code, $name, $module, $parentId, $order, $icon, $url, $isMenu) {
        $existing = Database::fetch("SELECT permission_id FROM permissions WHERE permission_code = ?", [$code]);
        if ($existing) {
            echo "  SKIP   {$code} (already exists, id={$existing['permission_id']})\n";
            return $existing['permission_id'];
        }
        Database::execute(
            "INSERT INTO permissions
                (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
            [$code, $name, $module, $parentId, $order, $icon, $url, $parentId ? 2 : 1, $isMenu ? 1 : 0]
        );
        $id = Database::connection()->lastInsertId();
        echo "  ADD    {$code} (id={$id})\n";
        return $id;
    }

    function assignToRole($permissionId, $roleCode) {
        $role = Database::fetch("SELECT role_id FROM user_roles WHERE role_code = ?", [$roleCode]);
        if (!$role) {
            echo "  WARN   Role {$roleCode} not found, skipping assignment\n";
            return;
        }
        $exists = Database::fetch(
            "SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?",
            [$role['role_id'], $permissionId]
        );
        if (!$exists) {
            Database::execute(
                "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                [$role['role_id'], $permissionId]
            );
            echo "         -> assigned to {$roleCode}\n";
        }
    }

    function assignToSuperAdmin($permissionId) {
        assignToRole($permissionId, 'SUPER_ADMIN');
    }

    function assignToAdmin($permissionId) {
        assignToRole($permissionId, 'ADMIN');
    }

    function assignToManager($permissionId) {
        assignToRole($permissionId, 'MANAGER');
    }

    function assignToCashier($permissionId) {
        assignToRole($permissionId, 'CASHIER');
    }

    // -------------------------------------------------------
    // TICKET STOCK parent menu
    // -------------------------------------------------------
    $parentId = insertPermission(
        'VIEW_TICKET_STOCK',
        'Ticket Stock',
        'TICKET_STOCK',
        null,
        6,
        'fas fa-boxes',
        null,
        1
    );
    assignToSuperAdmin($parentId);
    assignToAdmin($parentId);
    assignToManager($parentId);
    assignToCashier($parentId);

    echo "\n--- Ticket Stock Menu Items ---\n";

    $variantsId = insertPermission(
        'MANAGE_TICKET_VARIANTS',
        'Variants',
        'TICKET_STOCK',
        $parentId,
        1,
        'fas fa-palette',
        'admin/ticket-stock/variants',
        1
    );
    assignToSuperAdmin($variantsId);
    assignToAdmin($variantsId);
    assignToManager($variantsId);

    $balancesId = insertPermission(
        'VIEW_TICKET_STOCK_BALANCES',
        'Balances',
        'TICKET_STOCK',
        $parentId,
        2,
        'fas fa-warehouse',
        'admin/ticket-stock/balances',
        1
    );
    assignToSuperAdmin($balancesId);
    assignToAdmin($balancesId);
    assignToManager($balancesId);
    assignToCashier($balancesId);

    $requestsId = insertPermission(
        'VIEW_TICKET_STOCK_REQUESTS',
        'Stock Requests',
        'TICKET_STOCK',
        $parentId,
        3,
        'fas fa-file-alt',
        'admin/ticket-stock/requests',
        1
    );
    assignToSuperAdmin($requestsId);
    assignToAdmin($requestsId);
    assignToManager($requestsId);
    assignToCashier($requestsId);

    $movementsId = insertPermission(
        'VIEW_TICKET_STOCK_MOVEMENTS',
        'Movements',
        'TICKET_STOCK',
        $parentId,
        4,
        'fas fa-exchange-alt',
        'admin/ticket-stock/movements',
        1
    );
    assignToSuperAdmin($movementsId);
    assignToAdmin($movementsId);
    assignToManager($movementsId);

    $discrepanciesId = insertPermission(
        'VIEW_TICKET_STOCK_DISCREPANCIES',
        'Discrepancies',
        'TICKET_STOCK',
        $parentId,
        5,
        'fas fa-exclamation-triangle',
        'admin/ticket-stock/discrepancies',
        1
    );
    assignToSuperAdmin($discrepanciesId);
    assignToAdmin($discrepanciesId);
    assignToManager($discrepanciesId);

    echo "\n--- Ticket Stock Action Permissions ---\n";

    $actionPermissions = [
        ['CREATE_TICKET_STOCK_REQUEST', 'Create Stock Request', 10],
        ['APPROVE_TICKET_STOCK_REQUEST', 'Approve Stock Request', 11],
        ['DISPATCH_TICKET_STOCK', 'Dispatch Stock', 12],
        ['RECEIVE_TICKET_STOCK', 'Receive Stock', 13],
        ['ADJUST_TICKET_STOCK', 'Adjust Stock', 14],
        ['RESOLVE_TICKET_STOCK_DISCREPANCY', 'Resolve Discrepancy', 15],
        ['VIEW_ALL_TICKET_STOCK', 'View All Ticket Stock (cross-branch)', 16],
    ];

    foreach ($actionPermissions as $perm) {
        $pid = insertPermission($perm[0], $perm[1], 'TICKET_STOCK', $parentId, $perm[2], null, null, 0);

        if ($perm[0] === 'VIEW_ALL_TICKET_STOCK') {
            assignToSuperAdmin($pid);
            assignToAdmin($pid);
            assignToManager($pid);
            continue;
        }

        // SUPER_ADMIN and ADMIN get all actions
        assignToSuperAdmin($pid);
        assignToAdmin($pid);

        // MANAGER gets most actions except viewing all branches
        if ($perm[0] !== 'VIEW_ALL_TICKET_STOCK') {
            assignToManager($pid);
        }

        // CASHIER can only create and receive requests in their branch
        if (in_array($perm[0], ['CREATE_TICKET_STOCK_REQUEST', 'RECEIVE_TICKET_STOCK'])) {
            assignToCashier($pid);
        }
    }

    echo "\n=== Migration completed successfully! ===\n";
    echo "\nNew sidebar menu items added under 'Ticket Stock':\n";
    echo "  - Variants\n";
    echo "  - Balances\n";
    echo "  - Stock Requests\n";
    echo "  - Movements\n";
    echo "  - Discrepancies\n";

} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "</pre>\n";
