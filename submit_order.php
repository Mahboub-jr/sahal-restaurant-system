<?php
/**
 * Retired. Order submission now happens via actions/orders.php (do=create),
 * posted from the cart on place_order.php -- server-side pricing, CSRF and
 * role checks that this file never had (AUDIT-ADDENDUM.md BUG-1 was found
 * here). Kept as a redirect so old bookmarks and links do not 404.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

flash_info('Placing an order now happens from the New Order page.');
redirect('place_order.php');
