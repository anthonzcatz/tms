<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/app/helpers/ChargeService.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/database.php';

Auth::requireLogin();

$passengerId = (int) ($_GET['passenger_id'] ?? 0);
$settings = Database::fetch("SELECT * FROM system_settings WHERE setting_id = 1") ?: [];

$baseBalance = 0.00;
$feeBalance = 0.00;
$addOnBalance = 0.00;
$baseDisplay = 0.00;
$showBreakdown = false;
$totalBalance = 0.00;

$customer = Database::fetch(
    "SELECT cc.*, pa.fullname, pa.mobile_number, pa.email,
            comp.fullname AS company_passenger_name
     FROM customer_charges cc
     JOIN passenger_accounts pa ON cc.passenger_id = pa.passenger_id
     LEFT JOIN passenger_accounts comp ON cc.company_passenger_id = comp.passenger_id
     WHERE cc.passenger_id = :pid",
    ['pid' => $passengerId]
);

if (!empty($customer)) {
    $serviceFeeMode       = $customer['service_fee_mode'] ?? 'CUSTOMER';
    $companyPassengerName = $customer['company_passenger_name'] ?? '';
    $exemptEffectiveDate  = $customer['exempt_effective_date'] ?? null;

    $modeLabels = [
        'CUSTOMER' => 'Customer pays full',
        'WAIVED'   => 'Waived',
        'COMPANY'  => 'Company / CEO'
    ];
    $modeLabel = $modeLabels[$serviceFeeMode] ?? $modeLabels['CUSTOMER'];

    $baseBalance   = (float) ($customer['base_balance'] ?? 0);
    $addOnBalance  = (float) ($customer['add_on_balance'] ?? 0);
    $feeBalance    = (float) ($customer['fee_balance'] ?? 0);
    $totalBalance  = (float) ($customer['balance'] ?? 0);

    // Fallback for rows not yet backfilled with add_on columns.
    if ($addOnBalance <= 0 && ($totalBalance > $baseBalance + $feeBalance + 0.001)) {
        $addOnTotal  = ChargeService::getAddOnTotalForCustomer($passengerId);

        if ($addOnTotal <= 0) {
            // Service add-on pos_order_items may be missing for old transactions.
            $addOnBalance = round($totalBalance - $baseBalance - $feeBalance, 2);
        } else {
            $baseCharged = (float) ($customer['base_charged'] ?? 0);
            $basePaid    = (float) ($customer['base_paid'] ?? 0);
            $addOnPaid   = max(0.00, round($basePaid - ($baseCharged - $addOnTotal), 2));
            $addOnBalance = max(0.00, min($addOnTotal - $addOnPaid, $totalBalance - $baseBalance - $feeBalance));
        }
    }

    $baseDisplay   = $baseBalance;
    $showBreakdown = $baseBalance > 0 || $addOnBalance > 0 || $feeBalance > 0;
}


$empNameConcat = "CONCAT(e.first_name, IF(e.middle_name IS NOT NULL AND e.middle_name != '', CONCAT(' ', LEFT(e.middle_name, 1), '.'), ''), ' ', e.last_name)";

$charges = Database::fetchAll(
    "SELECT tp.payment_id AS entry_id, 'charge' AS entry_type, tp.amount,
            tp.created_at,
            COALESCE(st.transaction_code, tt.transaction_code) AS txn_code,
            tt.ticket_number,
            CASE
                WHEN tp.source_type = 'TICKET_TRANSACTION' THEN ticket_passenger.fullname
                WHEN tp.source_type = 'SERVICE_TRANSACTION' THEN service_passenger.fullname
                ELSE NULL
            END AS transaction_passenger_name,
            CASE WHEN tp.source_type = 'TICKET_TRANSACTION' THEN 'Ticket' ELSE stype.name END AS item_label,
            pm.method_name, bb.branch_name, {$empNameConcat} AS cashier_name
     FROM transaction_payments tp
     JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
     LEFT JOIN cashier_sessions cs ON tp.cashier_session_id = cs.session_id
     LEFT JOIN business_branches bb ON cs.branch_id = bb.branch_id
     LEFT JOIN user_accounts ua ON tp.created_by = ua.user_id
     LEFT JOIN employees e ON ua.emp_id = e.emp_id
     LEFT JOIN service_transactions st ON tp.source_type = 'SERVICE_TRANSACTION' AND tp.source_id = st.service_txn_id
     LEFT JOIN service_types stype ON st.service_type_id = stype.service_type_id
     LEFT JOIN ticket_transactions tt ON tp.source_type = 'TICKET_TRANSACTION' AND tp.source_id = tt.transaction_id
     LEFT JOIN passenger_accounts ticket_passenger ON tt.passenger_id = ticket_passenger.passenger_id
     LEFT JOIN passenger_accounts service_passenger ON st.passenger_id = service_passenger.passenger_id
     WHERE pm.tracks_credit = 1 AND tp.charged_to_passenger_id = :pid
     ORDER BY tp.created_at ASC",
    ['pid' => $passengerId]
);

