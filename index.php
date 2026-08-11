<?php
/**
 * Dashboard.
 *
 * The previous version fetched EVERY order row and json_decode()d them in a
 * PHP loop to work out best sellers (AUDIT.md F3) — that does not survive
 * contact with a real order volume. Everything here is aggregated in SQL.
 *
 * Item-level figures still read orders.items because order_items does not
 * exist yet; that is the Phase 3 migration. Where a number is only as good
 * as that blob, the card says so rather than implying false precision.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$title    = 'Dashboard';
$subtitle = 'Overview of today\'s trading';

/* -----------------------------------------------------------------
 | Figures
 |----------------------------------------------------------------- */

// Revenue is counted from completed orders only — pending and cancelled
// orders are not money in the till.
$todayRevenue = (float) db_value(
    "SELECT IFNULL(SUM(total_amount), 0) FROM orders
      WHERE DATE(created_at) = CURDATE() AND status = 'Completed'"
);

$yesterdayRevenue = (float) db_value(
    "SELECT IFNULL(SUM(total_amount), 0) FROM orders
      WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        AND status = 'Completed'"
);

$todayOrders = (int) db_value(
    'SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()'
);

$activeOrders = (int) db_value(
    "SELECT COUNT(*) FROM orders
      WHERE status IN ('Pending', 'Preparing', 'Ready')"
);

$weekRevenue = (float) db_value(
    "SELECT IFNULL(SUM(total_amount), 0) FROM orders
      WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
        AND status = 'Completed'"
);

// Day-on-day movement.
$revenueDelta = null;
if ($yesterdayRevenue > 0) {
    $revenueDelta = (($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100;
}

// Tables
$tableStats = db_one(
    "SELECT
        COUNT(*)                                          AS total,
        SUM(status = 'Available')                         AS available,
        SUM(status = 'Occupied')                          AS occupied,
        SUM(status = 'Reserved')                          AS reserved
       FROM tables"
) ?? ['total' => 0, 'available' => 0, 'occupied' => 0, 'reserved' => 0];

// Orders by status, for the strip and the doughnut.
$statusRows = db_all(
    'SELECT status, COUNT(*) AS c, IFNULL(SUM(total_amount),0) AS v
       FROM orders GROUP BY status'
);
$statusCounts = [];
foreach ($statusRows as $r) {
    $statusCounts[(string) $r['status']] = (int) $r['c'];
}

// Last 7 days of completed revenue — built in SQL, gaps filled in PHP so
// days with no trade still appear on the chart.
$trendRows = db_all(
    "SELECT DATE(created_at) AS d,
            IFNULL(SUM(total_amount), 0) AS revenue,
            COUNT(*) AS orders
       FROM orders
      WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        AND status = 'Completed'
      GROUP BY DATE(created_at)"
);
$byDate = [];
foreach ($trendRows as $r) {
    $byDate[(string) $r['d']] = $r;
}

$trendLabels = [];
$trendRevenue = [];
$trendOrders = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i day"));
    $trendLabels[]  = date('D', strtotime($day));
    $trendRevenue[] = round((float) ($byDate[$day]['revenue'] ?? 0), 2);
    $trendOrders[]  = (int) ($byDate[$day]['orders'] ?? 0);
}

// Recent orders
$recentOrders = db_all(
    'SELECT id, customer_name, order_type, total_amount, status, created_at
       FROM orders ORDER BY created_at DESC LIMIT 8'
);

// Menu spread by category — a real SQL aggregate.
$categoryRows = db_all(
    'SELECT c.name, COUNT(m.id) AS items
       FROM categories c
       LEFT JOIN menu_items m ON m.category_id = c.id
      GROUP BY c.id, c.name
      HAVING items > 0
      ORDER BY items DESC
      LIMIT 8'
);

// Best sellers. Still parsed from the JSON blob, so it is explicitly
// labelled as approximate until Phase 3 introduces order_items.
$itemCounts = [];
$unparseable = 0;
foreach (db_all("SELECT items FROM orders WHERE status <> 'Cancelled'") as $row) {
    $decoded = json_decode((string) $row['items'], true);
    if (!is_array($decoded)) {
        if (trim((string) $row['items']) !== '') {
            $unparseable++;
        }
        continue;
    }
    foreach ($decoded as $line) {
        if (!is_array($line) || !isset($line['name'])) {
            continue;
        }
        $name = trim((string) $line['name']);
        if ($name === '') {
            continue;
        }
        $itemCounts[$name] = ($itemCounts[$name] ?? 0) + 1;
    }
}
arsort($itemCounts);
$topItems = array_slice($itemCounts, 0, 6, true);
$topMax   = $topItems === [] ? 1 : max($topItems);

$pageScripts = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'];

include __DIR__ . '/includes/layout/app_start.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Good <?= date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', user_name())[0]) ?></h1>
    <p class="page-head__sub"><?= date('l, j F Y') ?></p>
  </div>
  <div class="page-head__actions">
    <?php if (has_role('admin', 'manager', 'cashier', 'waiter')): ?>
      <a class="btn btn-primary" href="<?= url('place_order.php') ?>">
        <i class="bi bi-plus-lg"></i> New order
      </a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary" href="<?= url('orders.php') ?>">
      <i class="bi bi-receipt"></i> All orders
    </a>
  </div>
