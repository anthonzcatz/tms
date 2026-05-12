<?php
/**
 * Users API Endpoint
 * Handles CRUD operations for user management
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check permission - SUPER_ADMIN or users with MANAGE_USERS permission
$user = Auth::user();
$canManageUsers = ($user['role_code'] === 'SUPER_ADMIN');

// Check MANAGE_USERS permission if not SUPER_ADMIN
if (!$canManageUsers) {
    $canManageUsers = Auth::can('MANAGE_USERS');
}

if (!$canManageUsers) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied. You need MANAGE_USERS permission to access this resource.']);
    exit;
}

// CSRF protection for POST/PUT/DELETE requests
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_token'] ?? $_GET['_token'] ?? null;
    if (!SecurityHelper::validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        case 'PUT':
            handlePut();
            break;
        case 'DELETE':
            handleDelete();
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Users API Error: " . $e->getMessage());
    error_log("Users API Trace: " . $e->getTraceAsString());
    error_log("Users API File: " . $e->getFile() . " Line: " . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error', 'debug' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}

/**
 * Handle GET requests - list users or get single user
 */
function handleGet() {
    $userId = $_GET['id'] ?? null;
    $roleId = $_GET['role_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $search = $_GET['search'] ?? '';
    
    if ($userId) {
        // Get single user
        $sql = "
            SELECT 
                ua.user_id,
                ua.user_code,
                ua.username,
                ua.email,
                ua.fullname,
                ua.profile_image,
                ua.branch_id,
                ua.status,
                ua.is_time_restricted,
                ua.allowed_login_start,
                ua.allowed_login_end,
                ua.allowed_days,
                ua.created_at,
                ua.updated_at,
                ua.last_login_at,
                ur.role_id,
                ur.role_name,
                ur.role_code
            FROM user_accounts ua
            LEFT JOIN user_roles ur ON ua.role_id = ur.role_id
            WHERE ua.user_id = :user_id
        ";
        
        $user = Database::fetch($sql, ['user_id' => (int)$userId]);
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        
        echo json_encode(['success' => true, 'data' => $user]);
        return;
    }
    
    // List users with filters
    $sql = "
        SELECT 
            ua.user_id,
            ua.user_code,
            ua.username,
            ua.email,
            ua.fullname,
            ua.profile_image,
            ua.branch_id,
            ua.status,
            ua.is_time_restricted,
            ua.allowed_login_start,
            ua.allowed_login_end,
            ua.allowed_days,
            ua.created_at,
            ua.updated_at,
            ua.last_login_at,
            ur.role_id,
            ur.role_name,
            ur.role_code
        FROM user_accounts ua
        LEFT JOIN user_roles ur ON ua.role_id = ur.role_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($roleId) {
        $sql .= " AND ua.role_id = :role_id";
        $params['role_id'] = (int)$roleId;
    }
    
    if ($status !== null && $status !== '') {
        $sql .= " AND ua.status = :status";
        $params['status'] = $status === '1' ? 'active' : 'inactive';
    }
    
    if ($search) {
        $sql .= " AND (
            ua.username LIKE :search OR
            ua.email LIKE :search OR
            ua.fullname LIKE :search
        )";
        $params['search'] = '%' . $search . '%';
    }
    
    $sql .= " ORDER BY ua.created_at DESC";
    
    $users = Database::fetchAll($sql, $params);
    
    echo json_encode(['success' => true, 'data' => $users]);
}

/**
 * Handle POST requests - create new user
 */
