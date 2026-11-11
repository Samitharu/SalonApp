@extends('layouts.portal')
@section('title', 'My day')
@section('mark')<i class="fa-solid fa-scissors" style="font-size:15px;"></i>@endsection
@section('who', $stylist->name)
@section('who_sub', now()->format('l, j F'))

@section('header_actions')
    <form method="POST" action="{{ route('stylist.logout') }}" data-no-lock>
        @csrf
        <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span class="hide-sm"> Sign out</span></button>
    </form>
@endsection

@php
    $active    = $appointments->firstWhere('status', 'in_progress');
    $remaining = $appointments->whereIn('status', ['pending', 'confirmed'])->count();
    $done      = $appointments->where('status', 'completed')->count();
    $nowStr    = now()->format('H:i:s');
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Today</h1>
        <p>{{ $remaining }} to go · {{ $done }} done{{ $active ? ' · 1 in progress' : '' }}@if($rating && $rating->n) · <span title="Average customer rating from {{ $rating->n }} {{ \Illuminate\Support\Str::plural('review', $rating->n) }}"><i class="fa-solid fa-star" style="color:#e0a526;" aria-hidden="true"></i> {{ number_format((float) $rating->avg, 1) }} ({{ $rating->n }})</span>@endif</p>
    </div>
    <div class="row row-wrap">
        @if($stylist->portalCan('portal.walkin'))<a href="{{ route('stylist.walkin') }}" class="btn"><i class="fa-solid fa-person-walking" aria-hidden="true"></i> Walk-in</a>@endif
        @if($stylist->portalCan('portal.bookings'))<a href="{{ route('stylist.walkin', ['mode' => 'later']) }}" class="btn"><i class="fa-solid fa-phone" aria-hidden="true"></i> Phone booking</a>@endif
        <button type="button" class="btn btn-primary" id="openScan"><i class="fa-solid fa-qrcode" aria-hidden="true"></i> Scan customer</button>
    </div>
</div>

@if($appointments->isEmpty())
    <div class="card">
        <div class="empty">
            <div class="empty-icon"><i class="fa-regular fa-calendar" aria-hidden="true"></i></div>
            <h3>No appointments today</h3>
            @if($stylist->portalCan('portal.walkin'))<p>Walk-in customers can be added with the Walk-in button.</p>@endif
        </div>
    </div>
@else
    <div class="stack-sm" role="list" aria-label="Today's appointments">
        @foreach($appointments as $a)
            @php
                $isPast = in_array($a->status, ['completed', 'cancelled', 'no_show']);
            @endphp
            <article class="job-card {{ $a->status === 'in_progress' ? 'is-active' : '' }} {{ $isPast ? 'is-muted' : '' }}" role="listitem">
                <div class="job-time">{{ fmt_time($a->slot_start) }}<small>until {{ fmt_time($a->slot_end) }}</small></div>
                <div style="min-width:0;">
                    <div class="row row-wrap" style="gap:var(--s-2);">
                        <span class="job-title">{{ $a->customer->name ?? '—' }}</span>
                        @include('partials.status', ['status' => $a->status])
                        @if($a->walk_in)<span class="badge badge-info no-dot">Walk-in</span>@endif
                    </div>
                    <div class="job-meta">
                        <span><i class="fa-solid fa-scissors" aria-hidden="true"></i> {{ $a->service->name ?? '—' }}</span>
                        @if($a->customer?->phone)<a href="tel:{{ $a->customer->phone }}"><i class="fa-solid fa-phone" aria-hidden="true"></i> {{ $a->customer->phone }}</a>@endif
                        @if($a->job_number)<span>Job <span class="chip-code">{{ $a->job_number }}</span></span>@endif
                        @if($a->payment_status === 'paid')<span class="badge badge-paid">Paid</span>@elseif($a->payment_status === 'partial')<span class="badge badge-confirmed no-dot">Advance paid</span>@endif
                        <span data-bill-summary="{{ $a->id }}">
                            @if($a->items->isNotEmpty())
                                <i class="fa-solid fa-plus" aria-hidden="true"></i> {{ $a->items->sum('quantity') }} {{ \Illuminate\Support\Str::plural('extra', $a->items->sum('quantity')) }} · Bill {{ money($a->total()) }}
                            @endif
                        </span>
                    </div>
                    @if($a->notes)<div class="text-sm mt-1"><i class="fa-regular fa-note-sticky icon-muted" aria-hidden="true"></i> {{ $a->notes }}</div>@endif
                </div>
                <div class="job-actions">
                    @if($stylist->portalCan('portal.extras') && !\App\Services\AppointmentCharges::isLocked($a))
                        <button type="button" class="btn" data-extras="{{ $a->id }}" data-title="{{ $a->customer->name ?? 'Customer' }}" data-subtitle="{{ $a->service->name ?? '' }} · {{ fmt_time($a->slot_start) }}">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i> Extras
                        </button>
                    @endif
                    @if(in_array($a->status, ['pending', 'confirmed']))
                        <button type="button" class="btn btn-soft" data-start="{{ $a->appointment_no }}"><i class="fa-solid fa-play" aria-hidden="true"></i> Start</button>
                    @elseif($a->status === 'in_progress')
                        <button type="button" class="btn btn-success" data-complete="{{ $a->id }}" data-name="{{ $a->customer->name ?? 'this customer' }}"><i class="fa-solid fa-check" aria-hidden="true"></i> Finish</button>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
