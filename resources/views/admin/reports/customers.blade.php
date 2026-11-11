@extends('admin.reports.layout')

@php $c = $customers; @endphp

@section('extra-filters')
    <div class="field">
        <label class="label" for="r-min">Regular = at least</label>
        <select class="select select-sm" id="r-min" name="min_visits">
            @foreach([2, 3, 4, 5, 10] as $n)
                <option value="{{ $n }}" @selected($c['minVisits'] === $n)>{{ $n }} visits</option>
            @endforeach
        </select>
    </div>
@endsection

@section('report')
<div class="stats">
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-user-group" aria-hidden="true"></i> Customers served</div>
        <div class="stat-value">{{ number_format($c['served']) }}</div>
        <div class="stat-foot">With a completed or paid visit</div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> New customers</div>
        <div class="stat-value">{{ number_format($c['new']) }}</div>
        <div class="stat-foot">First-ever visit in this period</div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-rotate" aria-hidden="true"></i> Repeat customers</div>
        <div class="stat-value">{{ number_format($c['returning']) }}</div>
        <div class="stat-foot">{{ $c['repeatRate'] === null ? 'No visits yet' : $c['repeatRate'] . '% have visited more than once' }}</div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-crown" aria-hidden="true"></i> Regulars</div>
        <div class="stat-value">{{ $c['regulars']->count() }}{{ $c['regulars']->count() >= 50 ? '+' : '' }}</div>
        <div class="stat-foot">{{ $c['minVisits'] }}+ visits in this period</div>
    </div>
</div>

<section class="card mb-5">
    <div class="card-header">
        <div>
            <h2 class="card-title">Regular customers</h2>
            <div class="card-subtitle">Customers with {{ $c['minVisits'] }} or more visits in this period, most loyal first. Pick “All time” or “This year” for a fuller picture.</div>
        </div>
    </div>
    @if($c['regulars']->isEmpty())
        <div class="empty">
            <div class="empty-icon"><i class="fa-solid fa-crown" aria-hidden="true"></i></div>
            <h3>No regulars in this period</h3>
            <p>Try a longer date range or a lower visit count.</p>
        </div>
    @else
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                    <tr><th>Customer</th><th class="num">Visits</th><th class="num">Total spent</th><th class="num">Average bill</th><th>Last visit</th><th>Usually books</th></tr>
                </thead>
                <tbody>
                    @foreach($c['regulars'] as $i => $r)
                        <tr>
                            <td class="cell-primary">
                                <a @perm('customers.view') href="{{ route('admin.customers.show', $r['id']) }}" @endperm class="person row-link" style="text-decoration:none;">
                                    <span class="avatar" aria-hidden="true">{{ initials($r['name']) }}</span>
                                    <span>
                                        <span class="person-name" style="display:block;">{{ $r['name'] }}@if($i < 3) <i class="fa-solid fa-crown" style="color:#e0a526;font-size:12px;" title="Top {{ $i + 1 }}" aria-label="Top {{ $i + 1 }}"></i>@endif</span>
                                        <span class="person-meta">{{ $r['phone'] }}</span>
                                    </span>
                                </a>
                            </td>
                            <td data-label="Visits" class="num fw-600">{{ $r['visits'] }}</td>
                            <td data-label="Total spent" class="num fw-600">{{ money($r['spent']) }}</td>
                            <td data-label="Average bill" class="num">{{ money($r['average']) }}</td>
                            <td data-label="Last visit">{{ \Carbon\Carbon::parse($r['last_visit'])->format('j M Y') }}</td>
                            <td data-label="Usually books">
                                <div>{{ $r['service'] ?? '—' }}</div>
                                @if($r['stylist'])<div class="table-sub">with {{ $r['stylist'] }}</div>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Regulars to win back</h2>
            <div class="card-subtitle">{{ $c['minVisits'] }}+ visits ever, no visit for 60+ days and nothing booked. A good list for a “we miss you” message.</div>
        </div>
    </div>
    @if($c['lapsed']->isEmpty())
        <div class="card-body"><p class="text-sm text-muted">No lapsed regulars — everyone who comes often is still coming.</p></div>
    @else
        <div class="table-wrap">
            <table class="table table-stack">
                <thead><tr><th>Customer</th><th class="num">Visits</th><th>Last visit</th><th class="actions"><span class="sr-only">Contact</span></th></tr></thead>
                <tbody>
                    @foreach($c['lapsed'] as $r)
                        <tr>
                            <td class="cell-primary">
                                <a @perm('customers.view') href="{{ route('admin.customers.show', $r['id']) }}" @endperm class="fw-600">{{ $r['name'] }}</a>
                                <div class="table-sub">{{ $r['phone'] }}</div>
                            </td>
                            <td data-label="Visits" class="num">{{ $r['visits'] }}</td>
                            <td data-label="Last visit">{{ \Carbon\Carbon::parse($r['last_visit'])->format('j M Y') }} <span class="text-muted">· {{ $r['days_ago'] }} days ago</span></td>
                            <td class="actions" data-label="">
                                @if($r['phone'])
                                    <a href="tel:{{ $r['phone'] }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-phone" aria-hidden="true"></i> Call</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
