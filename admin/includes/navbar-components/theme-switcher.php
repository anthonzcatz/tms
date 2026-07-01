<?php
/**
 * Theme Switcher Component
 * Renders the theme toggle dropdown (Light/Dark/Auto)
 * Usage: include __DIR__ . '/navbar-components/theme-switcher.php';
 * 
 * Parameters (optional):
 * - $dropdownId: Unique ID for the dropdown (default: 'themeSwitchDropdown')
 */
$dropdownId = $dropdownId ?? 'themeSwitchDropdown';
?>

<li class="nav-item ps-2 pe-0 d-flex align-items-center">
  <div class="dropdown theme-control-dropdown">
    <a class="nav-link d-flex align-items-center dropdown-toggle fa-icon-wait fs-9 pe-1 py-0" 
       href="#" 
       role="button" 
       id="<?php echo $dropdownId; ?>" 
       data-bs-toggle="dropdown" 
       aria-haspopup="true" 
       aria-expanded="false">
      <span class="fas fa-sun fs-7" data-fa-transform="shrink-2" data-theme-dropdown-toggle-icon="light"></span>
      <span class="fas fa-moon fs-7" data-fa-transform="shrink-3" data-theme-dropdown-toggle-icon="dark"></span>
      <span class="fas fa-adjust fs-7" data-fa-transform="shrink-2" data-theme-dropdown-toggle-icon="auto"></span>
    </a>
    <div class="dropdown-menu dropdown-menu-end dropdown-caret border py-0 mt-3" aria-labelledby="<?php echo $dropdownId; ?>">
      <div class="bg-white dark__bg-1000 rounded-2 py-2">
        <button class="dropdown-item d-flex align-items-center gap-2" type="button" value="light" data-theme-control="theme">
          <span class="fas fa-sun"></span>Light
          <span class="fas fa-check dropdown-check-icon ms-auto text-600"></span>
        </button>
        <button class="dropdown-item d-flex align-items-center gap-2" type="button" value="dark" data-theme-control="theme">
          <span class="fas fa-moon"></span>Dark
          <span class="fas fa-check dropdown-check-icon ms-auto text-600"></span>
        </button>
        <button class="dropdown-item d-flex align-items-center gap-2" type="button" value="auto" data-theme-control="theme">
          <span class="fas fa-adjust"></span>Auto
          <span class="fas fa-check dropdown-check-icon ms-auto text-600"></span>
        </button>
      </div>
    </div>
  </div>
</li>
