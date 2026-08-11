<?php
require_once __DIR__ . '/includes/legacy_guard.php';

include "library/conn.php";

// Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
  $name = mysqli_real_escape_string($conn, $_POST['name']);
  $email = mysqli_real_escape_string($conn, $_POST['email']);
  $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // secure hashing
  $role = mysqli_real_escape_string($conn, $_POST['role']);

  $query = "INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$password', '$role')";
  mysqli_query($conn, $query);
  header("Location: users.php?msg=added");
  exit();
}

// Delete User
if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  mysqli_query($conn, "DELETE FROM users WHERE id = $id");
  header("Location: users.php?msg=deleted");
  exit();
}

// Update User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
  $id = intval($_POST['user_id']);
  $name = mysqli_real_escape_string($conn, $_POST['name']);
  $email = mysqli_real_escape_string($conn, $_POST['email']);
  $role = mysqli_real_escape_string($conn, $_POST['role']);

  $updateQuery = "UPDATE users SET name='$name', email='$email', role='$role' WHERE id = $id";
  mysqli_query($conn, $updateQuery);
  header("Location: users.php?msg=updated");
  exit();
}

$users = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Users</title>
  <link rel="stylesheet" href="css/main.css">
  <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
<?php include "library/header.php"; ?>
<?php include "library/sidebar.php"; ?>

<main class="app-content">
  <div class="app-title">
    <h1><i class="bi bi-people-fill"></i> User Management</h1>
  </div>

  <div class="row">
    <!-- Add User Form -->
    <div class="col-md-4">
      <div class="card">
        <div class="card-header bg-primary text-white">Add User</div>
        <div class="card-body">
          <form method="POST">
            <div class="mb-3">
              <label>Name</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label>Email</label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
              <label>Password</label>
              <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3">
              <label>Role</label>
              <select name="role" class="form-select" required>
                <option value="admin">Admin</option>
                <option value="waiter">Waiter</option>
              </select>
            </div>
            <button type="submit" name="add_user" class="btn btn-primary w-100">Add User</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Users Table -->
    <div class="col-md-8">
      <div class="card">
        <div class="card-header bg-info text-white">User List</div>
        <div class="card-body table-responsive">
          <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php $i = 1; while ($user = mysqli_fetch_assoc($users)): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($user['name']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><span class="badge bg-<?= $user['role'] == 'admin' ? 'primary' : 'secondary' ?>"><?= $user['role'] ?></span></td>
                <td>
                  <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $user['id'] ?>">Edit</button>
                  <a href="?delete=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure to delete this user?')">Delete</a>
                </td>
              </tr>

              <!-- Edit Modal -->
              <div class="modal fade" id="editModal<?= $user['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <form method="POST" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit User</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                      <div class="mb-3">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                      </div>
                      <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                      </div>
                      <div class="mb-3">
                        <label>Role</label>
                        <select name="role" class="form-select">
                          <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                          <option value="waiter" <?= $user['role'] == 'waiter' ? 'selected' : '' ?>>Waiter</option>
                        </select>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="submit" name="update_user" class="btn btn-success">Save</button>
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
