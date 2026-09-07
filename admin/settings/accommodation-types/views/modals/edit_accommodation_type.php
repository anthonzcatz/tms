<div class="modal fade" id="editAccommodationTypeModal" tabindex="-1" aria-labelledby="editAccommodationTypeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editAccommodationTypeModalLabel">
          <span class="fas fa-edit me-2 text-warning"></span>Edit Accommodation Type
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editAccommodationTypeId">
        <div class="mb-3">
          <label class="form-label fw-bold" for="editAccommodationCode">Code <span class="text-danger">*</span></label>
          <input type="text" class="form-control text-uppercase" id="editAccommodationCode" maxlength="50">
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold" for="editAccommodationName">Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="editAccommodationName" maxlength="100">
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="editAccommodationIsDefault">
          <label class="form-check-label fw-bold" for="editAccommodationIsDefault">Set as Default</label>
          <small class="text-muted d-block ms-4">Only one accommodation type can be default at a time.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-1"></span>Cancel
        </button>
        <button type="button" class="btn btn-warning" onclick="submitEditAccommodationType()">
          <span class="fas fa-save me-1"></span>Update
        </button>
      </div>
    </div>
  </div>
</div>
