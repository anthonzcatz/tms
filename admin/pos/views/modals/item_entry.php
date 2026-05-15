<!-- Item Entry Modal -->
<div class="modal fade" id="itemEntryModal" tabindex="-1" aria-labelledby="itemEntryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="itemEntryModalLabel">
            <span class="fas fa-plus-circle me-2"></span>Add Item
          </h4>
          <p class="fs-10 mb-0 text-white">Customize service details before adding to cart</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close" onclick="cancelItemEntry()"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-12">
            <label class="form-label fw-semibold" for="itemServiceName">Service</label>
            <input type="text" class="form-control" id="itemServiceName" name="itemServiceName" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" for="itemQty">Qty</label>
            <input type="number" class="form-control" id="itemQty" name="itemQty" value="1" min="1" oninput="computeItemTotal()">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" for="itemUnitPrice">Unit Price (₱)</label>
            <input type="number" class="form-control" id="itemUnitPrice" name="itemUnitPrice" value="0.00" min="0" step="0.01" oninput="computeItemTotal()">
          </div>
          <div class="col-md-12">
            <label class="form-label fw-semibold" for="itemDescription">Description / Remarks</label>
            <input type="text" class="form-control" id="itemDescription" name="itemDescription" placeholder="Optional">
          </div>
          <div class="col-md-12">
            <label class="form-label fw-semibold">Item Total</label>
            <div class="form-control bg-light fw-bold text-success fs-5" id="itemTotalDisplay">₱0.00</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="cancelItemEntry()">Cancel</button>
        <button type="button" class="btn btn-success" onclick="addItemToCart()">
          <span class="fas fa-plus me-1"></span>Add to Cart
        </button>
      </div>
    </div>
  </div>
</div>
