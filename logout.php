<?php
/**
 * Sign out.
 *
 * POST only. A GET logout can be fired by any <img src="...logout.php"> on
 * any page the user visits, signing them out unexpectedly (AUDIT.md E5).
 * A GET request here just shows a confirmation button instead.
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    csrf_check();
    logout_user();
    flash_success('You have been signed out.');
    redirect('login.php');
}

if (!is_logged_in()) {
    redirect('login.php');
}

$title = 'Sign out';
include __DIR__ . '/includes/layout/head.php';
?>
<div class="denied-screen">
  <div class="denied-card">
    <div class="denied-icon" style="background:var(--warn-bg);color:var(--warn)">
      <i class="bi bi-box-arrow-right"></i>
    </div>
    <h1>Sign out?</h1>
    <p class="text-muted">
      You are signed in as <strong><?= e(user_name()) ?></strong>.
    </p>
    <form method="post" class="denied-actions" data-allow-resubmit>
      <?= csrf_field() ?>
      <button class="btn btn-primary" type="submit">
        <i class="bi bi-box-arrow-right"></i> Yes, sign out
      </button>
      <a class="btn btn-outline-secondary" href="<?= url('index.php') ?>">Cancel</a>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/layout/foot.php'; ?>
