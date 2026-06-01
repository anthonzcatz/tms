<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
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
                      <h4 class="mb-0 text-primary fw-bold">POS Machines</h4>
                      <h6 class="mb-1 text-primary d-none d-sm-block">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard/">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/bir/">BIR</a></li>
                            <li class="breadcrumb-item active">Machines</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto d-flex gap-2">
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMachineModal"><span class="fas fa-plus me-1"></span>Add Machine</button>
                    <a href="<?php echo BASE_URL; ?>/admin/bir/" class="btn btn-sm btn-outline-secondary"><span class="fas fa-arrow-left me-1"></span>Back</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php if (!empty($expiringMachines)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
          <strong><span class="fas fa-exclamation-triangle me-2"></span>Warning!</strong> 
          <?php echo count($expiringMachines); ?> machine(s) have accreditation expiring within 30 days.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header bg-body-tertiary"><h6 class="mb-0"><span class="fas fa-desktop me-2"></span>Registered POS Machines</h6></div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                  <tr><th>Machine</th><th>Branch</th><th>Serial Number</th><th>MIN</th><th>Accreditation</th><th>Permit</th><th>Validity</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($machines as $m): 
                    $expiryWarning = '';
                    if ($m['accreditation_expiry']) {
                        $expiry = new DateTime($m['accreditation_expiry']);
                        $today = new DateTime();
                        $daysUntil = $today->diff($expiry)->days;
                        if ($expiry < $today) $expiryWarning = 'text-danger fw-bold';
                        else if ($daysUntil <= 30) $expiryWarning = 'text-warning fw-bold';
                    }
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($m['machine_name']); ?></td>
                    <td><?php echo htmlspecialchars($m['branch_name']); ?></td>
                    <td><?php echo htmlspecialchars($m['serial_number']); ?></td>
                    <td><?php echo $m['min'] ? htmlspecialchars($m['min']) : '-'; ?></td>
                    <td><?php echo $m['accreditation_number'] ? htmlspecialchars($m['accreditation_number']) : '-'; ?><br><small class="<?php echo $expiryWarning; ?>">Exp: <?php echo $m['accreditation_expiry'] ? date('M d, Y', strtotime($m['accreditation_expiry'])) : 'N/A'; ?></small></td>
                    <td><?php echo $m['permit_number'] ? htmlspecialchars($m['permit_number']) : '-'; ?></td>
                    <td><?php echo ($m['validity_from'] && $m['validity_to']) ? date('M d, Y', strtotime($m['validity_from'])) . ' - ' . date('M d, Y', strtotime($m['validity_to'])) : '-'; ?></td>
                    <td><span class="badge bg-<?php echo match($m['status']) { 'active' => 'success', 'expired' => 'danger', 'suspended' => 'warning', default => 'secondary' }; ?>"><?php echo ucfirst($m['status']); ?></span></td>
                    <td>
                      <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editMachineModal<?php echo $m['machine_id']; ?>"><span class="fas fa-edit"></span></button>
                      <?php if ($userRoleCode === 'SUPER_ADMIN' || $userRoleCode === 'ADMIN'): ?>
                      <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('Delete this machine?')) { document.getElementById('deleteForm<?php echo $m['machine_id']; ?>').submit(); }"><span class="fas fa-trash"></span></button>
                      <form id="deleteForm<?php echo $m['machine_id']; ?>" method="POST" style="display:none;"><input type="hidden" name="action" value="delete_machine"><input type="hidden" name="machine_id" value="<?php echo $m['machine_id']; ?>"></form>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($machines)): ?><tr><td colspan="9" class="text-center text-muted py-4">No machines registered</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Add Machine Modal -->
  <div class="modal fade" id="addMachineModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Add POS Machine</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="add_machine">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Machine Name</label><input type="text" name="machine_name" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label">Branch</label><select name="branch_id" class="form-select" required><option value="">Select Branch</option><?php foreach ($branches as $b): ?><option value="<?php echo $b['branch_id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option><?php endforeach; ?></select></div>
              <div class="col-md-6"><label class="form-label">Serial Number</label><input type="text" name="serial_number" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label">Machine Type</label><select name="machine_type" class="form-select"><option value="POS">POS</option><option value="CAS">CAS</option></select></div>
              <div class="col-md-6"><label class="form-label">MIN (Machine ID)</label><input type="text" name="min" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Accreditation Number</label><input type="text" name="accreditation_number" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Accreditation Expiry</label><input type="date" name="accreditation_expiry" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Permit Number</label><input type="text" name="permit_number" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Validity From</label><input type="date" name="validity_from" class="form-control"></div>
              <div class="col-md-6"><label class="form-label">Validity To</label><input type="date" name="validity_to" class="form-control"></div>
            </div>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add Machine</button></div>
        </form>
      </div>
    </div>
  </div>

  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
</body>
</html>
