@extends('admin.reports.layout')

@php
    $top = $staff->where('id', '>', 0)->sortByDesc('revenue')->first();
    $mostDone = $staff->where('id', '>', 0)->sortByDesc('completed')->first();
    $bestRated = $staff->where('reviews', '>=', 3)->sortByDesc('rating')->first();
    $maxRevenue = max(1, $staff->max('revenue'));
@endphp

@section('report')
<div class="stats">
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-trophy" aria-hidden="true"></i> Top earner</div>
        <div class="stat-value stat-value-sm">{{ $top && $top['revenue'] > 0 ? $top['name'] : '—' }}</div>
        <div class="stat-foot">{{ $top && $top['revenue'] > 0 ? money($top['revenue']) : 'No revenue yet' }}</div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Most jobs completed</div>
        <div class="stat-value stat-value-sm">{{ $mostDone && $mostDone['completed'] ? $mostDone['name'] : '—' }}</div>
        <div class="stat-foot">{{ $mostDone && $mostDone['completed'] ? $mostDone['completed'] . ' completed' : 'None yet' }}</div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-star" aria-hidden="true"></i> Best rated</div>
        <div class="stat-value stat-value-sm">{{ $bestRated ? $bestRated['name'] : '—' }}</div>
        <div class="stat-foot">{{ $bestRated ? $bestRated['rating'] . ' / 5 from ' . $bestRated['reviews'] . ' reviews' : 'Needs 3+ reviews in the period' }}</div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-clock" aria-hidden="true"></i> Hours of service</div>
        <div class="stat-value">{{ number_format($staff->sum('hours'), 1) }}</div>
        <div class="stat-foot">Completed or paid work, by service length</div>
    </div>
</div>

<section class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Staff performance</h2>
            <div class="card-subtitle">Lost = cancelled / no-show. Rate = completed ÷ (completed + cancelled + no-show). Extras = add-ons sold on their bills. Ratings come from customer feedback.</div>
        </div>
    </div>
    @if($staff->isEmpty())
        <div class="empty"><h3>No staff yet</h3><p>Add staff to see their performance.</p></div>
    @else
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                    <tr>
                        <th>Stylist</th>
                        <th class="num">Bookings</th>
                        <th class="num">Completed</th>
                        <th class="num"><abbr title="Cancelled / no-show">Lost</abbr></th>
                        <th class="num">Rate</th>
                        <th class="num">Hours</th>
                        <th>Revenue</th>
                        <th class="num">Extras</th>
                        <th class="num">Rating</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($staff as $s)
                        <tr>
                            <td class="cell-primary">
                                <div class="person">
                                    <span class="avatar" aria-hidden="true">{{ $s['id'] ? initials($s['name']) : '?' }}</span>
                                    <div>
                                        <div class="fw-600 nowrap">{{ $s['name'] }} @unless($s['active'])<span class="badge badge-no_show no-dot">Inactive</span>@endunless</div>
                                        <div class="table-sub">{{ $s['title'] }}@if($s['walkins']) · {{ $s['walkins'] }} {{ \Illuminate\Support\Str::plural('walk-in', $s['walkins']) }}@endif</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Bookings" class="num">{{ $s['bookings'] }}</td>
                            <td data-label="Completed" class="num fw-600">{{ $s['completed'] }}</td>
                            <td data-label="Cancelled / no-show" class="num">{{ $s['cancelled'] }} / {{ $s['no_show'] }}</td>
                            <td data-label="Completion rate" class="num">{{ $s['completion'] === null ? '—' : $s['completion'] . '%' }}</td>
                            <td data-label="Hours" class="num">{{ number_format($s['hours'], 1) }}</td>
                            <td data-label="Revenue" class="report-money">
                                <span class="fw-600 tabular nowrap">{{ money($s['revenue']) }}</span>
                                <div class="meter"><span style="width: {{ round($s['revenue'] / $maxRevenue * 100) }}%"></span></div>
                            </td>
                            <td data-label="Extras sold" class="num">{{ money($s['extras']) }}</td>
                            <td data-label="Rating" class="num">
                                @if($s['rating'] !== null)
                                    <span class="nowrap"><i class="fa-solid fa-star" style="color:#e0a526;" aria-hidden="true"></i> {{ number_format($s['rating'], 1) }} <span class="text-muted">({{ $s['reviews'] }})</span></span>
                                @else
                                    <span class="text-muted">—</span>
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
