<?php
/**
 * Settings write operation. One row, one form, POST only, CSRF-checked,
 * admin only. No HTML is produced here -- redirects back to settings.php
 * with a flash message either way.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_post();
require_role('admin');
csrf_check();

$settings = db_one('SELECT * FROM settings ORDER BY id LIMIT 1');
if ($settings === null) {
    flash_error('No settings row exists to update.');
    redirect('settings.php');
}

$errors = [];

$name           = post('restaurant_name');
$address        = post('address');
$phone          = post('phone');
$email          = post('email');
$taxRate        = post_float('tax_rate', -1);
$serviceCharge  = post_float('service_charge', -1);
$currencySymbol = post('currency_symbol');
$invoicePrefix  = post('invoice_prefix');
$showLogo       = isset($_POST['show_logo_on_invoice']) ? 1 : 0;
$footerNote     = post('invoice_footer_note');

if ($name === '') {
    $errors[] = 'Restaurant name is required.';
}
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = 'That email address does not look valid.';
}
if ($taxRate < 0 || $taxRate > 100) {
    $errors[] = 'Tax rate must be between 0 and 100.';
}
if ($serviceCharge < 0 || $serviceCharge > 100) {
    $errors[] = 'Service charge must be between 0 and 100.';
}
if (mb_strlen($currencySymbol) > 10) {
    $errors[] = 'Currency symbol must be 10 characters or fewer.';
}
if (mb_strlen($invoicePrefix) > 20) {
    $errors[] = 'Invoice prefix must be 20 characters or fewer.';
}

// post() only handles scalars; opening_hours[] is an array field, read directly.
$days  = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$hours = [];
foreach ($days as $day) {
    $value = $_POST['opening_hours'][$day] ?? '';
    $hours[$day] = is_string($value) ? trim($value) : '';
}

$upload = upload_image($_FILES['logo'] ?? null, 'settings');
if (!$upload['ok']) {
    $errors[] = $upload['error'];
}

if ($errors !== []) {
    if ($upload['ok'] && $upload['filename'] !== null) {
        delete_upload($upload['filename'], 'settings');
    }
    flash_error(implode(' ', $errors));
    redirect('settings.php');
}

$logo = $settings['logo'];
if ($upload['filename'] !== null) {
    delete_upload($settings['logo'], 'settings');
    $logo = $upload['filename'];
} elseif (post('remove_logo') === '1') {
    delete_upload($settings['logo'], 'settings');
    $logo = null;
}

db_run(
    'UPDATE settings SET
        restaurant_name = ?, address = ?, phone = ?, email = ?, logo = ?,
        tax_rate = ?, service_charge = ?, currency_symbol = ?, invoice_prefix = ?,
        show_logo_on_invoice = ?, invoice_footer_note = ?, opening_hours = ?
      WHERE id = ?',
    [
        $name, $address !== '' ? $address : null, $phone !== '' ? $phone : null,
        $email !== '' ? $email : null, $logo,
        round($taxRate, 2), round($serviceCharge, 2),
        $currencySymbol !== '' ? $currencySymbol : '$', $invoicePrefix !== '' ? $invoicePrefix : null,
        $showLogo, $footerNote !== '' ? $footerNote : null, json_encode($hours),
        $settings['id'],
    ]
);

flash_success('Settings updated.');
redirect('settings.php');
