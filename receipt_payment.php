<?php
/**
 * Retired. This page showed one payment record joined to one customer,
 * which stopped making sense once an order could have more than one
 * payment (or none) -- invoice.php shows the whole order instead. Kept as
 * a redirect, translating the old payment id into the order it belonged
 * to, so old bookmarks or links land on the equivalent invoice.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager', 'cashier', 'waiter');

$paymentId = query_int('id');
$orderId   = $paymentId > 0
    ? db_value('SELECT order_id FROM payments WHERE id = ?', [$paymentId])
    : null;

if ($orderId !== null) {
    redirect('invoice.php?id=' . (int) $orderId);
}

flash_info('Payment receipts are now shown as an order invoice.');
redirect('payments.php');
