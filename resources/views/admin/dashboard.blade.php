@extends('layouts.admin')
@section('title', 'Dashboard')
@section('crumb', now()->format('l, j F Y'))

@php
    $maxWeek = max(1, collect($week)->max('count'));
    $maxTop  = max(1, $topServices->max('c') ?? 1);
@endphp

@section('content')
@unless($canBookings)
    <section class="card card-pad mb-4">
        <h1 class="card-title">Welcome, {{ auth('admin')->user()->name }}</h1>
        @if(auth('admin')->user()->effectivePermissions())
            <p class="text-sm text-muted mt-1">Use the menu to open the sections you have access to.</p>
        @else
            <p class="text-sm text-muted mt-1">Your account doesn’t have any permissions yet. Ask the salon owner to give you access to what you need.</p>
        @endif
    </section>
@endunless

@if($canBookings || $canRevenue || $canCustomers)
<div class="stats">
    @if($canBookings)
    <div class="stat">
        <div class="stat-label"><i class="fa-regular fa-calendar" aria-hidden="true"></i> Today's appointments</div>
        <div class="stat-value">{{ $stats['today'] }}</div>
        <div class="stat-foot">{{ $stats['today_done'] }} completed</div>
    </div>
    <a class="stat" href="{{ route('admin.appointments.index', ['status' => 'pending']) }}" style="text-decoration:none;">
        <div class="stat-label"><i class="fa-regular fa-hourglass-half" aria-hidden="true"></i> Awaiting confirmation</div>
        <div class="stat-value">{{ $stats['pending'] }}</div>
        <div class="stat-foot">{{ $stats['pending'] ? 'Review pending bookings →' : 'All caught up' }}</div>
    </a>
    @endif
    @if($canRevenue)
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-coins" aria-hidden="true"></i> Revenue today</div>
        <div class="stat-value"><small>{{ setting('currency', 'LKR') }}</small>{{ number_format($stats['revenue_today']) }}</div>
        <div class="stat-foot">Month to date: {{ setting('currency', 'LKR') }} {{ number_format($stats['revenue_month']) }}</div>
    </div>
    @endif
    @if($canCustomers)
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-user-group" aria-hidden="true"></i> Customers</div>
        <div class="stat-value">{{ number_format($stats['customers']) }}</div>
        <div class="stat-foot">+{{ $stats['customers_new'] }} new this month</div>
    </div>
    @endif
</div>
@endif

