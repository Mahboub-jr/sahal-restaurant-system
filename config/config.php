<?php
/**
 * Application configuration.
 *
 * Loaded by includes/bootstrap.php. Do not include this file directly from a
 * page — always go through bootstrap.php so the database, helpers, session and
 * error handling are all set up together.
 *
 * Target platform: PHP 8.0.30 / MariaDB 10.4 (XAMPP). Avoid 8.1+ syntax
 * (enums, readonly, never) — it will fatal on this server.
 */

declare(strict_types=1);

/* ---------------------------------------------------------------------
 | Local overrides — loaded FIRST
 |---------------------------------------------------------------------
 | config.local.php is gitignored. Anything it defines wins, because every
 | constant below is guarded by defined(). This is how real credentials stay
 | out of version control: copy the DB_* block into config.local.php and
 | edit it there.
 */
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

/**
 * Define a constant only if it has not already been set.
 */
function cfg(string $key, $value): void
{
    if (!defined($key)) {
        define($key, $value);
    }
}

/* ---------------------------------------------------------------------
 | Environment
 |---------------------------------------------------------------------
 | 'development' shows errors on screen. 'production' hides them and logs
 | instead. Flip this one constant when you deploy.
 */
cfg('APP_ENV', 'development');

cfg('APP_NAME', 'Sahal Restaurant');
cfg('APP_TAGLINE', 'Restaurant Management System');
cfg('APP_VERSION', '2.0.0');

/* ---------------------------------------------------------------------
 | Database
 |--------------------------------------------------------------------- */
cfg('DB_HOST', '127.0.0.1');
cfg('DB_NAME', 'restaurant_db');
cfg('DB_USER', 'root');
cfg('DB_PASS', '');
cfg('DB_CHARSET', 'utf8mb4');

/* ---------------------------------------------------------------------
 | Paths
 |---------------------------------------------------------------------
 | APP_ROOT is the filesystem path to the project root, with no trailing
 | slash. Everything server-side is resolved from it, so no page ever needs
 | to guess at '../'.
 */
cfg('APP_ROOT', dirname(__DIR__));
cfg('UPLOAD_ROOT', APP_ROOT . DIRECTORY_SEPARATOR . 'uploads');
cfg('UPLOAD_MENU_DIR', UPLOAD_ROOT . DIRECTORY_SEPARATOR . 'menu');

/* ---------------------------------------------------------------------
 | BASE_URL
 |---------------------------------------------------------------------
 | The single fix for the broken relative-asset problem described in
 | AUDIT.md §27. Derived once by comparing the project root against the
 | web server's document root, so it is correct no matter how deeply the
 | current script is nested.
 |
 |   D:\Xampp\htdocs\Restuarent_system  ->  /Restuarent_system/
 |
 | Every asset and link is then written as:  <?= url('css/app.css') ?>
 */
if (!defined('BASE_URL')) {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT'])
        ? realpath($_SERVER['DOCUMENT_ROOT'])
        : false;
    $appRoot = realpath(APP_ROOT);

    $base = '/';
    if ($docRoot !== false && $appRoot !== false) {
        $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
        $appRoot = rtrim(str_replace('\\', '/', $appRoot), '/');

        if ($appRoot === $docRoot) {
            $base = '/';
        } elseif (strpos($appRoot, $docRoot) === 0) {
            $base = substr($appRoot, strlen($docRoot)) . '/';
        }
    }

    // Fallback for unusual setups (symlinked vhosts, CLI): derive from the
    // running script instead.
    if ($base === '' || $base[0] !== '/') {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
    }

    define('BASE_URL', $base);
}

/* ---------------------------------------------------------------------
 | Uploads
 |--------------------------------------------------------------------- */
cfg('UPLOAD_MAX_BYTES', 2 * 1024 * 1024);          // 2 MB
cfg('UPLOAD_ALLOWED_MIME', 'image/jpeg,image/png,image/gif,image/webp');
cfg('UPLOAD_ALLOWED_EXT', 'jpg,jpeg,png,gif,webp');

/* ---------------------------------------------------------------------
 | Locale
 |--------------------------------------------------------------------- */
date_default_timezone_set('Africa/Mogadishu');

/* ---------------------------------------------------------------------
 | Roles
 |---------------------------------------------------------------------
 | Declared here rather than in the database because they are structural:
 | code branches on them. The `users`.`role` column stores the key.
 |
 | NOTE: the live database currently contains only 'admin' and 'waiter'.
 | The other three are supported by the code and can be assigned from
 | Staff > Users as soon as you need them.
 */
cfg('ROLES', [
    'admin'   => 'Administrator',
    'manager' => 'Manager',
    'cashier' => 'Cashier',
    'waiter'  => 'Waiter',
    'chef'    => 'Chef / Kitchen',
]);