function handlePost() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields (email is now optional)
    if (empty($data['username']) || empty($data['fullname'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username and full name are required']);
        return;
    }
    
    if (empty($data['role_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Role is required']);
        return;
    }
    
    if (empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Password is required for new users']);
        return;
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email format']);
        return;
    }
    
    // Check for duplicate username
    $existing = Database::fetch(
        "SELECT user_id FROM user_accounts WHERE username = :username",
        ['username' => $data['username']]
    );
    
    if ($existing) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username already exists']);
        return;
    }
    
    // Check for duplicate email
    $existing = Database::fetch(
        "SELECT user_id FROM user_accounts WHERE email = :email",
        ['email' => $data['email']]
    );
    
    if ($existing) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email already exists']);
        return;
    }
    
    // Generate user code (USR-XXXX format)
    $lastUser = Database::fetch(
        "SELECT user_code FROM user_accounts ORDER BY user_id DESC LIMIT 1"
    );
    
    $userCode = 'USR-0001';
    if ($lastUser && $lastUser['user_code']) {
        $lastNum = (int)preg_replace('/[^0-9]/', '', $lastUser['user_code']);
        $newNum = $lastNum + 1;
        $userCode = 'USR-' . str_pad($newNum, 4, '0', STR_PAD_LEFT);
    }
    
    // Hash password
    $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Handle profile image
    $profileImagePath = null;
    if (!empty($data['profile_image'])) {
        $profileImagePath = saveProfileImage($data['profile_image'], $userCode);
    }
    
    // Insert user
    Database::execute(
        "INSERT INTO user_accounts 
         (user_code, username, password_hash, email, fullname, role_id, branch_id, profile_image, status, is_time_restricted, allowed_login_start, allowed_login_end, allowed_days, created_at, updated_at) 
         VALUES (:user_code, :username, :password_hash, :email, :fullname, :role_id, :branch_id, :profile_image, :status, :is_time_restricted, :allowed_login_start, :allowed_login_end, :allowed_days, NOW(), NOW())",
        [
            'user_code' => $userCode,
            'username' => $data['username'],
            'password_hash' => $passwordHash,
            'email' => $data['email'],
            'fullname' => $data['fullname'],
            'role_id' => (int)$data['role_id'],
            'branch_id' => isset($data['branch_id']) ? ($data['branch_id'] ? (int)$data['branch_id'] : null) : null,
            'profile_image' => $profileImagePath,
            'status' => isset($data['status']) ? $data['status'] : 'active',
            'is_time_restricted' => isset($data['is_time_restricted']) ? (int)$data['is_time_restricted'] : 0,
            'allowed_login_start' => isset($data['allowed_login_start']) ? $data['allowed_login_start'] : null,
            'allowed_login_end' => isset($data['allowed_login_end']) ? $data['allowed_login_end'] : null,
            'allowed_days' => isset($data['allowed_days']) ? $data['allowed_days'] : null
        ]
    );
    
    $userId = Database::lastInsertId();
    
    // Log activity
    logActivity($userId, 'CREATE', "Created user: {$data['username']}");
    
    echo json_encode([
        'success' => true,
        'message' => 'User created successfully',
        'data' => ['user_id' => $userId]
    ]);
}

/**
 * Handle PUT requests - update user
 */
function handlePut() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $userId = $data['user_id'] ?? null;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        return;
    }
    
    // Check if user exists
    $existing = Database::fetch(
        "SELECT ua.*, ur.role_code 
         FROM user_accounts ua
         LEFT JOIN user_roles ur ON ua.role_id = ur.role_id
         WHERE ua.user_id = :user_id",
        ['user_id' => (int)$userId]
    );
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        return;
    }
    
    // Prevent editing SUPER_ADMIN users unless current user is SUPER_ADMIN
    if ($existing['role_code'] === 'SUPER_ADMIN') {
        $currentUser = Auth::user();
        if ($currentUser['role_code'] !== 'SUPER_ADMIN') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Cannot edit super admin accounts']);
            return;
        }
    }
    
    // Check for duplicate username (excluding current user)
    if (isset($data['username']) && $data['username'] !== $existing['username']) {
        $duplicate = Database::fetch(
            "SELECT user_id FROM user_accounts WHERE username = :username AND user_id != :user_id",
            ['username' => $data['username'], 'user_id' => (int)$userId]
        );
        
        if ($duplicate) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username already exists']);
            return;
        }
    }
    
    // Check for duplicate email (excluding current user)
    if (isset($data['email']) && $data['email'] !== $existing['email']) {
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid email format']);
            return;
        }
        
        $duplicate = Database::fetch(
            "SELECT user_id FROM user_accounts WHERE email = :email AND user_id != :user_id",
            ['email' => $data['email'], 'user_id' => (int)$userId]
        );
        
        if ($duplicate) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Email already exists']);
            return;
        }
    }
    
    // Build update query
    $updateFields = [];
    $params = ['user_id' => (int)$userId];
    
    if (isset($data['username'])) {
        $updateFields[] = "username = :username";
        $params['username'] = $data['username'];
    }
    
    if (isset($data['branch_id'])) {
        $updateFields[] = "branch_id = :branch_id";
        $params['branch_id'] = $data['branch_id'] ? (int)$data['branch_id'] : null;
    }
    
    if (isset($data['email'])) {
        $updateFields[] = "email = :email";
        $params['email'] = $data['email'];
    }
    
    if (isset($data['fullname'])) {
        $updateFields[] = "fullname = :fullname";
        $params['fullname'] = $data['fullname'];
    }
    
    if (isset($data['role_id'])) {
        // Prevent changing role of super admin
        if ($existing['role_code'] === 'SUPER_ADMIN' && $data['role_id'] != $existing['role_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Cannot change super admin role']);
            return;
        }
        
        $updateFields[] = "role_id = :role_id";
        $params['role_id'] = (int)$data['role_id'];
    }
    
    if (isset($data['status'])) {
        // Prevent deactivating super admin
        if ($existing['role_code'] === 'SUPER_ADMIN' && $data['status'] !== 'active') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Cannot deactivate super admin account']);
            return;
        }
        
        $updateFields[] = "status = :status";
        $params['status'] = $data['status'];
    }
    
    if (isset($data['is_time_restricted'])) {
        $updateFields[] = "is_time_restricted = :is_time_restricted";
        $params['is_time_restricted'] = (int)$data['is_time_restricted'];
    }
    
    if (isset($data['allowed_login_start'])) {
        $updateFields[] = "allowed_login_start = :allowed_login_start";
        $params['allowed_login_start'] = $data['allowed_login_start'];
    }
    
    if (isset($data['allowed_login_end'])) {
        $updateFields[] = "allowed_login_end = :allowed_login_end";
        $params['allowed_login_end'] = $data['allowed_login_end'];
    }
    
    if (isset($data['allowed_days'])) {
        $updateFields[] = "allowed_days = :allowed_days";
        $params['allowed_days'] = $data['allowed_days'];
    }
    
    // Handle password update
    if (!empty($data['password'])) {
        $updateFields[] = "password_hash = :password_hash";
        $params['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    
    // Handle profile image update
    if (!empty($data['profile_image'])) {
        // Only process if it's a base64 string (new image)
        if (strpos($data['profile_image'], 'data:image') === 0) {
            $profileImagePath = saveProfileImage($data['profile_image'], $existing['user_code']);
            if ($profileImagePath) {
                // Delete old image file if exists
                if ($existing['profile_image']) {
                    $oldImagePath = dirname(dirname(__DIR__)) . $existing['profile_image'];
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                $updateFields[] = "profile_image = :profile_image";
                $params['profile_image'] = $profileImagePath;
            }
        }
    }
    
    // Handle profile image removal
    if (isset($data['remove_profile_image']) && $data['remove_profile_image'] === true) {
        // Delete old image file if exists
        if ($existing['profile_image']) {
            $oldImagePath = dirname(dirname(__DIR__)) . $existing['profile_image'];
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
        }
        $updateFields[] = "profile_image = NULL";
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        return;
    }
    
    $updateFields[] = "updated_at = NOW()";
    
    $sql = "UPDATE user_accounts SET " . implode(", ", $updateFields) . " WHERE user_id = :user_id";
    
    Database::execute($sql, $params);
    
    // Log activity
    $currentUser = Auth::user();
    $actingUserId = $currentUser ? $currentUser['user_id'] : null;
    logActivity($userId, 'UPDATE', "Updated user: {$existing['username']}");
    
    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully'
    ]);
}

