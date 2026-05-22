<?php
/**
 * User Profile Banner Card (shared include)
 * Used by profile.php and settings.php
 * 
 * Requires: $userProfile (from navbar-context.php), $user (from Auth::user())
 * Optional: $currentView ('profile' or 'settings') to highlight active button
 */

// Use global $userProfile from navbar-context.php
$up = $userProfile ?? [];
$profileImagePath = !empty($up['profile_image']) ? BASE_URL . $up['profile_image'] : '';
$fullname = htmlspecialchars($up['fullname'] ?? $user['fullname'] ?? 'User');
$firstName = htmlspecialchars($up['first_name'] ?? '');
$lastName = htmlspecialchars($up['last_name'] ?? '');
$email = htmlspecialchars($up['email'] ?? '');
$phone = htmlspecialchars($up['phone'] ?? '');
$position = htmlspecialchars($up['position'] ?? '');
$department = htmlspecialchars($up['department'] ?? '');
$location = htmlspecialchars($up['location'] ?? '');
$roleName = htmlspecialchars($up['role_name'] ?? '');
$upInitials = htmlspecialchars($up['initials'] ?? 'U');

// Build subtitle: "Position at Department"
$subtitle = '';
if ($position && $department) {
    $subtitle = $position . ' at ' . $department;
} elseif ($position) {
    $subtitle = $position;
} elseif ($department) {
    $subtitle = $department;
} elseif ($roleName) {
    $subtitle = $roleName;
}

$currentView = $currentView ?? 'profile';
?>

<!-- Profile Banner Card -->
<div class="card mb-3">
  <div class="card-header position-relative min-vh-25 mb-7">
    <div class="bg-holder rounded-3 rounded-bottom-0" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/generic/4.jpg);"></div>
    <!--/.bg-holder-->
    <div class="avatar avatar-5xl avatar-profile">
      <?php if ($profileImagePath): ?>
        <img class="rounded-circle img-thumbnail shadow-sm" src="<?php echo $profileImagePath; ?>" width="200" alt="" />
      <?php else: ?>
        <div class="avatar-name rounded-circle img-thumbnail shadow-sm"><span><?php echo $upInitials; ?></span></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-lg-8">
        <h4 class="mb-1"><?php echo $fullname; ?><span data-bs-toggle="tooltip" data-bs-placement="right" title="Verified"><small class="fa fa-check-circle text-primary" data-fa-transform="shrink-4 down-2"></small></span></h4>
        <h5 class="fs-9 fw-normal"><?php echo $subtitle ?: 'No position assigned'; ?></h5>
        <p class="text-500"><?php echo $location ?: 'No location set'; ?></p>
        <?php if ($currentView === 'settings'): ?>
          <a class="btn btn-falcon-default btn-sm px-3" href="<?php echo BASE_URL; ?>/admin/user/">
            <span class="fas fa-user me-1"></span>Profile
          </a>
          <a class="btn btn-falcon-primary btn-sm px-3 ms-2" href="<?php echo BASE_URL; ?>/admin/user/?view=settings">
            <span class="fas fa-cog me-1"></span>Settings
          </a>
        <?php else: ?>
          <a class="btn btn-falcon-primary btn-sm px-3" href="<?php echo BASE_URL; ?>/admin/user/">
            <span class="fas fa-user me-1"></span>Profile
          </a>
          <a class="btn btn-falcon-default btn-sm px-3 ms-2" href="<?php echo BASE_URL; ?>/admin/user/?view=settings">
            <span class="fas fa-cog me-1"></span>Settings
          </a>
        <?php endif; ?>
        <div class="border-bottom border-dashed my-4 d-lg-none"></div>
      </div>
      <div class="col ps-2 ps-lg-3">
        <?php if ($roleName): ?>
        <div class="d-flex align-items-center mb-2"><span class="fas fa-user-shield fs-6 me-2 text-700" data-fa-transform="grow-2"></span>
          <div class="flex-1"><h6 class="mb-0"><?php echo $roleName; ?></h6></div>
        </div>
        <?php endif; ?>
        <?php if ($email): ?>
        <a class="d-flex align-items-center mb-2" href="mailto:<?php echo $email; ?>"><span class="fas fa-envelope fs-6 me-2 text-700" data-fa-transform="grow-2"></span>
          <div class="flex-1"><h6 class="mb-0"><?php echo $email; ?></h6></div>
        </a>
        <?php endif; ?>
        <?php if ($phone): ?>
        <div class="d-flex align-items-center mb-2"><span class="fas fa-phone fs-6 me-2 text-700" data-fa-transform="grow-2"></span>
          <div class="flex-1"><h6 class="mb-0"><?php echo $phone; ?></h6></div>
        </div>
        <?php endif; ?>
        <?php if ($department): ?>
        <div class="d-flex align-items-center mb-2"><span class="fas fa-building fs-6 me-2 text-700" data-fa-transform="grow-2"></span>
          <div class="flex-1"><h6 class="mb-0"><?php echo $department; ?></h6></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
