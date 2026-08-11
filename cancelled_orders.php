<?php
/**
 * Cancelled orders only.
 *
 * A narrower, admin/manager-only view of order_history.php filtered to
 * Cancelled -- kept as its own page because it is a first-class sidebar
 * link, but it shares order_history's query shape rather than duplicating
 * a second copy of the filter form.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager');

$title    = 'Cancelled orders';
$subtitle = 'Every order marked Cancelled';

$schemaReady = db_value(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'order_items'",
    [DB_NAME]
) !== null;

include __DIR__ . '/includes/layout/app_start.php';

if (!$schemaReady): ?>
  <div class="page-head"><h1 class="page-head__title">Cancelled orders</h1></div>
  <div class="alert alert-warning">
    <i class="bi bi-database-exclamation"></i>
    <div>
      <strong>Migration 005 has not been run yet.</strong>
      Apply <code>sql/migrations/005_order_items_and_totals.sql</code> in phpMyAdmin
      to see cancelled orders here.
    </div>
  </div>
<?php
  include __DIR__ . '/includes/layout/app_end.php';
  exit;
endif;

$search = query('customer');

$where  = ["o.status = 'Cancelled'"];
$params = [];
if ($search !== '') {
    $where[]  = 'o.customer_name LIKE ?';
    $params[] = '%' . $search . '%';
}

$orders = db_all(
    "SELECT o.*, t.table_number,
            (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', oi.item_name) SEPARATOR ', ')
               FROM order_items oi WHERE oi.order_id = o.id) AS items_summary
       FROM orders o
       LEFT JOIN tables t ON t.id = o.table_id
      WHERE " . implode(' AND ', $where) . '
      ORDER BY o.updated_at DESC',
    $params
);
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Cancelled orders</h1>
    <p class="page-head__sub"><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?></p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="<?= url('order_history.php') ?>">
      <i class="bi bi-clock-history"></i> Full history
    </a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label" for="customer">Customer</label>
        <input class="form-control" type="text" id="customer" name="customer" value="<?= e($search) ?>" placeholder="Name">
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i></button>
      </div>
      <?php if ($search !== ''): ?>
        <div class="col-12">
          <a class="btn btn-ghost btn-sm" href="<?= url('cancelled_orders.php') ?>"><i class="bi bi-x-lg"></i> Clear</a>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <?php if ($orders === []): ?>
    <div class="card-body">
      <div class="empty">
        <div class="empty__icon"><i class="bi bi-x-circle"></i></div>
        <div class="empty__title">No cancelled orders</div>
        <p class="empty__text">That's a good thing.</p>
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
            <th>Cancelled</th>
            <th class="text-end">Receipt</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><?= e($o['order_number'] ?? ('#' . $o['id'])) ?></td>
              <td><?= e($o['customer_name']) ?></td>
              <td>
                <div><?= e($o['order_type']) ?></div>
                <?php if ($o['table_number']): ?><div class="table__secondary">Table <?= e($o['table_number']) ?></div><?php endif; ?>
              </td>
              <td class="table__secondary truncate" style="max-width:260px"><?= e($o['items_summary'] ?? '—') ?></td>
              <td class="text-end fw-semi"><?= e(money($o['total_amount'])) ?></td>
              <td class="table__secondary"><?= e(date('j M Y, H:i', strtotime((string) $o['updated_at']))) ?></td>
              <td class="text-end">
                <a class="btn btn-ghost btn-icon btn-sm" href="<?= url('receipt.php?id=' . (int) $o['id']) ?>" title="Receipt">
                  <i class="bi bi-receipt"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout/app_end.php'; ?>
