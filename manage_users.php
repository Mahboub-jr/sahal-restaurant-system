<?php
/**
 * Users -- full CRUD, plus the role filter and CSV export user_roles.php
 * used to provide as a separate page. Merged here since "view users
 * filtered by role" and "manage users" were always the same list.
 *
 * This page only READS and RENDERS. Every write goes to actions/users.php.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin');

$title    = 'Users';
$subtitle = 'Accounts and roles';

$roleF = one_of(query('role'), array_keys(ROLES), '');

$where  = [];
$params = [];
if ($roleF !== '') {
    $where[]  = 'role = ?';
    $params[] = $roleF;
}
$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$users = db_all("SELECT id, name, email, role FROM users $whereSql ORDER BY id DESC", $params);

include __DIR__ . '/includes/layout/app_start.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Users</h1>
    <p class="page-head__sub"><?= count($users) ?> user<?= count($users) === 1 ? '' : 's' ?></p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="<?= url('export_user_roles.php' . ($roleF !== '' ? '?role=' . urlencode($roleF) : '')) ?>">
      <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
    </a>
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#userModal">
      <i class="bi bi-plus-lg"></i> Add user
    </button>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label" for="role">Role</label>
        <select class="form-select" id="role" name="role" onchange="this.form.submit()">
          <option value="">All roles</option>
          <?php foreach (ROLES as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $roleF === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($roleF !== ''): ?>
        <div class="col-md-2">
          <a class="btn btn-ghost btn-sm" href="<?= url('manage_users.php') ?>"><i class="bi bi-x-lg"></i> Clear</a>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <?php if ($users === []): ?>
    <div class="card-body">
      <div class="empty">
        <div class="empty__icon"><i class="bi bi-people"></i></div>
        <div class="empty__title">No users match</div>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th style="width:100px" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td class="table__primary">
                <?= e($u['name']) ?>
                <?php if ((int) $u['id'] === user_id()): ?><span class="badge-soft badge-soft--info">You</span><?php endif; ?>
              </td>
              <td class="table__secondary"><?= e($u['email']) ?></td>
              <td><span class="badge-soft badge-soft--<?= $u['role'] === 'admin' ? 'brand' : 'neutral' ?>"><?= e(ROLES[$u['role']] ?? $u['role']) ?></span></td>
              <td>
                <div class="table__actions justify-content-end">
                  <button class="btn btn-ghost btn-icon btn-sm js-edit" type="button" title="Edit"
                          data-user='<?= e(json_encode([
                              'id'    => (int) $u['id'],
                              'name'  => $u['name'],
                              'email' => $u['email'],
                              'role'  => $u['role'],
                          ], JSON_UNESCAPED_UNICODE)) ?>'>
                    <i class="bi bi-pencil"></i>
                  </button>
                  <?php if ((int) $u['id'] !== user_id()): ?>
                    <form method="post" action="<?= url('actions/users.php') ?>" class="m-0"
                          data-confirm="Delete “<?= e($u['name']) ?>”?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="do" value="delete">
                      <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
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

<!-- ============ Add / edit modal ============ -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?= url('actions/users.php') ?>" id="userForm">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="create" id="formAction">
        <input type="hidden" name="id" value="" id="formId">

        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Add user</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label" for="f_name">Name <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="text" id="f_name" name="name" maxlength="100" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_role">Role <span style="color:var(--bad)">*</span></label>
              <select class="form-select" id="f_role" name="role" required>
                <?php foreach (ROLES as $key => $label): ?>
                  <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="f_email">Email <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="email" id="f_email" name="email" maxlength="100" required>
            </div>
            <div class="col-12">
              <label class="form-label" for="f_password" id="passwordLabel">Password <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="password" id="f_password" name="password" minlength="8" autocomplete="new-password">
              <div class="form-hint" id="passwordHint">At least 8 characters.</div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="submitBtn">
            <i class="bi bi-check-lg"></i> Save user
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<'JS'
(function () {
  var modalEl   = document.getElementById('userModal');
  var form      = document.getElementById('userForm');
  var titleEl   = document.getElementById('modalTitle');
  var actionEl  = document.getElementById('formAction');
  var idEl      = document.getElementById('formId');
  var pwField   = document.getElementById('f_password');
  var pwLabel   = document.getElementById('passwordLabel');
  var pwHint    = document.getElementById('passwordHint');

  function resetToCreate() {
    form.reset();
    actionEl.value = 'create';
    idEl.value = '';
    titleEl.textContent = 'Add user';
    pwField.required = true;
    pwLabel.innerHTML = 'Password <span style="color:var(--bad)">*</span>';
    pwHint.textContent = 'At least 8 characters.';
  }

  document.querySelectorAll('[data-bs-target="#userModal"]').forEach(function (btn) {
    btn.addEventListener('click', resetToCreate);
  });

  document.querySelectorAll('.js-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var u;
      try { u = JSON.parse(btn.getAttribute('data-user')); } catch (e) { return; }

      actionEl.value = 'update';
      idEl.value = u.id;
      titleEl.textContent = 'Edit “' + u.name + '”';
      pwField.required = false;
      pwField.value = '';
      pwLabel.textContent = 'New password';
      pwHint.textContent = 'Leave blank to keep the current password.';

      form.querySelector('#f_name').value = u.name || '';
      form.querySelector('#f_email').value = u.email || '';
      form.querySelector('#f_role').value = u.role || '';

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
