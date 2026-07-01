<?php
/**
 * User Dropdown Component
 * Renders the user profile dropdown with links from navbar-config.php
 * Usage: include __DIR__ . '/navbar-components/user-dropdown.php';
 * 
 * Parameters (optional):
 * - $dropdownId: Unique ID for the dropdown (default: 'navbarDropdownUser')
 */
$config = require __DIR__ . '/../navbar-config.php';
$dropdownId = $dropdownId ?? 'navbarDropdownUser';
$links = $config['user_dropdown_links'];
?>

<li class="nav-item dropdown d-flex align-items-center">
  <a class="nav-link pe-0 ps-2" id="<?php echo $dropdownId; ?>" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <div class="avatar avatar-xl">
      <?php if ($profileImage): ?>
        <img class="rounded-circle" src="<?php echo BASE_URL . $profileImage; ?>" alt="User Avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
        <div class="avatar-name rounded-circle bg-primary-subtle text-primary align-items-center justify-content-center fw-bold" style="display:none !important;"><?php echo $initials; ?></div>
      <?php else: ?>
        <div class="avatar-name rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold"><?php echo $initials; ?></div>
      <?php endif; ?>
    </div>
  </a>
  <div class="dropdown-menu dropdown-caret dropdown-caret dropdown-menu-end py-0" aria-labelledby="<?php echo $dropdownId; ?>">
    <div class="bg-white dark__bg-1000 rounded-2 py-2">
      <div class="dropdown-item-text">
        <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user']['fullname'] ?? ''); ?></div>
        <div class="text-muted small"><?php echo htmlspecialchars($_SESSION['user']['role_name'] ?? ''); ?></div>
      </div>
      <div class="dropdown-divider"></div>
      <?php foreach ($links as $link): ?>
        <?php if (!empty($link['type']) && $link['type'] === 'logout'): ?>
          <a class="dropdown-item" 
             href="<?php echo $link['url']; ?>" 
             <?php echo !empty($link['data_toggle']) ? 'data-bs-toggle="' . $link['data_toggle'] . '"' : ''; ?>
             <?php echo !empty($link['data_target']) ? 'data-bs-target="' . $link['data_target'] . '"' : ''; ?>>
            <?php echo $link['label']; ?>
          </a>
        <?php else: ?>
          <a class="dropdown-item" href="<?php echo $link['url']; ?>">
            <?php echo $link['label']; ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</li>
