<?php
/**
 * Place a new order.
 *
 * Replaces place_order.php + submit_order.php. This page only READS and
 * RENDERS; the write (with server-side pricing and tax/service calculation)
 * happens in actions/orders.php. See that file for why prices are never
 * trusted from the client.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager', 'cashier', 'waiter');

$title    = 'New order';
$subtitle = 'Build a cart, then confirm';

$schemaReady = db_value(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'order_items'",
    [DB_NAME]
) !== null;

if (!$schemaReady) {
    include __DIR__ . '/includes/layout/app_start.php';
    ?>
    <div class="page-head">
      <h1 class="page-head__title">New order</h1>
    </div>
    <div class="alert alert-warning">
      <i class="bi bi-database-exclamation"></i>
      <div>
        <strong>Migration 005 has not been run yet.</strong>
        Apply <code>sql/migrations/005_order_items_and_totals.sql</code> in phpMyAdmin
        before placing orders.
      </div>
    </div>
    <?php
    include __DIR__ . '/includes/layout/app_end.php';
    exit;
}

$hasAvailability = db_value(
    "SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'menu_items' AND COLUMN_NAME = 'is_available'",
    [DB_NAME]
) !== null;

$categories = db_all('SELECT id, name FROM categories ORDER BY name');

$itemsSql = 'SELECT m.id, m.name, m.price, m.category_id, m.food_image
               FROM menu_items m
              WHERE m.category_id IS NOT NULL'
          . ($hasAvailability ? ' AND m.is_available = 1' : '')
          . ' ORDER BY m.name';
$menuItems = db_all($itemsSql);

$tables = db_all('SELECT id, table_number, capacity, status FROM tables ORDER BY table_number');

$currency  = setting('currency_symbol', '$');
$taxRate   = (float) setting('tax_rate', 0);
$serviceRate = (float) setting('service_charge', 0);

$pageScripts = [];

include __DIR__ . '/includes/layout/app_start.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">New order</h1>
    <p class="page-head__sub"><?= count($menuItems) ?> item<?= count($menuItems) === 1 ? '' : 's' ?> available to order</p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn-outline-secondary" href="<?= url('orders.php') ?>">
      <i class="bi bi-receipt"></i> All orders
    </a>
  </div>
</div>

<?php if ($menuItems === []): ?>
  <div class="alert alert-warning mb-3">
    <i class="bi bi-exclamation-triangle"></i>
    No menu items are available to order right now. Add or enable items on the
    <a href="<?= url('menu.php') ?>">Menu items</a> page first.
  </div>
<?php endif; ?>

<div class="row g-3">
  <!-- ============ Menu picker ============ -->
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-body py-3">
        <div class="row g-2 align-items-end">
          <div class="col-md-6">
            <label class="form-label" for="itemSearch">Search</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input class="form-control" type="search" id="itemSearch" placeholder="Find a dish">
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="categoryFilter">Category</label>
            <select class="form-select" id="categoryFilter">
              <option value="">All categories</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3" id="menuGrid">
      <?php foreach ($menuItems as $item): ?>
        <?php $img = upload_url($item['food_image'], 'menu'); ?>
        <div class="col-sm-6 col-xl-4 menu-tile"
             data-name="<?= e(mb_strtolower($item['name'])) ?>"
             data-category="<?= (int) $item['category_id'] ?>">
          <div class="card h-100">
            <?php if ($img !== null): ?>
              <img src="<?= e($img) ?>" class="card-img-top" style="height:120px;object-fit:cover" alt="">
            <?php endif; ?>
            <div class="card-body d-flex flex-column">
              <div class="fw-semi mb-1"><?= e($item['name']) ?></div>
              <div class="table__secondary mb-2"><?= e(money($item['price'])) ?></div>
              <button type="button" class="btn btn-primary btn-sm mt-auto js-add-item"
                      data-id="<?= (int) $item['id'] ?>"
                      data-name="<?= e($item['name']) ?>"
                      data-price="<?= e($item['price']) ?>">
                <i class="bi bi-plus-lg"></i> Add
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="table__secondary d-none" id="noMatches">Nothing matches that search.</p>
  </div>

  <!-- ============ Cart / order form ============ -->
  <div class="col-lg-4">
    <div class="card" style="position:sticky;top:1rem">
      <div class="card-header"><h2>Order</h2></div>
      <div class="card-body">
        <form method="post" action="<?= url('actions/orders.php') ?>" id="orderForm">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="create">
          <input type="hidden" name="cart" id="cartField">

          <div class="mb-2">
            <label class="form-label" for="customer_name">Customer name</label>
            <input class="form-control" type="text" id="customer_name" name="customer_name"
                   maxlength="100" required placeholder="Walk-in customer">
          </div>

          <div class="mb-2">
            <label class="form-label" for="order_type">Order type</label>
            <select class="form-select" id="order_type" name="order_type" required>
              <option value="">Choose…</option>
              <option value="Dine-In">Dine-In</option>
              <option value="Takeaway">Takeaway</option>
              <option value="Delivery">Delivery</option>
            </select>
          </div>

          <div class="mb-2 d-none" id="tableField">
            <label class="form-label" for="table_id">Table</label>
            <select class="form-select" id="table_id" name="table_id">
              <option value="">Choose a table…</option>
              <?php foreach ($tables as $t): ?>
                <option value="<?= (int) $t['id'] ?>" <?= $t['status'] !== 'Available' ? 'disabled' : '' ?>>
                  <?= e($t['table_number']) ?> · seats <?= (int) $t['capacity'] ?>
                  <?= $t['status'] !== 'Available' ? '(' . e($t['status']) . ')' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if ($tables === []): ?>
              <div class="form-hint" style="color:var(--warn)">
                No tables exist yet — add one on the <a href="<?= url('tables.php') ?>">Tables</a> page.
              </div>
            <?php endif; ?>
          </div>

          <hr>

          <div id="cartLines">
            <p class="table__secondary" id="cartEmpty">No items added yet.</p>
          </div>

          <div class="mb-2 mt-2">
            <label class="form-label" for="discount">Discount (<?= e($currency) ?>)</label>
            <input class="form-control" type="number" id="discount" name="discount"
                   step="0.01" min="0" value="0">
          </div>

          <div class="table-wrap mb-2">
            <table class="table table-sm mb-0">
              <tbody>
                <tr><td>Subtotal</td><td class="text-end" id="sumSubtotal"><?= e($currency) ?>0.00</td></tr>
                <tr><td>Discount</td><td class="text-end" id="sumDiscount">-<?= e($currency) ?>0.00</td></tr>
                <tr><td>Tax (<?= e(rtrim(rtrim(number_format($taxRate, 2), '0'), '.')) ?>%)</td><td class="text-end" id="sumTax"><?= e($currency) ?>0.00</td></tr>
                <tr><td>Service charge (<?= e(rtrim(rtrim(number_format($serviceRate, 2), '0'), '.')) ?>%)</td><td class="text-end" id="sumService"><?= e($currency) ?>0.00</td></tr>
                <tr class="fw-semi"><td>Total</td><td class="text-end" id="sumTotal"><?= e($currency) ?>0.00</td></tr>
              </tbody>
            </table>
          </div>
          <p class="form-hint">This total is an estimate — the server recalculates it from live prices and settings.</p>

          <button type="submit" class="btn btn-primary w-100" id="submitOrderBtn" disabled>
            <i class="bi bi-check-lg"></i> Place order
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
$currency_js    = ejs($currency);
$taxRate_js     = ejs($taxRate);
$serviceRate_js = ejs($serviceRate);
$inlineScript = <<<JS
(function () {
  var cart = {};
  var currency = {$currency_js};
  var taxRate = {$taxRate_js};
  var serviceRate = {$serviceRate_js};

  var cartLines   = document.getElementById('cartLines');
  var cartEmpty   = document.getElementById('cartEmpty');
  var cartField   = document.getElementById('cartField');
  var submitBtn   = document.getElementById('submitOrderBtn');
  var discountEl  = document.getElementById('discount');
  var orderTypeEl = document.getElementById('order_type');
  var tableField  = document.getElementById('tableField');
  var tableSelect = document.getElementById('table_id');

  function money(n) { return currency + n.toFixed(2); }

  function render() {
    var ids = Object.keys(cart);
    cartLines.innerHTML = '';
    if (ids.length === 0) {
      cartLines.appendChild(cartEmpty);
    }

    var subtotal = 0;
    ids.forEach(function (id) {
      var line = cart[id];
      var lineTotal = line.price * line.qty;
      subtotal += lineTotal;

      var row = document.createElement('div');
      row.className = 'd-flex justify-content-between align-items-center mb-2';
      row.innerHTML =
        '<div class="flex-fill me-2">' +
          '<div class="fw-semi" style="font-size:.875rem">' + line.name + '</div>' +
          '<div class="table__secondary">' + money(line.price) + ' each</div>' +
        '</div>' +
        '<div class="d-flex align-items-center gap-1">' +
          '<button type="button" class="btn btn-ghost btn-icon btn-sm js-dec">-</button>' +
          '<span style="min-width:1.5rem;text-align:center">' + line.qty + '</span>' +
          '<button type="button" class="btn btn-ghost btn-icon btn-sm js-inc">+</button>' +
        '</div>';

      row.querySelector('.js-dec').addEventListener('click', function () {
        line.qty -= 1;
        if (line.qty <= 0) { delete cart[id]; }
        render();
      });
      row.querySelector('.js-inc').addEventListener('click', function () {
        line.qty += 1;
        render();
      });

      cartLines.appendChild(row);
    });

    var discount = Math.max(0, Math.min(parseFloat(discountEl.value) || 0, subtotal));
    var base = subtotal - discount;
    var tax = base * taxRate / 100;
    var service = base * serviceRate / 100;
    var total = subtotal - discount + tax + service;

    document.getElementById('sumSubtotal').textContent = money(subtotal);
    document.getElementById('sumDiscount').textContent = '-' + money(discount);
    document.getElementById('sumTax').textContent = money(tax);
    document.getElementById('sumService').textContent = money(service);
    document.getElementById('sumTotal').textContent = money(total);

    var payload = ids.map(function (id) { return { id: parseInt(id, 10), qty: cart[id].qty }; });
    cartField.value = JSON.stringify(payload);
    submitBtn.disabled = payload.length === 0;
  }

  document.querySelectorAll('.js-add-item').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-id');
      if (!cart[id]) {
        cart[id] = { name: btn.getAttribute('data-name'), price: parseFloat(btn.getAttribute('data-price')), qty: 0 };
      }
      cart[id].qty += 1;
      render();
    });
  });

  discountEl.addEventListener('input', render);

  orderTypeEl.addEventListener('change', function () {
    var isDineIn = orderTypeEl.value === 'Dine-In';
    tableField.classList.toggle('d-none', !isDineIn);
    tableSelect.required = isDineIn;
    if (!isDineIn) { tableSelect.value = ''; }
  });

  // Search + category filters over the already-rendered grid, so the cart
  // survives filtering instead of a page reload wiping it out.
  var searchEl = document.getElementById('itemSearch');
  var catEl    = document.getElementById('categoryFilter');
  var tiles    = document.querySelectorAll('.menu-tile');
  var noMatches = document.getElementById('noMatches');

  function applyFilter() {
    var q = searchEl.value.trim().toLowerCase();
    var cat = catEl.value;
    var visible = 0;
    tiles.forEach(function (tile) {
      var matchesName = q === '' || tile.getAttribute('data-name').indexOf(q) !== -1;
      var matchesCat  = cat === '' || tile.getAttribute('data-category') === cat;
      var show = matchesName && matchesCat;
      tile.classList.toggle('d-none', !show);
      if (show) visible++;
    });
    noMatches.classList.toggle('d-none', visible !== 0);
  }
  searchEl.addEventListener('input', applyFilter);
  catEl.addEventListener('change', applyFilter);

  document.getElementById('orderForm').addEventListener('submit', function () {
    submitBtn.disabled = true;
  });

  render();
})();
JS;

include __DIR__ . '/includes/layout/app_end.php';
