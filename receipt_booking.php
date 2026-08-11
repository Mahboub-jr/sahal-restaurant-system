<?php
include "library/conn.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Invalid booking ID.";
    exit;
}

$id = intval($_GET['id']);

$query = "
SELECT b.id, b.booking_time, b.status, 
       c.name AS customer_name, 
       t.table_number 
FROM table_bookings b
JOIN customers c ON b.customer_id = c.id
JOIN tables t ON b.table_id = t.id
WHERE b.id = $id";

$result = mysqli_query($conn, $query);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    echo "Booking not found.";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Booking Receipt</title>
    <link rel="stylesheet" href="css/main.css">
    <?php include "library/head.php"; ?>
    <style>
        .receipt-container {
            max-width: 600px;
            margin: 50px auto;
            background: #fdfdfd;
            padding: 30px;
            border: 1px solid #ccc;
            border-radius: 8px;
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .receipt-header h2 {
            margin: 0;
        }
        .receipt-body table {
            width: 100%;
        }
    </style>
</head>
<body>
<main class="app-content">
    <div class="receipt-container shadow">
        <div class="receipt-header">
            <h2>Sahal Restaurant</h2>
            <p class="text-muted">Table Booking Receipt</p>
        </div>
        <div class="receipt-body">
            <table class="table table-bordered">
                <tr>
                    <th>Booking ID</th>
                    <td><?= $booking['id'] ?></td>
                </tr>
                <tr>
                    <th>Customer</th>
                    <td><?= htmlspecialchars($booking['customer_name']) ?></td>
                </tr>
                <tr>
                    <th>Table Number</th>
                    <td><?= htmlspecialchars($booking['table_number']) ?></td>
                </tr>
                <tr>
                    <th>Booking Time</th>
                    <td><?= date("F j, Y, g:i A", strtotime($booking['booking_time'])) ?></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        <span class="badge 
                            <?= $booking['status'] == 'Booked' ? 'bg-primary' : 
                                ($booking['status'] == 'Seated' ? 'bg-success' : 'bg-danger') ?>">
                            <?= $booking['status'] ?>
                        </span>
                    </td>
                </tr>
                
            </table>
        </div>

        <div class="text-center mt-4">
            <button onclick="window.print()" class="btn btn-outline-dark">
                <i class="bi bi-printer"></i> Print Receipt
            </button>
            <a href="table_booking.php" class="btn btn-secondary ms-2">Back</a>
        </div>
    </div>
</main>
</body>
</html>
