<?php
require_once __DIR__ . '/includes/legacy_guard.php';

session_start();
include "library/conn.php";

// Fetch settings
$settings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings LIMIT 1"));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $restaurant_name     = mysqli_real_escape_string($conn, $_POST['restaurant_name']);
  $address             = mysqli_real_escape_string($conn, $_POST['address']);
  $phone               = mysqli_real_escape_string($conn, $_POST['phone']);
  $email               = mysqli_real_escape_string($conn, $_POST['email']);
  $tax_rate            = floatval($_POST['tax_rate']);
  $service_charge      = floatval($_POST['service_charge']);
  $currency_symbol     = mysqli_real_escape_string($conn, $_POST['currency_symbol']);
  $invoice_prefix      = mysqli_real_escape_string($conn, $_POST['invoice_prefix']);
  $show_logo_on_invoice = isset($_POST['show_logo_on_invoice']) ? 1 : 0;
  $invoice_footer_note = mysqli_real_escape_string($conn, $_POST['invoice_footer_note']);
  $theme               = mysqli_real_escape_string($conn, $_POST['theme']);

  // Prepare JSON opening hours
  $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
  $hours = [];
  foreach ($days as $day) {
    $hours[$day] = $_POST['opening_hours'][$day] ?? '';
  }
  $opening_hours_json = json_encode($hours);

  // Update settings
  $sql = "UPDATE settings SET
            restaurant_name = '$restaurant_name',
            address = '$address',
            phone = '$phone',
            email = '$email',
            tax_rate = $tax_rate,
            service_charge = $service_charge,
            currency_symbol = '$currency_symbol',
            invoice_prefix = '$invoice_prefix',
            show_logo_on_invoice = $show_logo_on_invoice,
            invoice_footer_note = '$invoice_footer_note',
            theme = '$theme',
            opening_hours = '$opening_hours_json'
          WHERE id = {$settings['id']}";

  mysqli_query($conn, $sql);
  header("Location: settings.php?msg=updated");
  exit();
}

$opening_hours = json_decode($settings['opening_hours'], true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Settings</title>
  <link rel="stylesheet" href="css/main.css">
  <?php include "library/head.php"; ?>
</head>
<body class="app sidebar-mini">
<?php include "library/header.php"; ?>
<?php include "library/sidebar.php"; ?>

<main class="app-content">
  <div class="app-title">
    <h1><i class="bi bi-gear me-2"></i> Restaurant Settings</h1>
  </div>

  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
    <div class="alert alert-success">Settings updated successfully.</div>
  <?php endif; ?>

  <div class="row">
    <div class="col-md-10 offset-md-1">
      <form method="POST">
        <div class="card">
          <div class="card-header bg-primary text-white">General Info</div>
          <div class="card-body">
            <div class="mb-3">
              <label>Restaurant Name</label>
              <input type="text" name="restaurant_name" class="form-control" value="<?= htmlspecialchars($settings['restaurant_name']) ?>" required>
            </div>
            <div class="mb-3">
              <label>Address</label>
              <textarea name="address" class="form-control" required><?= htmlspecialchars($settings['address']) ?></textarea>
            </div>
            <div class="mb-3">
              <label>Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($settings['phone']) ?>" required>
            </div>
            <div class="mb-3">
              <label>Email</label>
              <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($settings['email']) ?>" required>
            </div>
          </div>
        </div>

        <div class="card mt-4">
          <div class="card-header bg-info text-white">Opening Hours</div>
          <div class="card-body">
            <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
              <div class="mb-2 row">
                <label class="col-sm-2 col-form-label"><?= $day ?></label>
                <div class="col-sm-10">
                  <input type="text" name="opening_hours[<?= $day ?>]" class="form-control" value="<?= htmlspecialchars($opening_hours[$day] ?? '') ?>" placeholder=" 9:00 AM - 9:00 PM">
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="card mt-4">
          <div class="card-header bg-secondary text-white">Invoice & Theme</div>
          <div class="card-body">
            <div class="mb-3">
              <label>Tax Rate (%)</label>
              <input type="number" step="0.01" name="tax_rate" class="form-control" value="<?= $settings['tax_rate'] ?>">
            </div>
            <div class="mb-3">
              <label>Service Charge (%)</label>
              <input type="number" step="0.01" name="service_charge" class="form-control" value="<?= $settings['service_charge'] ?>">
            </div>
            <div class="mb-3">
              <label>Currency Symbol</label>
              <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($settings['currency_symbol']) ?>">
            </div>
            <div class="mb-3">
              <label>Invoice Prefix</label>
              <input type="text" name="invoice_prefix" class="form-control" value="<?= htmlspecialchars($settings['invoice_prefix']) ?>">
            </div>
            <div class="mb-3 form-check">
              <input type="checkbox" name="show_logo_on_invoice" class="form-check-input" <?= $settings['show_logo_on_invoice'] ? 'checked' : '' ?>>
              <label class="form-check-label">Show Logo on Invoice</label>
            </div>
            <div class="mb-3">
              <label>Invoice Footer Note</label>
              <textarea name="invoice_footer_note" class="form-control"><?= htmlspecialchars($settings['invoice_footer_note']) ?></textarea>
            </div>
            <div class="mb-3">
              <label>Theme</label>
              <select name="theme" class="form-select">
                <option value="default" <?= $settings['theme'] === 'default' ? 'selected' : '' ?>>Default</option>
                <option value="dark" <?= $settings['theme'] === 'dark' ? 'selected' : '' ?>>Dark</option>
                <option value="light" <?= $settings['theme'] === 'light' ? 'selected' : '' ?>>Light</option>
              </select>
            </div>
          </div>
        </div>

        <div class="mt-3 d-grid">
          <button type="submit" class="btn btn-success"><i class="bi bi-save me-2"></i>Save Settings</button>
        </div>
      </form>
    </div>
  </div>
</main>

<?php include "library/footer.php"; ?>
<?php include "library/script.php"; ?>
</body>
</html>
