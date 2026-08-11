<?php
/**
 * Order history — Completed and Cancelled orders.
 *
 * Read-only. No status can change from here; go to Orders while a job is
 * still in progress.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager', 'cashier');

$title    = 'Order history';
$subtitle = 'Completed & cancelled orders';

$schemaReady = db_value(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'order_items'",
    [DB_NAME]
) !== null;

include __DIR__ . '/includes/layout/app_start.php';

if (!$schemaReady): ?>
  <div class="page-head"><h1 class="page-head__title">Order history</h1></div>
  <div class="alert alert-warning">
    <i class="bi bi-database-exclamation"></i>
    <div>
      <strong>Migration 005 has not been run yet.</strong>
      Apply <code>sql/migrations/005_order_items_and_totals.sql</code> in phpMyAdmin
      to see order history here.
    </div>
  </div>
<?php
  include __DIR__ . '/includes/layout/app_end.php';
  exit;
endif;

$search   = query('customer');
$statusF  = one_of(query('status'), ['Completed', 'Cancelled'], '');
$fromDate = query('from_date');
$toDate   = query('to_date');

$where  = ["o.status IN ('Completed','Cancelled')"];
$params = [];

if ($statusF !== '') {
    $where[]  = 'o.status = ?';
    $params[] = $statusF;
}
if ($search !== '') {
    $where[]  = 'o.customer_name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($fromDate !== '') {
    $where[]  = 'DATE(o.created_at) >= ?';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $where[]  = 'DATE(o.created_at) <= ?';
    $params[] = $toDate;
}

$orders = db_all(
    "SELECT o.*, t.table_number, u.name AS waiter_name,
            (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', oi.item_name) SEPARATOR ', ')
               FROM order_items oi WHERE oi.order_id = o.id) AS items_summary
       FROM orders o
       LEFT JOIN tables t ON t.id = o.table_id
       LEFT JOIN users  u ON u.id = o.user_id
      WHERE " . implode(' AND ', $where) . '
      ORDER BY o.updated_at DESC',
    $params
);
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Order history</h1>
    <p class="page-head__sub"><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?></p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="<?= url('orders.php') ?>">
      <i class="bi bi-receipt"></i> Active orders
    </a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label" for="customer">Customer</label>
        <input class="form-control" type="text" id="customer" name="customer" value="<?= e($search) ?>" placeholder="Name">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="status">Status</label>
        <select class="form-select" id="status" name="status">
          <option value="">Completed & Cancelled</option>
          <option value="Completed" <?= $statusF === 'Completed' ? 'selected' : '' ?>>Completed only</option>
          <option value="Cancelled" <?= $statusF === 'Cancelled' ? 'selected' : '' ?>>Cancelled only</option>
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
      <div class="col-md-1">
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i></button>
      </div>
      <?php if ($search !== '' || $statusF !== '' || $fromDate !== '' || $toDate !== ''): ?>
        <div class="col-12">
          <a class="btn btn-ghost btn-sm" href="<?= url('order_history.php') ?>"><i class="bi bi-x-lg"></i> Clear filters</a>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <?php if ($orders === []): ?>
    <div class="card-body">
      <div class="empty">
        <div class="empty__icon"><i class="bi bi-clock-history"></i></div>
        <div class="empty__title">Nothing here yet</div>
        <p class="empty__text">Completed and cancelled orders will appear once they happen.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table" id="historyTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Customer</th>
            <th>Type / table</th>
            <th>Items</th>
            <th class="text-end">Total</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Updated</th>
            <th class="text-end">Receipt</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><?= e($o['order_number'] ?? ('#' . $o['id'])) ?></td>
              <td>
                <div class="table__primary"><?= e($o['customer_name']) ?></div>
                <?php if ($o['waiter_name']): ?><div class="table__secondary">by <?= e($o['waiter_name']) ?></div><?php endif; ?>
              </td>
              <td>
                <div><?= e($o['order_type']) ?></div>
                <?php if ($o['table_number']): ?><div class="table__secondary">Table <?= e($o['table_number']) ?></div><?php endif; ?>
              </td>
              <td class="table__secondary truncate" style="max-width:260px"><?= e($o['items_summary'] ?? '—') ?></td>
              <td class="text-end fw-semi"><?= e(money($o['total_amount'])) ?></td>
              <td>
                <span class="badge-soft badge-soft--<?= $o['status'] === 'Completed' ? 'ok' : 'bad' ?>"><?= e($o['status']) ?></span>
              </td>
              <td>
                <span class="badge-soft badge-soft--<?= $o['payment_status'] === 'Paid' ? 'ok' : ($o['payment_status'] === 'Partially Paid' ? 'warn' : 'bad') ?>">
                  <?= e($o['payment_status']) ?>
                </span>
              </td>
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
