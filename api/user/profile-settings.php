<?php
/**
 * API Endpoint: Update User Profile Settings
 * Handles update of phone number, gender, and address
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

// Get current user
$user = Auth::user();
if (!$user || !isset($user['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$userId = $user['user_id'];

// Check if action is set
if (!isset($_POST['action']) || $_POST['action'] !== 'update_profile') {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Get form data
$phone = trim($_POST['phone'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$streetAddress = trim($_POST['street_address'] ?? '');
$provinceCode = trim($_POST['province_code'] ?? '');
$cityCode = trim($_POST['city_code'] ?? '');
$barangayCode = trim($_POST['barangay_code'] ?? '');
$permanentAddress = trim($_POST['permanent_address'] ?? '');

// Validate inputs
if (empty($phone)) {
    echo json_encode(['success' => false, 'error' => 'Phone number is required']);
    exit;
}

if (empty($gender)) {
    echo json_encode(['success' => false, 'error' => 'Gender is required']);
    exit;
}

// Street address is optional
// if (empty($streetAddress)) {
//     echo json_encode(['success' => false, 'error' => 'Street address is required']);
//     exit;
// }

if (empty($provinceCode) || empty($cityCode) || empty($barangayCode)) {
    echo json_encode(['success' => false, 'error' => 'Province, city, and barangay are required']);
    exit;
}

if (empty($permanentAddress)) {
    echo json_encode(['success' => false, 'error' => 'Permanent address is required']);
    exit;
}

// Get emp_id from user_accounts
$userData = Database::fetch("SELECT emp_id FROM user_accounts WHERE user_id = :user_id", ['user_id' => $userId]);
$empId = $userData['emp_id'] ?? null;

if (!$empId) {
    echo json_encode(['success' => false, 'error' => 'Employee record not found for this user']);
    exit;
}

// Update employees table (landmark is included in permanent_address)
try {
    error_log("Updating profile for emp_id: " . $empId);
    error_log("Phone: " . $phone);
    error_log("Gender: " . $gender);
    error_log("Street Address: " . $streetAddress);
    error_log("Province Code: " . $provinceCode);
    error_log("City Code: " . $cityCode);
    error_log("Barangay Code: " . $barangayCode);
    error_log("Permanent Address: " . $permanentAddress);
    
    Database::execute(
        "UPDATE employees SET b_cont_no = :phone, b_sex = :gender, emp_street_address = :street_address, emp_province_code = :province_code, emp_city_code = :city_code, emp_barangay_code = :barangay_code, b_permanent_address = :permanent_address WHERE emp_id = :emp_id",
        [
            'phone' => $phone,
            'gender' => $gender,
            'street_address' => $streetAddress,
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
            'barangay_code' => $barangayCode,
            'permanent_address' => $permanentAddress,
            'emp_id' => $empId
        ]
    );
    
    error_log("Profile update successful for emp_id: " . $empId);
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile settings updated successfully'
    ]);
} catch (Exception $e) {
    error_log("Profile update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to update profile: ' . $e->getMessage()]);
}
