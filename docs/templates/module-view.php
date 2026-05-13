<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/<category>/<module-name>/assets/css/<module-name>.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/<module-name>.css'); ?>">
<body>

  <!-- ===============================================-->
  <!--    Main Content-->
  <!-- ===============================================-->
  <main class="main" id="top">
    <div class="container" data-layout="container">
      <script>
        var isFluid = JSON.parse(localStorage.getItem('isFluid'));
        if (isFluid) {
          var container = document.querySelector('[data-layout]');
          container.classList.remove('container');
          container.classList.add('container-fluid');
        }
      </script>

      <?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?>

      <?php if (NAVBAR_POSITION === 'top'): ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?>
      <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?>
      <?php endif; ?>

      <div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php';
                break;
            case 'vertical':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php';
                break;
            case 'top':
            case 'double-top':
            default:
                break;
        }
        ?>

        <!-- ===============================================-->
        <!--    Page Content Start Here-->
        <!-- ===============================================-->

        <!-- Page Header -->
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h2 class="mb-1"><ModuleName></h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><ModuleName></li>
                  </ol>
                </nav>
              </div>
              <div>
                <button class="btn btn-primary">
                  <span class="fas fa-plus me-2"></span>Add New
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Content Cards / Tables / Forms -->
        <div class="card mb-4">
          <div class="card-header bg-light py-3">
            <h5 class="mb-0"><ModuleName> List</h5>
          </div>
          <div class="card-body">
            <p class="text-muted mb-0">Your module content goes here...</p>
          </div>
        </div>

        <!-- ===============================================-->
        <!--    Page Content End-->
        <!-- ===============================================-->

      </div>
    </div>
  </main>

  <!-- Include Footer -->
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>

  <script src="<?php echo BASE_URL; ?>/admin/<category>/<module-name>/assets/js/<module-name>.js?v=<?php echo filemtime(dirname(__DIR__) . '/assets/js/<module-name>.js'); ?>"></script>
</body>
</html>
