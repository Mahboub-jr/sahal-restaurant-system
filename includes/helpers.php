<?php
/**
 * Shared helper functions.
 *
 * Loaded by includes/bootstrap.php.
 */

declare(strict_types=1);

/* =====================================================================
 | Output escaping
 |===================================================================== */

/**
 * Escape a value for HTML output. Use this on EVERY piece of data that
 * came from the database or the user.
 *
 * Named `e` deliberately — short enough that there is no excuse to skip it.
 * AUDIT.md E7 lists the pages that were echoing raw values.
 */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Escape for use inside a JavaScript string or JSON blob.
 */
function ejs($value): string
{
    return json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
}

/* =====================================================================
 | URLs
 |===================================================================== */

/**
 * Build an absolute-from-webroot URL.
 *
 *   url()                -> /Restuarent_system/
 *   url('menu.php')      -> /Restuarent_system/menu.php
 *   url('css/app.css')   -> /Restuarent_system/css/app.css
 *
 * This is what makes assets resolve correctly from any directory depth —
 * the problem described in AUDIT.md §27.
 */
function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

/**
 * URL for an uploaded file, with a cache-busting stamp.
 */
function upload_url(?string $filename, string $subdir = 'menu'): ?string
{
    if ($filename === null || $filename === '') {
        return null;
    }

    // Historical rows store a bare filename that lives directly in uploads/.
    // Newer rows live in uploads/menu/. Support both.
    $nested = UPLOAD_ROOT . DIRECTORY_SEPARATOR . $subdir . DIRECTORY_SEPARATOR . $filename;
    $flat   = UPLOAD_ROOT . DIRECTORY_SEPARATOR . $filename;

    if (is_file($nested)) {
        return url('uploads/' . $subdir . '/' . rawurlencode($filename));
    }
    if (is_file($flat)) {
        return url('uploads/' . rawurlencode($filename));
    }
    return null;
}

/**
 * Redirect and stop. Always exits.
 */
function redirect(string $path): void
{
    // Only allow internal redirects — never send the user to an arbitrary
    // host supplied through a query string.
    if (preg_match('#^https?://#i', $path) === 1) {
        $path = '';
    }
    header('Location: ' . url($path));
    exit;
}

/**
 * True if the given path is the page currently being viewed.
 * Used to highlight the active sidebar link.
 */
function is_current(string $path): bool
{
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    return $current === basename($path);
}

/* =====================================================================
 | Flash messages
 |=====================================================================
 | Survive exactly one redirect, then clear themselves. This replaces the
 | ?msg=updated query-string pattern scattered through the old pages.
 */

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_success(string $m): void { flash('success', $m); }
function flash_error(string $m): void   { flash('danger', $m); }
function flash_warning(string $m): void { flash('warning', $m); }
function flash_info(string $m): void    { flash('info', $m); }

/**
 * Return all pending flash messages and clear them.
 */
function take_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

/* =====================================================================
 | Request helpers
 |===================================================================== */

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * Read a POST field as a trimmed string.
 */
function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_scalar($value) ? trim((string) $value) : $default;
}

function post_int(string $key, int $default = 0): int
{
    return isset($_POST[$key]) && is_numeric($_POST[$key]) ? (int) $_POST[$key] : $default;
}

function post_float(string $key, float $default = 0.0): float
{
    return isset($_POST[$key]) && is_numeric($_POST[$key]) ? (float) $_POST[$key] : $default;
}

function query(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_scalar($value) ? trim((string) $value) : $default;
}

function query_int(string $key, int $default = 0): int
{
    return isset($_GET[$key]) && is_numeric($_GET[$key]) ? (int) $_GET[$key] : $default;
}

/**
 * Constrain a value to a known set. Returns $default if it is not a member.
 *
 * This is the guard that BUG-1 needed: an ENUM column must never receive a
 * value that was not checked against its allowed list.
 */
function one_of($value, array $allowed, $default = null)
{
    return in_array($value, $allowed, true) ? $value : $default;
}

/* =====================================================================
 | Formatting
 |===================================================================== */

/**
 * Format money using the symbol configured in Settings.
 */
function money($amount): string
{
    $symbol = (string) setting('currency_symbol', '$');
    if ($symbol === '') {
        $symbol = '$';
    }
    return $symbol . number_format((float) $amount, 2);
}

/**
 * Read a single value from the settings row, cached per request.
 *
 * Never allowed to take a page down: if the settings table is missing or
 * empty, callers get their default.
 */
