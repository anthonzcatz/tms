<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php'; ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/ticket-stock/assets/css/ticket-stock.css?v=<?php echo filemtime(dirname(dirname(__DIR__)) . '/assets/css/ticket-stock.css'); ?>">
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

    <?php $movementDate = $movementDate ?? date('Y-m-d'); ?>
    <div class="row g-4 mb-4">
      <div class="col-12">
        <div class="card border-0 shadow-sm ticket-stock-page-header">
          <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
              <div class="ticket-stock-header-icon bg-primary-subtle text-primary">
                <span class="fas fa-exchange-alt"></span>
              </div>
              <div>
                <div class="text-uppercase text-muted fw-semibold fs-11 letter-spacing-1">Ticket Stock / Operations</div>
                <h4 class="mb-1 text-primary fw-bold">Movement Ledger</h4>
                <p class="mb-0 text-muted">Review every stock movement with its quantity change, balance trail, and source reference.</p>
              </div>
            </div>
            <div class="ticket-stock-date-chip">
              <span class="fas fa-calendar-day me-2 text-primary"></span>
              <span><?php echo htmlspecialchars(date('M j, Y', strtotime($movementDate)), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php $activeTicketStockModule = 'movements'; include dirname(dirname(__DIR__)) . '/_partials/ticket_stock_nav.php'; ?>

    <div class="card border-0 shadow-sm mb-3 ticket-stock-filter-card">
      <div class="card-header bg-body-tertiary d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <h6 class="mb-1 fw-bold"><span class="fas fa-filter me-2 text-primary"></span>Movement filters</h6>
          <small class="text-muted">Choose a date to focus the ledger. The current date is selected by default.</small>
        </div>
        <span class="badge badge-subtle-primary"><span class="fas fa-clock me-1"></span>Daily view</span>
      </div>
      <div class="card-body">
        <form id="movementFilters" class="row g-3 align-items-end" onsubmit="event.preventDefault(); loadTicketStockMovements();">
          <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label small fw-semibold" for="movementDate">Movement date</label>
            <input type="date" class="form-control" id="movementDate" value="<?php echo htmlspecialchars($movementDate, ENT_QUOTES, 'UTF-8'); ?>" aria-describedby="movementAutoFilterHint">
          </div>
          <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label small fw-semibold" for="movementBranch">Branch</label>
            <select class="form-select" id="movementBranch">
              <option value="">All accessible branches</option>
              <?php foreach ($branches as $branch): ?>
                <option value="<?php echo (int) $branch['branch_id']; ?>"><?php echo htmlspecialchars($branch['branch_name'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label small fw-semibold" for="movementProvider">Provider</label>
            <select class="form-select" id="movementProvider">
              <option value="">All providers</option>
              <?php foreach ($providers as $provider): ?>
                <option value="<?php echo (int) $provider['provider_id']; ?>"><?php echo htmlspecialchars($provider['provider_name'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label small fw-semibold" for="movementType">Movement type</label>
            <select class="form-select" id="movementType">
              <option value="">All movement types</option>
              <option value="OPENING_BALANCE">Opening balance</option>
              <option value="POS_SALE">POS sale</option>
              <option value="POS_SALE_REVERSAL">POS sale reversal</option>
              <option value="DISPATCH">Dispatch</option>
              <option value="RECEIPT">Receipt</option>
              <option value="ADJUSTMENT">Adjustment</option>
              <option value="DAMAGE_OR_VOID">Damage or void</option>
            </select>
          </div>
          <div class="col-12 col-sm-6 col-lg-1">
            <label class="form-label small fw-semibold" for="movementLimit">Rows</label>
            <select class="form-select" id="movementLimit">
              <option value="25">25</option>
              <option value="50" selected>50</option>
              <option value="100">100</option>
            </select>
          </div>
          <div class="col-12 col-lg-2 d-flex align-items-center justify-content-lg-end gap-2">
            <small id="movementAutoFilterHint" class="text-muted text-nowrap"><span class="fas fa-bolt text-warning me-1"></span>Auto-update</small>
            <button type="button" class="btn btn-falcon-default" title="Reset filters" aria-label="Reset filters" onclick="resetTicketStockMovementFilters()">
              <span class="fas fa-undo"></span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="card border-0 shadow-sm ticket-stock-movements-card">
      <div class="card-header bg-body-tertiary d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <h6 class="mb-1 fw-bold"><span class="fas fa-list-alt me-2 text-primary"></span>Movement history <span class="badge badge-subtle-primary ms-1" id="movementResultCount" aria-live="polite">0</span></h6>
          <small class="text-muted" id="movementResultSummary">Loading movements for the selected date...</small>
        </div>
        <span class="text-muted fs-11"><span class="fas fa-shield-alt me-1 text-success"></span>Immutable audit trail</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive ticket-stock-table-wrap">
          <table class="table table-hover align-middle ticket-stock-table ticket-stock-movements-table mb-0" id="ticketStockMovements">
            <thead class="ticket-stock-table-head text-uppercase fs-11">
              <tr>
                <th class="ps-3">Date &amp; time</th>
                <th>Movement</th>
                <th>Branch</th>
                <th>Provider</th>
                <th>Variant</th>
                <th class="text-end">Delta</th>
                <th class="text-end">Balance</th>
                <th>Reference</th>
                <th>Remarks</th>
                <th class="pe-3">Performed by</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="10" class="text-center text-muted py-5"><span class="fas fa-circle-notch fa-spin me-2"></span>Loading movements...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer bg-body-tertiary border-0">
        <small class="text-muted" id="movementResultFooter">Showing up to 50 movements</small>
      </div>
    </div>

    <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<script>
  window.TICKET_STOCK_PAGE = 'movements';
  window.TICKET_STOCK_DEFAULT_DATE = <?php echo json_encode($movementDate); ?>;
</script>
<script src="<?php echo BASE_URL; ?>/admin/ticket-stock/assets/js/ticket-stock.js?v=<?php echo filemtime(dirname(dirname(__DIR__)) . '/assets/js/ticket-stock.js'); ?>"></script>
<?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
<?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
<?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
</body>
</html>