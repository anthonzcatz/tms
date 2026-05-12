<?php
/**
 * Delete User Confirmation Modal
 */
?>
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger-subtle">
        <h5 class="modal-title text-danger" id="deleteUserModalLabel">
          <span class="fas fa-exclamation-triangle me-2"></span>
          Delete User
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-center mb-4">
          <div class="avatar avatar-4xl mb-3">
            <div class="avatar-name rounded-circle bg-danger-subtle text-danger fs-7">
              <span class="fas fa-user-slash"></span>
            </div>
          </div>
          <h5>Are you sure you want to delete this user?</h5>
          <p class="text-muted mb-0">
            This action cannot be undone. The user <strong id="deleteUsername"></strong> 
            will be permanently removed from the system.
          </p>
        </div>
        <div class="alert alert-warning" role="alert">
          <span class="fas fa-info-circle me-2"></span>
          <strong>Warning:</strong> All associated data including user sessions and activity logs will be deleted.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-falcon-default" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn" onclick="confirmDeleteUser()">
          <span class="fas fa-trash-alt me-1"></span>Delete User
        </button>
      </div>
    </div>
  </div>
</div>
