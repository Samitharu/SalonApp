@extends('layouts.admin')
@section('title', 'Opening hours')

@section('content')
<div class="page-head">
    <div>
        <h1>Opening hours</h1>
        <p>Customers can only book inside opening hours, never across a lunch break, and with cleanup time kept free after each appointment.</p>
    </div>
</div>

<form method="POST" action="{{ route('admin.hours.update') }}" class="card mb-6" novalidate>
    @csrf @method('PUT')
    <div class="card-header">
        <div>
            <h2 class="card-title">Weekly hours &amp; lunch break</h2>
            <div class="card-subtitle">Leave the break empty if the salon stays open all day. Stylists can also have their own break on their profile.</div>
        </div>
        <button type="button" class="btn btn-sm" id="copyMonday"><i class="fa-regular fa-copy" aria-hidden="true"></i> Copy Monday to all open days</button>
    </div>

    @if($errors->any() && !$errors->closure->any())
        <div class="card-body" style="padding-bottom:0;">
            <div class="alert alert-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <ul style="margin:0;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        </div>
    @endif

    <div class="hours-head" aria-hidden="true">
        <span>Day</span><span></span><span>Open</span><span>Lunch break <span class="optional">(optional)</span></span>
    </div>
    <div>
        @foreach([1, 2, 3, 4, 5, 6, 0] as $d)
            @php
                $h = $hours[$d] ?? null;
                $dayName = \App\Models\BusinessHour::DAYS[$d];
                $open  = old("days.$d.open", $h ? !$h->is_closed : ($d !== 0));
                $from  = old("days.$d.from", $h && $h->open_time ? substr($h->open_time, 0, 5) : '09:00');
                $to    = old("days.$d.to", $h && $h->close_time ? substr($h->close_time, 0, 5) : '18:00');
                $bFrom = old("days.$d.break_from", $h && $h->break_start ? substr($h->break_start, 0, 5) : '');
                $bTo   = old("days.$d.break_to", $h && $h->break_end ? substr($h->break_end, 0, 5) : '');
            @endphp
            <div class="hours-row {{ $open ? '' : 'is-closed' }}" data-hours-row data-day="{{ $d }}">
                <div class="fw-600">{{ $dayName }}</div>
                <label class="switch">
                    <input type="hidden" name="days[{{ $d }}][open]" value="0">
                    <input type="checkbox" name="days[{{ $d }}][open]" value="1" @checked($open) data-hours-toggle>
                    <span class="track" aria-hidden="true"></span>
                    <span data-hours-label>{{ $open ? 'Open' : 'Closed' }}</span>
                </label>
                <div class="times">
                    <span class="times-label">Open</span>
                    <label class="sr-only" for="from-{{ $d }}">{{ $dayName }} opening time</label>
                    <input class="input input-sm" type="time" id="from-{{ $d }}" name="days[{{ $d }}][from]" value="{{ $from }}" data-f="from">
                    <span class="text-muted">to</span>
                    <label class="sr-only" for="to-{{ $d }}">{{ $dayName }} closing time</label>
                    <input class="input input-sm" type="time" id="to-{{ $d }}" name="days[{{ $d }}][to]" value="{{ $to }}" data-f="to" @error("days.$d.to") aria-invalid="true" @enderror>
                </div>
                <div class="times">
                    <span class="times-label">Lunch</span>
                    <label class="sr-only" for="bfrom-{{ $d }}">{{ $dayName }} break start</label>
                    <input class="input input-sm" type="time" id="bfrom-{{ $d }}" name="days[{{ $d }}][break_from]" value="{{ $bFrom }}" data-f="break_from">
                    <span class="text-muted">to</span>
                    <label class="sr-only" for="bto-{{ $d }}">{{ $dayName }} break end</label>
                    <input class="input input-sm" type="time" id="bto-{{ $d }}" name="days[{{ $d }}][break_to]" value="{{ $bTo }}" data-f="break_to" @error("days.$d.break_to") aria-invalid="true" @enderror>
                    <button type="button" class="btn btn-ghost btn-icon btn-sm" data-clear-break aria-label="Clear {{ $dayName }} break" title="No break"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card-body" style="border-top:1px solid var(--border);">
        <div class="form-grid">
            <div class="field">
                <label class="label" for="buffer_minutes">Time between appointments (cleanup)</label>
                @php $buffer = (string) old('buffer_minutes', setting('buffer_minutes', '0')); @endphp
                <select class="select" id="buffer_minutes" name="buffer_minutes">
                    @foreach(['0' => 'None — back-to-back', '5' => '5 minutes', '10' => '10 minutes', '15' => '15 minutes', '20' => '20 minutes', '30' => '30 minutes'] as $val => $label)
                        <option value="{{ $val }}" @selected($buffer === (string) $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hint" style="align-self:center;">
                Kept free after every appointment for cleaning and setup. Services that need longer (e.g. colour) can set their own cleanup time in <a href="{{ route('admin.services.index') }}">Services</a>.
            </div>
        </div>
    </div>

    <div class="card-footer row row-end">
        <button type="submit" class="btn btn-primary">Save hours</button>
    </div>
</form>

<div class="grid-2" style="align-items:start;">
        <form method="POST" action="{{ route('admin.hours.closures.store') }}" class="card" novalidate>
            @csrf
            <div class="card-header">
                <div>
                    <h2 class="card-title">Add a closure</h2>
                    <div class="card-subtitle">Holidays or days the salon is shut.</div>
                </div>
            </div>
            <div class="card-body stack">
                <div class="field">
                    <label class="label" for="off_date">Date</label>
                    <input class="input" type="date" id="off_date" name="off_date" min="{{ now()->toDateString() }}" value="{{ old('off_date') }}" required @if($errors->closure->has('off_date')) aria-invalid="true" @endif>
                    @if($errors->closure->has('off_date'))<div class="error-text">{{ $errors->closure->first('off_date') }}</div>@endif
                </div>
                <div class="field">
                    <label class="label" for="reason">Reason <span class="optional">(optional)</span></label>
                    <input class="input" id="reason" name="reason" maxlength="150" value="{{ old('reason') }}" placeholder="e.g. Poya day">
                </div>
                <p class="hint">Existing bookings on that date are not cancelled automatically — check the schedule.</p>
            </div>
            <div class="card-footer row row-end"><button type="submit" class="btn btn-primary">Add closure</button></div>
        </form>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Upcoming closures</h2></div>
            @if($closures->isEmpty())
                <div class="card-body text-sm text-muted">No closures scheduled.</div>
            @else
                <ul class="timeline">
                    @foreach($closures as $c)
                        <li style="grid-template-columns:minmax(0,1fr) auto;">
                            <div>
                                <div class="fw-600">{{ $c->off_date->format('l, j F Y') }}</div>
                                @if($c->reason)<div class="text-sm text-muted">{{ $c->reason }}</div>@endif
                                @if($n = $affected[$c->off_date->toDateString()] ?? 0)
                                    <a href="{{ route('admin.appointments.index', ['date' => $c->off_date->toDateString(), 'scope' => 'all']) }}" class="badge badge-pending no-dot mt-1">
                                        {{ $n }} {{ \Illuminate\Support\Str::plural('booking', $n) }} still on this day
                                    </a>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('admin.hours.closures.destroy', $c) }}" data-confirm="Remove this closure?" data-confirm-button="Remove">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Remove closure on {{ $c->off_date->format('j M') }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-hours-toggle]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var row = cb.closest('[data-hours-row]');
            row.classList.toggle('is-closed', !cb.checked);
            row.querySelector('[data-hours-label]').textContent = cb.checked ? 'Open' : 'Closed';
        });
    });

    document.querySelectorAll('[data-clear-break]').forEach(function (b) {
        b.addEventListener('click', function () {
            b.closest('[data-hours-row]').querySelectorAll('[data-f="break_from"], [data-f="break_to"]').forEach(function (i) { i.value = ''; });
        });
    });

    document.getElementById('copyMonday').addEventListener('click', function () {
        var mon = document.querySelector('[data-hours-row][data-day="1"]');
        var vals = {};
        mon.querySelectorAll('[data-f]').forEach(function (i) { vals[i.dataset.f] = i.value; });
        var n = 0;
        document.querySelectorAll('[data-hours-row]').forEach(function (row) {
            if (row === mon || !row.querySelector('[data-hours-toggle]').checked) return;
            row.querySelectorAll('[data-f]').forEach(function (i) { i.value = vals[i.dataset.f]; });
            n++;
        });
        UI.toast(n ? 'Copied Monday’s hours to ' + n + ' open day' + (n > 1 ? 's' : '') + '. Remember to save.' : 'No other open days to copy to.', n ? 'success' : 'info');
    });
});
</script>
@endpush
