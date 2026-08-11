<?php
require_once __DIR__ . '/includes/legacy_guard.php';

include "library/conn.php";

// Add Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $order_id = intval($_POST['order_id']);
    $customer_id = intval($_POST['customer_id']);
    $amount = floatval($_POST['amount']);
    $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
    $method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    mysqli_query($conn, "INSERT INTO payments (order_id, customer_id, amount, payment_date, payment_method, status) 
                         VALUES ($order_id, $customer_id, $amount, '$payment_date', '$method', '$status')");

                         if ($_POST['status'] === 'Paid') {
                            mysqli_query($conn, "UPDATE orders SET status = 'completed' WHERE id = $order_id");
                        }
    header("Location: payments.php?msg=added");
    exit();
}

// Delete Payment
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM payments WHERE id=$id");
    header("Location: payments.php?msg=deleted");
    exit();
}

// Update Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $id = intval($_POST['payment_id']);
    $order_id = intval($_POST['order_id']);
    $customer_id = intval($_POST['customer_id']);
    $amount = floatval($_POST['amount']);
    $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
    $method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    mysqli_query($conn, "UPDATE payments 
                         SET order_id=$order_id, customer_id=$customer_id, amount=$amount, 
                             payment_date='$payment_date', payment_method='$method', status='$status'
                         WHERE id=$id");
    header("Location: payments.php?msg=updated");
    exit();
}

// Filters
$where = "1=1";
if (!empty($_GET['customer'])) {
    $customer = mysqli_real_escape_string($conn, $_GET['customer']);
    $where .= " AND c.name LIKE '%$customer%'";
}
if (!empty($_GET['method'])) {
    $method = mysqli_real_escape_string($conn, $_GET['method']);
    $where .= " AND p.payment_method = '$method'";
}
if (!empty($_GET['status'])) {
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $where .= " AND p.status = '$status'";
}

$payments = mysqli_query($conn, "
    SELECT p.*, c.name AS customer_name 
    FROM payments p 
    JOIN customers c ON p.customer_id = c.id 
    WHERE $where
    ORDER BY p.payment_date DESC
");

$orders = mysqli_query($conn, "SELECT id FROM orders");
$customers = mysqli_query($conn, "SELECT id, name FROM customers");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Payments</title>
    <link rel="stylesheet" href="css/main.css">
    <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
<?php include "library/header.php"; ?>
<?php include "library/sidebar.php"; ?>

<main class="app-content">
    <div class="app-title">
        <h1><i class="bi bi-credit-card"></i> Payments</h1>
    </div>

    <div class="row">
        <!-- Add Payment -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">Add Payment</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-2">
                            <label>Order ID</label>
                            <select name="order_id" class="form-select" required>
                                <option value="">-- Select Order --</option>
                                <?php while ($o = mysqli_fetch_assoc($orders)): ?>
                                    <option value="<?= $o['id'] ?>"><?= $o['id'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label>Customer</label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">-- Select Customer --</option>
                                <?php mysqli_data_seek($customers, 0); while ($c = mysqli_fetch_assoc($customers)): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label>Amount</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label>Payment Date</label>
                            <input type="datetime-local" name="payment_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                        <div class="mb-2">
                            <label>Method</label>
                            <select name="payment_method" class="form-select" required>
                                <option>Cash</option>
                                <option>Card</option>
                                <option>Mobile Money</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Status</label>
                            <select name="status" class="form-select" required>
                                <option>Paid</option>
                                <option>Pending</option>
                            </select>
                        </div>
                        <button type="submit" name="add_payment" class="btn btn-primary w-100">Add Payment</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Payment List -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-info text-white">All Payments</div>
                <div class="card-body table-responsive">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-4">
                            <input type="text" name="customer" class="form-control" placeholder="Customer Name" value="<?= $_GET['customer'] ?? '' ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="method" class="form-select">
                                <option value="">All Methods</option>
                                <option <?= ($_GET['method'] ?? '') == 'Cash' ? 'selected' : '' ?>>Cash</option>
                                <option <?= ($_GET['method'] ?? '') == 'Card' ? 'selected' : '' ?>>Card</option>
                                <option <?= ($_GET['method'] ?? '') == 'Mobile Money' ? 'selected' : '' ?>>Mobile Money</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option <?= ($_GET['status'] ?? '') == 'Paid' ? 'selected' : '' ?>>Paid</option>
                                <option <?= ($_GET['status'] ?? '') == 'Pending' ? 'selected' : '' ?>>Pending</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
                        </div>
                    </form>

                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $i = 1; while ($p = mysqli_fetch_assoc($payments)): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= $p['order_id'] ?></td>
                                <td><?= htmlspecialchars($p['customer_name']) ?></td>
                                <td>$<?= number_format($p['amount'], 2) ?></td>
                                <td><?= $p['payment_method'] ?></td>
                                <td>
                                    <span class="badge bg-<?= $p['status'] == 'Paid' ? 'success' : 'warning' ?>">
                                        <?= $p['status'] ?>
                                    </span>
                                </td>
                                <td><?= $p['payment_date'] ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $p['id'] ?>">Edit</button>
                                    <a href="?delete=<?= $p['id'] ?>" onclick="return confirm('Delete payment?')" class="btn btn-sm btn-danger">Delete</a>
                                    <a href="receipt_payment.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-receipt"></i> Receipt</a>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal<?= $p['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="POST" class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Payment</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                            <div class="mb-2">
                                                <label>Order ID</label>
                                                <select name="order_id" class="form-select" required>
                                                    <?php mysqli_data_seek($orders, 0); while ($o = mysqli_fetch_assoc($orders)): ?>
                                                        <option value="<?= $o['id'] ?>" <?= $o['id'] == $p['order_id'] ? 'selected' : '' ?>><?= $o['id'] ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                            <div class="mb-2">
                                                <label>Customer</label>
                                                <select name="customer_id" class="form-select" required>
                                                    <?php mysqli_data_seek($customers, 0); while ($c = mysqli_fetch_assoc($customers)): ?>
                                                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $p['customer_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                            <div class="mb-2">
                                                <label>Amount</label>
                                                <input type="number" step="0.01" name="amount" class="form-control" value="<?= $p['amount'] ?>" required>
                                            </div>
                                            <div class="mb-2">
                                                <label>Payment Date</label>
                                                <input type="datetime-local" name="payment_date" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($p['payment_date'])) ?>" required>
                                            </div>
                                            <div class="mb-2">
                                                <label>Method</label>
                                                <select name="payment_method" class="form-select">
                                                    <option <?= $p['payment_method'] == 'Cash' ? 'selected' : '' ?>>Cash</option>
                                                    <option <?= $p['payment_method'] == 'Card' ? 'selected' : '' ?>>Card</option>
                                                    <option <?= $p['payment_method'] == 'Mobile Money' ? 'selected' : '' ?>>Mobile Money</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label>Status</label>
                                                <select name="status" class="form-select">
                                                    <option <?= $p['status'] == 'Paid' ? 'selected' : '' ?>>Paid</option>
                                                    <option <?= $p['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" name="update_payment" class="btn btn-success">Save Changes</button>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "library/footer.php"; ?>
<?php include "library/script.php"; ?>
</body>
</html>
