
<?php
include "library/conn.php";
$upload_dir = "uploads/";
$_SESSION['user_role'] = $user['role'];
// Handle deletion
if (isset($_POST['delete_id'])) {
  $delete_id = intval($_POST['delete_id']);

  // Fetch and delete image file
  $result = mysqli_query($conn, "SELECT food_image FROM menu_items WHERE id = $delete_id");
  $row = mysqli_fetch_assoc($result);
  if ($row && !empty($row['food_image'])) {
    $image_path = $upload_dir . $row['food_image'];
    if (file_exists($image_path)) {
      unlink($image_path); // Delete image file
    }
  }

  // Delete item from database
  mysqli_query($conn, "DELETE FROM menu_items WHERE id = $delete_id");
  header("Location: menu.php");
  exit();
}

// Handle insertion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['name'])) {
  $name = mysqli_real_escape_string($conn, $_POST['name']);
  $category_id = intval($_POST['category_id']);
  $price = floatval($_POST['price']);
  $description = mysqli_real_escape_string($conn, $_POST['description']);

  $image_name = '';

  // If an image is uploaded
  if (isset($_FILES['food_image']) && $_FILES['food_image']['error'] === 0) {
    $tmp_name = $_FILES['food_image']['tmp_name'];
    $original_name = basename($_FILES['food_image']['name']);
    $image_name = time() . '_' . $original_name;

    if (!move_uploaded_file($tmp_name, $upload_dir . $image_name)) {
      echo "<div class='alert alert-danger'>Failed to upload image.</div>";
    }
  }

  $insert = "INSERT INTO menu_items (name, category_id, price, description, food_image) 
             VALUES ('$name', $category_id, '$price', '$description', '$image_name')";

  if (mysqli_query($conn, $insert)) {
    header("Location: menu.php");
    exit();
  } else {
    echo "<div class='alert alert-danger'>Error saving to database: " . mysqli_error($conn) . "</div>";
  }
}
?>
<?php if ($_SESSION['user_role'] === 'admin'): ?>
  <!-- This block will only be shown to admins -->
  <button>Add Employee</button>
  <a href="?delete=123">Delete</a>
<?php endif; ?>

<!DOCTYPE html>
<html>
<head>
  <title>Menu Items</title>
  <link rel="stylesheet" href="css/main.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">

<?php include "library/sidebar.php"; ?>
<?php include "library/header.php"; ?>

<main class="app-content">
  <div class="container-fluid mt-4">
    <div class="row">
      <!-- Form Section -->
      <div class="col-md-5">
        <div class="card">
          <div class="card-header bg-primary text-white">Add Menu Item</div>
          <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
              <div class="mb-3">
                <label class="form-label">Food Name</label>
                <input type="text" name="name" class="form-control" required>
              </div>

              <div class="mb-3">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-select" required>
                  <option value="">Select Category</option>
                  <?php
                  $categories = mysqli_query($conn, "SELECT * FROM categories");
                  while ($cat = mysqli_fetch_assoc($categories)) {
                    echo "<option value='{$cat['id']}'>{$cat['name']}</option>";
                  }
                  ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Price ($)</label>
                <input type="number" name="price" class="form-control" step="0.01" required>
              </div>

              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2" required></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label">Food Image</label>
                <input type="file" name="food_image" class="form-control" accept="image/*">
              </div>

              <button type="submit" class="btn btn-success w-100">Add Item</button>
            </form>
          </div>
        </div>
      </div>

      <!-- Display Section -->
      <div class="col-md-7">
        <div class="card">
          <div class="card-header bg-dark text-white">Menu Items</div>
          <div class="card-body table-responsive">
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Name</th>
                  <th>Category</th>
                  <th>Price</th>
                  <th>Image</th>
                  <th>Description</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $items = mysqli_query($conn, "SELECT m.*, c.name as category FROM menu_items m JOIN categories c ON m.category_id = c.id ORDER BY m.id DESC");
                $sn = 1;
                while ($item = mysqli_fetch_assoc($items)) {
                  echo "<tr>
                    <td>{$sn}</td>
                    <td>{$item['name']}</td>
                    <td>{$item['category']}</td>
                    <td>$" . number_format($item['price'], 2) . "</td>
                    <td>";
                      if (!empty($item['food_image'])) {
                        echo "<img src='{$upload_dir}{$item['food_image']}' width='60' height='60' style='object-fit:cover;'>";
                      } else {
                        echo "No Image";
                      }
                  echo "</td>
                    <td>{$item['description']}</td>
                    <td>
                      <form method='POST' onsubmit='return confirm(\"Are you sure you want to delete this item?\");'>
                        <input type='hidden' name='delete_id' value='{$item['id']}'>
                        <button type='submit' class='btn btn-danger btn-sm'>Delete</button>
                      </form>
                    </td>
                  </tr>";
                  $sn++;
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include "library/footer.php"; ?>
<?php include "library/script.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
