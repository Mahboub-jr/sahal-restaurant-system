<?php
/**
 * Kitchen display -- Pending / Preparing / Ready, one column each.
 *
 * Read-only except for the two buttons that move an order to the next
 * kitchen stage; both post to the same actions/orders.php (do=set_status)
 * that orders.php uses; see that file for why chef can move Pending and
 * Preparing but not Complete or Cancel.
 *
 * Auto-refreshes so a chef never has to reload by hand.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager', 'chef');

$title    = 'Kitchen';
$subtitle = 'Pending, preparing, ready';

$schemaReady = db_value(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'order_items'",
    [DB_NAME]
) !== null;

include __DIR__ . '/includes/layout/app_start.php';

if (!$schemaReady): ?>
  <div class="page-head"><h1 class="page-head__title">Kitchen</h1></div>
  <div class="alert alert-warning">
    <i class="bi bi-database-exclamation"></i>
    <div>
      <strong>Migration 005 has not been run yet.</strong>
      Apply <code>sql/migrations/005_order_items_and_totals.sql</code> in phpMyAdmin
      to use the kitchen display.
    </div>
  </div>
<?php
  include __DIR__ . '/includes/layout/app_end.php';
  exit;
endif;

$orders = db_all(
    "SELECT o.id, o.order_number, o.customer_name, o.order_type, o.status,
            o.created_at, o.updated_at, t.table_number
       FROM orders o
       LEFT JOIN tables t ON t.id = o.table_id
      WHERE o.status IN ('Pending', 'Preparing', 'Ready')
      ORDER BY o.created_at ASC"
);

$lines = [];
if ($orders !== []) {
    $ids          = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    foreach (db_all(
        "SELECT order_id, item_name, quantity, notes FROM order_items
          WHERE order_id IN ($placeholders) ORDER BY id",
        $ids
    ) as $line) {
        $lines[(int) $line['order_id']][] = $line;
    }
}

$columns = ['Pending' => [], 'Preparing' => [], 'Ready' => []];
foreach ($orders as $o) {
    $columns[$o['status']][] = $o;
}

// A ticket sitting too long in one stage gets a warning colour -- purely a
// visual nudge, no schema for per-stage timestamps exists yet, so this is
// measured from updated_at (the last status change).
$agingMinutes = 10;

$columnMeta = [
    'Pending'   => ['icon' => 'bi-hourglass-split', 'next' => 'Preparing', 'action' => 'Start preparing'],
    'Preparing' => ['icon' => 'bi-fire',             'next' => 'Ready',     'action' => 'Mark ready'],
    'Ready'     => ['icon' => 'bi-bell',              'next' => null,        'action' => null],
];
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Kitchen</h1>
    <p class="page-head__sub"><?= count($orders) ?> ticket<?= count($orders) === 1 ? '' : 's' ?> in progress</p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="<?= url('orders.php') ?>">
      <i class="bi bi-receipt"></i> All orders
    </a>
  </div>
</div>

<div class="row g-3">
  <?php foreach ($columnMeta as $status => $meta): ?>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h2><i class="bi <?= $meta['icon'] ?> me-1"></i> <?= e($status) ?></h2>
          <span class="badge-soft badge-soft--neutral"><?= count($columns[$status]) ?></span>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:.75rem">
          <?php if ($columns[$status] === []): ?>
            <p class="table__secondary mb-0">Nothing here.</p>
          <?php endif; ?>

          <?php foreach ($columns[$status] as $o): ?>
            <?php
            $ageMinutes = (int) floor((time() - strtotime((string) $o['updated_at'])) / 60);
            $aging      = $ageMinutes >= $agingMinutes;
            ?>
            <div class="card" style="border-left:3px solid var(--<?= $aging ? 'warn' : 'border-strong' ?>)">
              <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-start mb-1">
                  <div class="fw-semi"><?= e($o['order_number'] ?? ('#' . $o['id'])) ?></div>
                  <span class="table__secondary <?= $aging ? 'text-warning' : '' ?>"><?= $ageMinutes ?>m</span>
                </div>
                <div class="table__secondary mb-2">
                  <?= e($o['customer_name']) ?> ·
                  <?= e($o['order_type']) ?><?= $o['table_number'] ? ' · Table ' . e($o['table_number']) : '' ?>
                </div>

                <ul class="list-unstyled mb-2" style="font-size:.875rem">
                  <?php foreach ($lines[(int) $o['id']] ?? [] as $l): ?>
                    <li>
                      <span class="fw-semi"><?= (int) $l['quantity'] ?>×</span> <?= e($l['item_name']) ?>
                      <?php if (!empty($l['notes'])): ?>
                        <div class="table__secondary" style="padding-left:1.2rem">Note: <?= e($l['notes']) ?></div>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                  <?php if (empty($lines[(int) $o['id']])): ?>
                    <li class="table__secondary">No items recorded.</li>
                  <?php endif; ?>
                </ul>

                <?php if ($meta['next'] !== null): ?>
                  <form method="post" action="<?= url('actions/orders.php') ?>" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="set_status">
                    <input type="hidden" name="redirect" value="kitchen.php">
                    <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                    <input type="hidden" name="status" value="<?= e($meta['next']) ?>">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><?= e($meta['action']) ?></button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php
$inlineScript = <<<'JS'
setTimeout(function () { window.location.reload(); }, 25000);
JS;

include __DIR__ . '/includes/layout/app_end.php';
