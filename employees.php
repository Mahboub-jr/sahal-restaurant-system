<?php
require_once __DIR__ . '/includes/legacy_guard.php';

include "library/conn.php";

// Show message
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$alert = '';
if ($msg === 'added') $alert = '<div class="alert alert-success">Employee added successfully!</div>';
if ($msg === 'deleted') $alert = '<div class="alert alert-danger">Employee deleted successfully!</div>';
if ($msg === 'updated') $alert = '<div class="alert alert-info">Employee updated successfully!</div>';

// Filter logic
$where = [];

if (!empty($_GET['name'])) {
    $name = mysqli_real_escape_string($conn, $_GET['name']);
    $where[] = "name LIKE '%$name%'";
}
if (!empty($_GET['position'])) {
    $position = mysqli_real_escape_string($conn, $_GET['position']);
    $where[] = "position LIKE '%$position%'";
}
if (!empty($_GET['join_from'])) {
    $join_from = mysqli_real_escape_string($conn, $_GET['join_from']);
    $where[] = "join_date >= '$join_from'";
}
if (!empty($_GET['join_to'])) {
    $join_to = mysqli_real_escape_string($conn, $_GET['join_to']);
    $where[] = "join_date <= '$join_to'";
}
if (!empty($_GET['status'])) {
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $where[] = "status = '$status'";
}

$filter_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$employees = mysqli_query($conn, "SELECT * FROM employees $filter_sql ORDER BY id DESC");

// Add employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
  $name = mysqli_real_escape_string($conn, $_POST['name']);
  $position = mysqli_real_escape_string($conn, $_POST['position']);
  $phone = mysqli_real_escape_string($conn, $_POST['phone']);
  $email = mysqli_real_escape_string($conn, $_POST['email']);
  $status = mysqli_real_escape_string($conn, $_POST['status']);

  $query = "INSERT INTO employees (name, position, phone, email, status, join_date)
            VALUES ('$name', '$position', '$phone', '$email', '$status', CURRENT_DATE)";

  mysqli_query($conn, $query);
  header("Location: employees.php?msg=added");    
  exit();
}

// Delete employee
if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  mysqli_query($conn, "DELETE FROM employees WHERE id = $id");
  header("Location: employees.php?msg=deleted");
  exit();
}

// Update employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
  $id = intval($_POST['employee_id']);
  $name = mysqli_real_escape_string($conn, $_POST['name']);
  $position = mysqli_real_escape_string($conn, $_POST['position']);
  $phone = mysqli_real_escape_string($conn, $_POST['phone']);
  $email = mysqli_real_escape_string($conn, $_POST['email']);
  $status = mysqli_real_escape_string($conn, $_POST['status']);

  $query = "UPDATE employees SET 
              name='$name', 
              position='$position', 
              phone='$phone', 
              email='$email',
              status='$status'
            WHERE id = $id";
  mysqli_query($conn, $query);
  header("Location: employees.php?msg=updated");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Employees</title>
  <link rel="stylesheet" href="css/main.css">
  <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
<?php include "library/header.php"; ?>
<?php include "library/sidebar.php"; ?>

<main class="app-content">
  <div class="app-title">
    <h1><i class="bi bi-person-badge"></i> Employees</h1>
  </div>

  <?= $alert ?>

  <div class="row">
    <!-- Add Employee Form -->
    <div class="col-md-4">
      <div class="card">
        <div class="card-header bg-primary text-white">Add Employee</div>
        <div class="card-body">
          <form method="POST">
            <div class="mb-3">
              <label>Name</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label>Position</label>
              <input type="text" name="position" class="form-control" required>
            </div>
            <div class="mb-3">
              <label>Phone</label>
              <input type="text" name="phone" class="form-control" required>
            </div>
            <div class="mb-3">
              <label>Email</label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
              <label>Status</label>
              <select name="status" class="form-select" required>
                <option>Active</option>
                <option>Inactive</option>
              </select>
            </div>
            <button type="submit" name="add_employee" class="btn btn-primary w-100">Add Employee</button>
          </form>
        </div>
      </div>
    </div>

    <!-- View Employees -->
    <div class="col-md-8">
      <div class="card">
        <div class="card-header bg-info text-white">Employee List</div>
        <div class="card-body table-responsive">
          <form method="GET" class="row g-2 mb-4">
            <div class="col-md-2">
              <input type="text" name="name" class="form-control" placeholder="Name" value="<?= isset($_GET['name']) ? htmlspecialchars($_GET['name']) : '' ?>">
            </div>
            <div class="col-md-2">
              <input type="text" name="position" class="form-control" placeholder="Position" value="<?= isset($_GET['position']) ? htmlspecialchars($_GET['position']) : '' ?>">
            </div>
            <div class="col-md-2">
              <input type="date" name="join_from" class="form-control" value="<?= isset($_GET['join_from']) ? $_GET['join_from'] : '' ?>">
            </div>
            <div class="col-md-2">
              <input type="date" name="join_to" class="form-control" value="<?= isset($_GET['join_to']) ? $_GET['join_to'] : '' ?>">
            </div>
            <div class="col-md-2">
              <select name="status" class="form-select">
                <option value="">All Status</option>
                <option value="Active" <?= (isset($_GET['status']) && $_GET['status'] == 'Active') ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= (isset($_GET['status']) && $_GET['status'] == 'Inactive') ? 'selected' : '' ?>>Inactive</option>
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
                <th>Name</th>
                <th>Position</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php $i = 1; $modals = []; while ($emp = mysqli_fetch_assoc($employees)): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($emp['name']) ?></td>
                <td><?= htmlspecialchars($emp['position']) ?></td>
                <td><?= htmlspecialchars($emp['phone']) ?></td>
                <td><?= htmlspecialchars($emp['email']) ?></td>
                <td><span class="badge bg-<?= $emp['status'] == 'Active' ? 'success' : 'secondary' ?>"><?= $emp['status'] ?></span></td>
                <td>
                  <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $emp['id'] ?>">Edit</button>
                  <a href="?delete=<?= $emp['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure to delete this employee?')">Delete</a>
                </td>
              </tr>
              <?php ob_start(); ?>
              <!-- Edit Modal -->
              <div class="modal fade" id="editModal<?= $emp['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <form method="POST" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Employee</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <input type="hidden" name="employee_id" value="<?= $emp['id'] ?>">
                      <div class="mb-3">
                        <label>Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($emp['name']) ?>" class="form-control" required>
                      </div>
                      <div class="mb-3">
                        <label>Position</label>
                        <input type="text" name="position" value="<?= htmlspecialchars($emp['position']) ?>" class="form-control" required>
                      </div>
                      <div class="mb-3">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($emp['phone']) ?>" class="form-control" required>
                      </div>
                      <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($emp['email']) ?>" class="form-control" required>
                      </div>
                      <div class="mb-3">
                        <label>Status</label>
                        <select name="status" class="form-select">
                          <option <?= $emp['status'] == 'Active' ? 'selected' : '' ?>>Active</option>
                          <option <?= $emp['status'] == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="submit" name="update_employee" class="btn btn-success">Save Changes</button>
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                  </form>
                </div>
              </div>
              <?php $modals[] = ob_get_clean(); ?>
              <?php endwhile; ?>
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