$payments = Database::fetchAll(
    "SELECT cp.charge_payment_id AS entry_id, 'payment' AS entry_type, cp.amount_paid AS amount,
            cp.created_at, cp.payment_code AS txn_code, NULL AS ticket_number,
            'Payment Collection' AS item_label, pm.method_name, bb.branch_name,
            {$empNameConcat} AS cashier_name, cp.reference_number, cp.confirmation_status
     FROM charge_payments cp
     LEFT JOIN payment_methods pm ON cp.payment_method_id = pm.method_id
     LEFT JOIN business_branches bb ON cp.branch_id = bb.branch_id
     LEFT JOIN user_accounts ua ON cp.created_by = ua.user_id
     LEFT JOIN employees e ON ua.emp_id = e.emp_id
     WHERE cp.passenger_id = :pid
     ORDER BY cp.created_at ASC",
    ['pid' => $passengerId]
);

$refundTables = Database::fetch(
    "SELECT COUNT(*) AS table_count
     FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name IN ('ticket_refunds', 'refund_allocations')"
);
$hasRefundAllocationTables = (int) ($refundTables['table_count'] ?? 0) === 2;

$ticketReversalSourceParts = [
    "SELECT tc_source.cancellation_id,
            original_charge.charged_to_passenger_id,
            SUM(original_charge.amount) AS reversal_amount
     FROM ticket_cancellations tc_source
     JOIN transaction_payments original_charge
       ON original_charge.source_type = 'TICKET_TRANSACTION'
      AND original_charge.source_id = tc_source.transaction_id
     JOIN payment_methods original_method
       ON original_method.method_id = original_charge.payment_method_id
      AND original_method.tracks_credit = 1
     WHERE tc_source.operation_type = 'VOID'
       AND original_charge.amount > 0
       AND original_charge.charged_to_passenger_id IS NOT NULL
       AND original_charge.confirmation_status <> 'REJECTED'
     GROUP BY tc_source.cancellation_id, original_charge.charged_to_passenger_id"
];

$legacyRefundAllocationExclusion = '';
if ($hasRefundAllocationTables) {
    $ticketReversalSourceParts[] =
        "SELECT tr.cancellation_id,
                original_charge.charged_to_passenger_id,
                SUM(ra.amount) AS reversal_amount
         FROM ticket_refunds tr
         JOIN refund_allocations ra
           ON ra.refund_scope = 'TICKET'
          AND ra.refund_id = tr.refund_id
          AND ra.refund_route = 'CHARGE_REVERSAL'
          AND ra.status = 'PROCESSED'
         JOIN transaction_payments original_charge
           ON original_charge.payment_id = ra.source_payment_id
         JOIN payment_methods original_method
           ON original_method.method_id = original_charge.payment_method_id
          AND original_method.tracks_credit = 1
         WHERE original_charge.source_type = 'TICKET_TRANSACTION'
           AND original_charge.amount > 0
           AND original_charge.charged_to_passenger_id IS NOT NULL
           AND original_charge.confirmation_status <> 'REJECTED'
         GROUP BY tr.cancellation_id, original_charge.charged_to_passenger_id";

    $legacyRefundAllocationExclusion =
        "AND NOT EXISTS (
            SELECT 1
            FROM ticket_refunds tr_existing
            JOIN refund_allocations ra_existing
              ON ra_existing.refund_scope = 'TICKET'
             AND ra_existing.refund_id = tr_existing.refund_id
             AND ra_existing.refund_route = 'CHARGE_REVERSAL'
             AND ra_existing.status = 'PROCESSED'
            WHERE tr_existing.cancellation_id = tc_source.cancellation_id
        )";
}

