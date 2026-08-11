/* =====================================================================
   Sahal Restaurant — application shell behaviour

   Deliberately small and defensive. The old library/script.php crashed on
   every page because it assumed chart elements existed; nothing here runs
   without first checking that its target is present.
   ===================================================================== */

(function () {
  'use strict';

  var app       = document.getElementById('app');
  var sidebar   = document.getElementById('sidebar');
  var backdrop  = document.getElementById('sidebarBackdrop');
  var toggleBtn = document.getElementById('sidebarToggle');

  var DESKTOP = 992;
  var isDesktop = function () { return window.innerWidth >= DESKTOP; };

  /* ---------------------------------------------------------------
     Sidebar
     - desktop: collapse to icons, remembered across visits
     - mobile:  slide-over drawer with a backdrop
     --------------------------------------------------------------- */
  function openDrawer() {
    if (!app) return;
    app.classList.add('is-drawer-open');
    if (backdrop) backdrop.classList.add('is-visible');
    document.body.style.overflow = 'hidden';
  }

  function closeDrawer() {
    if (!app) return;
    app.classList.remove('is-drawer-open');
    if (backdrop) backdrop.classList.remove('is-visible');
    document.body.style.overflow = '';
  }

  function toggleCollapsed() {
    if (!app) return;
    app.classList.toggle('is-collapsed');
    try {
      localStorage.setItem('rms-sidebar', app.classList.contains('is-collapsed') ? 'collapsed' : 'expanded');
    } catch (e) { /* private browsing */ }
  }

  if (app) {
    try {
      if (localStorage.getItem('rms-sidebar') === 'collapsed' && isDesktop()) {
        app.classList.add('is-collapsed');
      }
    } catch (e) { /* ignore */ }
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      if (isDesktop()) {
        toggleCollapsed();
      } else if (app && app.classList.contains('is-drawer-open')) {
        closeDrawer();
      } else {
        openDrawer();
      }
    });
  }

  if (backdrop) backdrop.addEventListener('click', closeDrawer);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeDrawer();
  });

  // Close the drawer after tapping a link on mobile.
  if (sidebar) {
    sidebar.addEventListener('click', function (e) {
      var link = e.target.closest('a.sidebar__link');
      if (link && !isDesktop()) closeDrawer();
    });
  }

  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      if (isDesktop()) closeDrawer();
    }, 150);
  });

  /* ---------------------------------------------------------------
     Theme
     --------------------------------------------------------------- */
  var themeBtn = document.getElementById('themeToggle');

  function paintThemeIcon() {
    var icon = document.querySelector('[data-theme-icon]');
    if (!icon) return;
    var dark = document.documentElement.getAttribute('data-theme') === 'dark';
    icon.className = dark ? 'bi bi-sun' : 'bi bi-moon-stars';
    icon.setAttribute('data-theme-icon', '');
  }

  paintThemeIcon();

  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      var root = document.documentElement;
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('rms-theme', next); } catch (e) {}
      paintThemeIcon();
    });
  }

  /* ---------------------------------------------------------------
     Toasts — fade out after a while, but never error messages, which
     the user may need to read carefully.
     --------------------------------------------------------------- */
  var stack = document.getElementById('toastStack');
  if (stack) {
    Array.prototype.forEach.call(stack.children, function (toast, i) {
      if (toast.classList.contains('toast-item--danger')) return;
      setTimeout(function () {
        toast.style.transition = 'opacity .4s, transform .4s';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(24px)';
        setTimeout(function () { toast.remove(); }, 400);
      }, 5000 + i * 400);
    });
  }

  /* ---------------------------------------------------------------
     Confirm-before-submit for destructive forms.

     Usage:  <form method="post" data-confirm="Delete this item?">
     Every delete is a POST form, so this replaces the old
     ?delete=N links that a crawler could trigger (AUDIT.md E5).
     --------------------------------------------------------------- */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      e.preventDefault();
      return;
    }

    // Guard against double submission.
    var submitter = form.querySelector('[type="submit"]');
    if (submitter && !form.hasAttribute('data-allow-resubmit')) {
      setTimeout(function () {
        submitter.disabled = true;
        submitter.style.opacity = '.65';
      }, 0);
    }
  });

  /* ---------------------------------------------------------------
     Image preview for file inputs.
     Usage: <input type="file" data-preview="#previewImg">
     --------------------------------------------------------------- */
  document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input || input.type !== 'file') return;

    var selector = input.getAttribute('data-preview');
    if (!selector) return;

    var target = document.querySelector(selector);
    var file = input.files && input.files[0];
    if (!target || !file) return;

    if (!/^image\//.test(file.type)) {
      window.alert('Please choose an image file.');
      input.value = '';
      return;
    }

    var reader = new FileReader();
    reader.onload = function (ev) {
      target.src = ev.target.result;
      target.style.display = '';
      var placeholder = target.parentElement && target.parentElement.querySelector('[data-preview-placeholder]');
      if (placeholder) placeholder.style.display = 'none';
    };
    reader.readAsDataURL(file);
  });

  /* ---------------------------------------------------------------
     Auto-init tooltips where present.
     --------------------------------------------------------------- */
  if (window.bootstrap && window.bootstrap.Tooltip) {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      new window.bootstrap.Tooltip(el);
    });
  }
})();
