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
    'reports.php'           => ['admin', 'manager'],
    'export_report.php'     => ['admin', 'manager'],
    'export_user_roles.php' => ['admin'],

    'manage_users.php'      => ['admin'],
    'user_roles.php'        => ['admin'],
    'settings.php'          => ['admin'],
];

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$allowedRoles = $legacyAccess[$currentPage] ?? ['admin', 'manager'];

require_role($allowedRoles);
