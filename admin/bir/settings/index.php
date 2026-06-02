<?php
/**
 * BIR Settings Controller
 * Manage BIR accreditation and system settings
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/_guard.php';

$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';

// Only admin and super_admin can access
$allowedRoles = ['SUPER_ADMIN', 'ADMIN'];
if (!in_array($userRoleCode, $allowedRoles)) {
    header('Location: ' . BASE_URL . '/admin/bir/');
    exit;
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $updateData = [
            'company_tin' => $_POST['company_tin'] ?? null,
            'bir_accreditation_number' => $_POST['bir_accreditation_number'] ?? null,
            'bir_accreditation_expiry' => $_POST['bir_accreditation_expiry'] ?? null,
            'bir_permit_number' => $_POST['bir_permit_number'] ?? null,
            'bir_validity_from' => $_POST['bir_validity_from'] ?? null,
            'bir_validity_to' => $_POST['bir_validity_to'] ?? null,
            'bir_min' => $_POST['bir_min'] ?? null,
            'bir_machine_serial' => $_POST['bir_machine_serial'] ?? null,
            'bir_vat_rate' => $_POST['bir_vat_rate'] ?? 12.00,
            'bir_auto_or_assignment' => isset($_POST['bir_auto_or_assignment']) ? 1 : 0
        ];

        $fields = [];
        $values = [];
        foreach ($updateData as $key => $value) {
            $fields[] = "$key = :$key";
            $values[$key] = $value;
        }

        $sql = "UPDATE system_settings SET " . implode(', ', $fields) . " WHERE setting_id = 1";
        Database::execute($sql, $values);

        header('Location: ' . BASE_URL . '/admin/bir/settings/?success=' . urlencode('BIR settings updated successfully!'));
        exit;
    } catch (Exception $e) {
        header('Location: ' . BASE_URL . '/admin/bir/settings/?error=' . urlencode('Error updating settings: ' . $e->getMessage()));
        exit;
    }
}

// Check for success/error messages from redirect
$message = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Fetch current settings
$birSettings = Database::fetch(
    "SELECT * FROM system_settings WHERE setting_id = 1"
);

$viewData = [
    'birSettings' => $birSettings,
    'message' => $message,
    'error' => $error,
    'userRoleCode' => $userRoleCode
];

extract($viewData);

include __DIR__ . '/views/index.php';
