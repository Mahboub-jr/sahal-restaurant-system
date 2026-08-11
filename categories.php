<?php
/**
 * Menu categories -- full CRUD.
 *
 * This page only READS and RENDERS. Every write goes to
 * actions/categories.php.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager');

$title    = 'Categories';
$subtitle = 'How the menu is organised';

$categories = db_all(
    'SELECT c.*, COUNT(m.id) AS item_count
       FROM categories c
       LEFT JOIN menu_items m ON m.category_id = c.id
      GROUP BY c.id
      ORDER BY c.name'
);

include __DIR__ . '/includes/layout/app_start.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Categories</h1>
    <p class="page-head__sub"><?= count($categories) ?> categor<?= count($categories) === 1 ? 'y' : 'ies' ?></p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="<?= url('menu.php') ?>">
      <i class="bi bi-egg-fried"></i> Menu items
    </a>
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#categoryModal">
      <i class="bi bi-plus-lg"></i> Add category
    </button>
  </div>
</div>

<div class="card">
  <?php if ($categories === []): ?>
    <div class="card-body">
      <div class="empty">
        <div class="empty__icon"><i class="bi bi-tags"></i></div>
        <div class="empty__title">No categories yet</div>
        <p class="empty__text">Add the first one and it will show up when adding a menu item.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Description</th>
            <th class="text-center">Items</th>
            <th style="width:100px" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $c): ?>
            <tr>
              <td class="table__primary"><?= e($c['name']) ?></td>
              <td class="table__secondary"><?= e($c['description'] ?? '—') ?></td>
              <td class="text-center"><?= (int) $c['item_count'] ?></td>
              <td>
                <div class="table__actions justify-content-end">
                  <button class="btn btn-ghost btn-icon btn-sm js-edit" type="button" title="Edit"
                          data-category='<?= e(json_encode([
                              'id'          => (int) $c['id'],
                              'name'        => $c['name'],
                              'description' => $c['description'],
                          ], JSON_UNESCAPED_UNICODE)) ?>'>
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="post" action="<?= url('actions/categories.php') ?>" class="m-0"
                        data-confirm="Delete “<?= e($c['name']) ?>”?<?= $c['item_count'] > 0 ? ' It still has ' . (int) $c['item_count'] . ' menu item(s) on it, so this will be refused.' : '' ?>">
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
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?= url('actions/categories.php') ?>" id="categoryForm">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="create" id="formAction">
        <input type="hidden" name="id" value="" id="formId">

        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Add category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label" for="f_name">Name <span style="color:var(--bad)">*</span></label>
            <input class="form-control" type="text" id="f_name" name="name" maxlength="100" required>
          </div>
          <div class="mb-0">
            <label class="form-label" for="f_description">Description</label>
            <textarea class="form-control" id="f_description" name="description" rows="3" maxlength="1000"></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="submitBtn">
            <i class="bi bi-check-lg"></i> Save category
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<'JS'
(function () {
  var modalEl  = document.getElementById('categoryModal');
  var form     = document.getElementById('categoryForm');
  var titleEl  = document.getElementById('modalTitle');
  var actionEl = document.getElementById('formAction');
  var idEl     = document.getElementById('formId');

  function resetToCreate() {
    form.reset();
    actionEl.value = 'create';
    idEl.value = '';
    titleEl.textContent = 'Add category';
  }

  document.querySelectorAll('[data-bs-target="#categoryModal"]').forEach(function (btn) {
    btn.addEventListener('click', resetToCreate);
  });

  document.querySelectorAll('.js-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var c;
      try { c = JSON.parse(btn.getAttribute('data-category')); } catch (e) { return; }

      actionEl.value = 'update';
      idEl.value = c.id;
      titleEl.textContent = 'Edit “' + c.name + '”';

      form.querySelector('#f_name').value = c.name || '';
      form.querySelector('#f_description').value = c.description || '';

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