<div class="dash-grid" @unless($canBookings) style="grid-template-columns:minmax(0,1fr);" @endunless>
    {{-- Today's schedule --}}
    @if($canBookings)
    <section class="card" aria-labelledby="today-title">
        <div class="card-header">
            <div>
                <h2 class="card-title" id="today-title">Today's schedule</h2>
                <div class="card-subtitle">{{ $todayAppointments->count() }} {{ \Illuminate\Support\Str::plural('booking', $todayAppointments->count()) }}</div>
            </div>
            <a href="{{ route('admin.schedule') }}" class="btn btn-sm">Open calendar</a>
        </div>
        @if($todayAppointments->isEmpty())
            <div class="empty">
                <div class="empty-icon"><i class="fa-regular fa-sun" aria-hidden="true"></i></div>
                <h3>No appointments today</h3>
                <p>New online bookings will appear here automatically.</p>
                @perm('appointments.manage')<a href="{{ route('admin.appointments.create') }}" class="btn btn-sm"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add a booking</a>@endperm
            </div>
        @else
            <ul class="timeline">
                @foreach($todayAppointments as $a)
                    <li>
                        <div class="time">{{ fmt_time($a->slot_start) }}<small>{{ fmt_time($a->slot_end) }}</small></div>
                        <a href="{{ route('admin.appointments.show', $a) }}" class="grow" style="text-decoration:none;">
                            <div class="fw-600 truncate">{{ $a->customer->name ?? '—' }}</div>
                            <div class="text-sm text-muted truncate">{{ $a->service->name ?? '—' }} · {{ $a->staff->name ?? 'Unassigned' }}</div>
                        </a>
                        @include('partials.status', ['status' => $a->status])
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
    @endif

    <div class="stack">
        {{-- Low stock alert --}}
        @if($lowStock->isNotEmpty())
            <section class="card card-alert" aria-labelledby="stock-title">
                <div class="card-header">
                    <div>
                        <h2 class="card-title" id="stock-title"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Low stock</h2>
                        <div class="card-subtitle">{{ $lowStockCount }} {{ \Illuminate\Support\Str::plural('product', $lowStockCount) }} at or below the alert level</div>
                    </div>
                    <a href="{{ route('admin.products.index', ['status' => 'low']) }}" class="btn btn-sm">View all</a>
                </div>
                <ul class="list-plain low-stock-list">
                    @foreach($lowStock as $p)
                        <li>
                            <div class="grow">
                                <a href="{{ route('admin.products.show', $p) }}" class="fw-600">{{ $p->name }}</a>
                                <div class="text-xs {{ $p->stock <= 0 ? 'text-danger' : 'text-muted' }}">{{ $p->stock <= 0 ? 'Out of stock' : $p->qty($p->stock) . ' left' }}@if($p->brand) · {{ $p->brand }}@endif</div>
                            </div>
                            @perm('stock.manage')<a href="{{ route('admin.products.restock', ['product' => $p->id]) }}" class="btn btn-sm btn-primary">Restock</a>@endperm
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Next 7 days --}}
        @if($canBookings)
        <section class="card" aria-labelledby="week-title">
            <div class="card-header">
                <div>
                    <h2 class="card-title" id="week-title">Bookings, next 7 days</h2>
                    <div class="card-subtitle">Excludes cancelled and no-shows</div>
                </div>
            </div>
            <div class="card-body">
                <div class="bars" aria-hidden="true">
                    @foreach($week as $i => $day)
                        <div class="bar-col {{ $i === 0 ? 'is-today' : '' }}" title="{{ $day['label'] }} {{ $day['date'] }}: {{ $day['count'] }} {{ \Illuminate\Support\Str::plural('booking', $day['count']) }}">
                            <span class="bar-val">{{ $day['count'] }}</span>
                            <span class="bar {{ $day['count'] ? '' : 'is-zero' }}" style="height: {{ $day['count'] ? max(4, round($day['count'] / $maxWeek * 72)) : 2 }}%;"></span>
                            <span class="bar-label">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                <table class="sr-only">
                    <caption>Bookings per day for the next 7 days</caption>
                    <tr><th>Day</th><th>Bookings</th></tr>
                    @foreach($week as $day)<tr><td>{{ $day['label'] }} {{ $day['date'] }}</td><td>{{ $day['count'] }}</td></tr>@endforeach
                </table>
            </div>
        </section>
        @endif

        {{-- Top services --}}
        @if($canRevenue)
        <section class="card" aria-labelledby="top-title">
            <div class="card-header">
                <h2 class="card-title" id="top-title">Top services this month</h2>
            </div>
            <div class="card-body">
                @forelse($topServices as $row)
                    <div class="{{ $loop->first ? '' : 'mt-4' }}">
                        <div class="row row-between text-sm"><span class="fw-600 truncate">{{ $row->name }}</span><span class="tabular text-muted">{{ $row->c }}</span></div>
                        <div class="meter" aria-hidden="true"><span style="width: {{ round($row->c / $maxTop * 100) }}%;"></span></div>
                    </div>
                @empty
                    <p class="text-sm text-muted">No bookings this month yet.</p>
                @endforelse
            </div>
        </section>
        @endif

        {{-- Recent bookings --}}
        @if($canBookings)
        <section class="card" aria-labelledby="recent-title">
            <div class="card-header">
                <h2 class="card-title" id="recent-title">Latest bookings</h2>
                <a href="{{ route('admin.appointments.index', ['scope' => 'all']) }}" class="link-btn">View all</a>
            </div>
            @if($recent->isEmpty())
                <div class="card-body text-sm text-muted">No bookings yet.</div>
            @else
                <ul class="timeline">
                    @foreach($recent as $a)
                        <li>
                            <div class="time">{{ \Carbon\Carbon::parse($a->appointment_date)->format('j M') }}<small>{{ fmt_time($a->slot_start) }}</small></div>
                            <a href="{{ route('admin.appointments.show', $a) }}" style="text-decoration:none;min-width:0;">
                                <div class="fw-600 truncate">{{ $a->customer->name ?? '—' }}</div>
                                <div class="text-sm text-muted truncate">{{ $a->service->name ?? '—' }} · {{ $a->created_at->diffForHumans() }}</div>
                            </a>
                            @include('partials.status', ['status' => $a->status])
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
        @endif
    </div>
</div>
@endsection
