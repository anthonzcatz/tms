<?php
/**
 * Delete Company Modal
 */
?>
<div class="modal fade" id="deleteCompanyModal" tabindex="-1" aria-labelledby="deleteCompanyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-danger">
        <h5 class="modal-title text-white" id="deleteCompanyModalLabel">
          <span class="fas fa-trash-alt me-2"></span>Delete Company
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <p class="mb-1">Are you sure you want to delete</p>
        <p class="fw-bold" id="deleteCompanyName"></p>
        <p class="text-muted small">This action cannot be undone.</p>
        <input type="hidden" id="deleteCompanyId">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-falcon-default" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="confirmDeleteCompany()">
          <span class="fas fa-trash me-2"></span>Delete
        </button>
      </div>
    </div>
  </div>
</div>
