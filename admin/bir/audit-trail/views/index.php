<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php'; ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/bir/assets/css/bir.css">
<body>
  <main class="main" id="top">
    <div class="container" data-layout="container">
      <script>var isFluid = JSON.parse(localStorage.getItem('isFluid')); if (isFluid) { var container = document.querySelector('[data-layout]'); container.classList.remove('container'); container.classList.add('container-fluid'); }</script>
      <?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?>
      <div class="content">
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php'; ?>

        <div class="row g-4 mb-4">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center">
                    <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Audit Trail</h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard/">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/bir/">BIR</a></li>
                            <li class="breadcrumb-item active">Audit Trail</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto">
                    <a href="<?php echo BASE_URL; ?>/admin/bir/" class="btn btn-sm btn-outline-secondary"><span class="fas fa-arrow-left me-1"></span>Back</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="card mb-3">
          <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
              <div class="col-md-2"><label class="form-label small">Action</label><select name="action" class="form-select form-select-sm"><option value="">All</option><option value="create" <?php echo $filters['actionFilter'] === 'create' ? 'selected' : ''; ?>>Create</option><option value="modify" <?php echo $filters['actionFilter'] === 'modify' ? 'selected' : ''; ?>>Modify</option><option value="void" <?php echo $filters['actionFilter'] === 'void' ? 'selected' : ''; ?>>Void</option><option value="cancel" <?php echo $filters['actionFilter'] === 'cancel' ? 'selected' : ''; ?>>Cancel</option></select></div>
              <div class="col-md-2"><label class="form-label small">Table</label><select name="table" class="form-select form-select-sm"><option value="">All</option><option value="pos_orders" <?php echo $filters['tableFilter'] === 'pos_orders' ? 'selected' : ''; ?>>POS Orders</option><option value="bir_or_numbers" <?php echo $filters['tableFilter'] === 'bir_or_numbers' ? 'selected' : ''; ?>>OR Numbers</option></select></div>
              <div class="col-md-2"><label class="form-label small">User</label><select name="user_id" class="form-select form-select-sm"><option value="">All Users</option><?php foreach ($users as $u): ?><option value="<?php echo $u['user_id']; ?>" <?php echo $filters['userFilter'] == $u['user_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['fullname']); ?></option><?php endforeach; ?></select></div>
              <div class="col-md-2"><label class="form-label small">Date From</label><input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo $filters['dateFrom']; ?>"></div>
              <div class="col-md-2"><label class="form-label small">Date To</label><input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo $filters['dateTo']; ?>"></div>
              <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary w-100"><span class="fas fa-filter me-1"></span>Filter</button></div>
            </form>
          </div>
        </div>

        <!-- Audit Trail Table -->
        <div class="card">
          <div class="card-header bg-body-tertiary"><h6 class="mb-0"><span class="fas fa-history me-2"></span>Audit Logs</h6></div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                  <tr><th>Date/Time</th><th>User</th><th>Action</th><th>Table</th><th>Record ID</th><th>Order</th><th>Branch</th><th>Field Changed</th><th>Details</th></tr>
                </thead>
                <tbody>
                  <?php if (!empty($auditTrail)): ?>
                    <?php foreach ($auditTrail as $log): ?>
                    <tr>
                      <td><?php echo date('M d H:i', strtotime($log['created_at'])); ?></td>
                      <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                      <td><span class="badge bg-<?php echo match($log['action']) { 'create' => 'success', 'modify' => 'warning', 'void' => 'danger', 'cancel' => 'secondary', default => 'info' }; ?>"><?php echo ucfirst($log['action']); ?></span></td>
                      <td><?php echo htmlspecialchars($log['table_name']); ?></td>
                      <td><?php echo $log['record_id']; ?></td>
                      <td><?php echo $log['order_code'] ? htmlspecialchars($log['order_code']) : '-'; ?></td>
                      <td><?php echo $log['branch_name'] ? htmlspecialchars($log['branch_name']) : '-'; ?></td>
                      <td><?php echo $log['field_changed'] ? htmlspecialchars($log['field_changed']) : '-'; ?></td>
                      <td><button class="btn btn-sm btn-outline-info" data-bs-toggle="popover" data-bs-content="Old: <?php echo htmlspecialchars($log['old_value'] ?? 'N/A'); ?> New: <?php echo htmlspecialchars($log['new_value'] ?? 'N/A'); ?>"><span class="fas fa-eye"></span></button></td>
                    </tr>
                    <?php endforeach; ?>
                  <?php else: ?><tr><td colspan="9" class="text-center text-muted py-4">No audit logs found</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <script>var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]')); var popoverList = popoverTriggerList.map(function (popoverTriggerEl) { return new bootstrap.Popover(popoverTriggerEl); });</script>
</body>
</html>
