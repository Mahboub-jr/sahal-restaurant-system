<?php
/**
 * One-time administrator bootstrap.
 *
 * The original version sat in the web root, unguarded, publishing the
 * credentials admin@example.com / admin123 in plain sight (AUDIT.md E8).
 *
 * It now refuses to run once an administrator exists, so it cannot be used
 * to mint accounts on a working system. Delete this file after first use.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$existingAdmins = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'admin'");

$title = 'Create administrator';
include __DIR__ . '/includes/layout/head.php';
?>
<div class="denied-screen">
  <div class="denied-card" style="max-width:520px">

    <?php if ($existingAdmins > 0): ?>

      <div class="denied-icon" style="background:var(--ok-bg);color:var(--ok)">
        <i class="bi bi-shield-check"></i>
      </div>
      <h1>Already set up</h1>
      <p class="text-muted">
        This system has <strong><?= $existingAdmins ?></strong>
        administrator account<?= $existingAdmins === 1 ? '' : 's' ?>.
        This script will not create another.
      </p>
      <p class="text-muted" style="font-size:.8125rem">
        To add staff, sign in and use <strong>Users</strong>.
        Lost the password? Reset it in phpMyAdmin with a hash from
        <code>password_hash()</code>.
      </p>
      <div class="denied-actions">
        <a class="btn btn-primary" href="<?= url('login.php') ?>">
          <i class="bi bi-box-arrow-in-right"></i> Go to sign in
        </a>
      </div>

      <p class="form-hint mt-4 mb-0" style="color:var(--warn)">
        <i class="bi bi-exclamation-triangle"></i>
        Delete <code>create-admin.php</code> — it should not remain on a server.
      </p>

    <?php else: ?>

      <?php
      $created = false;
      $error   = null;
      $email   = '';

      if (is_post() && csrf_valid()) {
          $email    = post('email');
          $name     = post('name', 'Administrator');
          $password = $_POST['password'] ?? '';

          if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
              $error = 'Please enter a valid email address.';
          } elseif (strlen($password) < 8) {
              $error = 'Choose a password of at least 8 characters.';
          } elseif (db_value('SELECT 1 FROM users WHERE email = ?', [$email]) !== null) {
              $error = 'An account with that email already exists.';
          } else {
              db_run(
                  'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
                  [$name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']
              );
              $created = true;
          }
      }
      ?>

      <?php if ($created): ?>
        <div class="denied-icon" style="background:var(--ok-bg);color:var(--ok)">
          <i class="bi bi-check-lg"></i>
        </div>
        <h1>Administrator created</h1>
        <p class="text-muted">You can now sign in with that account.</p>
        <div class="denied-actions">
          <a class="btn btn-primary" href="<?= url('login.php') ?>">
            <i class="bi bi-box-arrow-in-right"></i> Sign in
          </a>
        </div>
        <p class="form-hint mt-4 mb-0" style="color:var(--bad)">
          <i class="bi bi-exclamation-triangle"></i>
          Now delete <code>create-admin.php</code>.
        </p>
      <?php else: ?>
        <div class="denied-icon" style="background:var(--brand-100);color:var(--brand-700)">
          <i class="bi bi-person-plus"></i>
        </div>
        <h1>Create the first administrator</h1>
        <p class="text-muted" style="font-size:.875rem">
          No administrator exists yet. Set one up, then delete this file.
        </p>

        <?php if ($error !== null): ?>
          <div class="alert alert-danger text-start mt-3"><div><?= e($error) ?></div></div>
        <?php endif; ?>

        <form method="post" class="text-start mt-3" data-allow-resubmit>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="name">Full name</label>
            <input class="form-control" type="text" id="name" name="name"
                   value="<?= e(post('name')) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email"
                   value="<?= e($email) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" type="password" id="password" name="password"
                   minlength="8" required>
            <div class="form-hint">At least 8 characters.</div>
          </div>
          <button class="btn btn-primary w-100 justify-content-center" type="submit">
            <i class="bi bi-person-plus"></i> Create administrator
          </button>
        </form>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/layout/foot.php'; ?>
