<!-- Delete Service Type Confirmation Modal -->
<div class="modal fade" id="deleteServiceTypeModal" tabindex="-1" aria-labelledby="deleteServiceTypeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow">
      <div class="modal-body text-center p-4">
        <div class="mb-3">
          <span class="fas fa-exclamation-circle text-danger fs-1"></span>
        </div>
        <h5 class="fw-bold text-dark" id="deleteServiceTypeModalLabel">Delete Service Type?</h5>
        <p class="text-muted mb-0">
          Are you sure you want to delete <strong id="deleteServiceTypeName" class="text-dark"></strong>?
        </p>
        <p class="text-muted small mt-2">This action cannot be undone if the service type is already used in transactions.</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0 pb-4">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteServiceTypeBtn">
          <span class="fas fa-trash me-1"></span>Delete
        </button>
      </div>
    </div>
  </div>
</div>
