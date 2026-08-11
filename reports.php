<?php
/**
 * Sales report -- real SQL, not a PHP loop over every row.
 *
 * The old version filtered on orders.served_by and orders.payment_method,
 * neither of which has ever existed as a column on `orders` -- every
 * request fataled with "Unknown column" the moment a filter or the export
 * ran. Waiter now comes from orders.user_id (added by migration 005);
 * payment method comes from the payments table, since an order can have
 * more than one payment method across multiple payments.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager');

$title    = 'Sales report';
$subtitle = 'Orders, filtered and totalled';

$hasUserId = db_value(
    "SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'user_id'",
    [DB_NAME]
) !== null;

$STATUSES = ['Pending', 'Preparing', 'Ready', 'Completed', 'Cancelled'];
$METHODS  = ['Cash', 'Card', 'Mobile Money'];

$customerF = query('customer');
$statusF   = one_of(query('status'), $STATUSES, '');
$methodF   = one_of(query('payment'), $METHODS, '');
$staffF    = query_int('staff');
$fromDate  = query('from_date');
$toDate    = query('to_date');

$where  = [];
$params = [];
if ($customerF !== '') {
    $where[]  = 'o.customer_name LIKE ?';
    $params[] = '%' . $customerF . '%';
}
if ($statusF !== '') {
    $where[]  = 'o.status = ?';
    $params[] = $statusF;
}
if ($methodF !== '') {
    $where[]  = 'EXISTS (SELECT 1 FROM payments p WHERE p.order_id = o.id AND p.payment_method = ?)';
    $params[] = $methodF;
}
if ($staffF > 0 && $hasUserId) {
    $where[]  = 'o.user_id = ?';
    $params[] = $staffF;
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

$userJoin = $hasUserId ? 'LEFT JOIN users u ON u.id = o.user_id' : '';
$userCol  = $hasUserId ? 'u.name AS waiter_name' : 'NULL AS waiter_name';

$orders = db_all(
    "SELECT o.id, o.order_number, o.customer_name, o.created_at, o.total_amount, o.status, $userCol,
            (SELECT GROUP_CONCAT(DISTINCT payment_method SEPARATOR ', ') FROM payments p WHERE p.order_id = o.id) AS payment_methods
       FROM orders o
       $userJoin
       $whereSql
      ORDER BY o.created_at DESC",
    $params
);

$staffList = $hasUserId
    ? db_all("SELECT DISTINCT u.id, u.name FROM orders o JOIN users u ON u.id = o.user_id ORDER BY u.name")
    : [];

$total = 0.0;
foreach ($orders as $o) {
    $total += (float) $o['total_amount'];
}

$statusColour = ['Pending' => 'neutral', 'Preparing' => 'warn', 'Ready' => 'info', 'Completed' => 'ok', 'Cancelled' => 'bad'];

$exportQuery = http_build_query([
    'customer'  => $customerF,
    'status'    => $statusF,
    'payment'   => $methodF,
    'staff'     => $staffF ?: '',
    'from_date' => $fromDate,
    'to_date'   => $toDate,
]);

include __DIR__ . '/includes/layout/app_start.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Sales report</h1>
    <p class="page-head__sub"><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?> · total <?= e(money($total)) ?></p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="<?= url('export_report.php?' . $exportQuery) ?>">
      <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
    </a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label" for="customer">Customer</label>
        <input class="form-control" type="text" id="customer" name="customer" value="<?= e($customerF) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="status">Status</label>
        <select class="form-select" id="status" name="status">
          <option value="">Any</option>
          <?php foreach ($STATUSES as $s): ?>
            <option value="<?= e($s) ?>" <?= $statusF === $s ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="payment">Payment</label>
        <select class="form-select" id="payment" name="payment">
          <option value="">Any</option>
          <?php foreach ($METHODS as $m): ?>
            <option value="<?= e($m) ?>" <?= $methodF === $m ? 'selected' : '' ?>><?= e($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="staff">Staff</label>
        <select class="form-select" id="staff" name="staff">
          <option value="">Any</option>
          <?php foreach ($staffList as $s): ?>
            <option value="<?= (int) $s['id'] ?>" <?= $staffF === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="from_date">From</label>
        <input class="form-control" type="date" id="from_date" name="from_date" value="<?= e($fromDate) ?>">
      </div>
      <div class="col-md-1">
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i></button>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="to_date">To</label>
        <input class="form-control" type="date" id="to_date" name="to_date" value="<?= e($toDate) ?>">
      </div>
      <?php if ($customerF !== '' || $statusF !== '' || $methodF !== '' || $staffF > 0 || $fromDate !== '' || $toDate !== ''): ?>
        <div class="col-md-2">
          <a class="btn btn-ghost btn-sm" href="<?= url('reports.php') ?>"><i class="bi bi-x-lg"></i> Reset</a>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if (!$hasUserId): ?>
  <div class="alert alert-warning mb-3">
    <i class="bi bi-database-exclamation"></i>
    Run migration 005 to filter and show which staff member served each order.
  </div>
<?php endif; ?>

<div class="card">
  <?php if ($orders === []): ?>
    <div class="card-body">
      <div class="empty">
        <div class="empty__icon"><i class="bi bi-graph-up"></i></div>
        <div class="empty__title">No sales data for this filter</div>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Customer</th>
            <th>Date</th>
            <th class="text-end">Amount</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Staff</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><?= e($o['order_number'] ?? ('#' . $o['id'])) ?></td>
              <td class="table__primary"><?= e($o['customer_name']) ?></td>
              <td class="table__secondary"><?= e(date('j M Y', strtotime((string) $o['created_at']))) ?></td>
              <td class="text-end fw-semi"><?= e(money($o['total_amount'])) ?></td>
              <td><span class="badge-soft badge-soft--<?= $statusColour[$o['status']] ?? 'neutral' ?>"><?= e($o['status']) ?></span></td>
              <td class="table__secondary"><?= e($o['payment_methods'] ?? '—') ?></td>
              <td class="table__secondary"><?= e($o['waiter_name'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="3" class="text-end">Total</th>
            <th class="text-end"><?= e(money($total)) ?></th>
            <th colspan="3"></th>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout/app_end.php'; ?>
