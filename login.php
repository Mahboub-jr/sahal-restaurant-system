<?php
/**
 * Sign-in page.
 *
 * The old version posted to backend/auth.php, which set $_SESSION['user']
 * while every other page read $_SESSION['user_role'] — so role checks were
 * dead everywhere (AUDIT.md C1). Authentication now lives in one place:
 * attempt_login() in includes/auth.php.
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Already signed in? Nothing to do here.
if (is_logged_in()) {
    redirect('index.php');
}

$error = null;
$email = '';

if (is_post()) {
    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $email = post('email');
        $error = attempt_login($email, $_POST['password'] ?? '');

        if ($error === null) {
            flash_success('Welcome back, ' . user_name() . '.');
            header('Location: ' . intended_url('index.php'));
            exit;
        }
    }
}

$title     = 'Sign in';
$bodyClass = 'auth-page';
include __DIR__ . '/includes/layout/head.php';
?>
<div class="auth">

  <!-- Brand panel -->
  <aside class="auth__aside">
    <div class="auth__aside-brand">
      <img class="auth__aside-logo" src="<?= url('images/Sahal_logo.jpeg') ?>" alt=""
           onerror="this.style.display='none'">
      <div>
        <div class="auth__aside-name"><?= e(APP_NAME) ?></div>
        <div style="font-size:.75rem;opacity:.7"><?= e(APP_TAGLINE) ?></div>
      </div>
    </div>

    <div class="auth__aside-copy">
      <h2>Run the floor, the kitchen and the books from one place.</h2>
      <p>Orders, tables, menu, payments and staff — all in a single system.</p>
    </div>

    <div class="auth__aside-foot">
      &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> · Mogadishu, Somalia
    </div>
  </aside>

  <!-- Form -->
  <main class="auth__main">
    <div class="auth__card">

      <h1 class="auth__title">Sign in</h1>
      <p class="auth__sub">Enter your credentials to access the dashboard.</p>

      <?php foreach (take_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> mb-3">
          <i class="bi bi-info-circle"></i>
          <div><?= e($f['message']) ?></div>
        </div>
      <?php endforeach; ?>

      <?php if ($error !== null): ?>
        <div class="alert alert-danger mb-3" role="alert">
          <i class="bi bi-exclamation-octagon-fill"></i>
          <div><?= e($error) ?></div>
        </div>
      <?php endif; ?>

      <form method="post" novalidate data-allow-resubmit>
        <?= csrf_field() ?>

        <div class="mb-3">
          <label class="form-label" for="email">Email address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input class="form-control" type="email" id="email" name="email"
                   value="<?= e($email) ?>" placeholder="you@restaurant.com"
                   autocomplete="username" required autofocus>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="password">Password</label>
          <div class="input-group" style="position:relative">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input class="form-control" type="password" id="password" name="password"
                   placeholder="Your password" autocomplete="current-password" required
                   style="padding-right:2.5rem">
            <button class="auth__toggle-pw" type="button" id="togglePw"
                    aria-label="Show password" tabindex="-1">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <button class="btn btn-primary btn-lg w-100 justify-content-center" type="submit">
          <i class="bi bi-box-arrow-in-right"></i> Sign in
        </button>
      </form>

      <p class="text-muted text-center mt-4 mb-0" style="font-size:.8125rem">
        Forgotten your password? Ask an administrator to reset it from
        <span class="fw-semi">Users</span>.
      </p>
    </div>
  </main>
</div>

<?php
$inlineScript = <<<'JS'
(function () {
  var btn = document.getElementById('togglePw');
  var pw  = document.getElementById('password');
  if (!btn || !pw) return;
  btn.addEventListener('click', function () {
    var show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    btn.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
})();
JS;

include __DIR__ . '/includes/layout/foot.php';
