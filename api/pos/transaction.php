<?php
/**
 * POS Single Transaction API — Fetch a single transaction by ID or code
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

Auth::requireLogin();
$user = Auth::user();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get ID from path parameter (e.g., /api/pos/transaction/12)
$path = $_SERVER['REQUEST_URI'];
$path = parse_url($path, PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

// Remove 'TMS' if present
if ($segments[0] === 'TMS') {
    array_shift($segments);
}

// Find the ID in the path (last segment after 'transaction')
$id = null;
$transactionIndex = array_search('transaction', $segments);
if ($transactionIndex !== false && isset($segments[$transactionIndex + 1])) {
    $id = $segments[$transactionIndex + 1];
}

// Also check query parameters
$id = $_GET['id'] ?? $id;
$code = $_GET['code'] ?? null;

if (!$id && !$code) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Transaction ID or code required']);
    exit;
}

// Decode ID if it's encrypted (not numeric)
if ($id && !is_numeric($id)) {
    $decodedId = IdEncoder::decode($id);
    if ($decodedId === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid transaction ID']);
        exit;
    }
    $id = $decodedId;
}

// Check if pos_orders table exists
$useOrdersTable = false;
try {
    $check = Database::fetch("SELECT 1 FROM pos_orders LIMIT 1");
    $useOrdersTable = true;
} catch (Exception $e) {
    $useOrdersTable = false;
}

$transaction = null;

if ($useOrdersTable) {
    // Fetch from pos_orders
    if ($id) {
        $transaction = Database::fetch(
            "SELECT o.*, b.branch_name, COALESCE(
                CASE
                    WHEN e.middle_name IS NOT NULL AND e.middle_name != ''
                    THEN CONCAT(e.first_name, ' ', SUBSTRING(e.middle_name, 1, 1), '. ', e.last_name)
                    ELSE CONCAT(e.first_name, ' ', e.last_name)
                END,
                ua.username
            ) as cashier_name
             FROM pos_orders o
             LEFT JOIN business_branches b ON o.branch_id = b.branch_id
             LEFT JOIN user_accounts ua ON o.created_by = ua.user_id
             LEFT JOIN employees e ON ua.emp_id = e.emp_id
             WHERE o.order_id = :id",
            ['id' => $id]
        );
    } else {
        $transaction = Database::fetch(
            "SELECT o.*, b.branch_name, COALESCE(
                CASE
                    WHEN e.middle_name IS NOT NULL AND e.middle_name != ''
                    THEN CONCAT(e.first_name, ' ', SUBSTRING(e.middle_name, 1, 1), '. ', e.last_name)
                    ELSE CONCAT(e.first_name, ' ', e.last_name)
                END,
                ua.username
            ) as cashier_name
             FROM pos_orders o
             LEFT JOIN business_branches b ON o.branch_id = b.branch_id
             LEFT JOIN user_accounts ua ON o.created_by = ua.user_id
             LEFT JOIN employees e ON ua.emp_id = e.emp_id
             WHERE o.order_code = :code",
            ['code' => $code]
        );
    }

    if ($transaction) {
        // Fetch order items
        $items = Database::fetchAll(
            "SELECT oi.*,
                    CASE
                        WHEN oi.item_type = 'TICKET' THEN p.fullname
                        WHEN oi.item_type = 'SERVICE' THEN st.name
                        ELSE oi.item_type
                    END as name
             FROM pos_order_items oi
             LEFT JOIN passenger_accounts p ON oi.passenger_id = p.passenger_id
             LEFT JOIN service_types st ON oi.service_type_id = st.service_type_id
             WHERE oi.order_id = :order_id",
            ['order_id' => $transaction['order_id']]
        );
        $transaction['order_items'] = $items;
    }
} else {
    // Fallback to ticket/service tables
    if ($code) {
        // Try tickets first
        $transaction = Database::fetch(
            "SELECT t.*, b.branch_name, COALESCE(
                CASE
                    WHEN e.middle_name IS NOT NULL AND e.middle_name != ''
                    THEN CONCAT(e.first_name, ' ', SUBSTRING(e.middle_name, 1, 1), '. ', e.last_name)
                    ELSE CONCAT(e.first_name, ' ', e.last_name)
                END,
                ua.username
            ) as cashier_name,
                    'TICKET' as type
             FROM ticket_transactions t
             LEFT JOIN business_branches b ON t.branch_id = b.branch_id
             LEFT JOIN user_accounts ua ON t.created_by = ua.user_id
             LEFT JOIN employees e ON ua.emp_id = e.emp_id
             WHERE t.transaction_code = :code",
            ['code' => $code]
        );

        if (!$transaction) {
            // Try service transactions
            $transaction = Database::fetch(
                "SELECT st.*, b.branch_name, COALESCE(
                    CASE
                        WHEN e.middle_name IS NOT NULL AND e.middle_name != ''
                        THEN CONCAT(e.first_name, ' ', SUBSTRING(e.middle_name, 1, 1), '. ', e.last_name)
                        ELSE CONCAT(e.first_name, ' ', e.last_name)
                    END,
                    ua.username
                ) as cashier_name,
                        'SERVICE' as type
                 FROM service_transactions st
                 LEFT JOIN business_branches b ON st.branch_id = b.branch_id
                 LEFT JOIN user_accounts ua ON st.created_by = ua.user_id
                 LEFT JOIN employees e ON ua.emp_id = e.emp_id
                 WHERE st.transaction_code = :code",
                ['code' => $code]
            );
        }
    } elseif ($id) {
        // Try by ID - need to determine if it's ticket or service
        $transaction = Database::fetch(
            "SELECT t.*, b.branch_name, COALESCE(
                CASE
                    WHEN e.middle_name IS NOT NULL AND e.middle_name != ''
                    THEN CONCAT(e.first_name, ' ', SUBSTRING(e.middle_name, 1, 1), '. ', e.last_name)
                    ELSE CONCAT(e.first_name, ' ', e.last_name)
                END,
                ua.username
            ) as cashier_name,
                    'TICKET' as type
             FROM ticket_transactions t
             LEFT JOIN business_branches b ON t.branch_id = b.branch_id
             LEFT JOIN user_accounts ua ON t.created_by = ua.user_id
             LEFT JOIN employees e ON ua.emp_id = e.emp_id
             WHERE t.transaction_id = :id",
            ['id' => $id]
        );

        if (!$transaction) {
            $transaction = Database::fetch(
                "SELECT st.*, b.branch_name, COALESCE(
                    CASE
                        WHEN e.middle_name IS NOT NULL AND e.middle_name != ''
                        THEN CONCAT(e.first_name, ' ', SUBSTRING(e.middle_name, 1, 1), '. ', e.last_name)
                        ELSE CONCAT(e.first_name, ' ', e.last_name)
                    END,
                    ua.username
                ) as cashier_name,
                        'SERVICE' as type
                 FROM service_transactions st
                 LEFT JOIN business_branches b ON st.branch_id = b.branch_id
                 LEFT JOIN user_accounts ua ON st.created_by = ua.user_id
                 LEFT JOIN employees e ON ua.emp_id = e.emp_id
                 WHERE st.transaction_id = :id",
                ['id' => $id]
            );
        }
    }

    if ($transaction && $transaction['type'] === 'SERVICE') {
        // Fetch service items
        $items = Database::fetchAll(
            "SELECT sti.*, st.service_type_name as name
             FROM service_transaction_items sti
             LEFT JOIN service_types st ON sti.service_type_id = st.service_type_id
             WHERE sti.transaction_id = :id",
            ['id' => $transaction['transaction_id']]
        );
        $transaction['items'] = $items;
    }
}

if (!$transaction) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Transaction not found']);
    exit;
}

echo json_encode(['success' => true, 'data' => $transaction]);
