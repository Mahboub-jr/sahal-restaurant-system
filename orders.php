<?php
/**
 * Active orders.
 *
 * Reads and renders only -- every status change and edit posts to
 * actions/orders.php. Replaces the mysqli version that built its WHERE
 * clause by string-concatenating $_GET values (still parameterised here)
 * and mutated order status over plain GET links (AUDIT.md E5).
 *
 * "Active" now means Pending / Preparing / Ready by default. The old page
 * excluded only Completed, which left Cancelled orders cluttering the
 * default view; Cancelled and Completed both belong on Order History /
 * Cancelled orders, which is what those pages are for.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager', 'cashier', 'waiter');

$title    = 'Orders';
$subtitle = 'Everything still in progress';

$schemaReady = db_value(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'order_items'",
    [DB_NAME]
) !== null;

include __DIR__ . '/includes/layout/app_start.php';

if (!$schemaReady): ?>
  <div class="page-head"><h1 class="page-head__title">Orders</h1></div>
  <div class="alert alert-warning">
    <i class="bi bi-database-exclamation"></i>
    <div>
      <strong>Migration 005 has not been run yet.</strong>
      Apply <code>sql/migrations/005_order_items_and_totals.sql</code> in phpMyAdmin
      to see orders here.
    </div>
  </div>
<?php
  include __DIR__ . '/includes/layout/app_end.php';
  exit;
endif;

$ORDER_TYPES = ['Dine-In', 'Takeaway', 'Delivery'];
$STATUSES    = ['Pending', 'Preparing', 'Ready', 'Completed', 'Cancelled'];

$search     = query('customer');
$statusF    = one_of(query('status'), $STATUSES, '');
$typeF      = one_of(query('order_type'), $ORDER_TYPES, '');
$fromDate   = query('from_date');
$toDate     = query('to_date');

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = 'o.customer_name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($statusF !== '') {
    $where[]  = 'o.status = ?';
    $params[] = $statusF;
} else {
    // Default view: still in progress. Completed/Cancelled live elsewhere.
    $where[]  = "o.status IN ('Pending','Preparing','Ready')";
}
if ($typeF !== '') {
    $where[]  = 'o.order_type = ?';
    $params[] = $typeF;
}
if ($fromDate !== '') {
    $where[]  = 'DATE(o.created_at) >= ?';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $where[]  = 'DATE(o.created_at) <= ?';
    $params[] = $toDate;
}

$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$orders = db_all(
    "SELECT o.*, t.table_number, u.name AS waiter_name,
            (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', oi.item_name) SEPARATOR ', ')
               FROM order_items oi WHERE oi.order_id = o.id) AS items_summary
       FROM orders o
       LEFT JOIN tables t ON t.id = o.table_id
       LEFT JOIN users  u ON u.id = o.user_id
       $whereSql
      ORDER BY o.created_at DESC",
    $params
);

$allowedNext = order_status_transitions();
$statusRoles = [
    'Preparing' => ['admin', 'manager', 'cashier', 'waiter'],
    'Ready'     => ['admin', 'manager', 'cashier', 'waiter'],
    'Completed' => ['admin', 'manager', 'cashier'],
    'Cancelled' => ['admin', 'manager'],
];
$paymentColour = [
    'Unpaid'          => 'bad',
    'Partially Paid'  => 'warn',
    'Paid'            => 'ok',
];
$statusColour = [
    'Pending'   => 'neutral',
    'Preparing' => 'warn',
    'Ready'     => 'info',
    'Completed' => 'ok',
    'Cancelled' => 'bad',
];
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Orders</h1>
    <p class="page-head__sub"><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?> shown</p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="<?= url('order_history.php') ?>">
      <i class="bi bi-clock-history"></i> Order history
    </a>
    <?php if (has_role('admin', 'manager', 'cashier', 'waiter')): ?>
      <a class="btn btn-primary" href="<?= url('place_order.php') ?>">
        <i class="bi bi-plus-lg"></i> New order
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label" for="customer">Customer</label>
        <input class="form-control" type="text" id="customer" name="customer" value="<?= e($search) ?>" placeholder="Name">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="status">Status</label>
        <select class="form-select" id="status" name="status">
          <option value="">Active (default)</option>
          <?php foreach ($STATUSES as $s): ?>
            <option value="<?= e($s) ?>" <?= $statusF === $s ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="order_type">Type</label>
        <select class="form-select" id="order_type" name="order_type">
          <option value="">Any</option>
          <?php foreach ($ORDER_TYPES as $t): ?>
            <option value="<?= e($t) ?>" <?= $typeF === $t ? 'selected' : '' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="from_date">From</label>
        <input class="form-control" type="date" id="from_date" name="from_date" value="<?= e($fromDate) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="to_date">To</label>
        <input class="form-control" type="date" id="to_date" name="to_date" value="<?= e($toDate) ?>">
      </div>
      <div class="col-md-1 d-flex gap-2">
        <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-search"></i></button>
      </div>
      <?php if ($search !== '' || $statusF !== '' || $typeF !== '' || $fromDate !== '' || $toDate !== ''): ?>
        <div class="col-12">
          <a class="btn btn-ghost btn-sm" href="<?= url('orders.php') ?>"><i class="bi bi-x-lg"></i> Clear filters</a>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <?php if ($orders === []): ?>
    <div class="card-body">
      <div class="empty">
        <div class="empty__icon"><i class="bi bi-receipt"></i></div>
        <div class="empty__title">No orders match</div>
        <p class="empty__text">Try a different filter, or place a new order.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Customer</th>
            <th>Type / table</th>
            <th>Items</th>
            <th class="text-end">Total</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Placed</th>
            <th style="width:220px" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <?php
            $next  = $allowedNext[$o['status']] ?? [];
            $terminal = $next === [];
            ?>
            <tr>
              <td>
                <div class="table__primary"><?= e($o['order_number'] ?? ('#' . $o['id'])) ?></div>
              </td>
              <td>
                <div class="table__primary"><?= e($o['customer_name']) ?></div>
                <?php if ($o['waiter_name']): ?>
                  <div class="table__secondary">by <?= e($o['waiter_name']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div><?= e($o['order_type']) ?></div>
                <?php if ($o['table_number']): ?>
                  <div class="table__secondary">Table <?= e($o['table_number']) ?></div>
                <?php endif; ?>
              </td>
              <td class="table__secondary truncate" style="max-width:260px">
                <?= e($o['items_summary'] ?? '—') ?>
              </td>
              <td class="text-end fw-semi"><?= e(money($o['total_amount'])) ?></td>
              <td>
                <span class="badge-soft badge-soft--<?= $statusColour[$o['status']] ?? 'neutral' ?>">
                  <?= e($o['status']) ?>
                </span>
              </td>
              <td>
                <span class="badge-soft badge-soft--<?= $paymentColour[$o['payment_status']] ?? 'neutral' ?>">
                  <?= e($o['payment_status']) ?>
                </span>
              </td>
              <td class="table__secondary"><?= e(time_ago($o['created_at'])) ?></td>
              <td>
                <div class="table__actions justify-content-end">
                  <?php foreach ($next as $nextStatus): ?>
                    <?php if (has_role($statusRoles[$nextStatus] ?? ['admin'])): ?>
                      <form method="post" action="<?= url('actions/orders.php') ?>" class="m-0"
                            <?= $nextStatus === 'Cancelled' ? 'data-confirm="Cancel order ' . e($o['order_number'] ?? ('#' . $o['id'])) . '?"' : '' ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="do" value="set_status">
                        <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                        <input type="hidden" name="status" value="<?= e($nextStatus) ?>">
                        <button type="submit" class="btn btn-ghost btn-sm" title="<?= e($nextStatus) ?>">
                          <?= e($nextStatus) ?>
                        </button>
                      </form>
                    <?php endif; ?>
                  <?php endforeach; ?>

                  <?php if (!$terminal && has_role('admin', 'manager', 'cashier')): ?>
                    <a class="btn btn-ghost btn-icon btn-sm" href="<?= url('update_order.php?id=' . (int) $o['id']) ?>" title="Edit">
                      <i class="bi bi-pencil"></i>
                    </a>
                  <?php endif; ?>

                  <a class="btn btn-ghost btn-icon btn-sm" href="<?= url('receipt.php?id=' . (int) $o['id']) ?>" title="Receipt">
                    <i class="bi bi-receipt"></i>
                  </a>

                  <?php if (has_role('admin', 'manager', 'cashier') && $o['status'] !== 'Cancelled'): ?>
                    <a class="btn btn-ghost btn-icon btn-sm" href="<?= url('payments.php?order_id=' . (int) $o['id']) ?>" title="Payments">
                      <i class="bi bi-cash-coin"></i>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout/app_end.php'; ?>