</div>

<!-- ============ KPI cards ============ -->
<div class="row g-3 mb-4">

  <div class="col-6 col-xl-3">
    <div class="stat">
      <div class="stat__top">
        <span class="stat__label">Revenue today</span>
        <span class="stat__icon stat__icon--ok"><i class="bi bi-cash-stack"></i></span>
      </div>
      <div class="stat__value"><?= e(money($todayRevenue)) ?></div>
      <div class="stat__meta">
        <?php if ($revenueDelta !== null): ?>
          <span class="stat__delta stat__delta--<?= $revenueDelta >= 0 ? 'up' : 'down' ?>">
            <i class="bi bi-arrow-<?= $revenueDelta >= 0 ? 'up' : 'down' ?>"></i>
            <?= number_format(abs($revenueDelta), 1) ?>%
          </span>
          vs yesterday
        <?php else: ?>
          No completed sales yesterday
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="stat">
      <div class="stat__top">
        <span class="stat__label">Orders today</span>
        <span class="stat__icon stat__icon--brand"><i class="bi bi-receipt"></i></span>
      </div>
      <div class="stat__value"><?= number_format($todayOrders) ?></div>
      <div class="stat__meta"><?= e(money($weekRevenue)) ?> this week</div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="stat">
      <div class="stat__top">
        <span class="stat__label">In progress</span>
        <span class="stat__icon stat__icon--warn"><i class="bi bi-hourglass-split"></i></span>
      </div>
      <div class="stat__value"><?= number_format($activeOrders) ?></div>
      <div class="stat__meta">Pending, preparing or ready</div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="stat">
      <div class="stat__top">
        <span class="stat__label">Tables free</span>
        <span class="stat__icon stat__icon--info"><i class="bi bi-grid-3x3"></i></span>
      </div>
      <div class="stat__value">
        <?= (int) $tableStats['available'] ?><span class="text-subtle" style="font-size:1.1rem">/<?= (int) $tableStats['total'] ?></span>
      </div>
      <div class="stat__meta">
        <?= (int) $tableStats['occupied'] ?> occupied ·
        <?= (int) $tableStats['reserved'] ?> reserved
      </div>
    </div>
  </div>
</div>

