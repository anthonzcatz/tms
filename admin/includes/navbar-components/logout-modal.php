<?php
/**
 * Logout Modal Component
 * Renders the logout confirmation modal
 * Usage: include __DIR__ . '/navbar-components/logout-modal.php';
 * 
 * Note: This should be included once per page, typically at the end of the navbar
 */

// Ensure modal is only included once
if (!defined('LOGOUT_MODAL_INCLUDED')) {
    define('LOGOUT_MODAL_INCLUDED', true);
?>
<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog mt-6" role="document">
    <div class="modal-content border-0">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="logoutModalLabel">Confirm Logout</h4>
          <p class="fs-10 mb-0 text-white">Are you sure you want to logout?</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body py-4 px-5">
        <p class="text-600">You will be logged out of your account and redirected to the login page.</p>
      </div>
      <div class="modal-footer bg-body-tertiary py-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="btn btn-danger">Logout</a>
      </div>
    </div>
  </div>
</div>
<?php } ?>
