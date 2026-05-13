<?php
/**
 * Migration: Add Phase 1 & Phase 2 sidebar permissions
 * Adds Payment Methods, Bank Accounts, Service Types (Settings children)
 * and Cashier POS, Bank Transfer Confirmations, Customer Charges, Cashier Shifts (Operations)
 *
 * Run: http://localhost/TMS/database/migrations/add_phase1_permissions.php
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

echo "<pre>\n";
echo "=== Phase 1 & 2 Permissions Migration ===\n\n";

try {
    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------
    function insertPermission($code, $name, $module, $parentId, $order, $icon, $url, $isMenu) {
        $existing = Database::fetch("SELECT permission_id FROM permissions WHERE permission_code = ?", [$code]);
        if ($existing) {
            echo "  SKIP   {$code} (already exists)\n";
            return $existing['permission_id'];
        }
        Database::execute(
            "INSERT INTO permissions
                (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
            [$code, $name, $module, $parentId, $order, $icon, $url, $parentId ? 2 : 1, $isMenu]
        );
        $id = Database::connection()->lastInsertId();
        echo "  ADD    {$code} (id={$id})\n";
        return $id;
    }

    function assignToSuperAdmin($permissionId) {
        $role = Database::fetch("SELECT role_id FROM user_roles WHERE role_code = 'SUPER_ADMIN'");
        if (!$role) { echo "  WARN   SUPER_ADMIN role not found\n"; return; }
        $exists = Database::fetch(
            "SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?",
            [$role['role_id'], $permissionId]
        );
        if (!$exists) {
            Database::execute("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                [$role['role_id'], $permissionId]);
            echo "         → assigned to SUPER_ADMIN\n";
        }
    }

    // -------------------------------------------------------
    // GET PARENT IDs
    // -------------------------------------------------------
    $settingsId = Database::fetch("SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_SETTINGS'");
    if (!$settingsId) { echo "ERROR: VIEW_SETTINGS not found.\n"; exit(1); }
    $settingsId = $settingsId['permission_id'];
    echo "Found VIEW_SETTINGS (id={$settingsId})\n\n";

    // -------------------------------------------------------
    // PHASE 1 — Settings Children
    // -------------------------------------------------------
    echo "--- Phase 1: Settings / Lookup Modules ---\n";

    $pmId = insertPermission('VIEW_PAYMENT_METHODS', 'Payment Methods', 'SETTINGS', $settingsId, 10, 'fas fa-credit-card', 'admin/settings/payment-methods', 1);
    assignToSuperAdmin($pmId);

    $baId = insertPermission('VIEW_BANK_ACCOUNTS', 'Bank Accounts', 'SETTINGS', $settingsId, 11, 'fas fa-university', 'admin/settings/bank-accounts', 1);
    assignToSuperAdmin($baId);

    $stId = insertPermission('VIEW_SERVICE_TYPES', 'Service Types', 'SETTINGS', $settingsId, 12, 'fas fa-concierge-bell', 'admin/settings/service-types', 1);
    assignToSuperAdmin($stId);

    echo "\n";

    // -------------------------------------------------------
    // PHASE 2 — Operations Parent + Children
    // -------------------------------------------------------
    echo "--- Phase 2: Operations Modules ---\n";

    // Check if OPERATIONS parent exists
    $opsParent = Database::fetch("SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_OPERATIONS'");
    if (!$opsParent) {
        Database::execute(
            "INSERT INTO permissions
                (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
            ['VIEW_OPERATIONS', 'Operations', 'OPERATIONS', null, 5, 'fas fa-cash-register', null, 1, 1]
        );
        $opsId = Database::connection()->lastInsertId();
        echo "  ADD    VIEW_OPERATIONS parent (id={$opsId})\n";
    } else {
        $opsId = $opsParent['permission_id'];
        echo "  SKIP   VIEW_OPERATIONS (already exists, id={$opsId})\n";
    }
    assignToSuperAdmin($opsId);

    $posId = insertPermission('VIEW_CASHIER_POS', 'Cashier POS', 'OPERATIONS', $opsId, 1, 'fas fa-cash-register', 'admin/pos', 1);
    assignToSuperAdmin($posId);

    $btId = insertPermission('VIEW_BANK_CONFIRMATIONS', 'Bank Confirmations', 'OPERATIONS', $opsId, 2, 'fas fa-check-double', 'admin/bank-confirmations', 1);
    assignToSuperAdmin($btId);

    $chId = insertPermission('VIEW_CUSTOMER_CHARGES', 'Customer Charges', 'OPERATIONS', $opsId, 3, 'fas fa-file-invoice-dollar', 'admin/charges', 1);
    assignToSuperAdmin($chId);

    $shId = insertPermission('VIEW_CASHIER_SHIFTS', 'Cashier Shifts', 'OPERATIONS', $opsId, 4, 'fas fa-clipboard-list', 'admin/shifts', 1);
    assignToSuperAdmin($shId);

    echo "\n=== Migration completed successfully! ===\n";
    echo "\nNew sidebar items added:\n";
    echo "  Settings → Payment Methods   (/admin/settings/payment-methods)\n";
    echo "  Settings → Bank Accounts     (/admin/settings/bank-accounts)\n";
    echo "  Settings → Service Types     (/admin/settings/service-types)\n";
    echo "  Operations → Cashier POS     (/admin/pos)\n";
    echo "  Operations → Bank Confirm.   (/admin/bank-confirmations)\n";
    echo "  Operations → Cust. Charges   (/admin/charges)\n";
    echo "  Operations → Cashier Shifts  (/admin/shifts)\n";

} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "</pre>\n";
