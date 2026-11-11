/*
 * Extras (add-ons) dialog shared by the stylist portal and the cashier.
 *
 *   Extras.open({
 *     title, subtitle,
 *     summary,                 // initial bill: { items, base_label, service_name, total_label, locked }
 *     loadUrl,                 // optional GET url returning the summary
 *     addUrl,                  // POST { service_id } or { name, price, quantity }
 *     removeUrl: function (itemId) { return url; },  // DELETE
 *     catalog,                 // [{ id, name, price_label, addon }]
 *     currency,
 *     onChange: function (summary) {}
 *   });
 *
 * Responses may be the summary itself or { appointment: summary }.
 */
(function () {
    'use strict';

    var dlg = null;
    var opts = null;
    var busy = false;
    var esc = function (s) { return UI.escapeHtml(s); };

    function build() {
        dlg = document.createElement('dialog');
        dlg.className = 'dialog dialog-lg';
        dlg.setAttribute('aria-labelledby', 'extras-title');
        dlg.innerHTML =
            '<div class="dialog-header">' +
            '  <div><div class="method-tag"><i class="fa-solid fa-plus" aria-hidden="true"></i> Extras</div>' +
            '  <h2 class="dialog-title" id="extras-title"></h2><p class="text-sm text-muted" data-x="subtitle"></p></div>' +
            '  <button type="button" class="btn btn-ghost btn-icon btn-sm" data-x="close" aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>' +
            '</div>' +
            '<div class="dialog-body stack">' +
            '  <div class="extras-bill" data-x="bill" aria-live="polite"></div>' +
            '  <div class="alert alert-error" data-x="error" role="alert" hidden></div>' +
            '  <div data-x="adder">' +
            '    <div class="row row-between mb-2">' +
            '      <div class="label">Add to bill</div>' +
            '      <div class="segmented" role="tablist" aria-label="Add from">' +
            '        <button type="button" role="tab" class="is-active" aria-selected="true" data-tab="menu">From menu</button>' +
            '        <button type="button" role="tab" aria-selected="false" data-tab="custom">Custom charge</button>' +
            '      </div>' +
            '    </div>' +
            '    <div data-panel="menu">' +
            '      <label class="sr-only" for="extras-search">Search items</label>' +
            '      <div class="input-icon mb-2"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>' +
            '        <input class="input input-sm" id="extras-search" placeholder="Search add-ons and services" autocomplete="off"></div>' +
            '      <div class="catalog-list" data-x="catalog"></div>' +
            '    </div>' +
            '    <form data-panel="custom" hidden novalidate class="stack-sm">' +
            '      <div class="form-grid">' +
            '        <div class="field full"><label class="label" for="extras-name">Description</label>' +
            '          <input class="input" id="extras-name" maxlength="120" placeholder="e.g. Long hair surcharge" autocomplete="off"></div>' +
            '        <div class="field"><label class="label" for="extras-price">Amount</label>' +
            '          <input class="input tabular" id="extras-price" type="number" min="0" step="0.01" inputmode="decimal"></div>' +
            '        <div class="field"><label class="label" for="extras-qty">Quantity</label>' +
            '          <input class="input" id="extras-qty" type="number" min="1" max="20" value="1"></div>' +
            '      </div>' +
            '      <div class="row row-end"><button type="submit" class="btn btn-primary" data-x="customAdd"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add charge</button></div>' +
            '    </form>' +
            '  </div>' +
            '</div>' +
            '<div class="dialog-footer"><button type="button" class="btn btn-primary" data-x="done">Done</button></div>';
        document.body.appendChild(dlg);

        q('close').addEventListener('click', close);
        q('done').addEventListener('click', close);
        dlg.addEventListener('click', function (e) { if (e.target === dlg) close(); });

        dlg.querySelectorAll('[data-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () { switchTab(tab.dataset.tab); });
        });

        dlg.querySelector('#extras-search').addEventListener('input', renderCatalog);

        q('catalog').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-add]');
            if (btn) add({ service_id: +btn.dataset.add }, btn);
        });

        q('bill').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-remove]');
            if (btn) remove(btn.dataset.remove, btn.dataset.name, btn);
        });

        dlg.querySelector('[data-panel="custom"]').addEventListener('submit', function (e) {
            e.preventDefault();
            var name = dlg.querySelector('#extras-name').value.trim();
            var price = dlg.querySelector('#extras-price').value.trim();
            var qty = parseInt(dlg.querySelector('#extras-qty').value, 10) || 1;
            if (!name) { showError('Enter a description for the charge.'); dlg.querySelector('#extras-name').focus(); return; }
            if (price === '' || isNaN(+price) || +price < 0) { showError('Enter a valid amount.'); dlg.querySelector('#extras-price').focus(); return; }
            add({ name: name, price: +price, quantity: qty }, q('customAdd'), function () {
                dlg.querySelector('#extras-name').value = '';
                dlg.querySelector('#extras-price').value = '';
                dlg.querySelector('#extras-qty').value = '1';
            });
        });
    }

    function q(name) { return dlg.querySelector('[data-x="' + name + '"]'); }

    function switchTab(name) {
        dlg.querySelectorAll('[data-tab]').forEach(function (t) {
            var on = t.dataset.tab === name;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', String(on));
        });
        dlg.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== name; });
        (name === 'custom' ? dlg.querySelector('#extras-name') : dlg.querySelector('#extras-search')).focus();
    }

    function showError(msg) {
        var el = q('error');
        el.innerHTML = '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>' + esc(msg) + '</div>';
        el.hidden = !msg;
    }

    function renderBill(s) {
        var rows = '<div class="extras-row"><div><div class="fw-600">' + esc(s.service_name) + '</div><div class="text-xs text-muted">Main service</div></div>' +
            '<div class="tabular fw-600">' + esc(s.base_label) + '</div><span class="extras-row-action"></span></div>';

        if (s.discount_label) {
            rows += '<div class="extras-row"><div><div class="fw-600">' + esc(s.offer_name || 'Offer') + '</div><div class="text-xs text-muted">Offer</div></div>' +
                '<div class="tabular fw-600 text-success">' + esc(s.discount_label) + '</div><span class="extras-row-action"></span></div>';
        }

        (s.items || []).forEach(function (i) {
            rows += '<div class="extras-row"><div><div class="fw-600">' + esc(i.name) + (i.quantity > 1 ? ' <span class="text-muted">× ' + i.quantity + '</span>' : '') + '</div>' +
                '<div class="text-xs text-muted">Extra' + (i.added_by ? ' · added by ' + esc(i.added_by) : '') + '</div></div>' +
                '<div class="tabular fw-600">' + esc(i.line_label) + '</div>' +
                '<span class="extras-row-action">' + (s.locked ? '' :
                    '<button type="button" class="btn btn-ghost btn-icon btn-sm" data-remove="' + i.id + '" data-name="' + esc(i.name) + '" aria-label="Remove ' + esc(i.name) + '"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>') +
                '</span></div>';
        });

        if (!(s.items || []).length) {
            rows += '<div class="extras-empty text-sm text-muted">No extras yet.</div>';
        }

        rows += '<div class="total-row mt-2"><span class="fw-600">Total</span><span class="amount" style="font-size:22px;">' + esc(s.total_label) + '</span></div>';
        q('bill').innerHTML = rows;
        q('adder').hidden = !!s.locked;
        if (s.locked) showError('This bill is closed, so extras can’t be changed.');
    }

    function renderCatalog() {
        var term = dlg.querySelector('#extras-search').value.trim().toLowerCase();
        var list = (opts.catalog || []).filter(function (c) { return !term || c.name.toLowerCase().indexOf(term) !== -1; });
        if (!list.length) {
            q('catalog').innerHTML = '<div class="text-sm text-muted" style="padding:12px 4px;">' +
                ((opts.catalog || []).length ? 'Nothing matches. Use “Custom charge” instead.' : 'No add-ons set up yet. Use “Custom charge”.') + '</div>';
            return;
        }
        var html = '';
        var group = null;
        list.forEach(function (c) {
            var g = c.addon ? 'Add-ons' : 'Services';
            if (g !== group) { html += '<div class="catalog-group">' + g + '</div>'; group = g; }
            html += '<button type="button" class="catalog-item" data-add="' + c.id + '"><span>' + esc(c.name) + '</span>' +
                '<span class="tabular">' + esc(c.price_label) + ' <i class="fa-solid fa-plus" aria-hidden="true"></i></span></button>';
        });
        q('catalog').innerHTML = html;
    }

    function apply(res) {
        var s = res.data.appointment || res.data;
        renderBill(s);
        if (opts.onChange) opts.onChange(s);
    }

    function add(body, btn, after) {
        if (busy) return;
        busy = true;
        showError('');
        if (btn) btn.disabled = true;
        UI.post(opts.addUrl, body).then(function (res) {
            busy = false;
            if (btn) btn.disabled = false;
            if (!res.ok) { showError(res.message || 'Could not add this item.'); return; }
            apply(res);
            if (after) after();
            UI.toast('Added to the bill.');
        });
    }

    function remove(id, name, btn) {
        UI.confirm({ title: 'Remove “' + name + '”?', message: 'It will be taken off this bill.', button: 'Remove' }).then(function (yes) {
            if (!yes) return;
            btn.disabled = true;
            UI.del(opts.removeUrl(id)).then(function (res) {
                if (!res.ok) { btn.disabled = false; showError(res.message || 'Could not remove this item.'); return; }
                apply(res);
            });
        });
    }

    function close() { if (dlg && dlg.open) dlg.close(); }

    function open(o) {
        opts = o;
        if (!dlg) build();
        dlg.querySelector('#extras-title').textContent = o.title || 'Extras';
        q('subtitle').textContent = o.subtitle || '';
        dlg.querySelector('#extras-search').value = '';
        showError('');
        switchTab('menu');
        renderCatalog();

        if (o.summary) {
            renderBill(o.summary);
        } else {
            q('bill').innerHTML = '<div class="loading-row"><span class="spinner" aria-hidden="true"></span> Loading bill…</div>';
            UI.get(o.loadUrl).then(function (res) {
                if (!res.ok) { showError(res.message || 'Could not load this bill.'); q('bill').innerHTML = ''; return; }
                renderBill(res.data.appointment || res.data);
            });
        }

        dlg.showModal();
        dlg.querySelector('#extras-search').focus();
    }

    window.Extras = { open: open, close: close };
})();
