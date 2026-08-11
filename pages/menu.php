<?php
include('../db.php');
$menuItems = $conn->query("SELECT * FROM menu ORDER BY created_at DESC")->fetchAll();
?>

<?php include('../library/head.php'); ?>
<?php include('../library/header.php'); ?>
<?php include('../library/sidebar.php'); ?>

<main class="app-content">
  <div class="app-title">
    <div>
      <h1><i class="bi bi-list"></i> Menu Items</h1>
      <p>Manage your restaurant's food menu</p>
    </div>
    <a href="add-menu.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Add New Item</a>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile">
        <div class="tile-body">
          <table class="table table-hover table-bordered" id="menuTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Price ($)</th>
                <th>Category</th>
                <th>Description</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
            <tr>
                <td>1</td>
                <td>Burger</td>
                <td>5.99</td>
                <td>Fast Food</td>
                <td>Delicious beef burger</td>
                <td><a href="#">Edit</a></td>
                </tr>
            </tbody>
                </table>
              </div>
              <?php foreach ($menuItems as $item): ?>
              <tr>
                <td><?= $item['id'] ?></td>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td><?= $item['price'] ?></td>
                <td><?= htmlspecialchars($item['category']) ?></td>
                <td><?= htmlspecialchars($item['description']) ?></td>
                <td>
                  <a href="edit-menu.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                  <a href="delete-menu.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include('../library/footer.php'); ?>
<script type="text/javascript">
  $('#menuTable').DataTable();
</script>
