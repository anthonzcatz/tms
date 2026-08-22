<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/database.php';

Auth::requireLogin();

$code = $_GET['code'] ?? '';
$settings = Database::fetch("SELECT * FROM system_settings WHERE setting_id = 1") ?: [];

$receipt = null;
if ($code) {
    $receipt = Database::fetch(
        "SELECT cp.payment_code, cp.amount_paid, cp.balance_before, cp.balance_after,
                cp.reference_number, cp.confirmation_status, cp.created_at,
                pm.method_name, bb.branch_name,
                pa.fullname AS customer_name, pa.mobile_number,
                CONCAT(e.first_name, IF(e.middle_name IS NOT NULL AND e.middle_name != '', CONCAT(' ', LEFT(e.middle_name, 1), '.'), ''), ' ', e.last_name) AS cashier_name
         FROM charge_payments cp
         LEFT JOIN payment_methods pm ON cp.payment_method_id = pm.method_id
         LEFT JOIN business_branches bb ON cp.branch_id = bb.branch_id
         LEFT JOIN passenger_accounts pa ON cp.passenger_id = pa.passenger_id
         LEFT JOIN user_accounts ua ON cp.created_by = ua.user_id
         LEFT JOIN employees e ON ua.emp_id = e.emp_id
         WHERE cp.payment_code = :code",
        ['code' => $code]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Receipt</title>
  <style>
    @page { size: auto; margin: 8mm; }
    body { background: #f5f5f5; font-family: 'Century Gothic', 'CenturyGothic', 'AppleGothic', sans-serif; margin: 0; padding: 16px; color: #333; font-size: 0.95rem; line-height: 1.35; }
    .receipt-container { max-width: 600px; margin: 0 auto; background: #fff; padding: 36px; border: 1px solid #e0e0e0; }
    .receipt-header { text-align: center; margin-bottom: 22px; padding-bottom: 14px; border-bottom: 1px solid #e0e0e0; }
    .receipt-header img { max-height: 55px; max-width: 140px; margin-bottom: 8px; }
    .receipt-header h2 { margin: 0; font-size: 1.35rem; font-weight: 600; color: #222; }
    .receipt-header .company-address,
    .receipt-header .company-contact { margin: 2px 0; color: #777; font-size: 0.8rem; line-height: 1.3; }
    .receipt-title { text-align: center; font-size: 1rem; font-weight: 600; margin: 20px 0 18px; text-transform: uppercase; letter-spacing: 1px; color: #444; }
    .receipt-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #eee; }
    .receipt-row .label { color: #555; font-weight: 600; }
    .receipt-row .value { font-weight: 500; text-align: right; max-width: 60%; color: #222; }
    .receipt-amount { text-align: center; margin: 22px 0; padding: 12px 0; border-top: 1px solid #e0e0e0; border-bottom: 1px solid #e0e0e0; }
    .receipt-amount .label { color: #666; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }
    .receipt-amount .amount { font-size: 1.75rem; font-weight: 600; color: #222; margin-top: 2px; }
    .receipt-signatures { margin-top: 44px; display: flex; justify-content: space-between; }
    .receipt-signatures div { width: 42%; border-top: 1px solid #333; padding-top: 4px; font-size: 0.8rem; text-align: center; color: #555; }
    .receipt-footer { margin-top: 28px; text-align: center; font-size: 0.75rem; color: #999; }
    .print-actions { text-align: center; margin: 20px 0; }
    .print-actions button { padding: 8px 20px; font-size: 0.95rem; cursor: pointer; border: none; border-radius: 4px; font-family: 'Century Gothic', 'CenturyGothic', 'AppleGothic', sans-serif; }
    .btn-primary { background: #0d6efd; color: #fff; }
    .btn-secondary { background: #6c757d; color: #fff; }
    .text-center { text-align: center; }
    .py-5 { padding-top: 3rem; padding-bottom: 3rem; }
    .text-danger { color: #dc3545; }
    @media print {
      body { background: #fff; padding: 0; }
      .receipt-container { border: none; margin: 0 auto; padding: 16px; }
      .print-actions, .no-print { display: none !important; }
    }
  </style>
</head>
<body>
  <?php if (!empty($receipt)):
    $statusMap = [
        'NOT_REQUIRED' => 'Confirmed',
        'CONFIRMED' => 'Confirmed',
        'PENDING' => 'Pending Confirmation',
        'REJECTED' => 'Rejected'
    ];
    $statusLabel = $statusMap[$receipt['confirmation_status']] ?? '—';
  ?>
  <div class="receipt-container">
    <div class="receipt-header">
      <?php if (!empty($settings['system_logo'])): ?>
      <img src="<?php echo BASE_URL . '/' . ltrim($settings['system_logo'], '/'); ?>" alt="Logo" style="max-height: 80px; max-width: 180px; margin-bottom: 12px;">
      <?php endif; ?>
      <h2><?php echo htmlspecialchars($settings['company_name'] ?? 'Company Name'); ?></h2>
      <?php if (!empty($settings['company_address'])): ?>
      <p class="company-address"><?php echo nl2br(htmlspecialchars($settings['company_address'])); ?></p>
      <?php endif; ?>
      <?php if (!empty($settings['company_contact_number'])): ?>
      <p class="company-contact">Contact: <?php echo htmlspecialchars($settings['company_contact_number']); ?></p>
      <?php endif; ?>
    </div>

    <div class="receipt-title">Official Receipt</div>

    <div class="receipt-row">
      <span class="label">Receipt No.</span>
      <span class="value"><?php echo htmlspecialchars($receipt['payment_code']); ?></span>
    </div>
    <div class="receipt-row">
      <span class="label">Date</span>
      <span class="value"><?php echo date('M d, Y h:i A', strtotime($receipt['created_at'])); ?></span>
    </div>
    <div class="receipt-row">
      <span class="label">Customer</span>
      <span class="value"><?php echo htmlspecialchars($receipt['customer_name'] ?? '—'); ?></span>
    </div>
    <?php if (!empty($receipt['mobile_number'])): ?>
    <div class="receipt-row">
      <span class="label">Contact</span>
      <span class="value"><?php echo htmlspecialchars($receipt['mobile_number']); ?></span>
    </div>
    <?php endif; ?>
    <div class="receipt-row">
      <span class="label">Branch</span>
      <span class="value"><?php echo htmlspecialchars($receipt['branch_name'] ?? '—'); ?></span>
    </div>
    <div class="receipt-row">
      <span class="label">Payment Method</span>
      <span class="value"><?php echo htmlspecialchars($receipt['method_name'] ?? '—'); ?></span>
    </div>
    <?php if (!empty($receipt['reference_number'])): ?>
    <div class="receipt-row">
      <span class="label">Reference No.</span>
      <span class="value"><?php echo htmlspecialchars($receipt['reference_number']); ?></span>
    </div>
    <?php endif; ?>
    <div class="receipt-row">
      <span class="label">Payment Status</span>
      <span class="value"><?php echo htmlspecialchars($statusLabel); ?></span>
    </div>

    <div class="receipt-amount">
      <div class="text-muted small">Amount Received</div>
      <div class="amount">₱<?php echo number_format((float) $receipt['amount_paid'], 2); ?></div>
    </div>

    <div class="receipt-row">
      <span class="label">Balance Before Payment</span>
      <span class="value">₱<?php echo number_format((float) $receipt['balance_before'], 2); ?></span>
    </div>
    <div class="receipt-row">
      <span class="label">Balance After Payment</span>
      <span class="value">₱<?php echo number_format((float) $receipt['balance_after'], 2); ?></span>
    </div>

    <div class="receipt-signatures">
      <div>Customer Signature</div>
      <div>Cashier / Authorized By</div>
    </div>

    <div class="receipt-footer">
      Thank you for your payment. Please keep this receipt for your records.
    </div>

    <div class="print-actions no-print">
      <button class="btn btn-primary" onclick="window.print()">
        <span class="fas fa-print me-1"></span>Print Receipt
      </button>
      <button class="btn btn-secondary ms-2" onclick="window.close()">Close</button>
    </div>
  </div>
  <?php else: ?>
  <div class="receipt-container text-center py-5">
    <p class="text-danger">Receipt not found.</p>
  </div>
  <?php endif; ?>
</body>
</html>
