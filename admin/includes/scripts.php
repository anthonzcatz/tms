    <!-- ===============================================-->
    <!--    JavaScripts-->
    <!-- ===============================================-->
    <script src="<?php echo BASE_URL; ?>/resources/vendors/popper/popper.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/bootstrap/bootstrap.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/anchorjs/anchor.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/is/is.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/chart/chart.umd.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/leaflet/leaflet.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/leaflet.markercluster/leaflet.markercluster.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/leaflet.tilelayer.colorfilter/leaflet-tilelayer-colorfilter-global.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/countup/countUp.umd.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/echarts/echarts.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/assets/data/world.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/dayjs/dayjs.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/flatpickr/flatpickr.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/fontawesome/all.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/lodash/lodash.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/list.js/list.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/assets/js/theme.js"></script>

    <!-- Global Session Expiration Alert -->
    <?php if (isset($_SESSION['session_expired']) && $_SESSION['session_expired']): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show session expiration alert using Bootstrap modal
            const alertHtml = `
                <div class="modal fade" id="sessionExpiredModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-warning bg-opacity-10">
                                <h5 class="modal-title text-warning">
                                    <span class="fas fa-exclamation-triangle me-2"></span>Session Expired
                                </h5>
                            </div>
                            <div class="modal-body">
                                <p>Your session has expired due to inactivity. Please log in again to continue.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" onclick="window.location.href = window.location.href.split('?')[0]">
                                    <span class="fas fa-check me-2"></span>OK
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            const modal = new bootstrap.Modal(document.getElementById('sessionExpiredModal'));
            modal.show();
            
            // Clear the session flag
            <?php unset($_SESSION['session_expired']); ?>
        });
    </script>
    <?php endif; ?>
