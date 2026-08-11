<?php
/**
 * Customers -- full CRUD.
 *
 * This page only READS and RENDERS. Every write goes to
 * actions/customers.php.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager', 'cashier');

$title    = 'Customers';
$subtitle = 'Optional records payments and reservations can link to';

$search = query('q');
$where  = [];
$params = [];
if ($search !== '') {
    $where[]  = '(name LIKE ? OR phone LIKE ? OR email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$customers = db_all("SELECT * FROM customers $whereSql ORDER BY created_at DESC", $params);

include __DIR__ . '/includes/layout/app_start.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Customers</h1>
    <p class="page-head__sub"><?= count($customers) ?> customer<?= count($customers) === 1 ? '' : 's' ?></p>
  </div>
  <div class="page-head__actions">
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#customerModal">
      <i class="bi bi-plus-lg"></i> Add customer
    </button>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label" for="q">Search</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input class="form-control" type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Name, phone or email">
        </div>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100" type="submit">Filter</button>
      </div>
      <?php if ($search !== ''): ?>
        <div class="col-12">
          <a class="btn btn-ghost btn-sm" href="<?= url('customers.php') ?>"><i class="bi bi-x-lg"></i> Clear</a>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <?php if ($customers === []): ?>
    <div class="card-body">
      <div class="empty">
        <div class="empty__icon"><i class="bi bi-people"></i></div>
        <div class="empty__title"><?= $search !== '' ? 'Nothing matches that search' : 'No customers yet' ?></div>
        <p class="empty__text">
          <?= $search !== '' ? 'Try a broader search, or clear it.' : 'Customer records are optional -- orders and reservations already capture a name and phone number on their own.' ?>
        </p>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Address</th>
            <th>Added</th>
            <th style="width:100px" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $c): ?>
            <tr>
              <td class="table__primary"><?= e($c['name']) ?></td>
              <td class="table__secondary"><?= e($c['phone'] ?? '—') ?></td>
              <td class="table__secondary"><?= e($c['email'] ?? '—') ?></td>
              <td class="table__secondary truncate" style="max-width:220px"><?= e($c['address'] ?? '—') ?></td>
              <td class="table__secondary"><?= e(date('j M Y', strtotime((string) $c['created_at']))) ?></td>
              <td>
                <div class="table__actions justify-content-end">
                  <button class="btn btn-ghost btn-icon btn-sm js-edit" type="button" title="Edit"
                          data-customer='<?= e(json_encode([
                              'id'      => (int) $c['id'],
                              'name'    => $c['name'],
                              'phone'   => $c['phone'],
                              'email'   => $c['email'],
                              'address' => $c['address'],
                          ], JSON_UNESCAPED_UNICODE)) ?>'>
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="post" action="<?= url('actions/customers.php') ?>" class="m-0"
                        data-confirm="Delete “<?= e($c['name']) ?>”?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
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
<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?= url('actions/customers.php') ?>" id="customerForm">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="create" id="formAction">
        <input type="hidden" name="id" value="" id="formId">

        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Add customer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-8">
              <label class="form-label" for="f_name">Full name <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="text" id="f_name" name="name" maxlength="100" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="f_phone">Phone</label>
              <input class="form-control" type="text" id="f_phone" name="phone" maxlength="20">
            </div>
            <div class="col-12">
              <label class="form-label" for="f_email">Email</label>
              <input class="form-control" type="email" id="f_email" name="email" maxlength="100">
            </div>
            <div class="col-12">
              <label class="form-label" for="f_address">Address</label>
              <textarea class="form-control" id="f_address" name="address" rows="2"></textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="submitBtn">
            <i class="bi bi-check-lg"></i> Save customer
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<'JS'
(function () {
  var modalEl  = document.getElementById('customerModal');
  var form     = document.getElementById('customerForm');
  var titleEl  = document.getElementById('modalTitle');
  var actionEl = document.getElementById('formAction');
  var idEl     = document.getElementById('formId');

  function resetToCreate() {
    form.reset();
    actionEl.value = 'create';
    idEl.value = '';
    titleEl.textContent = 'Add customer';
  }

  document.querySelectorAll('[data-bs-target="#customerModal"]').forEach(function (btn) {
    btn.addEventListener('click', resetToCreate);
  });

  document.querySelectorAll('.js-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var c;
      try { c = JSON.parse(btn.getAttribute('data-customer')); } catch (e) { return; }

      actionEl.value = 'update';
      idEl.value = c.id;
      titleEl.textContent = 'Edit “' + c.name + '”';

      form.querySelector('#f_name').value = c.name || '';
      form.querySelector('#f_phone').value = c.phone || '';
      form.querySelector('#f_email').value = c.email || '';
      form.querySelector('#f_address').value = c.address || '';

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
