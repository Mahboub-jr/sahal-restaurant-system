<?php
/**
 * Top bar for pages still on the legacy Vali layout.
 *
 * The previous version shipped four hardcoded fake notifications
 * ("Lisa sent you a mail", "Mail server not working") and pointed Settings,
 * Profile and Logout at Vali's static demo pages — page-user.html and
 * page-login.html — none of which exist as real destinations
 * (AUDIT.md §1.4).
 *
 * It now shows the real signed-in user and links to real pages.
 * Converted pages use includes/layout/topbar.php instead.
 */
$base = defined('BASE_URL') ? BASE_URL : '';
$name = function_exists('user_name') ? user_name() : 'User';
$role = function_exists('user_role_label') ? user_role_label() : '';
$isAdmin = function_exists('has_role') && has_role('admin');
?>
<header class="app-header">
  <a class="app-header__logo" href="<?= $base ?>index.php">
    <?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Restaurant') ?>
  </a>

  <a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>

  <ul class="app-nav">
    <li class="dropdown">
      <a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open profile menu">
        <i class="bi bi-person-circle fs-4"></i>
      </a>
      <ul class="dropdown-menu settings-menu dropdown-menu-end">
        <li class="px-3 py-2">
          <div style="font-weight:600"><?= htmlspecialchars($name) ?></div>
          <div style="font-size:12px;color:#64748b"><?= htmlspecialchars($role) ?></div>
        </li>
        <li><hr class="dropdown-divider"></li>

        <li>
          <a class="dropdown-item" href="<?= $base ?>index.php">
            <i class="bi bi-grid-1x2 me-2"></i> Dashboard
          </a>
        </li>

        <?php if ($isAdmin): ?>
          <li>
            <a class="dropdown-item" href="<?= $base ?>settings.php">
              <i class="bi bi-gear me-2"></i> Settings
            </a>
          </li>
        <?php endif; ?>

        <li><hr class="dropdown-divider"></li>

        <li>
          <!-- POST, not a link: a GET logout can be fired by any <img> tag. -->
          <form method="post" action="<?= $base ?>logout.php" class="m-0 px-1">
            <?= function_exists('csrf_field') ? csrf_field() : '' ?>
            <button type="submit" class="dropdown-item text-danger">
              <i class="bi bi-box-arrow-right me-2"></i> Sign out
            </button>
          </form>
        </li>
      </ul>
    </li>
  </ul>
</header>
