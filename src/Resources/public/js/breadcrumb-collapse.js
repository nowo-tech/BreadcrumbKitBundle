(function () {
  'use strict';

  /**
   * "Collapse on narrow screens" breadcrumb menu (details/summary disclosure, works without this script).
   * Adds: Escape closes the open menu and returns focus to its "…" toggle; a click outside closes it;
   * choosing a link closes it. Hook: details[data-bk-collapse]. Delegated listeners, bound once, so it works
   * with any load order and with Turbo-style DOM swaps. No inline handlers (CSP-safe).
   */
  if (window.__nowoBreadcrumbKitCollapseBound) return;
  window.__nowoBreadcrumbKitCollapseBound = true;

  var SELECTOR = 'details[data-bk-collapse]';

  /**
   * @param {HTMLDetailsElement} details
   * @param {boolean} focusToggle
   */
  function close(details, focusToggle) {
    if (!details.open) return;
    details.open = false;
    if (focusToggle) {
      var toggle = details.querySelector('summary');
      if (toggle && toggle.focus) toggle.focus();
    }
  }

  /** @return {HTMLDetailsElement[]} */
  function openMenus() {
    return Array.prototype.slice.call(document.querySelectorAll(SELECTOR + '[open]'));
  }

  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape' && ev.key !== 'Esc') return;
    var menus = openMenus();
    if (menus.length === 0) return;
    menus.forEach(function (details) {
      close(details, true);
    });
    ev.preventDefault();
  });

  document.addEventListener('click', function (ev) {
    var target = ev.target;
    var inside = target && target.closest ? target.closest(SELECTOR) : null;
    openMenus().forEach(function (details) {
      if (details !== inside) close(details, false);
    });
    if (inside && target.closest('a[href]')) close(inside, false);
  });
})();
