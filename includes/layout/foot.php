<?php
/**
 * Closing scripts and </html>.
 *
 * Replaces library/script.php, which unconditionally ran
 *     echarts.init(document.getElementById('salesChart'))
 * on every page. Those elements only existed on Vali's demo dashboard, so
 * the call threw on load and killed all subsequent JavaScript — including
 * the sidebar toggles (AUDIT.md C2). It also carried the template author's
 * Google Analytics tag.
 *
 * Page-specific scripts now opt in via $pageScripts / $inlineScript.
 */

$pageScripts  = $pageScripts  ?? [];
$inlineScript = $inlineScript ?? '';
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/app.js') ?>?v=<?= e(APP_VERSION) ?>"></script>

<?php foreach ($pageScripts as $src): ?>
  <script src="<?= e($src) ?>"></script>
<?php endforeach; ?>

<?php if ($inlineScript !== ''): ?>
  <script><?= $inlineScript ?></script>
<?php endif; ?>
</body>
</html>
