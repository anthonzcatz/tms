<?php
/**
 * BIR OR Numbers Controller
 * Manage Official Receipt numbering, void tracking, and series management
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/_guard.php';

$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';
$userBranchId = $user['branch_id'] ?? null;

// Check permission
$allowedRoles = ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'ACCOUNTANT'];
if (!in_array($userRoleCode, $allowedRoles)) {
    header('Location: ' . BASE_URL . '/admin/bir/');
    exit;
}

$message = '';
$error = '';

// Handle form submission for creating new OR series
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'create_series') {
            $branchId = $_POST['branch_id'] ?? null;
            $year = $_POST['year'] ?? date('Y');
            $startNumber = $_POST['start_number'] ?? 1;
            $endNumber = $_POST['end_number'] ?? 999999;

            $seriesCode = sprintf('%03d-%d', $branchId, $year);

            // Check if series already exists
            $existing = Database::fetch(
                "SELECT series_id FROM bir_or_series WHERE branch_id = ? AND year = ?",
                [$branchId, $year]
            );

            if ($existing) {
                header('Location: ' . BASE_URL . '/admin/bir/or-numbers/?error=' . urlencode('OR Series already exists for this branch and year.'));
                exit;
            } else {
                Database::execute(
                    "INSERT INTO bir_or_series (branch_id, year, series_code, start_number, current_number, end_number, status, created_by)
                     VALUES (?, ?, ?, ?, 0, ?, 'active', ?)",
                    [$branchId, $year, $seriesCode, $startNumber, $endNumber, $user['user_id']]
                );
                header('Location: ' . BASE_URL . '/admin/bir/or-numbers/?success=' . urlencode('OR Series created successfully!'));
                exit;
            }
        }

        if ($_POST['action'] === 'void_or') {
            $orId = $_POST['or_id'] ?? null;
            $reason = $_POST['void_reason'] ?? '';

            if (empty($reason)) {
                header('Location: ' . BASE_URL . '/admin/bir/or-numbers/?error=' . urlencode('Void reason is required.'));
                exit;
            } else {
                Database::execute(
                    "UPDATE bir_or_numbers
                     SET status = 'void', voided_at = NOW(), voided_by = ?, void_reason = ?
                     WHERE or_id = ? AND status = 'issued'",
                    [$user['user_id'], $reason, $orId]
                );
                header('Location: ' . BASE_URL . '/admin/bir/or-numbers/?success=' . urlencode('OR Number voided successfully!'));
                exit;
            }
        }
    } catch (Exception $e) {
        header('Location: ' . BASE_URL . '/admin/bir/or-numbers/?error=' . urlencode('Error: ' . $e->getMessage()));
        exit;
    }
}

// Check for success/error messages from redirect
$message = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Fetch OR numbers with filters
$branchFilter = $_GET['branch_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

$query = "
    SELECT orn.*, bb.branch_name, u.username as voided_by_name, po.order_code
    FROM bir_or_numbers orn
    LEFT JOIN business_branches bb ON orn.branch_id = bb.branch_id
    LEFT JOIN user_accounts u ON orn.voided_by = u.user_id
    LEFT JOIN pos_orders po ON orn.order_id = po.order_id
    WHERE 1=1
";
$params = [];

if ($branchFilter) {
    $query .= " AND orn.branch_id = ?";
    $params[] = $branchFilter;
}

if ($statusFilter) {
    $query .= " AND orn.status = ?";
    $params[] = $statusFilter;
}

if ($dateFrom) {
    $query .= " AND DATE(orn.issued_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $query .= " AND DATE(orn.issued_at) <= ?";
    $params[] = $dateTo;
}

if ($search) {
    $query .= " AND (orn.or_full_number LIKE ? OR po.order_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY orn.issued_at DESC LIMIT 100";

$orNumbers = Database::fetchAll($query, $params);

// Fetch OR series
$orSeries = Database::fetchAll(
    "SELECT s.*, bb.branch_name, u.username as created_by_name
     FROM bir_or_series s
     LEFT JOIN business_branches bb ON s.branch_id = bb.branch_id
     LEFT JOIN user_accounts u ON s.created_by = u.user_id
     ORDER BY s.year DESC, bb.branch_name ASC"
);

// Fetch branches for filter
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name FROM business_branches WHERE status = 'active' ORDER BY branch_name"
);

// Statistics
$stats = Database::fetch(
    "SELECT 
        COUNT(*) as total_or,
        SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued_count,
        SUM(CASE WHEN status = 'void' THEN 1 ELSE 0 END) as void_count,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
     FROM bir_or_numbers
     WHERE DATE(created_at) = CURDATE()"
);

$viewData = [
    'orNumbers' => $orNumbers,
    'orSeries' => $orSeries,
    'branches' => $branches,
    'stats' => $stats,
    'message' => $message,
    'error' => $error,
    'filters' => [
        'branch_id' => $branchFilter,
        'status' => $statusFilter,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'search' => $search
    ],
    'userRoleCode' => $userRoleCode
];

extract($viewData);

include __DIR__ . '/views/index.php';
