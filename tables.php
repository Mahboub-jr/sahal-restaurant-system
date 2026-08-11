<?php
/**
 * Tables -- full CRUD.
 *
 * This page only READS and RENDERS. Every write goes to
 * actions/tables.php. Status here is a manual override; orders and
 * reservations normally move it themselves as part of their own lifecycle.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager', 'waiter');

$title    = 'Tables';
$subtitle = 'Seating and live status';

$tables = db_all('SELECT * FROM tables ORDER BY table_number');

$counts = ['Available' => 0, 'Reserved' => 0, 'Occupied' => 0];
foreach ($tables as $t) {
    if (isset($counts[$t['status']])) {
        $counts[$t['status']]++;
    }
}

include __DIR__ . '/includes/layout/app_start.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Tables</h1>
    <p class="page-head__sub">
      <?= count($tables) ?> table<?= count($tables) === 1 ? '' : 's' ?>
      · <?= $counts['Available'] ?> available · <?= $counts['Reserved'] ?> reserved · <?= $counts['Occupied'] ?> occupied
    </p>
  </div>
  <?php if (has_role('admin', 'manager')): ?>
    <div class="page-head__actions">
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#tableModal">
        <i class="bi bi-plus-lg"></i> Add table
      </button>
    </div>
  <?php endif; ?>
</div>

<?php if ($tables === []): ?>
  <div class="card">
    <div class="card-body">
      <div class="empty">
        <div class="empty__icon"><i class="bi bi-grid-3x3"></i></div>
        <div class="empty__title">No tables yet</div>
        <p class="empty__text">Add your first table to start assigning Dine-In orders and reservations to it.</p>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($tables as $t): ?>
      <?php
      $colour = ['Available' => 'ok', 'Reserved' => 'warn', 'Occupied' => 'bad'][$t['status']] ?? 'neutral';
      ?>
      <div class="col-6 col-md-4 col-xl-3">
        <div class="card h-100">
          <div class="card-body text-center">
            <div class="fw-semi" style="font-size:1.1rem"><?= e($t['table_number']) ?></div>
            <div class="table__secondary mb-2"><i class="bi bi-people"></i> Seats <?= (int) $t['capacity'] ?></div>
            <span class="badge-soft badge-soft--<?= $colour ?>"><?= e($t['status']) ?></span>
            <?php if (has_role('admin', 'manager')): ?>
              <div class="table__actions justify-content-center mt-2">
                <button class="btn btn-ghost btn-icon btn-sm js-edit" type="button" title="Edit"
                        data-table='<?= e(json_encode([
                            'id'           => (int) $t['id'],
                            'table_number' => $t['table_number'],
                            'capacity'     => (int) $t['capacity'],
                            'status'       => $t['status'],
                        ], JSON_UNESCAPED_UNICODE)) ?>'>
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="post" action="<?= url('actions/tables.php') ?>" class="m-0"
                      data-confirm="Delete table “<?= e($t['table_number']) ?>”?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="do" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                  <button class="btn btn-ghost btn-icon btn-sm" type="submit" title="Delete" style="color:var(--bad)">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ============ Add / edit modal ============ -->
<div class="modal fade" id="tableModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?= url('actions/tables.php') ?>" id="tableForm">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="create" id="formAction">
        <input type="hidden" name="id" value="" id="formId">

        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Add table</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label" for="f_number">Table number <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="text" id="f_number" name="table_number" maxlength="50" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_capacity">Capacity <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="number" id="f_capacity" name="capacity" min="1" max="100" required>
            </div>
            <div class="col-12">
              <label class="form-label" for="f_status">Status</label>
              <select class="form-select" id="f_status" name="status">
                <option value="Available">Available</option>
                <option value="Reserved">Reserved</option>
                <option value="Occupied">Occupied</option>
              </select>
              <div class="form-hint">Orders and reservations normally set this on their own -- change it by hand only to correct a mistake.</div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="submitBtn">
            <i class="bi bi-check-lg"></i> Save table
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<'JS'
(function () {
  var modalEl  = document.getElementById('tableModal');
  var form     = document.getElementById('tableForm');
  var titleEl  = document.getElementById('modalTitle');
  var actionEl = document.getElementById('formAction');
  var idEl     = document.getElementById('formId');

  function resetToCreate() {
    form.reset();
    actionEl.value = 'create';
    idEl.value = '';
    titleEl.textContent = 'Add table';
  }

  document.querySelectorAll('[data-bs-target="#tableModal"]').forEach(function (btn) {
    btn.addEventListener('click', resetToCreate);
  });

  document.querySelectorAll('.js-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var t;
      try { t = JSON.parse(btn.getAttribute('data-table')); } catch (e) { return; }

      actionEl.value = 'update';
      idEl.value = t.id;
      titleEl.textContent = 'Edit “' + t.table_number + '”';

      form.querySelector('#f_number').value = t.table_number || '';
      form.querySelector('#f_capacity').value = t.capacity || '';
      form.querySelector('#f_status').value = t.status || 'Available';

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
