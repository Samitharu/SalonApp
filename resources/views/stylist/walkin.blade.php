@extends('layouts.portal')
@section('title', 'New booking')
@section('mark')<i class="fa-solid fa-scissors" style="font-size:15px;"></i>@endsection
@section('who', $stylist->name)
@section('who_sub', 'New booking')
@section('container_class', 'container-sm')

@section('header_actions')
    <a href="{{ route('stylist.dashboard') }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> My day</a>
@endsection

@section('content')
<div class="page-head">
    <div>
        <h1>New booking</h1>
        <p id="modeLead">{{ $mode === 'later' ? 'Book a customer in for later — over the phone or at the desk.' : 'For a customer who is here now. Starts with you right away (or later today).' }}</p>
    </div>
</div>

<form id="walkinForm" class="stack-lg" novalidate data-no-lock>
    <div class="segmented segmented-block" role="radiogroup" aria-label="Booking type">
        @if($canWalkin)
        <button type="button" role="radio" data-mode="walkin" aria-checked="{{ $mode === 'walkin' ? 'true' : 'false' }}" class="{{ $mode === 'walkin' ? 'is-active' : '' }}">
            <i class="fa-solid fa-person-walking" aria-hidden="true"></i> Walk-in · today
        </button>
        @endif
        @if($canLater)
        <button type="button" role="radio" data-mode="later" aria-checked="{{ $mode === 'later' ? 'true' : 'false' }}" class="{{ $mode === 'later' ? 'is-active' : '' }}">
            <i class="fa-solid fa-phone" aria-hidden="true"></i> Phone / later
        </button>
        @endif
    </div>

    <section class="card card-pad">
        <h2 class="card-title mb-4" id="svc-label">1. Service</h2>
        @if($services->isEmpty())
            <p class="text-muted">No active services. Ask an admin to add services.</p>
        @else
            <div class="option-list" role="radiogroup" aria-labelledby="svc-label">
                @foreach($services as $s)
                    <button type="button" class="option" role="radio" aria-checked="false" data-service="{{ $s->id }}">
                        <span class="option-check"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                        @if($s->imageUrl())
                            <img class="option-thumb" src="{{ $s->imageUrl() }}" alt="" width="56" height="56" loading="lazy" decoding="async">
                        @endif
                        <span class="option-main">
                            <span class="option-title">{{ $s->name }}</span>
                            <span class="option-sub">{{ $s->durationLabel() }}</span>
                        </span>
                        <span class="option-aside">@include('partials.price', ['service' => $s])</span>
                    </button>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card card-pad" data-later {{ $mode === 'later' ? '' : 'hidden' }}>
        <h2 class="card-title mb-4">2. Date &amp; stylist</h2>
        <div class="form-grid">
            <div class="field">
                <label class="label" for="bDate">Date <span class="req">*</span></label>
                <input class="input" type="date" id="bDate" value="{{ now()->toDateString() }}" min="{{ now()->toDateString() }}" max="{{ $maxDate }}">
            </div>
            <div class="field">
                <label class="label" for="bStaff">Stylist</label>
                <select class="select" id="bStaff">
                    <option value="{{ $stylist->id }}" selected>Me ({{ $stylist->name }})</option>
                    <option value="">Any available stylist</option>
                    @foreach($staff->where('id', '!=', $stylist->id) as $member)
                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    <section class="card card-pad">
        <h2 class="card-title mb-4"><span data-step-time>{{ $mode === 'later' ? '3' : '2' }}</span>. <span id="timeTitle">{{ $mode === 'later' ? 'Time' : 'Start time' }}</span></h2>
        <div id="slotArea" aria-live="polite"><div class="panel-muted text-sm text-muted">Choose a service first.</div></div>
    </section>

    <section class="card card-pad">
        <h2 class="card-title mb-4"><span data-step-customer>{{ $mode === 'later' ? '4' : '3' }}</span>. Customer</h2>
        <div class="form-grid">
            <div class="field">
                <label class="label" for="custName">Name <span class="req">*</span></label>
                <input class="input" id="custName" maxlength="120" autocomplete="off" required>
            </div>
            <div class="field">
                <label class="label" for="custPhone">Phone <span class="req">*</span></label>
                <input class="input" id="custPhone" type="tel" inputmode="tel" maxlength="20" autocomplete="off" required>
                <div class="hint">Returning customers are matched by phone.</div>
            </div>
            <div class="field">
                <label class="label" for="custEmail">Email <span class="req" data-later {{ $mode === 'later' ? '' : 'hidden' }}>*</span><span class="optional" data-walkin {{ $mode === 'later' ? 'hidden' : '' }}>(optional)</span></label>
                <input class="input" id="custEmail" type="email" inputmode="email" maxlength="120" autocomplete="off">
                <div class="hint" data-later {{ $mode === 'later' ? '' : 'hidden' }}>The booking confirmation is emailed here.</div>
            </div>
            <div class="field" data-later {{ $mode === 'later' ? '' : 'hidden' }}>
                <span class="label">Booked via</span>
                <div class="segmented" role="radiogroup" aria-label="Booked via">
                    <button type="button" role="radio" data-type="phone" aria-checked="true" class="is-active"><i class="fa-solid fa-phone" aria-hidden="true"></i> Phone call</button>
                    <button type="button" role="radio" data-type="counter" aria-checked="false"><i class="fa-solid fa-store" aria-hidden="true"></i> At the desk</button>
                </div>
            </div>
            <div class="field full">
                <label class="label" for="custNotes">Notes <span class="optional">(optional)</span></label>
                <input class="input" id="custNotes" maxlength="500" autocomplete="off" placeholder="e.g. prefers a short trim, allergic to …">
            </div>
        </div>
    </section>

    <button type="submit" class="btn btn-primary btn-lg btn-block" id="createBtn" disabled>{{ $mode === 'later' ? 'Confirm booking' : 'Create & start appointment' }}</button>
