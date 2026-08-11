<?php
include "library/conn.php";

// 1. Determine filter
$validStatuses = ['Pending','Preparing','Ready','Completed','Cancelled'];
$filter = $_GET['status'] ?? null;
if ($filter && in_array($filter, $validStatuses, true)) {
    
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
    

    $sql = "SELECT * FROM orders WHERE status = '" . mysqli_real_escape_string($conn,$filter) . "'";
} else {
    // Default: show all except Completed (you can adjust as needed)
    $sql = "SELECT * FROM orders WHERE status NOT IN ('Completed')";
}

$orders = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" href="css/main.css">
  <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
  <?php include "library/sidebar.php"; ?>
  <?php include "library/header.php"; ?>

<main class="app-content">
  <div class="app-title">
    <div>
      <h1><i class="bi bi-receipt-cutoff"></i> Orders</h1>
      <p><?= $filter ? "Filtered by “{$filter}”" : "Manage all active orders" ?></p>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-md-12">
      <a href="orders.php" class="btn btn-outline-secondary<?= $filter?'':' d-none' ?>">Clear Filter</a>
    </div>
  </div>

  <div class="tile">
    <div class="tile-body">

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
        <option value="Cancelled" <?= isset($_GET['status']) && $_GET['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
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
      <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search">Filter</i></button>
    </div>
  </form>
</div>


      <table class="table table-hover table-bordered" id="ordersTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Items</th>
            <th>Total</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $count = 1;
        while ($row = mysqli_fetch_assoc($orders)) {
          // Items
          $items_html = '<ul class="list-unstyled mb-0">';
          $items = json_decode($row['items'], true);
          if (json_last_error()===JSON_ERROR_NONE && is_array($items)) {
            foreach ($items as $it) {
              $n = htmlspecialchars($it['name']);
              $p = number_format($it['price'],2);
              $items_html .= "<li><i class='bi bi-dot'></i> $n <span class='text-muted'>(\$$p)</span></li>";
            }
          } else {
            $items_html .= "<li class='text-danger'>Invalid item data</li>";
          }
          $items_html .= '</ul>';

          // Badge
          switch ($row['status']) {
            case 'Pending':   $b='secondary'; break;
            case 'Preparing': $b='warning';   break;
            case 'Ready':     $b='info';      break;
            case 'Completed': $b='success';   break;
            case 'Cancelled': $b='danger';    break;
            default:          $b='dark';
          }

          echo "<tr>
                  <td>{$count}</td>
                  <td>".htmlspecialchars($row['customer_name'])."</td>
                  <td>".htmlspecialchars($row['order_type'])."</td>
                  <td>{$items_html}</td>
                  <td>$".number_format($row['total_amount'],2)."</td>
                  <td><span class='badge bg-{$b}'>".htmlspecialchars($row['status'])."</span></td>
                  <td>
                    <a href='update_order.php?id={$row['id']}' class='btn btn-sm btn-info mb-1'>Update</a>
                    <a href='complete_order.php?id={$row['id']}' class='btn btn-sm btn-success mb-1'>Complete</a>
                    <a href='cancel_order.php?id={$row['id']}' class='btn btn-sm btn-danger mb-1'
                       onclick='return confirm(\"Cancel this order?\")'>Cancel</a>
                    <a href='receipt.php?id={$row['id'] }' class='btn btn-sm btn-outline-primary mb-1'>
                       <i class='bi bi-receipt'></i> Receipt</a>
                    
                  </td>
                </tr>";
          $count++;
        }
        if ($count === 1) {
          echo "<tr><td colspan='7' class='text-center text-muted'>No orders found.</td></tr>";
        }
        ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<?php include "library/footer.php"; ?>
<?php include "library/script.php"; ?>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
