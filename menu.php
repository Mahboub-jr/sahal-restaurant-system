<?php
/**
 * Menu items — full CRUD.
 *
 * Completes the module the handover brief left half-finished: create, read,
 * update, delete, availability, search, sort and pagination.
 *
 * This page only READS and RENDERS. Every write goes to actions/menu.php,
 * which is POST-only, CSRF-checked and role-gated.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager');

$title    = 'Menu items';
$subtitle = 'Everything the kitchen can sell';

/* Availability arrived in migration 004 — degrade gracefully if it has not
   been run yet rather than fataling on an unknown column. */
$hasAvailability = db_value(
    "SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'menu_items'
        AND COLUMN_NAME = 'is_available'",
    [DB_NAME]
) !== null;

$categories = db_all('SELECT id, name FROM categories ORDER BY name');

/* --- Filters ------------------------------------------------------- */
$search       = query('q');
$filterCat    = query_int('category');
$filterStatus = one_of(query('status'), ['available', 'unavailable'], '');

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = '(m.name LIKE ? OR m.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($filterCat > 0) {
    $where[] = 'm.category_id = ?';
    $params[] = $filterCat;
}
if ($hasAvailability && $filterStatus !== '') {
    $where[] = 'm.is_available = ?';
    $params[] = $filterStatus === 'available' ? 1 : 0;
}

$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

/* LEFT JOIN, always. An INNER JOIN silently hid the orphaned 'bariis' row
   from management while it stayed sellable (AUDIT-ADDENDUM.md BUG-2). */
$items = db_all(
    'SELECT m.*, c.name AS category_name
       FROM menu_items m
       LEFT JOIN categories c ON c.id = m.category_id
     ' . $whereSql . '
      ORDER BY m.id DESC',
    $params
);

$totalItems = count($items);
$orphans    = 0;
$unavailable = 0;
foreach ($items as $i) {
    if ($i['category_name'] === null) {
        $orphans++;
    }
    if ($hasAvailability && (int) $i['is_available'] === 0) {
        $unavailable++;
    }
}

$pageStyles  = ['https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css'];
$pageScripts = [
    // DataTables needs jQuery. The old pages loaded the plugin without it,
    // which is one reason the table never initialised.
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js',
];

include __DIR__ . '/includes/layout/app_start.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Menu items</h1>
    <p class="page-head__sub">
      <?= (int) $totalItems ?> item<?= $totalItems === 1 ? '' : 's' ?>
      <?php if ($unavailable > 0): ?>
        · <?= (int) $unavailable ?> unavailable
      <?php endif; ?>
      <?php if ($orphans > 0): ?>
        · <span style="color:var(--warn)"><?= (int) $orphans ?> without a category</span>
      <?php endif; ?>
    </p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="<?= url('categories.php') ?>">
      <i class="bi bi-tags"></i> Categories
    </a>
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#itemModal">
      <i class="bi bi-plus-lg"></i> Add item
    </button>
  </div>
</div>

<?php if (!$hasAvailability): ?>
  <div class="alert alert-warning mb-3">
    <i class="bi bi-database-exclamation"></i>
    <div>
      <strong>Migration 004 has not been run.</strong>
      The availability switch is disabled until you apply
      <code>sql/migrations/004_menu_items_availability.sql</code> in phpMyAdmin.
      Everything else on this page works normally.
    </div>
  </div>
<?php endif; ?>

