<?php
include "library/conn.php";

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $query = "UPDATE orders SET status = 'Cancelled' WHERE id = $id";

    if (mysqli_query($conn, $query)) {
        header("Location: orders.php?msg=cancelled");
        exit();
    } else {
        echo "Error updating order: " . mysqli_error($conn);
    }
} else {
    echo "Invalid order ID.";
}
?>
