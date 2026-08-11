<?php
/**
 * Printable reservation confirmation slip. Replaces receipt_booking.php.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager', 'waiter', 'cashier');

$id = query_int('id');
if ($id <= 0) {
    flash_error('Invalid reservation.');
    redirect('reservations.php');
}

$reservation = db_one(
    'SELECT r.*, t.table_number
       FROM reservations r
       LEFT JOIN tables t ON t.id = r.table_id
      WHERE r.id = ?',
    [$id]
);

if ($reservation === null) {
    flash_error('That reservation no longer exists.');
    redirect('reservations.php');
}

$restaurantName = setting('restaurant_name', APP_NAME);
$phone          = setting('phone', '');

$statusColour = [
    'Pending'   => 'secondary',
    'Confirmed' => 'primary',
    'Seated'    => 'warning',
    'Completed' => 'success',
    'Cancelled' => 'danger',
    'No-show'   => 'danger',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reservation #<?= (int) $reservation['id'] ?> · <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    @media print { .no-print { display: none !important; } body { background: #fff; } }
    body { background: #f8f9fa; }
    .slip { background: #fff; padding: 2rem; margin: 2rem auto; max-width: 480px; box-shadow: 0 0 10px rgba(0,0,0,.1); border-radius: .5rem; }
  </style>
</head>
<body class="py-4">
  <div class="slip">
    <div class="d-flex justify-content-between align-items-start mb-3 no-print">
      <a class="btn btn-outline-secondary btn-sm" href="<?= url('reservations.php') ?>">
        <i class="bi bi-arrow-left"></i> Back
      </a>
      <button class="btn btn-primary btn-sm" onclick="window.print()">
        <i class="bi bi-printer"></i> Print
      </button>
    </div>

    <div class="text-center mb-3">
      <h2><?= e($restaurantName) ?></h2>
      <p class="text-muted mb-0">Reservation confirmation</p>
      <?php if ($phone !== ''): ?><div class="text-muted small"><?= e($phone) ?></div><?php endif; ?>
    </div>
    <hr>

    <table class="table table-borderless mb-0">
      <tr><th style="width:40%">Reservation #</th><td><?= (int) $reservation['id'] ?></td></tr>
      <tr><th>Customer</th><td><?= e($reservation['customer_name']) ?></td></tr>
      <?php if ($reservation['phone']): ?><tr><th>Phone</th><td><?= e($reservation['phone']) ?></td></tr><?php endif; ?>
      <tr><th>Party size</th><td><?= $reservation['party_size'] !== null ? (int) $reservation['party_size'] : '—' ?></td></tr>
      <tr><th>Date &amp; time</th><td><?= e(date('l, j F Y — H:i', strtotime((string) $reservation['reserved_at']))) ?></td></tr>
      <tr><th>Table</th><td><?= $reservation['table_number'] ? e($reservation['table_number']) : 'Not yet assigned' ?></td></tr>
      <tr>
        <th>Status</th>
        <td><span class="badge bg-<?= $statusColour[$reservation['status']] ?? 'secondary' ?>"><?= e($reservation['status']) ?></span></td>
      </tr>
      <?php if ($reservation['notes']): ?>
        <tr><th>Notes</th><td><?= e($reservation['notes']) ?></td></tr>
      <?php endif; ?>
    </table>

    <p class="text-center text-muted mb-0 mt-4">We look forward to seeing you!</p>
  </div>
</body>
</html>
