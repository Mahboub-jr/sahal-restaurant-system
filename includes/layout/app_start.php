<?php
/**
 * Opens a standard authenticated page: head + sidebar + topbar + content.
 *
 * A page becomes:
 *
 *     <?php
 *     require_once __DIR__ . '/includes/bootstrap.php';
 *     require_role('admin', 'manager');
 *     $title = 'Menu';
 *     include __DIR__ . '/includes/layout/app_start.php';
 *     ?>
 *     ... page content ...
 *     <?php include __DIR__ . '/includes/layout/app_end.php'; ?>
 *
 * That replaces the ~40 lines of duplicated head/sidebar/header/footer
 * includes and CSS tags that every old page carried (AUDIT.md §26).
 */

if (!defined('APP_BOOTSTRAPPED')) {
    require_once dirname(__DIR__) . '/bootstrap.php';
}

include __DIR__ . '/head.php';
?>
<div class="app" id="app">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <?php include __DIR__ . '/topbar.php'; ?>

  <main class="content">
    <?php include __DIR__ . '/flash.php'; ?>