/**
 * Handle DELETE requests - delete user
 */
function handleDelete() {
    $userId = $_GET['id'] ?? null;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        return;
    }
    
    // Check if user exists
    $existing = Database::fetch(
        "SELECT ua.*, ur.role_code 
         FROM user_accounts ua
         LEFT JOIN user_roles ur ON ua.role_id = ur.role_id
         WHERE ua.user_id = :user_id",
        ['user_id' => (int)$userId]
    );
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        return;
    }
    
    // Prevent deleting super admin
    if ($existing['role_code'] === 'SUPER_ADMIN') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Cannot delete super admin accounts']);
        return;
    }
    
    // Prevent self-deletion
    $currentUser = Auth::user();
    if ($currentUser['user_id'] == $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Cannot delete your own account']);
        return;
    }
    
    // Delete user sessions first
    Database::execute(
        "DELETE FROM user_sessions WHERE user_id = :user_id",
        ['user_id' => (int)$userId]
    );
    
    // Delete user
    Database::execute(
        "DELETE FROM user_accounts WHERE user_id = :user_id",
        ['user_id' => (int)$userId]
    );
    
    // Log activity
    $currentUser = Auth::user();
    $actingUserId = $currentUser ? $currentUser['user_id'] : null;
    logActivity($userId, 'DELETE', "Deleted user: {$existing['username']}");
    
    echo json_encode([
        'success' => true,
        'message' => 'User deleted successfully'
    ]);
}

/**
 * Log activity
 */
function logActivity($userId, $action, $description) {
    try {
        $currentUser = Auth::user();
        $actingUserId = $currentUser ? $currentUser['user_id'] : null;
        Database::execute(
            "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at) 
             VALUES (:user_id, :action, :description, :ip_address, :user_agent, NOW())",
            [
                'user_id' => $actingUserId,
                'action' => $action,
                'description' => $description,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]
        );
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Save profile image from base64 to file
 */
function saveProfileImage($base64Image, $userCode) {
    try {
        // Extract base64 data
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
            $imageType = $matches[1];
            $base64Data = substr($base64Image, strpos($base64Image, ',') + 1);
            $imageData = base64_decode($base64Data);
            
            if ($imageData === false) {
                error_log("Failed to decode base64 image");
                return null;
            }
            
            // Create upload directory if it doesn't exist (api/images/users folder)
            $uploadDir = dirname(dirname(__DIR__)) . '/images/users/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $filename = $userCode . '_' . time() . '.' . $imageType;
            $filepath = $uploadDir . $filename;
            
            // Save image
            if (file_put_contents($filepath, $imageData)) {
                // Return relative path for database (from project root)
                return '/api/images/users/' . $filename;
            } else {
                error_log("Failed to save profile image to file");
                return null;
            }
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error saving profile image: " . $e->getMessage());
        return null;
    }
}
