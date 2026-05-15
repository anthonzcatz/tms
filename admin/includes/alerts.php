<?php
/**
 * Common Alert Display Component (Toast Notifications)
 * Displays success/error messages from session as toast notifications
 */
?>
<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
  <?php if (isset($_SESSION['success'])): ?>
    <div class="toast show bg-success text-white" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
      <div class="toast-header bg-success text-white">
        <span class="fas fa-check-circle me-2"></span>
        <strong class="me-auto">Success</strong>
        <small>Just now</small>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body">
        <?php 
        echo htmlspecialchars($_SESSION['success']); 
        unset($_SESSION['success']);
        ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (isset($_SESSION['error'])): ?>
    <div class="toast show bg-danger text-white" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
      <div class="toast-header bg-danger text-white">
        <span class="fas fa-exclamation-circle me-2"></span>
        <strong class="me-auto">Error</strong>
        <small>Just now</small>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body">
        <?php 
        echo htmlspecialchars($_SESSION['error']); 
        unset($_SESSION['error']);
        ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (isset($_SESSION['warning'])): ?>
    <div class="toast show bg-warning text-dark" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
      <div class="toast-header bg-warning text-dark">
        <span class="fas fa-exclamation-triangle me-2"></span>
        <strong class="me-auto">Warning</strong>
        <small>Just now</small>
        <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body">
        <?php 
        echo htmlspecialchars($_SESSION['warning']); 
        unset($_SESSION['warning']);
        ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (isset($_SESSION['info'])): ?>
    <div class="toast show bg-info text-white" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
      <div class="toast-header bg-info text-white">
        <span class="fas fa-info-circle me-2"></span>
        <strong class="me-auto">Info</strong>
        <small>Just now</small>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body">
        <?php 
        echo htmlspecialchars($_SESSION['info']); 
        unset($_SESSION['info']);
        ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
// Auto-dismiss toasts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
  const toasts = document.querySelectorAll('.toast');
  toasts.forEach(function(toast) {
    setTimeout(function() {
      const bsToast = bootstrap.Toast.getInstance(toast) || new bootstrap.Toast(toast, { delay: 5000 });
      bsToast.hide();
    }, 5000);
  });
});
</script>
