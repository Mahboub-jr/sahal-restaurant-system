<?php
require_once __DIR__ . '/includes/legacy_guard.php';

include "library/conn.php";

// Filters
$filters = [];
$payment_filter = $_GET['payment'] ?? '';
$staff_filter   = $_GET['staff'] ?? '';
$customer_filter = $_GET['customer'] ?? '';
$status_filter = $_GET['status'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

if ($payment_filter) {
  $filters[] = "payment_method = '" . mysqli_real_escape_string($conn, $payment_filter) . "'";
}
if ($staff_filter) {
  $filters[] = "served_by = '" . mysqli_real_escape_string($conn, $staff_filter) . "'";
}
if ($customer_filter) {
  $filters[] = "customer_name LIKE '%" . mysqli_real_escape_string($conn, $customer_filter) . "%'";
}
if ($status_filter) {
  $filters[] = "status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}
if ($from_date) {
  $filters[] = "DATE(created_at) >= '" . mysqli_real_escape_string($conn, $from_date) . "'";
}
if ($to_date) {
  $filters[] = "DATE(created_at) <= '" . mysqli_real_escape_string($conn, $to_date) . "'";
}

$where = count($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';
$query = "SELECT * FROM orders $where ORDER BY created_at DESC";

$result = mysqli_query($conn, $query);

// Staff list
$staffs = mysqli_query($conn, "SELECT DISTINCT served_by FROM orders WHERE served_by IS NOT NULL");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Sales Report</title>
  <link rel="stylesheet" href="css/main.css">
  <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
<?php include "library/header.php"; ?>
<?php include "library/sidebar.php"; ?>

<main class="app-content">
  <div class="app-title">
    <h1><i class="bi bi-graph-up"></i> Sales Report</h1>
  </div>

  <!-- Filters -->
  <form method="GET" class="row g-2 mb-4">
    <div class="col-md-3">
      <input type="text" name="customer" class="form-control" placeholder="Customer Name" value="<?= htmlspecialchars($customer_filter) ?>">
    </div>
    <div class="col-md-2">
      <select name="status" class="form-select">
        <option value="">-- Status --</option>
        <?php
        $statuses = ['Pending','Preparing','Ready','Completed','Cancelled'];
        foreach ($statuses as $status) {
          $sel = ($status_filter == $status) ? 'selected' : '';
          echo "<option value='$status' $sel>$status</option>";
        }
        ?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="payment" class="form-select">
        <option value="">-- Payment --</option>
        <option value="cash" <?= $payment_filter == 'cash' ? 'selected' : '' ?>>Cash</option>
        <option value="card" <?= $payment_filter == 'card' ? 'selected' : '' ?>>Card</option>
        <option value="transfer" <?= $payment_filter == 'transfer' ? 'selected' : '' ?>>Transfer</option>
      </select>
    </div>
    <div class="col-md-2">
      <select name="staff" class="form-select">
        <option value="">-- Staff --</option>
        <?php while ($staff = mysqli_fetch_assoc($staffs)): ?>
          <option value="<?= $staff['served_by'] ?>" <?= $staff_filter == $staff['served_by'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($staff['served_by']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-md-2">
      <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
    </div>
    <div class="col-md-2">
      <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
    </div>
    <div class="col-md-1">
      <button class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
    </div>
    <div class="col-md-1">
      <a href="reports.php" class="btn btn-secondary w-100">Reset</a>
    </div>
  </form>

  <!-- Export Buttons -->
  <div class="mb-3">
    <a href="export_report.php?type=sales&format=excel&payment=<?= $payment_filter ?>&staff=<?= $staff_filter ?>&customer=<?= $customer_filter ?>&status=<?= $status_filter ?>&from_date=<?= $from_date ?>&to_date=<?= $to_date ?>" class="btn btn-success btn-sm">
      <i class="bi bi-file-earmark-excel"></i> Export Excel
    </a>
    <a href="export_report.php?type=sales&format=pdf&payment=<?= $payment_filter ?>&staff=<?= $staff_filter ?>&customer=<?= $customer_filter ?>&status=<?= $status_filter ?>&from_date=<?= $from_date ?>&to_date=<?= $to_date ?>" class="btn btn-danger btn-sm">
      <i class="bi bi-file-earmark-pdf"></i> Export PDF
    </a>
  </div>

  <!-- Sales Table -->
  <div class="card">
    <div class="card-header bg-primary text-white">Sales Data</div>
    <div class="card-body table-responsive">
      <table class="table table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Staff</th>
          </tr>
        </thead>
        <tbody>
        <?php
$i = 1;
$total = 0;
if (mysqli_num_rows($result) > 0):
  while ($row = mysqli_fetch_assoc($result)):
?>
<tr>
  <td><?= $i++ ?></td>
  <td>#<?= $row['id'] ?></td>
  <td><?= htmlspecialchars($row['customer_name']) ?></td>
  <td><?= date('Y-m-d', strtotime($row['created_at'])) ?></td>
  <td>$<?= number_format($row['total_amount'], 2) ?></td>
  <td><span class="badge bg-<?= getStatusColor($row['status']) ?>"><?= $row['status'] ?></span></td>
  <td><?= ucfirst($row['payment_method']) ?></td>
  <td><?= htmlspecialchars($row['served_by']) ?></td>
</tr>
<?php
  $total += $row['total_amount'];
  endwhile;
else:
?>
<tr>
  <td colspan="8" class="text-center text-muted">No sales data found for this filter.</td>
</tr>
<?php endif; ?>

        </tbody>
        <tfoot>
          <tr>
            <th colspan="4" class="text-end">Total:</th>
            <th>$<?= number_format($total, 2) ?></th>
            <th colspan="3"></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</main>

<?php
// helper for status color
function getStatusColor($status) {
  return match($status) {
    'Pending' => 'secondary',
    'Preparing' => 'warning',
    'Ready' => 'info',
    'Completed' => 'success',
    'Cancelled' => 'danger',
    default => 'dark'
  };
}
?>

<?php include "library/footer.php"; ?>
<?php include "library/script.php"; ?>
</body>
</html>
