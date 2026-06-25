<?php
/**
 * Companies API Endpoint
 * Handles company management operations
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

$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';

// Check permission - SUPER_ADMIN or users with MANAGE_COMPANIES permission
$canView = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('MANAGE_COMPANIES');

if (!$canView) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied. You need MANAGE_COMPANIES permission to access this resource.']);
    exit;
}

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
    error_log("Companies API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

function handleGet() {
    $companyId = $_GET['id'] ?? null;

    if ($companyId) {
        $company = Database::fetch(
            "SELECT * FROM companies WHERE comp_id = :comp_id",
            ['comp_id' => (int)$companyId]
        );

        if ($company) {
            echo json_encode(['success' => true, 'data' => $company]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Company not found']);
        }
        return;
    }

    $companies = Database::fetchAll(
        "SELECT * FROM companies ORDER BY comp_name"
    );

    echo json_encode([
        'success' => true,
        'data' => ['companies' => $companies]
    ]);
}

function handlePost() {
    global $user, $userRoleCode;

    $canCreate = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('CREATE_COMPANY');
    if (!$canCreate) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied. You need CREATE_COMPANY permission.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $companyName = trim($input['comp_name'] ?? '');
    $companyCode = trim($input['comp_abbre'] ?? '');
    $companyAddress = trim($input['company_address'] ?? '');
    $companyContact = trim($input['company_contact'] ?? '');
    $companyEmail = trim($input['company_email'] ?? '');
    $status = $input['comp_status'] ?? 'active';

    if (!$companyName) {
        echo json_encode(['success' => false, 'error' => 'Company name is required']);
        return;
    }

    $existing = Database::fetch(
        "SELECT comp_id FROM companies WHERE comp_name = :comp_name",
        ['comp_name' => $companyName]
    );

    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Company name already exists']);
        return;
    }

    Database::execute(
        "INSERT INTO companies (comp_name, comp_abbre, company_address, company_contact, company_email, comp_status, comp_addedby, comp_dateadded)
         VALUES (:comp_name, :comp_abbre, :company_address, :company_contact, :company_email, :comp_status, :comp_addedby, NOW())",
        [
            'comp_name' => $companyName,
            'comp_abbre' => $companyCode ?: null,
            'company_address' => $companyAddress ?: null,
            'company_contact' => $companyContact ?: null,
            'company_email' => $companyEmail ?: null,
            'comp_status' => $status,
            'comp_addedby' => $user['user_id'] ?? 1
        ]
    );

    $companyId = Database::connection()->lastInsertId();

    echo json_encode(['success' => true, 'message' => 'Company created successfully', 'comp_id' => $companyId]);
}

function handlePut() {
    global $user, $userRoleCode;

    $canUpdate = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('UPDATE_COMPANY');
    if (!$canUpdate) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied. You need UPDATE_COMPANY permission.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $companyId = $input['comp_id'] ?? null;

    if (!$companyId) {
        echo json_encode(['success' => false, 'error' => 'Missing company ID']);
        return;
    }

    $currentCompany = Database::fetch(
        "SELECT * FROM companies WHERE comp_id = :comp_id",
        ['comp_id' => (int)$companyId]
    );

    if (!$currentCompany) {
        echo json_encode(['success' => false, 'error' => 'Company not found']);
        return;
    }

    $updateFields = [];
    $params = ['comp_id' => (int)$companyId];

    if (isset($input['comp_name'])) {
        $companyName = trim($input['comp_name']);
        if (!$companyName) {
            echo json_encode(['success' => false, 'error' => 'Company name is required']);
            return;
        }
        $existing = Database::fetch(
            "SELECT comp_id FROM companies WHERE comp_name = :comp_name AND comp_id != :comp_id",
            ['comp_name' => $companyName, 'comp_id' => (int)$companyId]
        );
        if ($existing) {
            echo json_encode(['success' => false, 'error' => 'Company name already exists']);
            return;
        }
        $updateFields[] = "comp_name = :comp_name";
        $params['comp_name'] = $companyName;
    }

    if (isset($input['comp_abbre'])) {
        $updateFields[] = "comp_abbre = :comp_abbre";
        $params['comp_abbre'] = trim($input['comp_abbre']) ?: null;
    }
    if (isset($input['company_address'])) {
        $updateFields[] = "company_address = :company_address";
        $params['company_address'] = trim($input['company_address']) ?: null;
    }
    if (isset($input['company_contact'])) {
        $updateFields[] = "company_contact = :company_contact";
        $params['company_contact'] = trim($input['company_contact']) ?: null;
    }
    if (isset($input['company_email'])) {
        $updateFields[] = "company_email = :company_email";
        $params['company_email'] = trim($input['company_email']) ?: null;
    }
    if (isset($input['comp_status'])) {
        $updateFields[] = "comp_status = :comp_status";
        $params['comp_status'] = $input['comp_status'];
    }

    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        return;
    }

    $sql = "UPDATE companies SET " . implode(', ', $updateFields) . " WHERE comp_id = :comp_id";
    Database::execute($sql, $params);

    echo json_encode(['success' => true, 'message' => 'Company updated successfully']);
}

function handleDelete() {
    global $user, $userRoleCode;

    $canDelete = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('DELETE_COMPANY');
    if (!$canDelete) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied. You need DELETE_COMPANY permission.']);
        exit;
    }

    $companyId = $_GET['id'] ?? null;
    if (!$companyId) {
        echo json_encode(['success' => false, 'error' => 'Missing company ID']);
        return;
    }

    $currentCompany = Database::fetch(
        "SELECT * FROM companies WHERE comp_id = :comp_id",
        ['comp_id' => (int)$companyId]
    );

    if (!$currentCompany) {
        echo json_encode(['success' => false, 'error' => 'Company not found']);
        return;
    }

    $hasEmployees = Database::fetch(
        "SELECT COUNT(*) as count FROM employees WHERE b_company_id = :comp_id",
        ['comp_id' => (int)$companyId]
    );

    if ($hasEmployees && $hasEmployees['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete company with existing employees']);
        return;
    }

    Database::execute(
        "DELETE FROM companies WHERE comp_id = :comp_id",
        ['comp_id' => (int)$companyId]
    );

    echo json_encode(['success' => true, 'message' => 'Company deleted successfully']);
}