@endif

@if($openBookings->isNotEmpty())
    <section class="mt-8">
        <h2 class="mb-2" style="font-size:17px;">Unassigned today</h2>
        <p class="text-sm text-muted mb-4">Customers who chose “any stylist”. Starting one assigns it to you.</p>
        <div class="stack-sm">
            @foreach($openBookings as $a)
                <article class="job-card">
                    <div class="job-time">{{ fmt_time($a->slot_start) }}<small>until {{ fmt_time($a->slot_end) }}</small></div>
                    <div style="min-width:0;">
                        <div class="job-title">{{ $a->customer->name ?? '—' }}</div>
                        <div class="job-meta"><span>{{ $a->service->name ?? '—' }}</span></div>
                    </div>
                    <div class="job-actions">
                        <button type="button" class="btn" data-start="{{ $a->appointment_no }}">Take &amp; start</button>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif

@if($upcoming->isNotEmpty())
    <section class="mt-8">
        <h2 class="mb-4" style="font-size:17px;">Coming up</h2>
        <div class="card">
            <ul class="timeline">
                @foreach($upcoming as $a)
                    <li>
                        <div class="time">{{ \Carbon\Carbon::parse($a->appointment_date)->format('D j') }}<small>{{ fmt_time($a->slot_start) }}</small></div>
                        <div style="min-width:0;">
                            <div class="fw-600 truncate">{{ $a->customer->name ?? '—' }}</div>
                            <div class="text-sm text-muted truncate">{{ $a->service->name ?? '—' }}</div>
                        </div>
                        @include('partials.status', ['status' => $a->status])
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif

{{-- Scan dialog --}}
<dialog class="dialog dialog-lg" id="scanDialog" aria-labelledby="scanTitle">
    <div class="dialog-header">
        <div>
            <h2 class="dialog-title" id="scanTitle">Scan customer QR</h2>
            <p class="text-sm text-muted">Starts the job for that booking.</p>
        </div>
        <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close-scan aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="dialog-body stack">
        <div class="scanner">
            <div id="qrReader"></div>
            <div class="scanner-frame" id="scanFrame" hidden><span></span></div>
            <div class="scanner-state" id="scanState"><span class="spinner" aria-hidden="true"></span><span>Starting camera…</span></div>
        </div>
        <p class="text-sm text-muted" id="scanHint" aria-live="polite"></p>

        <form id="manualForm" class="row" novalidate data-no-lock>
            <label class="sr-only" for="manualRef">Booking reference</label>
            <input class="input input-mono grow" id="manualRef" placeholder="Type reference, e.g. AB12CD34" maxlength="20" autocomplete="off" autocapitalize="characters">
            <button type="submit" class="btn btn-primary">Start</button>
        </form>
        <div>
            <label class="btn btn-ghost btn-sm" for="qrFile"><i class="fa-regular fa-image" aria-hidden="true"></i> Scan from a photo</label>
            <input type="file" id="qrFile" accept="image/*" class="sr-only">
        </div>
    </div>
</dialog>

{{-- Job complete dialog --}}
<dialog class="dialog" id="doneDialog" aria-labelledby="doneTitle">
    <div class="dialog-header"><h2 class="dialog-title" id="doneTitle">Job finished</h2></div>
    <div class="dialog-body stack">
        <p>Give the customer this job number to pay at the cashier:</p>
        <div class="big-code" id="doneCode"></div>
    </div>
    <div class="dialog-footer"><button type="button" class="btn btn-primary btn-block" id="doneOk">Done</button></div>
