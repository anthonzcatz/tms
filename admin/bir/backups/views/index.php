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
                <p class="text-muted mb-0">Create a full database backup for BIR compliance.</p>
              </div>
              <form method="POST" class="mb-0"><input type="hidden" name="action" value="create_backup"><button type="submit" class="btn btn-primary"><span class="fas fa-database me-2"></span>Create Backup</button></form>
            </div>
          </div>
        </div>

        <!-- Backup History -->
        <div class="card">
          <div class="card-header bg-body-tertiary"><h6 class="mb-0"><span class="fas fa-history me-2"></span>Backup History</h6></div>
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
                        <form method="POST" class="d-inline"><input type="hidden" name="action" value="verify_backup"><input type="hidden" name="backup_id" value="<?php echo $b['backup_id']; ?>"><button type="submit" class="btn btn-sm btn-outline-primary" title="Verify"><span class="fas fa-check-circle"></span></button></form>
                        <?php if ($b['file_path'] && file_exists($b['file_path'])): ?>
                        <a href="<?php echo str_replace(dirname(dirname(dirname(__DIR__))), BASE_URL, $b['file_path']); ?>" class="btn btn-sm btn-outline-success" download title="Download"><span class="fas fa-download"></span></a>
                        <?php endif; ?>
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
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
</body>
</html>
