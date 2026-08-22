<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php'; ?><link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/ticket-stock/assets/css/ticket-stock.css">
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
      <?php include NAVBAR_POSITION === 'top' ? dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php' : dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?>
    <?php else: ?>
      <?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?>
    <?php endif; ?>
    <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      <div class="content">
        <?php include NAVBAR_POSITION === 'combo' ? dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php' : dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php'; ?>
    <?php endif; ?>
<div class="row g-4 mb-4"><div class="col-12"><div class="card border-0 shadow-sm"><div class="card-header"><h4 class="mb-1 text-primary">Ticket Stock <span class="text-info">Discrepancies</span></h4><small class="text-muted">Investigate and resolve shortages, excess, wrong variants, and serial mismatches.</small></div></div></div></div>
<?php $activeTicketStockModule = 'discrepancies'; include dirname(dirname(__DIR__)) . '/_partials/ticket_stock_nav.php'; ?>
<div class="card mb-3"><div class="card-body"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label small">Status</label><select class="form-select" id="discrepancyStatus"><option value="">All statuses</option><option>OPEN</option><option>INVESTIGATING</option><option>RESOLVED</option><option>WRITTEN_OFF</option></select></div><div class="col-md-2"><button class="btn btn-primary w-100" onclick="loadTicketStockDiscrepancies()"><span class="fas fa-filter me-1"></span>Apply</button></div></div></div></div>
<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover table-sm ticket-stock-table mb-0" id="ticketStockDiscrepancies"><thead class="table-light"><tr><th>Created</th><th>Request</th><th>Variant</th><th>Type</th><th class="text-end">Expected</th><th class="text-end">Actual</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody><tr><td colspan="8" class="text-center py-4">Loading...</td></tr></tbody></table></div></div></div>
<?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      </div>
    <?php endif; ?>
  </div>
</main><script>window.TICKET_STOCK_PAGE = 'discrepancies';</script><script src="<?php echo BASE_URL; ?>/admin/ticket-stock/assets/js/ticket-stock.js"></script><?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?></body></html>
