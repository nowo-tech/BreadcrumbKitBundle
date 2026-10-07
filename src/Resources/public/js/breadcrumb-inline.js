(function () {
  'use strict';

  /**
   * Inline breadcrumb editor: opens/closes the <dialog> via data-* hooks only.
   * Hooks: [data-breadcrumb-kit-inline-wrap], [data-bk-inline-open], [data-bk-inline-dialog], [data-bk-inline-close].
   * Uses event delegation so it works regardless of load order and with Turbo-style DOM swaps.
   */
  if (window.__nowoBreadcrumbKitInlineBound) return;
  window.__nowoBreadcrumbKitInlineBound = true;

  document.addEventListener('click', function (ev) {
    var target = ev.target;
    if (!target || !target.closest) return;

    var openBtn = target.closest('[data-bk-inline-open]');
    if (openBtn) {
      var wrap = openBtn.closest('[data-breadcrumb-kit-inline-wrap]');
      var dlg = wrap && wrap.querySelector('[data-bk-inline-dialog]');
      if (dlg && dlg.showModal) dlg.showModal();
      return;
    }

    var closeBtn = target.closest('[data-bk-inline-close]');
    if (closeBtn) {
      var dlgEl = closeBtn.closest('[data-bk-inline-dialog]');
      if (dlgEl && dlgEl.close) dlgEl.close();
    }
  });
})();
