<?php
/**
 * Retired. Merged into manage_users.php, which now has the same role
 * filter and export link this page offered, plus actual editing -- there
 * was never a reason for these to be two separate pages. Kept as a
 * redirect so old bookmarks or links do not 404.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_role('admin');

$role = query('role');
redirect('manage_users.php' . ($role !== '' ? '?role=' . urlencode($role) : ''));
