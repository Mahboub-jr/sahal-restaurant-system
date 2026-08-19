<?php
/**
 * Reservations.
 *
 * Replaces table_booking.php. This page only READS and RENDERS; every
 * write goes through actions/reservations.php, which also keeps a
 * reservation's table in sync (Available <-> Reserved <-> Occupied) the
 * same way actions/orders.php does for Dine-In orders.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager', 'waiter', 'cashier');

$title    = 'Reservations';
$subtitle = 'Bookings for a table, ahead of time';

$schemaReady = db_value(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'reservations'",
    [DB_NAME]
) !== null;

include __DIR__ . '/includes/layout/app_start.php';

if (!$schemaReady): ?>
  <div class="page-head"><h1 class="page-head__title">Reservations</h1></div>
  <div class="alert alert-warning">
    <i class="bi bi-database-exclamation"></i>
    <div>
      <strong>Migration 007 has not been run yet.</strong>
      Apply <code>sql/migrations/007_reservations_and_inventory.sql</code> in phpMyAdmin
      to use reservations.
    </div>
  </div>
<?php
  include __DIR__ . '/includes/layout/app_end.php';
  exit;
endif;

$STATUSES = ['Pending', 'Confirmed', 'Seated', 'Completed', 'Cancelled', 'No-show'];

$search   = query('customer');
$statusF  = one_of(query('status'), $STATUSES, '');
$fromDate = query('from_date');
$toDate   = query('to_date');

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = 'r.customer_name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($statusF !== '') {
    $where[]  = 'r.status = ?';
    $params[] = $statusF;
} else {
    $where[] = "r.status NOT IN ('Completed','Cancelled','No-show')";
}
if ($fromDate !== '') {
    $where[]  = 'DATE(r.reserved_at) >= ?';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $where[]  = 'DATE(r.reserved_at) <= ?';
    $params[] = $toDate;
}

$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$reservations = db_all(
    "SELECT r.*, t.table_number, u.name AS taken_by
       FROM reservations r
       LEFT JOIN tables t ON t.id = r.table_id
       LEFT JOIN users  u ON u.id = r.user_id
       $whereSql
      ORDER BY r.reserved_at ASC",
    $params
);

$tables = db_all('SELECT id, table_number, capacity, status FROM tables ORDER BY table_number');

$allowedNext = reservation_status_transitions();
$statusColour = [
    'Pending'   => 'neutral',
    'Confirmed' => 'info',
    'Seated'    => 'warn',
    'Completed' => 'ok',
    'Cancelled' => 'bad',
    'No-show'   => 'bad',
];
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Reservations</h1>
    <p class="page-head__sub"><?= count($reservations) ?> shown</p>
  </div>
  <div class="page-head__actions">
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#reservationModal">
      <i class="bi bi-plus-lg"></i> New reservation
    </button>
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
      <div class="col-md-3">
        <label class="form-label" for="status">Status</label>
        <select class="form-select" id="status" name="status">
          <option value="">Upcoming (default)</option>
          <?php foreach ($STATUSES as $s): ?>
            <option value="<?= e($s) ?>" <?= $statusF === $s ? 'selected' : '' ?>><?= e($s) ?></option>
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
      <div class="col-md-2">
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i></button>
      </div>
      <?php if ($search !== '' || $statusF !== '' || $fromDate !== '' || $toDate !== ''): ?>
        <div class="col-12">
          <a class="btn btn-ghost btn-sm" href="<?= url('reservations.php') ?>"><i class="bi bi-x-lg"></i> Clear filters</a>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <?php if ($reservations === []): ?>
    <div class="card-body">
      <div class="empty">
        <div class="empty__icon"><i class="bi bi-journal-bookmark"></i></div>
        <div class="empty__title">Nothing here</div>
        <p class="empty__text">Try a different filter, or take a new reservation.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>When</th>
            <th>Customer</th>
            <th class="text-center">Party</th>
            <th>Table</th>
            <th>Status</th>
            <th>Taken by</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reservations as $r): ?>
            <?php $next = $allowedNext[$r['status']] ?? []; ?>
            <tr>
              <td>
                <div class="table__primary"><?= e(date('j M Y', strtotime((string) $r['reserved_at']))) ?></div>
                <div class="table__secondary"><?= e(date('H:i', strtotime((string) $r['reserved_at']))) ?></div>
              </td>
              <td>
                <div class="table__primary"><?= e($r['customer_name']) ?></div>
                <?php if ($r['phone']): ?><div class="table__secondary"><?= e($r['phone']) ?></div><?php endif; ?>
              </td>
              <td class="text-center"><?= $r['party_size'] !== null ? (int) $r['party_size'] : '—' ?></td>
              <td><?= $r['table_number'] ? e($r['table_number']) : '<span class="table__secondary">Unassigned</span>' ?></td>
              <td><span class="badge-soft badge-soft--<?= $statusColour[$r['status']] ?? 'neutral' ?>"><?= e($r['status']) ?></span></td>
              <td class="table__secondary"><?= e($r['taken_by'] ?? '—') ?></td>
              <td class="text-end">
                <div class="table__actions justify-content-end">
                  <?php foreach ($next as $nextStatus): ?>
                    <form method="post" action="<?= url('actions/reservations.php') ?>" class="m-0"
                          <?= in_array($nextStatus, ['Cancelled', 'No-show'], true)
                              ? 'data-confirm="Mark ' . e($r['customer_name']) . '’s reservation as ' . e($nextStatus) . '?"'
                              : '' ?>>
                      <?= csrf_field() ?>
                      <input type="hidden" name="do" value="set_status">
                      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                      <input type="hidden" name="status" value="<?= e($nextStatus) ?>">
                      <button type="submit" class="btn btn-ghost btn-sm"><?= e($nextStatus) ?></button>
                    </form>
                  <?php endforeach; ?>

                  <?php if (!in_array($r['status'], ['Completed', 'Cancelled', 'No-show'], true)): ?>
                    <button class="btn btn-ghost btn-icon btn-sm js-edit" type="button" title="Edit"
                            data-reservation='<?= e(json_encode([
                                'id'            => (int) $r['id'],
                                'customer_name' => $r['customer_name'],
                                'phone'         => $r['phone'],
                                'customer_id'   => $r['customer_id'] !== null ? (int) $r['customer_id'] : '',
                                'party_size'    => $r['party_size'],
                                'table_id'      => $r['table_id'] !== null ? (int) $r['table_id'] : '',
                                'reserved_at'   => date('Y-m-d\TH:i', strtotime((string) $r['reserved_at'])),
                                'notes'         => $r['notes'],
                            ], JSON_UNESCAPED_UNICODE)) ?>'>
                      <i class="bi bi-pencil"></i>
                    </button>
                  <?php endif; ?>

                  <a class="btn btn-ghost btn-icon btn-sm" href="<?= url('reservation_slip.php?id=' . (int) $r['id']) ?>" title="Print slip">
                    <i class="bi bi-receipt"></i>
                  </a>

                  <?php if (has_role('admin', 'manager')): ?>
                    <form method="post" action="<?= url('actions/reservations.php') ?>" class="m-0"
                          data-confirm="Delete this reservation? This cannot be undone.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="do" value="delete">
                      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                      <button class="btn btn-ghost btn-icon btn-sm" type="submit" title="Delete" style="color:var(--bad)">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
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

<!-- ============ New / edit reservation modal ============ -->
<div class="modal fade" id="reservationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?= url('actions/reservations.php') ?>" id="reservationForm">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="create" id="formAction">
        <input type="hidden" name="id" value="" id="formId">

        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">New reservation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-8">
              <label class="form-label" for="f_customer_name">Customer name <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="text" id="f_customer_name" name="customer_name" maxlength="100" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="f_party_size">Party size <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="number" id="f_party_size" name="party_size" min="1" max="100" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_phone">Phone</label>
              <input class="form-control" type="text" id="f_phone" name="phone" maxlength="20">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_reserved_at">Date &amp; time <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="datetime-local" id="f_reserved_at" name="reserved_at" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_table">Table</label>
              <select class="form-select" id="f_table" name="table_id">
                <option value="">Unassigned for now</option>
                <?php foreach ($tables as $t): ?>
                  <option value="<?= (int) $t['id'] ?>" data-status="<?= e($t['status']) ?>">
                    <?= e($t['table_number']) ?> · seats <?= (int) $t['capacity'] ?>
                    <?= $t['status'] !== 'Available' ? '(' . e($t['status']) . ')' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6" id="statusField">
              <label class="form-label" for="f_status">Status</label>
              <select class="form-select" id="f_status" name="status">
                <option value="Confirmed">Confirmed</option>
                <option value="Pending">Pending</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="f_notes">Notes</label>
              <textarea class="form-control" id="f_notes" name="notes" rows="2" maxlength="255"
                        placeholder="Allergies, occasion, seating preference…"></textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="submitBtn">
            <i class="bi bi-check-lg"></i> Save reservation
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<'JS'
(function () {
  var modalEl    = document.getElementById('reservationModal');
  var form       = document.getElementById('reservationForm');
  var titleEl    = document.getElementById('modalTitle');
  var actionEl   = document.getElementById('formAction');
  var idEl       = document.getElementById('formId');
  var statusField = document.getElementById('statusField');

  function nowLocal() {
    var d = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
    return d.toISOString().slice(0, 16);
  }

  function resetToCreate() {
    form.reset();
    actionEl.value = 'create';
    idEl.value = '';
    titleEl.textContent = 'New reservation';
    statusField.classList.remove('d-none');
    document.getElementById('f_reserved_at').value = nowLocal();
  }

  document.querySelectorAll('[data-bs-target="#reservationModal"]').forEach(function (btn) {
    btn.addEventListener('click', resetToCreate);
  });

  document.querySelectorAll('.js-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var r;
      try { r = JSON.parse(btn.getAttribute('data-reservation')); } catch (e) { return; }

      actionEl.value = 'update';
      idEl.value = r.id;
      titleEl.textContent = 'Edit reservation';
      statusField.classList.add('d-none');

      form.querySelector('#f_customer_name').value = r.customer_name || '';
      form.querySelector('#f_phone').value = r.phone || '';
      form.querySelector('#f_party_size').value = r.party_size || '';
      form.querySelector('#f_table').value = r.table_id || '';
      form.querySelector('#f_reserved_at').value = r.reserved_at || '';
      form.querySelector('#f_notes').value = r.notes || '';

      new bootstrap.Modal(modalEl).show();
    });
  });

  modalEl.addEventListener('hidden.bs.modal', function () {
    var btn = document.getElementById('submitBtn');
    btn.disabled = false;
    btn.style.opacity = '';
  });
})();
JS;

include __DIR__ . '/includes/layout/app_end.php';
