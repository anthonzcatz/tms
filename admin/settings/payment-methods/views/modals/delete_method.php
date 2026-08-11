<!-- Delete Payment Method Confirmation Modal -->
<div class="modal fade" id="deleteMethodModal" tabindex="-1" aria-labelledby="deleteMethodModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow">
      <div class="modal-body text-center p-4">
        <div class="mb-3">
          <span class="fas fa-exclamation-circle text-danger fs-1"></span>
        </div>
        <h5 class="fw-bold text-dark" id="deleteMethodModalLabel">Delete Payment Method?</h5>
        <p class="text-muted mb-0">
          Are you sure you want to delete <strong id="deleteMethodName" class="text-dark"></strong>?
        </p>
        <p class="text-muted small mt-2">This action cannot be undone if the payment method is already used in transactions.</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0 pb-4">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteMethodBtn">
          <span class="fas fa-trash me-1"></span>Delete
        </button>
      </div>
    </div>
  </div>
</div>
