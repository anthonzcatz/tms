<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/app/helpers/ChargeService.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/database.php';

Auth::requireLogin();

$passengerId = (int) ($_GET['passenger_id'] ?? 0);
$settings = Database::fetch("SELECT * FROM system_settings WHERE setting_id = 1") ?: [];

$customer = Database::fetch(
    "SELECT cc.*, pa.fullname, pa.mobile_number, pa.email,
            comp.fullname AS company_passenger_name
     FROM customer_charges cc
     JOIN passenger_accounts pa ON cc.passenger_id = pa.passenger_id
     LEFT JOIN passenger_accounts comp ON cc.company_passenger_id = comp.passenger_id
     WHERE cc.passenger_id = :pid",
    ['pid' => $passengerId]
);

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
        // The unallocated amount in balance is the outstanding add-on.
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Balance</title>
  <style>
    @page { size: auto; margin: 12mm; }
    * { box-sizing: border-box; }
    body { background: #eef1f5; font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif; margin: 0; padding: 24px; color: #1f2937; font-size: 0.95rem; line-height: 1.45; }
    .balance-container { max-width: 680px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; }
    .accent-bar { height: 6px; background: linear-gradient(90deg, #1e3a8a 0%, #3b82f6 100%); }
    .balance-header { text-align: center; padding: 32px 36px 24px; border-bottom: 2px solid #000; }
    .balance-header img { max-height: 64px; max-width: 160px; margin-bottom: 10px; }
    .balance-header h2 { margin: 0; font-size: 1.5rem; font-weight: 700; color: #111827; letter-spacing: -0.3px; }
    .balance-header .company-meta { margin-top: 8px; color: #6b7280; font-size: 0.82rem; line-height: 1.4; }
    .balance-body { padding: 32px 36px; }
    .balance-title { display: inline-block; font-size: 1.05rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 1.5px; margin: 0 0 22px; padding-bottom: 6px; border-bottom: 2px solid #000; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 32px; margin-bottom: 28px; }
    .info-grid .field { display: flex; flex-direction: column; }
    .info-grid .field .label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.8px; color: #6b7280; margin-bottom: 3px; }
    .info-grid .field .value { font-size: 0.98rem; color: #111827; font-weight: 600; }
    .balance-card { background: #f8fafc; border: 1px solid #000; border-radius: 10px; padding: 28px; margin-bottom: 28px; }
    .balance-card .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .balance-card .card-title { font-size: 0.82rem; text-transform: uppercase; letter-spacing: 1px; color: #4b5563; font-weight: 600; }
    .balance-card .amount { font-size: 2.2rem; font-weight: 800; color: #1e3a8a; letter-spacing: -0.5px; }
    .balance-breakdown { margin-top: 16px; padding-top: 16px; border-top: 1px dashed #000; font-size: 0.9rem; color: #4b5563; }
    .breakdown-row { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; }
    .breakdown-row:last-child { margin-bottom: 0; }
    .breakdown-row .label { font-weight: 600; color: #4b5563; }
    .breakdown-row .amount { font-weight: 700; color: #111827; font-size: 1.05rem; }
    .breakdown-row.muted .label,
    .breakdown-row.muted .amount { color: #6b7280; }
    .status-badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-outstanding { background: #fef3c7; color: #92400e; }
    .status-clear { background: #d1fae5; color: #065f46; }
    .notes { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 18px; border-radius: 0 6px 6px 0; font-size: 0.85rem; color: #78350f; }
    .notes strong { color: #92400e; }
    .print-actions { text-align: center; padding: 24px 36px 36px; border-top: 1px solid #e5e7eb; }
    .print-actions button { padding: 10px 24px; font-size: 0.95rem; cursor: pointer; border: none; border-radius: 6px; font-weight: 600; font-family: inherit; }
    .btn-primary { background: #1e3a8a; color: #fff; }
    .btn-secondary { background: #e5e7eb; color: #374151; margin-left: 10px; }
    .ms-1 { margin-left: 6px; }
    .me-1 { margin-right: 6px; }
    .text-center { text-align: center; }
    .py-5 { padding: 3rem 0; }
    .text-danger { color: #dc2626; }
    @media print {
      body { background: #fff; padding: 0; }
      .balance-container { box-shadow: none; border: none; border-radius: 0; }
      .print-actions, .no-print { display: none !important; }
      .notes { background: #fff; border-left: 4px solid #f59e0b; }
    }
  </style>
</head>
<body>
  <?php if (!empty($customer)):
    $status      = strtoupper($customer['status'] ?? 'CLEAR');
    $statusClass = ($status === 'CLEAR') ? 'status-clear' : 'status-outstanding';
  ?>
  <div class="balance-container">
    <div class="accent-bar"></div>
    <div class="balance-header">
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

    <div class="balance-body">
      <div class="balance-title">Account Balance</div>

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
        <div class="amount">₱<?php echo number_format($totalBalance, 2); ?></div>
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

      <div class="notes">
        <strong>Note:</strong> This document is valid as of the date shown above.
        <?php if ($serviceFeeMode !== 'CUSTOMER' && !empty($exemptEffectiveDate)): ?>
        Service fees for ticket transactions from <?php echo date('M d, Y h:i A', strtotime($exemptEffectiveDate)); ?> are
        <?php echo $serviceFeeMode === 'WAIVED' ? 'waived' : 'billed to ' . htmlspecialchars($companyPassengerName ?: 'the company/CEO account'); ?>.
        <?php endif; ?>
        Please settle your outstanding balance at your earliest convenience. For inquiries, contact <?php echo htmlspecialchars($settings['company_contact_number'] ?? 'our office'); ?>.
      </div>
    </div>

    <div class="print-actions no-print">
      <button class="btn btn-primary" onclick="window.print()">
        <span class="fas fa-print me-1"></span>Print
      </button>
      <button class="btn btn-secondary" onclick="window.close()">Close</button>
    </div>
  </div>
  <?php else: ?>
  <div class="balance-container">
    <div class="accent-bar"></div>
    <div class="text-center py-5">
      <p class="text-danger">Customer not found.</p>
    </div>
  </div>
  <?php endif; ?>
</body>
</html>
