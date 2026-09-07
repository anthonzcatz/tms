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
      <?php include NAVBAR_POSITION === 'top' ? dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php' : dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?>
    <?php else: ?>
      <?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?>
    <?php endif; ?>
    <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      <div class="content">
        <?php include NAVBAR_POSITION === 'combo' ? dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php' : dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php'; ?>
    <?php endif; ?>
<div class="row g-4 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h4 class="mb-1 text-primary">Ticket Stock <span class="text-info">Requests</span></h4>
          <small class="text-muted">Request, approve, receive, and close ticket stock requests.</small>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createStockRequestModal"><span class="fas fa-plus me-1"></span>New Request</button>
      </div>
    </div>
  </div>
</div>
<?php $activeTicketStockModule = 'requests'; include dirname(dirname(__DIR__)) . '/_partials/ticket_stock_nav.php'; ?>
<div class="card border-0 shadow-sm mb-3 ticket-stock-filter-card">
  <div class="card-header bg-body-tertiary">
    <h6 class="mb-1 fw-bold"><span class="fas fa-filter me-2 text-primary"></span>Request filters</h6>
    <small class="text-muted">Filter requests by status, branch, provider, reason, today, month, year, or a custom date range.</small>
  </div>
  <div class="card-body">
    <form id="requestFilters" class="row g-3 align-items-end" onsubmit="event.preventDefault(); loadTicketStockRequests();">
      <div class="col-12 col-sm-6 col-lg-2">
        <label class="form-label small fw-semibold" for="requestStatus">Status</label>
        <select class="form-select" id="requestStatus">
          <option value="">All statuses</option>
          <option>DRAFT</option>
          <option>SUBMITTED</option>
          <option>APPROVED</option>
          <option>DISPATCHED</option>
          <option>PARTIALLY_RECEIVED</option>
          <option>RECEIVED</option>
          <option>REJECTED</option>
          <option>DISPUTED</option>
          <option>CLOSED</option>
          <option>CANCELLED</option>
        </select>
      </div>
      <div class="col-12 col-sm-6 col-lg-2">
        <label class="form-label small fw-semibold" for="requestBranch">Branch</label>
        <select class="form-select" id="requestBranch">
          <option value="">All accessible branches</option>
          <?php foreach ($branches as $branch): ?>
            <option value="<?php echo (int) $branch['branch_id']; ?>"><?php echo htmlspecialchars($branch['branch_name'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-sm-6 col-lg-2">
        <label class="form-label small fw-semibold" for="requestProvider">Provider</label>
        <select class="form-select" id="requestProvider">
          <option value="">All providers</option>
          <?php foreach ($providers as $provider): ?>
            <option value="<?php echo (int) $provider['provider_id']; ?>"><?php echo htmlspecialchars($provider['provider_code'] . ' - ' . $provider['provider_name'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-sm-6 col-lg-2">
        <label class="form-label small fw-semibold" for="requestReason">Reason</label>
        <select class="form-select" id="requestReason">
          <option value="">All reasons</option>
          <option value="REPLENISHMENT">Replenishment</option>
          <option value="OPENING_BALANCE">Opening balance</option>
          <option value="TRANSFER">Transfer</option>
          <option value="EMERGENCY">Emergency</option>
          <option value="RETURN_REPLACEMENT">Return replacement</option>
          <option value="OTHER">Other</option>
        </select>
      </div>
      <div class="col-12 col-sm-6 col-lg-2">
        <label class="form-label small fw-semibold" for="requestDateMode">Date period</label>
        <select class="form-select" id="requestDateMode">
          <option value="today" selected>Today</option>
          <option value="all">All dates</option>
          <option value="month">By month</option>
          <option value="range">Custom date range</option>
          <option value="year">By year (annual)</option>
        </select>
      </div>
      <div class="col-12 col-lg-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1"><span class="fas fa-filter me-1"></span>Apply</button>
        <button type="button" class="btn btn-falcon-default" title="Reset filters" aria-label="Reset filters" onclick="resetTicketStockRequestFilters()"><span class="fas fa-undo"></span></button>
      </div>
      <div class="col-12 col-sm-6 col-lg-3 d-none" id="requestMonthFilter">
        <label class="form-label small fw-semibold" for="requestFilterMonth">Month</label>
        <input type="month" class="form-control" id="requestFilterMonth">
      </div>
      <div class="col-12 col-sm-6 col-lg-3 d-none" id="requestDateFromFilter">
        <label class="form-label small fw-semibold" for="requestFilterDateFrom">Start date</label>
        <input type="date" class="form-control" id="requestFilterDateFrom">
      </div>
      <div class="col-12 col-sm-6 col-lg-3 d-none" id="requestDateToFilter">
        <label class="form-label small fw-semibold" for="requestFilterDateTo">End date</label>
        <input type="date" class="form-control" id="requestFilterDateTo">
      </div>
      <div class="col-12 col-sm-6 col-lg-3 d-none" id="requestYearFilter">
        <label class="form-label small fw-semibold" for="requestFilterYear">Year / annual</label>
        <input type="number" class="form-control" id="requestFilterYear" min="1000" max="9998" step="1" placeholder="YYYY">
      </div>
    </form>
  </div>
</div>
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm ticket-stock-table mb-0" id="ticketStockRequests">
        <thead class="table-light">
          <tr>
            <th>Request</th>
            <th>Request date</th>
            <th>Destination</th>
            <th>Provider</th>
            <th>Reason</th>
            <th>Qty Requested/Received</th>
            <th>Status</th>
            <th>Requested By</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody><tr><td colspan="9" class="text-center py-4">Loading...</td></tr></tbody>
      </table>
    </div>
  </div>
</div>
<?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<div class="modal fade" id="createStockRequestModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">New Stock Request</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Destination Branch <span class="text-danger">*</span></label>
            <select class="form-select" id="createDestinationBranch">
              <option value="">Select branch</option>
              <?php foreach ($branches as $branch): ?>
              <option value="<?php echo (int) $branch['branch_id']; ?>"><?php echo htmlspecialchars($branch['branch_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Provider <span class="text-danger">*</span></label>
            <select class="form-select" id="createProvider">
              <option value="">Select provider</option>
              <?php foreach ($providers as $provider): ?>
              <option value="<?php echo (int) $provider['provider_id']; ?>"><?php echo htmlspecialchars($provider['provider_code'] . ' - ' . $provider['provider_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Reason</label>
            <select class="form-select" id="createReason">
              <option>REPLENISHMENT</option>
              <option>OPENING_BALANCE</option>
              <option>EMERGENCY</option>
              <option>RETURN_REPLACEMENT</option>
              <option>OTHER</option>
            </select>
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Items <span class="text-danger">*</span></label>
          <div id="createRequestItems">
            <div class="row g-2 request-item-row mb-2">
              <div class="col-7">
                <select class="form-select request-item-variant">
                  <option value="">Select variant</option>
                  <?php foreach ($variants as $variant): ?>
                  <option value="<?php echo (int) $variant['variant_id']; ?>" data-provider-id="<?php echo (int) $variant['provider_id']; ?>"><?php echo htmlspecialchars($variant['variant_code'] . ' - ' . $variant['variant_name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-3">
                <input type="number" class="form-control request-item-qty" min="1" placeholder="Qty">
              </div>
              <div class="col-2 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRequestItemRow(this)"><span class="fas fa-times"></span></button>
              </div>
            </div>
          </div>
          <button class="btn btn-sm btn-outline-secondary" type="button" onclick="addRequestItemRow()"><span class="fas fa-plus me-1"></span>Add item</button>
        </div>

        <textarea class="form-control mt-3" id="createRequestRemarks" rows="2" placeholder="Remarks"></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-outline-primary" onclick="submitStockRequest(false)"><span class="fas fa-save me-1"></span>Save as Draft</button>
        <button class="btn btn-primary" onclick="submitStockRequest(true)"><span class="fas fa-paper-plane me-1"></span>Submit Request</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="stockRequestModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Stock Request Details</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="stockRequestDetails"></div>
        <div id="stockRequestActionForm" class="d-none mt-3"></div>
        <div class="mt-3">
          <label class="form-label small" for="requestActionReason">Reason / remarks <span class="text-muted">(used for reject, cancel, close)</span></label>
          <textarea class="form-control" id="requestActionReason" rows="2" placeholder="Enter reason or remarks"></textarea>
        </div>
        <input type="hidden" id="requestActionId">
        <input type="hidden" id="requestActionStatus">
      </div>
      <div class="modal-footer">
        <div id="stockRequestActionButtons" class="d-flex gap-2"></div>
        <div id="stockRequestActionConfirm" class="d-none d-flex gap-2"></div>
      </div>
    </div>
  </div>
</div>

<script>
  window.TICKET_STOCK_PAGE = 'requests';
  window.DEFAULT_DESTINATION_BRANCH_ID = <?php echo $defaultDestinationBranchId ? (int) $defaultDestinationBranchId : 'null'; ?>;
  window.DESTINATION_BRANCH_IDS = <?php echo $canViewAllTicketStock ? 'null' : json_encode($userBranchIds); ?>;
</script>
<script src="<?php echo BASE_URL; ?>/admin/ticket-stock/assets/js/ticket-stock.js?v=<?php echo filemtime(dirname(dirname(__DIR__)) . '/assets/js/ticket-stock.js'); ?>"></script>
<script>
function addRequestItemRow() {
  const container = document.getElementById('createRequestItems');
  const first = container.querySelector('.request-item-row');
  if (!first) return;
  const providerId = document.getElementById('createProvider').value;
  const selectedVariantIds = new Set([...container.querySelectorAll('.request-item-variant')].map(select => select.value).filter(Boolean));
  const hasAvailableVariant = [...first.querySelector('.request-item-variant').options].some(option => option.value && !selectedVariantIds.has(option.value) && (!providerId || option.dataset.providerId === String(providerId)));
  if (!hasAvailableVariant) {
    ticketStockToast('error', 'All available variants have already been added.');
    return;
  }
  const clone = first.cloneNode(true);
  const variantSelect = clone.querySelector('.request-item-variant');
  variantSelect.value = '';
  variantSelect.disabled = false;
  const qtyInput = clone.querySelector('.request-item-qty');
  if (qtyInput) qtyInput.value = '';
  container.appendChild(clone);
  updateRequestVariantOptions(providerId || '');
}

function removeRequestItemRow(btn) {
  const row = btn.closest('.request-item-row');
  if (!row) return;
  const container = document.getElementById('createRequestItems');
  if (container.querySelectorAll('.request-item-row').length <= 1) {
    const qtyInput = row.querySelector('.request-item-qty');
    if (qtyInput) qtyInput.value = '';
    const sel = row.querySelector('.request-item-variant');
    if (sel) sel.value = '';
    return;
  }
  row.remove();
}

async function submitStockRequest(submit) {
  const destinationBranchId = document.getElementById('createDestinationBranch').value;
  const providerId = document.getElementById('createProvider').value;
  if (!destinationBranchId) {
    ticketStockToast('error', 'Please select a destination branch.');
    return;
  }
  if (!providerId) {
    ticketStockToast('error', 'Please select a provider.');
    return;
  }
  const rawItems = [...document.querySelectorAll('.request-item-row')].map(row => ({
    variant_id: row.querySelector('.request-item-variant').value,
    requested_qty: row.querySelector('.request-item-qty').value
  })).filter(item => item.variant_id && item.requested_qty);
  if (new Set(rawItems.map(item => item.variant_id)).size !== rawItems.length) {
    ticketStockToast('error', 'Each variant can only be added once.');
    return;
  }
  const items = rawItems.map(item => ({ ...item, requested_qty: Number(item.requested_qty) }));
  if (!items.length) {
    ticketStockToast('error', 'Please add at least one valid item with a variant and quantity.');
    return;
  }
  if (items.some(item => !Number.isFinite(item.requested_qty) || item.requested_qty <= 0)) {
    ticketStockToast('error', 'Requested quantity must be greater than 0.');
    return;
  }
  try {
    await ticketStockMutate({
      action: 'request_create',
      destination_branch_id: destinationBranchId,
      provider_id: providerId,
      request_reason: document.getElementById('createReason').value,
      remarks: document.getElementById('createRequestRemarks').value.trim(),
      items: items.map(item => ({ ...item, requested_qty: String(item.requested_qty) })),
      submit
    });
    bootstrap.Modal.getInstance(document.getElementById('createStockRequestModal'))?.hide();
    ticketStockToast('success', submit ? 'Stock request submitted.' : 'Stock request saved as draft.');
    loadTicketStockRequests();
  } catch (error) {
    ticketStockToast('error', error.message);
  }
}
</script>
<?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
<?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
<?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
</body>
</html>
