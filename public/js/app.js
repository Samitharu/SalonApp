/* Shared UI behaviour for every area of the app. No dependencies. */
(function () {
    'use strict';

    var doc = document;
    var root = doc.documentElement;
    var csrf = (doc.querySelector('meta[name="csrf-token"]') || {}).content || '';

    // ── Theme ──────────────────────────────────────────────────────────
    // The initial theme is applied by an inline script in <head> (no flash).
    function storedTheme() {
        try { return localStorage.getItem('theme'); } catch (e) { return null; }
    }
    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
        doc.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
            btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
            btn.setAttribute('title', theme === 'dark' ? 'Light mode' : 'Dark mode');
        });
    }
    applyTheme(root.getAttribute('data-theme') || 'light');

    doc.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-theme-toggle]');
        if (!btn) return;
        var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem('theme', next); } catch (err) { /* private mode */ }
        applyTheme(next);
    });

    if (window.matchMedia) {
        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        var onChange = function (ev) { if (!storedTheme()) applyTheme(ev.matches ? 'dark' : 'light'); };
        if (mq.addEventListener) mq.addEventListener('change', onChange);
    }

    // ── Disclosure toggles (mobile nav, admin sidebar) ─────────────────
    doc.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-toggle-target]');
        if (!btn) return;
        var target = doc.getElementById(btn.getAttribute('data-toggle-target'));
        if (!target) return;
        var open = btn.getAttribute('aria-expanded') !== 'true';
        setDisclosure(btn, target, open);
    });

    function setDisclosure(btn, target, open) {
        btn.setAttribute('aria-expanded', String(open));
        if (target.hasAttribute('data-drawer')) {
            target.classList.toggle('is-open', open);
            var backdrop = doc.getElementById(target.id + '-backdrop');
            if (backdrop) backdrop.classList.toggle('is-open', open);
        } else {
            target.hidden = !open;
        }
    }

    doc.addEventListener('click', function (e) {
        var backdrop = e.target.closest('[data-drawer-backdrop]');
        if (!backdrop) return;
        var id = backdrop.getAttribute('data-drawer-backdrop');
        var btn = doc.querySelector('[data-toggle-target="' + id + '"]');
        var target = doc.getElementById(id);
        if (btn && target) setDisclosure(btn, target, false);
    });

    doc.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        doc.querySelectorAll('[data-toggle-target][aria-expanded="true"]').forEach(function (btn) {
            var target = doc.getElementById(btn.getAttribute('data-toggle-target'));
            if (target) { setDisclosure(btn, target, false); btn.focus(); }
        });
    });

    // ── Helpers ────────────────────────────────────────────────────────
    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function setLoading(btn, loading, label) {
        if (!btn) return;
        if (loading) {
            if (!btn.dataset.originalHtml) btn.dataset.originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.classList.add('is-loading');
            btn.innerHTML = '<span class="spinner" aria-hidden="true"></span><span>' + escapeHtml(label || 'Please wait…') + '</span>';
        } else {
            btn.disabled = false;
            btn.classList.remove('is-loading');
            if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
            delete btn.dataset.originalHtml;
        }
    }

    /**
     * JSON request with CSRF. Resolves to { ok, status, data, message }.
     * Never rejects for HTTP errors; network failures give a friendly message.
     */
    function request(method, url, body) {
        var opts = {
            method: method,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        };
        if (method !== 'GET') {
            opts.headers['Content-Type'] = 'application/json';
            opts.headers['X-CSRF-TOKEN'] = csrf;
            opts.body = JSON.stringify(body || {});
        }

        return fetch(url, opts).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                var message = data.error || data.message || '';
                if (res.status === 422 && data.errors) {
                    var first = Object.keys(data.errors)[0];
                    message = data.errors[first][0];
                } else if (res.status === 419) {
                    message = 'Your session expired. Please refresh the page and try again.';
                } else if (res.status === 429) {
                    message = 'Too many attempts. Please wait a minute and try again.';
                } else if (res.status >= 500) {
                    message = 'Something went wrong on our side. Please try again.';
                }
                return { ok: res.ok, status: res.status, data: data, message: message };
            });
        }).catch(function () {
            return { ok: false, status: 0, data: {}, message: 'Network error. Check your connection and try again.' };
        });
    }

    // ── Toasts ─────────────────────────────────────────────────────────
    function toastRegion() {
        var region = doc.getElementById('toast-region');
        if (!region) {
            region = doc.createElement('div');
            region.id = 'toast-region';
            region.className = 'toast-region';
            region.setAttribute('role', 'status');
            region.setAttribute('aria-live', 'polite');
            doc.body.appendChild(region);
        }
        return region;
    }

    function dismissToast(el) {
        el.classList.add('is-leaving');
        setTimeout(function () { el.remove(); }, 200);
    }

    function armToast(el) {
        var close = el.querySelector('.toast-close');
        if (close) close.addEventListener('click', function () { dismissToast(el); });
        var ms = el.classList.contains('toast-error') ? 8000 : 5000;
        setTimeout(function () { if (el.isConnected) dismissToast(el); }, ms);
    }

    function toast(message, type) {
        type = type || 'success';
        var icon = type === 'error' ? 'fa-circle-exclamation' : (type === 'info' ? 'fa-circle-info' : 'fa-circle-check');
        var el = doc.createElement('div');
        el.className = 'toast toast-' + type;
        el.innerHTML = '<i class="fa-solid ' + icon + '" aria-hidden="true"></i><div>' + escapeHtml(message) +
            '</div><button type="button" class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>';
        toastRegion().appendChild(el);
        armToast(el);
    }

    doc.querySelectorAll('.toast').forEach(armToast);

    // ── Confirm & alert dialogs ────────────────────────────────────────
    // UI.confirm({ title, message, details: [[label, value]], button, cancel, tone })
    // UI.alert({ title, message, details, button, tone })
    // tone: danger (default for confirm) | primary | success | warning | error | info
    // Forms: <form data-confirm="Delete this service?" data-confirm-button="Delete">
    var TONES = {
        danger:  { icon: 'fa-triangle-exclamation', btn: 'btn-danger' },
        warning: { icon: 'fa-circle-exclamation',   btn: 'btn-primary' },
        primary: { icon: 'fa-circle-question',      btn: 'btn-primary' },
        success: { icon: 'fa-check',                btn: 'btn-primary' },
        error:   { icon: 'fa-xmark',                btn: 'btn-primary' },
        info:    { icon: 'fa-info',                 btn: 'btn-primary' }
    };
    var uiDialog = null;

    function getUiDialog() {
        if (uiDialog) return uiDialog;
        uiDialog = doc.createElement('dialog');
        uiDialog.className = 'dialog dialog-alert';
        uiDialog.setAttribute('aria-labelledby', 'ui-dialog-title');
        uiDialog.setAttribute('aria-describedby', 'ui-dialog-message');
        uiDialog.innerHTML =
            '<form method="dialog">' +
            '<div class="dialog-alert-body">' +
            '<div class="dialog-icon" data-icon aria-hidden="true"><i class="fa-solid"></i></div>' +
            '<h2 class="dialog-title" id="ui-dialog-title"></h2>' +
            '<div class="dialog-message" id="ui-dialog-message"></div>' +
            '<dl class="kv dialog-details" data-details></dl>' +
            '</div>' +
            '<div class="dialog-footer dialog-footer-split">' +
            '<button type="submit" value="cancel" class="btn" data-cancel>Cancel</button>' +
            '<button type="submit" value="ok" class="btn" data-ok>OK</button>' +
            '</div></form>';
        // Clicking the dimmed backdrop counts as cancel.
        uiDialog.addEventListener('click', function (e) { if (e.target === uiDialog) uiDialog.close('cancel'); });
        doc.body.appendChild(uiDialog);
        return uiDialog;
    }

    function showUiDialog(opts, isAlert) {
        return new Promise(function (resolve) {
            var tone = TONES[opts.tone] ? opts.tone : (isAlert ? 'info' : 'danger');
            var dlg = getUiDialog();

            if (typeof dlg.showModal !== 'function') {
                var text = (opts.title || '') + (opts.message ? '\n' + opts.message : '');
                if (isAlert) { window.alert(text); resolve(true); } else { resolve(window.confirm(text)); }
                return;
            }

            var icon = dlg.querySelector('[data-icon]');
            icon.className = 'dialog-icon tone-' + tone;
            icon.querySelector('i').className = 'fa-solid ' + TONES[tone].icon;
            dlg.querySelector('#ui-dialog-title').textContent = opts.title || (isAlert ? 'Notice' : 'Are you sure?');

            var msg = dlg.querySelector('#ui-dialog-message');
            msg.textContent = opts.message || '';
            msg.hidden = !opts.message;

            var details = dlg.querySelector('[data-details]');
            details.innerHTML = (opts.details || []).map(function (row) {
                return '<div><dt>' + escapeHtml(row[0]) + '</dt><dd>' + escapeHtml(row[1]) + '</dd></div>';
            }).join('');
            details.hidden = !(opts.details && opts.details.length);

            var ok = dlg.querySelector('[data-ok]');
            var cancel = dlg.querySelector('[data-cancel]');
            ok.textContent = opts.button || (isAlert ? 'OK' : 'Confirm');
            ok.className = 'btn ' + TONES[tone].btn;
            cancel.textContent = opts.cancel || 'Cancel';
            cancel.hidden = !!isAlert;
            dlg.querySelector('.dialog-footer').classList.toggle('is-single', !!isAlert);

            var opener = doc.activeElement;
            dlg.returnValue = '';
            dlg.addEventListener('close', function handler() {
                dlg.removeEventListener('close', handler);
                if (opener && opener.focus && opener.isConnected) opener.focus();
                resolve(isAlert ? true : dlg.returnValue === 'ok');
            });
            dlg.showModal();
            // Destructive actions default focus to Cancel; others to the main button.
            (tone === 'danger' && !isAlert ? cancel : ok).focus();
        });
    }

    function confirmAction(opts) { return showUiDialog(opts || {}, false); }
    function alertBox(opts) { return showUiDialog(typeof opts === 'string' ? { message: opts } : (opts || {}), true); }

    doc.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') return;
        e.preventDefault();
        confirmAction({
            title: form.getAttribute('data-confirm'),
            message: form.getAttribute('data-confirm-message'),
            button: form.getAttribute('data-confirm-button') || 'Confirm',
            tone: form.getAttribute('data-confirm-tone')
        }).then(function (yes) {
            if (!yes) return;
            form.dataset.confirmed = '1';
            if (typeof form.requestSubmit === 'function') form.requestSubmit(); else form.submit();
        });
    }, true);

    // ── Prevent double submission ──────────────────────────────────────
    doc.addEventListener('submit', function (e) {
        var form = e.target;
        if (e.defaultPrevented || form.hasAttribute('data-no-lock') || form.method.toLowerCase() === 'get' || form.method === 'dialog') return;
        var btn = form.querySelector('button[type="submit"]:not([formnovalidate]), button:not([type])');
        if (btn) setTimeout(function () { setLoading(btn, true, btn.getAttribute('data-loading') || 'Saving…'); }, 0);
    });

    // Restore buttons when navigating back (bfcache).
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) doc.querySelectorAll('.btn.is-loading').forEach(function (b) { setLoading(b, false); });
    });

    // ── Auto-submit selects ────────────────────────────────────────────
    doc.addEventListener('change', function (e) {
        var el = e.target.closest('[data-autosubmit]');
        if (el && el.form) {
            if (el.form.hasAttribute('data-confirm')) el.form.requestSubmit(); else el.form.submit();
        }
    });

    // ── Password visibility ────────────────────────────────────────────
    doc.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-toggle-password]');
        if (!btn) return;
        var input = doc.getElementById(btn.getAttribute('data-toggle-password'));
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        btn.innerHTML = '<i class="fa-solid ' + (show ? 'fa-eye-slash' : 'fa-eye') + '" aria-hidden="true"></i>';
    });

    // ── Image preview for file inputs ──────────────────────────────────
    doc.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.matches || !input.matches('input[type="file"][data-preview]')) return;
        var img = doc.getElementById(input.getAttribute('data-preview'));
        var file = input.files && input.files[0];
        if (img && file && /^image\//.test(file.type)) {
            img.src = URL.createObjectURL(file);
            img.hidden = false;
        }
    });

    // ── Shrink photos before upload ────────────────────────────────────
    // <input type="file" data-resize="2400" data-max-file="2097152" data-max-total="8388608"
    //        data-max-count="12" data-upload-error="someId">
    // Phone photos (4–8 MB) are scaled down in the browser so they fit the
    // server's upload limits and upload quickly; the server still validates
    // and re-encodes everything. Submit stays disabled while this runs.
    function shrink(file, max, limit) {
        var big = file.size > 1500000 || (limit && file.size > limit);
        if (!/^image\/(jpeg|png|webp)$/.test(file.type) || !window.createImageBitmap) return Promise.resolve(file);
        var type = file.type === 'image/jpeg' ? 'image/jpeg' : 'image/webp';
        var name = file.name.replace(/\.[^.]+$/, '') + (type === 'image/jpeg' ? '.jpg' : '.webp');
        // Smaller and softer on each try until the photo fits the per-file limit.
        var tries = [[1, 0.86], [0.8, 0.8], [0.6, 0.74], [0.45, 0.7]];

        return createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bmp) {
            var base = Math.min(1, max / Math.max(bmp.width, bmp.height));
            if (base === 1 && !big) { bmp.close && bmp.close(); return file; }

            function attempt(k, best) {
                if (k >= tries.length) return best;
                var c = doc.createElement('canvas');
                c.width = Math.max(1, Math.round(bmp.width * base * tries[k][0]));
                c.height = Math.max(1, Math.round(bmp.height * base * tries[k][0]));
                c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
                return new Promise(function (resolve) { c.toBlob(resolve, type, tries[k][1]); }).then(function (blob) {
                    // The browser can't encode this type: keep what we have.
                    if (!blob || blob.type !== type) return best;
                    var out = blob.size < best.size ? new File([blob], name, { type: type, lastModified: Date.now() }) : best;
                    return (!limit || out.size <= limit) ? out : attempt(k + 1, out);
                });
            }
            return attempt(0, file).then(function (out) { bmp.close && bmp.close(); return out; });
        }).catch(function () { return file; });
    }

    function mb(bytes) { return (bytes / 1048576).toFixed(bytes < 10485760 ? 1 : 0) + ' MB'; }

    doc.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.matches || !input.matches('input[type="file"][data-resize]') || input._shrunk) return;
        var form = input.form;
        var buttons = form ? form.querySelectorAll('[type="submit"]') : [];
        var errorEl = input.dataset.uploadError ? doc.getElementById(input.dataset.uploadError) : null;
        var files = Array.prototype.slice.call(input.files || []);
        var maxCount = +input.dataset.maxCount || 0, maxFile = +input.dataset.maxFile || 0, maxTotal = +input.dataset.maxTotal || 0;

        function report(msg) {
            if (errorEl) { errorEl.textContent = msg || ''; errorEl.hidden = !msg; }
            input.setAttribute('aria-invalid', msg ? 'true' : 'false');
            buttons.forEach(function (b) { b.disabled = !!msg; });
        }
        if (!files.length) return report('');
        if (maxCount && files.length > maxCount) return report('Choose up to ' + maxCount + ' photos at a time (you picked ' + files.length + ').');

        buttons.forEach(function (b) { b.disabled = true; b.dataset.label = b.dataset.label || b.innerHTML; b.textContent = 'Preparing photos…'; });
        // Aim each photo at its share of the request limit too, so a full batch fits.
        var perFile = Math.min(maxFile || Infinity, maxTotal ? Math.floor(maxTotal / files.length) : Infinity);
        Promise.all(files.map(function (f) { return shrink(f, +input.dataset.resize || 2400, isFinite(perFile) ? perFile : 0); })).then(function (out) {
            buttons.forEach(function (b) { b.innerHTML = b.dataset.label; });
            var total = 0, tooBig = null;
            out.forEach(function (f) { total += f.size; if (maxFile && f.size > maxFile && !tooBig) tooBig = f; });
            if (window.DataTransfer) {
                try {
                    var dt = new DataTransfer();
                    out.forEach(function (f) { dt.items.add(f); });
                    input._shrunk = true;
                    input.files = dt.files;
                    input._shrunk = false;
                } catch (err) { /* keep the originals */ }
            }
            if (tooBig) return report('“' + tooBig.name + '” is ' + mb(tooBig.size) + ' — the limit is ' + mb(maxFile) + ' per photo.');
            if (maxTotal && total > maxTotal) return report('These photos add up to ' + mb(total) + ' — upload up to ' + mb(maxTotal) + ' at a time. Try fewer photos.');
            report('');
            input.dispatchEvent(new CustomEvent('files-ready', { bubbles: true, detail: { files: out } }));
        });
    });

    window.UI = {
        csrf: csrf,
        escapeHtml: escapeHtml,
        setLoading: setLoading,
        get: function (url) { return request('GET', url); },
        post: function (url, body) { return request('POST', url, body); },
        del: function (url) { return request('DELETE', url, {}); },
        toast: toast,
        confirm: confirmAction,
        alert: alertBox,
        formatTime: function (hhmm) {
            var parts = String(hhmm).split(':');
            var h = parseInt(parts[0], 10);
            return ((h % 12) || 12) + ':' + parts[1] + ' ' + (h >= 12 ? 'PM' : 'AM');
        }
    };
})();
