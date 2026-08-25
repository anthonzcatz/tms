<?php
/**
 * POS Single Transaction API — Fetch a single transaction by ID or code
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';

Auth::requireLogin();
$user = Auth::user();
$method = $_SERVER['REQUEST_METHOD'];

// Check if ID encryption is enabled
$encryptIds = false;
try {
    $systemSettings = Database::fetch("SELECT encrypt_ids FROM system_settings WHERE setting_id = 1");
    if ($systemSettings && isset($systemSettings['encrypt_ids']) && $systemSettings['encrypt_ids'] == 1) {
        $encryptIds = true;
    }
} catch (Exception $e) {
    // Default to false if table doesn't exist or query fails
    $encryptIds = false;
}

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
            "SELECT o.*, b.branch_name,
                b.region_name, b.province_name, b.city_municipality_name,
                b.barangay_name, b.street_address, b.landmark, b.zip_code,
                b.contact_number as branch_contact,
                COALESCE(
                CASE
                    WHEN e.middle_name IS NOT NULL AND e.middle_name != ''
                    THEN CONCAT(e.first_name, ' ', SUBSTRING(e.middle_name, 1, 1), '. ', e.last_name)
                    ELSE CONCAT(e.first_name, ' ', e.last_name)
                END,
                ua.username
            ) as cashier_name,
            orn.or_full_number, orn.or_number, orn.or_series, orn.status as or_status,
            vt.vat_type, vt.vat_amount, vt.taxable_amount, vt.non_taxable_amount,
            vt.exemption_type, vt.exemption_id_number, vt.exemption_name
             FROM pos_orders o
             LEFT JOIN business_branches b ON o.branch_id = b.branch_id
             LEFT JOIN user_accounts ua ON o.created_by = ua.user_id
             LEFT JOIN employees e ON ua.emp_id = e.emp_id
             LEFT JOIN bir_or_numbers orn ON o.or_number_id = orn.or_id
             LEFT JOIN bir_vat_transactions vt ON o.order_id = vt.order_id
             WHERE o.order_id = :id",
            ['id' => $id]
        );
    } else {
        $transaction = Database::fetch(
            "SELECT o.*, b.branch_name,
                b.region_name, b.province_name, b.city_municipality_name,
                b.barangay_name, b.street_address, b.landmark, b.zip_code,
                b.contact_number as branch_contact,
                COALESCE(
                CASE
                    WHEN e.middle_name IS NOT NULL AND e.middle_name != ''
                    THEN CONCAT(e.first_name, ' ', SUBSTRING(e.middle_name, 1, 1), '. ', e.last_name)
                    ELSE CONCAT(e.first_name, ' ', e.last_name)
                END,
                ua.username
            ) as cashier_name,
            orn.or_full_number, orn.or_number, orn.or_series, orn.status as or_status,
            vt.vat_type, vt.vat_amount, vt.taxable_amount, vt.non_taxable_amount,
            vt.exemption_type, vt.exemption_id_number, vt.exemption_name
             FROM pos_orders o
             LEFT JOIN business_branches b ON o.branch_id = b.branch_id
             LEFT JOIN user_accounts ua ON o.created_by = ua.user_id
             LEFT JOIN employees e ON ua.emp_id = e.emp_id
             LEFT JOIN bir_or_numbers orn ON o.or_number_id = orn.or_id
             LEFT JOIN bir_vat_transactions vt ON o.order_id = vt.order_id
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
                    END as name,
                    at.name as accommodation_name,
                    at.code as accommodation_code,
                    dt.name as discount_name,
                    dt.code as discount_code,
                    tp_op.provider_name as provider_name,
                    tp_op.provider_type as provider_type,
                    pt_op.type_label as provider_type_label,
                    tp_parent.provider_name as parent_provider_name,
                    v.variant_name,
                    v.variant_code,
                    tp_wallet.provider_name as wallet_provider_name,
                    pv_wallet.variant_name as wallet_variant_name,
                    pv_wallet.variant_code as wallet_variant_code
             FROM pos_order_items oi
             LEFT JOIN passenger_accounts p ON oi.passenger_id = p.passenger_id
             LEFT JOIN service_types st ON oi.service_type_id = st.service_type_id
             LEFT JOIN accommodation_types at ON oi.accommodation_id = at.accommodation_id
             LEFT JOIN discount_types dt ON oi.discount_id = dt.discount_id
             LEFT JOIN ticket_providers tp_op ON oi.provider_id = tp_op.provider_id
             LEFT JOIN provider_types pt_op ON pt_op.type_code = tp_op.provider_type
             LEFT JOIN ticket_providers tp_parent ON tp_op.parent_provider_id = tp_parent.provider_id
             LEFT JOIN provider_ticket_variants v ON oi.variant_id = v.variant_id
             LEFT JOIN provider_wallets pw ON oi.wallet_id = pw.wallet_id
             LEFT JOIN ticket_providers tp_wallet ON pw.provider_id = tp_wallet.provider_id
             LEFT JOIN provider_ticket_variants pv_wallet ON pw.variant_id = pv_wallet.variant_id
             WHERE oi.order_id = :order_id",
            ['order_id' => $transaction['order_id']]
        );
        $transaction['order_items'] = $items;
        $transaction['adjustments'] = Database::fetchAll(
            "SELECT ta.adjustment_id, ta.type, ta.amount, ta.reason, ta.charged_to,
                    ta.charged_to_passenger_id, pa.fullname AS charged_to_passenger_name,
                    ta.responsible_user_id,
                    COALESCE(CONCAT_WS(' ', e.first_name, e.last_name), ua.username) AS responsible_cashier_name,
                    ta.cashier_session_id, ta.approval_status, ta.settlement_status,
                    ta.approved_at, ta.settled_at, ta.created_at,
                    tc.cancellation_id, tc.operation_type, tc.reason_category,
                    tc.cancellation_type, tc.status AS cancellation_status,
                    tc.processed_at AS cancellation_processed_at,
                    tr.processed_at AS refund_processed_at,
                    COALESCE(tr.processed_by, tc.approved_by, tc.requested_by) AS refund_processed_by,
                    COALESCE(
                        NULLIF(CONCAT_WS(' ',
                            NULLIF(TRIM(e_refund.first_name), ''),
                            IF(e_refund.middle_name IS NOT NULL AND e_refund.middle_name != '', CONCAT(UPPER(LEFT(e_refund.middle_name, 1)), '.'), NULL),
                            NULLIF(TRIM(e_refund.last_name), '')
                        ), ''),
                        ua_refund.username,
                        'Unassigned'
                    ) AS refund_processed_by_name,
                    CASE
                        WHEN tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR') THEN 0
                        WHEN tc.responsibility IN ('CUSTOMER', 'CASHIER')
                          OR tc.reason_category IN ('CUSTOMER_REQUEST', 'CUSTOMER_ERROR', 'CASHIER_ERROR')
                            THEN COALESCE(tc.void_fee, 0)
                        ELSE 0
                    END AS void_fee,
                    CASE
                        WHEN tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR') THEN 0
                        WHEN tc.responsibility IN ('CUSTOMER', 'CASHIER')
                          OR tc.reason_category IN ('CUSTOMER_REQUEST', 'CUSTOMER_ERROR', 'CASHIER_ERROR')
                            THEN COALESCE(tc.void_service_fee, 0)
                        ELSE 0
                    END AS void_service_fee,
                    CASE
                        WHEN tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR')
                            THEN COALESCE(NULLIF(tc.lost_sales_void_fee, 0), tc.void_fee, 0)
                        ELSE 0
                    END AS lost_sales_void_fee,
                    COALESCE(tc.lost_sales_service_fee, 0) AS lost_sales_service_fee,
                    CASE
                        WHEN tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR')
                         AND tc.status = 'completed' THEN COALESCE(tt_adj.total_amount, 0)
                        ELSE 0
                    END AS technical_void_amount
             FROM ticket_adjustments ta
             JOIN pos_order_items oi ON oi.reference_id = ta.transaction_id AND oi.item_type = 'TICKET'
             JOIN ticket_transactions tt_adj ON tt_adj.transaction_id = ta.transaction_id
             LEFT JOIN ticket_cancellations tc ON tc.cancellation_id = ta.cancellation_id
             LEFT JOIN ticket_refunds tr ON tr.cancellation_id = tc.cancellation_id
             LEFT JOIN passenger_accounts pa ON pa.passenger_id = ta.charged_to_passenger_id
             LEFT JOIN user_accounts ua ON ua.user_id = ta.responsible_user_id
             LEFT JOIN employees e ON e.emp_id = ua.emp_id
             LEFT JOIN user_accounts ua_refund
                ON ua_refund.user_id = COALESCE(tr.processed_by, tc.approved_by, tc.requested_by)
             LEFT JOIN employees e_refund ON e_refund.emp_id = ua_refund.emp_id
             WHERE oi.order_id = :order_id
             ORDER BY ta.created_at DESC",
            ['order_id' => $transaction['order_id']]
        );
    }
} else {
    // Fallback to ticket/service tables
    if ($code) {
        // Try tickets first
        $transaction = Database::fetch(
            "SELECT t.*, b.branch_name,
                b.region_name, b.province_name, b.city_municipality_name,
                b.barangay_name, b.street_address, b.landmark, b.zip_code,
                b.contact_number as branch_contact,
                COALESCE(
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
                "SELECT st.*, b.branch_name,
                    b.region_name, b.province_name, b.city_municipality_name,
                    b.barangay_name, b.street_address, b.landmark, b.zip_code,
                    b.contact_number as branch_contact,
                    COALESCE(
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
            "SELECT t.*, b.branch_name,
                b.region_name, b.province_name, b.city_municipality_name,
                b.barangay_name, b.street_address, b.landmark, b.zip_code,
                b.contact_number as branch_contact,
                COALESCE(
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
                "SELECT st.*, b.branch_name,
                    b.region_name, b.province_name, b.city_municipality_name,
                    b.barangay_name, b.street_address, b.landmark, b.zip_code,
                    b.contact_number as branch_contact,
                    COALESCE(
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

if ($transaction && ($transaction['type'] ?? '') === 'TICKET' && !isset($transaction['adjustments'])) {
    $transaction['adjustments'] = Database::fetchAll(
        "SELECT ta.adjustment_id, ta.type, ta.amount, ta.reason, ta.charged_to,
                ta.charged_to_passenger_id, pa.fullname AS charged_to_passenger_name,
                ta.responsible_user_id,
                COALESCE(CONCAT_WS(' ', e.first_name, e.last_name), ua.username) AS responsible_cashier_name,
                ta.cashier_session_id, ta.approval_status, ta.settlement_status,
                ta.approved_at, ta.settled_at, ta.created_at,
                tc.cancellation_id, tc.operation_type, tc.reason_category,
                tc.cancellation_type, tc.status AS cancellation_status,
                tc.processed_at AS cancellation_processed_at,
                tr.processed_at AS refund_processed_at,
                COALESCE(tr.processed_by, tc.approved_by, tc.requested_by) AS refund_processed_by,
                COALESCE(
                    NULLIF(CONCAT_WS(' ',
                        NULLIF(TRIM(e_refund.first_name), ''),
                        IF(e_refund.middle_name IS NOT NULL AND e_refund.middle_name != '', CONCAT(UPPER(LEFT(e_refund.middle_name, 1)), '.'), NULL),
                        NULLIF(TRIM(e_refund.last_name), '')
                    ), ''),
                    ua_refund.username,
                    'Unassigned'
                ) AS refund_processed_by_name,
                CASE
                    WHEN tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR') THEN 0
                    WHEN tc.responsibility IN ('CUSTOMER', 'CASHIER')
                      OR tc.reason_category IN ('CUSTOMER_REQUEST', 'CUSTOMER_ERROR', 'CASHIER_ERROR')
                        THEN COALESCE(tc.void_fee, 0)
                    ELSE 0
                END AS void_fee,
                CASE
                    WHEN tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR') THEN 0
                    WHEN tc.responsibility IN ('CUSTOMER', 'CASHIER')
                      OR tc.reason_category IN ('CUSTOMER_REQUEST', 'CUSTOMER_ERROR', 'CASHIER_ERROR')
                        THEN COALESCE(tc.void_service_fee, 0)
                    ELSE 0
                END AS void_service_fee,
                CASE
                        WHEN tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR')
                            THEN COALESCE(NULLIF(tc.lost_sales_void_fee, 0), tc.void_fee, 0)
                        ELSE 0
                    END AS lost_sales_void_fee,
                COALESCE(tc.lost_sales_service_fee, 0) AS lost_sales_service_fee,
                CASE
                    WHEN tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR')
                     AND tc.status = 'completed' THEN COALESCE(tt_adj.total_amount, 0)
                    ELSE 0
                END AS technical_void_amount
         FROM ticket_adjustments ta
         LEFT JOIN ticket_cancellations tc ON tc.cancellation_id = ta.cancellation_id
         LEFT JOIN ticket_refunds tr ON tr.cancellation_id = tc.cancellation_id
         LEFT JOIN ticket_transactions tt_adj ON tt_adj.transaction_id = ta.transaction_id
         LEFT JOIN passenger_accounts pa ON pa.passenger_id = ta.charged_to_passenger_id
         LEFT JOIN user_accounts ua ON ua.user_id = ta.responsible_user_id
         LEFT JOIN employees e ON e.emp_id = ua.emp_id
         LEFT JOIN user_accounts ua_refund
            ON ua_refund.user_id = COALESCE(tr.processed_by, tc.approved_by, tc.requested_by)
         LEFT JOIN employees e_refund ON e_refund.emp_id = ua_refund.emp_id
         WHERE ta.transaction_id = :transaction_id
         ORDER BY ta.created_at DESC",
        ['transaction_id' => (int) $transaction['transaction_id']]
    );
}

if ($transaction && !empty($transaction['adjustments'])) {
    foreach ($transaction['adjustments'] as $adjustment) {
        if (strtoupper((string)($adjustment['type'] ?? '')) !== 'REFUND' || empty($adjustment['refund_processed_at'])) {
            continue;
        }
        if (empty($transaction['refund_processed_at'])
            || strcmp((string)$adjustment['refund_processed_at'], (string)$transaction['refund_processed_at']) > 0) {
            $transaction['refund_processed_at'] = $adjustment['refund_processed_at'];
            $transaction['refund_processed_by'] = $adjustment['refund_processed_by'] ?? null;
            $transaction['refund_processed_by_name'] = $adjustment['refund_processed_by_name'] ?? 'Unassigned';
        }
    }
}

if (!$transaction) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Transaction not found']);
    exit;
}

// Branch access control
$allowedBranchIds = PosAccess::allowedBranchIds($user);
if ($allowedBranchIds !== null && !in_array((int) $transaction['branch_id'], $allowedBranchIds, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied: transaction does not belong to an assigned branch']);
    exit;
}

// Encode IDs if encryption is enabled
if ($encryptIds) {
    $transaction['order_id'] = IdEncoder::encode($transaction['order_id']);
    if (isset($transaction['order_items'])) {
        foreach ($transaction['order_items'] as &$item) {
            $item['item_id'] = IdEncoder::encode($item['item_id']);
            $item['order_id'] = IdEncoder::encode($item['order_id']);
        }
    }
    if (isset($transaction['items'])) {
        foreach ($transaction['items'] as &$item) {
            if (isset($item['item_id'])) {
                $item['item_id'] = IdEncoder::encode($item['item_id']);
            }
        }
    }
}

echo json_encode(['success' => true, 'transaction' => $transaction]);
