@extends('layouts.portal')
@section('title', 'Cashier')
@section('mark')<i class="fa-solid fa-cash-register" style="font-size:15px;"></i>@endsection
@section('who', 'Cashier station')
@section('who_sub', now()->format('l, j F Y'))
@section('container_class', '')

@section('header_actions')
    <form method="POST" action="{{ route('cashier.lock') }}" data-no-lock>
        @csrf
        <button type="submit" class="btn btn-ghost btn-sm" title="{{ $isAdmin ? 'Back to admin' : 'Lock station' }}">
            <i class="fa-solid {{ $isAdmin ? 'fa-arrow-left' : 'fa-lock' }}" aria-hidden="true"></i><span class="hide-sm"> {{ $isAdmin ? 'Admin' : 'Lock' }}</span>
        </button>
    </form>
@endsection

@section('content')
<div class="cashier-grid">
    {{-- Lookup --}}
    <div class="stack no-print">
        <section class="card">
            <div class="card-header">
                <div>
                    <h1 class="card-title" style="font-size:17px;">Find a bill</h1>
                    <div class="card-subtitle">Scan the customer's QR or enter the job number.</div>
                </div>
                <button type="button" class="btn btn-sm" id="camToggle" aria-pressed="false"><i class="fa-solid fa-camera" aria-hidden="true"></i> Camera</button>
            </div>
            <div class="card-body stack">
                <div class="scanner" id="scannerBox" hidden>
                    <div id="qrReader"></div>
                    <div class="scanner-frame" id="scanFrame" hidden><span></span></div>
                    <div class="scanner-state" id="scanState"><span class="spinner" aria-hidden="true"></span><span>Starting camera…</span></div>
                </div>
                <form id="lookupForm" class="row" novalidate data-no-lock>
                    <label class="sr-only" for="lookupRef">Job number or booking reference</label>
                    <input class="input input-mono grow" id="lookupRef" placeholder="J3KFX or AB12CD34" maxlength="20" autocomplete="off" autocapitalize="characters" autofocus aria-describedby="lookupError">
                    <button type="submit" class="btn btn-primary" id="lookupBtn">Find</button>
                </form>
                <div class="alert alert-error" id="lookupError" role="alert" hidden></div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Waiting to pay</h2>
                <span class="badge badge-pending no-dot">{{ $awaiting->count() }}</span>
            </div>
            @if($awaiting->isEmpty())
                <div class="card-body text-sm text-muted">No finished jobs waiting for payment.</div>
            @else
                <ul class="timeline">
                    @foreach($awaiting as $a)
                        <li style="grid-template-columns:76px minmax(0,1fr) auto;">
                            <div class="time"><span class="chip-code">{{ $a->job_number }}</span></div>
                            <div style="min-width:0;">
                                <div class="fw-600 truncate">{{ $a->customer->name ?? '—' }} <span class="tabular text-muted">· {{ money($a->total()) }}</span></div>
                                <div class="text-sm text-muted truncate">{{ $a->service->name ?? '—' }}@if($a->items->isNotEmpty()) + {{ $a->items->sum('quantity') }} {{ \Illuminate\Support\Str::plural('extra', $a->items->sum('quantity')) }}@endif · {{ $a->staff->name ?? '—' }}</div>
                            </div>
                            <button type="button" class="btn btn-soft btn-sm" data-lookup="{{ $a->job_number ?: $a->appointment_no }}">Open</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if($paidToday->isNotEmpty())
            <section class="card">
                <div class="card-header"><h2 class="card-title">Paid today</h2></div>
                <ul class="timeline">
                    @foreach($paidToday as $a)
                        <li style="grid-template-columns:76px minmax(0,1fr) auto;">
                            <div class="time">{{ $a->paid_at->format('g:i A') }}</div>
                            <div class="truncate">{{ $a->customer->name ?? '—' }} <span class="text-muted">· {{ $a->service->name ?? '' }}</span></div>
                            <span class="tabular fw-600">{{ money($a->total()) }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    {{-- Bill / receipt --}}
    <section class="card" id="bill" aria-live="polite">
        <div class="empty" id="billEmpty">
            <div class="empty-icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></div>
            <h3>No bill open</h3>
            <p>Look up a job number or scan a booking QR to take payment.</p>
        </div>

        <div id="billBody" hidden>
            <div class="card-header">
                <div>
                    <div class="card-title">{{ setting('salon_name', 'Salon') }}</div>
                    <div class="card-subtitle" id="billDate"></div>
                </div>
                <span id="billStatus"></span>
            </div>
            <div class="card-body stack">
                <div class="grid-2">
                    <div class="panel-muted"><div class="text-xs text-muted">Job number</div><div class="mono fw-700" style="font-size:20px;" data-b="job_number"></div></div>
                    <div class="panel-muted"><div class="text-xs text-muted">Booking ref</div><div class="mono fw-600" style="font-size:16px;margin-top:4px;" data-b="appointment_no"></div></div>
                </div>
                <dl class="kv">
                    <div><dt>Customer</dt><dd data-b="customer_name"></dd></div>
                    <div><dt>Phone</dt><dd data-b="customer_phone"></dd></div>
                    <div><dt>Stylist</dt><dd data-b="stylist_name"></dd></div>
                    <div><dt>Appointment</dt><dd><span data-b="date"></span>, <span data-b="time"></span></dd></div>
                </dl>

                <div>
                    <div class="row row-between mb-2">
                        <span class="label">Charges</span>
                        <button type="button" class="btn btn-soft btn-sm no-print" id="billExtras"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add extra</button>
                    </div>
                    <div class="bill-lines" id="billLines"></div>
                </div>
                <div class="total-row"><span class="fw-600">Total</span><span class="amount" data-b="total_label"></span></div>

                {{-- Advances and split payments received so far --}}
                <div id="billPayments" hidden>
                    <div class="label mb-2">Payments</div>
                    <div class="bill-lines" id="billPaymentLines"></div>
                    <div class="total-row balance-row"><span class="fw-700">Balance due</span><span class="amount" id="billBalance"></span></div>
                </div>

                <div id="payArea" class="no-print">
                    <div class="row row-between mb-2">
                        <span class="label" id="payAreaLabel">Take payment</span>
                        <button type="button" class="btn btn-ghost btn-sm" id="advanceBtn" hidden><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i> Record advance</button>
                    </div>
                    <div class="pay-methods pay-methods-4">
                        @foreach(\App\Models\Payment::METHODS as $key => $label)
                            <button type="button" class="pay-method" data-pay="{{ $key }}"><i class="fa-solid {{ \App\Models\Payment::ICONS[$key] }}" aria-hidden="true"></i>{{ $label }}</button>
                        @endforeach
                    </div>
                    <p class="hint mt-2">Paying with more than one method? Pick one, then use “Split”.</p>
                </div>

                <div id="paidArea" hidden>
                    <div class="alert alert-success"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div><strong>Paid</strong> <span id="paidInfo"></span></div></div>
                    <dl class="kv mt-2" id="tenderRows" hidden>
                        <div><dt>Cash received</dt><dd class="tabular" id="tenderReceived"></dd></div>
                        <div><dt>Change given</dt><dd class="tabular" id="tenderChange"></dd></div>
                    </dl>
                </div>
                <p class="print-only text-center text-sm" style="margin-top:16px;">Thank you for visiting!</p>
                <div class="print-only receipt-qr" id="receiptQrWrap" hidden>
                    <div id="receiptQr"></div>
                    <div class="text-xs">Scan to rate your visit</div>
                </div>
                <p class="print-only text-center text-xs" id="receiptRateText">Rate your visit: {{ preg_replace('#^https?://#', '', route('review.create')) }}</p>
            </div>
            <div class="card-footer row row-between no-print">
                <button type="button" class="btn" id="newBill"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Next customer</button>
                <button type="button" class="btn btn-ink" id="printBill"><i class="fa-solid fa-print" aria-hidden="true"></i> Print receipt</button>
            </div>
        </div>
    </section>
</div>

{{-- ── Payment confirmation ─────────────────────────────────────── --}}
<dialog class="dialog" id="payDialog" aria-labelledby="payTitle">
    <form id="payForm" novalidate data-no-lock>
        <div class="dialog-header">
            <div>
                <div class="method-tag" id="payMethodTag"></div>
                <h2 class="dialog-title" id="payTitle">Confirm payment</h2>
            </div>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-pay-cancel aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>

        <div class="dialog-body stack">
            <div class="pay-amount"><span id="payAmountLabel">Balance due</span><strong id="payAmount"></strong></div>
            <div class="text-sm text-muted" id="payContext"></div>

            <dl class="kv">
                <div><dt>Customer</dt><dd id="payCustomer"></dd></div>
                <div><dt>Job / reference</dt><dd class="mono" id="payJob"></dd></div>
            </dl>

            {{-- One line per method; "Split" adds another --}}
            <div class="pay-lines" id="payLinesEdit"></div>
            <button type="button" class="btn btn-soft btn-sm" id="addLine"><i class="fa-solid fa-plus" aria-hidden="true"></i> Split with another method</button>

            <div class="change-row" id="remainingRow" aria-live="polite"><span id="remainingLabel">Remaining</span><strong id="remainingAmount">—</strong></div>

            <div class="field" id="advanceNoteField" hidden>
                <label class="label" for="payNote">Note <span class="optional">(optional)</span></label>
                <input class="input" id="payNote" maxlength="255" placeholder="e.g. bridal deposit, paid by sister">
            </div>

            <div class="alert alert-error" id="payError" role="alert" hidden></div>
        </div>

        <div class="dialog-footer">
            <button type="button" class="btn" data-pay-cancel>Cancel</button>
            <button type="submit" class="btn btn-success btn-lg" id="payConfirm">Confirm payment</button>
        </div>
    </form>
</dialog>

{{-- ── Payment received ─────────────────────────────────────────── --}}
<dialog class="dialog dialog-alert" id="paidDialog" aria-labelledby="paidTitle" aria-describedby="paidMessage">
    <div class="dialog-alert-body">
        <div class="dialog-icon tone-success" aria-hidden="true"><i class="fa-solid fa-check"></i></div>
        <h2 class="dialog-title" id="paidTitle">Payment received</h2>
        <div class="paid-amount" id="paidAmount"></div>
        <p class="dialog-message" id="paidMessage"></p>
        <div class="change-row mt-4" id="paidChange" hidden><span>Give change</span><strong id="paidChangeAmount"></strong></div>
        <div class="paid-qr" id="paidQrWrap" hidden>
            <div id="paidQr" aria-hidden="true"></div>
            <div class="text-sm"><strong>Ask for a review</strong><br><span class="text-muted">The customer can scan this with their phone to rate the visit. It’s also printed on the receipt.</span></div>
        </div>
    </div>
    <div class="dialog-footer dialog-footer-split">
        <button type="button" class="btn" id="paidPrint"><i class="fa-solid fa-print" aria-hidden="true"></i> Print receipt</button>
        <button type="button" class="btn btn-primary" id="paidNext">Next customer</button>
    </div>
</dialog>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<script src="{{ asset_v('js/qr-scanner.js') }}" defer></script>
<script src="{{ asset_v('js/extras.js') }}" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var routes = { lookup: @json(route('cashier.lookup')), pay: @json(url('/cashier/pay')), appts: @json(url('/cashier/appointments')), receipt: @json(url('/cashier/receipt')) };
    var catalog = @json($catalog);
    var currency = @json(setting('currency', 'LKR'));
    var current = null;
    var input = document.getElementById('lookupRef');
    var lookupError = document.getElementById('lookupError');
    var box = document.getElementById('scannerBox');
    var stateEl = document.getElementById('scanState');
    var frame = document.getElementById('scanFrame');
    var camBtn = document.getElementById('camToggle');
    var $ = function (id) { return document.getElementById(id); };

    function fmt(n) {
        return currency + ' ' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ── Scanner ────────────────────────────────────────────────────────
    var scanner = QrScanner.create('qrReader', {
        onCode: function (ref) { input.value = ref; return lookup(ref); },
        onState: function (s, msg) {
            stateEl.hidden = s === 'live';
            frame.hidden = s !== 'live';
            if (s === 'starting') stateEl.innerHTML = '<span class="spinner" aria-hidden="true"></span><span>' + UI.escapeHtml(msg) + '</span>';
            if (s === 'error') stateEl.innerHTML = '<i class="fa-solid fa-camera-rotate" style="font-size:24px;" aria-hidden="true"></i><span>' + UI.escapeHtml(msg) + '</span>';
        }
    });

    camBtn.addEventListener('click', function () {
        var on = box.hidden;
        box.hidden = !on;
        camBtn.setAttribute('aria-pressed', String(on));
        if (on) scanner.start(); else scanner.stop();
    });

    // ── Bill ───────────────────────────────────────────────────────────
    function set(key, value) {
        document.querySelectorAll('[data-b="' + key + '"]').forEach(function (el) { el.textContent = value == null ? '—' : value; });
    }

    // Itemised lines: main service + each extra.
    function linesHtml(a) {
        var esc = UI.escapeHtml;
        var html = '<div class="extras-row"><div><div class="fw-600">' + esc(a.service_name) + '</div><div class="text-xs text-muted">Service</div></div>' +
            '<div class="tabular fw-600">' + esc(a.base_label) + '</div></div>';
        if (a.discount_label) {
            html += '<div class="extras-row"><div><div class="fw-600">' + esc(a.offer_name || 'Offer') + '</div><div class="text-xs text-muted">Offer</div></div>' +
                '<div class="tabular fw-600 text-success">' + esc(a.discount_label) + '</div></div>';
        }
        (a.items || []).forEach(function (i) {
            html += '<div class="extras-row"><div><div class="fw-600">' + esc(i.name) + (i.quantity > 1 ? ' <span class="text-muted">× ' + i.quantity + '</span>' : '') + '</div>' +
                '<div class="text-xs text-muted">Extra' + (i.quantity > 1 ? ' · ' + esc(fmt(i.price)) + ' each' : '') + '</div></div>' +
                '<div class="tabular fw-600">' + esc(i.line_label) + '</div></div>';
        });
        return html;
    }

    function render(a) {
        current = a;
        ['job_number', 'appointment_no', 'customer_name', 'customer_phone', 'stylist_name', 'date', 'time', 'total_label'].forEach(function (k) { set(k, a[k]); });
        $('billLines').innerHTML = linesHtml(a);
        $('billDate').textContent = new Date().toLocaleString();
        var paid = a.payment_status === 'paid';
        $('billStatus').innerHTML = paid ? '<span class="badge badge-paid">Paid</span>'
            : (a.payment_status === 'partial' ? '<span class="badge badge-confirmed">Advance paid</span>' : '<span class="badge badge-pending">Unpaid</span>');
        $('billExtras').hidden = !!a.locked;

        // Payments received so far (advances, split settlement).
        var pays = a.payments || [];
        $('billPayments').hidden = pays.length === 0;
        $('billPaymentLines').innerHTML = pays.map(function (p) {
            return '<div class="extras-row"><div><div class="fw-600"><i class="fa-solid ' + ({ cash: 'fa-money-bill-wave', card: 'fa-credit-card', bank_transfer: 'fa-building-columns', qr: 'fa-qrcode' }[p.method] || 'fa-coins') + ' text-muted" aria-hidden="true"></i> ' +
                UI.escapeHtml(p.method_label) + (p.kind === 'advance' ? ' <span class="badge badge-confirmed no-dot">Advance</span>' : '') + '</div>' +
                '<div class="text-xs text-muted">' + UI.escapeHtml(p.at || '') + (p.reference ? ' · Ref ' + UI.escapeHtml(p.reference) : '') + (p.change > 0 ? ' · change ' + UI.escapeHtml(fmt(p.change)) : '') + '</div></div>' +
                '<div class="tabular fw-600">− ' + UI.escapeHtml(p.amount_label) + '</div></div>';
        }).join('');
        $('billBalance').textContent = a.balance_label;
        document.querySelector('.balance-row').hidden = paid;

        $('payArea').hidden = paid;
        $('advanceBtn').hidden = !(a.can_take_advance && a.balance > 0);
        $('payAreaLabel').textContent = !paid && a.balance <= 0 ? 'Fully paid by advance — pick any method to close the bill' : 'Take payment · ' + a.balance_label;
        $('paidArea').hidden = !paid;
        $('paidInfo').textContent = paid ? ('by ' + (a.payment_method_label || '') + (a.paid_at ? ' · ' + a.paid_at : '')) : '';
        var hasCash = paid && a.cash_received != null;
        $('tenderRows').hidden = !hasCash;
        if (hasCash) {
            $('tenderReceived').textContent = fmt(a.cash_received);
            $('tenderChange').textContent = fmt(a.change || 0);
        }
        drawQr('receiptQr', 'receiptQrWrap', a.feedback_url, 112);
        $('receiptRateText').hidden = !!a.feedback_url;
        $('billEmpty').hidden = true;
        $('billBody').hidden = false;
        if (window.innerWidth < 900) $('bill').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function showLookupError(message) {
        lookupError.innerHTML = '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>' + UI.escapeHtml(message) + '</div>';
        lookupError.hidden = false;
        input.setAttribute('aria-invalid', 'true');
    }

    function clearLookupError() {
        lookupError.hidden = true;
        input.removeAttribute('aria-invalid');
    }

    function lookup(ref) {
        var btn = $('lookupBtn');
        clearLookupError();
        UI.setLoading(btn, true, 'Finding…');
        return UI.post(routes.lookup, { ref: ref }).then(function (res) {
            UI.setLoading(btn, false);
            if (res.ok && res.data.success) {
                render(res.data.appointment);
                if (scanner.isRunning()) { scanner.stop(); box.hidden = true; camBtn.setAttribute('aria-pressed', 'false'); }
                return;
            }
            showLookupError(res.message || 'No bill found for that reference.');
            input.select();
            setTimeout(function () { scanner.resume(); }, 1200);
        });
    }

    $('lookupForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var ref = input.value.trim().toUpperCase();
        if (!ref) { showLookupError('Enter a job number or booking reference.'); input.focus(); return; }
        lookup(ref);
    });
    input.addEventListener('input', clearLookupError);

    document.querySelectorAll('[data-lookup]').forEach(function (b) {
        b.addEventListener('click', function () { input.value = b.dataset.lookup; lookup(b.dataset.lookup); });
    });

    // ── Extras ─────────────────────────────────────────────────────────
    $('billExtras').addEventListener('click', function () {
        if (!current) return;
        var id = current.id;
        Extras.open({
            title: current.customer_name,
            subtitle: current.service_name + ' · Job ' + current.job_number,
            summary: current,
            addUrl: routes.appts + '/' + id + '/items',
            removeUrl: function (itemId) { return routes.appts + '/' + id + '/items/' + itemId; },
            catalog: catalog,
            onChange: function (a) { render(a); }
        });
    });

    // ── Payment dialog: split lines and advances ───────────────────────
    var payDialog = $('payDialog');
    var paidDialog = $('paidDialog');
    var METHODS = @json(\App\Models\Payment::METHODS);
    var payMode = 'payment';   // payment = settle the bill · advance = before the visit
    var lines = [];            // [{ method, amount, cash_received, reference, auto }]

    function round2(n) { return Math.round((parseFloat(n) || 0) * 100) / 100; }
    function linesSum() { return round2(lines.reduce(function (s, l) { return s + round2(l.amount); }, 0)); }

    function methodOptions(sel) {
        return Object.keys(METHODS).map(function (k) {
            return '<option value="' + k + '"' + (k === sel ? ' selected' : '') + '>' + UI.escapeHtml(METHODS[k]) + '</option>';
        }).join('');
    }

    function lineExtra(l, i) {
        if (l.method === 'cash') {
            return '<div class="pay-line-extra">' +
                '<label class="label" for="cr' + i + '">Cash received <span class="optional">(for change)</span></label>' +
                '<input class="input tabular" id="cr' + i + '" data-k="cash_received" data-i="' + i + '" type="number" inputmode="decimal" min="0" step="0.01" value="' + UI.escapeHtml(String(l.cash_received || '')) + '" placeholder="' + UI.escapeHtml(String(l.amount || '')) + '">' +
                '<div class="pay-line-change" data-change="' + i + '"></div></div>';
        }
        if (l.method === 'bank_transfer' || l.method === 'qr') {
            return '<div class="pay-line-extra">' +
                '<label class="label" for="rf' + i + '">' + (l.method === 'qr' ? 'QR transaction ID' : 'Bank / slip reference') + ' <span class="optional">(optional)</span></label>' +
                '<input class="input" id="rf' + i + '" data-k="reference" data-i="' + i + '" maxlength="100" value="' + UI.escapeHtml(l.reference || '') + '" placeholder="' + (l.method === 'qr' ? 'From the QR confirmation' : 'From the transfer slip / SMS') + '"></div>';
        }
        return '<div class="pay-line-extra"><div class="text-xs text-muted"><i class="fa-regular fa-credit-card" aria-hidden="true"></i> Confirm only after the card terminal shows <strong>approved</strong>.</div></div>';
    }

    function renderLines() {
        $('payLinesEdit').innerHTML = lines.map(function (l, i) {
            return '<div class="pay-line" data-line="' + i + '">' +
                '<div class="pay-line-top">' +
                '<select class="select" data-k="method" data-i="' + i + '" aria-label="Payment method ' + (i + 1) + '">' + methodOptions(l.method) + '</select>' +
                '<input class="input tabular pay-line-amount" data-k="amount" data-i="' + i + '" type="number" inputmode="decimal" min="0" step="0.01" value="' + UI.escapeHtml(String(l.amount === '' ? '' : l.amount)) + '" aria-label="Amount ' + (i + 1) + '">' +
                (lines.length > 1 ? '<button type="button" class="btn btn-ghost btn-icon btn-sm" data-remove="' + i + '" aria-label="Remove payment ' + (i + 1) + '"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>' : '') +
                '</div>' + lineExtra(l, i) + '</div>';
        }).join('');
        $('addLine').hidden = lines.length >= 4 || lines.length === 0;
        lines.forEach(function (_, i) { updateLineChange(i); });
        updateRemaining();
    }

    function updateLineChange(i) {
        var el = document.querySelector('[data-change="' + i + '"]');
        var l = lines[i];
        if (!el || !l || l.method !== 'cash') return;
        if (l.cash_received === '' || l.cash_received == null) { el.textContent = ''; el.className = 'pay-line-change'; return; }
        var diff = round2(round2(l.cash_received) - round2(l.amount));
        el.className = 'pay-line-change ' + (diff < 0 ? 'is-short' : 'is-ok');
        el.textContent = diff < 0 ? 'Short by ' + fmt(-diff) : 'Give change ' + fmt(diff);
    }

    /** Settling: the last untouched line soaks up whatever is left. */
    function rebalance(except) {
        if (payMode !== 'payment' || lines.length < 2) return;
        var last = lines.length - 1;
        if (!lines[last].auto || last === except) return;
        var others = round2(lines.slice(0, last).reduce(function (s, l) { return s + round2(l.amount); }, 0));
        lines[last].amount = Math.max(0, round2(current.balance - others));
        var input = document.querySelector('[data-k="amount"][data-i="' + last + '"]');
        if (input) input.value = lines[last].amount;
        updateLineChange(last);
    }

    function updateRemaining() {
        var sum = linesSum(), row = $('remainingRow'), ok;
        row.classList.remove('is-short', 'is-empty');
        if (payMode === 'payment') {
            var rem = round2(current.balance - sum);
            $('remainingLabel').textContent = rem > 0 ? 'Still to pay' : (rem < 0 ? 'Over by' : 'Fully covered');
            $('remainingAmount').textContent = Math.abs(rem) < 0.01 ? '✓' : fmt(Math.abs(rem));
            if (Math.abs(rem) >= 0.01) row.classList.add('is-short');
            ok = Math.abs(rem) < 0.01;
        } else {
            $('remainingLabel').textContent = sum > current.balance + 0.009 ? 'More than the balance' : 'Advance total';
            $('remainingAmount').textContent = fmt(sum);
            ok = sum > 0 && sum <= current.balance + 0.009;
            if (!ok) row.classList.add(sum > 0 ? 'is-short' : 'is-empty');
        }
        var cash = 0;
        lines.forEach(function (l) {
            if (round2(l.amount) <= 0) ok = false;
            if (l.method === 'cash') {
                cash++;
                if (l.cash_received !== '' && l.cash_received != null && round2(l.cash_received) + 0.009 < round2(l.amount)) ok = false;
            }
        });
        if (cash > 1) { ok = false; $('remainingLabel').textContent = 'Use one cash line'; }
        if (payMode === 'payment' && lines.length === 0) ok = current.balance <= 0;   // advance covered everything

        $('payConfirm').disabled = !ok;
        $('payConfirm').textContent = payMode === 'advance' ? 'Record advance · ' + fmt(sum)
            : (current.balance <= 0 ? 'Close bill' : 'Confirm payment · ' + fmt(sum));
    }

    function openPay(m, mode) {
        if (!current) return;
        payMode = mode || 'payment';
        var due = current.balance;
        var wanted = round2(current.advance_required - current.advance_paid);
        var amount = payMode === 'advance' ? (wanted > 0 ? Math.min(due, wanted) : '') : due;
        lines = due > 0 ? [{ method: m || 'cash', amount: amount, cash_received: '', reference: '', auto: false }] : [];

        $('payMethodTag').innerHTML = payMode === 'advance'
            ? '<i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i> Advance before the visit'
            : '<i class="fa-solid fa-cash-register" aria-hidden="true"></i> Settle the bill';
        $('payTitle').textContent = payMode === 'advance' ? 'Record advance' : 'Confirm payment';
        $('payAmount').textContent = current.balance_label;
        $('payContext').textContent = 'Total ' + current.total_label
            + (current.paid_so_far > 0 ? ' · already paid ' + current.paid_so_far_label : '')
            + (payMode === 'advance' && current.advance_required > 0 ? ' · this service asks for ' + fmt(current.advance_required) + ' advance' : '');
        $('payCustomer').textContent = current.customer_name;
        $('payJob').textContent = current.job_number && current.job_number !== '—' ? current.job_number + ' · ' + current.appointment_no : current.appointment_no;
        $('advanceNoteField').hidden = payMode !== 'advance';
        $('payNote').value = '';
        $('payError').hidden = true;
        renderLines();
        payDialog.showModal();
        var first = $('payLinesEdit').querySelector('.pay-line-amount');
        if (first && payMode === 'advance' && !amount) first.focus(); else $('payConfirm').focus();
    }

    document.querySelectorAll('[data-pay]').forEach(function (b) {
        b.addEventListener('click', function () { openPay(b.dataset.pay, 'payment'); });
    });
    $('advanceBtn').addEventListener('click', function () { openPay('bank_transfer', 'advance'); });

    function onLineInput(e) {
        var el = e.target, k = el.dataset.k, i = +el.dataset.i;
        if (!k || !lines[i]) return;
        lines[i][k] = el.value;
        $('payError').hidden = true;
        if (k === 'method') { renderLines(); return; }
        if (k === 'amount') { lines[i].auto = false; rebalance(i); }
        updateLineChange(i);
        updateRemaining();
    }
    $('payLinesEdit').addEventListener('input', onLineInput);
    $('payLinesEdit').addEventListener('change', function (e) { if (e.target.dataset.k === 'method') onLineInput(e); });

    $('payLinesEdit').addEventListener('click', function (e) {
        var rm = e.target.closest('[data-remove]');
        if (!rm) return;
        lines.splice(+rm.dataset.remove, 1);
        if (payMode === 'payment' && lines.length === 1) { lines[0].amount = current.balance; lines[0].auto = false; }
        renderLines();
    });

    $('addLine').addEventListener('click', function () {
        if (lines.length >= 4) return;
        var used = lines.map(function (l) { return l.method; });
        var next = ['card', 'cash', 'qr', 'bank_transfer'].filter(function (m) { return used.indexOf(m) === -1; })[0] || 'card';
        var rem = payMode === 'payment' ? Math.max(0, round2(current.balance - linesSum())) : '';
        lines.push({ method: next, amount: rem, cash_received: '', reference: '', auto: payMode === 'payment' });
        renderLines();
        var inputs = $('payLinesEdit').querySelectorAll('.pay-line-amount');
        // Settling: usually the first line is lowered next, so focus it; the new line follows automatically.
        (payMode === 'payment' && inputs.length > 1 ? inputs[0] : inputs[inputs.length - 1]).focus();
    });

    payDialog.querySelectorAll('[data-pay-cancel]').forEach(function (b) {
        b.addEventListener('click', function () { payDialog.close(); });
    });
    payDialog.addEventListener('click', function (e) { if (e.target === payDialog) payDialog.close(); });

    function payError(message) {
        $('payError').innerHTML = '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>' + UI.escapeHtml(message) + '</div>';
        $('payError').hidden = false;
    }

    $('payForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if ($('payConfirm').disabled) return;
        var mode = payMode;
        var body = {
            kind: mode,
            expected_balance: current.balance,
            note: mode === 'advance' ? ($('payNote').value.trim() || null) : null,
            lines: lines.map(function (l) {
                return {
                    method: l.method,
                    amount: round2(l.amount),
                    cash_received: l.method === 'cash' && l.cash_received !== '' ? round2(l.cash_received) : null,
                    reference: (l.method === 'bank_transfer' || l.method === 'qr') && l.reference ? l.reference.trim() : null
                };
            })
        };

        var btn = $('payConfirm');
        UI.setLoading(btn, true, 'Recording…');
        UI.post(routes.pay + '/' + current.id, body).then(function (res) {
            UI.setLoading(btn, false);
            if (res.status === 409 && res.data.appointment) {
                // The bill changed while open (e.g. an extra was added): refresh and re-check.
                render(res.data.appointment);
                openPay(lines[0] ? lines[0].method : 'cash', mode);
                payError(res.message);
                return;
            }
            if (!(res.ok && res.data.success)) {
                payError(res.message || 'Payment could not be recorded. Please try again.');
                updateRemaining();
                return;
            }
            payDialog.close();
            render(res.data.appointment);
            if (mode === 'advance') {
                UI.toast('Advance of ' + fmt(body.lines.reduce(function (s, l) { return s + l.amount; }, 0)) + ' recorded. Balance due ' + res.data.appointment.balance_label + '.', 'success');
            } else {
                showPaid(res.data.appointment);
            }
        });
    });

    /** Render a scan-to-rate QR (hidden when there's no link or the library didn't load). */
    function drawQr(boxId, wrapId, url, size) {
        var box = $(boxId), wrap = $(wrapId);
        box.innerHTML = '';
        if (!url || !window.QRCode) { wrap.hidden = true; return; }
        new QRCode(box, { text: url, width: size * 2, height: size * 2, colorDark: '#000', colorLight: '#fff', correctLevel: QRCode.CorrectLevel.M });
        wrap.hidden = false;
    }

    function showPaid(a) {
        $('paidAmount').textContent = a.total_label;
        var settled = (a.payments || []).filter(function (p) { return p.kind === 'payment'; });
        $('paidMessage').textContent = (settled.length > 1 ? settled.map(function (p) { return p.method_label + ' ' + p.amount_label; }).join(' + ') : a.payment_method_label + ' payment')
            + ' from ' + a.customer_name + ' · Job ' + a.job_number + (a.advance_paid > 0 ? ' · includes ' + fmt(a.advance_paid) + ' advance' : '');
        $('paidChange').hidden = !(a.change > 0);
        if (a.change > 0) $('paidChangeAmount').textContent = fmt(a.change);
        drawQr('paidQr', 'paidQrWrap', a.feedback_url, 96);
        paidDialog.showModal();
        $('paidNext').focus();
    }

    function resetBill() {
        current = null;
        input.value = '';
        clearLookupError();
        $('billBody').hidden = true;
        $('billEmpty').hidden = false;
        input.focus();
    }

    // Print the designed receipt in a hidden frame, so the cashier stays on this screen.
    // The receipt page prints itself once its fonts and logo have loaded.
    function printReceipt() {
        if (!current) return;
        var old = document.getElementById('receiptFrame');
        if (old) old.remove();
        var frame = document.createElement('iframe');
        frame.id = 'receiptFrame';
        frame.title = 'Receipt';
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
        frame.src = routes.receipt + '/' + current.id + '?print=1';
        document.body.appendChild(frame);
    }

    $('paidPrint').addEventListener('click', function () { paidDialog.close(); printReceipt(); });
    $('paidNext').addEventListener('click', function () { paidDialog.close(); resetBill(); });
    $('printBill').addEventListener('click', printReceipt);
    $('newBill').addEventListener('click', resetBill);
});
</script>
@endpush
