<?php
/**
 * POS Tickets API — Process ticket bookings with mixed payments
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/BIRHelper.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/TicketStockHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

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

// Validate
if (!$sessionId)        { echo json_encode(['success' => false, 'error' => 'Session ID required.']); exit; }
if (!$branchId)         { echo json_encode(['success' => false, 'error' => 'Branch ID required.']); exit; }
if (empty($tickets))    { echo json_encode(['success' => false, 'error' => 'At least one ticket is required.']); exit; }
if (empty($payments))   { echo json_encode(['success' => false, 'error' => 'No payment provided.']); exit; }

// Verify session is open and belongs to the provided branch
$session = Database::fetch("SELECT * FROM cashier_sessions WHERE session_id = :id AND status = 'OPEN'", ['id' => $sessionId]);
if (!$session) { echo json_encode(['success' => false, 'error' => 'No active session found.']); exit; }
if ((int)$session['cashier_user_id'] !== (int)$user['user_id'] || (int)$session['branch_id'] !== (int)$branchId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Session/branch mismatch.']);
    exit;
}

// Read system settings once — used inside the transaction loop
$sysSettings = Database::fetch("SELECT pos_allow_insufficient_wallet FROM system_settings WHERE setting_id = 1") ?? [];
$allowWalletOverdraft = !empty($sysSettings['pos_allow_insufficient_wallet']);

// Process transaction with retry for duplicate key errors
$maxRetries = 50;
$lastError = null;

function generateOrderCode() {
    $today = date('Ymd');
    $prefix = 'ORD-' . $today;

    // Get the last order code for today
    $lastOrder = Database::fetch(
        "SELECT order_code FROM pos_orders WHERE order_code LIKE :prefix ORDER BY order_code DESC LIMIT 1",
        ['prefix' => $prefix . '%']
    );

    if ($lastOrder) {
        // Extract the sequence number from the last order code
        // Format: ORD-YYYYMMDD-HHMMSS-###
        $parts = explode('-', $lastOrder['order_code']);
        $lastSeq = (int)end($parts);
        $nextSeq = $lastSeq + 1;
    } else {
        // First order of the day
        $nextSeq = 1;
    }

    return 'ORD-' . date('Ymd-His') . '-' . sprintf('%03d', $nextSeq);
}

for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
    try {
        // Generate unique order code: ORD-YYYYMMDD-HHMM-### (sequential)
        $orderCode = generateOrderCode();

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
        foreach ($tickets as $ticket) {
            $providerId = $ticket['provider_id'] ?? null;
            if (!$providerId) {
                Database::connection()->rollBack();
                echo json_encode(['success' => false, 'error' => 'Provider is required for each ticket.']); exit;
            }

            // Determine selected ticket variant, if any
            $variantId = !empty($ticket['variant_id']) ? (int) $ticket['variant_id'] : null;

            // Resolve wallet. Prefer the wallet_id already resolved by the POS UI
            // (it may have fallen back to a parent provider or a different accessible
            // branch), but validate it is active and matches the selected provider/variant.
            $resolvedWallet = null;
            $walletIdFromTicket = !empty($ticket['wallet_id']) ? (int) $ticket['wallet_id'] : null;
            if ($walletIdFromTicket) {
                $walletFromTicket = Database::fetch(
                    "SELECT * FROM provider_wallets WHERE wallet_id = :wid AND status = 'active'",
                    ['wid' => $walletIdFromTicket]
                );

                if ($walletFromTicket) {
                    // Ensure the wallet branch is one the cashier may access
                    $allowedBranches = !empty($user['branch_id'])
                        ? array_filter(array_map('intval', explode(',', $user['branch_id'])))
                        : [];
                    $canAccessBranch = ($user['role_code'] === 'SUPER_ADMIN')
                        || empty($allowedBranches)
                        || in_array((int)$walletFromTicket['branch_id'], $allowedBranches, true);

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
            $isVariantWallet = !empty($resolvedWallet['variant_id']);

            // Validate variant belongs to provider and is active
            if ($variantId) {
                $variant = TicketStockHelper::getVariant($variantId);
                if (!$variant || (int) $variant['provider_id'] !== (int) $providerId || !(bool) $variant['is_active']) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Selected ticket variant is invalid, not for this provider, or inactive.']); exit;
                }
            }

            // Generate per-ticket transaction code: TKT-YYYYMMDD-HHMM-###
            $txnCode = 'TKT-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));
            if (!$firstTxnCode) $firstTxnCode = $txnCode;

            try {
                Database::execute(
                    "INSERT INTO ticket_transactions
                        (transaction_code, wallet_id, provider_id, branch_id, passenger_id, accommodation_id, discount_id, variant_id,
                         origin, destination, travel_date, ticket_number,
                         base_amount, service_fee, discount_amount, total_amount, status,
                         cashier_session_id, created_by, created_at)
                     VALUES (:code, :wallet, :provider, :branch, :passenger, :accommodation_id, :discount_id, :variant_id,
                             :origin, :destination, :travel_date, :ticket_number,
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

            // --- Deduct ticket stock when a variant was selected and is NOT backed by a variant-specific wallet ---
            if ($variantId && !$isVariantWallet) {
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
                         accommodation_id, discount_id, provider_id, wallet_id, passenger_id, variant_id, description,
                         unit_price, service_fee, discount_amount, total_amount,
                         origin, destination, travel_date, created_at)
                     VALUES (:oid, 'TICKET', :ref, :code, :ticket_number,
                             :accommodation_id, :discount_id, :provider_id, :wallet_id, :passenger_id, :variant_id, :description,
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

            // --- Wallet balance deduction (Base Amount only, NOT Service Fee) ---
            $baseAmount = floatval($ticket['base_amount'] ?? 0);

            if ($walletId && $baseAmount > 0) {
                $wallet = Database::fetch(
                    "SELECT * FROM provider_wallets WHERE wallet_id = :wid AND status = 'active' FOR UPDATE",
                    ['wid' => $walletId]
                );
                if (!$wallet) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Wallet not found or inactive.']); exit;
                }
                if ($wallet['current_balance'] < $baseAmount && !$allowWalletOverdraft) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Insufficient wallet balance. Required: ₱' . number_format($baseAmount, 2) . ', Available: ₱' . number_format($wallet['current_balance'], 2) . '. Contact your manager to top up the provider wallet or enable overdraft in System Settings > POS Settings.']); exit;
                }

                $balanceBefore = $wallet['current_balance'];
                $balanceAfter  = $balanceBefore - $baseAmount;

                Database::execute(
                    "UPDATE provider_wallets SET current_balance = :new_balance, updated_at = :updated_at WHERE wallet_id = :wid",
                    ['new_balance' => $balanceAfter, 'updated_at' => date('Y-m-d H:i:s'), 'wid' => $walletId]
                );

                $walletTxnCode = 'SALE-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));
                Database::execute(
                    "INSERT INTO wallet_transactions
                        (wallet_id, txn_code, txn_type, direction, amount, balance_before, balance_after, reference_table, reference_id, remarks, created_by, created_at)
                     VALUES (:wid, :code, 'SALE', 'OUT', :amount, :before, :after, 'ticket_transactions', :ref_id, :remarks, :uid, :created_at)",
                    [
                        'wid'    => $walletId,
                        'code'   => $walletTxnCode,
                        'amount' => $baseAmount,
                        'before' => $balanceBefore,
                        'after'  => $balanceAfter,
                        'ref_id' => $ticketTxnId,
                        'remarks'=> "Ticket sale - Base Amount only. Order: {$orderCode}, Txn: {$txnCode}",
                        'uid'    => $user['user_id'],
                        'created_at' => date('Y-m-d H:i:s'),
                    ]
                );

                logActivity($user['user_id'], 'WALLET_DEDUCTION', 'POS', $txnCode, null,
                    ['wallet_id' => $walletId, 'amount' => $baseAmount, 'balance_before' => $balanceBefore, 'balance_after' => $balanceAfter]);
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

                // Auto-resolve passenger_id: use payment passenger_id or fall back to ticket's passenger
                $resolvedPassengerId = $passengerId ?: ($ticket['passenger_id'] ?? null);

                // Handle credit-tracking payments — post to customer_charges
                if ($tracksCredit && $resolvedPassengerId) {
                    // Lock the row because we will update the aggregate in the same transaction.
                    $existingCharge = Database::fetch(
                        "SELECT * FROM customer_charges WHERE passenger_id = :pid FOR UPDATE",
                        ['pid' => $resolvedPassengerId]
                    );
                    if (!$existingCharge) {
                        Database::execute(
                            "INSERT INTO customer_charges (passenger_id, total_charged, total_paid, balance, status, last_charge_date)
                             VALUES (:pid, 0, 0, 0, 'CLEAR', :last_charge_date)",
                            ['pid' => $resolvedPassengerId, 'last_charge_date' => date('Y-m-d H:i:s')]
                        );
                    }
                    // Fixed CASE WHEN END
                    Database::execute(
                        "UPDATE customer_charges
                         SET total_charged = total_charged + :amt1,
                             balance = balance + :amt2,
                             status = CASE WHEN (balance + :amt3) > 0 THEN 'OUTSTANDING' ELSE 'CLEAR' END,
                             last_charge_date = :last_charge_date,
                             updated_at = :updated_at
                         WHERE passenger_id = :pid",
                        ['pid' => $resolvedPassengerId, 'amt1' => $amount, 'amt2' => $amount, 'amt3' => $amount, 'last_charge_date' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
                    );
                }

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
            }
        }

        // --- Create service transactions ---
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
                    'passenger' => $svc['passenger_id'] ?? null,
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
                    'passenger_id'    => $svc['passenger_id'] ?? null,
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

        // --- Update cashier session totals ---
        Database::execute(
            "UPDATE cashier_sessions SET total_sales = total_sales + :total WHERE session_id = :id",
            ['total' => $orderTotal, 'id' => $sessionId]
        );

        logActivity($user['user_id'], 'PROCESS_ORDER', 'POS', $orderCode, null,
            ['order_code' => $orderCode, 'order_id' => $orderId, 'total' => $orderTotal, 'tickets' => count($ticketTxnIds), 'services' => count($serviceTxnIds)]);

        // Commit transaction
        Database::connection()->commit();

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

        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $lastError = $e->getMessage();
            continue;
        }

        echo json_encode(['success' => false, 'error' => 'Transaction failed: ' . $e->getMessage(), 'debug' => $e->getTraceAsString()]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Failed to process order after ' . $maxRetries . ' attempts. Last error: ' . $lastError]);
