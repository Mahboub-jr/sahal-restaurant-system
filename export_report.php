<?php
include "library/conn.php";
require_once "library/tcpdf/TCPDF-main/tcpdf.php"; // For PDF export

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'excel';
$payment = isset($_GET['payment']) ? mysqli_real_escape_string($conn, $_GET['payment']) : '';
$staff = isset($_GET['staff']) ? mysqli_real_escape_string($conn, $_GET['staff']) : '';

if ($type !== 'sales') {
  die("Invalid report type.");
}

// Build query
$conditions = [];
if ($payment) $conditions[] = "payment_method = '$payment'";
if ($staff) $conditions[] = "served_by = '$staff'";
$where = count($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$query = "SELECT id, order_date, total_amount, payment_method, served_by FROM orders $where ORDER BY order_date DESC";
$result = mysqli_query($conn, $query);

// Excel Export
if ($format === 'excel') {
  header("Content-Type: application/vnd.ms-excel");
  header("Content-Disposition: attachment; filename=sales_report.xls");

  echo "Order ID\tDate\tAmount\tPayment Method\tServed By\n";
  while ($row = mysqli_fetch_assoc($result)) {
    echo "#{$row['id']}\t{$row['order_date']}\t{$row['total_amount']}\t{$row['payment_method']}\t{$row['served_by']}\n";
  }
  exit;
}

// PDF Export
if ($format === 'pdf') {
  $pdf = new TCPDF();
  $pdf->SetTitle("Sales Report");
  $pdf->AddPage();

  $html = '<h3>Sales Report</h3>';
  if ($payment || $staff) {
    $html .= '<small>Filters: '
      . ($payment ? 'Payment = ' . ucfirst($payment) . ' ' : '')
      . ($staff ? '| Staff = ' . htmlspecialchars($staff) : '')
      . '</small><br><br>';
  }

  $html .= '<table border="1" cellpadding="4">
    <thead>
      <tr>
        <th><b>Order ID</b></th>
        <th><b>Date</b></th>
        <th><b>Amount</b></th>
        <th><b>Payment</b></th>
        <th><b>Served By</b></th>
      </tr>
    </thead>
    <tbody>';

  $total = 0;
  while ($row = mysqli_fetch_assoc($result)) {
    $html .= "<tr>
      <td>#{$row['id']}</td>
      <td>{$row['order_date']}</td>
      <td>$" . number_format($row['total_amount'], 2) . "</td>
      <td>{$row['payment_method']}</td>
      <td>{$row['served_by']}</td>
    </tr>";
    $total += $row['total_amount'];
  }

  $html .= "<tr><td colspan='2'><b>Total</b></td><td><b>$" . number_format($total, 2) . "</b></td><td colspan='2'></td></tr>";
  $html .= '</tbody></table>';

  $pdf->writeHTML($html, true, false, true, false, '');
  $pdf->Output('sales_report.pdf', 'D');
  exit;
}

die("Invalid export format.");
