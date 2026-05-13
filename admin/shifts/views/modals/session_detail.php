<!-- Session Detail Modal -->
<div class="modal fade" id="sessionDetailModal" tabindex="-1" aria-labelledby="sessionDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="sessionDetailModalLabel">
            <span class="fas fa-clipboard-list me-2"></span>Shift Detail
          </h4>
          <p class="fs-10 mb-0 text-white" id="sessionDetailSubtitle">Loading...</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div id="sessionDetailContent">
          <div class="text-center py-5"><span class="fas fa-spinner fa-spin fs-3"></span><p class="mt-2 text-muted">Loading session details...</p></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