</dialog>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" defer></script>
<script src="{{ asset_v('js/qr-scanner.js') }}" defer></script>
<script src="{{ asset_v('js/extras.js') }}" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var routes = { scan: @json(route('stylist.scan-customer')), complete: @json(url('/stylist/complete')), appts: @json(url('/stylist/appointments')) };
    var catalog = @json($catalog);

    // ── Extras (add-ons) ──────────────────────────────────────────────
    document.querySelectorAll('[data-extras]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.extras;
            Extras.open({
                title: btn.dataset.title,
                subtitle: btn.dataset.subtitle,
                loadUrl: routes.appts + '/' + id + '/charges',
                addUrl: routes.appts + '/' + id + '/items',
                removeUrl: function (itemId) { return routes.appts + '/' + id + '/items/' + itemId; },
                catalog: catalog,
                onChange: function (s) {
                    var qty = (s.items || []).reduce(function (n, i) { return n + i.quantity; }, 0);
                    var el = document.querySelector('[data-bill-summary="' + id + '"]');
                    if (el) el.innerHTML = qty ? '<i class="fa-solid fa-plus" aria-hidden="true"></i> ' + qty + ' extra' + (qty > 1 ? 's' : '') + ' · Bill ' + UI.escapeHtml(s.total_label) : '';
                }
            });
        });
    });
    var dlg = document.getElementById('scanDialog');
    var stateEl = document.getElementById('scanState');
    var frame = document.getElementById('scanFrame');
    var hint = document.getElementById('scanHint');

    var scanner = QrScanner.create('qrReader', {
        onCode: function (ref) { return startJob(ref, true); },
        onState: function (s, msg) {
            stateEl.hidden = s === 'live';
            frame.hidden = s !== 'live';
            if (s === 'starting') stateEl.innerHTML = '<span class="spinner" aria-hidden="true"></span><span>' + UI.escapeHtml(msg) + '</span>';
            if (s === 'error') stateEl.innerHTML = '<i class="fa-solid fa-camera-rotate" style="font-size:24px;" aria-hidden="true"></i><span>' + UI.escapeHtml(msg) + '</span>';
            if (s === 'live') hint.textContent = msg || '';
        }
    });

    function startJob(ref, fromScan) {
        hint.textContent = 'Starting ' + ref + '…';
        return UI.post(routes.scan, { appointment_no: ref }).then(function (res) {
            if (res.ok && res.data.success) {
                var a = res.data.appointment;
                UI.toast('Started: ' + a.customer_name + ' — ' + a.service_name);
                scanner.stop();
                setTimeout(function () { location.reload(); }, 600);
                return;
            }
            hint.textContent = '';
            UI.toast(res.message || 'Could not start this job.', 'error');
            if (fromScan) setTimeout(function () { scanner.resume(); }, 1200);
        });
    }

    document.getElementById('openScan').addEventListener('click', function () {
        hint.textContent = '';
        document.getElementById('manualRef').value = '';
        dlg.showModal();
        scanner.start();
    });

    function closeScan() { scanner.stop(); if (dlg.open) dlg.close(); }
    document.querySelector('[data-close-scan]').addEventListener('click', closeScan);
    dlg.addEventListener('cancel', function () { scanner.stop(); });

    document.getElementById('manualForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var ref = document.getElementById('manualRef').value.trim().toUpperCase();
        if (ref) startJob(ref, false);
    });

    document.getElementById('qrFile').addEventListener('change', function () {
        var f = this.files && this.files[0];
        if (!f) return;
        hint.textContent = 'Reading photo…';
        scanner.scanFile(f).then(function (ref) { startJob(ref, false); })
            .catch(function (err) { hint.textContent = ''; UI.toast(err.message || 'Could not read a QR code from that photo.', 'error'); });
        this.value = '';
    });

    document.querySelectorAll('[data-start]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            UI.setLoading(btn, true, 'Starting…');
            UI.post(routes.scan, { appointment_no: btn.dataset.start }).then(function (res) {
                if (res.ok && res.data.success) { location.reload(); return; }
                UI.setLoading(btn, false);
                UI.toast(res.message || 'Could not start this job.', 'error');
            });
        });
    });

    var done = document.getElementById('doneDialog');
    document.getElementById('doneOk').addEventListener('click', function () { location.reload(); });
    done.addEventListener('close', function () { location.reload(); });

    document.querySelectorAll('[data-complete]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            UI.confirm({ title: 'Finish this job?', message: 'Mark ' + btn.dataset.name + '\'s appointment as completed.', button: 'Finish job', tone: 'primary' })
                .then(function (yes) {
                    if (!yes) return;
                    UI.setLoading(btn, true, 'Finishing…');
                    UI.post(routes.complete + '/' + btn.dataset.complete, {}).then(function (res) {
                        if (res.ok && res.data.success) {
                            document.getElementById('doneCode').textContent = res.data.job_number;
                            done.showModal();
                            return;
                        }
                        UI.setLoading(btn, false);
                        UI.toast(res.message || 'Could not complete this job.', 'error');
                    });
                });
        });
    });
});
</script>
@endpush
