@extends('admin.reports.layout')

@php
    $maxRevenue = max(1, $services->max('revenue'));
    $best = $services->first();
    $mostBooked = $services->sortByDesc('bookings')->first();
@endphp

@section('report')
<div class="stats">
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-trophy" aria-hidden="true"></i> Top service</div>
        <div class="stat-value stat-value-sm">{{ $best && $best['revenue'] > 0 ? $best['name'] : '—' }}</div>
        <div class="stat-foot">{{ $best && $best['revenue'] > 0 ? money($best['revenue']) . ' revenue' : 'No revenue yet' }}</div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-fire" aria-hidden="true"></i> Most booked</div>
        <div class="stat-value stat-value-sm">{{ $mostBooked ? $mostBooked['name'] : '—' }}</div>
        <div class="stat-foot">{{ $mostBooked ? $mostBooked['bookings'] . ' bookings' : 'No bookings yet' }}</div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-scissors" aria-hidden="true"></i> Services booked</div>
        <div class="stat-value">{{ $services->count() }}</div>
        <div class="stat-foot">Different services in this period</div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-plus" aria-hidden="true"></i> Extras sold</div>
        <div class="stat-value"><small>{{ setting('currency', 'LKR') }}</small>{{ number_format($extras->sum('revenue')) }}</div>
        <div class="stat-foot">{{ $extras->sum('qty') }} items added to bills</div>
    </div>
</div>

<div class="report-grid report-grid-wide">
    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Services</h2>
                <div class="card-subtitle">Revenue here is the service itself; extras added to the same bill are listed separately.</div>
            </div>
        </div>
        @if($services->isEmpty())
            <div class="card-body"><p class="text-sm text-muted">No bookings in this period.</p></div>
        @else
            <div class="table-wrap">
                <table class="table table-stack">
                    <thead><tr><th>Service</th><th class="num">Bookings</th><th class="num">Completed</th><th class="num">Lost</th><th>Revenue</th></tr></thead>
                    <tbody>
                        @foreach($services as $s)
                            <tr>
                                <td class="cell-primary">
                                    <div class="fw-600">{{ $s['name'] }}</div>
                                    <div class="table-sub">{{ money($s['price']) }} · {{ $s['duration'] }} min</div>
                                </td>
                                <td data-label="Bookings" class="num">{{ $s['bookings'] }}</td>
                                <td data-label="Completed" class="num fw-600">{{ $s['completed'] }}</td>
                                <td data-label="Lost" class="num" title="Cancelled or no-show">{{ $s['lostRate'] }}%</td>
                                <td data-label="Revenue" class="report-money">
                                    <span class="fw-600 tabular nowrap">{{ money($s['revenue']) }}</span>
                                    <div class="meter"><span style="width: {{ round($s['revenue'] / $maxRevenue * 100) }}%"></span></div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card">
        <div class="card-header"><h2 class="card-title">Extras &amp; add-ons sold</h2></div>
        @if($extras->isEmpty())
            <div class="card-body"><p class="text-sm text-muted">No extras added to bills in this period.</p></div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Revenue</th></tr></thead>
                    <tbody>
                        @foreach($extras as $e)
                            <tr><td>{{ $e['name'] }}</td><td class="num">{{ $e['qty'] }}</td><td class="num fw-600">{{ money($e['revenue']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
