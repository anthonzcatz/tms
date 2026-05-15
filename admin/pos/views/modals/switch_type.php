<!-- Switch Transaction Type Confirmation Modal -->
<div class="modal fade" id="switchTypeModal" tabindex="-1" aria-labelledby="switchTypeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="switchTypeModalLabel">
          <span class="fas fa-exclamation-triangle text-warning me-2"></span>Switch Transaction Type
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">Switching transaction type will clear the current cart. Are you sure you want to continue?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="cancelSwitchType()">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="confirmSwitchType()">
          <span class="fas fa-check me-1"></span>Yes, Continue
        </button>
      </div>
    </div>
  </div>
</div>
