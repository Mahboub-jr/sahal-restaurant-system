<?php
require_once __DIR__ . '/includes/legacy_guard.php';

include "library/conn.php";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $customer = mysqli_real_escape_string($conn, $_POST['customer'] ?? '');
  $order_type_raw = trim($_POST['order_type'] ?? '');
  $total = floatval($_POST['total_amount'] ?? 0);
  $items_raw = $_POST['items'] ?? '';
  $items_json = mysqli_real_escape_string($conn, $items_raw);
  $status = "Pending";
  $created_at = date('Y-m-d H:i:s');

  // Whitelist order_type against the orders.order_type ENUM.
  // Without this check an unmatched value is silently stored as '' by MySQL
  // (non-strict mode), which is how orders 17, 19 and 20 lost their type.
  // See AUDIT-ADDENDUM.md BUG-1.
  $allowed_order_types = ['Dine-In', 'Takeaway', 'Delivery'];
  $order_type = in_array($order_type_raw, $allowed_order_types, true)
      ? $order_type_raw
      : null;

  // Validate
  if (empty($customer) || empty($items_json) || $total <= 0) {
    $error = "Invalid input data. Please check the form again.";
  } elseif ($order_type === null) {
    $error = "Please choose a valid order type ("
           . implode(', ', $allowed_order_types) . ").";
  } elseif (!is_array(json_decode($items_raw, true)) || json_decode($items_raw, true) === []) {
    $error = "Your order contains no items.";
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
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
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
