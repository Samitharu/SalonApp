@extends('admin.reports.layout')

@php
    $b = $bookings;
    $pct = fn ($v) => $v === null ? '—' : $v . '%';
    $toPoints = fn (array $data) => collect($data)->map(fn ($v, $k) => ['label' => $k, 'short' => $k, 'value' => $v])->values()->all();
@endphp

@section('report')
<div class="stats">
    <div class="stat">
        <div class="stat-label"><i class="fa-regular fa-calendar" aria-hidden="true"></i> Bookings</div>
        <div class="stat-value">{{ number_format($b['total']) }}</div>
        @include('admin.reports.partials.delta', ['current' => $b['total'], 'previous' => $b['previous']])
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Completion rate</div>
        <div class="stat-value">{{ $pct($b['completion']) }}</div>
        <div class="stat-foot">Of bookings that are finished (completed, cancelled or no-show)</div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-ban" aria-hidden="true"></i> Cancellations</div>
        <div class="stat-value">{{ $pct($b['cancelRate']) }}</div>
        <div class="stat-foot">{{ $b['status']['cancelled'] }} cancelled · {{ $b['cancelledByCustomer'] }} by the customer online</div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-user-xmark" aria-hidden="true"></i> No-shows</div>
        <div class="stat-value">{{ $pct($b['noShowRate']) }}</div>
        <div class="stat-foot">{{ $b['status']['no_show'] }} {{ \Illuminate\Support\Str::plural('booking', $b['status']['no_show']) }}</div>
    </div>
</div>

<section class="card mb-5">
    <div class="card-header"><h2 class="card-title">Bookings per {{ $report->days() > 62 ? 'month' : 'day' }}</h2></div>
    <div class="card-body">
        @include('admin.reports.partials.bars', ['points' => $b['series'], 'title' => 'Bookings per ' . ($report->days() > 62 ? 'month' : 'day')])
    </div>
</section>

<div class="report-grid">
    <section class="card">
        <div class="card-header"><h2 class="card-title">By status</h2></div>
        <div class="card-body stack-sm">
            @foreach($b['status'] as $status => $count)
                <div class="report-row">
                    @include('partials.status', ['status' => $status])
                    <div class="meter"><span style="width: {{ $b['total'] ? round($count / $b['total'] * 100) : 0 }}%"></span></div>
                    <span class="tabular fw-600">{{ $count }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <div class="card-header"><h2 class="card-title">How customers booked</h2></div>
        <div class="card-body stack-sm">
            @php $chTotal = max(1, array_sum($b['channels'])); @endphp
            @foreach($b['channels'] as $label => $count)
                <div class="report-row">
                    <span class="text-sm">{{ $label }}</span>
                    <div class="meter"><span style="width: {{ round($count / $chTotal * 100) }}%"></span></div>
                    <span class="tabular fw-600">{{ $count }} <span class="text-muted fw-400">({{ round($count / $chTotal * 100) }}%)</span></span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Busiest days</h2>
                <div class="card-subtitle">Bookings that weren’t cancelled</div>
            </div>
        </div>
        <div class="card-body">
            @include('admin.reports.partials.bars', ['points' => $toPoints($b['weekdays']), 'title' => 'Bookings by weekday'])
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Busiest times</h2>
                <div class="card-subtitle">By start hour</div>
            </div>
        </div>
        <div class="card-body">
            @if($b['hours'])
                @include('admin.reports.partials.bars', ['points' => $toPoints($b['hours']), 'title' => 'Bookings by start hour'])
            @else
                <p class="text-sm text-muted">No bookings in this period.</p>
            @endif
        </div>
    </section>
</div>
@endsection
