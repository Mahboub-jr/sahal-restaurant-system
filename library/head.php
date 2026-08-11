<?php
/**
 * <head> contents for pages still on the legacy Vali layout.
 *
 * The previous version carried the Vali template's own marketing metadata:
 * a <title> of "Vali Admin - Free Bootstrap 5 Admin Template", Twitter
 * handles for @pratikborsadiya, and Open Graph tags pointing at the template
 * author's blog. Every page in the restaurant system advertised itself as
 * someone else's demo (AUDIT.md §1.4, §32).
 *
 * Converted pages use includes/layout/head.php instead.
 */

$legacyTitle = $pageTitle
    ?? (defined('APP_NAME') ? APP_NAME : 'Restaurant Management System');
$base = defined('BASE_URL') ? BASE_URL : '';
?>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="description" content="Restaurant management system">
<title><?= htmlspecialchars($legacyTitle) ?></title>
<link rel="icon" href="<?= $base ?>images/Sahal_logo.jpeg" type="image/jpeg">
<link rel="stylesheet" href="<?= $base ?>css/main.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
