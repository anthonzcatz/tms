<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php'; ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/ticket-stock/assets/css/ticket-stock.css">
<body>
<main class="main" id="top">
  <div class="container" data-layout="container">
    <script>
      var isFluid = JSON.parse(localStorage.getItem('isFluid'));
      if (isFluid) {
        var container = document.querySelector('[data-layout]');
        container.classList.remove('container');
        container.classList.add('container-fluid');
      }
    </script>
    <?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?>
      <?php if (NAVBAR_POSITION === 'top'): ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?>
      <?php else: ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?>
      <?php endif; ?>
    <?php else: ?>
      <?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?>
    <?php endif; ?>
    <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      <div class="content">
        <?php if (NAVBAR_POSITION === 'combo'): ?>
          <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?>
        <?php else: ?>
          <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
      <div class="row g-4 mb-4"><div class="col-12"><div class="card border-0 shadow-sm"><div class="card-header"><div class="d-flex justify-content-between align-items-start gap-3"><div><h4 class="mb-1 text-primary">Ticket Stock <span class="text-info">Balances</span></h4><small class="text-muted">Current on-hand, reserved, available, and reorder quantities by branch.</small></div><small id="ticketStockRealtimeStatus" class="text-muted text-nowrap"><span class="fas fa-circle-notch fa-spin me-1"></span>Connecting...</small></div></div></div></div>
      <?php $activeTicketStockModule = 'balances'; include dirname(dirname(__DIR__)) . '/_partials/ticket_stock_nav.php'; ?>

      <div class="row g-3 mb-3">
        <?php foreach ([['balanceTotal','Rows','fa-list'],['balanceOnHand','On Hand','fa-boxes'],['balanceReserved','Reserved','fa-lock'],['balanceAvailable','Available','fa-check-circle']] as $stat): ?>
          <div class="col-sm-6 col-lg-3"><div class="card ticket-stock-stat h-100"><div class="card-body"><div class="text-muted small"><?php echo $stat[1]; ?></div><div class="d-flex justify-content-between align-items-end"><strong class="fs-4" id="<?php echo $stat[0]; ?>">0</strong><span class="fas <?php echo $stat[2]; ?> text-primary fs-3"></span></div></div></div></div>
        <?php endforeach; ?>
      </div>

      <div class="card mb-3"><div class="card-body"><div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small">Branch</label><select class="form-select" id="balanceBranch"><option value="">All accessible branches</option><?php foreach ($branches as $branch): ?><option value="<?php echo (int) $branch['branch_id']; ?>"><?php echo htmlspecialchars($branch['branch_name']); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small">Provider</label><select class="form-select" id="balanceProvider"><option value="">All providers</option><?php foreach ($providers as $provider): ?><option value="<?php echo (int) $provider['provider_id']; ?>"><?php echo htmlspecialchars($provider['provider_code'] . ' - ' . $provider['provider_name']); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small">Variant</label><select class="form-select" id="balanceVariant"><option value="">All variants</option><?php foreach ($variants as $variant): ?><option value="<?php echo (int) $variant['variant_id']; ?>"><?php echo htmlspecialchars($variant['variant_code'] . ' - ' . $variant['variant_name']); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1" onclick="loadTicketStockBalances()"><span class="fas fa-filter me-1"></span>Apply</button><button class="btn btn-outline-secondary" onclick="location.reload()"><span class="fas fa-sync"></span></button></div>
      </div></div></div>

      <div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover ticket-stock-table mb-0" id="ticketStockBalances"><thead class="table-light"><tr><th>Branch</th><th>Provider</th><th>Variant</th><th class="text-end">On Hand</th><th class="text-end">Reserved</th><th class="text-end">Available</th><th class="text-end">Reorder</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody><tr><td colspan="9" class="text-center py-4">Loading...</td></tr></tbody></table></div></div></div>
    <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<div class="modal fade" id="stockAdjustmentModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Adjust Ticket Stock</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" id="adjustStockBranch"><input type="hidden" id="adjustStockProvider"><input type="hidden" id="adjustStockVariant"><input type="hidden" id="adjustStockCurrentOnHand"><div class="alert alert-info">Current on-hand: <strong id="adjustStockCurrent">0</strong></div><label class="form-label">New on-hand quantity</label><input type="number" class="form-control" id="adjustStockNewOnHand" placeholder="e.g. 20"><label class="form-label mt-3">Reason</label><textarea class="form-control" id="adjustStockReason" rows="3"></textarea></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" onclick="submitStockAdjustment()">Save Adjustment</button></div></div></div></div>
<div class="modal fade" id="stockReceiptModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Top Up Ticket Stock</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" id="receiveStockBranch"><input type="hidden" id="receiveStockProvider"><input type="hidden" id="receiveStockVariant"><div class="alert alert-info">Current on-hand: <strong id="receiveStockCurrent">0</strong> tickets</div><label class="form-label" for="receiveStockQty">Quantity to receive</label><input type="number" min="1" step="1" class="form-control" id="receiveStockQty" placeholder="e.g. 20"><label class="form-label mt-3" for="receiveStockReason">Reason / reference</label><textarea class="form-control" id="receiveStockReason" rows="3" placeholder="e.g. Provider replenishment"></textarea></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success" onclick="submitStockReceipt()">Top Up Stock</button></div></div></div></div>
<script>
  window.TICKET_STOCK_PAGE = 'balances';
  window.TICKET_STOCK_REALTIME_CONFIG = {
    pusher: {
      enabled: <?php echo $pusherConfigured ? 'true' : 'false'; ?>,
      key: <?php echo json_encode($pusherKey); ?>,
      cluster: <?php echo json_encode($pusherCluster); ?>,
      authEndpoint: <?php echo json_encode(BASE_URL . '/api/pusher/auth'); ?>,
      branchIds: <?php echo json_encode($realtimeBranchIds); ?>
    }
  };
</script>
<?php if ($pusherConfigured): ?><script src="https://js.pusher.com/8.4.0/pusher.min.js"></script><?php endif; ?>
<script src="<?php echo BASE_URL; ?>/admin/assets/js/branch-realtime.js?v=<?php echo filemtime(dirname(dirname(dirname(__DIR__))) . '/assets/js/branch-realtime.js'); ?>"></script>
<script src="<?php echo BASE_URL; ?>/admin/ticket-stock/assets/js/ticket-stock.js?v=<?php echo filemtime(dirname(dirname(__DIR__)) . '/assets/js/ticket-stock.js'); ?>"></script>
<?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
<?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
<?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
</body></html>
