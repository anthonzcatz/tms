<?php
/**
 * Run Branches Permission Migration
 * Execute this file to add the branch management permission to the database
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

echo "Starting Branches Permission Migration...\n";

try {
    // Check if SETTINGS parent permission exists
    $settingsPermission = Database::fetch(
        "SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_SETTINGS'"
    );
    
    if (!$settingsPermission) {
        echo "ERROR: VIEW_SETTINGS permission not found. Please ensure settings permissions exist.\n";
        exit(1);
    }
    
    $settingsPermissionId = $settingsPermission['permission_id'];
    
    // Check if VIEW_BRANCHES already exists
    $existing = Database::fetch(
        "SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_BRANCHES'"
    );
    
    if ($existing) {
        echo "VIEW_BRANCHES permission already exists. Skipping insertion.\n";
    } else {
        // Insert VIEW_BRANCHES permission as a child of SETTINGS
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
                'VIEW_BRANCHES',
                'Branches',
                'SETTINGS',
                $settingsPermissionId,
                2,
                'fas fa-building',
                'admin/settings/branches',
                1
            ]
        );
        echo "✓ Added VIEW_BRANCHES permission\n";
    }
    
    // Get the permission ID
    $branchesPermission = Database::fetch(
        "SELECT permission_id FROM permissions WHERE permission_code = 'VIEW_BRANCHES'"
    );
    $branchesPermissionId = $branchesPermission['permission_id'];
    
    // Get SUPER_ADMIN role ID
    $superAdminRole = Database::fetch(
        "SELECT role_id FROM user_roles WHERE role_code = 'SUPER_ADMIN'"
    );
    
    if ($superAdminRole) {
        // Check if already assigned
        $existingAssignment = Database::fetch(
            "SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?",
            [$superAdminRole['role_id'], $branchesPermissionId]
        );
        
        if (!$existingAssignment) {
            Database::execute(
                "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                [$superAdminRole['role_id'], $branchesPermissionId]
            );
            echo "✓ Assigned VIEW_BRANCHES to SUPER_ADMIN role\n";
        } else {
            echo "VIEW_BRANCHES already assigned to SUPER_ADMIN role\n";
        }
    }
    
    // Add CRUD permissions
    $crudPermissions = [
        ['CREATE_BRANCH', 'Create Branch', 3],
        ['UPDATE_BRANCH', 'Update Branch', 4],
        ['DELETE_BRANCH', 'Delete Branch', 5]
    ];
    
    foreach ($crudPermissions as $perm) {
        $existing = Database::fetch(
            "SELECT permission_id FROM permissions WHERE permission_code = ?",
            [$perm[0]]
        );
        
        if (!$existing) {
            Database::execute(
                "INSERT INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, is_menu_item) VALUES (?, ?, ?, ?, ?, ?)",
                [$perm[0], $perm[1], 'SETTINGS', $settingsPermissionId, $perm[2], 0]
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
    echo "You can now access Branch Management at: /admin/settings/branches\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
