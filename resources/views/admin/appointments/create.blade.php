@extends('layouts.admin')
@section('title', old('walk_in', $walkin) ? 'New walk-in' : 'New booking')
@section('crumb', 'Appointments')

@php $isWalkin = (bool) old('walk_in', $walkin); @endphp

@section('content')
<div class="page-head">
    <div>
        <h1 id="pageTitle">{{ $isWalkin ? 'New walk-in' : 'New booking' }}</h1>
        <p id="pageLead">{{ $isWalkin ? 'For a customer who is here now. The appointment starts immediately and gets a job number.' : 'Add a phone or counter booking. It is confirmed straight away.' }}</p>
    </div>
</div>

<form method="POST" action="{{ route('admin.appointments.store') }}" class="grid" style="grid-template-columns:minmax(0,1fr);max-width:860px;" id="adminBookForm" novalidate>
    @csrf

    @if($errors->any())
        <div class="alert alert-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
            <div>Please fix the highlighted fields.</div></div>
    @endif

    <section class="card card-pad">
        <h2 class="card-title mb-2">Type</h2>
        <div class="segmented" role="radiogroup" aria-label="Booking type">
            <button type="button" role="radio" data-mode="booking" aria-checked="{{ $isWalkin ? 'false' : 'true' }}" class="{{ $isWalkin ? '' : 'is-active' }}"><i class="fa-regular fa-calendar" aria-hidden="true"></i> Booking for later</button>
            <button type="button" role="radio" data-mode="walkin" aria-checked="{{ $isWalkin ? 'true' : 'false' }}" class="{{ $isWalkin ? 'is-active' : '' }}"><i class="fa-solid fa-person-walking" aria-hidden="true"></i> Walk-in — start now</button>
        </div>
        <input type="hidden" name="walk_in" id="walk_in" value="{{ $isWalkin ? 1 : 0 }}">
    </section>

    <section class="card card-pad">
        <h2 class="card-title mb-4">Customer</h2>
        <div class="form-grid">
            <div class="field">
                <label class="label" for="name">Name <span class="req">*</span></label>
                <input class="input" id="name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="off" @error('name') aria-invalid="true" @enderror>
                @error('name')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label class="label" for="phone">Phone <span class="req">*</span></label>
                <input class="input" id="phone" name="phone" type="tel" value="{{ old('phone') }}" required maxlength="20" autocomplete="off" @error('phone') aria-invalid="true" @enderror>
                @error('phone')<div class="error-text">{{ $message }}</div>@enderror
                <div class="hint">Existing customers are matched by phone number.</div>
            </div>
            <div class="field">
                <label class="label" for="email">Email <span class="req" data-booking-only>*</span><span class="optional" data-walkin-only @unless($isWalkin) hidden @endunless>(optional)</span></label>
                <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" maxlength="120" autocomplete="off" @error('email') aria-invalid="true" @enderror>
                @error('email')<div class="error-text">{{ $message }}</div>@else<div class="hint" data-booking-only>The booking confirmation is emailed here.</div>@enderror
            </div>
            <div class="field" data-booking-only>
                <label class="label" for="type">Booked via</label>
                <select class="select" id="type" name="type">
                    <option value="phone" @selected(old('type', 'phone') === 'phone')>Phone call</option>
                    <option value="counter" @selected(old('type') === 'counter')>At the counter</option>
                    <option value="online" @selected(old('type') === 'online')>Online / message</option>
                </select>
            </div>
        </div>
    </section>

    <section class="card card-pad">
        <h2 class="card-title mb-4">Appointment</h2>
        <div class="form-grid">
            <div class="field full">
                <label class="label" for="service_id">Service <span class="req">*</span></label>
                <select class="select" id="service_id" name="service_id" required @error('service_id') aria-invalid="true" @enderror>
                    <option value="">Choose a service</option>
                    @foreach($services as $s)
                        <option value="{{ $s->id }}" @selected((string) old('service_id') === (string) $s->id)>{{ $s->name }} — {{ $s->durationLabel() }} · {{ money($s->price) }}</option>
                    @endforeach
                </select>
                @error('service_id')<div class="error-text">{{ $message }}</div>@else<div class="hint">Usual prices shown. Any offer for the visit date is taken off automatically.</div>@enderror
            </div>
            <div class="field" data-booking-only>
                <label class="label" for="date">Date <span class="req">*</span></label>
                <input class="input" type="date" id="date" name="date" value="{{ old('date', $date) }}" min="{{ now()->toDateString() }}" required @error('date') aria-invalid="true" @enderror>
                @error('date')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label class="label" for="staff_id">Stylist</label>
                <select class="select" id="staff_id" name="staff_id">
                    <option value="">Any available</option>
                    @foreach($staff as $s)
                        <option value="{{ $s->id }}" @selected((string) old('staff_id') === (string) $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field full">
                <span class="label" id="slot-label"><span id="slotLabelText">{{ $isWalkin ? 'Start time (today)' : 'Time' }}</span> <span class="req">*</span></span>
                <input type="hidden" name="start_time" id="start_time" value="{{ old('start_time') }}">
                <div id="adminSlots" aria-live="polite" aria-labelledby="slot-label">
                    <div class="panel-muted text-sm text-muted">{{ $isWalkin ? 'Choose a service to see free start times today.' : 'Choose a service and date to see free times.' }}</div>
                </div>
                @error('start_time')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field full">
                <label class="label" for="notes">Internal notes <span class="optional">(optional)</span></label>
                <textarea class="textarea" id="notes" name="notes" maxlength="500" rows="3">{{ old('notes') }}</textarea>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.appointments.index') }}" class="btn">Cancel</a>
            <button type="submit" class="btn btn-primary" id="adminBookSubmit" data-loading="Saving…">{{ $isWalkin ? 'Start walk-in' : 'Create booking' }}</button>
        </div>
    </section>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var service = document.getElementById('service_id');
    var date = document.getElementById('date');
    var staff = document.getElementById('staff_id');
    var hidden = document.getElementById('start_time');
    var area = document.getElementById('adminSlots');
    var url = @json(route('admin.appointments.slots'));
    var walkinInput = document.getElementById('walk_in');
    var req = 0;

    function isWalkin() { return walkinInput.value === '1'; }

    function setMode(mode) {
        var walk = mode === 'walkin';
        walkinInput.value = walk ? '1' : '0';
        document.querySelectorAll('[data-mode]').forEach(function (b) {
            var on = b.dataset.mode === mode;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-checked', String(on));
        });
        document.querySelectorAll('[data-booking-only]').forEach(function (el) { el.hidden = walk; });
        document.querySelectorAll('[data-walkin-only]').forEach(function (el) { el.hidden = !walk; });
        date.required = !walk;
        document.getElementById('pageTitle').textContent = walk ? 'New walk-in' : 'New booking';
        document.getElementById('pageLead').textContent = walk
            ? 'For a customer who is here now. The appointment starts immediately and gets a job number.'
            : 'Add a phone or counter booking. It is confirmed straight away.';
        document.getElementById('slotLabelText').textContent = walk ? 'Start time (today)' : 'Time';
        document.getElementById('adminBookSubmit').textContent = walk ? 'Start walk-in' : 'Create booking';
        hidden.value = '';
        load();
    }

    document.querySelectorAll('[data-mode]').forEach(function (b) {
        b.addEventListener('click', function () { setMode(b.dataset.mode); });
    });

    function load() {
        if (!service.value || (!isWalkin() && !date.value)) return;
        var id = ++req;
        area.innerHTML = '<div class="loading-row"><span class="spinner"></span> Checking availability…</div>';
        var q = '?service_id=' + encodeURIComponent(service.value) + '&date=' + encodeURIComponent(date.value || @json(now()->toDateString())) +
            (staff.value ? '&staff_id=' + encodeURIComponent(staff.value) : '') + (isWalkin() ? '&walkin=1' : '');
        UI.get(url + q).then(function (res) {
            if (id !== req) return;
            if (!res.ok) { area.innerHTML = '<div class="alert alert-error">' + UI.escapeHtml(res.message || 'Could not load times.') + '</div>'; return; }
            var slots = res.data || [];
            if (!slots.length) {
                hidden.value = '';
                area.innerHTML = '<div class="panel-muted text-sm">No free times for this selection. Try another date or stylist.</div>';
                return;
            }
            var html = '<div class="slot-grid">';
            var d = new Date(), nowMin = d.getHours() * 60 + d.getMinutes();
            slots.forEach(function (s) {
                var on = s.start === hidden.value;
                var who = s.stylists && s.stylists.length ? s.stylists.map(function (x) { return x.name; }).join(', ') : '';
                var p = s.start.split(':'), m = (+p[0]) * 60 + (+p[1]);
                var label = isWalkin() && Math.abs(m - nowMin) <= 15 ? 'Now' : UI.formatTime(s.start);
                html += '<button type="button" class="slot" aria-pressed="' + on + '" data-start="' + UI.escapeHtml(s.start) + '" aria-label="' + UI.escapeHtml(UI.formatTime(s.start)) + '" title="' + UI.escapeHtml(who ? 'Free: ' + who : '') + '">' + UI.escapeHtml(label) + '</button>';
            });
            area.innerHTML = html + '</div>';
            if (hidden.value && !area.querySelector('[aria-pressed="true"]')) hidden.value = '';
        });
    }

    area.addEventListener('click', function (e) {
        var b = e.target.closest('.slot');
        if (!b) return;
        hidden.value = b.dataset.start;
        area.querySelectorAll('.slot').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
    });

    [service, date, staff].forEach(function (el) { el.addEventListener('change', function () { hidden.value = ''; load(); }); });

    document.getElementById('adminBookForm').addEventListener('submit', function (e) {
        if (!hidden.value) {
            e.preventDefault();
            e.stopImmediatePropagation();
            UI.toast('Please choose a time slot.', 'error');
        }
    }, true);

    document.querySelectorAll('[data-booking-only]').forEach(function (el) { el.hidden = isWalkin(); });
    date.required = !isWalkin();
    load();
});
</script>
@endpush
