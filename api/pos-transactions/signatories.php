<?php
/**
 * Financial Report Signatories API
 * GET  - read signatories for a branch
 *      - read active positions (positions=1)
 *      - read employees for a branch (employees=1)
 * POST - save signatories for a branch
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';

function signatoryRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function signatoryLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function signatoryBranchId($value): int
{
    if ($value === null || $value === '' || is_array($value)) {
        return 0;
    }

    $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $validated === false ? 0 : (int) $validated;
}

function signatoryPositiveId($value, string $field): int
{
    if ($value === null || $value === '' || $value === 0 || $value === '0') {
        return 0;
    }
    if (is_array($value)) {
        throw new InvalidArgumentException($field . ' must be a valid integer.');
    }

    $validated = filter_var($value, FILTER_VALIDATE_INT);
    if ($validated === false || $validated < 0) {
        throw new InvalidArgumentException($field . ' must be a valid integer.');
    }
    return (int) $validated;
}

function signatoryBoolean($value): int
{
    if (is_array($value)) {
        return 0;
    }
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    if (is_int($value) || is_float($value)) {
        return ((int) $value) === 0 ? 0 : 1;
    }
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
}

function loadActiveSignatoryPositions(): array
{
    $positions = Database::fetchAll(
        "SELECT pos_id AS position_id, position_name
         FROM position
         WHERE status = 'active'
         ORDER BY position_name"
    );

    $positionMap = [];
    foreach ($positions as $position) {
        $positionMap[(int) $position['position_id']] = $position;
    }
    return $positionMap;
}

function loadSignatoryEmployees(int $branchId, ?array $employeeIds = null): array
{
    $params = ['selected_branch' => $branchId];
    $where = [
        "e.first_name IS NOT NULL",
        "e.first_name != ''",
        '(e.branch_id = :selected_branch OR e.branch_id IS NULL)'
    ];

    if ($employeeIds !== null) {
        $employeeIds = array_values(array_unique(array_filter(
            array_map('intval', $employeeIds),
            static fn (int $employeeId): bool => $employeeId > 0
        )));
        if (empty($employeeIds)) {
            return [];
        }

        $employeePlaceholders = [];
        foreach ($employeeIds as $index => $employeeId) {
            $placeholder = ':signatory_employee_' . $index;
            $employeePlaceholders[] = $placeholder;
            $params['signatory_employee_' . $index] = $employeeId;
        }
        $where[] = 'e.emp_id IN (' . implode(',', $employeePlaceholders) . ')';
    }

    return Database::fetchAll(
        "SELECT e.emp_id AS employee_id,
                e.first_name,
                e.last_name,
                e.middle_name,
                e.job_title AS position_id,
                p.position_name,
                CONCAT_WS(' ',
                    NULLIF(TRIM(e.first_name), ''),
                    NULLIF(TRIM(e.middle_name), ''),
                    NULLIF(TRIM(e.last_name), '')
                ) AS full_name
         FROM employees e
         LEFT JOIN position p ON e.job_title = p.pos_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY e.last_name, e.first_name",
        $params
    );
}

function normalizeStoredSignatories(array $signatories, int $branchId, bool $strict = false): array
{
    $employeeIds = [];
    foreach ($signatories as $signatory) {
        if (is_array($signatory)) {
            $employeeId = (int) ($signatory['employee_id'] ?? 0);
            if ($employeeId > 0) {
                $employeeIds[] = $employeeId;
            }
        }
    }

    $employeeMap = [];
    foreach (loadSignatoryEmployees($branchId, $employeeIds) as $employee) {
        $employeeMap[(int) $employee['employee_id']] = $employee;
    }

    $positionMap = loadActiveSignatoryPositions();
    $normalized = [];
    foreach ($signatories as $index => $signatory) {
        if (!is_array($signatory)) {
            continue;
        }

        $label = trim((string) ($signatory['label'] ?? ''));
        if ($label === '') {
            continue;
        }
        $employeeId = (int) ($signatory['employee_id'] ?? 0);
        $positionId = (int) ($signatory['position_id'] ?? 0);
        $name = trim((string) ($signatory['name'] ?? ''));
        $positionName = trim((string) ($signatory['position_name'] ?? ''));
        $employeeAvailable = true;

        if ($employeeId > 0) {
            if (!isset($employeeMap[$employeeId])) {
                if ($strict) {
                    throw new InvalidArgumentException('One or more selected employees are not available for this branch.');
                }
                $employeeAvailable = false;
            } else {
                $employee = $employeeMap[$employeeId];
                $name = trim((string) $employee['full_name']);
                $positionId = (int) ($employee['position_id'] ?? 0);
                $positionName = trim((string) ($employee['position_name'] ?? ''));
            }
        } elseif ($positionId > 0 && isset($positionMap[$positionId])) {
            $positionName = trim((string) $positionMap[$positionId]['position_name']);
        } elseif ($positionId > 0) {
            $positionId = 0;
            $positionName = '';
        }

        $normalized[] = [
            'label' => $label,
            'name' => $name,
            'employee_id' => $employeeId,
            'position_id' => $positionId,
            'position_name' => $positionName,
            'sort_order' => (int) ($signatory['sort_order'] ?? $index),
            'is_active' => signatoryBoolean($signatory['is_active'] ?? 0),
            'employee_available' => $employeeAvailable ? 1 : 0
        ];
    }

    usort($normalized, static function (array $left, array $right): int {
        return ($left['sort_order'] <=> $right['sort_order'])
            ?: strcasecmp($left['label'], $right['label']);
    });

    foreach ($normalized as $index => &$signatory) {
        $signatory['sort_order'] = $index;
    }
    unset($signatory);

    return $normalized;
}

Auth::requireLogin();
$user = Auth::user();

if (!$user) {
    signatoryRespond(['success' => false, 'error' => 'Unauthorized'], 401);
}

$userRoleCode = $user['role_code'] ?? '';
$canViewSignatories = $userRoleCode === 'SUPER_ADMIN'
    || Auth::canAccessModule('admin/pos/transactions/')
    || Auth::canAccessModule('admin/pos/');
$canManageSignatories = in_array($userRoleCode, ['SUPER_ADMIN', 'MANAGER'], true)
    || Auth::can('VIEW_SETTINGS');

if (!$canViewSignatories) {
    signatoryRespond(['success' => false, 'error' => 'Permission denied'], 403);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$input = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 65536) {
        signatoryRespond(['success' => false, 'error' => 'Invalid request payload.'], 400);
    }
    $decodedInput = json_decode($raw ?: '', true);
    if (!is_array($decodedInput)) {
        signatoryRespond(['success' => false, 'error' => 'Request body must be valid JSON.'], 400);
    }
    $input = $decodedInput;
}

if (!in_array($method, ['GET', 'POST'], true)) {
    signatoryRespond(['success' => false, 'error' => 'Method not allowed.'], 405);
}

if ($method === 'GET' && !empty($_GET['positions'])) {
    try {
        $positions = array_values(loadActiveSignatoryPositions());
        signatoryRespond(['success' => true, 'positions' => $positions]);
    } catch (Throwable $e) {
        error_log('[Financial report signatories] Position lookup failed: ' . $e->getMessage());
        signatoryRespond(['success' => false, 'error' => 'Unable to load positions.'], 500);
    }
}

$branchId = signatoryBranchId($method === 'POST'
    ? ($input['branch_id'] ?? null)
    : ($_GET['branch_id'] ?? null));

if ($method === 'GET' && !empty($_GET['employees'])) {
    if ($branchId <= 0) {
        signatoryRespond(['success' => false, 'error' => 'Branch is required.'], 400);
    }
    try {
        PosAccess::assertBranchAccess($user, $branchId);
        $employees = loadSignatoryEmployees($branchId);
        signatoryRespond(['success' => true, 'employees' => $employees, 'branch_id' => $branchId]);
    } catch (InvalidArgumentException $e) {
        signatoryRespond(['success' => false, 'error' => $e->getMessage()], 400);
    } catch (Throwable $e) {
        error_log('[Financial report signatories] Employee lookup failed: ' . $e->getMessage());
        signatoryRespond(['success' => false, 'error' => 'Unable to load employees.'], 500);
    }
}

if ($branchId <= 0) {
    signatoryRespond(['success' => false, 'error' => 'Branch is required.'], 400);
}

try {
    PosAccess::assertBranchAccess($user, $branchId);
} catch (InvalidArgumentException $e) {
    signatoryRespond(['success' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    signatoryRespond(['success' => false, 'error' => $e->getMessage()], 403);
}

try {
    if ($method === 'GET') {
        $row = Database::fetch(
            "SELECT branch_id, branch_name, financial_report_signatories
             FROM business_branches
             WHERE branch_id = :branch_id",
            ['branch_id' => $branchId]
        );
        $signatories = [];
        if ($row && $row['financial_report_signatories'] !== null
            && trim((string) $row['financial_report_signatories']) !== '') {
            $decoded = json_decode((string) $row['financial_report_signatories'], true);
            if (is_array($decoded)) {
                $signatories = normalizeStoredSignatories($decoded, $branchId);
            }
        }
        signatoryRespond([
            'success' => true,
            'branch_id' => $branchId,
            'branch_name' => $row['branch_name'] ?? '',
            'signatories' => $signatories,
            'configured' => $row && $row['financial_report_signatories'] !== null
                && trim((string) $row['financial_report_signatories']) !== ''
        ]);
    }

    if ($method === 'POST') {
        if (!$canManageSignatories) {
            signatoryRespond(['success' => false, 'error' => 'Only managers or authorized settings users can save signatories.'], 403);
        }

        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST['_token']
            ?? $_GET['_token']
            ?? null;
        if (!SecurityHelper::validateCSRFToken($csrfToken)) {
            signatoryRespond(['success' => false, 'error' => 'Invalid CSRF token.'], 403);
        }

        $signatories = $input['signatories'] ?? [];
        if (!is_array($signatories)) {
            signatoryRespond(['success' => false, 'error' => 'Signatories must be an array.'], 400);
        }
        if (count($signatories) > 20) {
            signatoryRespond(['success' => false, 'error' => 'A branch can have at most 20 signatories.'], 422);
        }

        $employeeIds = [];
        foreach ($signatories as $signatory) {
            if (is_array($signatory)) {
                $employeeId = signatoryPositiveId($signatory['employee_id'] ?? 0, 'Employee ID');
                if ($employeeId > 0) {
                    $employeeIds[] = $employeeId;
                }
            }
        }

        $employeeMap = [];
        foreach (loadSignatoryEmployees($branchId, $employeeIds) as $employee) {
            $employeeMap[(int) $employee['employee_id']] = $employee;
        }
        $missingEmployeeIds = array_values(array_diff(array_unique($employeeIds), array_keys($employeeMap)));
        if (!empty($missingEmployeeIds)) {
            signatoryRespond([
                'success' => false,
                'error' => 'One or more selected employees are not available for the selected branch. Please select them again.'
            ], 422);
        }

        $positionMap = loadActiveSignatoryPositions();
        $sanitized = [];
        foreach ($signatories as $index => $signatory) {
            if (!is_array($signatory)) {
                signatoryRespond(['success' => false, 'error' => 'Each signatory must be an object.'], 422);
            }

            $label = trim((string) ($signatory['label'] ?? ''));
            $employeeId = signatoryPositiveId($signatory['employee_id'] ?? 0, 'Employee ID');
            $positionId = signatoryPositiveId($signatory['position_id'] ?? 0, 'Position ID');
            $name = trim((string) ($signatory['name'] ?? ''));
            $isActive = signatoryBoolean($signatory['is_active'] ?? 0);
            $hasOtherValues = $employeeId > 0 || $positionId > 0 || $name !== '' || $isActive === 1;

            if ($label === '') {
                if ($hasOtherValues) {
                    signatoryRespond(['success' => false, 'error' => 'Every signatory row must have a role or label.'], 422);
                }
                continue;
            }
            if (signatoryLength($label) > 100) {
                signatoryRespond(['success' => false, 'error' => 'Signatory labels must be 100 characters or fewer.'], 422);
            }
            if (signatoryLength($name) > 150) {
                signatoryRespond(['success' => false, 'error' => 'Signatory names must be 150 characters or fewer.'], 422);
            }

            $positionName = '';
            if ($employeeId > 0) {
                $employee = $employeeMap[$employeeId];
                $name = trim((string) $employee['full_name']);
                $positionId = (int) ($employee['position_id'] ?? 0);
                $positionName = trim((string) ($employee['position_name'] ?? ''));
            } elseif ($positionId > 0) {
                if (!isset($positionMap[$positionId])) {
                    signatoryRespond(['success' => false, 'error' => 'One or more selected positions are invalid or inactive.'], 422);
                }
                $positionName = trim((string) $positionMap[$positionId]['position_name']);
            }

            if ($isActive === 1 && $employeeId === 0 && $name === '') {
                signatoryRespond([
                    'success' => false,
                    'error' => 'Active signatory "' . $label . '" needs an employee or a display name. Deactivate or remove the row if it is not needed.'
                ], 422);
            }

            $sanitized[] = [
                'label' => $label,
                'name' => $name,
                'employee_id' => $employeeId,
                'position_id' => $positionId,
                'position_name' => $positionName,
                'sort_order' => count($sanitized),
                'is_active' => $isActive
            ];
        }

        $json = json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            signatoryRespond(['success' => false, 'error' => 'Unable to encode signatories.'], 422);
        }

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $branch = Database::fetch(
                "SELECT branch_id
                 FROM business_branches
                 WHERE branch_id = :branch_id AND status = 'active'
                 FOR UPDATE",
                ['branch_id' => $branchId]
            );
            if (!$branch) {
                throw new InvalidArgumentException('Invalid or inactive branch selected.');
            }
            Database::execute(
                "UPDATE business_branches
                 SET financial_report_signatories = :signatories
                 WHERE branch_id = :branch_id",
                ['signatories' => $json, 'branch_id' => $branchId]
            );
            $connection->commit();
        } catch (Throwable $e) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $e;
        }

        signatoryRespond([
            'success' => true,
            'branch_id' => $branchId,
            'configured' => true,
            'signatories' => $sanitized
        ]);
    }

    signatoryRespond(['success' => false, 'error' => 'Method not allowed.'], 405);
} catch (InvalidArgumentException $e) {
    signatoryRespond(['success' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('[Financial report signatories] Request failed: ' . $e->getMessage());
    signatoryRespond(['success' => false, 'error' => 'Unable to process signatories right now.'], 500);
}
