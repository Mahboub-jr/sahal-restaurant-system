<?php
/**
 * PHPUnit bootstrap.
 *
 * Deliberately does NOT load includes/bootstrap.php -- that opens a real
 * database connection and starts a session, neither of which a unit test
 * should need. Instead it loads exactly the two dependency-free files
 * under test (helpers.php, business.php) plus the one constant url()
 * needs, so this suite runs with no DB, no XAMPP, and no CI service
 * container required.
 *
 * Anything that needs a live database belongs in tests/Integration, with
 * its own bootstrap -- see that directory's README once it exists.
 */

declare(strict_types=1);

define('BASE_URL', '/Restuarent_system/');

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/business.php';
