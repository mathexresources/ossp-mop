/* ============================================================
   OSSP MOP — app.js
   Global UI helpers (loaded on every page)
   ============================================================ */

(function () {
    'use strict';

    /* ── 1. Flash message auto-dismiss (4 s) ─────────────── */
    function initFlashDismiss() {
        document.querySelectorAll('.alert-auto-dismiss').forEach(function (el) {
            setTimeout(function () {
                try {
                    bootstrap.Alert.getOrCreateInstance(el).close();
                } catch (e) {
                    el.style.display = 'none';
                }
            }, 4000);
        });
    }

    /* ── 2. Submit button loading state ──────────────────── */
    function initSubmitLoading() {
        document.querySelectorAll('form[data-loading]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('[type="submit"]');
                if (!btn) return;
                // Avoid double-trigger on validation failure (browser native)
                if (btn.dataset.loading) return;
                btn.dataset.loading = '1';
                btn.disabled = true;
                var originalHtml = btn.innerHTML;
                btn.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
                    'Processing\u2026';
                // Safety reset after 12 s (handles errors / non-redirect responses)
                setTimeout(function () {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    delete btn.dataset.loading;
                }, 12000);
            });
        });
    }

    /* ── 3. Clickable table rows ──────────────────────────── */
    function initRowLinks() {
        document.querySelectorAll('tr[data-href]').forEach(function (row) {
            row.addEventListener('click', function (e) {
                if (e.target.closest('a, button, form, input, select, label')) return;
                window.location.href = row.dataset.href;
            });
        });
    }

    /* ── Boot ─────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        initFlashDismiss();
        initSubmitLoading();
        initRowLinks();
    });
})();
