<?php
/**
 * Inventory -- stock items.
 *
 * Read and render only. Creating/editing an item goes through
 * actions/inventory.php; adjusting how much of it you have goes through
 * stock_movements.php, never a direct edit here -- see that page for why.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager');

$title    = 'Inventory';
$subtitle = 'Stock items and reorder levels';

$schemaReady = db_value(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'inventory_items'",
    [DB_NAME]
) !== null;

include __DIR__ . '/includes/layout/app_start.php';

if (!$schemaReady): ?>
  <div class="page-head"><h1 class="page-head__title">Inventory</h1></div>
  <div class="alert alert-warning">
    <i class="bi bi-database-exclamation"></i>
    <div>
      <strong>Migration 007 has not been run yet.</strong>
      Apply <code>sql/migrations/007_reservations_and_inventory.sql</code> in phpMyAdmin
      to use inventory.
    </div>
  </div>
<?php
  include __DIR__ . '/includes/layout/app_end.php';
  exit;
endif;

$search = query('q');

$where  = [];
$params = [];
if ($search !== '') {
    $where[]  = '(name LIKE ? OR supplier LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$items = db_all(
    "SELECT * FROM inventory_items $whereSql ORDER BY name",
    $params
);

$lowStock = 0;
foreach ($items as $i) {
    if ((float) $i['quantity_on_hand'] <= (float) $i['reorder_level']) {
        $lowStock++;
    }
}
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Inventory</h1>
    <p class="page-head__sub">
      <?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?>
      <?php if ($lowStock > 0): ?>
        · <span style="color:var(--warn)"><?= $lowStock ?> at or below reorder level</span>
      <?php endif; ?>
    </p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="<?= url('stock_movements.php') ?>">
      <i class="bi bi-arrow-left-right"></i> Stock movements
    </a>
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#itemModal">
      <i class="bi bi-plus-lg"></i> Add item
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
          <input class="form-control" type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Name or supplier">
        </div>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100" type="submit">Filter</button>
      </div>
      <?php if ($search !== ''): ?>
        <div class="col-12">
          <a class="btn btn-ghost btn-sm" href="<?= url('inventory.php') ?>"><i class="bi bi-x-lg"></i> Clear</a>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <?php if ($items === []): ?>
    <div class="card-body">
      <div class="empty">
        <div class="empty__icon"><i class="bi bi-box-seam"></i></div>
        <div class="empty__title"><?= $search !== '' ? 'Nothing matches that search' : 'No stock items yet' ?></div>
        <p class="empty__text">
          <?= $search !== '' ? 'Try a broader search, or clear it.' : 'Add the first ingredient or supply you want to track.' ?>
        </p>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Item</th>
            <th>Supplier</th>
            <th class="text-end">On hand</th>
            <th class="text-end">Reorder at</th>
            <th class="text-end">Cost / unit</th>
            <th style="width:100px" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <?php $low = (float) $item['quantity_on_hand'] <= (float) $item['reorder_level']; ?>
            <tr<?= $low ? ' style="background:var(--warn-bg)"' : '' ?>>
              <td>
                <div class="table__primary"><?= e($item['name']) ?></div>
                <?php if ($item['notes']): ?><div class="table__secondary"><?= e($item['notes']) ?></div><?php endif; ?>
              </td>
              <td class="table__secondary"><?= e($item['supplier'] ?? '—') ?></td>
              <td class="text-end fw-semi">
                <?= e(number_format((float) $item['quantity_on_hand'], 2)) ?> <?= e($item['unit']) ?>
                <?php if ($low): ?>
                  <div><span class="badge-soft badge-soft--warn">Reorder</span></div>
                <?php endif; ?>
              </td>
              <td class="text-end table__secondary"><?= e(number_format((float) $item['reorder_level'], 2)) ?> <?= e($item['unit']) ?></td>
              <td class="text-end"><?= $item['cost_per_unit'] !== null ? e(money($item['cost_per_unit'])) : '—' ?></td>
              <td>
                <div class="table__actions justify-content-end">
                  <button class="btn btn-ghost btn-icon btn-sm js-edit" type="button" title="Edit"
                          data-item='<?= e(json_encode([
                              'id'            => (int) $item['id'],
                              'name'          => $item['name'],
                              'unit'          => $item['unit'],
                              'reorder_level' => $item['reorder_level'],
                              'cost_per_unit' => $item['cost_per_unit'],
                              'supplier'      => $item['supplier'],
                              'notes'         => $item['notes'],
                          ], JSON_UNESCAPED_UNICODE)) ?>'>
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="post" action="<?= url('actions/inventory.php') ?>" class="m-0"
                        data-confirm="Delete “<?= e($item['name']) ?>”? This only works if it has no stock movements yet.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
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

<!-- ============ Add / edit item modal ============ -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?= url('actions/inventory.php') ?>" id="itemForm">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="create" id="formAction">
        <input type="hidden" name="id" value="" id="formId">

        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Add stock item</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-8">
              <label class="form-label" for="f_name">Name <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="text" id="f_name" name="name" maxlength="100" required placeholder="e.g. Basmati rice">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="f_unit">Unit <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="text" id="f_unit" name="unit" maxlength="20" required placeholder="kg, l, pcs…">
            </div>

            <div class="col-md-6" id="initialQtyField">
              <label class="form-label" for="f_initial_qty">Starting quantity</label>
              <input class="form-control" type="number" id="f_initial_qty" name="initial_quantity" step="0.01" min="0" value="0">
              <div class="form-hint">Recorded as a "Received" movement, not editable later -- adjust via Stock movements.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_reorder">Reorder level</label>
              <input class="form-control" type="number" id="f_reorder" name="reorder_level" step="0.01" min="0" value="0">
            </div>

            <div class="col-md-6">
              <label class="form-label" for="f_cost">Cost per unit (<?= e(setting('currency_symbol', '$')) ?>)</label>
              <input class="form-control" type="number" id="f_cost" name="cost_per_unit" step="0.01" min="0">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_supplier">Supplier</label>
              <input class="form-control" type="text" id="f_supplier" name="supplier" maxlength="100">
            </div>

            <div class="col-12">
              <label class="form-label" for="f_notes">Notes</label>
              <textarea class="form-control" id="f_notes" name="notes" rows="2" maxlength="255"></textarea>
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
  var modalEl  = document.getElementById('itemModal');
  var form     = document.getElementById('itemForm');
  var titleEl  = document.getElementById('modalTitle');
  var actionEl = document.getElementById('formAction');
  var idEl     = document.getElementById('formId');
  var initialQtyField = document.getElementById('initialQtyField');

  function resetToCreate() {
    form.reset();
    actionEl.value = 'create';
    idEl.value = '';
    titleEl.textContent = 'Add stock item';
    initialQtyField.classList.remove('d-none');
  }

  document.querySelectorAll('[data-bs-target="#itemModal"]').forEach(function (btn) {
    btn.addEventListener('click', resetToCreate);
  });

  document.querySelectorAll('.js-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var item;
      try { item = JSON.parse(btn.getAttribute('data-item')); } catch (e) { return; }

      actionEl.value = 'update';
      idEl.value = item.id;
      titleEl.textContent = 'Edit “' + item.name + '”';
      initialQtyField.classList.add('d-none');

      form.querySelector('#f_name').value = item.name || '';
      form.querySelector('#f_unit').value = item.unit || '';
      form.querySelector('#f_reorder').value = item.reorder_level || 0;
      form.querySelector('#f_cost').value = item.cost_per_unit || '';
      form.querySelector('#f_supplier').value = item.supplier || '';
      form.querySelector('#f_notes').value = item.notes || '';

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
