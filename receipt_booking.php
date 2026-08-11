<?php
/**
 * Retired. Replaced by reservation_slip.php. Kept as a redirect,
 * translating the old table_bookings id to its migrated reservations id
 * where possible, so old bookmarks or links do not 404.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$oldId = query_int('id');
$hasLegacyTable = db_value(
    "SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'table_bookings_legacy'",
    [DB_NAME]
) !== null;

if ($hasLegacyTable && $oldId > 0) {
    $legacy = db_one('SELECT customer_id, table_id, booking_time FROM table_bookings_legacy WHERE id = ?', [$oldId]);
    if ($legacy !== null) {
        $reservationId = db_value(
            'SELECT id FROM reservations
              WHERE customer_id <=> ? AND table_id <=> ? AND reserved_at = ?
              LIMIT 1',
            [$legacy['customer_id'], $legacy['table_id'], $legacy['booking_time']]
        );
        if ($reservationId !== null) {
            redirect('reservation_slip.php?id=' . (int) $reservationId);
        }
    }
}

flash_info('Booking receipts are now printed from Reservations.');
redirect('reservations.php');
