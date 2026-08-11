<?php
/**
 * Application sidebar.
 *
 * Every entry declares which roles may see it. The same role list is
 * enforced by require_role() on the target page — hiding a link here is
 * convenience, NOT security (AUDIT.md §13).
 *
 * Entries whose target file does not exist yet are marked 'todo' and render
 * disabled, so the navigation shows the real shape of the system without
 * linking to blank pages.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    require_once dirname(__DIR__) . '/bootstrap.php';
}

/**
 * nav item: [label, icon, href, roles, todo?]
 */
$navSections = [
    'Overview' => [
        ['Dashboard', 'bi-grid-1x2', 'index.php', ['admin', 'manager', 'cashier', 'waiter', 'chef']],
    ],
    'Operations' => [
        ['New Order',      'bi-plus-square',   'place_order.php', ['admin', 'manager', 'cashier', 'waiter']],
        ['Orders',         'bi-receipt',       'orders.php',      ['admin', 'manager', 'cashier', 'waiter']],
        ['Kitchen',        'bi-fire',          'kitchen.php',     ['admin', 'manager', 'chef']],
        ['Order History',  'bi-clock-history', 'order_history.php', ['admin', 'manager', 'cashier']],
        ['Cancelled',      'bi-x-circle',      'cancelled_orders.php', ['admin', 'manager']],
    ],
    'Menu' => [
        ['Menu Items', 'bi-egg-fried', 'menu.php',       ['admin', 'manager']],
        ['Categories', 'bi-tags',      'categories.php', ['admin', 'manager']],
    ],
    'Front of house' => [
        ['Tables',       'bi-grid-3x3',  'tables.php',        ['admin', 'manager', 'waiter']],
        ['Reservations', 'bi-journal-bookmark', 'reservations.php', ['admin', 'manager', 'waiter', 'cashier']],
        ['Customers',    'bi-people',    'customers.php',     ['admin', 'manager', 'cashier']],
    ],
    'Money' => [
        ['Payments', 'bi-credit-card-2-front', 'payments.php', ['admin', 'manager', 'cashier']],
        ['Reports',  'bi-bar-chart-line',      'reports.php',  ['admin', 'manager']],
    ],
    'Inventory' => [
        ['Stock Items',     'bi-box-seam',     'inventory.php',       ['admin', 'manager']],
        ['Stock Movements', 'bi-arrow-left-right', 'stock_movements.php', ['admin', 'manager']],
    ],
    'People' => [
        ['Staff',      'bi-person-badge',  'employees.php',         ['admin', 'manager']],
        ['Attendance', 'bi-calendar-check','attendance.php',        ['admin', 'manager']],
        ['Users',      'bi-shield-lock',   'manage_users.php',      ['admin']],
        ['Roles',      'bi-diagram-3',     'user_roles.php',        ['admin']],
    ],
    'System' => [
        ['Settings', 'bi-gear', 'settings.php', ['admin']],
    ],
];

$initials = strtoupper(substr(trim(user_name()), 0, 1));
?>
<aside class="sidebar" id="sidebar">

  <div class="sidebar__brand">
    <?php $logo = url('images/Sahal_logo.jpeg'); ?>
    <img class="sidebar__logo" src="<?= e($logo) ?>" alt="" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'sidebar__logo',textContent:'S'}))">
    <div class="sidebar__brand-text">
      <div class="sidebar__brand-name"><?= e(APP_NAME) ?></div>
      <div class="sidebar__brand-sub">Management</div>
    </div>
  </div>

  <nav class="sidebar__nav">
    <?php foreach ($navSections as $section => $items): ?>
      <?php
      // Only render a section heading if the user can see at least one of
      // its items.
      $visible = array_filter($items, function ($item) {
          return has_role($item[3]);
      });
      if ($visible === []) {
          continue;
      }
      ?>
      <div class="sidebar__section"><?= e($section) ?></div>

      <?php foreach ($visible as $item): ?>
        <?php
        list($label, $icon, $href, $roles) = $item;
        $todo   = $item[4] ?? false;
        $active = is_current($href);
        ?>
        <?php if ($todo): ?>
          <span class="sidebar__link" style="opacity:.4;cursor:not-allowed" title="Not built yet">
            <i class="bi <?= e($icon) ?>"></i>
            <span class="sidebar__link-label"><?= e($label) ?></span>
            <span class="sidebar__badge" style="background:var(--slate-600)">soon</span>
          </span>
        <?php else: ?>
          <a class="sidebar__link <?= $active ? 'is-active' : '' ?>"
             href="<?= url($href) ?>"
             <?= $active ? 'aria-current="page"' : '' ?>>
            <i class="bi <?= e($icon) ?>"></i>
            <span class="sidebar__link-label"><?= e($label) ?></span>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar__footer">
    <?= e(APP_NAME) ?> · v<?= e(APP_VERSION) ?>
  </div>
</aside>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
