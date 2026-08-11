<?php
require_once __DIR__ . '/includes/legacy_guard.php';

include "library/conn.php";

// Handle Add Booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_booking'])) {
    $customer_id = intval($_POST['customer_id']);
    $table_id = intval($_POST['table_id']);
    $booking_time = mysqli_real_escape_string($conn, $_POST['booking_time']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $query = "INSERT INTO table_bookings (customer_id, table_id, booking_time, status) 
              VALUES ($customer_id, $table_id, '$booking_time', '$status')";
    mysqli_query($conn, $query);
    header("Location: table_booking.php?msg=added");
    exit();
}

// Handle Delete Booking
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM table_bookings WHERE id = $id");
    header("Location: table_booking.php?msg=deleted");
    exit();
}

// Handle Update Booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking'])) {
    $id = intval($_POST['booking_id']);
    $customer_id = intval($_POST['customer_id']);
    $table_id = intval($_POST['table_id']);
    $booking_time = mysqli_real_escape_string($conn, $_POST['booking_time']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $update = "UPDATE table_bookings 
               SET customer_id=$customer_id, table_id=$table_id, booking_time='$booking_time', status='$status' 
               WHERE id=$id";
    mysqli_query($conn, $update);
    header("Location: table_booking.php?msg=updated");
    exit();
}

// Fetch bookings with joined names
$bookings = mysqli_query($conn, "
    SELECT b.*, c.name AS customer_name, t.table_number 
    FROM table_bookings b
    JOIN customers c ON b.customer_id = c.id
    JOIN tables t ON b.table_id = t.id
    ORDER BY b.booking_time DESC
");

$customers = mysqli_query($conn, "SELECT id, name FROM customers");
$tables = mysqli_query($conn, "SELECT id, table_number FROM tables");
?>



<!DOCTYPE html>
<html lang="en">
<head>
  <title>Table Bookings</title>
  <link rel="stylesheet" href="css/main.css">
  <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
<?php include "library/header.php"; ?>
<?php include "library/sidebar.php"; ?>

<main class="app-content">
  <div class="app-title">
    <h1><i class="bi bi-calendar-check"></i> Table Bookings</h1>
  </div>

  <div class="row">
    <!-- Add Booking Form -->
    <div class="col-md-4">
      <div class="card">
        <div class="card-header bg-primary text-white">Add Booking</div>
        <div class="card-body">
          <form method="POST">
            <div class="mb-3">
              <label>Customer</label>
              <select name="customer_id" class="form-select" required>
                <option value="">-- Select Customer --</option>
                <?php while ($c = mysqli_fetch_assoc($customers)): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="mb-3">
              <label>Table</label>
              <select name="table_id" class="form-select" required>
                <option value="">-- Select Table --</option>
                <?php while ($t = mysqli_fetch_assoc($tables)): ?>
                  <option value="<?= $t['id'] ?>">Table <?= htmlspecialchars($t['table_number']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="mb-3">
              <label>Booking Time</label>
              <input type="datetime-local" name="booking_time" class="form-control" required>
            </div>
            <div class="mb-3">
              <label>Status</label>
              <select name="status" class="form-select" required>
                <option>Booked</option>
                <option>Seated</option>
                <option>Cancelled</option>
              </select>
            </div>
            <button type="submit" name="add_booking" class="btn btn-primary">Add Booking</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Booking List -->
    <div class="col-md-8">
      <div class="card">
        <div class="card-header bg-info text-white">All Bookings</div>
        <div class="card-body table-responsive">

        <form method="GET" class="row g-2 mb-4">
  <div class="col-md-4">
    <input type="text" name="customer" class="form-control" placeholder="Search by customer name" value="<?= isset($_GET['customer']) ? htmlspecialchars($_GET['customer']) : '' ?>">
  </div>

  <div class="col-md-3">
    <select name="table" class="form-select">
      <option value="">All Tables</option>
      <?php
        $tables = mysqli_query($conn, "SELECT id, table_number FROM tables");
        while ($t = mysqli_fetch_assoc($tables)) {
          $selected = (isset($_GET['table']) && $_GET['table'] == $t['id']) ? 'selected' : '';
          echo "<option value='{$t['id']}' $selected>{$t['table_number']}</option>";
        }
      ?>
    </select>
  </div>

  <div class="col-md-3">
    <select name="status" class="form-select">
      <option value="">All Statuses</option>
      <option <?= isset($_GET['status']) && $_GET['status'] == 'Booked' ? 'selected' : '' ?>>Booked</option>
      <option <?= isset($_GET['status']) && $_GET['status'] == 'Seated' ? 'selected' : '' ?>>Seated</option>
      <option <?= isset($_GET['status']) && $_GET['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
    </select>
  </div>
  <div class="col-md-2 d-grid">
    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
  </div>
</form>
          <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Customer</th>
                <th>Table</th>
                <th>Time</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php $i = 1; while ($row = mysqli_fetch_assoc($bookings)): ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><?= htmlspecialchars($row['customer_name']) ?></td>
                  <td><?= htmlspecialchars($row['table_number']) ?></td>
                  <td><?= htmlspecialchars($row['booking_time']) ?></td>
                  <td><span class="badge bg-<?= 
                        $row['status'] == 'Booked' ? 'primary' : 
                        ($row['status'] == 'Seated' ? 'success' : 'danger') ?>">
                        <?= $row['status'] ?></span></td>
                  <td>
                    <!-- Edit Button triggers modal -->
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">Edit</button>
                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete booking?')">Delete</a>
                    <a href='receipt_booking.php?id=<?= $row['id'] ?>' class='btn btn-sm btn-outline-primary'>
                      <i class='bi bi-receipt'></i> Receipt</a>

                  </td>
                </tr>

                <!-- Edit Modal -->
                <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <form method="POST" class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Edit Booking</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <input type="hidden" name="booking_id" value="<?= $row['id'] ?>">
                        <div class="mb-3">
                          <label>Customer</label>
                          <select name="customer_id" class="form-select" required>
                            <?php
                            $custList = mysqli_query($conn, "SELECT id, name FROM customers");
                            while ($c = mysqli_fetch_assoc($custList)) {
                              $selected = $c['id'] == $row['customer_id'] ? 'selected' : '';
                              echo "<option value='{$c['id']}' $selected>".htmlspecialchars($c['name'])."</option>";
                            }
                            ?>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label>Table</label>
                          <select name="table_id" class="form-select" required>
                            <?php
                            $tableList = mysqli_query($conn, "SELECT id, table_number FROM tables");
                            while ($t = mysqli_fetch_assoc($tableList)) {
                              $selected = $t['id'] == $row['table_id'] ? 'selected' : '';
                              echo "<option value='{$t['id']}' $selected>Table ".htmlspecialchars($t['table_number'])."</option>";
                            }
                            ?>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label>Booking Time</label>
                          <input type="datetime-local" name="booking_time" value="<?= date('Y-m-d\TH:i', strtotime($row['booking_time'])) ?>" class="form-control" required>
                        </div>
                        <div class="mb-3">
                          <label>Status</label>
                          <select name="status" class="form-select" required>
                            <option <?= $row['status'] == 'Booked' ? 'selected' : '' ?>>Booked</option>
                            <option <?= $row['status'] == 'Seated' ? 'selected' : '' ?>>Seated</option>
                            <option <?= $row['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                          </select>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="submit" name="update_booking" class="btn btn-success">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      </div>
                    </form>
                  </div>
                </div>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include "library/footer.php"; ?>
<?php include "library/script.php"; ?>
</body>
</html>
