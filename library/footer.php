<?php
/**
 * Footer for pages still on the legacy Vali layout.
 *
 * The previous version referenced img/hirgal-logo.pngk — a typo'd extension
 * pointing at a directory that does not exist, so every page rendered a
 * broken image icon (AUDIT.md C6).
 *
 * Converted pages use includes/layout/foot.php instead.
 */
$appName = defined('APP_NAME') ? APP_NAME : 'Restaurant System';
$version = defined('APP_VERSION') ? APP_VERSION : '1.0';
?>
<footer class="app-content-footer" style="padding:1rem 1.5rem;border-top:1px solid #e2e8f0;
        margin-top:2rem;font-size:12px;color:#64748b;display:flex;
        justify-content:space-between;flex-wrap:wrap;gap:.5rem">
  <span>
    <strong style="text-transform:uppercase;letter-spacing:.04em">
      <?= htmlspecialchars($appName) ?>
    </strong>
    &nbsp;·&nbsp; Version <?= htmlspecialchars($version) ?>
  </span>
  <span>&copy; <?= date('Y') ?> — all rights reserved</span>
</footer>