<?php if ($orphans > 0): ?>
  <div class="alert alert-warning mb-3">
    <i class="bi bi-exclamation-triangle"></i>
    <div>
      <strong><?= (int) $orphans ?> item<?= $orphans === 1 ? '' : 's' ?> point at a category that no longer exists.</strong>
      They are shown below with a “No category” badge. Edit each one to assign a
      real category — or run <code>sql/migrations/002_fix_orphan_menu_category.sql</code>,
      which also adds a constraint preventing it from happening again.
    </div>
  </div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label" for="q">Search</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input class="form-control" type="search" id="q" name="q"
                 value="<?= e($search) ?>" placeholder="Name or description">
        </div>
      </div>

      <div class="col-md-3">
        <label class="form-label" for="category">Category</label>
        <select class="form-select" id="category" name="category">
          <option value="">All categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $filterCat === (int) $c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($hasAvailability): ?>
        <div class="col-md-2">
          <label class="form-label" for="status">Status</label>
          <select class="form-select" id="status" name="status">
            <option value="">Any</option>
            <option value="available"   <?= $filterStatus === 'available' ? 'selected' : '' ?>>Available</option>
            <option value="unavailable" <?= $filterStatus === 'unavailable' ? 'selected' : '' ?>>Unavailable</option>
          </select>
        </div>
      <?php endif; ?>

      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary flex-fill justify-content-center" type="submit">Filter</button>
        <?php if ($search !== '' || $filterCat > 0 || $filterStatus !== ''): ?>
          <a class="btn btn-ghost btn-icon" href="<?= url('menu.php') ?>" title="Clear filters">
            <i class="bi bi-x-lg"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card">
  <?php if ($items === []): ?>
    <div class="card-body">
      <div class="empty">
        <div class="empty__icon"><i class="bi bi-egg-fried"></i></div>
        <div class="empty__title">
          <?= ($search !== '' || $filterCat > 0 || $filterStatus !== '')
                ? 'Nothing matches those filters'
                : 'No menu items yet' ?>
        </div>
        <p class="empty__text">
          <?= ($search !== '' || $filterCat > 0 || $filterStatus !== '')
                ? 'Try a broader search, or clear the filters.'
                : 'Add your first dish and it will appear here and on the order screen.' ?>
        </p>
        <?php if ($search !== '' || $filterCat > 0 || $filterStatus !== ''): ?>
          <a class="btn btn-outline-secondary" href="<?= url('menu.php') ?>">Clear filters</a>
        <?php else: ?>
          <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#itemModal">
            <i class="bi bi-plus-lg"></i> Add the first item
          </button>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table" id="menuTable">
        <thead>
          <tr>
            <th style="width:60px">Image</th>
            <th>Item</th>
            <th>Category</th>
            <th class="text-end">Price</th>
            <?php if ($hasAvailability): ?><th>Status</th><?php endif; ?>
            <th>Added</th>
            <th style="width:120px" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <?php
            $img       = upload_url($item['food_image'], 'menu');
            $available = !$hasAvailability || (int) $item['is_available'] === 1;
            ?>
            <tr<?= $available ? '' : ' style="opacity:.62"' ?>>
              <td>
                <?php if ($img !== null): ?>
                  <img class="table__thumb" src="<?= e($img) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <div class="table__thumb table__thumb--empty" title="No image">
                    <i class="bi bi-image"></i>
                  </div>
                <?php endif; ?>
              </td>

              <td>
                <div class="table__primary"><?= e($item['name']) ?></div>
                <?php if (trim((string) $item['description']) !== ''): ?>
                  <div class="table__secondary truncate" style="max-width:320px">
                    <?= e($item['description']) ?>
                  </div>
                <?php endif; ?>
              </td>

              <td>
                <?php if ($item['category_name'] === null): ?>
                  <span class="badge-soft badge-soft--warn"
                        title="This item's category no longer exists">No category</span>
                <?php else: ?>
                  <span class="badge-soft badge-soft--neutral"><?= e($item['category_name']) ?></span>
                <?php endif; ?>
              </td>

              <td class="text-end fw-semi"><?= e(money($item['price'])) ?></td>

              <?php if ($hasAvailability): ?>
                <td>
                  <form method="post" action="<?= url('actions/menu.php') ?>" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="toggle">
                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                    <button type="submit"
                            class="badge-soft badge-soft--<?= $available ? 'ok' : 'neutral' ?>"
                            style="border:0;cursor:pointer"
                            title="Click to mark <?= $available ? 'unavailable' : 'available' ?>">
                      <?= $available ? 'Available' : 'Unavailable' ?>
                    </button>
                  </form>
                </td>
              <?php endif; ?>

              <td class="table__secondary"><?= e(date('j M Y', strtotime((string) $item['created_at']))) ?></td>

              <td>
                <div class="table__actions">
                  <button class="btn btn-ghost btn-icon btn-sm js-edit"
                          type="button"
                          title="Edit"
                          data-item='<?= e(json_encode([
                              'id'           => (int) $item['id'],
                              'name'         => $item['name'],
                              'category_id'  => (int) $item['category_id'],
                              'price'        => $item['price'],
                              'description'  => $item['description'],
                              'is_available' => $hasAvailability ? (int) $item['is_available'] : 1,
                              'image'        => $img,
                          ], JSON_UNESCAPED_UNICODE)) ?>'>
                    <i class="bi bi-pencil"></i>
                  </button>

                  <!-- POST, never a link. A GET delete can be fired by a
                       crawler or a prefetching browser (AUDIT.md E5). -->
                  <form method="post" action="<?= url('actions/menu.php') ?>" class="m-0"
                        data-confirm="Delete “<?= e($item['name']) ?>”? This cannot be undone.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                    <button class="btn btn-ghost btn-icon btn-sm" type="submit"
                            title="Delete" style="color:var(--bad)">
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
<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="post" action="<?= url('actions/menu.php') ?>"
            enctype="multipart/form-data" id="menuForm" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="create" id="formAction">
        <input type="hidden" name="id" value="" id="formId">
        <input type="hidden" name="remove_image" value="0" id="formRemoveImage">

        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Add menu item</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">

            <div class="col-md-8">
              <label class="form-label" for="f_name">Item name <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="text" id="f_name" name="name"
                     maxlength="100" required placeholder="e.g. Grilled goat meat">
            </div>

            <div class="col-md-4">
              <label class="form-label" for="f_price">Price <span style="color:var(--bad)">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><?= e(setting('currency_symbol', '$')) ?></span>
                <input class="form-control" type="number" id="f_price" name="price"
                       step="0.01" min="0" required placeholder="0.00">
              </div>
            </div>

            <div class="col-md-8">
              <label class="form-label" for="f_category">Category <span style="color:var(--bad)">*</span></label>
              <select class="form-select" id="f_category" name="category_id" required>
                <option value="">Choose a category…</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($categories === []): ?>
                <div class="form-hint" style="color:var(--warn)">
                  No categories exist yet — <a href="<?= url('categories.php') ?>">create one first</a>.
                </div>
              <?php endif; ?>
            </div>

            <?php if ($hasAvailability): ?>
              <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" role="switch"
                         id="f_available" name="is_available" value="1" checked>
                  <label class="form-check-label" for="f_available">Available to order</label>
                </div>
              </div>
            <?php endif; ?>

            <div class="col-12">
              <label class="form-label" for="f_description">Description</label>
              <textarea class="form-control" id="f_description" name="description"
                        rows="3" maxlength="2000"
                        placeholder="Short description shown to staff when ordering"></textarea>
            </div>

            <div class="col-12">
              <label class="form-label" for="f_image">Photo</label>
              <div class="d-flex align-items-start gap-3">
                <div style="position:relative;flex-shrink:0">
                  <img id="imgPreview" src="" alt=""
                       style="display:none;width:88px;height:88px;object-fit:cover;border-radius:var(--radius);border:1px solid var(--border)">
                  <div data-preview-placeholder
                       style="width:88px;height:88px;border-radius:var(--radius);border:1px dashed var(--border-strong);display:grid;place-items:center;color:var(--text-subtle)">
                    <i class="bi bi-image" style="font-size:1.4rem"></i>
                  </div>
                </div>
                <div class="flex-fill">
                  <input class="form-control" type="file" id="f_image" name="food_image"
                         accept="image/jpeg,image/png,image/gif,image/webp"
                         data-preview="#imgPreview">
                  <div class="form-hint">
                    JPG, PNG, GIF or WebP · max <?= round(UPLOAD_MAX_BYTES / 1024 / 1024, 1) ?> MB.
                    The file is verified as a real image and renamed on the server.
                  </div>
                  <button type="button" class="btn btn-ghost btn-sm mt-2 d-none" id="removeImageBtn"
                          style="color:var(--bad)">
                    <i class="bi bi-trash"></i> Remove current photo
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="submitBtn">
            <i class="bi bi-check-lg"></i> Save item
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<'JS'
(function () {
  var modalEl   = document.getElementById('itemModal');
  var form      = document.getElementById('menuForm');
  var titleEl   = document.getElementById('modalTitle');
  var actionEl  = document.getElementById('formAction');
  var idEl      = document.getElementById('formId');
  var removeEl  = document.getElementById('formRemoveImage');
  var preview   = document.getElementById('imgPreview');
  var placeholder = document.querySelector('[data-preview-placeholder]');
  var removeBtn = document.getElementById('removeImageBtn');

  function showPreview(src) {
    if (src) {
      preview.src = src;
      preview.style.display = '';
      if (placeholder) placeholder.style.display = 'none';
      removeBtn.classList.remove('d-none');
    } else {
      preview.src = '';
      preview.style.display = 'none';
      if (placeholder) placeholder.style.display = '';
      removeBtn.classList.add('d-none');
    }
  }

  function resetToCreate() {
    form.reset();
    actionEl.value = 'create';
    idEl.value = '';
    removeEl.value = '0';
    titleEl.textContent = 'Add menu item';
    showPreview(null);
  }

  // "Add item" buttons
  document.querySelectorAll('[data-bs-target="#itemModal"]').forEach(function (btn) {
    btn.addEventListener('click', resetToCreate);
  });

  // Edit buttons
  document.querySelectorAll('.js-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var item;
      try { item = JSON.parse(btn.getAttribute('data-item')); } catch (e) { return; }

      actionEl.value = 'update';
      idEl.value = item.id;
      removeEl.value = '0';
      titleEl.textContent = 'Edit “' + item.name + '”';

      form.querySelector('#f_name').value = item.name || '';
      form.querySelector('#f_price').value = item.price || '';
      form.querySelector('#f_category').value = item.category_id || '';
      form.querySelector('#f_description').value = item.description || '';

      var avail = form.querySelector('#f_available');
      if (avail) avail.checked = item.is_available === 1;

      form.querySelector('#f_image').value = '';
      showPreview(item.image);

      new bootstrap.Modal(modalEl).show();
    });
  });

  removeBtn.addEventListener('click', function () {
    removeEl.value = '1';
    form.querySelector('#f_image').value = '';
    showPreview(null);
  });

  // Re-enable the submit button if the modal is dismissed, since app.js
  // disables it on submit to prevent double posting.
  modalEl.addEventListener('hidden.bs.modal', function () {
    var btn = document.getElementById('submitBtn');
    btn.disabled = false;
    btn.style.opacity = '';
  });

  // DataTables for sort + paginate. Column count is read from the DOM, so
  // it can never drift out of sync the way the old hardcoded config did.
  var table = document.getElementById('menuTable');
  if (table && window.jQuery && jQuery.fn.DataTable) {
    var lastCol = table.querySelectorAll('thead th').length - 1;
    jQuery(table).DataTable({
      pageLength: 10,
      lengthMenu: [10, 25, 50, 100],
      order: [],
      // Image and Actions columns are not sortable or searchable.
      columnDefs: [
        { targets: [0, lastCol], orderable: false, searchable: false }
      ],
      language: {
        search: '',
        searchPlaceholder: 'Search this page…',
        lengthMenu: 'Show _MENU_',
        info: 'Showing _START_–_END_ of _TOTAL_',
        infoEmpty: 'Nothing to show',
        paginate: { previous: '‹', next: '›' }
      }
    });
  }
})();
JS;

include __DIR__ . '/includes/layout/app_end.php';
