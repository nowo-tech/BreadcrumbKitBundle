(function () {
  'use strict';

  var _cfg = window.__breadcrumbKitDashboard || {};
  var _fw = ((_cfg.cssFramework || 'bootstrap5') + '').trim().toLowerCase();

  function isBootstrapFw(fw) {
    return fw === 'bootstrap' || fw === 'bootstrap5' || fw === 'bootstrap4' || fw === 'tabler';
  }

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
        bodyEl.innerHTML = '<div class="nowo-ui-flash nowo-ui-flash--error alert alert-danger">' + t('errorLoadingForm', 'Could not load the form.') + '</div>';
      });
  }

  // --- Custom-framework modal helpers (non-bootstrap stacks) ---

  function openModal(id, opener) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.add('nowo-ui-modal-open');
    el.removeAttribute('hidden');
    el.setAttribute('aria-hidden', 'false');
    document.body.classList.add('nowo-modal-open');
    // Dispatch synthetic show.bs.modal so existing listeners keep working.
    var ev;
    try {
      ev = new CustomEvent('show.bs.modal', { bubbles: true, cancelable: true });
    } catch (e) {
      ev = document.createEvent('Event');
      ev.initEvent('show.bs.modal', true, true);
    }
    ev.relatedTarget = opener || null;
    el.dispatchEvent(ev);
  }

  function closeModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('nowo-ui-modal-open');
    el.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.nowo-ui-modal-open')) {
      document.body.classList.remove('nowo-modal-open');
    }
  }

  function bindCustomModals() {
    document.addEventListener('click', function (ev) {
      var opener = ev.target.closest('[data-nowo-modal-open]');
      if (opener) {
        var id = opener.getAttribute('data-nowo-modal-open') || '';
        if (!id) {
          var target = opener.getAttribute('data-nowo-modal-target') || '';
          id = target.replace(/^#/, '');
        }
        if (id) openModal(id, opener);
        return;
      }
      var closer = ev.target.closest('[data-nowo-modal-close]');
      if (closer) {
        var modalEl = closer.closest('.nowo-ui-modal-open[id]');
        var closeId = (modalEl && modalEl.id) || closer.getAttribute('data-nowo-modal-close') || '';
        if (closeId) closeModal(closeId);
      }
    });
    // Click directly on the overlay backdrop closes the modal.
    document.addEventListener('click', function (ev) {
      if (ev.target.matches && ev.target.matches('.nowo-ui-modal-open[id]')) {
        closeModal(ev.target.id);
      }
    });
  }

  // --- Bootstrap-style event listeners (work for all frameworks via show.bs.modal) ---

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

    var loading = '<div class="nowo-ui-muted text-center py-4">' + t('loading', 'Loading…') + '</div>';

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

    var loading = '<div class="nowo-ui-muted text-center py-4">' + t('loading', 'Loading…') + '</div>';

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

    var loading = '<div class="nowo-ui-muted text-center py-4">' + t('loading', 'Loading…') + '</div>';
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
    // Re-read config at init time in case the inline script ran after this file.
    _cfg = window.__breadcrumbKitDashboard || {};
    _fw = ((_cfg.cssFramework || 'bootstrap5') + '').trim().toLowerCase();

    if (!isBootstrapFw(_fw)) {
      bindCustomModals();
    }

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
