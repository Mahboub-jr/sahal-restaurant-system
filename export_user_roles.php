<?php
/**
 * CSV export of users, optionally filtered by role.
 *
 * Replaces the old export_user_roles.php, which had a debug
 * `echo "TCPDF found!"; exit;` left in it above the real code -- meaning
 * every request fataled before ever reaching the export logic below it
 * (a genuine parse error, confirmed with `php -l`). The PDF option pulled
 * in a 27 MB vendored TCPDF library for a three-column table; dropped in
 * favour of CSV, which every spreadsheet app already opens, plus the
 * browser's own print-to-PDF if a PDF file is what's actually wanted.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin');

$role = one_of(query('role'), array_keys(ROLES), '');

$sql    = 'SELECT name, email, role FROM users';
$params = [];
if ($role !== '') {
    $sql .= ' WHERE role = ?';
    $params[] = $role;
}
$sql .= ' ORDER BY id DESC';

$users = db_all($sql, $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="user_roles.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Name', 'Email', 'Role']);
foreach ($users as $u) {
    fputcsv($out, [$u['name'], $u['email'], ROLES[$u['role']] ?? $u['role']]);
}
fclose($out);
