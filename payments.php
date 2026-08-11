<?php
/**
 * Payments -- record and review, one order at a time.
 *
 * This page only READS and RENDERS. actions/payments.php does every write,
 * including the duplicate-payment guard that stops a second payment being
 * recorded once an order's balance reaches zero (AUDIT-ADDENDUM.md BUG-5).
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager', 'cashier');

$title    = 'Payments';
$subtitle = 'Record and review payments against orders';

$focusOrderId = query_int('order_id');

$PAYMENT_METHODS = ['Cash', 'Card', 'Mobile Money'];
$PAYMENT_STATUSES = ['Paid', 'Pending'];

/* --- Orders available to pay against --------------------------------- */
$payableOrders = db_all(
    "SELECT o.id, o.order_number, o.customer_name, o.total_amount,
            COALESCE((SELECT SUM(amount) FROM payments WHERE order_id = o.id AND status = 'Paid'), 0) AS paid_sum
       FROM orders o
      WHERE o.status <> 'Cancelled'
      ORDER BY o.created_at DESC"
);

$customers = db_all('SELECT id, name FROM customers ORDER BY name');

/* --- Filters ----------------------------------------------------------- */
$search  = query('customer');
$methodF = one_of(query('method'), $PAYMENT_METHODS, '');
$statusF = one_of(query('status'), $PAYMENT_STATUSES, '');

$where  = [];
$params = [];

if ($focusOrderId > 0) {
    $where[]  = 'p.order_id = ?';
    $params[] = $focusOrderId;
}
if ($search !== '') {
    $where[]  = '(o.customer_name LIKE ? OR c.name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($methodF !== '') {
    $where[]  = 'p.payment_method = ?';
    $params[] = $methodF;
}
if ($statusF !== '') {
    $where[]  = 'p.status = ?';
    $params[] = $statusF;
}

$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$payments = db_all(
    "SELECT p.*, o.order_number, o.customer_name AS order_customer_name, o.total_amount,
            c.name AS linked_customer_name,
            (SELECT COALESCE(SUM(amount), 0) FROM payments p2
              WHERE p2.order_id = p.order_id AND p2.status = 'Paid') AS order_paid_sum
       FROM payments p
       JOIN orders o ON o.id = p.order_id
       LEFT JOIN customers c ON c.id = p.customer_id
       $whereSql
      ORDER BY p.payment_date DESC",
    $params
);

include __DIR__ . '/includes/layout/app_start.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Payments</h1>
    <p class="page-head__sub"><?= count($payments) ?> payment<?= count($payments) === 1 ? '' : 's' ?> shown</p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="<?= url('orders.php') ?>">
      <i class="bi bi-receipt"></i> Orders
    </a>
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#paymentModal">
      <i class="bi bi-plus-lg"></i> Record payment
    </button>
  </div>
</div>

