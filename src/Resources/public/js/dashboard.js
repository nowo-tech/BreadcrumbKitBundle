/*
 * Breadcrumb Kit dashboard (REQ-UI-003): loads the collection / item forms into the UiKit dialogs.
 *
 * An opener is a link to the full-page form with data-nowo-ui-dialog-open="<dialog id>" (opened by UiKit's
 * js/nowo-ui-dialog.js) and data-bk-dialog-url (the same form with ?_partial=1). This script fetches that URL into the
 * dialog's [data-bk-dialog-body] and copies [data-bk-dialog-title] into the dialog title. Without JavaScript (or
 * without nowo-ui-dialog.js) the link opens the full-page form. Forms with data-bk-submit-once submit once.
 * CSP-safe: no inline handlers, text only from data attributes.
 */
(function () {
  'use strict';

  function loadInto(dialog, url) {
    var body = dialog.querySelector('[data-bk-dialog-body]');
    if (!body) return;
    var message = function (key) {
      var p = document.createElement('p');
      p.textContent = body.getAttribute(key) || '';
      body.replaceChildren(p);
    };
    message('data-bk-loading');
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error(String(r.status));
        return r.text();
      })
      .then(function (html) {
        body.innerHTML = html;
        var titled = body.querySelector('[data-bk-dialog-title]');
        var title = dialog.querySelector('#' + CSS.escape(dialog.id) + '-title');
        if (titled && title && titled.getAttribute('data-bk-dialog-title')) {
          title.textContent = titled.getAttribute('data-bk-dialog-title');
        }
        var field = body.querySelector('input:not([type=hidden]), select, textarea');
        if (field) field.focus();
      })
      .catch(function () {
        message('data-bk-error');
      });
  }

  document.addEventListener('click', function (ev) {
    var opener = ev.target instanceof Element ? ev.target.closest('[data-bk-dialog-url]') : null;
    if (!opener || typeof window.nowoUiOpenDialog !== 'function') return;
    var dialog = document.getElementById(opener.getAttribute('data-nowo-ui-dialog-open') || '');
    if (!(dialog instanceof HTMLDialogElement)) return;
    loadInto(dialog, opener.getAttribute('data-bk-dialog-url'));
  });

  document.addEventListener('submit', function (ev) {
    var form = ev.target instanceof HTMLFormElement && ev.target.matches('[data-bk-submit-once]') ? ev.target : null;
    if (!form) return;
    if (form.dataset.bkSubmitting === '1') {
      ev.preventDefault();
      return;
    }
    form.dataset.bkSubmitting = '1';
    var submit = form.querySelector('button[type="submit"]');
    if (submit) submit.disabled = true;
  }, true);
})();
