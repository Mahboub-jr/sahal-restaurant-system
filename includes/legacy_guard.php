<?php
/**
 * Authentication guard for pages that have not been converted yet.
 *
 * The audit found that 39 of 41 pages could be opened by anyone who knew the
 * URL (AUDIT.md E1). Rewriting all of them at once would be reckless, so this
 * file closes the hole immediately while the conversion proceeds page by page.
 *
 * It is prepended to every legacy page as:
 *
 *     require_once __DIR__ . '/includes/legacy_guard.php';
 *
 * It deliberately does three things and no more:
 *   1. boots config/database/session
 *   2. requires a signed-in user
 *   3. enforces the same role list the sidebar advertises for that page
 *
 * Legacy pages keep using mysqli through library/conn.php; the two
 * connections coexist safely. As each page is converted it drops this
 * include and calls require_role() directly.
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Which roles may open which legacy page.
 * Anything not listed here falls back to admin + manager, which is the
 * safe default: too strict is recoverable, too loose is a breach.
 */
$legacyAccess = [
    'place_order.php'       => ['admin', 'manager', 'cashier', 'waiter'],
    'submit_order.php'      => ['admin', 'manager', 'cashier', 'waiter'],
    'add_order.php'         => ['admin', 'manager', 'cashier', 'waiter'],
    'orders.php'            => ['admin', 'manager', 'cashier', 'waiter'],
    'update_order.php'      => ['admin', 'manager', 'cashier'],
    'cancel_order.php'      => ['admin', 'manager'],
    'complete_order.php'    => ['admin', 'manager', 'cashier'],
    'cancelled_orders.php'  => ['admin', 'manager'],
    'order_history.php'     => ['admin', 'manager', 'cashier'],
    'receipt.php'           => ['admin', 'manager', 'cashier'],

    'categories.php'        => ['admin', 'manager'],

    'tables.php'            => ['admin', 'manager', 'waiter'],
    'table_booking.php'     => ['admin', 'manager', 'waiter'],
    'receipt_booking.php'   => ['admin', 'manager', 'cashier'],
    'customers.php'         => ['admin', 'manager', 'cashier'],

    'payments.php'          => ['admin', 'manager', 'cashier'],
    'receipt_payment.php'   => ['admin', 'manager', 'cashier'],

    'reports.php'           => ['admin', 'manager'],
    'export_report.php'     => ['admin', 'manager'],
    'export_user_roles.php' => ['admin'],

    'employees.php'         => ['admin', 'manager'],
    'attendance.php'        => ['admin', 'manager'],
    'attendance_report.php' => ['admin', 'manager'],

    'manage_users.php'      => ['admin'],
    'user_roles.php'        => ['admin'],
    'settings.php'          => ['admin'],
];

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$allowedRoles = $legacyAccess[$currentPage] ?? ['admin', 'manager'];

require_role($allowedRoles);
