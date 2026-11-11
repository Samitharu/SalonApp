@extends('layouts.admin')
@section('title', 'Change booking ' . $appointment->appointment_no)
@section('crumb', 'Appointments')

@php
    $a = $appointment;
    $curDate = \Carbon\Carbon::parse($a->appointment_date)->toDateString();
    $curStart = substr($a->slot_start, 0, 5);
    $selDate = old('date', $curDate < now()->toDateString() ? now()->toDateString() : $curDate);
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Change booking <span class="chip-code">{{ $a->appointment_no }}</span></h1>
        <p>{{ $a->customer->name ?? 'Customer' }} · currently {{ $a->service->name ?? '—' }}, {{ \Carbon\Carbon::parse($curDate)->format('D j M') }} at {{ fmt_time($a->slot_start) }} with {{ $a->staff->name ?? 'any stylist' }}.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.appointments.show', $a) }}" class="btn"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</a>
    </div>
</div>

<form method="POST" action="{{ route('admin.appointments.reschedule.save', $a) }}" class="grid" style="grid-template-columns:minmax(0,1fr);max-width:860px;" id="rescheduleForm" novalidate>
    @csrf

    @if($errors->any())
        <div class="alert alert-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
            <div>{{ $errors->first() }}</div></div>
    @endif

    <section class="card card-pad">
        <div class="form-grid">
            <div class="field full">
                <label class="label" for="service_id">Service <span class="req">*</span></label>
                <select class="select" id="service_id" name="service_id" required>
                    @foreach($services as $s)
                        <option value="{{ $s->id }}" @selected((string) old('service_id', $a->service_id) === (string) $s->id)>{{ $s->name }} — {{ $s->durationLabel() }} · {{ money($s->price) }}</option>
                    @endforeach
                </select>
                <div class="hint">@if($a->discount() > 0)This booking has {{ $a->offer_name ?: 'an offer' }} ({{ money($a->discount()) }} off). @endif Changing the service or date uses the offers for the new date. Changing only the time or stylist keeps the price.</div>
            </div>
            <div class="field">
                <label class="label" for="date">Date <span class="req">*</span></label>
                <input class="input" type="date" id="date" name="date" value="{{ $selDate }}" min="{{ now()->toDateString() }}" required>
            </div>
            <div class="field">
                <label class="label" for="staff_id">Stylist</label>
                <select class="select" id="staff_id" name="staff_id">
                    <option value="">Any available</option>
                    @foreach($staff as $s)
                        <option value="{{ $s->id }}" @selected((string) old('staff_id', $a->staff_id) === (string) $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field full">
                <span class="label" id="slot-label">Time <span class="req">*</span></span>
                <input type="hidden" name="start_time" id="start_time" value="{{ old('start_time', $selDate === $curDate ? $curStart : '') }}">
                <div id="adminSlots" aria-live="polite" aria-labelledby="slot-label">
                    <div class="panel-muted text-sm text-muted">Loading free times…</div>
                </div>
                <div class="hint">The booking’s current time is marked “Current”. Extras already on the bill are kept.</div>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.appointments.show', $a) }}" class="btn">Keep as is</a>
            <button type="submit" class="btn btn-primary" data-loading="Saving…">Save changes</button>
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
    var current = { date: @json($curDate), start: @json($curStart) };
    var ignore = @json($a->id);
    var req = 0;

    function load() {
        if (!service.value || !date.value) return;
        var id = ++req;
        area.innerHTML = '<div class="loading-row"><span class="spinner"></span> Checking availability…</div>';
        var q = '?service_id=' + encodeURIComponent(service.value) + '&date=' + encodeURIComponent(date.value) +
            (staff.value ? '&staff_id=' + encodeURIComponent(staff.value) : '') + '&ignore=' + ignore;
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
            slots.forEach(function (s) {
                var on = s.start === hidden.value;
                var isCur = date.value === current.date && s.start === current.start;
                var who = s.stylists && s.stylists.length ? s.stylists.map(function (x) { return x.name; }).join(', ') : '';
                html += '<button type="button" class="slot' + (isCur ? ' is-current' : '') + '" aria-pressed="' + on + '" data-start="' + UI.escapeHtml(s.start) + '" aria-label="' + UI.escapeHtml(UI.formatTime(s.start) + (isCur ? ' (current time)' : '')) + '" title="' + UI.escapeHtml(who ? 'Free: ' + who : '') + '">' + UI.escapeHtml(UI.formatTime(s.start)) + '</button>';
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

    [service, date, staff].forEach(function (el) { el.addEventListener('change', load); });

    document.getElementById('rescheduleForm').addEventListener('submit', function (e) {
        if (!hidden.value) {
            e.preventDefault();
            e.stopImmediatePropagation();
            UI.toast('Please choose a time slot.', 'error');
        }
    }, true);

    load();
});
</script>
@endpush
