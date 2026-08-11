<?php
require_once __DIR__ . '/includes/legacy_guard.php';

include "library/conn.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid payment ID.");
}

$payment_id = intval($_GET['id']);

$query = "
SELECT p.*, 
       c.name AS customer_name, 
       o.id AS order_id 
FROM payments p
JOIN customers c ON p.customer_id = c.id
JOIN orders o ON p.order_id = o.id
WHERE p.id = $payment_id
LIMIT 1
";

$result = mysqli_query($conn, $query);
$payment = mysqli_fetch_assoc($result);

if (!$payment) {
    die("Payment not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Receipt</title>
  <link rel="stylesheet" href="css/main.css">
  <style>
    @media print {
      .no-print { display: none; }
      body { background: white; }
    }
    .receipt-box {
      max-width: 600px;
      margin: auto;
      padding: 30px;
      border: 1px solid #eee;
      background: #fff;
      font-family: 'Arial', sans-serif;
    }
    .receipt-header {
      text-align: center;
      margin-bottom: 30px;
    }
  </style>
</head>
<body>

<div class="receipt-box">
  <div class="receipt-header">
    <h2>Payment Receipt</h2>
    <p><strong>Receipt ID:</strong> #<?= $payment['id'] ?></p>
  </div>

  <table class="table table-borderless">
    <tr>
      <th>Customer:</th>
      <td><?= htmlspecialchars($payment['customer_name']) ?></td>
    </tr>
    <tr>
      <th>Order ID:</th>
      <td>#<?= $payment['order_id'] ?></td>
    </tr>
    <tr>
      <th>Amount Paid:</th>
      <td>$<?= number_format($payment['amount'], 2) ?></td>
    </tr>
    <tr>
      <th>Payment Date:</th>
      <td><?= date("d M Y, h:i A", strtotime($payment['payment_date'])) ?></td>
    </tr>
    <tr>
      <th>Method:</th>
      <td><?= $payment['payment_method'] ?></td>
    </tr>
    <tr>
      <th>Status:</th>
      <td>
        <span class="badge bg-<?= $payment['status'] == 'Paid' ? 'success' : 'warning' ?>">
          <?= $payment['status'] ?>
        </span>
      </td>
    </tr>
  </table>

  <hr>
  <p><strong>Thank you!</strong> This is your official payment receipt.</p>
  
  <div class="text-center no-print mt-4">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print Receipt</button>
    <a href="payments.php" class="btn btn-secondary">Back</a>
  </div>
</div>

</body>
</html>