</form>

{{-- Walk-in started --}}
<dialog class="dialog" id="doneDialog" aria-labelledby="doneTitle">
    <div class="dialog-header"><h2 class="dialog-title" id="doneTitle">Appointment started</h2></div>
    <div class="dialog-body stack">
        <p id="doneWho"></p>
        <p>Job number for the cashier:</p>
        <div class="big-code" id="doneCode"></div>
    </div>
    <div class="dialog-footer dialog-footer-split">
        <button type="button" class="btn" id="doneExtras"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add extras</button>
        <a href="{{ route('stylist.dashboard') }}" class="btn btn-primary">Back to my day</a>
    </div>
</dialog>

{{-- Phone / later booking confirmed --}}
<dialog class="dialog dialog-alert" id="bookedDialog" aria-labelledby="bookedTitle" aria-describedby="bookedWhen">
    <div class="dialog-alert-body">
        <div class="dialog-icon tone-success" aria-hidden="true"><i class="fa-solid fa-calendar-check"></i></div>
        <h2 class="dialog-title" id="bookedTitle">Booking confirmed</h2>
        <p class="dialog-message" id="bookedWhen"></p>
        <div class="panel-muted stack-sm" style="text-align:left;">
            <div class="row row-between text-sm"><span class="text-muted">Customer</span><strong id="bookedWho"></strong></div>
            <div class="row row-between text-sm"><span class="text-muted">Stylist</span><strong id="bookedStylist"></strong></div>
            <div class="row row-between text-sm"><span class="text-muted">Reference</span><strong class="mono" id="bookedRef"></strong></div>
        </div>
        <p class="hint" id="bookedHint">Give the customer the reference — they can use it to change or cancel online.</p>
    </div>
    <div class="dialog-footer dialog-footer-split">
        <button type="button" class="btn" id="bookAnother"><i class="fa-solid fa-plus" aria-hidden="true"></i> Book another</button>
        <a href="{{ route('stylist.dashboard') }}" class="btn btn-primary">Back to my day</a>
    </div>
