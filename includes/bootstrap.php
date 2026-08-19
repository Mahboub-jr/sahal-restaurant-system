<?php
/**
 * Single entry point for every page in the application.
 *
 * Put this at the very top of a page, before any output:
 *
 *     <?php
 *     require_once __DIR__ . '/includes/bootstrap.php';
 *     require_login();                 // or require_role('admin','manager')
 *     $title = 'Menu';
 *     include __DIR__ . '/includes/layout/head.php';
 *
 * Replaces the old pattern of including library/conn.php and hoping the
 * session existed.
 */

declare(strict_types=1);

if (defined('APP_BOOTSTRAPPED')) {
    return;
}
define('APP_BOOTSTRAPPED', true);

/* --- 1. Configuration ------------------------------------------------ */
require_once __DIR__ . '/../config/config.php';

/* --- 2. Error handling ----------------------------------------------- */
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

/**
 * Turn genuine warnings into exceptions during development so that silent
 * breakage — the kind that produced BUG-1 — surfaces immediately instead of
 * being written into the middle of the page and ignored.
 *
 * Notices and deprecations are deliberately NOT promoted: they are noisy on
 * a codebase mid-migration and would stop pages that are otherwise working.
 */
if (APP_ENV === 'development') {
    set_error_handler(function (int $severity, string $message, string $file, int $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        $fatal = E_WARNING | E_USER_WARNING | E_RECOVERABLE_ERROR;
        if (($severity & $fatal) === 0) {
            return false;   // let PHP handle notices/deprecations normally
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
}

set_exception_handler(function (Throwable $e): void {
    error_log(sprintf(
        'Uncaught %s: %s in %s:%d',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    if (!headers_sent()) {
        http_response_code(500);
    }

    if (APP_ENV === 'development') {
        echo '<div style="font:14px/1.6 ui-monospace,monospace;background:#fff1f2;'
           . 'border:1px solid #fecdd3;color:#881337;padding:20px;margin:20px;'
           . 'border-radius:12px">'
           . '<strong style="font-size:16px">' . htmlspecialchars(get_class($e)) . '</strong><br>'
           . htmlspecialchars($e->getMessage()) . '<br><br>'
           . '<span style="opacity:.75">' . htmlspecialchars($e->getFile())
           . ' line ' . $e->getLine() . '</span><pre style="white-space:pre-wrap;'
           . 'margin-top:12px;opacity:.7">' . htmlspecialchars($e->getTraceAsString())
           . '</pre></div>';
    } else {
        echo '<h1>Something went wrong</h1><p>The error has been logged.</p>';
    }
});

/* --- 3. Database, helpers, business logic, auth ----------------------- */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/business.php';
require_once __DIR__ . '/auth.php';

/* --- 4. Session ------------------------------------------------------- */
session_boot();

/* --- 5. Security headers --------------------------------------------- */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
}
