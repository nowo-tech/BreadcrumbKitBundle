(function () {
  'use strict';

  /**
   * "Collapse on narrow screens" breadcrumb menu (details/summary disclosure, works without this script).
   * Adds: Escape closes the open menu and returns focus to its "…" toggle; a click outside closes it;
   * choosing a link closes it. Hook: details[data-bk-collapse]. Delegated listeners, bound once, so it works
   * with any load order and with Turbo-style DOM swaps. No inline handlers (CSP-safe).
   * A close fades first: data-bk-closing starts the effect and `open` goes false when it ends, with a timer in
   * case transitionend never fires. Without a transition (0ms, or a host without the kit's CSS) it closes at once.
   */
  if (window.__nowoBreadcrumbKitCollapseBound) return;
  window.__nowoBreadcrumbKitCollapseBound = true;

  var SELECTOR = 'details[data-bk-collapse]';
  var CLOSING_ATTR = 'data-bk-closing';
  var MENU_SELECTOR = '.breadcrumb-collapse__menu';
  var SLACK_MS = 50;

  /** @type {WeakMap<HTMLDetailsElement, function(): void>} */
  var closing = new WeakMap();

  /**
   * @param {string} value
   * @return {number} milliseconds
   */
  function toMs(value) {
    var number = parseFloat(value);
    if (isNaN(number)) return 0;
    return /ms\s*$/.test(value) ? number : number * 1000;
  }

  /**
   * @param {Element} element
   * @return {number} the longest transition of the element in its current state, in milliseconds
   */
  function effectMs(element) {
    var style = window.getComputedStyle(element);
    var durations = (style.transitionDuration || '').split(',');
    var delays = (style.transitionDelay || '').split(',');
    return durations.reduce(function (longest, duration, index) {
      return Math.max(longest, toMs(duration) + toMs(delays[index % delays.length] || '0'));
    }, 0);
  }

  /** @param {HTMLDetailsElement} details */
  function closeWithEffect(details) {
    if (closing.has(details)) return;
    details.setAttribute(CLOSING_ATTR, '');
    var menu = details.querySelector(MENU_SELECTOR);
    var ms = menu ? effectMs(menu) : 0;
    var timer;
    var onEnd;
    var finish = function () {
      closing.delete(details);
      window.clearTimeout(timer);
      if (menu) menu.removeEventListener('transitionend', onEnd);
      details.removeAttribute(CLOSING_ATTR);
      details.open = false;
    };
    if (ms <= 0) {
      finish();
      return;
    }
    onEnd = function (ev) {
      if (ev.target === menu) finish();
    };
    closing.set(details, finish);
    menu.addEventListener('transitionend', onEnd);
    timer = window.setTimeout(finish, ms + SLACK_MS);
  }

  /**
   * @param {HTMLDetailsElement} details
   * @param {boolean} focusToggle
   */
  function close(details, focusToggle) {
    if (!details.open) return;
    closeWithEffect(details);
    if (focusToggle) {
      var toggle = details.querySelector('summary');
      if (toggle && toggle.focus) toggle.focus();
    }
  }

  /** @return {HTMLDetailsElement[]} */
  function openMenus() {
    return Array.prototype.slice.call(document.querySelectorAll(SELECTOR + '[open]:not([' + CLOSING_ATTR + '])'));
  }

  // Turbo clones the page right after this event: a cached page must not hold a half-closed menu.
  document.addEventListener('turbo:before-cache', function () {
    Array.prototype.slice.call(document.querySelectorAll(SELECTOR + '[' + CLOSING_ATTR + ']')).forEach(function (details) {
      var finish = closing.get(details);
      if (finish) finish();
    });
  });

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
    // The toggle: an open menu closes through the fade, and a menu fading out finishes and opens again natively.
    var toggle = inside && target.closest('summary');
    if (toggle && toggle.parentNode === inside) {
      if (closing.has(inside)) {
        closing.get(inside)();
      } else if (inside.open) {
        ev.preventDefault();
        close(inside, false);
      }
    }
    openMenus().forEach(function (details) {
      if (details !== inside) close(details, false);
    });
    if (inside && target.closest('a[href]')) close(inside, false);
  });
})();
