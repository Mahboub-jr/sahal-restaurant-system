<?php include "library/conn.php"; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Cancelled Orders</title>
    <link rel="stylesheet" href="css/main.css">
    <?php include "library/head.php"; ?>
</head>
<body>
<?php include "library/sidebar.php"; ?>
<?php include "library/header.php"; ?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1>Cancelled Orders</h1>
            <p>Only showing orders with status = 'Cancelled'</p>
        </div>
    </div>

    <div class="tile">
        <div class="tile-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT * FROM orders WHERE status = 'Cancelled'";
                    $orders = mysqli_query($conn, $query);

                    if (!$orders) {
                        echo "<tr><td colspan='3'>Query error: " . mysqli_error($conn) . "</td></tr>";
                    } elseif (mysqli_num_rows($orders) == 0) {
                        echo "<tr><td colspan='3'>No cancelled orders found.</td></tr>";
                    } else {
                        $count = 1;
                        while ($row = mysqli_fetch_assoc($orders)) {
                            echo "<tr>
                                    <td>{$count}</td>
                                    <td>" . htmlspecialchars($row['customer_name']) . "</td>
                                    <td>" . htmlspecialchars($row['status']) . "</td>
                                  </tr>";
                            $count++;
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include "library/footer.php"; ?>
<?php include "library/script.php"; ?>
</body>
</html>
