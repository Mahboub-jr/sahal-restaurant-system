<?php
/**
 * Invoice -- an order plus every payment recorded against it.
 *
 * Replaces receipt_payment.php, which showed one payment record at a time
 * (joined to a single customer) and had no idea an order could have more
 * than one payment, or none. This is keyed by order, not by payment.
 *
 * Its own minimal HTML shell, like receipt.php -- meant to be printed on
 * its own, not inside the app chrome.
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

$payments = db_all(
    'SELECT p.*, c.name AS customer_name
       FROM payments p
       LEFT JOIN customers c ON c.id = p.customer_id
      WHERE p.order_id = ?
      ORDER BY p.payment_date',
    [$id]
);

$paidSum = 0.0;
foreach ($payments as $p) {
    if ($p['status'] === 'Paid') {
        $paidSum += (float) $p['amount'];
    }
}
$balance = round((float) $order['total_amount'] - $paidSum, 2);

$restaurantName   = setting('restaurant_name', APP_NAME);
$address          = setting('address', '');
$phone            = setting('phone', '');
$invoicePrefix    = (string) setting('invoice_prefix', 'INV-');
$footerNote       = setting('invoice_footer_note', '');
$showLogo         = (string) setting('show_logo_on_invoice', '0') === '1';
$logoUrl          = $showLogo ? upload_url((string) setting('logo', ''), 'settings') : null;
$invoiceNumber    = $invoicePrefix . str_pad((string) $order['id'], 6, '0', STR_PAD_LEFT);

$statusColour = [
    'Pending'   => 'secondary',
    'Preparing' => 'warning',
    'Ready'     => 'info',
    'Completed' => 'success',
    'Cancelled' => 'danger',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoice <?= e($invoiceNumber) ?> · <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    @media print { .no-print { display: none !important; } body { background: #fff; } }
    body { background: #f8f9fa; }
    .invoice { background: #fff; padding: 2rem; margin: 2rem auto; max-width: 680px; box-shadow: 0 0 10px rgba(0,0,0,.1); border-radius: .5rem; }
    .invoice h2, .invoice h6 { margin-bottom: .25rem; }
    .table th, .table td { vertical-align: middle; }
    .logo { max-height: 64px; margin-bottom: .5rem; }
  </style>
</head>
<body class="py-4">
  <div class="invoice">
    <div class="d-flex justify-content-between align-items-start mb-3 no-print">
      <a class="btn btn-outline-secondary btn-sm" href="<?= url('orders.php') ?>">
        <i class="bi bi-arrow-left"></i> Back
      </a>
      <div class="d-flex gap-2">
        <?php if (has_role('admin', 'manager', 'cashier') && $balance > 0 && $order['status'] !== 'Cancelled'): ?>
          <a class="btn btn-success btn-sm" href="<?= url('payments.php?order_id=' . $id) ?>">
            <i class="bi bi-cash-coin"></i> Record payment
          </a>
        <?php endif; ?>
        <button class="btn btn-primary btn-sm" onclick="window.print()">
          <i class="bi bi-printer"></i> Print
        </button>
      </div>
    </div>

    <div class="text-center mb-3">
      <?php if ($logoUrl !== null): ?><img class="logo" src="<?= e($logoUrl) ?>" alt=""><?php endif; ?>
      <h2><?= e($restaurantName) ?></h2>
      <?php if ($address !== ''): ?><div class="text-muted small"><?= e($address) ?></div><?php endif; ?>
      <?php if ($phone !== ''): ?><div class="text-muted small"><?= e($phone) ?></div><?php endif; ?>
    </div>
    <hr>

    <div class="row mb-3">
      <div class="col-6">
        <h6>Invoice: <strong><?= e($invoiceNumber) ?></strong></h6>
        <h6>Order: <strong><?= e($order['order_number'] ?? ('#' . $order['id'])) ?></strong></h6>
        <h6 class="mb-0">
          Order status:
          <span class="badge bg-<?= $statusColour[$order['status']] ?? 'secondary' ?>"><?= e($order['status']) ?></span>
        </h6>
      </div>
      <div class="col-6 text-end">
        <h6>Billed to</h6>
        <p class="mb-0"><?= e($order['customer_name']) ?></p>
        <small class="text-muted">
          <?= e($order['order_type']) ?><?= $order['table_number'] ? ' · Table ' . e($order['table_number']) : '' ?>
        </small>
        <?php if ($order['waiter_name']): ?>
          <div class="text-muted small">Served by <?= e($order['waiter_name']) ?></div>
        <?php endif; ?>
        <div class="text-muted small"><?= e(date('d M Y, H:i', strtotime((string) $order['created_at']))) ?></div>
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
          <th colspan="4" class="text-end fs-5">Total due</th>
          <th class="text-end fs-5"><?= e(money($order['total_amount'])) ?></th>
        </tr>
      </tfoot>
    </table>

    <h6 class="mt-4">Payments</h6>
    <?php if ($payments === []): ?>
      <p class="text-muted">No payments recorded yet.</p>
    <?php else: ?>
      <table class="table table-sm table-bordered">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Method</th>
            <th>Status</th>
            <th>Customer on file</th>
            <th class="text-end">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td><?= e(date('d M Y, H:i', strtotime((string) $p['payment_date']))) ?></td>
              <td><?= e($p['payment_method']) ?></td>
              <td>
                <span class="badge bg-<?= $p['status'] === 'Paid' ? 'success' : 'warning' ?>"><?= e($p['status']) ?></span>
              </td>
              <td><?= e($p['customer_name'] ?? '—') ?></td>
              <td class="text-end"><?= e(money($p['amount'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <div class="row">
      <div class="col-6"></div>
      <div class="col-6">
        <table class="table table-borderless mb-0">
          <tr>
            <th>Paid</th>
            <td class="text-end"><?= e(money($paidSum)) ?></td>
          </tr>
          <tr class="fs-5 fw-semibold">
            <th><?= $balance > 0 ? 'Balance due' : ($balance < 0 ? 'Overpaid by' : 'Settled') ?></th>
            <td class="text-end"><?= e(money(abs($balance))) ?></td>
          </tr>
        </table>
      </div>
    </div>

    <?php if ($balance < 0): ?>
      <div class="alert alert-warning mt-2 mb-0">
        <i class="bi bi-exclamation-triangle"></i>
        This order has received more in "Paid" payments than its total. That is not
        auto-corrected — review the payments above and adjust or refund by hand.
      </div>
    <?php endif; ?>

    <p class="text-center text-muted mb-0 mt-4">
      <?= $footerNote !== '' ? e($footerNote) : 'Thank you for dining with us!' ?>
    </p>
  </div>
</body>
</html>
