<div class="modal fade" id="addAccommodationTypeModal" tabindex="-1" aria-labelledby="addAccommodationTypeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addAccommodationTypeModalLabel">
          <span class="fas fa-plus-circle me-2 text-primary"></span>Add Accommodation Type
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-bold" for="addAccommodationCode">Code <span class="text-danger">*</span></label>
          <input type="text" class="form-control text-uppercase" id="addAccommodationCode" placeholder="e.g. ECONOMY" maxlength="50">
          <small class="text-muted">Unique identifier, will be uppercased automatically.</small>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold" for="addAccommodationName">Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="addAccommodationName" placeholder="e.g. Economy" maxlength="100">
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="addAccommodationIsDefault">
          <label class="form-check-label fw-bold" for="addAccommodationIsDefault">Set as Default</label>
          <small class="text-muted d-block ms-4">Setting this will unset the current default accommodation type.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-1"></span>Cancel
        </button>
        <button type="button" class="btn btn-primary" onclick="submitAddAccommodationType()">
          <span class="fas fa-save me-1"></span>Save
        </button>
      </div>
    </div>
  </div>
</div>