<!-- ============ Charts ============ -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header">
        <h2>Revenue, last 7 days</h2>
        <span class="badge-soft badge-soft--neutral">Completed orders only</span>
      </div>
      <div class="card-body">
        <?php if (array_sum($trendRevenue) <= 0): ?>
          <div class="empty">
            <div class="empty__icon"><i class="bi bi-graph-up"></i></div>
            <div class="empty__title">No completed sales this week</div>
            <p class="empty__text">
              Revenue appears here once orders reach the <strong>Completed</strong> status.
            </p>
          </div>
        <?php else: ?>
          <div style="height:280px"><canvas id="revenueChart"></canvas></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><h2>Orders by status</h2></div>
      <div class="card-body">
        <?php if ($statusCounts === []): ?>
          <div class="empty">
            <div class="empty__icon"><i class="bi bi-pie-chart"></i></div>
            <div class="empty__title">No orders yet</div>
          </div>
        <?php else: ?>
          <div style="height:200px"><canvas id="statusChart"></canvas></div>
          <div class="mt-3">
            <?php foreach ($statusCounts as $status => $count): ?>
              <a href="<?= url('orders.php?status=' . urlencode((string) $status)) ?>"
                 class="d-flex justify-content-between align-items-center py-1"
                 style="font-size:.8125rem;color:var(--text)">
                <span class="badge-soft badge-soft--<?= $status === 'Completed' ? 'ok' : ($status === 'Cancelled' ? 'bad' : ($status === 'Preparing' ? 'warn' : 'info')) ?>">
                  <?= e($status) ?>
                </span>
                <span class="fw-semi"><?= (int) $count ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ============ Recent orders + best sellers ============ -->
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header">
        <h2>Recent orders</h2>
        <a href="<?= url('orders.php') ?>" style="font-size:.8125rem">View all <i class="bi bi-arrow-right"></i></a>
      </div>

      <?php if ($recentOrders === []): ?>
        <div class="card-body">
          <div class="empty">
            <div class="empty__icon"><i class="bi bi-receipt"></i></div>
            <div class="empty__title">No orders yet</div>
            <p class="empty__text">Orders will appear here as soon as staff start taking them.</p>
            <a class="btn btn-primary" href="<?= url('place_order.php') ?>">
              <i class="bi bi-plus-lg"></i> Take the first order
            </a>
          </div>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Type</th>
                <th class="text-end">Total</th>
                <th>Status</th>
                <th>When</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentOrders as $o): ?>
                <tr>
                  <td class="mono">#<?= (int) $o['id'] ?></td>
                  <td class="table__primary"><?= e($o['customer_name']) ?></td>
                  <td>
                    <?php if (trim((string) $o['order_type']) === '' || $o['order_type'] === 'Unknown'): ?>
                      <span class="text-subtle" title="Lost to the order_type bug — see AUDIT-ADDENDUM.md">—</span>
                    <?php else: ?>
                      <span class="table__secondary"><?= e($o['order_type']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end fw-semi"><?= e(money($o['total_amount'])) ?></td>
                  <td>
                    <span class="badge-soft badge-soft--<?= $o['status'] === 'Completed' ? 'ok' : ($o['status'] === 'Cancelled' ? 'bad' : ($o['status'] === 'Preparing' ? 'warn' : 'info')) ?>">
                      <?= e($o['status']) ?>
                    </span>
                  </td>
                  <td class="table__secondary"><?= e(time_ago($o['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        <h2>Best sellers</h2>
        <span class="badge-soft badge-soft--warn" data-bs-toggle="tooltip"
              title="Counted from the orders.items JSON blob. Quantities are not recorded yet, so each line counts once. Accurate figures arrive with the order_items migration.">
          Approximate
        </span>
      </div>
      <div class="card-body">
        <?php if ($topItems === []): ?>
          <div class="empty">
            <div class="empty__icon"><i class="bi bi-star"></i></div>
            <div class="empty__title">Not enough data</div>
            <p class="empty__text">Best sellers appear once orders have been placed.</p>
          </div>
        <?php else: ?>
          <?php foreach ($topItems as $name => $count): ?>
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semi text-truncate" style="font-size:.875rem"><?= e($name) ?></span>
                <span class="table__secondary"><?= (int) $count ?></span>
              </div>
              <div style="height:6px;background:var(--surface-muted);border-radius:99px;overflow:hidden">
                <div style="height:100%;width:<?= max(4, round(($count / $topMax) * 100)) ?>%;background:var(--brand-500);border-radius:99px"></div>
              </div>
            </div>
          <?php endforeach; ?>

          <?php if ($unparseable > 0): ?>
            <p class="form-hint mb-0">
              <i class="bi bi-info-circle"></i>
              <?= (int) $unparseable ?> older order<?= $unparseable === 1 ? '' : 's' ?>
              store items as free text and could not be counted.
            </p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
$chartData = [
    'labels'   => $trendLabels,
    'revenue'  => $trendRevenue,
    'orders'   => $trendOrders,
    'statuses' => array_keys($statusCounts),
    'counts'   => array_values($statusCounts),
];

$inlineScript = '
(function () {
  if (typeof Chart === "undefined") return;

  var data = ' . json_encode($chartData) . ';
  var css  = getComputedStyle(document.documentElement);
  var text = css.getPropertyValue("--text-muted").trim() || "#64748b";
  var grid = css.getPropertyValue("--border").trim() || "#e2e8f0";

  Chart.defaults.font.family = "Inter, system-ui, sans-serif";
  Chart.defaults.color = text;

  var revenueEl = document.getElementById("revenueChart");
  if (revenueEl) {
    var ctx = revenueEl.getContext("2d");
    var fill = ctx.createLinearGradient(0, 0, 0, 260);
    fill.addColorStop(0, "rgba(249,115,22,.28)");
    fill.addColorStop(1, "rgba(249,115,22,0)");

    new Chart(revenueEl, {
      type: "line",
      data: {
        labels: data.labels,
        datasets: [{
          label: "Revenue",
          data: data.revenue,
          borderColor: "#ea580c",
          backgroundColor: fill,
          borderWidth: 2.5,
          fill: true,
          tension: .38,
          pointRadius: 3,
          pointBackgroundColor: "#ea580c",
          pointBorderColor: "#fff",
          pointBorderWidth: 2,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: "#0f172a",
            padding: 12,
            cornerRadius: 8,
            displayColors: false,
            callbacks: {
              label: function (c) {
                var i = c.dataIndex;
                return [" Revenue: " + c.parsed.y.toFixed(2),
                        " Orders: " + data.orders[i]];
              }
            }
          }
        },
        scales: {
          y: { beginAtZero: true, border: { display: false },
               grid: { color: grid }, ticks: { padding: 8 } },
          x: { border: { display: false }, grid: { display: false } }
        }
      }
    });
  }

  var statusEl = document.getElementById("statusChart");
  if (statusEl) {
    var palette = {
      Pending: "#64748b", Confirmed: "#0284c7", Preparing: "#d97706",
      Ready: "#f97316", Served: "#0ea5e9", Completed: "#16a34a", Cancelled: "#dc2626"
    };
    new Chart(statusEl, {
      type: "doughnut",
      data: {
        labels: data.statuses,
        datasets: [{
          data: data.counts,
          backgroundColor: data.statuses.map(function (s) { return palette[s] || "#94a3b8"; }),
          borderWidth: 0,
          hoverOffset: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: "68%",
        plugins: {
          legend: { display: false },
          tooltip: { backgroundColor: "#0f172a", padding: 10, cornerRadius: 8 }
        }
      }
    });
  }
})();
';

include __DIR__ . '/includes/layout/app_end.php';
