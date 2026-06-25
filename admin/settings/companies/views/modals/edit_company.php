<?php
/**
 * Edit Company Modal
 */
?>
<div class="modal fade" id="editCompanyModal" tabindex="-1" aria-labelledby="editCompanyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="editCompanyModalLabel">
            <span class="fas fa-edit me-2"></span>Edit Company
          </h4>
          <p class="fs-10 mb-0 text-white">Update company details</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-4">
        <form id="editCompanyForm" novalidate>
          <input type="hidden" id="editCompanyId">
          <div class="mb-3">
            <label for="editCompanyName" class="form-label fw-bold">Company Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="editCompanyName" required>
            <div class="invalid-feedback">Company name is required</div>
          </div>
          <div class="mb-3">
            <label for="editCompanyCode" class="form-label fw-bold">Company Abbreviation</label>
            <input type="text" class="form-control" id="editCompanyCode">
          </div>
          <div class="mb-3">
            <label for="editCompanyAddress" class="form-label fw-bold">Address</label>
            <textarea class="form-control" id="editCompanyAddress" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label for="editCompanyContact" class="form-label fw-bold">Contact Number</label>
            <input type="text" class="form-control" id="editCompanyContact">
          </div>
          <div class="mb-3">
            <label for="editCompanyEmail" class="form-label fw-bold">Email</label>
            <input type="email" class="form-control" id="editCompanyEmail">
          </div>
          <div class="mb-3">
            <label for="editCompanyStatus" class="form-label fw-bold">Company Status</label>
            <select class="form-select" id="editCompanyStatus">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-falcon-default" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="updateCompany()">
          <span class="fas fa-save me-2"></span>Update Company
        </button>
      </div>
    </div>
  </div>
</div>
