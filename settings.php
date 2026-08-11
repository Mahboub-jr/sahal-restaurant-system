<?php
/**
 * Settings -- one row, one form.
 *
 * This page only READS and RENDERS. The write goes to actions/settings.php.
 *
 * Dropped the old "Theme" select (default/dark/light): it wrote to
 * settings.theme, but nothing in the app -- old or new -- ever read that
 * column back. The dark/light toggle actually in use (the moon icon in
 * the top bar) is a separate, per-browser localStorage preference, not a
 * restaurant-wide setting.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin');

$title    = 'Settings';
$subtitle = 'Restaurant profile, invoicing and opening hours';

$settings = db_one('SELECT * FROM settings ORDER BY id LIMIT 1');
if ($settings === null) {
    flash_error('No settings row exists. Check the settings table.');
    redirect('index.php');
}

$openingHours = json_decode((string) $settings['opening_hours'], true);
if (!is_array($openingHours)) {
    $openingHours = [];
}
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

$logoUrl = upload_url($settings['logo'], 'settings');

include __DIR__ . '/includes/layout/app_start.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-head__title">Settings</h1>
  </div>
</div>

<form method="post" action="<?= url('actions/settings.php') ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="card mb-3">
    <div class="card-header"><h2>General</h2></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="f_name">Restaurant name <span style="color:var(--bad)">*</span></label>
          <input class="form-control" type="text" id="f_name" name="restaurant_name" maxlength="255"
                 value="<?= e($settings['restaurant_name']) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="f_phone">Phone</label>
          <input class="form-control" type="text" id="f_phone" name="phone" maxlength="50" value="<?= e($settings['phone']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="f_email">Email</label>
          <input class="form-control" type="email" id="f_email" name="email" maxlength="100" value="<?= e($settings['email']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="f_address">Address</label>
          <textarea class="form-control" id="f_address" name="address" rows="1"><?= e($settings['address']) ?></textarea>
        </div>

        <div class="col-12">
          <label class="form-label" for="f_logo">Logo</label>
          <div class="d-flex align-items-start gap-3">
            <div style="position:relative;flex-shrink:0">
              <img id="logoPreview" src="<?= e($logoUrl ?? '') ?>" alt=""
                   style="<?= $logoUrl === null ? 'display:none;' : '' ?>width:88px;height:88px;object-fit:cover;border-radius:var(--radius);border:1px solid var(--border)">
              <div data-preview-placeholder
                   style="<?= $logoUrl !== null ? 'display:none;' : '' ?>width:88px;height:88px;border-radius:var(--radius);border:1px dashed var(--border-strong);display:grid;place-items:center;color:var(--text-subtle)">
                <i class="bi bi-image" style="font-size:1.4rem"></i>
              </div>
            </div>
            <div class="flex-fill">
              <input class="form-control" type="file" id="f_logo" name="logo"
                     accept="image/jpeg,image/png,image/gif,image/webp" data-preview="#logoPreview">
              <div class="form-hint">Shown on the invoice when "Show logo on invoice" is checked below.</div>
              <input type="hidden" name="remove_logo" value="0" id="formRemoveLogo">
              <button type="button" class="btn btn-ghost btn-sm mt-2 <?= $logoUrl === null ? 'd-none' : '' ?>"
                      id="removeLogoBtn" style="color:var(--bad)">
                <i class="bi bi-trash"></i> Remove current logo
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><h2>Opening hours</h2></div>
    <div class="card-body">
      <div class="row g-2">
        <?php foreach ($days as $day): ?>
          <div class="col-md-6">
            <label class="form-label" for="hours_<?= e($day) ?>"><?= e($day) ?></label>
            <input class="form-control" type="text" id="hours_<?= e($day) ?>"
                   name="opening_hours[<?= e($day) ?>]" value="<?= e($openingHours[$day] ?? '') ?>"
                   placeholder="9:00 AM – 9:00 PM">
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><h2>Invoicing</h2></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label" for="f_tax">Tax rate (%)</label>
          <input class="form-control" type="number" id="f_tax" name="tax_rate" step="0.01" min="0" max="100"
                 value="<?= e($settings['tax_rate']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="f_service">Service charge (%)</label>
          <input class="form-control" type="number" id="f_service" name="service_charge" step="0.01" min="0" max="100"
                 value="<?= e($settings['service_charge']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="f_currency">Currency symbol</label>
          <input class="form-control" type="text" id="f_currency" name="currency_symbol" maxlength="10"
                 value="<?= e($settings['currency_symbol']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="f_prefix">Invoice number prefix</label>
          <input class="form-control" type="text" id="f_prefix" name="invoice_prefix" maxlength="20"
                 value="<?= e($settings['invoice_prefix']) ?>">
          <div class="form-hint">Order invoices use their own order number, not this -- this is here for a future dedicated invoice sequence.</div>
        </div>
        <div class="col-md-6 d-flex align-items-end">
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="f_show_logo" name="show_logo_on_invoice"
                   value="1" <?= $settings['show_logo_on_invoice'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="f_show_logo">Show logo on invoice</label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label" for="f_footer">Invoice footer note</label>
          <textarea class="form-control" id="f_footer" name="invoice_footer_note" rows="2"
                    maxlength="255"><?= e($settings['invoice_footer_note']) ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">
    <i class="bi bi-check-lg"></i> Save settings
  </button>
</form>

<?php
$inlineScript = <<<'JS'
(function () {
  var removeBtn = document.getElementById('removeLogoBtn');
  var removeEl  = document.getElementById('formRemoveLogo');
  var preview   = document.getElementById('logoPreview');
  var placeholder = document.querySelector('[data-preview-placeholder]');
  var fileInput = document.getElementById('f_logo');

  removeBtn.addEventListener('click', function () {
    removeEl.value = '1';
    fileInput.value = '';
    preview.style.display = 'none';
    if (placeholder) placeholder.style.display = '';
    removeBtn.classList.add('d-none');
  });

  fileInput.addEventListener('change', function () {
    if (fileInput.files && fileInput.files[0]) {
      removeEl.value = '0';
    }
  });
})();
JS;

include __DIR__ . '/includes/layout/app_end.php';