$ticketReversalSourceParts[] =
    "SELECT tc_source.cancellation_id,
            original_charge.charged_to_passenger_id,
            ROUND(
                tc_source.charge_amount * SUM(original_charge.amount)
                / NULLIF(total_charge.total_amount, 0),
                2
            ) AS reversal_amount
     FROM ticket_cancellations tc_source
     JOIN transaction_payments original_charge
       ON original_charge.source_type = 'TICKET_TRANSACTION'
      AND original_charge.source_id = tc_source.transaction_id
     JOIN payment_methods original_method
       ON original_method.method_id = original_charge.payment_method_id
      AND original_method.tracks_credit = 1
     JOIN (
         SELECT total_payment.source_id,
                SUM(total_payment.amount) AS total_amount
         FROM transaction_payments total_payment
         JOIN payment_methods total_method
           ON total_method.method_id = total_payment.payment_method_id
          AND total_method.tracks_credit = 1
         WHERE total_payment.source_type = 'TICKET_TRANSACTION'
           AND total_payment.amount > 0
           AND total_payment.charged_to_passenger_id IS NOT NULL
           AND total_payment.confirmation_status <> 'REJECTED'
         GROUP BY total_payment.source_id
     ) total_charge ON total_charge.source_id = tc_source.transaction_id
     WHERE COALESCE(tc_source.operation_type, 'REFUND') <> 'VOID'
       AND tc_source.charge_amount > 0
       AND original_charge.amount > 0
       AND original_charge.charged_to_passenger_id IS NOT NULL
       AND original_charge.confirmation_status <> 'REJECTED'
       {$legacyRefundAllocationExclusion}
     GROUP BY tc_source.cancellation_id,
              tc_source.charge_amount,
              original_charge.charged_to_passenger_id,
              total_charge.total_amount";

$ticketReversalSourceSql = implode(' UNION ALL ', $ticketReversalSourceParts);

$reversals = Database::fetchAll(
    "SELECT tc.cancellation_id AS entry_id,
            'reversal' AS entry_type,
            reversal_source.reversal_amount AS amount,
            tc.approved_at AS created_at,
            tc.transaction_code AS txn_code,
            tt.ticket_number,
            ticket_passenger.fullname AS transaction_passenger_name,
            CASE
                WHEN COALESCE(tc.operation_type, 'REFUND') = 'VOID' THEN 'Ticket Void'
                ELSE 'Ticket Cancellation'
            END AS item_label,
            NULL AS method_name,
            bb.branch_name,
            {$empNameConcat} AS cashier_name,
            tc.remarks AS notes,
            COALESCE(tc.operation_type, 'REFUND') AS operation_type
     FROM ticket_cancellations tc
     JOIN ({$ticketReversalSourceSql}) AS reversal_source
       ON reversal_source.cancellation_id = tc.cancellation_id
     LEFT JOIN ticket_transactions tt ON tc.transaction_id = tt.transaction_id
     LEFT JOIN passenger_accounts ticket_passenger ON tt.passenger_id = ticket_passenger.passenger_id
     LEFT JOIN cashier_sessions cs ON tc.cashier_session_id = cs.session_id
     LEFT JOIN business_branches bb ON cs.branch_id = bb.branch_id
     LEFT JOIN user_accounts ua ON tc.approved_by = ua.user_id
     LEFT JOIN employees e ON ua.emp_id = e.emp_id
     WHERE reversal_source.charged_to_passenger_id = :pid
       AND reversal_source.reversal_amount > 0
       AND tc.charge_amount > 0
       AND tc.status IN ('approved', 'completed')
     ORDER BY tc.approved_at ASC",
    ['pid' => $passengerId]
);

$entries = array_merge($charges, $payments, $reversals);
usort($entries, function ($a, $b) {
    return strcmp($a['created_at'], $b['created_at']);
});

