<?php
include "library/conn.php";

// Handle Add Table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_table'])) {
  $number = mysqli_real_escape_string($conn, $_POST['table_number']);
  $capacity = intval($_POST['capacity']);
  $status = mysqli_real_escape_string($conn, $_POST['status']);
  mysqli_query($conn, "INSERT INTO tables (table_number, capacity, status) VALUES ('$number', $capacity, '$status')");
  header("Location: tables.php?msg=added");
  exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  mysqli_query($conn, "DELETE FROM tables WHERE id = $id");
  header("Location: tables.php?msg=deleted");
  exit();
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_table'])) {
  $id = intval($_POST['id']);
  $number = mysqli_real_escape_string($conn, $_POST['table_number']);
  $capacity = intval($_POST['capacity']);
  $status = mysqli_real_escape_string($conn, $_POST['status']);
  mysqli_query($conn, "UPDATE tables SET table_number='$number', capacity=$capacity, status='$status' WHERE id=$id");
  header("Location: tables.php?msg=updated");
  exit();
}

// Get all tables
$tables = mysqli_query($conn, "SELECT * FROM tables ORDER BY id DESC");
?>



//session_start();
//if (!isset($_SESSION['user_id'])) {
  //header("Location: login.php"); // Redirect to login if not logged in
  //exit();
//}




<!DOCTYPE html>
<html lang="en">
<head>
  <title>Table Reservations</title>
  <link rel="stylesheet" href="css/main.css">
  <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
<?php include "library/sidebar.php"; ?>
<?php include "library/header.php"; ?>

<main class="app-content">
  <div class="app-title">
    <div>
      <h1><i class="bi bi-table"></i> Manage Tables</h1>
      <p>Reservation Table Management</p>
    </div>
  </div>

  <!-- Add Table Form -->
  <div class="row">
    <div class="col-md-5">
      <div class="card">
        <div class="card-header bg-primary text-white">Add New Table</div>
        <div class="card-body">
          <form method="POST">
            <div class="mb-3">
              <label class="form-label">Table Number</label>
              <input type="text" name="table_number" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Capacity</label>
              <input type="number" name="capacity" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Status</label>
              <select name="status" class="form-select" required>
                <option>Available</option>
                <option>Reserved</option>
                <option>Occupied</option>
              </select>
            </div>
            <button type="submit" name="add_table" class="btn btn-success">Add Table</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Table List -->
    <div class="col-md-7">
      <div class="card">
        <div class="card-header bg-info text-white">Tables List</div>
        <div class="card-body table-responsive">
          <table class="table table-striped table-hover">
            <thead>
              <tr>
                <th>#</th>
                <th>Table No</th>
                <th>Capacity</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($tables)) { ?>
  <tr>
    <td><?= $row['id'] ?></td>
    <td><?= htmlspecialchars($row['table_number']) ?></td>
    <td><?= $row['capacity'] ?></td>
    <td><?= $row['status'] ?></td>
    <td>
      <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">Edit</button>
      <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this table?')">Delete</a>
    </td>
  </tr>
  <?php ob_start(); ?>
  <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
      <form method="POST" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Table #<?= $row['id'] ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="<?= $row['id'] ?>">
          <div class="mb-3">
            <label>Table Number</label>
            <input type="text" name="table_number" value="<?= htmlspecialchars($row['table_number']) ?>" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>Capacity</label>
            <input type="number" name="capacity" value="<?= $row['capacity'] ?>" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>Status</label>
            <select name="status" class="form-select">
              <option <?= $row['status'] === 'Available' ? 'selected' : '' ?>>Available</option>
              <option <?= $row['status'] === 'Reserved' ? 'selected' : '' ?>>Reserved</option>
              <option <?= $row['status'] === 'Occupied' ? 'selected' : '' ?>>Occupied</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" name="update_table" class="btn btn-primary">Update</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
  <?php $modals[] = ob_get_clean(); ?>
<?php } ?>

            </tbody>
          </table>
          <?php foreach ($modals as $modal) echo $modal; ?>

        </div>
      </div>
    </div>
  </div>
</main>

<?php include "library/footer.php"; ?>
<?php include "library/script.php"; ?>
</body>
</html>