</dialog>
@endsection

@push('scripts')
<script src="{{ asset_v('js/extras.js') }}" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var routes = { slots: @json(route('stylist.walkin-slots')), store: @json(route('stylist.walkin-store')), appts: @json(url('/stylist/appointments')) };
    var catalog = @json(\App\Services\AppointmentCharges::catalog());
    var created = null;
    var mode = @json($mode);
    var chosen = { service: null, start: null, type: 'phone' };

    var $ = function (id) { return document.getElementById(id); };
    var area = $('slotArea'), btn = $('createBtn'), name = $('custName'), phone = $('custPhone');
    var date = $('bDate'), staffSel = $('bStaff');
    var req = 0;

    $('doneExtras').addEventListener('click', function () {
        if (!created) return;
        Extras.open({
            title: created.customer_name,
            subtitle: created.service_name + ' · Job ' + created.job_number,
            loadUrl: routes.appts + '/' + created.id + '/charges',
            addUrl: routes.appts + '/' + created.id + '/items',
            removeUrl: function (itemId) { return routes.appts + '/' + created.id + '/items/' + itemId; },
            catalog: catalog
        });
    });

    var email = $('custEmail');
    function emailOk() {
        var v = email.value.trim();
        return v ? /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) : mode !== 'later';
    }
    function ready() { btn.disabled = !(chosen.service && chosen.start && name.value.trim().length >= 2 && phone.value.trim().length >= 7 && emailOk()); }
    [name, phone, email].forEach(function (el) { el.addEventListener('input', ready); });

    function setMode(m) {
        mode = m;
        var later = m === 'later';
        document.querySelectorAll('[data-mode]').forEach(function (b) {
            var on = b.dataset.mode === m;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-checked', String(on));
        });
        document.querySelectorAll('[data-later]').forEach(function (el) { el.hidden = !later; });
        document.querySelectorAll('[data-walkin]').forEach(function (el) { el.hidden = later; });
        document.querySelector('[data-step-time]').textContent = later ? '3' : '2';
        document.querySelector('[data-step-customer]').textContent = later ? '4' : '3';
        $('timeTitle').textContent = later ? 'Time' : 'Start time';
        $('modeLead').textContent = later
            ? 'Book a customer in for later — over the phone or at the desk.'
            : 'For a customer who is here now. Starts with you right away (or later today).';
        btn.textContent = later ? 'Confirm booking' : 'Create & start appointment';
        history.replaceState(null, '', later ? '?mode=later' : location.pathname);
        chosen.start = null;
        if (chosen.service) load(); else area.innerHTML = '<div class="panel-muted text-sm text-muted">Choose a service first.</div>';
        ready();
    }
    document.querySelectorAll('[data-mode]').forEach(function (b) { b.addEventListener('click', function () { setMode(b.dataset.mode); }); });

    document.querySelectorAll('[data-type]').forEach(function (b) {
        b.addEventListener('click', function () {
            chosen.type = b.dataset.type;
            document.querySelectorAll('[data-type]').forEach(function (x) { var on = x === b; x.classList.toggle('is-active', on); x.setAttribute('aria-checked', String(on)); });
        });
    });

    document.querySelectorAll('[data-service]').forEach(function (b) {
        b.addEventListener('click', function () {
            chosen.service = b.dataset.service; chosen.start = null;
            document.querySelectorAll('[data-service]').forEach(function (x) { x.setAttribute('aria-checked', String(x === b)); });
            load(); ready();
        });
    });

    [date, staffSel].forEach(function (el) { el.addEventListener('change', function () { chosen.start = null; if (chosen.service) load(); ready(); }); });

    function load() {
        var id = ++req, later = mode === 'later';
        if (later && !date.value) { area.innerHTML = '<div class="panel-muted text-sm">Choose a date.</div>'; return; }
        area.innerHTML = '<div class="loading-row"><span class="spinner"></span> Checking free times…</div>';
        var q = '?service_id=' + encodeURIComponent(chosen.service) + '&mode=' + mode +
            (later ? '&date=' + encodeURIComponent(date.value) + (staffSel.value ? '&staff_id=' + encodeURIComponent(staffSel.value) : '') : '');
        UI.get(routes.slots + q).then(function (res) {
            if (id !== req) return;
            if (!res.ok) { area.innerHTML = '<div class="alert alert-error">' + UI.escapeHtml(res.message || 'Could not load times.') + '</div>'; return; }
            var slots = res.data || [];
            if (!slots.length) {
                area.innerHTML = '<div class="panel-muted text-sm">' + (later
                    ? 'No free times on this day for this stylist. Try another date or “Any available stylist”.'
                    : 'You have no free time left today for this service.') + '</div>';
                return;
            }
            var d = new Date(), nowMin = d.getHours() * 60 + d.getMinutes();
            var html = '<div class="slot-grid">';
            slots.forEach(function (s) {
                var p = s.start.split(':'), m = (+p[0]) * 60 + (+p[1]);
                var label = !later && Math.abs(m - nowMin) <= 15 ? 'Now' : UI.formatTime(s.start);
                var who = s.stylists && s.stylists.length ? s.stylists.map(function (x) { return x.name; }).join(', ') : '';
                html += '<button type="button" class="slot" aria-pressed="false" data-start="' + UI.escapeHtml(s.start) + '" aria-label="' +
                    UI.escapeHtml(UI.formatTime(s.start)) + '" title="' + UI.escapeHtml(later && who ? 'Free: ' + who : '') + '">' + UI.escapeHtml(label) + '</button>';
            });
            area.innerHTML = html + '</div>';
        });
    }

    area.addEventListener('click', function (e) {
        var b = e.target.closest('.slot');
        if (!b) return;
        chosen.start = b.dataset.start;
        area.querySelectorAll('.slot').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
        ready();
    });

    $('bookAnother').addEventListener('click', function () { location.href = location.pathname + '?mode=later'; });

    $('walkinForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (btn.disabled) return;
        var later = mode === 'later';
        UI.setLoading(btn, true, later ? 'Booking…' : 'Creating…');
        var payload = {
            mode: mode, customer_name: name.value.trim(), customer_phone: phone.value.trim(),
            customer_email: email.value.trim() || null, service_id: chosen.service, start_time: chosen.start, notes: $('custNotes').value.trim()
        };
        if (later) { payload.date = date.value; payload.staff_id = staffSel.value || null; payload.type = chosen.type; }

        UI.post(routes.store, payload).then(function (res) {
            if (res.ok && res.data.success) {
                created = res.data;
                if (res.data.mode === 'later' || res.data.status !== 'in_progress') {
                    $('bookedWhen').textContent = res.data.service_name + ' · ' + res.data.when;
                    $('bookedWho').textContent = res.data.customer_name;
                    $('bookedStylist').textContent = res.data.stylist_name ? (res.data.mine ? 'You' : res.data.stylist_name) : 'Assigned on arrival';
                    $('bookedRef').textContent = res.data.appointment_no;
                    $('bookedHint').textContent = res.data.email && @json(mail_is_live())
                        ? 'The confirmation is being emailed to ' + res.data.email + '. They can use the reference to change or cancel online.'
                        : 'Give the customer the reference — they can use it to change or cancel online.';
                    $('bookedDialog').showModal();
                } else {
                    $('doneCode').textContent = res.data.job_number;
                    $('doneWho').textContent = res.data.customer_name + ' — ' + res.data.service_name;
                    $('doneDialog').showModal();
                }
                return;
            }
            UI.setLoading(btn, false);
            UI.toast(res.message || 'Could not create the booking.', 'error');
            if (res.status === 422) load();
        });
    });
});
</script>
@endpush
