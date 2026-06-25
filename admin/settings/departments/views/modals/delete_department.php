<div class="modal fade" id="deleteDepartmentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-danger">
        <h5 class="modal-title text-white">Delete Department</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <p>Are you sure you want to delete <strong id="deleteDepartmentName"></strong>?</p>
        <p class="text-muted small">This action cannot be undone.</p>
        <input type="hidden" id="deleteDepartmentId">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-falcon-default" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="confirmDeleteDepartment()">Delete</button>
      </div>
    </div>
  </div>
</div>
