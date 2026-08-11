<?php
/**
 * Printable receipt.
 *
 * Deliberately its own minimal HTML shell rather than includes/layout/ --
 * a receipt is meant to be printed on its own, not inside the app chrome.
 * Still goes through bootstrap.php for auth, e() and money().
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager', 'cashier', 'waiter');

$id = query_int('id');
if ($id <= 0) {
    flash_error('Invalid order.');
    redirect('orders.php');
}

$order = db_one(
    'SELECT o.*, t.table_number, u.name AS waiter_name
       FROM orders o
       LEFT JOIN tables t ON t.id = o.table_id
       LEFT JOIN users  u ON u.id = o.user_id
      WHERE o.id = ?',
    [$id]
);

if ($order === null) {
    flash_error('That order no longer exists.');
    redirect('orders.php');
}

$hasOrderItems = db_value(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'order_items'",
    [DB_NAME]
) !== null;

$lines = $hasOrderItems
    ? db_all('SELECT item_name, unit_price, quantity, subtotal FROM order_items WHERE order_id = ? ORDER BY id', [$id])
    : [];

$restaurantName = setting('restaurant_name', APP_NAME);
$address        = setting('address', '');
$phone          = setting('phone', '');
$footerNote     = setting('invoice_footer_note', '');

$statusColour = [
    'Pending'   => 'secondary',
    'Preparing' => 'warning',
    'Ready'     => 'info',
    'Completed' => 'success',
    'Cancelled' => 'danger',
];
$paymentColour = [
    'Unpaid'         => 'danger',
    'Partially Paid' => 'warning',
    'Paid'           => 'success',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Receipt <?= e($order['order_number'] ?? ('#' . $order['id'])) ?> · <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    @media print { .no-print { display: none !important; } body { background: #fff; } }
    body { background: #f8f9fa; }
    .receipt { background: #fff; padding: 2rem; margin: 2rem auto; max-width: 620px; box-shadow: 0 0 10px rgba(0,0,0,.1); border-radius: .5rem; }
    .receipt h2, .receipt h6 { margin-bottom: .25rem; }
    .table th, .table td { vertical-align: middle; }
  </style>
</head>
<body class="py-4">
  <div class="receipt">
    <div class="d-flex justify-content-between align-items-start mb-3 no-print">
      <a class="btn btn-outline-secondary btn-sm" href="<?= url('orders.php') ?>">
        <i class="bi bi-arrow-left"></i> Back
      </a>
      <button class="btn btn-primary btn-sm" onclick="window.print()">
        <i class="bi bi-printer"></i> Print
      </button>
    </div>

    <div class="text-center mb-3">
      <h2><?= e($restaurantName) ?></h2>
      <?php if ($address !== ''): ?><div class="text-muted small"><?= e($address) ?></div><?php endif; ?>
      <?php if ($phone !== ''): ?><div class="text-muted small"><?= e($phone) ?></div><?php endif; ?>
    </div>
    <hr>

    <div class="row mb-3">
      <div class="col-6">
        <h6>Receipt: <strong><?= e($order['order_number'] ?? ('#' . $order['id'])) ?></strong></h6>
        <h6>Date: <strong><?= e(date('d M Y, H:i', strtotime((string) $order['created_at']))) ?></strong></h6>
        <h6 class="mb-0">
          Status:
          <span class="badge bg-<?= $statusColour[$order['status']] ?? 'secondary' ?>"><?= e($order['status']) ?></span>
          <span class="badge bg-<?= $paymentColour[$order['payment_status']] ?? 'secondary' ?>"><?= e($order['payment_status']) ?></span>
        </h6>
      </div>
      <div class="col-6 text-end">
        <h6>Customer</h6>
        <p class="mb-0"><?= e($order['customer_name']) ?></p>
        <small class="text-muted">
          <?= e($order['order_type']) ?><?= $order['table_number'] ? ' · Table ' . e($order['table_number']) : '' ?>
        </small>
        <?php if ($order['waiter_name']): ?>
          <div class="text-muted small">Served by <?= e($order['waiter_name']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <table class="table table-bordered">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Item</th>
          <th class="text-center">Qty</th>
          <th class="text-end">Unit</th>
          <th class="text-end">Line total</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($lines === []): ?>
          <tr>
            <td colspan="5" class="text-center text-muted">
              <?= $hasOrderItems ? 'No line items recorded for this order.' : 'Item detail unavailable — run migration 005.' ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($lines as $i => $l): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= e($l['item_name']) ?></td>
              <td class="text-center"><?= (int) $l['quantity'] ?></td>
              <td class="text-end"><?= $l['unit_price'] !== null ? e(money($l['unit_price'])) : '—' ?></td>
              <td class="text-end"><?= $l['subtotal'] !== null ? e(money($l['subtotal'])) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="4" class="text-end">Subtotal</th>
          <th class="text-end"><?= $order['subtotal'] !== null ? e(money($order['subtotal'])) : '—' ?></th>
        </tr>
        <?php if ((float) $order['discount'] > 0): ?>
          <tr>
            <th colspan="4" class="text-end">Discount</th>
            <th class="text-end">-<?= e(money($order['discount'])) ?></th>
          </tr>
        <?php endif; ?>
        <tr>
          <th colspan="4" class="text-end">Tax</th>
          <th class="text-end"><?= $order['tax'] !== null ? e(money($order['tax'])) : '—' ?></th>
        </tr>
        <tr>
          <th colspan="4" class="text-end">Service charge</th>
          <th class="text-end"><?= $order['service_charge'] !== null ? e(money($order['service_charge'])) : '—' ?></th>
        </tr>
        <tr class="table-light">
          <th colspan="4" class="text-end fs-5">Total</th>
          <th class="text-end fs-5"><?= e(money($order['total_amount'])) ?></th>
        </tr>
      </tfoot>
    </table>

    <p class="text-center text-muted mb-0 mt-4">
      <?= $footerNote !== '' ? e($footerNote) : 'Thank you for dining with us!' ?>
    </p>
  </div>
</body>
</html>
