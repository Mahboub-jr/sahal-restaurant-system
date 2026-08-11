<?php
/**
 * Authentication, authorisation and CSRF.
 *
 * Fixes AUDIT.md C1 (the session shape mismatch that left every role check
 * dead) and E1 (no access control on 39 of 41 pages).
 *
 * The session stores ONE canonical shape:
 *
 *     $_SESSION['user'] = ['id','name','email','role']
 *
 * Chosen because backend/auth.php — the handler the login form actually
 * posts to — already wrote $_SESSION['user']. Pages never read that array
 * directly; they call current_user() / user_role(), so the shape can change
 * later without touching every page again.
 */

declare(strict_types=1);

/* =====================================================================
 | Session
 |===================================================================== */

/**
 * Start the session with hardened cookie settings.
 * Called once by bootstrap.php before any output.
 */
function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL,
        'httponly' => true,   // JavaScript cannot read the session cookie
        'secure'   => $https, // only sent over HTTPS when available
        'samesite' => 'Lax',  // blocks the cookie on cross-site POSTs
    ]);

    session_name('RMS_SESSION');
    session_start();

    // Idle timeout — 8 hours, a long restaurant shift.
    $timeout = 8 * 60 * 60;
    if (isset($_SESSION['_last_seen']) && (time() - $_SESSION['_last_seen']) > $timeout) {
        logout_user();
        flash_warning('Your session expired. Please sign in again.');
    }
    $_SESSION['_last_seen'] = time();
}

/* =====================================================================
 | Who is signed in
 |===================================================================== */

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;
    return is_array($user) && isset($user['id']) ? $user : null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function user_id(): ?int
{
    $user = current_user();
    return $user === null ? null : (int) $user['id'];
}

function user_name(): string
{
    $user = current_user();
    return $user === null ? 'Guest' : (string) $user['name'];
}

/**
 * The signed-in user's role key, lowercased. Null when signed out.
 */
function user_role(): ?string
{
    $user = current_user();
    return $user === null ? null : strtolower(trim((string) $user['role']));
}

/**
 * Human-readable role label.
 */
function user_role_label(): string
{
    $role = user_role();
    return $role === null ? '' : (ROLES[$role] ?? ucfirst($role));
}

/**
 * True when the current user holds any of the given roles.
 * 'admin' always passes.
 */
function has_role(...$roles): bool
{
    $current = user_role();
    if ($current === null) {
        return false;
    }
    if ($current === 'admin') {
        return true;
    }

    // Accept has_role('a','b') and has_role(['a','b']).
    $wanted = [];
    foreach ($roles as $role) {
        if (is_array($role)) {
            $wanted = array_merge($wanted, $role);
        } else {
            $wanted[] = $role;
        }
    }

    foreach ($wanted as $role) {
        if ($current === strtolower(trim((string) $role))) {
            return true;
        }
    }
    return false;
}

/* =====================================================================
 | Gates
 |=====================================================================
 | These enforce access on the SERVER. Hiding a sidebar link is presentation,
 | not security — every protected page must call one of these at the top,
 | before any output.
 */

/**
 * Require a signed-in user. Sends guests to the login page and remembers
 * where they were heading.
 */
function require_login(): void
{
    if (is_logged_in()) {
        return;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? null;
    }

    flash_warning('Please sign in to continue.');
    redirect('login.php');
}

/**
 * Require a signed-in user holding one of the given roles.
 */
function require_role(...$roles): void
{
    require_login();

    if (has_role(...$roles)) {
        return;
    }

    http_response_code(403);
    render_denied($roles);
    exit;
}

/**
 * Full-page 403. Kept deliberately plain and honest.
 */
