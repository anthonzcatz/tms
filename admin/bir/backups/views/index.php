<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php'; ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/bir/assets/css/bir.css?v=<?php echo filemtime(dirname(dirname(__DIR__)) . '/assets/css/bir.css'); ?>">
<body>
  <main class="main" id="top">
    <div class="container" data-layout="container">
      <script>var isFluid = JSON.parse(localStorage.getItem('isFluid')); if (isFluid) { var container = document.querySelector('[data-layout]'); container.classList.remove('container'); container.classList.add('container-fluid'); }</script>

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

        <div class="row g-4 mb-4">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center">
                    <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Backup & Restore</h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard/">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/bir/">BIR</a></li>
                            <li class="breadcrumb-item active">Backups</li>
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

        <?php if ($message): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

        <!-- Create Backup Card -->
        <div class="card mb-4">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h5 class="mb-1">Create New Backup</h5>
                <p class="text-muted mb-0">Create a full database backup (structure + data) for BIR compliance.</p>
              </div>
              <form method="POST" class="mb-0" id="backupForm">
                <input type="hidden" name="action" value="create_backup">
                <button type="submit" class="btn btn-primary" id="createBackupBtn">
                  <span class="fas fa-database me-2"></span>Create Backup
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Backup History -->
        <div class="card">
          <div class="card-header bg-body-tertiary">
            <div class="d-flex justify-content-between align-items-center">
              <h6 class="mb-0"><span class="fas fa-history me-2"></span>Backup History</h6>
              <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                <span class="fas fa-filter me-1"></span>Filters
              </button>
            </div>
          </div>
          <div class="collapse <?php echo (isset($_GET['status']) || isset($_GET['date_from']) || isset($_GET['date_to'])) ? 'show' : ''; ?>" id="filterCollapse">
            <div class="card-body border-bottom">
              <form method="GET" class="row g-3">
                <div class="col-md-3">
                  <label class="form-label">Status</label>
                  <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="success" <?php echo (isset($_GET['status']) && $_GET['status'] === 'success') ? 'selected' : ''; ?>>Success</option>
                    <option value="failed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'failed') ? 'selected' : ''; ?>>Failed</option>
                    <option value="corrupted" <?php echo (isset($_GET['status']) && $_GET['status'] === 'corrupted') ? 'selected' : ''; ?>>Corrupted</option>
                    <option value="deleted" <?php echo (isset($_GET['status']) && $_GET['status'] === 'deleted') ? 'selected' : ''; ?>>Deleted</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Date From</label>
                  <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Date To</label>
                  <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                  <button type="submit" class="btn btn-primary me-2"><span class="fas fa-search me-1"></span>Filter</button>
                  <a href="<?php echo BASE_URL; ?>/admin/bir/backups/" class="btn btn-outline-secondary"><span class="fas fa-times me-1"></span>Clear</a>
                </div>
              </form>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                  <tr><th>Date</th><th>Type</th><th>Size</th><th>Checksum</th><th>Status</th><th>Created By</th><th>Actions</th></tr>
                </thead>
                <tbody>
                  <?php if (!empty($backups)): ?>
                    <?php foreach ($backups as $b): ?>
                    <tr>
                      <td><?php echo date('M d, Y H:i', strtotime($b['created_at'])); ?></td>
                      <td><?php echo ucfirst($b['backup_type']); ?></td>
                      <td><?php echo number_format($b['file_size'] / 1024 / 1024, 2); ?> MB</td>
                      <td><code class="small"><?php echo substr($b['checksum'], 0, 16); ?>...</code></td>
                      <td><span class="badge bg-<?php echo match($b['status']) { 'success' => 'success', 'failed' => 'danger', 'corrupted' => 'warning', default => 'secondary' }; ?>"><?php echo ucfirst($b['status']); ?></span></td>
                      <td><?php echo htmlspecialchars($b['created_by_name']); ?></td>
                      <td>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Verify this backup?');" data-backup-id="<?php echo $b['backup_id']; ?>" data-action="verify">
                          <input type="hidden" name="action" value="verify_backup">
                          <input type="hidden" name="backup_id" value="<?php echo $b['backup_id']; ?>">
                          <button type="submit" class="btn btn-sm btn-outline-primary action-btn" title="Verify"><span class="fas fa-check-circle"></span></button>
                        </form>
                        <?php if ($b['file_path'] && file_exists($b['file_path'])): ?>
                        <a href="?action=download&backup_id=<?php echo $b['backup_id']; ?>" class="btn btn-sm btn-outline-success" title="Download"><span class="fas fa-download"></span></a>
                        <?php endif; ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this backup? This cannot be undone.');" data-backup-id="<?php echo $b['backup_id']; ?>" data-action="delete">
                          <input type="hidden" name="action" value="delete_backup">
                          <input type="hidden" name="backup_id" value="<?php echo $b['backup_id']; ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger action-btn" title="Delete"><span class="fas fa-trash"></span></button>
                        </form>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php else: ?><tr><td colspan="7" class="text-center text-muted py-4">No backups found</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>

  <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
  </div>
  <?php endif; ?>

  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>

  <script>
    // Backup form with loading state
    document.getElementById('backupForm').addEventListener('submit', function(e) {
      const btn = document.getElementById('createBackupBtn');
      const originalText = btn.innerHTML;

      btn.disabled = true;
      btn.innerHTML = '<span class="fas fa-spinner fa-spin me-2"></span>Creating Backup...';

      // Form will submit normally, button will reset on page reload
    });

    // Action buttons (verify, delete) with loading state
    document.querySelectorAll('.action-btn').forEach(function(btn) {
      btn.closest('form').addEventListener('submit', function(e) {
        const action = this.dataset.action;
        const originalIcon = btn.innerHTML;

        btn.disabled = true;
        if (action === 'verify') {
          btn.innerHTML = '<span class="fas fa-spinner fa-spin"></span>';
        } else if (action === 'delete') {
          btn.innerHTML = '<span class="fas fa-spinner fa-spin"></span>';
        }
      });
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
      });
    }, 5000);
  </script>
</body>
</html>
