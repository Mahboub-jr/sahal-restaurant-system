<?php
/**
 * Top bar.
 *
 * Replaces library/header.php, which shipped four hardcoded fake
 * notifications ("Lisa sent you a mail") and linked Settings, Profile and
 * Logout to Vali's static .html demo pages (AUDIT.md §1.4).
 *
 * Notifications here are real: they come from live order and table state.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    require_once dirname(__DIR__) . '/bootstrap.php';
}

$initials = strtoupper(mb_substr(trim(user_name()), 0, 1));

/* Real alerts, not placeholders. Cheap COUNT queries. */
$alerts = [];

try {
    $pendingOrders = (int) db_value(
        "SELECT COUNT(*) FROM orders WHERE status = 'Pending'"
    );
    if ($pendingOrders > 0) {
        $alerts[] = [
            'icon'  => 'bi-hourglass-split',
            'tone'  => 'warn',
            'text'  => $pendingOrders . ' order' . ($pendingOrders === 1 ? '' : 's') . ' awaiting action',
            'href'  => 'orders.php?status=Pending',
        ];
    }

    $preparing = (int) db_value(
        "SELECT COUNT(*) FROM orders WHERE status = 'Preparing'"
    );
    if ($preparing > 0) {
        $alerts[] = [
            'icon' => 'bi-fire',
            'tone' => 'info',
            'text' => $preparing . ' in the kitchen now',
            'href' => 'orders.php?status=Preparing',
        ];
    }

    $occupied = (int) db_value(
        "SELECT COUNT(*) FROM tables WHERE status = 'Occupied'"
    );
    $totalTables = (int) db_value('SELECT COUNT(*) FROM tables');
    if ($totalTables > 0 && $occupied === $totalTables) {
        $alerts[] = [
            'icon' => 'bi-exclamation-triangle',
            'tone' => 'bad',
            'text' => 'All tables are occupied',
            'href' => 'tables.php',
        ];
    }
} catch (Throwable $e) {
    // The bar must never take the page down.
    error_log('Topbar alerts failed: ' . $e->getMessage());
}
?>
<header class="topbar">
  <button class="topbar__toggle" id="sidebarToggle" type="button" aria-label="Toggle navigation">
    <i class="bi bi-list"></i>
  </button>

  <h1 class="topbar__title d-none d-sm-block"><?= e($title ?? 'Dashboard') ?></h1>

  <div class="topbar__spacer"></div>

  <!-- Theme -->
  <button class="topbar__icon-btn" id="themeToggle" type="button"
          aria-label="Toggle dark mode" title="Toggle dark mode">
    <i class="bi bi-moon-stars" data-theme-icon></i>
  </button>

  <!-- Notifications -->
  <div class="dropdown">
    <button class="topbar__icon-btn" type="button" data-bs-toggle="dropdown"
            data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications">
      <i class="bi bi-bell"></i>
      <?php if ($alerts !== []): ?><span class="topbar__dot"></span><?php endif; ?>
    </button>
    <div class="dropdown-menu dropdown-menu-end" style="width:310px">
      <div class="dropdown-header d-flex justify-content-between align-items-center">
        <span>Notifications</span>
        <?php if ($alerts !== []): ?>
          <span class="badge-soft badge-soft--brand"><?= count($alerts) ?></span>
        <?php endif; ?>
      </div>
      <div class="dropdown-divider"></div>

      <?php if ($alerts === []): ?>
        <div class="px-3 py-4 text-center text-muted" style="font-size:.8125rem">
          <i class="bi bi-check2-circle d-block mb-2" style="font-size:1.5rem;color:var(--ok)"></i>
          Nothing needs your attention
        </div>
      <?php else: ?>
        <?php foreach ($alerts as $a): ?>
          <a class="dropdown-item" href="<?= url($a['href']) ?>">
            <i class="bi <?= e($a['icon']) ?>" style="color:var(--<?= e($a['tone']) ?>)"></i>
            <span><?= e($a['text']) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- User -->
  <div class="dropdown">
    <button class="topbar__user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <span class="avatar"><?= e($initials) ?></span>
      <span class="topbar__user-meta">
        <span class="topbar__user-name d-block"><?= e(user_name()) ?></span>
        <span class="topbar__user-role d-block"><?= e(user_role_label()) ?></span>
      </span>
      <i class="bi bi-chevron-down text-subtle" style="font-size:.7rem"></i>
    </button>

    <div class="dropdown-menu dropdown-menu-end" style="width:230px">
      <div class="dropdown-header">
        <div class="fw-semi text-truncate" style="color:var(--text)"><?= e(user_name()) ?></div>
        <div class="text-truncate"><?= e(current_user()['email'] ?? '') ?></div>
      </div>
      <div class="dropdown-divider"></div>

      <?php if (has_role('admin')): ?>
        <a class="dropdown-item" href="<?= url('settings.php') ?>">
          <i class="bi bi-gear"></i> Settings
        </a>
      <?php endif; ?>

      <a class="dropdown-item" href="<?= url('index.php') ?>">
        <i class="bi bi-grid-1x2"></i> Dashboard
      </a>

      <div class="dropdown-divider"></div>

      <!-- Sign-out is a POST: a GET logout can be triggered by any image tag
           on any page (AUDIT.md E5). -->
      <form method="post" action="<?= url('logout.php') ?>" class="m-0">
        <?= csrf_field() ?>
        <button type="submit" class="dropdown-item text-danger w-100">
          <i class="bi bi-box-arrow-right"></i> Sign out
        </button>
      </form>
    </div>
  </div>
</header>
