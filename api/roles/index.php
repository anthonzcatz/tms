<?php
require_once __DIR__ . '/../../config/bootstrap.php';

// Set headers
header('Content-Type: application/json');

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Check authentication
    if (!Auth::check()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    // Check authorization (SUPER_ADMIN only)
    if ($_SESSION['user']['role_code'] !== 'SUPER_ADMIN') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden - SUPER_ADMIN only']);
        exit;
    }

    switch ($method) {
        case 'GET':
            $roles = Database::fetchAll("SELECT * FROM user_roles ORDER BY role_code");
            echo json_encode($roles);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['role_code']) || !isset($data['role_name'])) {
                throw new Exception('Missing required fields: role_code and role_name');
            }

            // Validate role_code format (uppercase letters and underscores only)
            if (!preg_match('/^[A-Z_]+$/', $data['role_code'])) {
                throw new Exception('Role code must contain only uppercase letters and underscores');
            }

            // Check if role_code already exists
            $existing = Database::fetch(
                "SELECT role_id FROM user_roles WHERE role_code = :role_code",
                ['role_code' => $data['role_code']]
            );

            if ($existing) {
                throw new Exception('Role code already exists');
            }

            // Insert new role
            $result = Database::execute(
                "INSERT INTO user_roles (role_code, role_name, role_description) VALUES (:role_code, :role_name, :role_description)",
                [
                    'role_code' => $data['role_code'],
                    'role_name' => $data['role_name'],
                    'role_description' => $data['role_description'] ?? null
                ]
            );

            if ($result > 0) {
                echo json_encode(['success' => true, 'message' => 'Role created successfully']);
            } else {
                throw new Exception('Failed to create role');
            }
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['role_id']) || !isset($data['role_code']) || !isset($data['role_name'])) {
                throw new Exception('Missing required fields: role_id, role_code, and role_name');
            }

            // Prevent editing SUPER_ADMIN role
            $role = Database::fetch(
                "SELECT role_code FROM user_roles WHERE role_id = :role_id",
                ['role_id' => (int)$data['role_id']]
            );

            if ($role && $role['role_code'] === 'SUPER_ADMIN') {
                throw new Exception('Cannot edit SUPER_ADMIN role');
            }

            // Validate role_code format
            if (!preg_match('/^[A-Z_]+$/', $data['role_code'])) {
                throw new Exception('Role code must contain only uppercase letters and underscores');
            }

            // Check if role_code already exists for another role
            $existing = Database::fetch(
                "SELECT role_id FROM user_roles WHERE role_code = :role_code AND role_id != :role_id",
                ['role_code' => $data['role_code'], 'role_id' => (int)$data['role_id']]
            );

            if ($existing) {
                throw new Exception('Role code already exists');
            }

            // Update role
            $result = Database::execute(
                "UPDATE user_roles SET role_code = :role_code, role_name = :role_name, role_description = :role_description WHERE role_id = :role_id",
                [
                    'role_code' => $data['role_code'],
                    'role_name' => $data['role_name'],
                    'role_description' => $data['role_description'] ?? null,
                    'role_id' => (int)$data['role_id']
                ]
            );

            if ($result > 0) {
                echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
            } else {
                throw new Exception('Failed to update role');
            }
            break;

        case 'DELETE':
            $roleId = $_GET['id'] ?? null;

            if (!$roleId) {
                throw new Exception('Missing role_id parameter');
            }

            // Prevent deleting SUPER_ADMIN role
            $role = Database::fetch(
                "SELECT role_code FROM user_roles WHERE role_id = :role_id",
                ['role_id' => (int)$roleId]
            );

            if ($role && $role['role_code'] === 'SUPER_ADMIN') {
                throw new Exception('Cannot delete SUPER_ADMIN role');
            }

            // Check if role has assigned users
            $hasUsers = Database::fetch(
                "SELECT user_id FROM user_accounts WHERE role_id = :role_id LIMIT 1",
                ['role_id' => (int)$roleId]
            );

            if ($hasUsers) {
                throw new Exception('Cannot delete role with assigned users');
            }

            // Delete role permissions first
            Database::execute(
                "DELETE FROM role_permissions WHERE role_id = :role_id",
                ['role_id' => (int)$roleId]
            );

            // Delete role
            $result = Database::execute(
                "DELETE FROM user_roles WHERE role_id = :role_id",
                ['role_id' => (int)$roleId]
            );

            if ($result > 0) {
                echo json_encode(['success' => true, 'message' => 'Role deleted successfully']);
            } else {
                throw new Exception('Failed to delete role');
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
