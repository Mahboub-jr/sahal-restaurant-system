<?php
require_once __DIR__ . '/includes/legacy_guard.php';

include "library/conn.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order History</title>
  <link rel="stylesheet" href="css/main.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php include "library/head.php"; ?>
  <style>
    /* Optional: tighten up table rows */
    #historyTable tbody tr td { vertical-align: middle; }
  </style>
</head>
<body class="app sidebar-mini">
  <?php include "library/sidebar.php"; ?>
  <?php include "library/header.php"; ?>

  <main class="app-content">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-clock-history me-2"></i>Order History</h1>
        <small class="text-muted">Completed &amp; Canceled Orders</small>
      </div>

      <div class="card shadow-sm">
        <div class="card-body p-0">

        <div class="container mt-4 mb-3">
  <h4 class="mb-3"><i class="bi bi-funnel"></i> Filter Orders</h4>
  <form method="GET" class="row g-3 align-items-end">
    
    <!-- Customer Name -->
    <div class="col-md-4">
      <label class="form-label">Customer Name</label>
      <input type="text" name="customer" class="form-control" placeholder="Enter name" value="<?= isset($_GET['customer']) ? htmlspecialchars($_GET['customer']) : '' ?>">
    </div>

    <!-- Status -->
    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="">All</option>
        <option value="Pending" <?= isset($_GET['status']) && $_GET['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
        <option value="In Progress" <?= isset($_GET['status']) && $_GET['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
        <option value="Preparing" <?= isset($_GET['status']) && $_GET['status'] === 'Preparing' ? 'selected' : '' ?>>Preparing</option>
        <option value="Ready" <?= isset($_GET['status']) && $_GET['status'] === 'Ready' ? 'selected' : '' ?>>Ready</option>
        <option value="Completed" <?= isset($_GET['status']) && $_GET['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
        <option value="Canceled" <?= isset($_GET['status']) && $_GET['status'] === 'Canceled' ? 'selected' : '' ?>>Canceled</option>
      </select>
    </div>

    <!-- Date Range -->
    <div class="col-md-2">
      <label class="form-label">From Date</label>
      <input type="date" name="from_date" class="form-control" value="<?= isset($_GET['from_date']) ? htmlspecialchars($_GET['from_date']) : '' ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">To Date</label>
      <input type="date" name="to_date" class="form-control" value="<?= isset($_GET['to_date']) ? htmlspecialchars($_GET['to_date']) : '' ?>">
    </div>

    <!-- Filter Button -->
    <div class="col-md-1">
      <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
    </div>
  </form>
</div>

        
          <table class="table table-hover mb-0" id="historyTable">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Customer</th>
                <th>Type</th>
                <th>Items</th>
                <th>Total ($)</th>
                <th>Status</th>
                <th>Updated At</th>
              </tr>
            </thead>
            <tbody>
              <?php 

$filters = [];
$where = "";

if (!empty($_GET['customer'])) {
    $customer = mysqli_real_escape_string($conn, $_GET['customer']);
    $filters[] = "customer_name LIKE '%$customer%'";
}

if (!empty($_GET['status'])) {
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $filters[] = "status = '$status'";
}

if (!empty($_GET['from_date'])) {
    $from = mysqli_real_escape_string($conn, $_GET['from_date']);
    $filters[] = "DATE(created_at) >= '$from'";
}

if (!empty($_GET['to_date'])) {
    $to = mysqli_real_escape_string($conn, $_GET['to_date']);
    $filters[] = "DATE(created_at) <= '$to'";
}

if (count($filters) > 0) {
    $where = "WHERE " . implode(" AND ", $filters);
}

$sql = "SELECT * FROM orders $where ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);


              $sql = "SELECT * FROM orders WHERE status IN ('Completed','Canceled') ORDER BY updated_at DESC";
              $res = mysqli_query($conn, $sql);
              if (!$res) {
                echo "<tr><td colspan='7' class='text-center text-danger'>Query failed: " . mysqli_error($conn) . "</td></tr>";
              } elseif (mysqli_num_rows($res) === 0) {
                echo "<tr><td colspan='7' class='text-center text-muted'>No completed or canceled orders found.</td></tr>";
              } else {
                $i = 1;
                while ($row = mysqli_fetch_assoc($res)) {
                  // Decode & format items
                  $items_html = '<ul class="list-unstyled mb-0">';
                  $items = json_decode($row['items'], true);
                  if (json_last_error() === JSON_ERROR_NONE && is_array($items)) {
                    foreach ($items as $it) {
                      $n = htmlspecialchars($it['name']);
                      $p = number_format($it['price'], 2);
                      $items_html .= "<li><i class='bi bi-dot'></i> $n <span class='text-muted'>(\$$p)</span></li>";
                    }
                  } else {
                    $items_html .= "<li class='text-danger'>Invalid item data</li>";
                  }
                  $items_html .= '</ul>';

                  // Status badge
                  switch ($row['status']) {
                    case 'Completed': $badge = 'success'; break;
                    case 'Canceled':  $badge = 'danger';  break;
                    default:          $badge = 'secondary';
                  }

                  // Print row
                  echo "<tr>
                          <td>{$i}</td>
                          <td>" . htmlspecialchars($row['customer_name']) . "</td>
                          <td>" . htmlspecialchars($row['order_type']) . "</td>
                          <td>{$items_html}</td>
                          <td>" . number_format($row['total_amount'], 2) . "</td>
                          <td><span class='badge bg-{$badge}'>" . htmlspecialchars($row['status']) . "</span></td>
                          <td>" . date('d M Y, H:i', strtotime($row['updated_at'])) . "</td>
                        </tr>";
                  $i++;
                }
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
