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
              <h4 class="mb-1 text-primary">Ticket Stock <span class="text-info">Access</span></h4>
              <small class="text-muted">Assign users who may approve or receive stock requests for each branch.</small>
            </div>
            <button class="btn btn-primary" type="button" onclick="openTicketStockAccessModal()">
              <span class="fas fa-user-plus me-1"></span>New Assignment
            </button>
          </div>
        </div>
      </div>
    </div>

    <?php $activeTicketStockModule = 'access'; include dirname(dirname(__DIR__)) . '/_partials/ticket_stock_nav.php'; ?>

    <div class="card mb-3">
      <div class="card-body">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label small" for="ticketStockAccessBranchFilter">Branch</label>
            <select class="form-select" id="ticketStockAccessBranchFilter">
              <option value="">All branches</option>
              <?php foreach ($branches as $branch): ?>
                <option value="<?php echo (int) $branch['branch_id']; ?>"><?php echo htmlspecialchars($branch['branch_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small" for="ticketStockAccessTypeFilter">Access Type</label>
            <select class="form-select" id="ticketStockAccessTypeFilter">
              <option value="">All types</option>
              <option value="APPROVER">Approver</option>
              <option value="RECEIVER">Receiver</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small" for="ticketStockAccessStatusFilter">Status</label>
            <select class="form-select" id="ticketStockAccessStatusFilter">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
              <option value="">All statuses</option>
            </select>
          </div>
          <div class="col-md-2">
            <button class="btn btn-primary w-100" type="button" onclick="loadTicketStockAccessAssignments()">
              <span class="fas fa-filter me-1"></span>Apply
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm ticket-stock-table mb-0" id="ticketStockAccessTable">
            <thead class="table-light">
              <tr>
                <th>Branch</th>
                <th>User</th>
                <th>Role</th>
                <th>Access Type</th>
                <th>Status</th>
                <th>Created</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="7" class="text-center py-4">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<div class="modal fade" id="ticketStockAccessModal" tabindex="-1" aria-labelledby="ticketStockAccessModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ticketStockAccessModalLabel">New Ticket Stock Access Assignment</h5>
        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2">
          A user must still have the matching global Ticket Stock permission. This assignment limits the action to the selected branch.
        </div>
        <input type="hidden" id="ticketStockAccessAssignmentId">
        <div class="mb-3">
          <label class="form-label" for="ticketStockAccessUser">User <span class="text-danger">*</span></label>
          <select class="form-select" id="ticketStockAccessUser" required>
            <option value="">Select user</option>
            <?php foreach ($users as $assignmentUser): ?>
              <option value="<?php echo (int) $assignmentUser['user_id']; ?>">
                <?php echo htmlspecialchars(($assignmentUser['full_name'] ?: $assignmentUser['username']) . ' (' . $assignmentUser['role_code'] . ')'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label" for="ticketStockAccessBranch">Branch <span class="text-danger">*</span></label>
          <select class="form-select" id="ticketStockAccessBranch" required>
            <option value="">Select branch</option>
            <?php foreach ($branches as $branch): ?>
              <option value="<?php echo (int) $branch['branch_id']; ?>"><?php echo htmlspecialchars($branch['branch_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label" for="ticketStockAccessType">Access Type <span class="text-danger">*</span></label>
          <select class="form-select" id="ticketStockAccessType" required>
            <option value="APPROVER">Approver</option>
            <option value="RECEIVER">Receiver</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="button" onclick="saveTicketStockAccessAssignment()">
          <span class="fas fa-save me-1"></span>Save Assignment
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  window.TICKET_STOCK_ACCESS_CONFIG = {
    apiUrl: '<?php echo BASE_URL; ?>/api/ticket-stock-access',
    canManageAllBranches: <?php echo $canManageAllBranches ? 'true' : 'false'; ?>
  };
</script>
<script src="<?php echo BASE_URL; ?>/admin/ticket-stock/access/assets/js/access.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/access.js'); ?>"></script>
<?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
<?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
<?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
</body>
</html>
