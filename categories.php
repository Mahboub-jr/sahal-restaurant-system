<?php
include "library/conn.php";

// Handle form submission (Add Category)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $now = date('Y-m-d H:i:s');

    $insert = "INSERT INTO categories (name, description, created_at) VALUES ('$name', '$desc', '$now')";
    mysqli_query($conn, $insert);
    header("Location: categories.php");
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM categories WHERE id = $id");
    header("Location: categories.php");
    exit();
}

// Fetch all categories
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Categories</title>
    <link rel="stylesheet" href="css/main.css">
    <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
<?php include "library/sidebar.php"; ?>
<?php include "library/header.php"; ?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="bi bi-tags"></i> Manage Categories</h1>
            <p>View, add, and delete menu categories</p>
        </div>
    </div>

    <div class="row">
        <!-- Add Category Form -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">Add New Category</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Category Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" required></textarea>
                        </div>
                        <button type="submit" name="add_category" class="btn btn-primary w-100">Add Category</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Category List -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">Category List</div>
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Created At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $i = 1;
                        while ($cat = mysqli_fetch_assoc($categories)) {
                            echo "<tr>
                                <td>{$i}</td>
                                <td>{$cat['name']}</td>
                                <td>{$cat['description']}</td>
                                <td>{$cat['created_at']}</td>
                                <td>
                                    <a href='?delete={$cat['id']}' class='btn btn-sm btn-danger' onclick=\"return confirm('Delete this category?')\">Delete</a>
                                </td>
                            </tr>";
                            $i++;
                        }
                        ?>
                        </tbody>
                    </table>
                    <?php if (mysqli_num_rows($categories) == 0): ?>
                        <p class="text-muted text-center">No categories yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include "library/footer.php"; ?>
<?php include "library/script.php"; ?>
</body>
</html>
