
<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "restaurant_db"); // adjust DB name if needed

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $description = $_POST['description'];

    // Handle image upload
    $image = $_FILES['image']['name'];
    $target = "uploads/" . basename($image);

    if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
        $stmt = $conn->prepare("INSERT INTO menu_items (name, price, category, image, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sdsss", $name, $price, $category, $image, $description);

        if ($stmt->execute()) {
            $message = "Menu item added successfully!";
        } else {
            $message = "Failed to add item. Error: " . $conn->error;
        }
    } else {
        $message = "Failed to upload image.";
    }
}
?>


<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php"); // Redirect to login if not logged in
  exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<?php include "library/header.php";?>
    <title>Add Menu Item</title>
    <link rel="stylesheet" type="text/css" href="css/main.css">
</head>
<body class="app sidebar-mini">
<main class="app-content">
    <div class="app-title">
        <h1>Add New Menu Item</h1>
    </div>
    <?php require "library/sidebar.php";?>

    <?php if (isset($message)): ?>
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Item Name</label>
            <input class="form-control" type="text" name="name" required>
        </div>
        <div class="form-group">
            <label>Price (USD)</label>
            <input class="form-control" type="number" step="0.01" name="price" required>
        </div>
        <div class="form-group">
            <label>Category</label>
            <input class="form-control" type="text" name="category">
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea class="form-control" name="description"></textarea>
        </div>
        <div class="form-group">
            <label>Item Image</label>
            <input class="form-control" type="file" name="image" required>
        </div>
        <button class="btn btn-primary" type="submit">Add Item</button>
    </form>
</main>
       <!-- footer-->
       <?php include "library/footer.php";?>
</body>
</html>