function setting(string $key, $default = null)
{
    static $settings = null;

    if ($settings === null) {
        try {
            $settings = db_one('SELECT * FROM settings ORDER BY id LIMIT 1') ?? [];
        } catch (Throwable $e) {
            error_log('settings() failed: ' . $e->getMessage());
            $settings = [];
        }
    }

    $value = $settings[$key] ?? null;
    return ($value === null || $value === '') ? $default : $value;
}

/**
 * "3 minutes ago", "2 days ago".
 */
function time_ago(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '—';
    }

    $diff = time() - $ts;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' d ago';

    return date('j M Y', $ts);
}

/**
 * Bootstrap colour name for an order status.
 */
function status_colour(?string $status): string
{
    switch ((string) $status) {
        case 'Pending':   return 'secondary';
        case 'Confirmed': return 'info';
        case 'Preparing': return 'warning';
        case 'Ready':     return 'primary';
        case 'Served':    return 'info';
        case 'Completed': return 'success';
        case 'Cancelled': return 'danger';
        default:          return 'light';
    }
}

/* =====================================================================
 | Secure image upload
 |=====================================================================
 | Replaces the unchecked move_uploaded_file() calls in menu.php and
 | add-menu.php (AUDIT.md E3), where a file called shell.php would land in
 | uploads/ and execute.
 */

/**
 * Validate and store an uploaded image.
 *
 * Returns ['ok' => true, 'filename' => '...'] or ['ok' => false, 'error' => '...'].
 * A missing/empty upload returns ok with filename null — callers decide
 * whether that is acceptable.
 */
function upload_image(?array $file, string $subdir = 'menu'): array
{
    if ($file === null || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'filename' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'The file is larger than the server allows.',
            UPLOAD_ERR_FORM_SIZE  => 'The file is larger than the form allows.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
        ];
        return ['ok' => false, 'error' => $messages[$file['error']] ?? 'Upload failed.'];
    }

    // 1. Reject anything not actually uploaded via POST — blocks a caller
    //    being tricked into moving an arbitrary local file.
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }

    // 2. Size.
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        return [
            'ok'    => false,
            'error' => 'Image must be smaller than '
                     . round(UPLOAD_MAX_BYTES / 1024 / 1024, 1) . ' MB.',
        ];
    }
    if ($file['size'] === 0) {
        return ['ok' => false, 'error' => 'The file is empty.'];
    }

    // 3. It must genuinely be an image. getimagesize() reads the header, so
    //    a PHP script renamed to .jpg fails here.
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return ['ok' => false, 'error' => 'That file is not a valid image.'];
    }

    $mime    = strtolower((string) $info['mime']);
    $allowed = explode(',', UPLOAD_ALLOWED_MIME);
    if (!in_array($mime, $allowed, true)) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, GIF and WebP images are allowed.'];
    }

    // 4. Extension comes from OUR map, never from the user's filename.
    $extByMime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = $extByMime[$mime] ?? null;
    if ($ext === null) {
        return ['ok' => false, 'error' => 'Unsupported image type.'];
    }

    // 5. Generated filename. Nothing the user supplied survives.
    try {
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    } catch (Exception $e) {
        $filename = date('Ymd_His') . '_' . uniqid('', true) . '.' . $ext;
    }

    $dir = UPLOAD_ROOT . DIRECTORY_SEPARATOR . $subdir;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Could not create the upload folder.'];
    }

    $target = $dir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return ['ok' => false, 'error' => 'Could not save the uploaded image.'];
    }

    @chmod($target, 0644);

    return ['ok' => true, 'filename' => $filename];
}

/**
 * Delete a previously uploaded image. Refuses to touch anything outside
 * the uploads directory.
 */
function delete_upload(?string $filename, string $subdir = 'menu'): void
{
    if ($filename === null || $filename === '') {
        return;
    }

    // basename() strips any '../' an attacker managed to get into the column.
    $filename = basename($filename);

    foreach ([
        UPLOAD_ROOT . DIRECTORY_SEPARATOR . $subdir . DIRECTORY_SEPARATOR . $filename,
        UPLOAD_ROOT . DIRECTORY_SEPARATOR . $filename,
    ] as $path) {
        $real = realpath($path);
        if ($real !== false
            && strpos($real, (string) realpath(UPLOAD_ROOT)) === 0
            && is_file($real)) {
            @unlink($real);
            return;
        }
    }
}
