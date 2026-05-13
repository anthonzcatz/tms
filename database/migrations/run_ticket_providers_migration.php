<?php
/**
 * Run Ticket Providers Migration
 * Execute this file to add the ticket providers permission to the database
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

echo "Starting Ticket Providers Migration...\n";

try {
    // Check and add WALLETS parent permission if it doesn't exist
    $walletsPermission = Database::fetch(
        "SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_WALLET_MANAGEMENT'"
    );
    
    if (!$walletsPermission) {
        // Add WALLETS parent permission
        Database::execute(
            "INSERT INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, is_menu_item) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            ['VIEW_WALLET_MANAGEMENT', 'Wallet Management', 'WALLET_MANAGEMENT', NULL, 4, 'fas fa-wallet', 'admin/wallet', 1]
        );
        echo "✓ Added VIEW_WALLET_MANAGEMENT parent permission\n";
        
        $walletsPermission = Database::fetch(
            "SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_WALLET_MANAGEMENT'"
        );
    } else {
        echo "VIEW_WALLET_MANAGEMENT permission already exists\n";
    }
    
    $walletsPermissionId = $walletsPermission['permission_id'];
    
    // Check and add child wallet permissions
    $walletChildren = [
        ['VIEW_WALLET_TRANSACTIONS', 'Wallet Transactions', 'admin/wallet/wallet-transactions', 1],
        ['VIEW_PROVIDER_WALLETS', 'Provider Wallets', 'admin/wallet/provider-wallets', 2]
    ];
    
    foreach ($walletChildren as $child) {
        $existing = Database::fetch(
            "SELECT permission_id FROM permissions WHERE permission_code = ?",
            [$child[0]]
        );
        
        if (!$existing) {
            Database::execute(
                "INSERT INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, is_menu_item) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$child[0], $child[1], 'WALLET_MANAGEMENT', $walletsPermissionId, $child[3], 'fas fa-wallet', $child[2], 1]
            );
            echo "✓ Added {$child[0]} permission\n";
        } else {
            echo "{$child[0]} already exists\n";
        }
    }
    
    // Check if VIEW_TICKET_PROVIDERS already exists
    $existing = Database::fetch(
        "SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_TICKET_PROVIDERS'"
    );
    
    if ($existing) {
        echo "VIEW_TICKET_PROVIDERS permission already exists. Skipping insertion.\n";
    } else {
        // Insert VIEW_TICKET_PROVIDERS permission
        Database::execute(
            "INSERT INTO permissions (
                permission_code, 
                permission_name, 
                module_name, 
                parent_permission_id, 
                menu_order, 
                menu_icon, 
                menu_url, 
                is_menu_item
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                'VIEW_TICKET_PROVIDERS',
                'Ticket Providers',
                'WALLET_MANAGEMENT',
                $walletsPermissionId,
                3,
                'fas fa-plane',
                'admin/wallet/ticket-providers',
                1
            ]
        );
        echo "✓ Added VIEW_TICKET_PROVIDERS permission\n";
    }
    
    // Get the permission ID
    $ticketProvidersPermission = Database::fetch(
        "SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_TICKET_PROVIDERS'"
    );
    $ticketProvidersPermissionId = $ticketProvidersPermission['permission_id'];
    
    // Get SUPER_ADMIN role ID
    $superAdminRole = Database::fetch(
        "SELECT role_id FROM user_roles WHERE role_code = 'SUPER_ADMIN'"
    );
    
    if ($superAdminRole) {
        // Check if already assigned
        $existingAssignment = Database::fetch(
            "SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?",
            [$superAdminRole['role_id'], $ticketProvidersPermissionId]
        );
        
        if (!$existingAssignment) {
            Database::execute(
                "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                [$superAdminRole['role_id'], $ticketProvidersPermissionId]
            );
            echo "✓ Assigned VIEW_TICKET_PROVIDERS to SUPER_ADMIN role\n";
        } else {
            echo "VIEW_TICKET_PROVIDERS already assigned to SUPER_ADMIN role\n";
        }
    }
    
    // Add CRUD permissions
    $crudPermissions = [
        ['CREATE_TICKET_PROVIDER', 'Create Ticket Provider', 4],
        ['UPDATE_TICKET_PROVIDER', 'Update Ticket Provider', 5],
        ['DELETE_TICKET_PROVIDER', 'Delete Ticket Provider', 6]
    ];
    
    foreach ($crudPermissions as $perm) {
        $existing = Database::fetch(
            "SELECT permission_id FROM permissions WHERE permission_code = ?",
            [$perm[0]]
        );
        
        if (!$existing) {
            Database::execute(
                "INSERT INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, is_menu_item) VALUES (?, ?, ?, ?, ?, ?)",
                [$perm[0], $perm[1], 'WALLET_MANAGEMENT', $walletsPermissionId, $perm[2], 0]
            );
            echo "✓ Added {$perm[0]} permission\n";
            
            // Assign to SUPER_ADMIN
            if ($superAdminRole) {
                $newPermId = Database::connection()->lastInsertId();
                Database::execute(
                    "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                    [$superAdminRole['role_id'], $newPermId]
                );
            }
        } else {
            echo "{$perm[0]} already exists\n";
        }
    }
    
    echo "\nMigration completed successfully!\n";
    echo "You can now access Ticket Providers at: /admin/wallet/ticket-providers\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
