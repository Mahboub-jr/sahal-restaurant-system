<?php
require_once __DIR__ . '/includes/legacy_guard.php';

include "library/conn.php";

if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // safer than using raw $_GET directly
    $query = "UPDATE orders SET status = 'Completed' WHERE id = $id";

    if (mysqli_query($conn, $query)) {
        header("Location: orders.php?msg=completed");
        exit();
    } else {
        header("Location: orders.php?msg=error");
        exit();
    }
} else {
    header("Location: orders.php?msg=invalid");
    exit();
}
