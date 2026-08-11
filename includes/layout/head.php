<?php
/**
 * Opening HTML, <head>, and the start of <body>.
 *
 * Replaces library/head.php, which still carried Vali's marketing meta tags,
 * Twitter handles and Open Graph URLs pointing at the template author's blog
 * (AUDIT.md §1.4 / §32).
 *
 * Set $title before including. Optionally $subtitle and $bodyClass.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    require_once dirname(__DIR__) . '/bootstrap.php';
}

$title      = $title      ?? 'Dashboard';
$subtitle   = $subtitle   ?? null;
$bodyClass  = $bodyClass  ?? '';
$pageStyles = $pageStyles ?? [];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light dark">
  <meta name="robots" content="noindex, nofollow">
  <meta name="description" content="<?= e(APP_NAME) ?> — <?= e(APP_TAGLINE) ?>">

  <title><?= e($title) ?> · <?= e(APP_NAME) ?></title>

  <link rel="icon" href="<?= url('images/Sahal_logo.jpeg') ?>" type="image/jpeg">

  <!-- Restore the theme before first paint so there is no white flash -->
  <script>
    (function () {
      try {
        var t = localStorage.getItem('rms-theme');
        if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
          document.documentElement.setAttribute('data-theme', 'dark');
        }
      } catch (e) {}
    })();
  </script>

  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <link href="<?= url('assets/css/app.css') ?>?v=<?= e(APP_VERSION) ?>" rel="stylesheet">

  <?php foreach ($pageStyles as $href): ?>
    <link href="<?= e($href) ?>" rel="stylesheet">
  <?php endforeach; ?>
</head>
<body class="<?= e($bodyClass) ?>">
