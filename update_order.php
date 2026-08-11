<?php
include "library/conn.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header("Location: orders.php?msg=invalid_id");
  exit();
}

$id = intval($_GET['id']);
$res = mysqli_query($conn, "SELECT * FROM orders WHERE id = $id");
$order = mysqli_fetch_assoc($res);

if (!$order) {
  echo "Order not found.";
  exit();
}

// Decode items JSON
$decoded_items = json_decode($order['items'], true);

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $type   = mysqli_real_escape_string($conn, $_POST['order_type']);
  $status = mysqli_real_escape_string($conn, $_POST['status']);
  $total  = floatval($_POST['total_amount']);

  $items = [];
  foreach ($_POST['item_name'] as $i => $name) {
    $items[] = [
      'name'  => mysqli_real_escape_string($conn, $name),
      'price' => floatval($_POST['item_price'][$i])
    ];
  }
  $items_json = json_encode($items);

  $sql = "UPDATE orders SET
            order_type    = '$type',
            items         = '$items_json',
            total_amount  = $total,
            status        = '$status'
          WHERE id = $id";

  if (mysqli_query($conn, $sql)) {
    header("Location: orders.php?msg=updated");
    exit();
  } else {
    echo "<div class='alert alert-danger'>Update failed: " . mysqli_error($conn) . "</div>";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Update Order</title>
  <link rel="stylesheet" href="css/main.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
  <?php include "library/sidebar.php"; ?>
  <?php include "library/header.php"; ?>

  <main class="app-content">
    <div class="container-fluid">
      <div class="card shadow-sm">
        <div class="card-header">
          <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Update Order #<?= $order['id'] ?></h5>
        </div>
        <div class="card-body">
          <form method="POST" class="row g-3">
            <!-- Customer (read-only) -->
            <div class="col-md-6">
              <label class="form-label">Customer Name</label>
              <input type="text" class="form-control" value="<?= htmlspecialchars($order['customer_name']) ?>" readonly>
            </div>
            <!-- Order Type -->
            <div class="col-md-6">
              <label class="form-label">Order Type</label>
              <select name="order_type" class="form-select" required>
                <?php 
                foreach (['Dine-In','Takeaway','Delivery'] as $opt) {
                  $sel = $order['order_type'] === $opt ? 'selected' : '';
                  echo "<option value='$opt' $sel>$opt</option>";
                }
                ?>
              </select>
            </div>
            <!-- Items -->
            <div class="col-12">
              <label class="form-label">Items</label>
              <div id="items-wrapper">
                <?php
                if (is_array($decoded_items)) {
                  foreach ($decoded_items as $it) {
                    $n = htmlspecialchars($it['name']);
                    $p = htmlspecialchars($it['price']);
                    echo "
                      <div class='row mb-2 item-row'>
                        <div class='col-md-6'>
                          <input type='text' name='item_name[]' value='$n' class='form-control' placeholder='Item name' required>
                        </div>
                        <div class='col-md-4'>
                          <input type='number' step='0.01' name='item_price[]' value='$p' class='form-control' placeholder='Price' required>
                        </div>
                        <div class='col-md-2'>
                          <button type='button' class='btn btn-danger remove-item'>Remove</button>
                        </div>
                      </div>";
                  }
                } else {
                  echo "<div class='alert alert-warning'>Failed to decode items.</div>";
                }
                ?>
              </div>
              <button type="button" class="btn btn-success btn-sm" id="add-item">+ Add Item</button>
            </div>
            <!-- Total & Status -->
            <div class="col-md-6">
              <label class="form-label">Total Amount ($)</label>
              <input type="number" name="total_amount" step="0.01" value="<?= htmlspecialchars($order['total_amount']) ?>" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select name="status" class="form-select" required>
                <?php 
                foreach (['Pending','Preparing','Ready','Completed','Cancelled'] as $st) {
                  $sel = $order['status'] === $st ? 'selected' : '';
                  echo "<option value='$st' $sel>$st</option>";
                }
                ?>
              </select>
            </div>
            <!-- Actions -->
            <div class="col-12">
              <button type="submit" class="btn btn-primary">Update Order</button>
              <a href="orders.php" class="btn btn-secondary">Back to Orders</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>

  <?php include "library/footer.php"; ?>
  <?php include "library/script.php"; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  // Add / Remove item rows
  document.getElementById('add-item').addEventListener('click', () => {
    const wrapper = document.getElementById('items-wrapper');
    wrapper.insertAdjacentHTML('beforeend', `
      <div class="row mb-2 item-row">
        <div class="col-md-6">
          <input type="text" name="item_name[]" class="form-control" placeholder="Item name" required>
        </div>
        <div class="col-md-4">
          <input type="number" step="0.01" name="item_price[]" class="form-control" placeholder="Price" required>
        </div>
        <div class="col-md-2">
          <button type="button" class="btn btn-danger remove-item">Remove</button>
        </div>
      </div>`);
  });
  document.addEventListener('click', e => {
    if (e.target.classList.contains('remove-item')) {
      e.target.closest('.item-row').remove();
    }
  });
  </script>
</body>
</html>
