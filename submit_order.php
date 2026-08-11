<?php
include "library/conn.php";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $customer = mysqli_real_escape_string($conn, $_POST['customer']);
  $order_type = mysqli_real_escape_string($conn, $_POST['order_type']);
  $total = floatval($_POST['total_amount']);
  $items_json = mysqli_real_escape_string($conn, $_POST['items']);
  $status = "Pending";
  $created_at = date('Y-m-d H:i:s');

  // Validate
  if (empty($customer) || empty($order_type) || empty($items_json) || $total <= 0) {
    $error = "Invalid input data. Please check the form again.";
  } else {
    // Insert into orders table
    $sql = "INSERT INTO orders (customer_name, order_type, items, total_amount, status, created_at)
            VALUES ('$customer', '$order_type', '$items_json', '$total', '$status', '$created_at')";

    if (mysqli_query($conn, $sql)) {
      header("Location: orders.php?success=1");
      exit();
    } else {
      $error = "Failed to submit order: " . mysqli_error($conn);
    }
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Order Submission</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-8">

        <?php if (isset($error)): ?>
          <div class="alert alert-danger">
            <strong>Error:</strong> <?= $error ?>
          </div>
          <a href="place_order.php" class="btn btn-secondary">⬅ Back to Order</a>
        <?php else: ?>
          <div class="alert alert-info">
            <strong>Processing your order...</strong>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</body>
</html>
