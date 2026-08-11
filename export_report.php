<?php
/**
 * CSV export of the sales report, same filters as reports.php.
 *
 * Replaces the old export_report.php, which queried orders.served_by and
 * orders.payment_method -- neither column has ever existed, so every
 * export fataled with "Unknown column". The PDF option pulled in TCPDF for
 * a five-column table; dropped in favour of CSV plus the browser's own
 * print-to-PDF, same call as export_user_roles.php.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'manager');

$hasUserId = db_value(
    "SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'user_id'",
    [DB_NAME]
) !== null;

$STATUSES = ['Pending', 'Preparing', 'Ready', 'Completed', 'Cancelled'];
$METHODS  = ['Cash', 'Card', 'Mobile Money'];

$customerF = query('customer');
$statusF   = one_of(query('status'), $STATUSES, '');
$methodF   = one_of(query('payment'), $METHODS, '');
$staffF    = query_int('staff');
$fromDate  = query('from_date');
$toDate    = query('to_date');

$where  = [];
$params = [];
if ($customerF !== '') {
    $where[]  = 'o.customer_name LIKE ?';
    $params[] = '%' . $customerF . '%';
}
if ($statusF !== '') {
    $where[]  = 'o.status = ?';
    $params[] = $statusF;
}
if ($methodF !== '') {
    $where[]  = 'EXISTS (SELECT 1 FROM payments p WHERE p.order_id = o.id AND p.payment_method = ?)';
    $params[] = $methodF;
}
if ($staffF > 0 && $hasUserId) {
    $where[]  = 'o.user_id = ?';
    $params[] = $staffF;
}
if ($fromDate !== '') {
    $where[]  = 'DATE(o.created_at) >= ?';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $where[]  = 'DATE(o.created_at) <= ?';
    $params[] = $toDate;
}
$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$userJoin = $hasUserId ? 'LEFT JOIN users u ON u.id = o.user_id' : '';
$userCol  = $hasUserId ? 'u.name AS waiter_name' : 'NULL AS waiter_name';

$orders = db_all(
    "SELECT o.id, o.order_number, o.customer_name, o.created_at, o.total_amount, o.status, $userCol,
            (SELECT GROUP_CONCAT(DISTINCT payment_method SEPARATOR ', ') FROM payments p WHERE p.order_id = o.id) AS payment_methods
       FROM orders o
       $userJoin
       $whereSql
      ORDER BY o.created_at DESC",
    $params
);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="sales_report.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Order', 'Customer', 'Date', 'Amount', 'Status', 'Payment method(s)', 'Staff']);
$total = 0.0;
foreach ($orders as $o) {
    fputcsv($out, [
        $o['order_number'] ?? ('#' . $o['id']),
        $o['customer_name'],
        date('Y-m-d', strtotime((string) $o['created_at'])),
        number_format((float) $o['total_amount'], 2, '.', ''),
        $o['status'],
        $o['payment_methods'] ?? '',
        $o['waiter_name'] ?? '',
    ]);
    $total += (float) $o['total_amount'];
}
fputcsv($out, []);
fputcsv($out, ['', '', '', number_format($total, 2, '.', ''), '', '', 'Total']);
fclose($out);
