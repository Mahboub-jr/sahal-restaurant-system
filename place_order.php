<?php
include "library/conn.php";
$result = mysqli_query($conn, "SELECT * FROM menu_items");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Place Order</title>
  <link rel="stylesheet" href="css/main.css">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    .menu-card img { height: 150px; object-fit: cover; }
    .cart-box { max-height: 400px; overflow-y: auto; }
  </style>
</head>
<body class="bg-light">
  <div class="container py-4">
    <h2 class="text-center mb-4">📝 Place an Order</h2>

    <div class="row">
      <!-- Menu Items -->
      <div class="col-md-8">
        <div class="row">
          <?php while ($item = mysqli_fetch_assoc($result)): ?>
            <div class="col-md-4 mb-4">
              <div class="card menu-card">
              <img src="uploads/<?= htmlspecialchars($item['food_image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($item['name']) ?>">
              <div class="card-body">
                  <h5 class="card-title"><?= $item['name'] ?></h5>
                  <p class="card-text">$<?= number_format($item['price'], 2) ?></p>
                  <button class="btn btn-sm btn-primary" onclick="addToCart('<?= $item['id'] ?>', '<?= $item['name'] ?>', <?= $item['price'] ?>)">Add to Order</button>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      </div>

      <!-- Cart -->
      <div class="col-md-4">
        <div class="card">
          <div class="card-header bg-dark text-white">🛒 Your Order</div>
          <div class="card-body cart-box" id="cart"></div>
          <div class="card-footer">
            <form method="POST" action="submit_order.php" onsubmit="return prepareOrder()">
              <input type="hidden" name="items" id="orderItems">
              <div class="mb-2">
                <label>Customer Name</label>
                <input type="text" name="customer" class="form-control" required>
              </div>
              <div class="mb-2">
                <label>Order Type</label>
                <select name="order_type" class="form-control" required>
                  <option>Dine In</option>
                  <option>Take Away</option>
                </select>
              </div>
              <div class="mb-2">
                <label>Total ($)</label>
                <input type="text" id="totalAmount" name="total_amount" class="form-control" readonly>
              </div>
              <button type="submit" class="btn btn-success w-100">Submit Order</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    let cart = [];
    function addToCart(id, name, price) {
      cart.push({id, name, price});
      renderCart();
    }

    function renderCart() {
      let html = '', total = 0;
      cart.forEach((item, index) => {
        html += `<p>${item.name} - $${item.price.toFixed(2)} <button onclick="removeItem(${index})" class="btn btn-sm btn-danger float-end">X</button></p>`;
        total += item.price;
      });
      document.getElementById('cart').innerHTML = html || '<p>No items added yet.</p>';
      document.getElementById('totalAmount').value = total.toFixed(2);
    }

    function removeItem(index) {
      cart.splice(index, 1);
      renderCart();
    }

    function prepareOrder() {
      if (cart.length === 0) {
        alert('Please add at least one item to your order.');
        return false;
      }
      document.getElementById('orderItems').value = JSON.stringify(cart);
      return true;
    }
  </script>
</body>
</html>
