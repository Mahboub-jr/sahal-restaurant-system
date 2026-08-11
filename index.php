<?php
include "library/conn.php";

// 1. Daily Sales
$todayRes = mysqli_query($conn, "
  SELECT IFNULL(SUM(total_amount),0) AS total
  FROM orders
  WHERE DATE(created_at) = CURDATE()
");
$today = mysqli_fetch_assoc($todayRes)['total'];

// 2. Weekly Sales
$weekRes = mysqli_query($conn, "
  SELECT IFNULL(SUM(total_amount),0) AS total
  FROM orders
  WHERE YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1)
");
$week = mysqli_fetch_assoc($weekRes)['total'];

// 3. Order Counts by Status
$statusRes = mysqli_query($conn, "
  SELECT status, COUNT(*) AS cnt
  FROM orders
  GROUP BY status
");
$statusCounts = [];
while ($r = mysqli_fetch_assoc($statusRes)) {
  $statusCounts[$r['status']] = $r['cnt'];
}

// 4. Best-Selling Items
$itemRes = mysqli_query($conn, "SELECT items FROM orders");
$itemCounts = [];
while ($r = mysqli_fetch_assoc($itemRes)) {
  $items = json_decode($r['items'], true);
  if (is_array($items)) {
    foreach ($items as $it) {
      $n = $it['name'];
      $itemCounts[$n] = ($itemCounts[$n] ?? 0) + 1;
    }
  }
}
arsort($itemCounts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <link rel="stylesheet" href="css/main.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
  <?php include "library/sidebar.php"; ?>
  <?php include "library/header.php"; ?>

  <main class="app-content">
    <div class="container-fluid py-4">
      <!-- Summary Cards -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="card text-white bg-primary">
            <div class="card-body">
              <h5 class="card-title">Today's Sales</h5>
              <p class="card-text display-6">$<?= number_format($today,2) ?></p>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card text-white bg-success">
            <div class="card-body">
              <h5 class="card-title">This Week's Sales</h5>
              <p class="card-text display-6">$<?= number_format($week,2) ?></p>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card text-white bg-secondary">
            <div class="card-body">
              <h5 class="card-title">Total Orders</h5>
              <p class="card-text display-6"><?= array_sum($statusCounts) ?></p>
            </div>
          </div>
        </div>
      </div>

      <!-- Orders by Status -->
      <div class="row g-3 mb-4">
        <?php 
        $statusMap = [
          'Pending'     => 'secondary',
          'Preparing'   => 'warning',
          'Ready'       => 'info',
          'Completed'   => 'success',
          'Cancelled'   => 'danger',
        ];
        
        foreach ($statusMap as $st => $color): 
          // build URL-safe status
          $url = 'orders.php?status=' . urlencode($st);
      ?>
        <div class="col-md-3">
          <a href="<?= $url ?>" class="text-decoration-none">
            <div class="card border-0 text-center">
              <div class="card-body">
                <span class="badge bg-<?= $color ?> mb-2"><?= $st ?></span>
                <p class="h2"><?= $statusCounts[$st] ?? 0 ?></p>
              </div>
            </div>
          </a>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Best-Selling Items -->
      <div class="card shadow-sm">
        <div class="card-header">
          <h5 class="mb-0"><i class="bi bi-bar-chart-line-fill me-2"></i>Best-Selling Items</h5>
        </div>
        <div class="card-body p-0">
          <table class="table table-striped mb-0">
            <thead class="table-light">
              <tr><th>#</th><th>Item Name</th><th>Times Ordered</th></tr>
            </thead>
            <tbody>
              <?php
              $i = 1;
              foreach ($itemCounts as $name => $cnt) {
                echo "<tr>
                        <td>{$i}</td>
                        <td>" . htmlspecialchars($name) . "</td>
                        <td>{$cnt}</td>
                      </tr>";
                if (++$i > 10) break;
              }
              if ($i === 1) {
                echo "<tr><td colspan='3' class='text-center text-muted'>No items ordered yet.</td></tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <?php include "library/footer.php"; ?>
  <?php include "library/script.php"; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