function render_denied(array $roles = []): void
{
    $flat = [];
    foreach ($roles as $role) {
        $flat = array_merge($flat, is_array($role) ? $role : [$role]);
    }
    $needed = array_map(function ($r) {
        return ROLES[$r] ?? ucfirst((string) $r);
    }, $flat);

    $title = 'Access denied';
    include __DIR__ . '/layout/head.php';
    ?>
    <div class="denied-screen">
      <div class="denied-card">
        <div class="denied-icon"><i class="bi bi-shield-lock"></i></div>
        <h1>Access denied</h1>
        <p>
          You are signed in as <strong><?= e(user_name()) ?></strong>
          (<?= e(user_role_label()) ?>), which does not have permission for this page.
        </p>
        <?php if ($needed !== []): ?>
          <p class="text-muted small">
            Required: <?= e(implode(', ', $needed)) ?>
          </p>
        <?php endif; ?>
        <div class="denied-actions">
          <a class="btn btn-primary" href="<?= url('index.php') ?>">
            <i class="bi bi-house me-1"></i> Back to dashboard
          </a>
          <a class="btn btn-outline-secondary" href="<?= url('logout.php') ?>">
            Sign out
          </a>
        </div>
      </div>
    </div>
    <?php
    include __DIR__ . '/layout/foot.php';
}

/* =====================================================================
 | Sign in / out
 |===================================================================== */

/**
 * Verify credentials and start an authenticated session.
 * Returns an error string on failure, or null on success.
 */
function attempt_login(string $email, string $password): ?string
{
    $email = trim($email);

    if ($email === '' || $password === '') {
        return 'Please enter both your email and password.';
    }

    // Simple brute-force brake: 5 failures locks the form for 60 seconds.
    $attempts = $_SESSION['_login_attempts'] ?? 0;
    $lockedAt = $_SESSION['_login_locked_at'] ?? 0;
    if ($attempts >= 5 && (time() - $lockedAt) < 60) {
        return 'Too many failed attempts. Please wait a minute and try again.';
    }

    $user = db_one(
        'SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1',
        [$email]
    );

    // password_verify runs even when no user matched, so the response time
    // does not reveal whether an address exists.
    $hash = $user['password'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';

    if ($user === null || !password_verify($password, $hash)) {
        $_SESSION['_login_attempts']  = $attempts + 1;
        $_SESSION['_login_locked_at'] = time();
        return 'Those credentials do not match our records.';
    }

    // Rehash if PHP's default cost has moved on since the account was made.
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        db_run('UPDATE users SET password = ? WHERE id = ?', [
            password_hash($password, PASSWORD_DEFAULT),
            $user['id'],
        ]);
    }

    // New session id on privilege change — blocks session fixation
    // (AUDIT.md E9).
    session_regenerate_id(true);

    unset($_SESSION['_login_attempts'], $_SESSION['_login_locked_at']);

    $_SESSION['user'] = [
        'id'    => (int) $user['id'],
        'name'  => (string) $user['name'],
        'email' => (string) $user['email'],
        'role'  => strtolower(trim((string) $user['role'])),
    ];

    return null;
}

/**
 * Clear the session completely.
 */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    session_boot();
}

/**
 * Where to send the user after signing in.
 */
function intended_url(string $fallback = 'index.php'): string
{
    $intended = $_SESSION['_intended'] ?? null;
    unset($_SESSION['_intended']);

    if (is_string($intended) && $intended !== '' && strpos($intended, '//') !== 0) {
        return $intended;
    }
    return url($fallback);
}

/* =====================================================================
 | CSRF
 |=====================================================================
 | AUDIT.md E4 — there was not a single token in the codebase, so every
 | state-changing form was forgeable.
 |
 | Usage:
 |   in the form ....  <?= csrf_field() ?>
 |   in the handler ..  csrf_check();
 */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        try {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['_csrf'] = hash('sha256', uniqid('', true) . microtime());
        }
    }
    return $_SESSION['_csrf'];
}

/**
 * Hidden input carrying the token. Drop this inside every POST form.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/**
 * True when the submitted token matches.
 */
function csrf_valid(): bool
{
    $submitted = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($submitted)
        && $submitted !== ''
        && hash_equals((string) ($_SESSION['_csrf'] ?? ''), $submitted);
}

/**
 * Abort the request unless the token is valid. Call at the top of every
 * POST handler.
 */
function csrf_check(): void
{
    if (csrf_valid()) {
        return;
    }

    http_response_code(419);
    flash_error('Your session expired or the form was not submitted from this site. Please try again.');
    redirect(basename($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
}

/**
 * Destructive actions must not be reachable by a link, a crawler or an
 * <img src>. AUDIT.md E5 lists the nine pages that were deleting over GET.
 */
function require_post(): void
{
    if (is_post()) {
        return;
    }
    http_response_code(405);
    exit('This action must be submitted as a POST request.');
}