$runningBalance = 0;
$entryDebits = 0.00;
$entryCredits = 0.00;
foreach ($entries as &$entry) {
    if ($entry['entry_type'] === 'charge') {
        $runningBalance += (float) $entry['amount'];
        $entry['debit'] = (float) $entry['amount'];
        $entry['credit'] = 0;
        $entryDebits += $entry['debit'];
    } else {
        $runningBalance -= (float) $entry['amount'];
        $entry['debit'] = 0;
        $entry['credit'] = (float) $entry['amount'];
        $entryCredits += $entry['credit'];
    }
    $entry['balance'] = $runningBalance;
}
unset($entry);

// If the service fee mode reallocated an outstanding fee away from this customer,
// add an adjustment row so the statement matches the current balance.
$customerBalance = (float) ($customer['balance'] ?? 0);
if ($runningBalance != $customerBalance && !empty($customer['exempt_effective_date']) && $serviceFeeMode !== 'CUSTOMER') {
    $reallocationAmount = round($runningBalance - $customerBalance, 2);
    if ($reallocationAmount > 0) {
        $reallocationLabel = $serviceFeeMode === 'WAIVED'
            ? 'Service fee waived'
            : 'Service fee billed to ' . ($companyPassengerName ?: 'company/CEO account');

        $entries[] = [
            'created_at'          => $customer['exempt_effective_date'],
            'entry_type'          => 'reallocation',
            'item_label'          => $reallocationLabel,
            'txn_code'            => '',
            'ticket_number'       => '',
            'method_name'         => '',
            'branch_name'         => '',
            'cashier_name'        => '',
            'reference_number'    => '',
            'confirmation_status' => '',
            'debit'               => 0,
            'credit'              => $reallocationAmount,
            'balance'             => $customerBalance,
            'source_type'         => null
        ];

        usort($entries, function ($a, $b) {
            return strcmp($a['created_at'], $b['created_at']);
        });

        $runningBalance = 0;
        $entryDebits = 0.00;
        $entryCredits = 0.00;
        foreach ($entries as &$entry) {
            if ($entry['entry_type'] === 'charge') {
                $runningBalance += (float) $entry['amount'];
                $entry['debit'] = (float) $entry['amount'];
                $entry['credit'] = 0;
                $entryDebits += $entry['debit'];
            } elseif ($entry['entry_type'] === 'reallocation') {
                $runningBalance -= (float) $entry['credit'];
                $entry['debit'] = 0;
                $entry['credit'] = (float) $entry['credit'];
                $entryCredits += $entry['credit'];
            } else {
                $runningBalance -= (float) $entry['amount'];
                $entry['debit'] = 0;
                $entry['credit'] = (float) $entry['amount'];
                $entryCredits += $entry['credit'];
            }
            $entry['balance'] = $runningBalance;
        }
        unset($entry);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Statement of Account</title>
  <style>
    @page { size: auto; margin: 12mm; }
    * { box-sizing: border-box; }
    body { background: #eef1f5; font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif; margin: 0; padding: 24px; color: #1f2937; font-size: 0.95rem; line-height: 1.45; }
    .statement-container { max-width: 760px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; }
    .accent-bar { height: 6px; background: linear-gradient(90deg, #1e3a8a 0%, #3b82f6 100%); }
    .statement-header { text-align: center; padding: 32px 36px 24px; border-bottom: 2px solid #000; }
    .statement-header img { max-height: 64px; max-width: 160px; margin-bottom: 10px; }
    .statement-header h2 { margin: 0; font-size: 1.5rem; font-weight: 700; color: #111827; letter-spacing: -0.3px; }
    .statement-header .company-meta { margin-top: 8px; color: #6b7280; font-size: 0.82rem; line-height: 1.4; }
    .statement-body { padding: 32px 36px; }
    .statement-title { display: inline-block; font-size: 1.05rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 1.5px; margin: 0 0 22px; padding-bottom: 6px; border-bottom: 2px solid #000; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 32px; margin-bottom: 28px; }
    .info-grid .field { display: flex; flex-direction: column; }
    .info-grid .field .label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.8px; color: #6b7280; margin-bottom: 3px; }
    .info-grid .field .value { font-size: 0.98rem; color: #111827; font-weight: 600; }
    .balance-card { background: #f8fafc; border: 1px solid #000; border-radius: 10px; padding: 24px; margin-bottom: 28px; }
    .balance-card .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .balance-card .card-title { font-size: 0.82rem; text-transform: uppercase; letter-spacing: 1px; color: #4b5563; font-weight: 600; }
    .balance-card .amount { font-size: 1.9rem; font-weight: 800; color: #1e3a8a; letter-spacing: -0.5px; }
    .balance-breakdown { margin-top: 14px; padding-top: 14px; border-top: 1px dashed #000; font-size: 0.9rem; color: #4b5563; }
    .breakdown-row { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; }
    .breakdown-row:last-child { margin-bottom: 0; }
    .breakdown-row .label { font-weight: 600; color: #4b5563; }
    .breakdown-row .amount { font-weight: 700; color: #111827; font-size: 1.05rem; }
    .breakdown-row.muted .label,
    .breakdown-row.muted .amount { color: #6b7280; }
    .status-badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-outstanding { background: #fef3c7; color: #92400e; }
    .status-overdue { background: #fee2e2; color: #991b1b; }
    .status-clear { background: #d1fae5; color: #065f46; }
    .status-written_off { background: #e5e7eb; color: #374151; }
    table { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 0.83rem; }
    th, td { padding: 9px 8px; text-align: left; vertical-align: top; }
    th { background: #f3f4f6; border-top: 2px solid #000; border-bottom: 2px solid #000; font-weight: 700; color: #111827; text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.75rem; }
    td { border-bottom: 1px solid #e5e7eb; }
    tbody tr { page-break-inside: avoid; }
    tbody tr:nth-child(even) { background: #fafafa; }
    tfoot td { border-top: 2px solid #000; border-bottom: none; font-weight: 700; }
    .text-end { text-align: right; }
    .text-muted { color: #6b7280; }
    .text-success { color: #15803d; }
    .text-danger { color: #b91c1c; }
    .text-info { color: #0e7490; }
    .notes { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 18px; border-radius: 0 6px 6px 0; font-size: 0.85rem; color: #78350f; margin-top: 28px; }
    .notes strong { color: #92400e; }
    .print-actions { text-align: center; padding: 24px 36px 36px; border-top: 1px solid #e5e7eb; }
    .print-actions button { padding: 10px 24px; font-size: 0.95rem; cursor: pointer; border: none; border-radius: 6px; font-weight: 600; font-family: inherit; }
    .btn-primary { background: #1e3a8a; color: #fff; }
    .btn-secondary { background: #e5e7eb; color: #374151; margin-left: 10px; }
    .me-1 { margin-right: 6px; }
    .text-center { text-align: center; }
    .py-5 { padding: 3rem 0; }
    @media print {
      body { background: #fff; padding: 0; }
      .statement-container { box-shadow: none; border: none; border-radius: 0; }
      .print-actions, .no-print { display: none !important; }
      .notes { background: #fff; border-left: 4px solid #f59e0b; }
      tbody tr:nth-child(even) { background: transparent; }
    }
  </style>
</head>
<body>
  <?php if (!empty($customer)):
    $status      = strtoupper($customer['status'] ?? 'CLEAR');
    $statusClassMap = [
        'OUTSTANDING'  => 'status-outstanding',
        'OVERDUE'      => 'status-overdue',
        'CLEAR'        => 'status-clear',
        'WRITTEN_OFF'  => 'status-written_off'
    ];
    $statusClass  = $statusClassMap[$status] ?? 'status-outstanding';
    $totalCharged = $entryDebits;
    $totalPaid    = $entryCredits;
    $balance      = $runningBalance;
  ?>
  <div class="statement-container">
    <div class="accent-bar"></div>
    <div class="statement-header">
      <?php if (!empty($settings['system_logo'])): ?>
      <img src="<?php echo BASE_URL . '/' . ltrim($settings['system_logo'], '/'); ?>" alt="Logo">
      <?php endif; ?>
      <h2><?php echo htmlspecialchars($settings['company_name'] ?? 'Company Name'); ?></h2>
      <?php if (!empty($settings['company_address'])): ?>
      <div class="company-meta"><?php echo nl2br(htmlspecialchars($settings['company_address'])); ?></div>
      <?php endif; ?>
      <?php if (!empty($settings['company_contact_number'])): ?>
      <div class="company-meta">Contact: <?php echo htmlspecialchars($settings['company_contact_number']); ?></div>
      <?php endif; ?>
    </div>

    <div class="statement-body">
      <div class="statement-title">Statement of Account</div>

      <div class="info-grid">
        <div class="field">
          <span class="label">Customer</span>
          <span class="value"><?php echo htmlspecialchars($customer['fullname']); ?></span>
        </div>
        <div class="field">
          <span class="label">Statement Date</span>
          <span class="value"><?php echo date('M d, Y h:i A'); ?></span>
        </div>
        <?php if (!empty($customer['mobile_number'])): ?>
        <div class="field">
          <span class="label">Contact Number</span>
          <span class="value"><?php echo htmlspecialchars($customer['mobile_number']); ?></span>
        </div>
        <?php endif; ?>
        <div class="field">
          <span class="label">Account Status</span>
          <span class="value">
            <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
          </span>
        </div>
        <?php if (!empty($customer['email'])): ?>
        <div class="field">
          <span class="label">Email</span>
          <span class="value"><?php echo htmlspecialchars($customer['email']); ?></span>
        </div>
        <?php endif; ?>
        <div class="field">
          <span class="label">Service Fee Mode</span>
          <span class="value">
            <?php echo htmlspecialchars($modeLabel); ?>
            <?php if ($serviceFeeMode === 'COMPANY' && !empty($companyPassengerName)): ?>
            <span class="text-muted">(<?php echo htmlspecialchars($companyPassengerName); ?>)</span>
            <?php elseif ($serviceFeeMode === 'WAIVED' && !empty($exemptEffectiveDate)): ?>
            <span class="text-muted">from <?php echo date('M d, Y', strtotime($exemptEffectiveDate)); ?></span>
            <?php endif; ?>
          </span>
        </div>
      </div>

      <div class="balance-card">
        <div class="card-header">
          <span class="card-title">Current Outstanding Balance</span>
          <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
        </div>
        <div class="amount">₱<?php echo number_format($balance, 2); ?></div>
        <?php if ($showBreakdown):
          $serviceFeeLabel = 'Service Fee';
          if ($serviceFeeMode === 'WAIVED') {
              $serviceFeeLabel = $feeBalance > 0 ? 'Service Fee (pre-waiver)' : 'Service Fee (waived)';
          } elseif ($serviceFeeMode === 'COMPANY') {
              $serviceFeeLabel = $feeBalance > 0 ? 'Service Fee (billed to company)' : 'Service Fee (billed to company)';
          }
          $feeRowClass = ($feeBalance <= 0 && $serviceFeeMode !== 'CUSTOMER') ? 'breakdown-row muted' : 'breakdown-row';
        ?>
        <div class="balance-breakdown">
          <div class="breakdown-row">
            <span class="label">Base</span>
            <span class="amount">₱<?php echo number_format($baseDisplay, 2); ?></span>
          </div>
          <?php if ($addOnBalance > 0): ?>
          <div class="breakdown-row">
            <span class="label">Service Add-ons</span>
            <span class="amount">₱<?php echo number_format($addOnBalance, 2); ?></span>
          </div>
          <?php endif; ?>
          <div class="<?php echo $feeRowClass; ?>">
            <span class="label"><?php echo htmlspecialchars($serviceFeeLabel); ?></span>
            <span class="amount">₱<?php echo number_format($feeBalance, 2); ?></span>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Description</th>
            <th>Ref / Code</th>
            <th>Branch</th>
            <th class="text-end">Debit (₱)</th>
            <th class="text-end">Credit (₱)</th>
            <th class="text-end">Balance (₱)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($entries)): ?>
          <tr>
            <td colspan="7" class="text-center text-muted py-5">No transaction history found.</td>
          </tr>
          <?php else: ?>
          <?php foreach ($entries as $e): ?>
          <tr>
            <td><?php echo date('M d, Y h:i A', strtotime($e['created_at'])); ?></td>
            <td>
              <?php if ($e['entry_type'] === 'charge'): ?>
                <span class="text-danger">CHARGE</span> — <?php echo htmlspecialchars($e['item_label'] ?? ''); ?>
                <?php if (!empty($e['transaction_passenger_name'])): ?>
                <div class="text-muted">Passenger: <?php echo htmlspecialchars($e['transaction_passenger_name']); ?></div>
                <?php endif; ?>
              <?php elseif ($e['entry_type'] === 'payment'):
                  $paymentStatusLabels = ['PENDING' => 'Pending Confirmation', 'CONFIRMED' => 'Confirmed', 'REJECTED' => 'Rejected'];
                  $paymentStatus = $paymentStatusLabels[$e['confirmation_status']] ?? '';
              ?>
                <span class="text-success">PAYMENT</span> — <?php echo htmlspecialchars($e['method_name'] ?? 'Cash'); ?>
                <?php if ($paymentStatus): ?>
                  <span class="text-muted">(<?php echo htmlspecialchars($paymentStatus); ?>)</span>
                <?php endif; ?>
              <?php elseif ($e['entry_type'] === 'reallocation'): ?>
                <span class="text-info">REALLOCATION</span> — <?php echo htmlspecialchars($e['item_label'] ?? ''); ?>
              <?php elseif ($e['entry_type'] === 'reversal'):
                  $reversalLabel = ($e['operation_type'] ?? '') === 'VOID' ? 'VOID / CHARGE REVERSAL' : 'CHARGE REVERSAL';
              ?>
                <span class="text-info"><?php echo htmlspecialchars($reversalLabel); ?></span> — <?php echo htmlspecialchars($e['item_label'] ?? ''); ?>
                <?php if (!empty($e['transaction_passenger_name'])): ?>
                <div class="text-muted">Passenger: <?php echo htmlspecialchars($e['transaction_passenger_name']); ?></div>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-info">REVERSAL</span> — <?php echo htmlspecialchars($e['item_label'] ?? ''); ?>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($e['txn_code'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($e['branch_name'] ?? '—'); ?></td>
            <td class="text-end"><?php echo $e['debit'] > 0 ? number_format($e['debit'], 2) : '—'; ?></td>
            <td class="text-end"><?php echo $e['credit'] > 0 ? number_format($e['credit'], 2) : '—'; ?></td>
            <td class="text-end"><strong><?php echo number_format($e['balance'], 2); ?></strong></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="4" class="text-end">Total Charged</td>
            <td class="text-end"><?php echo number_format($totalCharged, 2); ?></td>
            <td colspan="2"></td>
          </tr>
          <tr>
            <td colspan="5" class="text-end">Total Paid / Reversed</td>
            <td class="text-end"><?php echo number_format($totalPaid, 2); ?></td>
            <td></td>
          </tr>
          <tr>
            <td colspan="6" class="text-end">Current Outstanding Balance</td>
            <td class="text-end"><?php echo number_format($balance, 2); ?></td>
          </tr>
        </tfoot>
      </table>

      <div class="notes">
        <strong>Note:</strong> This is an official Statement of Account valid as of the date shown above.
        <?php if ($serviceFeeMode !== 'CUSTOMER' && !empty($exemptEffectiveDate)): ?>
        Service fees for ticket transactions from <?php echo date('M d, Y h:i A', strtotime($exemptEffectiveDate)); ?> are
        <?php echo $serviceFeeMode === 'WAIVED' ? 'waived' : 'billed to ' . htmlspecialchars($companyPassengerName ?: 'the company/CEO account'); ?>.
        <?php endif; ?>
        Please settle your outstanding balance at your earliest convenience. For inquiries, contact <?php echo htmlspecialchars($settings['company_contact_number'] ?? 'our office'); ?>.
      </div>
    </div>

    <div class="print-actions no-print">
      <button class="btn btn-primary" onclick="window.print()">
        <span class="fas fa-print me-1"></span>Print Statement
      </button>
      <button class="btn btn-secondary" onclick="window.close()">Close</button>
    </div>
  </div>
  <?php else: ?>
  <div class="statement-container">
    <div class="accent-bar"></div>
    <div class="text-center py-5">
      <p class="text-danger">Customer not found.</p>
    </div>
  </div>
  <?php endif; ?>
</body>
</html>
