/*
 * Breadcrumb Kit dashboard (REQ-UI-003): finishing touches on the UiKit remote dialogs that hold the dashboard forms.
 *
 * Openers are UiKit 2.2 remote-dialog links (Action::dialogId + the full-page form URL): js/nowo-ui-dialog.js opens
 * the dialog, fetches the URL (X-Requested-With → the controllers answer with the form partial) into its body and
 * dispatches nowo-ui:dialog-loaded; without JavaScript (or when the request fails) the link opens the full-page form.
 * This script only:
 *   - shows the dialog's loading text while a form is fetched (no stale form from the previous opener),
 *   - copies [data-bk-dialog-title] of the loaded form into the dialog title and focuses its first field,
 *   - submits forms with data-bk-submit-once once.
 * Deprecated (2.8, removed in 3.0): openers with data-bk-dialog-url (DashboardPages::dialogOpener()) are still loaded
 * here for host templates that print them.
 * CSP-safe: no inline handlers, text only from data attributes.
 */
(function () {
  'use strict';

  function bodyOf(dialog) {
    return dialog.querySelector('[data-bk-dialog-body]');
  }

  function message(dialog, key) {
    var body = bodyOf(dialog);
    if (!body) return;
    var p = document.createElement('p');
    p.textContent = body.getAttribute(key) || '';
    body.replaceChildren(p);
  }

  function finish(dialog) {
    var body = bodyOf(dialog);
    if (!body) return;
    var titled = body.querySelector('[data-bk-dialog-title]');
    var title = dialog.querySelector('#' + CSS.escape(dialog.id) + '-title');
    if (titled && title && titled.getAttribute('data-bk-dialog-title')) {
      title.textContent = titled.getAttribute('data-bk-dialog-title');
    }
    var field = body.querySelector('input:not([type=hidden]), select, textarea');
    if (field) field.focus();
  }

  function dialogFor(opener) {
    var dialog = document.getElementById(opener.getAttribute('data-nowo-ui-dialog-open') || '');
    return dialog instanceof HTMLDialogElement && bodyOf(dialog) ? dialog : null;
  }

  // Capture phase: before nowo-ui-dialog.js (bubble phase) starts the fetch, so the loading text never replaces it.
  document.addEventListener('click', function (ev) {
    var opener = ev.target instanceof Element ? ev.target.closest('[data-nowo-ui-dialog-src][data-nowo-ui-dialog-open]') : null;
    var dialog = opener ? dialogFor(opener) : null;
    if (dialog) message(dialog, 'data-bk-loading');
  }, true);

  document.addEventListener('nowo-ui:dialog-loaded', function (ev) {
    if (ev.target instanceof HTMLDialogElement) finish(ev.target);
  });

  // Deprecated data-bk-dialog-url openers (?_partial=1 URL fetched here).
  document.addEventListener('click', function (ev) {
    var opener = ev.target instanceof Element ? ev.target.closest('[data-bk-dialog-url]') : null;
    if (!opener || typeof window.nowoUiOpenDialog !== 'function') return;
    var dialog = dialogFor(opener);
    if (!dialog) return;
    message(dialog, 'data-bk-loading');
    fetch(opener.getAttribute('data-bk-dialog-url'), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error(String(r.status));
        return r.text();
      })
      .then(function (html) {
        bodyOf(dialog).innerHTML = html;
        finish(dialog);
      })
      .catch(function () {
        message(dialog, 'data-bk-error');
      });
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
