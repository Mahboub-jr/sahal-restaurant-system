<?php
/**
 * Renders any pending flash messages as dismissible toasts.
 * Included automatically by app_start.php.
 */

$flashes = take_flashes();
if ($flashes === []) {
    return;
}

$icons = [
    'success' => 'bi-check-circle-fill',
    'danger'  => 'bi-exclamation-octagon-fill',
    'warning' => 'bi-exclamation-triangle-fill',
    'info'    => 'bi-info-circle-fill',
];
?>
<div class="toast-stack" id="toastStack">
  <?php foreach ($flashes as $f): ?>
    <?php $type = $f['type'] ?? 'info'; ?>
    <div class="toast-item toast-item--<?= e($type) ?>" role="status">
      <i class="bi <?= e($icons[$type] ?? $icons['info']) ?>"></i>
      <div><?= e($f['message']) ?></div>
      <button type="button" class="toast-item__close" aria-label="Dismiss"
              onclick="this.parentElement.remove()">
        <i class="bi bi-x-lg" style="font-size:.75rem"></i>
      </button>
    </div>
  <?php endforeach; ?>
</div>
