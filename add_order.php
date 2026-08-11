<?php
require_once __DIR__ . '/includes/legacy_guard.php';

include "library/conn.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer = mysqli_real_escape_string($conn, $_POST['customer_name']);
    $type = mysqli_real_escape_string($conn, $_POST['order_type']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $total = floatval($_POST['total_amount']);

    $items = [];
    foreach ($_POST['item_name'] as $index => $name) {
        $items[] = [
            'name' => mysqli_real_escape_string($conn, $name),
            'price' => floatval($_POST['item_price'][$index])
        ];
    }

    $items_json = json_encode($items);

    $query = "INSERT INTO orders (customer_name, order_type, items, total_amount, status)
              VALUES ('$customer', '$type', '$items_json', $total, '$status')";

    if (mysqli_query($conn, $query)) {
        header("Location: orders.php?msg=added");
        exit();
    } else {
        echo "Failed to add order: " . mysqli_error($conn);
    }
}
?>


<!DOCTYPE html>
<html>
<head>
    <title>Add New Order</title>
    <link rel="stylesheet" href="css/main.css">
    <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
<?php include "library/sidebar.php"; ?>
<?php include "library/header.php"; ?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="bi bi-plus-square"></i> New Order</h1>
            <p>Add a customer order with multiple items</p>
        </div>
    </div>

    <form method="POST" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Customer Name</label>
            <input type="text" name="customer_name" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Order Type</label>
            <select name="order_type" class="form-select" required>
                <option value="Dine-In">Dine-In</option>
                <option value="Takeaway">Takeaway</option>
                <option value="Delivery">Delivery</option>
            </select>
        </div>

        <div class="col-md-12">
            <label class="form-label">Items</label>
            <div id="items-wrapper">
                <div class="row mb-2">
                    <div class="col-md-6">
                        <input type="text" name="item_name[]" class="form-control" placeholder="Item name" required>
                    </div>
                    <div class="col-md-4">
                        <input type="number" name="item_price[]" class="form-control" step="0.01" placeholder="Price" required>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger remove-item">Remove</button>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-success btn-sm" id="add-item">+ Add Item</button>
        </div>

        <div class="col-md-6">
            <label class="form-label">Total Amount ($)</label>
            <input type="number" name="total_amount" step="0.01" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="Pending">Pending</option>
                <option value="In Progress">In Progress</option>
            </select>
        </div>

        <div class="col-md-12">
            <button type="submit" class="btn btn-primary">Add Order</button>
            <a href="orders.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</main>

<?php include "library/footer.php"; ?>
<?php include "library/script.php"; ?>

<script>
document.getElementById('add-item').addEventListener('click', function () {
    const itemHTML = `
        <div class="row mb-2">
            <div class="col-md-6">
                <input type="text" name="item_name[]" class="form-control" placeholder="Item name" required>
            </div>
            <div class="col-md-4">
                <input type="number" name="item_price[]" class="form-control" step="0.01" placeholder="Price" required>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger remove-item">Remove</button>
            </div>
        </div>
    `;
    document.getElementById('items-wrapper').insertAdjacentHTML('beforeend', itemHTML);
});

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-item')) {
        e.target.closest('.row').remove();
    }
});
</script>
</body>
</html>
