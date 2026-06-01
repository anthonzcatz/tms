<?php
/**
 * BIR POS Machines Controller
 * Manage POS machine accreditation details
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(__DIR__) . '/_guard.php';

Auth::requireLogin();

$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';

$allowedRoles = ['SUPER_ADMIN', 'ADMIN', 'MANAGER'];
if (!in_array($userRoleCode, $allowedRoles)) {
    header('Location: ' . BASE_URL . '/admin/bir/');
    exit;
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_machine') {
            Database::execute(
                "INSERT INTO bir_pos_machines (branch_id, machine_name, serial_number, accreditation_number, 
                 accreditation_expiry, machine_type, min, permit_number, validity_from, validity_to, status, created_by) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)",
                [
                    $_POST['branch_id'], $_POST['machine_name'], $_POST['serial_number'],
                    $_POST['accreditation_number'], $_POST['accreditation_expiry'],
                    $_POST['machine_type'], $_POST['min'], $_POST['permit_number'],
                    $_POST['validity_from'], $_POST['validity_to'], $user['user_id']
                ]
            );
            $message = 'POS Machine added successfully!';
        }
        
        if ($_POST['action'] === 'update_machine') {
            Database::execute(
                "UPDATE bir_pos_machines 
                 SET branch_id = ?, machine_name = ?, serial_number = ?, accreditation_number = ?,
                     accreditation_expiry = ?, machine_type = ?, min = ?, permit_number = ?,
                     validity_from = ?, validity_to = ?, status = ?
                 WHERE machine_id = ?",
                [
                    $_POST['branch_id'], $_POST['machine_name'], $_POST['serial_number'],
                    $_POST['accreditation_number'], $_POST['accreditation_expiry'],
                    $_POST['machine_type'], $_POST['min'], $_POST['permit_number'],
                    $_POST['validity_from'], $_POST['validity_to'], $_POST['status'],
                    $_POST['machine_id']
                ]
            );
            $message = 'POS Machine updated successfully!';
        }
        
        if ($_POST['action'] === 'delete_machine') {
            Database::execute("DELETE FROM bir_pos_machines WHERE machine_id = ?", [$_POST['machine_id']]);
            $message = 'POS Machine deleted successfully!';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Fetch machines
$machines = Database::fetchAll(
    "SELECT m.*, bb.branch_name, u.fullname as created_by_name
     FROM bir_pos_machines m
     LEFT JOIN business_branches bb ON m.branch_id = bb.branch_id
     LEFT JOIN user_accounts u ON m.created_by = u.user_id
     ORDER BY m.status = 'active' DESC, bb.branch_name ASC"
);

// Check for expiring accreditations
$expiringMachines = array_filter($machines, function($m) {
    if (empty($m['accreditation_expiry'])) return false;
    $expiry = new DateTime($m['accreditation_expiry']);
    $today = new DateTime();
    $daysUntilExpiry = $today->diff($expiry)->days;
    return $expiry > $today && $daysUntilExpiry <= 30;
});

$branches = Database::fetchAll(
    "SELECT branch_id, branch_name FROM business_branches WHERE status = 'active' ORDER BY branch_name"
);

$viewData = [
    'machines' => $machines,
    'expiringMachines' => $expiringMachines,
    'branches' => $branches,
    'message' => $message,
    'error' => $error,
    'userRoleCode' => $userRoleCode
];

extract($viewData);

include __DIR__ . '/views/index.php';
