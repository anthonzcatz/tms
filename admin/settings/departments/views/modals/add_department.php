<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white">Add Department</h4>
          <p class="fs-10 mb-0 text-white">Create a new department</p>
        </div>
        <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <form id="addDepartmentForm" novalidate>
          <div class="mb-3">
            <label class="form-label fw-bold">Department Name *</label>
            <input type="text" class="form-control" id="addDepartmentName" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Department Code</label>
            <input type="text" class="form-control" id="addDepartmentCode">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Status</label>
            <select class="form-select" id="addDepartmentStatus">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-falcon-default" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveDepartment()">Save Department</button>
      </div>
    </div>
  </div>
</div>
