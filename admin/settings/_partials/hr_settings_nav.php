<div class="card mb-3">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="text-muted small fw-bold me-1">HR Setup:</span>
      <a href="<?php echo BASE_URL; ?>/admin/settings/organization" class="btn btn-sm btn-<?php echo $activeModule === 'organization' ? 'primary' : 'falcon-default'; ?>">Organization</a>
      <a href="<?php echo BASE_URL; ?>/admin/settings/departments" class="btn btn-sm btn-<?php echo $activeModule === 'departments' ? 'primary' : 'falcon-default'; ?>">Departments</a>
      <a href="<?php echo BASE_URL; ?>/admin/settings/sub-departments" class="btn btn-sm btn-<?php echo $activeModule === 'sub-departments' ? 'primary' : 'falcon-default'; ?>">Sub-Departments</a>
      <a href="<?php echo BASE_URL; ?>/admin/settings/positions" class="btn btn-sm btn-<?php echo $activeModule === 'positions' ? 'primary' : 'falcon-default'; ?>">Positions</a>
      <a href="<?php echo BASE_URL; ?>/admin/settings/employment-status" class="btn btn-sm btn-<?php echo $activeModule === 'employment-status' ? 'primary' : 'falcon-default'; ?>">Employment Status</a>
      <a href="<?php echo BASE_URL; ?>/admin/settings/employees" class="btn btn-sm btn-<?php echo $activeModule === 'employees' ? 'primary' : 'falcon-default'; ?>">Employees</a>
      <a href="<?php echo BASE_URL; ?>/admin/settings/companies" class="btn btn-sm btn-<?php echo $activeModule === 'companies' ? 'primary' : 'falcon-default'; ?>">Companies</a>
    </div>
  </div>
</div>
