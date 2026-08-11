<?php
/**
 * Retired. Completing an order mutated status over a plain GET link
 * (AUDIT.md E5 -- fireable by a crawler or a prefetching browser). The
 * Orders page now posts to actions/orders.php (do=set_status) instead.
 * Kept as a redirect so old bookmarks or links do not 404.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

flash_info('Use the status buttons on the Orders page to complete an order.');
redirect('orders.php');
