<div class="modal fade" id="addDiscountTypeModal" tabindex="-1" aria-labelledby="addDiscountTypeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addDiscountTypeModalLabel">
          <span class="fas fa-plus-circle me-2 text-primary"></span>Add Discount Type
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-bold">Code <span class="text-danger">*</span></label>
          <input type="text" class="form-control text-uppercase" id="addCode" placeholder="e.g. SENIOR" maxlength="50">
          <small class="text-muted">Unique identifier, will be uppercased automatically.</small>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="addName" placeholder="e.g. Senior Citizen" maxlength="100">
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Description</label>
          <textarea class="form-control" id="addDescription" rows="2" placeholder="Optional description"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Discount Percentage (%)</label>
          <div class="input-group">
            <input type="number" class="form-control" id="addDiscountPercentage" value="0" min="0" max="100" step="0.01">
            <span class="input-group-text">%</span>
          </div>
          <small class="text-muted">Enter 0 for no discount (e.g. Regular fare).</small>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="addIsDefault">
          <label class="form-check-label fw-bold" for="addIsDefault">
            Set as Default
          </label>
          <small class="text-muted d-block ms-4">Only one discount type can be default. Setting this will unset the current default.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-1"></span>Cancel
        </button>
        <button type="button" class="btn btn-primary" onclick="submitAddDiscountType()">
          <span class="fas fa-save me-1"></span>Save
        </button>
      </div>
    </div>
  </div>
</div>