<?php if ($payableOrders === []): ?>
  <div class="alert alert-warning mb-3">
    <i class="bi bi-exclamation-triangle"></i>
    No orders to pay against yet. Place an order first.
  </div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label" for="customer">Customer</label>
        <input class="form-control" type="text" id="customer" name="customer" value="<?= e($search) ?>" placeholder="Name">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="method">Method</label>
        <select class="form-select" id="method" name="method">
          <option value="">Any</option>
          <?php foreach ($PAYMENT_METHODS as $m): ?>
            <option value="<?= e($m) ?>" <?= $methodF === $m ? 'selected' : '' ?>><?= e($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="status">Status</label>
        <select class="form-select" id="status" name="status">
          <option value="">Any</option>
          <?php foreach ($PAYMENT_STATUSES as $s): ?>
            <option value="<?= e($s) ?>" <?= $statusF === $s ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i></button>
      </div>
      <?php if ($focusOrderId > 0 || $search !== '' || $methodF !== '' || $statusF !== ''): ?>
        <div class="col-12">
          <a class="btn btn-ghost btn-sm" href="<?= url('payments.php') ?>"><i class="bi bi-x-lg"></i> Clear filters</a>
          <?php if ($focusOrderId > 0): ?>
            <span class="table__secondary">Showing only order <?= (int) $focusOrderId ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <?php if ($payments === []): ?>
    <div class="card-body">
      <div class="empty">
        <div class="empty__icon"><i class="bi bi-credit-card"></i></div>
        <div class="empty__title">No payments match</div>
        <p class="empty__text">Record one, or try a different filter.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Customer</th>
            <th class="text-end">Amount</th>
            <th>Method</th>
            <th>Status</th>
            <th>Date</th>
            <th class="text-end">Order balance</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <?php $balance = round((float) $p['total_amount'] - (float) $p['order_paid_sum'], 2); ?>
            <tr>
              <td><?= e($p['order_number'] ?? ('#' . $p['order_id'])) ?></td>
              <td><?= e($p['linked_customer_name'] ?? $p['order_customer_name']) ?></td>
              <td class="text-end fw-semi"><?= e(money($p['amount'])) ?></td>
              <td><?= e($p['payment_method']) ?></td>
              <td>
                <span class="badge-soft badge-soft--<?= $p['status'] === 'Paid' ? 'ok' : 'warn' ?>"><?= e($p['status']) ?></span>
              </td>
              <td class="table__secondary"><?= e(date('j M Y, H:i', strtotime((string) $p['payment_date']))) ?></td>
              <td class="text-end <?= $balance > 0 ? 'text-warning' : '' ?>"><?= e(money($balance)) ?></td>
              <td class="text-end">
                <div class="table__actions justify-content-end">
                  <button class="btn btn-ghost btn-icon btn-sm js-edit"
                          type="button" title="Edit"
                          data-payment='<?= e(json_encode([
                              'id'             => (int) $p['id'],
                              'order_id'       => (int) $p['order_id'],
                              'customer_id'    => $p['customer_id'] !== null ? (int) $p['customer_id'] : '',
                              'amount'         => $p['amount'],
                              'payment_method' => $p['payment_method'],
                              'status'         => $p['status'],
                              'payment_date'   => date('Y-m-d\TH:i', strtotime((string) $p['payment_date'])),
                          ], JSON_UNESCAPED_UNICODE)) ?>'>
                    <i class="bi bi-pencil"></i>
                  </button>
                  <a class="btn btn-ghost btn-icon btn-sm" href="<?= url('invoice.php?id=' . (int) $p['order_id']) ?>" title="Invoice">
                    <i class="bi bi-receipt"></i>
                  </a>
                  <form method="post" action="<?= url('actions/payments.php') ?>" class="m-0"
                        data-confirm="Delete this payment of <?= e(money($p['amount'])) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button class="btn btn-ghost btn-icon btn-sm" type="submit" title="Delete" style="color:var(--bad)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ============ Record / edit payment modal ============ -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?= url('actions/payments.php') ?>" id="paymentForm">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="create" id="formAction">
        <input type="hidden" name="id" value="" id="formId">

        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Record payment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label" for="f_order">Order <span style="color:var(--bad)">*</span></label>
            <select class="form-select" id="f_order" name="order_id" required>
              <option value="">Choose an order…</option>
              <?php foreach ($payableOrders as $o): ?>
                <?php $bal = round((float) $o['total_amount'] - (float) $o['paid_sum'], 2); ?>
                <option value="<?= (int) $o['id'] ?>" data-balance="<?= e(money($bal)) ?>"
                        <?= $focusOrderId === (int) $o['id'] ? 'selected' : '' ?>>
                  <?= e($o['order_number'] ?? ('#' . $o['id'])) ?> — <?= e($o['customer_name']) ?>
                  (balance <?= e(money($bal)) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-hint" id="balanceHint"></div>
          </div>

          <div class="mb-2">
            <label class="form-label" for="f_customer">Customer on file (optional)</label>
            <select class="form-select" id="f_customer" name="customer_id">
              <option value="">Not linked to a customer record</option>
              <?php foreach ($customers as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-hint">
              Orders capture the customer's name as free text; this only links to a
              record in <a href="<?= url('customers.php') ?>">Customers</a> if you want one.
            </div>
          </div>

          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label" for="f_amount">Amount (<?= e(setting('currency_symbol', '$')) ?>) <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="number" id="f_amount" name="amount" step="0.01" min="0.01" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_date">Date <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="datetime-local" id="f_date" name="payment_date" required>
            </div>
          </div>

          <div class="row g-2 mt-0">
            <div class="col-md-6">
              <label class="form-label" for="f_method">Method <span style="color:var(--bad)">*</span></label>
              <select class="form-select" id="f_method" name="payment_method" required>
                <option value="">Choose…</option>
                <?php foreach ($PAYMENT_METHODS as $m): ?>
                  <option value="<?= e($m) ?>"><?= e($m) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_status">Status</label>
              <select class="form-select" id="f_status" name="status">
                <option value="Paid">Paid</option>
                <option value="Pending">Pending</option>
              </select>
              <div class="form-hint">Only "Paid" counts toward the order's balance.</div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="submitBtn">
            <i class="bi bi-check-lg"></i> Save payment
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$focusOrderId_js = ejs($focusOrderId);
$inlineScript = <<<JS
(function () {
  var modalEl  = document.getElementById('paymentModal');
  var form     = document.getElementById('paymentForm');
  var titleEl  = document.getElementById('modalTitle');
  var actionEl = document.getElementById('formAction');
  var idEl     = document.getElementById('formId');
  var orderEl  = document.getElementById('f_order');
  var balanceHint = document.getElementById('balanceHint');
  var dateEl   = document.getElementById('f_date');

  function nowLocal() {
    var d = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
    return d.toISOString().slice(0, 16);
  }

  function updateBalanceHint() {
    var opt = orderEl.options[orderEl.selectedIndex];
    var bal = opt ? opt.getAttribute('data-balance') : null;
    balanceHint.textContent = bal ? ('Remaining balance: ' + bal) : '';
  }

  function resetToCreate() {
    form.reset();
    actionEl.value = 'create';
    idEl.value = '';
    titleEl.textContent = 'Record payment';
    dateEl.value = nowLocal();
    updateBalanceHint();
  }

  document.querySelectorAll('[data-bs-target="#paymentModal"]').forEach(function (btn) {
    btn.addEventListener('click', resetToCreate);
  });

  document.querySelectorAll('.js-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var p;
      try { p = JSON.parse(btn.getAttribute('data-payment')); } catch (e) { return; }

      actionEl.value = 'update';
      idEl.value = p.id;
      titleEl.textContent = 'Edit payment';

      orderEl.value = p.order_id;
      form.querySelector('#f_customer').value = p.customer_id || '';
      form.querySelector('#f_amount').value = p.amount;
      form.querySelector('#f_date').value = p.payment_date;
      form.querySelector('#f_method').value = p.payment_method;
      form.querySelector('#f_status').value = p.status;
      updateBalanceHint();

      new bootstrap.Modal(modalEl).show();
    });
  });

  orderEl.addEventListener('change', updateBalanceHint);

  modalEl.addEventListener('hidden.bs.modal', function () {
    var btn = document.getElementById('submitBtn');
    btn.disabled = false;
    btn.style.opacity = '';
  });

  updateBalanceHint();

  var focusOrderId = {$focusOrderId_js};
  if (focusOrderId) {
    dateEl.value = nowLocal();
    new bootstrap.Modal(modalEl).show();
  }
})();
JS;

include __DIR__ . '/includes/layout/app_end.php';
