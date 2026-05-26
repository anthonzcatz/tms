<div class="modal fade" id="editDiscountTypeModal" tabindex="-1" aria-labelledby="editDiscountTypeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editDiscountTypeModalLabel">
          <span class="fas fa-edit me-2 text-warning"></span>Edit Discount Type
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editDiscountId">
        <div class="mb-3">
          <label class="form-label fw-bold">Code <span class="text-danger">*</span></label>
          <input type="text" class="form-control text-uppercase" id="editCode" maxlength="50">
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="editName" maxlength="100">
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Description</label>
          <textarea class="form-control" id="editDescription" rows="2"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Discount Percentage (%)</label>
          <div class="input-group">
            <input type="number" class="form-control" id="editDiscountPercentage" min="0" max="100" step="0.01">
            <span class="input-group-text">%</span>
          </div>
          <small class="text-muted">Enter 0 for no discount.</small>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="editIsDefault">
          <label class="form-check-label fw-bold" for="editIsDefault">
            Set as Default
          </label>
          <small class="text-muted d-block ms-4">Only one discount type can be default at a time.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-1"></span>Cancel
        </button>
        <button type="button" class="btn btn-warning" onclick="submitEditDiscountType()">
          <span class="fas fa-save me-1"></span>Update
        </button>
      </div>
    </div>
  </div>
</div>
