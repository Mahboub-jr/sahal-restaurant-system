<?php
/**
 * Retired. Superseded by reservations.php, which adds party size, notes,
 * an optional customer_id link instead of a required one, a fuller status
 * lifecycle, and keeps a table's status in sync automatically. Kept as a
 * redirect so old bookmarks or links do not 404.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

flash_info('Table bookings are now called Reservations.');
redirect('reservations.php');
