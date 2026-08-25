<?php
/**
 * BIR POS Machines Controller
 * Manage POS machine accreditation details
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/PosAccess.php';
require_once dirname(__DIR__) . '/_guard.php';

$user = Auth::user();
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
$allowedBranchIds = PosAccess::allowedBranchIds($user);

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
            PosAccess::assertBranchAccess($user, (int) ($_POST['branch_id'] ?? 0));
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
            header('Location: ' . BASE_URL . '/admin/bir/machines/?success=' . urlencode('POS Machine added successfully!'));
            exit;
        }

        if ($_POST['action'] === 'update_machine') {
            $currentMachine = Database::fetch(
                "SELECT branch_id FROM bir_pos_machines WHERE machine_id = ?",
                [$_POST['machine_id']]
            );
            if (!$currentMachine) {
                throw new RuntimeException('POS machine not found.');
            }
            PosAccess::assertBranchAccess($user, (int) $currentMachine['branch_id']);
            PosAccess::assertBranchAccess($user, (int) ($_POST['branch_id'] ?? 0));
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
            header('Location: ' . BASE_URL . '/admin/bir/machines/?success=' . urlencode('POS Machine updated successfully!'));
            exit;
        }

        if ($_POST['action'] === 'delete_machine') {
            $currentMachine = Database::fetch(
                "SELECT branch_id FROM bir_pos_machines WHERE machine_id = ?",
                [$_POST['machine_id']]
            );
            if (!$currentMachine) {
                throw new RuntimeException('POS machine not found.');
            }
            PosAccess::assertBranchAccess($user, (int) $currentMachine['branch_id']);
            Database::execute("DELETE FROM bir_pos_machines WHERE machine_id = ?", [$_POST['machine_id']]);
            header('Location: ' . BASE_URL . '/admin/bir/machines/?success=' . urlencode('POS Machine deleted successfully!'));
            exit;
        }
    } catch (Exception $e) {
        header('Location: ' . BASE_URL . '/admin/bir/machines/?error=' . urlencode('Error: ' . $e->getMessage()));
        exit;
    }
}

// Check for success/error messages from redirect
$message = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Fetch machines
$machineBranchFilter = '';
$machineParams = [];
if ($allowedBranchIds !== null) {
    if (!$allowedBranchIds) {
        $machineBranchFilter = 'WHERE 1 = 0';
    } else {
        $machineBranchFilter = 'WHERE m.branch_id IN (' . implode(',', array_fill(0, count($allowedBranchIds), '?')) . ')';
        $machineParams = $allowedBranchIds;
    }
}
$machines = Database::fetchAll(
    "SELECT m.*, bb.branch_name, u.username as created_by_name
     FROM bir_pos_machines m
     LEFT JOIN business_branches bb ON m.branch_id = bb.branch_id
     LEFT JOIN user_accounts u ON m.created_by = u.user_id
     {$machineBranchFilter}
     ORDER BY m.status = 'active' DESC, bb.branch_name ASC",
    $machineParams
);

// Check for expiring accreditations
$expiringMachines = array_filter($machines, function($m) {
    if (empty($m['accreditation_expiry'])) return false;
    $expiry = new DateTime($m['accreditation_expiry']);
    $today = new DateTime();
    $daysUntilExpiry = $today->diff($expiry)->days;
    return $expiry > $today && $daysUntilExpiry <= 30;
});

$branchWhere = ["status = 'active'"];
$branchParams = [];
PosAccess::applyBranchScope($branchWhere, $branchParams, 'branch_id', $user, 'bir_machine_dropdown_branch');
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name
     FROM business_branches
     WHERE " . implode(' AND ', $branchWhere) . "
     ORDER BY branch_name",
    $branchParams
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
