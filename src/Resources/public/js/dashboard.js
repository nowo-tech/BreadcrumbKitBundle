(function () {
  'use strict';

  function t(key, fallback) {
    var g = typeof window !== 'undefined' ? window.breadcrumbKitI18n : null;
    return (g && g[key]) ? g[key] : fallback;
  }

  function fetchPartial(url, bodyEl, loadingHtml) {
    if (!bodyEl) return;
    bodyEl.innerHTML = loadingHtml;
    fetch(url)
      .then(function (r) { return r.text(); })
      .then(function (html) { bodyEl.innerHTML = html; })
      .catch(function () {
        bodyEl.innerHTML = '<div class="alert alert-danger">' + t('errorLoadingForm', 'Could not load the form.') + '</div>';
      });
  }

  function bindDeleteModal() {
    var modalEl = document.getElementById('modal-bk-delete');
    var msgEl = document.getElementById('modal-bk-delete-message');
    var form = document.getElementById('form-bk-delete-confirm');
    if (!modalEl || !msgEl || !form) return;

    var deleteCollectionTpl = t('deleteCollectionConfirm', 'Delete this collection and all its items?');
    var deleteItemTpl = t('deleteItemConfirm', 'Delete this item?');

    modalEl.addEventListener('show.bs.modal', function (ev) {
      var btn = ev.relatedTarget;
      if (!btn || !btn.classList.contains('btn-bk-delete')) return;
      form.action = btn.getAttribute('data-url') || '';
      var tokenInput = form.querySelector('input[name="_token"]');
      if (tokenInput) tokenInput.value = btn.getAttribute('data-token') || '';
      var name = btn.getAttribute('data-name') || '';
      var itemId = btn.getAttribute('data-id') || '';
      var itemRoute = btn.getAttribute('data-route') || '';
      if (itemId && itemRoute) {
        msgEl.textContent = deleteItemTpl.replace('%id%', itemId).replace('%route%', itemRoute);
      } else {
        msgEl.textContent = deleteCollectionTpl.replace('%code%', name);
      }
    });
  }

  function bindCollectionFormModal() {
    var modalEl = document.getElementById('modal-bk-collection-form');
    var bodyEl = document.getElementById('modal-bk-collection-form-body');
    var titleEl = document.getElementById('modal-bk-collection-form-label');
    if (!modalEl || !bodyEl) return;

    var loading = '<div class="text-center py-4 text-muted">' + t('loading', 'Loading…') + '</div>';

    modalEl.addEventListener('show.bs.modal', function (ev) {
      var btn = ev.relatedTarget;
      if (!btn || !btn.classList.contains('btn-bk-collection-form')) return;
      var url = btn.getAttribute('data-bk-url');
      var ttl = btn.getAttribute('data-bk-title') || t('defaultCollectionModalTitle', 'Collection');
      if (!url) return;
      if (titleEl) titleEl.textContent = ttl;
      fetchPartial(url, bodyEl, loading);
    });
  }

  function bindItemFormModal() {
    var modalEl = document.getElementById('modal-bk-item-form');
    var bodyEl = document.getElementById('modal-bk-item-form-body');
    var titleEl = document.getElementById('modal-bk-item-form-label');
    if (!modalEl || !bodyEl) return;

    var loading = '<div class="text-center py-4 text-muted">' + t('loading', 'Loading…') + '</div>';

    modalEl.addEventListener('show.bs.modal', function (ev) {
      var btn = ev.relatedTarget;
      if (!btn || !btn.classList.contains('btn-bk-item-form')) return;
      var url = btn.getAttribute('data-bk-url');
      var ttl = btn.getAttribute('data-bk-title') || t('defaultItemModalTitle', 'Item');
      if (!url) return;
      if (titleEl) titleEl.textContent = ttl;
      fetchPartial(url, bodyEl, loading);
    });
  }

  function bindImportModal() {
    var modalEl = document.getElementById('modal-bk-import');
    var bodyEl = document.getElementById('modal-bk-import-body');
    if (!modalEl || !bodyEl) return;

    var loading = '<div class="text-center py-4 text-muted">' + t('loading', 'Loading…') + '</div>';
    var cfg = window.__breadcrumbKitDashboard || {};

    modalEl.addEventListener('show.bs.modal', function (ev) {
      var btn = ev.relatedTarget;
      var url = (btn && btn.getAttribute('data-bk-import-url')) || cfg.importPartialUrl;
      if (!url) return;
      fetchPartial(url, bodyEl, loading);
    });

    bodyEl.addEventListener('submit', function (ev) {
      var form = ev.target.closest('form[data-import-form], form.import-form');
      if (!form || !bodyEl.contains(form)) return;
      if (form.dataset.bkSubmitting === '1') {
        ev.preventDefault();
        return;
      }
      form.dataset.bkSubmitting = '1';
      var sb = form.querySelector('button[type="submit"]');
      if (sb) sb.disabled = true;
    }, true);
  }

  function init() {
    bindDeleteModal();
    bindCollectionFormModal();
    bindItemFormModal();
    bindImportModal();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
