<?php
/**
 * Nine-Dots Dropdown Component
 * Renders the nine-dots menu with links from navbar-config.php
 * Usage: include __DIR__ . '/navbar-components/nine-dots-dropdown.php';
 * 
 * Parameters (optional):
 * - $dropdownId: Unique ID for the dropdown (default: 'navbarDropdownMenu')
 */
$config = require __DIR__ . '/../navbar-config.php';
$dropdownId = $dropdownId ?? 'navbarDropdownMenu';
$links = $config['nine_dots_links'];
$iconPath = $config['icon_path'];
?>

<li class="nav-item dropdown px-1 d-flex align-items-center">
  <a class="nav-link fa-icon-wait nine-dots p-1" id="<?php echo $dropdownId; ?>" role="button" data-hide-on-body-scroll="data-hide-on-body-scroll" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
      <circle cx="2" cy="2" r="2" fill="#6C6E71"></circle>
      <circle cx="2" cy="8" r="2" fill="#6C6E71"></circle>
      <circle cx="2" cy="14" r="2" fill="#6C6E71"></circle>
      <circle cx="8" cy="8" r="2" fill="#6C6E71"></circle>
      <circle cx="8" cy="14" r="2" fill="#6C6E71"></circle>
      <circle cx="14" cy="8" r="2" fill="#6C6E71"></circle>
      <circle cx="14" cy="14" r="2" fill="#6C6E71"></circle>
      <circle cx="8" cy="2" r="2" fill="#6C6E71"></circle>
      <circle cx="14" cy="2" r="2" fill="#6C6E71"></circle>
    </svg>
  </a>
  <div class="dropdown-menu dropdown-caret dropdown-caret dropdown-menu-end dropdown-menu-card dropdown-caret-bg" aria-labelledby="<?php echo $dropdownId; ?>">
    <div class="card shadow-none">
      <div class="scrollbar-overlay nine-dots-dropdown">
        <div class="card-body px-3">
          <div class="row text-center gx-0 gy-0">
            <?php foreach ($links as $link): ?>
              <?php if (!empty($link['divider_before'])): ?>
                <div class="col-12">
                  <hr class="my-3 mx-n3 bg-200" />
                </div>
              <?php endif; ?>
              
              <div class="col-4">
                <a class="d-block hover-bg-200 px-2 py-3 rounded-3 text-center text-decoration-none" 
                   href="<?php echo $link['url']; ?>" 
                   <?php echo !empty($link['target']) ? 'target="' . $link['target'] . '"' : ''; ?>>
                  
                  <?php if ($link['type'] === 'avatar'): ?>
                    <!-- User avatar with initials fallback -->
                    <div class="avatar avatar-2xl">
                      <?php if ($profileImage): ?>
                        <img class="rounded-circle" src="<?php echo BASE_URL . $profileImage; ?>" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                        <div class="avatar-name rounded-circle" style="display:none;"><span><?php echo $initials; ?></span></div>
                      <?php else: ?>
                        <div class="avatar-name rounded-circle"><span><?php echo $initials; ?></span></div>
                      <?php endif; ?>
                    </div>
                  
                  <?php elseif ($link['type'] === 'image'): ?>
                    <!-- Image icon -->
                    <img class="rounded" src="<?php echo $iconPath . $link['icon'] . '.png'; ?>" alt="" width="40" height="40" />
                  
                  <?php elseif ($link['type'] === 'initials'): ?>
                    <!-- Initials avatar with color -->
                    <?php 
                    $color = $link['color'] ?? 'primary';
                    $bgClass = "bg-{$color}-subtle";
                    $textClass = "text-{$color}";
                    ?>
                    <div class="avatar avatar-2xl">
                      <div class="avatar-name rounded-circle <?php echo $bgClass; ?> <?php echo $textClass; ?>"><span class="fs-7"><?php echo $link['icon']; ?></span></div>
                    </div>
                  
                  <?php elseif ($link['type'] === 'icon'): ?>
                    <!-- SVG icon (for support) -->
                    <div class="avatar avatar-2xl">
                      <div class="avatar-name rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center">
                        <span class="fas fa-life-ring fs-7"></span>
                      </div>
                    </div>
                  
                  <?php endif; ?>
                  
                  <p class="mb-0 fw-medium text-800 text-truncate fs-11 <?php echo !empty($link['type']) && $link['type'] === 'avatar' ? '' : 'pt-1'; ?>">
                    <?php echo $link['label']; ?>
                  </p>
                </a>
              </div>
            <?php endforeach; ?>
            
            <div class="col-12">
              <a class="btn btn-outline-primary btn-sm mt-4" href="#!">Show more</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</li>
