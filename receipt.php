<?php
include "library/conn.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  echo "Invalid order ID.";
  exit;
}

$id = intval($_GET['id']);
$res = mysqli_query($conn, "SELECT * FROM orders WHERE id = $id");
$order = mysqli_fetch_assoc($res);

if (!$order) {
  echo "Order not found.";
  exit;
}

// Decode items JSON
$items = json_decode($order['items'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Receipt #<?= $order['id'] ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    @media print {
      .no-print { display: none !important; }
    }
    body { background: #f8f9fa; }
    .receipt { background: #fff; padding: 2rem; margin: 2rem auto; max-width: 600px; box-shadow: 0 0 10px rgba(0,0,0,.1); }
    .receipt h2, .receipt h6 { margin-bottom: .5rem; }
    .table th, .table td { vertical-align: middle; }
  </style>
</head>
<body class="py-4">
  <div class="receipt">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Sahal Restaurent</h2>
      <button class="btn btn-primary no-print" onclick="window.print()">
        <i class="bi bi-printer"></i> Print
      </button>
    </div>

    <div class="row mb-3">
      <div class="col-6">
        <h6>Receipt #: <strong><?= $order['id'] ?></strong></h6>
        <h6>Date: <strong><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></strong></h6>
      </div>
      <div class="col-6 text-end">
        <h6>Customer:</h6>
        <p class="mb-0"><?= htmlspecialchars($order['customer_name']) ?></p>
        <small class="text-muted"><?= htmlspecialchars($order['order_type']) ?></small>
      </div>
    </div>

    <table class="table table-bordered">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Item</th>
          <th class="text-end">Price ($)</th>
        </tr>
      </thead>
      <tbody>
        <?php 
          $i = 1; 
          $subtotal = 0;
          if (is_array($items)) {
            foreach ($items as $it) {
              $name  = htmlspecialchars($it['name']);
              $price = floatval($it['price']);
              $subtotal += $price;
              echo "<tr>
                      <td>{$i}</td>
                      <td>{$name}</td>
                      <td class='text-end'>".number_format($price,2)."</td>
                    </tr>";
              $i++;
            }
          }
        ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="2" class="text-end">Subtotal</th>
          <th class="text-end"><?= number_format($subtotal,2) ?></th>
        </tr>
        <tr>
          <th colspan="2" class="text-end">Status</th>
          <th class="text-end">
            <?php
              $badge = match($order['status']) {
                'Pending'   => 'secondary',
                'Preparing' => 'warning',
                'Ready'     => 'info',
                'Completed' => 'success',
                'Cancelled' => 'danger',
                default     => 'dark'
              };
            ?>
            <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($order['status']) ?></span>
          </th>
        </tr>
      </tfoot>
    </table>

    <p class="text-center text-muted mb-0 mt-4">Thank you for your order!</p>
  </div>
</body>
</html>
