<?php
/**
 * POS Tickets API — Process ticket bookings with mixed payments
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/BIRHelper.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/TicketStockHelper.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/BalanceLedgerService.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/ProviderWalletDeductionService.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/ChargeService.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/CashierTransportAccess.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PusherService.php';

function logActivity($userId, $action, $module, $ref = null, $old = null, $new = null) {
    $now = date('Y-m-d H:i:s');
    Database::execute(
        "INSERT INTO activity_logs (user_id, device_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
         VALUES (:uid, NULL, :action, :mod, :ref, :ip, :old, :new, :created_at)",
        ['uid' => $userId, 'action' => $action, 'mod' => $module, 'ref' => $ref,
         'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
         'old' => $old ? json_encode($old) : null, 'new' => $new ? json_encode($new) : null,
         'created_at' => $now]
    );
}

function isDuplicateOrderCodeError(Throwable $e): bool {
    $message = strtolower($e->getMessage());
    return strpos($message, 'duplicate entry') !== false && strpos($message, 'order_code') !== false;
}

function findExistingPosOrder(string $orderCode, int $userId, int $sessionId, int $branchId): ?array {
    return Database::fetch(
        "SELECT order_id, order_code, grand_total, amount_paid, change_amount
         FROM pos_orders
         WHERE order_code = :code
           AND created_by = :uid
           AND cashier_session_id = :session
           AND branch_id = :branch
         LIMIT 1",
        ['code' => $orderCode, 'uid' => $userId, 'session' => $sessionId, 'branch' => $branchId]
    );
}

Auth::requireLogin();
$user = Auth::user();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$sessionId = $input['session_id'] ?? null;
$branchId  = $input['branch_id'] ?? null;
$payments  = $input['payments'] ?? [];

// Support both single ticket (legacy) and multiple tickets array
$tickets   = $input['tickets'] ?? [];
if (empty($tickets) && !empty($input['ticket'])) {
    $tickets = [$input['ticket']]; // backward-compat: wrap single ticket
}
$services  = $input['services'] ?? [];
$requestedOrderCode = trim((string) ($input['order_code'] ?? ''));

if ($requestedOrderCode !== '' && !preg_match('/^ORD-\d{8}-\d{6}-[A-F0-9]{16}$/i', $requestedOrderCode)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid order request code.']);
    exit;
}

// Validate
if (!$sessionId)        { echo json_encode(['success' => false, 'error' => 'Session ID required.']); exit; }
if (!$branchId)         { echo json_encode(['success' => false, 'error' => 'Branch ID required.']); exit; }
if (empty($tickets))    { echo json_encode(['success' => false, 'error' => 'At least one ticket is required.']); exit; }
if (empty($payments))   { echo json_encode(['success' => false, 'error' => 'No payment provided.']); exit; }

// Verify the active session and branch against current server-side access.
try {
    $session = PosAccess::assertSessionForTransaction((int) $sessionId, (int) $branchId, $user);
    $sessionId = (int) $sessionId;
    $branchId = (int) $branchId;
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

// Read system settings once — used inside the transaction loop.
// Default to requiring ticket numbers if an older database has not received the migration yet.
try {
    $sysSettings = Database::fetch("SELECT pos_allow_insufficient_wallet, pos_ticket_number_required FROM system_settings WHERE setting_id = 1") ?? [];
} catch (Throwable $e) {
    $sysSettings = Database::fetch("SELECT pos_allow_insufficient_wallet FROM system_settings WHERE setting_id = 1") ?? [];
    $sysSettings['pos_ticket_number_required'] = 1;
}
$allowWalletOverdraft = !empty($sysSettings['pos_allow_insufficient_wallet']);
$ticketNumberRequired = (int) ($sysSettings['pos_ticket_number_required'] ?? 1) === 1;

foreach ($tickets as $ticket) {
    if ($ticketNumberRequired && trim((string) ($ticket['ticket_number'] ?? '')) === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Ticket number is required.']);
        exit;
    }

    $ticketNotes = is_string($ticket['ticket_notes'] ?? null) ? trim($ticket['ticket_notes']) : '';
    $ticketNotesLength = function_exists('mb_strlen') ? mb_strlen($ticketNotes, 'UTF-8') : strlen($ticketNotes);
    if ($ticketNotesLength > 500) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Ticket notes must be 500 characters or fewer.']);
        exit;
    }
}

// Process transaction with retry for duplicate key errors
$maxRetries = 50;
$lastError = null;
$changedWalletIds = [];

function generateOrderCode() {
    // Random suffix avoids the check-then-increment race under concurrent cashiers.
    return 'ORD-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(5)));
}

for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
    try {
        // Generate unique order code: ORD-YYYYMMDD-HHMM-### (sequential)
        $orderCode = $requestedOrderCode ?: generateOrderCode();

        // Start database transaction
        Database::connection()->beginTransaction();

        // --- Compute totals across all tickets + services ---
        $ticketsTotal   = array_sum(array_column($tickets, 'total_amount'));
        $servicesTotal  = array_sum(array_column($services, 'total_amount'));
        $discountTotal  = array_sum(array_column($tickets, 'discount_amount'));
        $orderTotal     = $ticketsTotal + $servicesTotal;
        $totalPaid      = array_sum(array_column($payments, 'amount'));

        // Pre-compute profit columns
        $totalCost        = array_sum(array_column($tickets, 'base_amount'));  // Cost paid to provider
        $totalServiceFees = array_sum(array_column($tickets, 'service_fee'));  // Service fees = profit
        $totalAddOns      = array_sum(array_column($services, 'total_amount')); // Add-ons = profit
        $totalProfit      = $totalServiceFees + $totalAddOns;

        // Denormalize cashier name from employee record
        $cashierName = null;
        $cashierUserId = $user['user_id'];
        $empRow = Database::fetch(
            "SELECT e.first_name, e.middle_name, e.last_name
             FROM user_accounts ua
             JOIN employees e ON e.emp_id = ua.emp_id
             WHERE ua.user_id = :uid",
            ['uid' => $cashierUserId]
        );
        if ($empRow) {
            $mid = !empty($empRow['middle_name']) ? ' ' . substr($empRow['middle_name'],0,1) . '.' : '';
            $cashierName = trim($empRow['first_name'] . $mid . ' ' . $empRow['last_name']);
        }
        if (!$cashierName) $cashierName = $user['username'] ?? null;

        // Denormalize all payment methods (handles multi-payment)
        $uniqueMethodIds = array_unique(array_filter(array_column($payments, 'payment_method_id')));
        $paymentMethodsDetail = [];
        foreach ($uniqueMethodIds as $pmId) {
            $pmRow = Database::fetch("SELECT method_id, method_name FROM payment_methods WHERE method_id = :id", ['id' => $pmId]);
            if ($pmRow) {
                $totalForMethod = array_sum(array_column(array_filter($payments, fn($p) => $p['payment_method_id'] == $pmId), 'amount'));
                $paymentMethodsDetail[] = ['method_id' => (int)$pmRow['method_id'], 'method_name' => $pmRow['method_name'], 'amount' => $totalForMethod];
            }
        }
        $primaryPaymentMethod  = implode(' + ', array_column($paymentMethodsDetail, 'method_name')) ?: null;
        $paymentMethodIds      = implode(',', array_column($paymentMethodsDetail, 'method_id')) ?: null;
        $paymentMethodsJson    = !empty($paymentMethodsDetail) ? json_encode($paymentMethodsDetail) : null;

        if ($totalPaid < $orderTotal) {
            Database::connection()->rollBack();
            echo json_encode(['success' => false, 'error' => "Payment (₱{$totalPaid}) is less than total (₱{$orderTotal})."]); exit;
        }

        // --- Create pos_orders record ---
        try {
            Database::execute(
                "INSERT INTO pos_orders
                    (order_code, branch_id, cashier_session_id, created_by,
                     subtotal, discount_total, total_cost, total_service_fees, total_add_ons, total_profit,
                     grand_total, original_grand_total, amount_paid, change_amount,
                     payment_method, cashier_name, cashier_user_id,
                     payment_method_ids, payment_methods_json, status, created_at)
                 VALUES (:code, :branch, :session, :uid,
                         :subtotal, :discount, :total_cost, :total_service_fees, :total_add_ons, :total_profit,
                         :grand, :original, :paid, :change,
                         :payment_method, :cashier_name, :cashier_user_id,
                         :payment_method_ids, :payment_methods_json, 'completed', :created_at)",
                [
                    'code'                => $orderCode,
                    'branch'              => $branchId,
                    'session'             => $sessionId,
                    'uid'                 => $user['user_id'],
                    'subtotal'            => $orderTotal + $discountTotal,
                    'discount'            => $discountTotal,
                    'total_cost'          => $totalCost,
                    'total_service_fees'  => $totalServiceFees,
                    'total_add_ons'       => $totalAddOns,
                    'total_profit'        => $totalProfit,
                    'grand'               => $orderTotal,
                    'original'            => $orderTotal,
                    'paid'                => $totalPaid,
                    'change'              => $totalPaid - $orderTotal,
                    'payment_method'      => $primaryPaymentMethod,
                    'cashier_name'        => $cashierName,
                    'cashier_user_id'     => $cashierUserId,
                    'payment_method_ids'  => $paymentMethodIds,
                    'payment_methods_json'=> $paymentMethodsJson,
                    'created_at'          => date('Y-m-d H:i:s'),
                ]
            );
        } catch (Exception $e) {
            Database::connection()->rollBack();
            error_log("Failed to insert into pos_orders: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to create order: ' . $e->getMessage()]); exit;
        }

        $orderId = Database::connection()->lastInsertId();

        // Verify order was created successfully by querying it back
        $createdOrder = Database::fetch(
            "SELECT order_id, order_code FROM pos_orders WHERE order_id = :oid",
            ['oid' => $orderId]
        );

        if (!$createdOrder) {
            Database::connection()->rollBack();
            error_log("Order creation failed. lastInsertId returned: $orderId, but order not found in database.");
            echo json_encode(['success' => false, 'error' => 'Failed to create order record. Order was not persisted to database.']); exit;
        }

        // --- BIR Integration: Assign OR Number and Create VAT Transaction ---
        $orData = null;
        $vatData = null;
        
        // Get VAT type from input (default to 12_percent)
        $vatType = $input['vat_type'] ?? '12_percent';
        $exemptionType = $input['exemption_type'] ?? null;
        $exemptionIdNumber = $input['exemption_id_number'] ?? null;
        $exemptionName = $input['exemption_name'] ?? null;
        
        // Assign OR number if auto-assignment is enabled
        if (BIRHelper::isAutoORAssignmentEnabled()) {
            $orData = BIRHelper::assignORNumber($orderId, $branchId, $user['user_id']);
            if (!$orData) {
                error_log("Failed to assign OR number for order $orderId");
            }
        }
        
        // Create VAT transaction
        $vatData = BIRHelper::createVATTransaction($orderId, $orderTotal, $vatType, $exemptionType, $exemptionIdNumber, $exemptionName);
        if (!$vatData) {
            error_log("Failed to create VAT transaction for order $orderId");
        }
        
        // Log audit trail for order creation
        BIRHelper::logAuditTrail($orderId, $user['user_id'], 'create', 'pos_orders', $orderId, null, null, null, 'POS ticket order created');

        // --- Process each ticket ---
        $ticketTxnIds = [];
        $firstTxnCode = null;
        $firstTicketPassengerId = null;
        foreach ($tickets as $ticket) {
            if ($firstTicketPassengerId === null && !empty($ticket['passenger_id'])) {
                $firstTicketPassengerId = $ticket['passenger_id'];
            }
            $providerId = $ticket['provider_id'] ?? null;
            if (!$providerId) {
                Database::connection()->rollBack();
                echo json_encode(['success' => false, 'error' => 'Provider is required for each ticket.']); exit;
            }

            try {
                CashierTransportAccess::assertAllowed($user, (int) $providerId);
            } catch (Throwable $e) {
                Database::connection()->rollBack();
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]); exit;
            }

            // Determine selected ticket variant, if any
            $variantId = !empty($ticket['variant_id']) ? (int) $ticket['variant_id'] : null;

            // Resolve wallet. Prefer the wallet_id already resolved by the POS UI
            // (it may have fallen back to a parent provider), but validate it is active,
            // belongs to this transaction branch, and matches the selected provider/variant.
            $resolvedWallet = null;
            $walletIdFromTicket = !empty($ticket['wallet_id']) ? (int) $ticket['wallet_id'] : null;
            if ($walletIdFromTicket) {
                $walletFromTicket = Database::fetch(
                    "SELECT * FROM provider_wallets WHERE wallet_id = :wid AND status = 'active'",
                    ['wid' => $walletIdFromTicket]
                );

                if ($walletFromTicket) {
                    // Ensure the wallet belongs to this transaction branch and is accessible
                    $allowedBranches = PosAccess::allowedBranchIds($user);
                    $walletMatchesBranch = (int) $walletFromTicket['branch_id'] === (int) $branchId;
                    $canAccessBranch = $walletMatchesBranch
                        && ($allowedBranches === null
                            || in_array((int) $walletFromTicket['branch_id'], $allowedBranches, true));

                    if ($canAccessBranch) {
                        // Build the operating provider's ancestor chain so parent wallets are accepted
                        $allowedProviderIds = [(int)$providerId];
                        $currentProviderId = (int)$providerId;
                        while ($currentProviderId > 0) {
                            $parent = Database::fetch(
                                "SELECT parent_provider_id FROM ticket_providers WHERE provider_id = :pid",
                                ['pid' => $currentProviderId]
                            );
                            if (!$parent || empty($parent['parent_provider_id'])) {
                                break;
                            }
                            $currentProviderId = (int)$parent['parent_provider_id'];
                            $allowedProviderIds[] = $currentProviderId;
                        }

                        $walletVariantId = !empty($walletFromTicket['variant_id']) ? (int)$walletFromTicket['variant_id'] : null;
                        if (in_array((int)$walletFromTicket['provider_id'], $allowedProviderIds, true) &&
                            ($walletVariantId === null || $walletVariantId === $variantId)) {
                            $resolvedWallet = $walletFromTicket;
                        }
                    }
                }
            }

            // Fall back to WalletResolver if no valid wallet_id was supplied
            if (!$resolvedWallet) {
                $resolvedWallet = WalletResolver::resolve((int)$providerId, (int)$branchId, $variantId);
            }
            if (!$resolvedWallet) {
                Database::connection()->rollBack();
                echo json_encode(['success' => false, 'error' => 'No active wallet found for the selected provider, branch and variant.']); exit;
            }
            $walletId = $resolvedWallet['wallet_id'];

            // Validate variant belongs to provider and is active
            $stockControlled = false;
            if ($variantId) {
                $variant = TicketStockHelper::getVariant($variantId);
                if (!$variant || (int) $variant['provider_id'] !== (int) $providerId || !(bool) $variant['is_active']) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Selected ticket variant is invalid, not for this provider, or inactive.']); exit;
                }
                $stockControlled = (bool) $variant['stock_controlled'];
            }

            // Generate per-ticket transaction code: TKT-YYYYMMDD-HHMM-###
            $txnCode = 'TKT-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));
            if (!$firstTxnCode) $firstTxnCode = $txnCode;

            try {
                Database::execute(
                    "INSERT INTO ticket_transactions
                        (transaction_code, wallet_id, provider_id, branch_id, passenger_id, accommodation_id, discount_id, variant_id,
                         origin, destination, travel_date, ticket_number, ticket_action, ticket_notes,
                         base_amount, service_fee, discount_amount, total_amount, status,
                         cashier_session_id, created_by, created_at)
                     VALUES (:code, :wallet, :provider, :branch, :passenger, :accommodation_id, :discount_id, :variant_id,
                             :origin, :destination, :travel_date, :ticket_number, :ticket_action, :ticket_notes,
                             :base_amount, :service_fee, :discount_amount, :total_amount, 'booked',
                             :session, :uid, :created_at)",
                    [
                        'code'            => $txnCode,
                        'wallet'          => $walletId,
                        'provider'        => (int)$providerId,
                        'branch'          => $branchId,
                        'passenger'       => $ticket['passenger_id'] ?? null,
                        'accommodation_id'=> $ticket['accommodation_id'] ?? null,
                        'discount_id'     => $ticket['discount_id'] ?? null,
                        'variant_id'      => $variantId,
                        'origin'          => $ticket['origin'] ?? null,
                        'destination'     => $ticket['destination'] ?? null,
                        'travel_date'     => $ticket['travel_date'] ?? null,
                        'ticket_number'   => $ticket['ticket_number'] ?? null,
                        'ticket_action'   => !empty($ticket['ticket_action']) ? strtoupper(trim($ticket['ticket_action'])) : null,
                        'ticket_notes'    => is_string($ticket['ticket_notes'] ?? null) && trim($ticket['ticket_notes']) !== '' ? trim($ticket['ticket_notes']) : null,
                        'base_amount'     => floatval($ticket['base_amount'] ?? 0),
                        'service_fee'     => floatval($ticket['service_fee'] ?? 0),
                        'discount_amount' => floatval($ticket['discount_amount'] ?? 0),
                        'total_amount'    => floatval($ticket['total_amount'] ?? 0),
                        'session'         => $sessionId,
                        'uid'             => $user['user_id'],
                        'created_at'      => date('Y-m-d H:i:s'),
                    ]
                );
            } catch (Exception $e) {
                Database::connection()->rollBack();
                error_log("Failed to insert into ticket_transactions: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Failed to create ticket transaction: ' . $e->getMessage()]); exit;
            }

            $ticketTxnId = Database::connection()->lastInsertId();
            $ticketTxnIds[] = $ticketTxnId;

            // --- Deduct one physical ticket when a stock-controlled variant was selected ---
            if ($variantId && $stockControlled) {
                $ticketNumber = $ticket['ticket_number'] ?? $txnCode;
                try {
                    TicketStockHelper::deductForSale(
                        (int) $branchId,
                        (int) $providerId,
                        $variantId,
                        1,
                        (int) $user['user_id'],
                        [
                            'reference_type'     => 'TICKET_TRANSACTION',
                            'reference_id'       => $ticketTxnId,
                            'ticket_number_from' => $ticketNumber,
                            'ticket_number_to'   => $ticketNumber,
                            'remarks'            => 'POS sale',
                            'release_reserved'  => 1,
                            'session_id'        => (string) $sessionId,
                        ]
                    );
                } catch (Exception $stockEx) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Stock deduction failed: ' . $stockEx->getMessage()]); exit;
                }
            }

            // --- Write order item for this ticket ---
            try {
                Database::execute(
                    "INSERT INTO pos_order_items
                        (order_id, item_type, reference_id, transaction_code, ticket_number,
                         accommodation_id, discount_id, provider_id, wallet_id, passenger_id, variant_id, ticket_action, ticket_notes, description,
                         unit_price, service_fee, discount_amount, total_amount,
                         origin, destination, travel_date, created_at)
                     VALUES (:oid, 'TICKET', :ref, :code, :ticket_number,
                             :accommodation_id, :discount_id, :provider_id, :wallet_id, :passenger_id, :variant_id, :ticket_action, :ticket_notes, :description,
                             :unit_price, :service_fee, :discount_amount, :total,
                             :origin, :destination, :travel_date, :created_at)",
                    [
                        'oid'              => $orderId,
                        'ref'              => $ticketTxnId,
                        'code'             => $txnCode,
                        'ticket_number'    => $ticket['ticket_number'] ?? $txnCode,
                        'accommodation_id' => !empty($ticket['accommodation_id']) ? intval($ticket['accommodation_id']) : null,
                        'discount_id'      => !empty($ticket['discount_id']) ? intval($ticket['discount_id']) : null,
                        'provider_id'      => (int)$providerId,
                        'wallet_id'        => $walletId,
                        'passenger_id'     => $ticket['passenger_id'] ?? null,
                        'variant_id'       => $variantId,
                        'ticket_action'    => !empty($ticket['ticket_action']) ? strtoupper(trim($ticket['ticket_action'])) : null,
                        'ticket_notes'     => is_string($ticket['ticket_notes'] ?? null) && trim($ticket['ticket_notes']) !== '' ? trim($ticket['ticket_notes']) : null,
                        'description'      => $ticket['description'] ?? null,
                        'unit_price'       => floatval($ticket['base_amount'] ?? 0),
                        'service_fee'      => floatval($ticket['service_fee'] ?? 0),
                        'discount_amount'  => floatval($ticket['discount_amount'] ?? 0),
                        'total'            => floatval($ticket['total_amount'] ?? 0),
                        'origin'           => $ticket['origin'] ?? null,
                        'destination'      => $ticket['destination'] ?? null,
                        'travel_date'      => $ticket['travel_date'] ?? null,
                        'created_at'       => date('Y-m-d H:i:s'),
                    ]
                );
            } catch (Exception $e) {
                Database::connection()->rollBack();
                error_log("Failed to insert into pos_order_items for ticket. Order ID: $orderId, Ticket Txn ID: $ticketTxnId. Error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Failed to create order item for ticket: ' . $e->getMessage()]); exit;
            }

            logActivity($user['user_id'], 'CREATE_TICKET_TRANSACTION', 'POS', $txnCode, null,
                ['order_code' => $orderCode, 'ticket_id' => $ticketTxnId, 'provider_id' => (int)$providerId, 'wallet_id' => $walletId]);

            // --- Wallet balance deduction based on the resolved wallet owner's policy ---
            $walletPolicy = ProviderWalletDeductionService::forWallet((int) $walletId);
            $walletDebit = ProviderWalletDeductionService::saleDebit($ticket, $walletPolicy);

            if ($walletId && $walletDebit['amount'] > 0 && !$variantId) {
                $walletMovement = BalanceLedgerService::walletMovement(
                    (int) $walletId,
                    'SALE',
                    'OUT',
                    $walletDebit['amount'],
                    'ticket_transactions',
                    (int) $ticketTxnId,
                    "Ticket sale - {$walletDebit['mode']}. Base: {$walletDebit['base_amount']}, Service Fee: {$walletDebit['service_fee']}. Order: {$orderCode}, Txn: {$txnCode}",
                    (int) $user['user_id'],
                    'pos-sale:' . (int) $ticketTxnId,
                    null,
                    (bool) $allowWalletOverdraft
                );

                $changedWalletIds[] = (int) $walletId;
                logActivity($user['user_id'], 'WALLET_DEDUCTION', 'POS', $txnCode, null,
                    [
                        'wallet_id' => $walletId,
                        'deduction_mode' => $walletDebit['mode'],
                        'base_amount' => $walletDebit['base_amount'],
                        'service_fee' => $walletDebit['service_fee'],
                        'amount' => $walletDebit['amount'],
                        'balance_before' => $walletMovement['balance_before'],
                        'balance_after' => $walletMovement['balance_after'],
                        'wallet_txn_code' => $walletMovement['txn_code'],
                    ]);
            }

            // --- Record payment against ticket ---
            foreach ($payments as $pay) {
                $methodId    = $pay['payment_method_id'] ?? null;
                $amount      = floatval($pay['amount'] ?? 0);
                $refNum      = $pay['reference_number'] ?? null;
                $bankAcctId  = $pay['bank_account_id'] ?? null;
                $passengerId = $pay['passenger_id'] ?? null;
                if (!$methodId || $amount <= 0) continue;

                $methodInfo    = Database::fetch("SELECT * FROM payment_methods WHERE method_id = :id", ['id' => $methodId]);
                $confirmStatus = ($methodInfo && $methodInfo['requires_confirmation']) ? 'PENDING' : 'NOT_REQUIRED';
                $methodType    = $methodInfo['method_type'] ?? '';
                $tracksCredit  = !empty($methodInfo['tracks_credit']);

                if (in_array($methodType, ['BANK_TRANSFER', 'E_WALLET'], true) && !$bankAcctId) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Please select a bank account for ' . ($methodInfo['method_name'] ?? 'this payment method') . '.']); exit;
                }
                if ($bankAcctId) {
                    $bankAccount = Database::fetch(
                        "SELECT bank_account_id, branch_id, is_active
                         FROM bank_accounts
                         WHERE bank_account_id = :bank_account_id",
                        ['bank_account_id' => (int) $bankAcctId]
                    );
                    if (!$bankAccount || !(int) $bankAccount['is_active']
                        || ($bankAccount['branch_id'] !== null && (int) $bankAccount['branch_id'] !== (int) $branchId)) {
                        Database::connection()->rollBack();
                        echo json_encode(['success' => false, 'error' => 'Selected bank account is not available for this branch.']); exit;
                    }
                }

                // Auto-resolve passenger_id: use payment passenger_id or fall back to ticket's passenger
                $resolvedPassengerId = $passengerId ?: ($ticket['passenger_id'] ?? null);

                Database::execute(
                    "INSERT INTO transaction_payments
                        (source_type, source_id, payment_method_id, bank_account_id, amount, reference_number,
                         payment_date, confirmation_status, charged_to_passenger_id, cashier_session_id, created_by, created_at)
                     VALUES ('TICKET_TRANSACTION', :src, :method, :bank, :amount, :ref, CURDATE(), :confirm, :passenger, :session, :uid, :created_at)",
                    [
                        'src'       => $ticketTxnId,
                        'method'    => $methodId,
                        'bank'      => $bankAcctId ?: null,
                        'amount'    => $amount,
                        'ref'       => $refNum,
                        'confirm'   => $confirmStatus,
                        'passenger' => $resolvedPassengerId ?: null,
                        'session'   => $sessionId,
                        'uid'       => $user['user_id'],
                        'created_at' => date('Y-m-d H:i:s'),
                    ]
                );

                // Update cashier session payment type breakdown based on method type
                switch ($methodType) {
                    case 'CASH':
                        Database::execute("UPDATE cashier_sessions SET total_cash = total_cash + :amt WHERE session_id = :id", ['amt' => $amount, 'id' => $sessionId]);
                        break;
                    case 'BANK_TRANSFER':
                        Database::execute("UPDATE cashier_sessions SET total_bank_transfer = total_bank_transfer + :amt WHERE session_id = :id", ['amt' => $amount, 'id' => $sessionId]);
                        break;
                    case 'E_WALLET':
                        Database::execute("UPDATE cashier_sessions SET total_e_wallet = total_e_wallet + :amt WHERE session_id = :id", ['amt' => $amount, 'id' => $sessionId]);
                        break;
                    case 'CHARGE':
                        Database::execute("UPDATE cashier_sessions SET total_charge = total_charge + :amt WHERE session_id = :id", ['amt' => $amount, 'id' => $sessionId]);
                        break;
                    default:
                        Database::execute("UPDATE cashier_sessions SET total_other = total_other + :amt WHERE session_id = :id", ['amt' => $amount, 'id' => $sessionId]);
                        break;
                }
            }
        }

        // --- Create service transactions ---
        // Determine the default charge account for services from credit-tracking payments.
        $defaultChargePassengerId = null;
        foreach ($payments as $pay) {
            $methodId = $pay['payment_method_id'] ?? null;
            if (!$methodId) continue;

            $methodInfo = Database::fetch("SELECT tracks_credit FROM payment_methods WHERE method_id = :id", ['id' => $methodId]);
            if (!empty($methodInfo['tracks_credit']) && !empty($pay['passenger_id'])) {
                $defaultChargePassengerId = $pay['passenger_id'];
                break;
            }
        }
        if (!$defaultChargePassengerId) {
            foreach ($tickets as $ticket) {
                if (!empty($ticket['passenger_id'])) {
                    $defaultChargePassengerId = $ticket['passenger_id'];
                    break;
                }
            }
        }

        $serviceTxnIds = [];
        foreach ($services as $svc) {
            $serviceTypeId = $svc['service_type_id'] ?? null;
            if (!$serviceTypeId) continue;

            // Generate per-service transaction code: SVC-YYYYMMDD-HHMM-###
            $svcCode = 'SVC-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));

            Database::execute(
                "INSERT INTO service_transactions
                    (transaction_code, branch_id, service_type_id, passenger_id, description,
                     quantity, unit_price, total_amount, status, cashier_session_id, created_by, created_at)
                 VALUES (:code, :branch, :stype, :passenger, :desc, :qty, :price, :total, 'completed', :session, :uid, :created_at)",
                [
                    'code'      => $svcCode,
                    'branch'    => $branchId,
                    'stype'     => $serviceTypeId,
                    'passenger' => $defaultChargePassengerId,
                    'desc'      => $svc['description'] ?? null,
                    'qty'       => intval($svc['quantity'] ?? 1),
                    'price'     => floatval($svc['unit_price'] ?? 0),
                    'total'     => floatval($svc['total_amount'] ?? 0),
                    'session'   => $sessionId,
                    'uid'       => $user['user_id'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
            $serviceTxnId    = Database::connection()->lastInsertId();
            $serviceTxnIds[] = $serviceTxnId;

            // --- Write order item for this service ---
            Database::execute(
                "INSERT INTO pos_order_items
                    (order_id, item_type, reference_id, transaction_code,
                     service_type_id, passenger_id, description,
                     quantity, unit_price, service_fee, discount_amount, total_amount, created_at)
                 VALUES (:oid, 'SERVICE', :ref, :code,
                         :service_type_id, :passenger_id, :description,
                         :quantity, :unit_price, 0, 0, :total, :created_at)",
                [
                    'oid'             => $orderId,
                    'ref'             => $serviceTxnId,
                    'code'            => $svcCode,
                    'service_type_id' => $svc['service_type_id'] ?? null,
                    'passenger_id'    => $defaultChargePassengerId,
                    'description'     => $svc['description'] ?? null,
                    'quantity'        => intval($svc['quantity'] ?? 1),
                    'unit_price'      => floatval($svc['unit_price'] ?? 0),
                    'total'           => floatval($svc['total_amount'] ?? 0),
                    'created_at'      => date('Y-m-d H:i:s'),
                ]
            );

            logActivity($user['user_id'], 'CREATE_SERVICE_TRANSACTION', 'POS', $svcCode, null,
                ['order_code' => $orderCode, 'service_txn_id' => $serviceTxnId, 'service_type_id' => $serviceTypeId]);
        }

        // --- Post customer charges for credit-tracking payments ---
        // Ticket and add-on amounts are split proportionally so the customer
        // is charged the full order total with service fees handled by mode.
        if ($orderTotal > 0) {
            $paymentDate = date('Y-m-d H:i:s');
            foreach ($payments as $pay) {
                $methodId    = $pay['payment_method_id'] ?? null;
                $amount      = floatval($pay['amount'] ?? 0);
                $passengerId = $pay['passenger_id'] ?? null;
                if ($amount <= 0 || !$methodId) {
                    continue;
                }

                $methodInfo = Database::fetch("SELECT * FROM payment_methods WHERE method_id = :id", ['id' => $methodId]);
                if (empty($methodInfo['tracks_credit'])) {
                    continue;
                }

                $resolvedPassengerId = $passengerId ?: $firstTicketPassengerId;
                if (!$resolvedPassengerId) {
                    continue;
                }

                // Ticket portions (split proportionally among tickets, then base/fee within each ticket)
                foreach ($tickets as $ticket) {
                    $ticketTotal   = floatval($ticket['total_amount'] ?? 0);
                    if ($ticketTotal <= 0) continue;

                    $ticketPayment = round($amount * ($ticketTotal / $orderTotal), 2);
                    if ($ticketPayment <= 0) continue;

                    ChargeService::postTicketCharge(
                        $resolvedPassengerId,
                        $ticketPayment,
                        floatval($ticket['base_amount'] ?? 0),
                        floatval($ticket['service_fee'] ?? 0),
                        $ticketTotal,
                        $paymentDate
                    );
                }

                // Service add-ons are tracked separately from ticket base.
                $servicePayment = round($amount * ($servicesTotal / $orderTotal), 2);
                if ($servicePayment > 0) {
                    ChargeService::addToCustomerCharge($resolvedPassengerId, $servicePayment, 0, 0, $servicePayment);
                }
            }
        }

        // --- Update cashier session totals ---
        Database::execute(
            "UPDATE cashier_sessions SET total_sales = total_sales + :total WHERE session_id = :id",
            ['total' => $orderTotal, 'id' => $sessionId]
        );

        logActivity($user['user_id'], 'PROCESS_ORDER', 'POS', $orderCode, null,
            ['order_code' => $orderCode, 'order_id' => $orderId, 'total' => $orderTotal, 'tickets' => count($ticketTxnIds), 'services' => count($serviceTxnIds)]);

        // Commit transaction before notifying other POS clients.
        Database::connection()->commit();

        PusherService::triggerBranch((int) $branchId, 'pos.transaction.completed', [
            'branch_id' => (int) $branchId,
            'order_id' => (int) $orderId,
            'transaction_code' => $orderCode,
            'wallet_ids' => array_values(array_unique($changedWalletIds)),
            'completed_at' => date(DATE_ATOM),
        ]);

        $stockVariantIds = array_values(array_unique(array_filter(array_map(
            static fn (array $ticket): int => (int) ($ticket['variant_id'] ?? 0),
            $tickets
        ))));
        if ($stockVariantIds) {
            PusherService::triggerBranch((int) $branchId, 'ticket_stock.updated', [
                'branch_id' => (int) $branchId,
                'variant_ids' => $stockVariantIds,
                'source' => 'pos_sale',
                'changed_at' => date(DATE_ATOM),
            ]);
        }

        echo json_encode([
            'success'          => true,
            'message'          => 'Order processed successfully.',
            'transaction_code' => $orderCode,
            'order_id'         => $orderId,
            'ticket_ids'       => $ticketTxnIds,
            'total'            => $orderTotal,
            'paid'             => $totalPaid,
            'change'           => $totalPaid - $orderTotal,
            'or_number'        => $orData['or_full_number'] ?? null,
            'or_id'            => $orData['or_id'] ?? null,
            'vat_data'         => $vatData ?? null,
        ]);

        exit;

    } catch (Exception $e) {
        if (Database::connection()->inTransaction()) {
            Database::connection()->rollBack();
        }

        if ($requestedOrderCode !== '' && isDuplicateOrderCodeError($e)) {
            $existingOrder = findExistingPosOrder($requestedOrderCode, (int) $user['user_id'], $sessionId, $branchId);
            if ($existingOrder) {
                $ticketIds = array_map(
                    'intval',
                    array_column(
                        Database::fetchAll(
                            "SELECT reference_id
                             FROM pos_order_items
                             WHERE order_id = :order_id AND item_type = 'TICKET'",
                            ['order_id' => $existingOrder['order_id']]
                        ),
                        'reference_id'
                    )
                );
                echo json_encode([
                    'success'          => true,
                    'already_processed' => true,
                    'message'          => 'Order already processed.',
                    'transaction_code' => $existingOrder['order_code'],
                    'order_id'         => (int) $existingOrder['order_id'],
                    'ticket_ids'       => $ticketIds,
                    'total'            => (float) $existingOrder['grand_total'],
                    'paid'             => (float) $existingOrder['amount_paid'],
                    'change'           => (float) $existingOrder['change_amount'],
                ]);
                exit;
            }
        }

        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $lastError = $e->getMessage();
            continue;
        }

        if ($e->getMessage() === 'Insufficient wallet balance.') {
            echo json_encode([
                'success' => false,
                'code'    => 'INSUFFICIENT_WALLET_BALANCE',
                'error'   => 'The selected wallet balance is insufficient to cover the ticket base fare. Please top up the wallet or select another wallet.',
            ]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Transaction failed: ' . $e->getMessage(), 'debug' => $e->getTraceAsString()]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Failed to process order after ' . $maxRetries . ' attempts. Last error: ' . $lastError]);
