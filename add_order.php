<?php
/**
 * Retired. This page duplicated place_order.php with a second, unlinked
 * form that wrote raw $_POST prices straight into `orders` -- superseded
 * by the New Order screen. Kept as a redirect so old bookmarks or links do
 * not 404.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

flash_info('Placing an order now happens from the New Order page.');
redirect('place_order.php');
