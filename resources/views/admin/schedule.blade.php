@extends('layouts.admin')
@section('title', 'Schedule')
@section('crumb', $date->format('l, j F Y'))

@php
    $hourH   = 72;   // px per hour, matches --hour-h
    $pxPerMin = $hourH / 60;
    $hoursCount = max(1, ($endMin - $startMin) / 60);
    $isToday = $date->isToday();
    $nowMin  = now()->hour * 60 + now()->minute;
    $prev    = $date->copy()->subDay()->toDateString();
    $next    = $date->copy()->addDay()->toDateString();
    $toMin   = fn ($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $isToday ? 'Today' : $date->format('l') }}</h1>
        <p>
            {{ $date->format('j F Y') }} ·
            {{ $total }} {{ \Illuminate\Support\Str::plural('appointment', $total) }}
            @if($closure) · <span class="badge badge-cancelled no-dot">Closed{{ $closure->reason ? ': ' . $closure->reason : '' }}</span>
            @elseif(!$hours || $hours->is_closed) · <span class="badge badge-neutral no-dot">Normally closed</span>
            @else · Open {{ fmt_time($hours->open_time) }} – {{ fmt_time($hours->close_time) }}
            @endif
        </p>
    </div>
    <div class="row row-wrap">
        <div class="row" style="gap:4px;">
            <a href="{{ route('admin.schedule', ['date' => $prev]) }}" class="btn btn-icon" aria-label="Previous day"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
            <a href="{{ route('admin.schedule') }}" class="btn" @if($isToday) aria-disabled="true" @endif>Today</a>
            <a href="{{ route('admin.schedule', ['date' => $next]) }}" class="btn btn-icon" aria-label="Next day"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
        </div>
        <form method="GET" action="{{ route('admin.schedule') }}">
            <label class="sr-only" for="jumpDate">Go to date</label>
            <input type="date" id="jumpDate" name="date" class="input" value="{{ $date->toDateString() }}" data-autosubmit>
        </form>
        @perm('appointments.manage')<a href="{{ route('admin.appointments.create', ['date' => $date->toDateString()]) }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Book</a>@endperm
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="legend" aria-label="Status colours">
            <span><i style="background:var(--warning)"></i>Pending</span>
            <span><i style="background:var(--info)"></i>Confirmed</span>
            <span><i style="background:var(--progress)"></i>In progress</span>
            <span><i style="background:var(--success)"></i>Completed</span>
            <span><i style="background:var(--muted)"></i>No-show</span>
        </div>
        <a href="{{ route('admin.appointments.index', ['date' => $date->toDateString(), 'scope' => 'all']) }}" class="link-btn">List view</a>
    </div>

    @if($columns->isEmpty())
        <div class="empty">
            <div class="empty-icon"><i class="fa-solid fa-id-badge" aria-hidden="true"></i></div>
            <h3>No active staff</h3>
            <p>Add stylists to see their schedules side by side.</p>
            @perm('staff.manage')<a href="{{ route('admin.employees.create') }}" class="btn btn-sm">Add staff</a>@endperm
        </div>
    @else
    <div class="schedule-wrap">
        <div class="schedule" style="--hour-h: {{ $hourH }}px; grid-template-columns: 64px repeat({{ $columns->count() }}, minmax(170px, 1fr));">
            <div class="sch-corner"></div>
            @foreach($columns as $col)
                <div class="sch-col-head">
                    <span class="avatar avatar-sm" aria-hidden="true">{{ $col['id'] ? initials($col['name']) : '?' }}</span>
                    <span class="truncate">{{ $col['name'] }}</span>
                    <span class="count">{{ $col['items']->count() }}</span>
                </div>
            @endforeach

            <div class="sch-times" aria-hidden="true">
                @for($m = $startMin; $m < $endMin; $m += 60)
                    <div class="sch-time">{{ \Carbon\Carbon::today()->addMinutes($m)->format('g A') }}</div>
                @endfor
            </div>

            @foreach($columns as $col)
                <div class="sch-col" style="height: {{ $hoursCount * $hourH }}px;" role="list" aria-label="{{ $col['name'] }}">
                    @foreach($col['breaks'] ?? [] as [$bs, $be, $bl])
                        @if($be > $startMin && $bs < $endMin)
                            <div class="sch-break" style="top: {{ (max($bs, $startMin) - $startMin) * $pxPerMin }}px; height: {{ (min($be, $endMin) - max($bs, $startMin)) * $pxPerMin }}px;" aria-hidden="true">
                                <span>{{ $bl }}</span>
                            </div>
                        @endif
                    @endforeach
                    @if($isToday && $nowMin >= $startMin && $nowMin <= $endMin)
                        <div class="sch-now" style="top: {{ ($nowMin - $startMin) * $pxPerMin }}px;" aria-hidden="true"></div>
                    @endif
                    @foreach($col['items'] as $a)
                        @php
                            $top = ($toMin($a->slot_start) - $startMin) * $pxPerMin;
                            $h   = max(28, ($toMin($a->slot_end) - $toMin($a->slot_start)) * $pxPerMin - 2);
                        @endphp
                        <a href="{{ route('admin.appointments.show', $a) }}" class="sch-item st-{{ $a->status }}" role="listitem"
                           style="top: {{ $top }}px; height: {{ $h }}px;"
                           aria-label="{{ fmt_time($a->slot_start) }} to {{ fmt_time($a->slot_end) }}, {{ $a->customer->name ?? 'Customer' }}, {{ $a->service->name ?? '' }}, {{ status_label($a->status) }}">
                            <div class="t">{{ fmt_time($a->slot_start) }}</div>
                            <div class="n truncate">{{ $a->customer->name ?? '—' }}</div>
                            @if($h >= 60)<div class="s truncate">{{ $a->service->name ?? '' }}</div>@endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
