<?php
include "library/conn.php";

// Handle Delete
if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  mysqli_query($conn, "DELETE FROM customers WHERE id = $id");
  header("Location: customers.php");
  exit;
}

// Handle Edit
$editMode = false;
$editData = ['id' => '', 'name' => '', 'phone' => '', 'email' => '', 'address' => ''];
if (isset($_GET['edit'])) {
  $id = intval($_GET['edit']);
  $result = mysqli_query($conn, "SELECT * FROM customers WHERE id = $id");
  if (mysqli_num_rows($result) == 1) {
    $editMode = true;
    $editData = mysqli_fetch_assoc($result);
  }
}

// Handle Add/Update Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $name = mysqli_real_escape_string($conn, $_POST['name']);
  $phone = mysqli_real_escape_string($conn, $_POST['phone']);
  $email = mysqli_real_escape_string($conn, $_POST['email']);
  $address = mysqli_real_escape_string($conn, $_POST['address']);

  if (isset($_POST['id']) && $_POST['id'] != '') {
    // Update
    $id = intval($_POST['id']);
    $update = "UPDATE customers SET name='$name', phone='$phone', email='$email', address='$address' WHERE id=$id";
    mysqli_query($conn, $update);
  } else {
    // Insert
    $insert = "INSERT INTO customers (name, phone, email, address) VALUES ('$name', '$phone', '$email', '$address')";
    mysqli_query($conn, $insert);
  }

  header("Location: customers.php");
  exit;
}

// Fetch customers to display
$customers = mysqli_query($conn, "SELECT * FROM customers ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Customers</title>
  <link rel="stylesheet" href="css/main.css">
  <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
<?php include "library/sidebar.php"; ?>
<?php include "library/header.php"; ?>

<main class="app-content">
  <div class="app-title">
    <div>
      <h1><i class="bi bi-people"></i> Customers</h1>
      <p>Add, update, and manage customers</p>
    </div>
  </div>

  <div class="row">
    <!-- Add/Update Customer Form -->
    <div class="col-md-5">
      <div class="card">
        <div class="card-header bg-primary text-white"><?= $editMode ? 'Update Customer' : 'Add Customer' ?></div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="id" value="<?= htmlspecialchars($editData['id']) ?>">
            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($editData['name']) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editData['phone']) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editData['email']) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($editData['address']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-<?= $editMode ? 'warning' : 'primary' ?>">
              <?= $editMode ? 'Update' : 'Add' ?> Customer
            </button>
            <?php if ($editMode): ?>
              <a href="customers.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>

    <!-- Customer List Table -->
    <div class="col-md-7">
      <div class="card">
        <div class="card-header bg-info text-white">Customer List</div>
        <div class="card-body table-responsive">
          <table class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Address</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $count = 1;
              while ($row = mysqli_fetch_assoc($customers)) {
                echo "<tr>
                  <td>{$count}</td>
                  <td>".htmlspecialchars($row['name'])."</td>
                  <td>".htmlspecialchars($row['phone'])."</td>
                  <td>".htmlspecialchars($row['email'])."</td>
                  <td>".htmlspecialchars($row['address'])."</td>
                  <td>".date('d M Y', strtotime($row['created_at']))."</td>
                  <td>
                    <a href='customers.php?edit={$row['id']}' class='btn btn-sm btn-warning'>Edit</a>
                    <a href='customers.php?delete={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Delete this customer?\")'>Delete</a>
                  </td>
                </tr>";
                $count++;
              }
              ?>
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
