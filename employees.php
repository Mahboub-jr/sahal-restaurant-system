<?php
/**
 * Employees -- full CRUD.
 *
 * This page only READS and RENDERS. Every write goes to
 * actions/employees.php.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager');

$title    = 'Staff';
$subtitle = 'Employee records';

$search   = query('name');
$position = query('position');
$statusF  = one_of(query('status'), ['Active', 'Inactive'], '');
$joinFrom = query('join_from');
$joinTo   = query('join_to');

$where  = [];
$params = [];
if ($search !== '') {
    $where[]  = 'name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($position !== '') {
    $where[]  = 'position LIKE ?';
    $params[] = '%' . $position . '%';
}
if ($statusF !== '') {
    $where[]  = 'status = ?';
    $params[] = $statusF;
}
if ($joinFrom !== '') {
    $where[]  = 'join_date >= ?';
    $params[] = $joinFrom;
}
if ($joinTo !== '') {
    $where[]  = 'join_date <= ?';
    $params[] = $joinTo;
}
$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$employees = db_all("SELECT * FROM employees $whereSql ORDER BY id DESC", $params);

include __DIR__ . '/includes/layout/app_start.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Staff</h1>
    <p class="page-head__sub"><?= count($employees) ?> employee<?= count($employees) === 1 ? '' : 's' ?></p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="<?= url('attendance.php') ?>">
      <i class="bi bi-calendar-check"></i> Attendance
    </a>
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#employeeModal">
      <i class="bi bi-plus-lg"></i> Add employee
    </button>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label" for="name">Name</label>
        <input class="form-control" type="text" id="name" name="name" value="<?= e($search) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="position">Position</label>
        <input class="form-control" type="text" id="position" name="position" value="<?= e($position) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="status">Status</label>
        <select class="form-select" id="status" name="status">
          <option value="">Any</option>
          <option value="Active" <?= $statusF === 'Active' ? 'selected' : '' ?>>Active</option>
          <option value="Inactive" <?= $statusF === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="join_from">Joined from</label>
        <input class="form-control" type="date" id="join_from" name="join_from" value="<?= e($joinFrom) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="join_to">to</label>
        <input class="form-control" type="date" id="join_to" name="join_to" value="<?= e($joinTo) ?>">
      </div>
      <div class="col-12">
        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button>
        <?php if ($search !== '' || $position !== '' || $statusF !== '' || $joinFrom !== '' || $joinTo !== ''): ?>
          <a class="btn btn-ghost btn-sm" href="<?= url('employees.php') ?>"><i class="bi bi-x-lg"></i> Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <?php if ($employees === []): ?>
    <div class="card-body">
      <div class="empty">
        <div class="empty__icon"><i class="bi bi-person-badge"></i></div>
        <div class="empty__title">No employees match</div>
        <p class="empty__text">Add the first one, or try a different filter.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Position</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Joined</th>
            <th>Status</th>
            <th style="width:100px" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($employees as $emp): ?>
            <tr>
              <td class="table__primary"><?= e($emp['name']) ?></td>
              <td class="table__secondary"><?= e($emp['position'] ?? '—') ?></td>
              <td class="table__secondary"><?= e($emp['phone'] ?? '—') ?></td>
              <td class="table__secondary"><?= e($emp['email'] ?? '—') ?></td>
              <td class="table__secondary"><?= e(date('j M Y', strtotime((string) $emp['join_date']))) ?></td>
              <td><span class="badge-soft badge-soft--<?= $emp['status'] === 'Active' ? 'ok' : 'neutral' ?>"><?= e($emp['status']) ?></span></td>
              <td>
                <div class="table__actions justify-content-end">
                  <button class="btn btn-ghost btn-icon btn-sm js-edit" type="button" title="Edit"
                          data-employee='<?= e(json_encode([
                              'id'       => (int) $emp['id'],
                              'name'     => $emp['name'],
                              'position' => $emp['position'],
                              'phone'    => $emp['phone'],
                              'email'    => $emp['email'],
                              'status'   => $emp['status'],
                          ], JSON_UNESCAPED_UNICODE)) ?>'>
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="post" action="<?= url('actions/employees.php') ?>" class="m-0"
                        data-confirm="Delete “<?= e($emp['name']) ?>”?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $emp['id'] ?>">
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

<!-- ============ Add / edit modal ============ -->
<div class="modal fade" id="employeeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?= url('actions/employees.php') ?>" id="employeeForm">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="create" id="formAction">
        <input type="hidden" name="id" value="" id="formId">

        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Add employee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-8">
              <label class="form-label" for="f_name">Name <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="text" id="f_name" name="name" maxlength="100" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="f_status">Status</label>
              <select class="form-select" id="f_status" name="status">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_position">Position <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="text" id="f_position" name="position" maxlength="100" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_phone">Phone</label>
              <input class="form-control" type="text" id="f_phone" name="phone" maxlength="20">
            </div>
            <div class="col-12">
              <label class="form-label" for="f_email">Email</label>
              <input class="form-control" type="email" id="f_email" name="email" maxlength="100">
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="submitBtn">
            <i class="bi bi-check-lg"></i> Save employee
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<'JS'
(function () {
  var modalEl  = document.getElementById('employeeModal');
  var form     = document.getElementById('employeeForm');
  var titleEl  = document.getElementById('modalTitle');
  var actionEl = document.getElementById('formAction');
  var idEl     = document.getElementById('formId');

  function resetToCreate() {
    form.reset();
    actionEl.value = 'create';
    idEl.value = '';
    titleEl.textContent = 'Add employee';
  }

  document.querySelectorAll('[data-bs-target="#employeeModal"]').forEach(function (btn) {
    btn.addEventListener('click', resetToCreate);
  });

  document.querySelectorAll('.js-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var emp;
      try { emp = JSON.parse(btn.getAttribute('data-employee')); } catch (e) { return; }

      actionEl.value = 'update';
      idEl.value = emp.id;
      titleEl.textContent = 'Edit “' + emp.name + '”';

      form.querySelector('#f_name').value = emp.name || '';
      form.querySelector('#f_position').value = emp.position || '';
      form.querySelector('#f_phone').value = emp.phone || '';
      form.querySelector('#f_email').value = emp.email || '';
      form.querySelector('#f_status').value = emp.status || 'Active';

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
