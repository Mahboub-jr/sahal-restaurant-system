<?php
/**
 * Stock movements -- the append-only ledger behind inventory.php's
 * quantity_on_hand. Recording one is the only write this page offers;
 * see actions/stock_movements.php for why there is no edit or delete.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager');

$title    = 'Stock movements';
$subtitle = 'Every change to stock, and why';

$schemaReady = db_value(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'stock_movements'",
    [DB_NAME]
) !== null;

include __DIR__ . '/includes/layout/app_start.php';

if (!$schemaReady): ?>
  <div class="page-head"><h1 class="page-head__title">Stock movements</h1></div>
  <div class="alert alert-warning">
    <i class="bi bi-database-exclamation"></i>
    <div>
      <strong>Migration 007 has not been run yet.</strong>
      Apply <code>sql/migrations/007_reservations_and_inventory.sql</code> in phpMyAdmin
      to use stock movements.
    </div>
  </div>
<?php
  include __DIR__ . '/includes/layout/app_end.php';
  exit;
endif;

$items = db_all('SELECT id, name, unit, quantity_on_hand FROM inventory_items ORDER BY name');

$MOVEMENT_TYPES = ['Received', 'Used', 'Wasted', 'Correction'];

$itemF = query_int('item_id');
$typeF = one_of(query('type'), $MOVEMENT_TYPES, '');

$where  = [];
$params = [];
if ($itemF > 0) {
    $where[]  = 'm.inventory_item_id = ?';
    $params[] = $itemF;
}
if ($typeF !== '') {
    $where[]  = 'm.type = ?';
    $params[] = $typeF;
}
$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$movements = db_all(
    "SELECT m.*, i.name AS item_name, i.unit, u.name AS recorded_by
       FROM stock_movements m
       JOIN inventory_items i ON i.id = m.inventory_item_id
       LEFT JOIN users u ON u.id = m.user_id
       $whereSql
      ORDER BY m.created_at DESC
      LIMIT 200",
    $params
);
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Stock movements</h1>
    <p class="page-head__sub"><?= count($movements) ?> shown (most recent 200)</p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="<?= url('inventory.php') ?>">
      <i class="bi bi-box-seam"></i> Inventory
    </a>
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#movementModal"
            <?= $items === [] ? 'disabled title="Add a stock item first"' : '' ?>>
      <i class="bi bi-plus-lg"></i> Record movement
    </button>
  </div>
</div>

<?php if ($items === []): ?>
  <div class="alert alert-warning mb-3">
    <i class="bi bi-exclamation-triangle"></i>
    No stock items yet — <a href="<?= url('inventory.php') ?>">add one</a> before recording a movement.
  </div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label" for="item_id">Item</label>
        <select class="form-select" id="item_id" name="item_id">
          <option value="">All items</option>
          <?php foreach ($items as $i): ?>
            <option value="<?= (int) $i['id'] ?>" <?= $itemF === (int) $i['id'] ? 'selected' : '' ?>><?= e($i['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="type">Type</label>
        <select class="form-select" id="type" name="type">
          <option value="">Any</option>
          <?php foreach ($MOVEMENT_TYPES as $t): ?>
            <option value="<?= e($t) ?>" <?= $typeF === $t ? 'selected' : '' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Filter</button>
      </div>
      <?php if ($itemF > 0 || $typeF !== ''): ?>
        <div class="col-12">
          <a class="btn btn-ghost btn-sm" href="<?= url('stock_movements.php') ?>"><i class="bi bi-x-lg"></i> Clear filters</a>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <?php if ($movements === []): ?>
    <div class="card-body">
      <div class="empty">
        <div class="empty__icon"><i class="bi bi-arrow-left-right"></i></div>
        <div class="empty__title">No movements match</div>
        <p class="empty__text">Record one, or try a different filter.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Item</th>
            <th>Type</th>
            <th class="text-end">Change</th>
            <th>Reason</th>
            <th>Recorded by</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($movements as $m): ?>
            <?php $positive = (float) $m['change_qty'] > 0; ?>
            <tr>
              <td class="table__secondary"><?= e(date('j M Y, H:i', strtotime((string) $m['created_at']))) ?></td>
              <td><?= e($m['item_name']) ?></td>
              <td><span class="badge-soft badge-soft--<?= $positive ? 'ok' : 'bad' ?>"><?= e($m['type']) ?></span></td>
              <td class="text-end fw-semi" style="color:var(--<?= $positive ? 'ok' : 'bad' ?>)">
                <?= $positive ? '+' : '' ?><?= e(number_format((float) $m['change_qty'], 2)) ?> <?= e($m['unit']) ?>
              </td>
              <td class="table__secondary"><?= e($m['reason'] ?? '—') ?></td>
              <td class="table__secondary"><?= e($m['recorded_by'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ============ Record movement modal ============ -->
<div class="modal fade" id="movementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?= url('actions/stock_movements.php') ?>" id="movementForm">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="create">

        <div class="modal-header">
          <h5 class="modal-title">Record stock movement</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label" for="f_item">Item <span style="color:var(--bad)">*</span></label>
              <select class="form-select" id="f_item" name="inventory_item_id" required>
                <option value="">Choose an item…</option>
                <?php foreach ($items as $i): ?>
                  <option value="<?= (int) $i['id'] ?>" data-unit="<?= e($i['unit']) ?>"
                          <?= $itemF === (int) $i['id'] ? 'selected' : '' ?>>
                    <?= e($i['name']) ?> (<?= e(number_format((float) $i['quantity_on_hand'], 2)) ?> <?= e($i['unit']) ?> on hand)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="f_type">Type <span style="color:var(--bad)">*</span></label>
              <select class="form-select" id="f_type" name="type" required>
                <option value="">Choose…</option>
                <option value="Received">Received</option>
                <option value="Used">Used</option>
                <option value="Wasted">Wasted</option>
                <option value="Correction">Correction</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="f_quantity">Quantity <span style="color:var(--bad)">*</span></label>
              <input class="form-control" type="number" id="f_quantity" name="quantity" step="0.01" min="0.01" required>
            </div>

            <div class="col-12 d-none" id="directionField">
              <label class="form-label" for="f_direction">Direction <span style="color:var(--bad)">*</span></label>
              <select class="form-select" id="f_direction" name="direction">
                <option value="increase">Increase stock</option>
                <option value="decrease">Decrease stock</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label" for="f_reason">Reason</label>
              <input class="form-control" type="text" id="f_reason" name="reason" maxlength="255"
                     placeholder="e.g. Delivery from supplier, spoiled, stock count correction">
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="submitBtn">
            <i class="bi bi-check-lg"></i> Record
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<'JS'
(function () {
  var modalEl = document.getElementById('movementModal');
  var typeEl  = document.getElementById('f_type');
  var directionField = document.getElementById('directionField');
  var directionEl = document.getElementById('f_direction');

  typeEl.addEventListener('change', function () {
    var isCorrection = typeEl.value === 'Correction';
    directionField.classList.toggle('d-none', !isCorrection);
    directionEl.required = isCorrection;
  });

  modalEl.addEventListener('hidden.bs.modal', function () {
    var btn = document.getElementById('submitBtn');
    btn.disabled = false;
    btn.style.opacity = '';
  });
})();
JS;

include __DIR__ . '/includes/layout/app_end.php';
