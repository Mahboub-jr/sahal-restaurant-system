<?php
require_once __DIR__ . '/includes/legacy_guard.php';

include "library/conn.php";
require_once "library/tcpdf/TCPDF-main/tcpdf.php"; // For PDF export
// Filter logic
$filter_role = isset($_GET['role']) ? mysqli_real_escape_string($conn, $_GET['role']) : '';

// Fetch users based on role filter
if ($filter_role && in_array($filter_role, ['admin', 'waiter'])) {
  $query = "SELECT * FROM users WHERE role = '$filter_role' ORDER BY id DESC";
} else {
  $query = "SELECT * FROM users ORDER BY id DESC";
}
$users = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>User Roles</title>
  <link rel="stylesheet" href="css/main.css">
  <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
<?php include "library/header.php"; ?>
<?php include "library/sidebar.php"; ?>

<main class="app-content">
  <div class="app-title">
    <h1><i class="bi bi-person-badge-fill"></i> User Roles</h1>
  </div>

  <!-- Filter Bar -->
  <div class="row mb-3">
    <div class="col-md-6">
      <form method="GET" class="d-flex gap-2">
        <select name="role" class="form-select" onchange="this.form.submit()">
          <option value="">-- Filter by Role --</option>
          <option value="admin" <?= $filter_role == 'admin' ? 'selected' : '' ?>>Admin</option>
          <option value="waiter" <?= $filter_role == 'waiter' ? 'selected' : '' ?>>Waiter</option>
        </select>
        <?php if ($filter_role): ?>
          <a href="user_roles.php" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Roles Table -->

  <div class="mb-3">
  <a href="export_user_roles.php?format=excel<?= $filter_role ? '&role=' . $filter_role : '' ?>" class="btn btn-success btn-sm">
    <i class="bi bi-file-earmark-excel"></i> Export to Excel
  </a>
  <a href="export_user_roles.php?format=pdf<?= $filter_role ? '&role=' . $filter_role : '' ?>" class="btn btn-danger btn-sm">
    <i class="bi bi-file-earmark-pdf"></i> Export to PDF
  </a>
</div>

  <div class="card">
    <div class="card-header bg-info text-white">User Role List</div>
    <div class="card-body table-responsive">
      <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; while ($user = mysqli_fetch_assoc($users)): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($user['name']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td><span class="badge bg-<?= $user['role'] == 'admin' ? 'primary' : 'secondary' ?>"><?= $user['role'] ?></span></td>
          </tr>
          <?php endwhile; ?>
          <?php if (mysqli_num_rows($users) == 0): ?>
          <tr>
            <td colspan="4" class="text-center text-muted">No users found for selected role.</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<?php include "library/footer.php"; ?>
<?php include "library/script.php"; ?>
</body>
</html>
