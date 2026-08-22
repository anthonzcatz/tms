<?php
/**
 * Search Box Component
 * Renders the search input with dropdown results
 * Usage: include __DIR__ . '/navbar-components/search-box.php';
 */
?>
<li class="nav-item d-flex align-items-center">
  <div class="search-box" data-list='{"valueNames":["title"]}'>
    <form class="position-relative d-flex align-items-center" data-bs-toggle="search" data-bs-display="static">
      <input class="form-control search-input fuzzy-search" type="search" placeholder="Search..." aria-label="Search" />
      <span class="fas fa-search search-box-icon"></span>
    </form>
    <div class="btn-close-falcon-container position-absolute end-0 top-50 translate-middle shadow-none" data-bs-dismiss="search">
      <button class="btn btn-link btn-close-falcon p-0" aria-label="Close" onclick="var e=event||window.event; if(e){e.stopPropagation(); e.preventDefault();} var s=this.closest('.search-box'); var i=s.querySelector('.fuzzy-search'); if(i){i.value=''; i.dispatchEvent(new Event('input', {bubbles:true})); i.dispatchEvent(new Event('keyup', {bubbles:true})); i.focus();} return false;"></button>
    </div>
    <div class="dropdown-menu border font-base start-0 mt-2 py-0 overflow-hidden w-100">
      <div class="scrollbar list py-3" style="max-height: 24rem;">
        <?php include __DIR__ . '/search-data.php'; ?>
      </div>
      <div class="text-center mt-n3">
        <p class="fallback fw-bold fs-8 d-none">No Result Found.</p>
      </div>
    </div>
  </div>
</li>
