<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/bir/assets/css/bir.css?v=<?php echo filemtime(dirname(dirname(__DIR__)) . '/assets/css/bir.css'); ?>">
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
        <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
          <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?>
        <?php endif; ?>
      <?php else: ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?>
      <?php endif; ?>
      
      <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      <div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; break;
            case 'vertical':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php'; break;
        }
        ?>
      <?php endif; ?>

        <!-- Header Card -->
        <div class="row g-4 mb-4">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center">
                    <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">OR Numbers Management</h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard/">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/bir/">BIR</a></li>
                            <li class="breadcrumb-item active">OR Numbers</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto d-flex gap-2">
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createSeriesModal">
                      <span class="fas fa-plus me-1"></span>New Series
                    </button>
                    <a href="<?php echo BASE_URL; ?>/admin/bir/" class="btn btn-sm btn-outline-secondary">
                      <span class="fas fa-arrow-left me-1"></span>Back
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-3">
          <div class="col-6 col-md-3">
            <div class="card h-100">
              <div class="card-body text-center">
                <h3 class="text-primary mb-1"><?php echo number_format($stats['total_or'] ?? 0); ?></h3>
                <small class="text-muted">Today's OR</small>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card h-100">
              <div class="card-body text-center">
                <h3 class="text-success mb-1"><?php echo number_format($stats['issued_count'] ?? 0); ?></h3>
                <small class="text-muted">Issued</small>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card h-100">
              <div class="card-body text-center">
                <h3 class="text-warning mb-1"><?php echo number_format($stats['void_count'] ?? 0); ?></h3>
                <small class="text-muted">Void</small>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card h-100">
              <div class="card-body text-center">
                <h3 class="text-danger mb-1"><?php echo number_format($stats['cancelled_count'] ?? 0); ?></h3>
                <small class="text-muted">Cancelled</small>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="card mb-3">
          <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
              <div class="col-md-2">
                <label class="form-label small">Branch</label>
                <select name="branch_id" class="form-select form-select-sm">
                  <option value="">All Branches</option>
                  <?php foreach ($branches as $branch): ?>
                  <option value="<?php echo $branch['branch_id']; ?>" <?php echo $filters['branch_id'] == $branch['branch_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($branch['branch_name']); ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select form-select-sm">
                  <option value="">All Status</option>
                  <option value="issued" <?php echo $filters['status'] === 'issued' ? 'selected' : ''; ?>>Issued</option>
                  <option value="void" <?php echo $filters['status'] === 'void' ? 'selected' : ''; ?>>Void</option>
                  <option value="cancelled" <?php echo $filters['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label small">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo $filters['date_from']; ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label small">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo $filters['date_to']; ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label small">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="OR # or Order..." value="<?php echo htmlspecialchars($filters['search']); ?>">
              </div>
              <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                  <span class="fas fa-filter me-1"></span>Filter
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- OR Series Tab -->
        <div class="card mb-3">
          <div class="card-header bg-body-tertiary">
            <h6 class="mb-0"><span class="fas fa-list-ol me-2"></span>OR Series Management</h6>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Series Code</th>
                    <th>Branch</th>
                    <th>Year</th>
                    <th>Start</th>
                    <th>Current</th>
                    <th>End</th>
                    <th>Remaining</th>
                    <th>Status</th>
                    <th>Created By</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($orSeries as $series): 
                    $remaining = $series['end_number'] - $series['current_number'];
                    $warning = $remaining < 100 ? 'text-danger fw-bold' : '';
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($series['series_code']); ?></td>
                    <td><?php echo htmlspecialchars($series['branch_name']); ?></td>
                    <td><?php echo $series['year']; ?></td>
                    <td><?php echo number_format($series['start_number']); ?></td>
                    <td><?php echo number_format($series['current_number']); ?></td>
                    <td><?php echo number_format($series['end_number']); ?></td>
                    <td class="<?php echo $warning; ?>"><?php echo number_format($remaining); ?></td>
                    <td>
                      <span class="badge bg-<?php echo $series['status'] === 'active' ? 'success' : 'secondary'; ?>">
                        <?php echo ucfirst($series['status']); ?>
                      </span>
                    </td>
                    <td><?php echo htmlspecialchars($series['created_by_name']); ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- OR Numbers List -->
        <div class="card">
          <div class="card-header bg-body-tertiary">
            <h6 class="mb-0"><span class="fas fa-receipt me-2"></span>OR Numbers List</h6>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                  <tr>
                    <th>OR Number</th>
                    <th>Order</th>
                    <th>Branch</th>
                    <th>Status</th>
                    <th>Issued At</th>
                    <th>Voided By</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($orNumbers)): ?>
                    <?php foreach ($orNumbers as $or): ?>
                    <tr>
                      <td class="fw-semibold"><?php echo htmlspecialchars($or['or_full_number']); ?></td>
                      <td><?php echo $or['order_code'] ? htmlspecialchars($or['order_code']) : '-'; ?></td>
                      <td><?php echo htmlspecialchars($or['branch_name']); ?></td>
                      <td>
                        <?php 
                        $badgeClass = match($or['status']) {
                            'issued' => 'success',
                            'void' => 'warning',
                            'cancelled' => 'danger',
                            default => 'secondary'
                        };
                        ?>
                        <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo ucfirst($or['status']); ?></span>
                      </td>
                      <td><?php echo date('M d, Y H:i', strtotime($or['issued_at'])); ?></td>
                      <td><?php echo $or['voided_by_name'] ? htmlspecialchars($or['voided_by_name']) : '-'; ?></td>
                      <td>
                        <?php if ($or['status'] === 'issued' && in_array($userRoleCode, ['SUPER_ADMIN', 'ADMIN', 'MANAGER'])): ?>
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#voidOrModal" 
                                data-or-id="<?php echo $or['or_id']; ?>" data-or-number="<?php echo htmlspecialchars($or['or_full_number']); ?>">
                          <span class="fas fa-ban"></span> Void
                        </button>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="7" class="text-center text-muted py-4">No OR numbers found</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>

  <!-- Create Series Modal -->
  <div class="modal fade" id="createSeriesModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Create New OR Series</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="create_series">
            
            <div class="mb-3">
              <label class="form-label">Branch</label>
              <select name="branch_id" class="form-select" required>
                <option value="">Select Branch</option>
                <?php foreach ($branches as $branch): ?>
                <option value="<?php echo $branch['branch_id']; ?>"><?php echo htmlspecialchars($branch['branch_name']); ?></option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">OR series are per branch</small>
            </div>

            <div class="mb-3">
              <label class="form-label">Year</label>
              <input type="number" name="year" class="form-control" value="<?php echo date('Y'); ?>" min="2020" max="2099" required>
              <small class="text-muted">Tax year for this OR series</small>
            </div>

            <div class="mb-3">
              <label class="form-label">Start Number</label>
              <input type="number" name="start_number" class="form-control" value="1" min="1" required placeholder="e.g., 1">
              <small class="text-muted">First OR number in this series (e.g., 001)</small>
            </div>

            <div class="mb-3">
              <label class="form-label">End Number</label>
              <input type="number" name="end_number" class="form-control" value="999999" min="1" required placeholder="e.g., 999999">
              <small class="text-muted">Last OR number in this series (e.g., 999999)</small>
            </div>

            <div class="alert alert-info">
              <small><strong>OR Format:</strong> BranchCode-Year-Sequence (e.g., 001-2024-000001)</small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Create Series</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Void OR Modal -->
  <div class="modal fade" id="voidOrModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Void OR Number</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="void_or">
            <input type="hidden" name="or_id" id="voidOrId">
            
            <p>Are you sure you want to void OR Number: <strong id="voidOrNumber"></strong>?</p>
            
            <div class="mb-3">
              <label class="form-label">Void Reason <span class="text-danger">*</span></label>
              <textarea name="void_reason" class="form-control" rows="3" required placeholder="Enter reason for voiding..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning">Void OR</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
  </div>
  <?php endif; ?>

  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
  
  <script>
    // Void OR Modal
    document.getElementById('voidOrModal').addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var orId = button.getAttribute('data-or-id');
        var orNumber = button.getAttribute('data-or-number');
        
        document.getElementById('voidOrId').value = orId;
        document.getElementById('voidOrNumber').textContent = orNumber;
    });
  </script>
</body>
</html>
