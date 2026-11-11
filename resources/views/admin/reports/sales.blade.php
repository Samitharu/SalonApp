@extends('admin.reports.layout')

@php $s = $sales; $cur = setting('currency', 'LKR'); @endphp

@section('report')
<div class="stats">
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-coins" aria-hidden="true"></i> Revenue</div>
        <div class="stat-value"><small>{{ $cur }}</small>{{ number_format($s['revenue']) }}</div>
        @include('admin.reports.partials.delta', ['current' => $s['revenue'], 'previous' => $s['previous']])
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-receipt" aria-hidden="true"></i> Bills</div>
        <div class="stat-value">{{ number_format($s['bills']) }}</div>
        <div class="stat-foot">Average bill {{ money($s['average']) }}</div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Collected</div>
        <div class="stat-value"><small>{{ $cur }}</small>{{ number_format($s['collected']) }}</div>
        <div class="stat-foot">Paid at the cashier</div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> Outstanding</div>
        <div class="stat-value"><small>{{ $cur }}</small>{{ number_format($s['outstanding']) }}</div>
        <div class="stat-foot">{{ $s['outstanding'] > 0 ? 'Completed but not yet paid' : 'Nothing unpaid' }}</div>
    </div>
</div>

<section class="card mb-5">
    <div class="card-header">
        <div>
            <h2 class="card-title">Revenue per {{ $report->days() > 62 ? 'month' : 'day' }}</h2>
            <div class="card-subtitle">Hover or tab to a bar for the exact amount.</div>
        </div>
    </div>
    <div class="card-body">
        @include('admin.reports.partials.bars', ['points' => $s['series'], 'title' => 'Revenue per ' . ($report->days() > 62 ? 'month' : 'day'), 'money' => true])
    </div>
</section>

<div class="report-grid">
    <section class="card">
        <div class="card-header"><h2 class="card-title">Where the money came from</h2></div>
        <div class="card-body stack">
            @php $base = max(1, $s['revenue']); @endphp
            <div>
                <div class="row row-between text-sm"><span>Services</span><span class="fw-600 tabular">{{ money($s['services']) }}</span></div>
                <div class="meter"><span style="width: {{ round($s['services'] / $base * 100) }}%"></span></div>
            </div>
            <div>
                <div class="row row-between text-sm"><span>Extras &amp; add-ons</span><span class="fw-600 tabular">{{ money($s['extras']) }}</span></div>
                <div class="meter"><span style="width: {{ round($s['extras'] / $base * 100) }}%"></span></div>
            </div>
            <div class="label mt-2">By payment method</div>
            <dl class="kv">
                @php $paidTotal = max(1, collect($s['methods'])->sum('total')); @endphp
                @foreach($s['methods'] as $key => $m)
                    <div><dt><i class="fa-solid {{ \App\Models\Payment::ICONS[$key] }}" aria-hidden="true"></i> {{ $m['label'] }} · {{ $m['count'] }} {{ \Illuminate\Support\Str::plural('payment', $m['count']) }}</dt>
                        <dd>{{ money($m['total']) }} <span class="text-muted">({{ round($m['total'] / $paidTotal * 100) }}%)</span></dd></div>
                @endforeach
            </dl>
            @if($s['advancesHeld']['total'] > 0)
                <div class="alert alert-info"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
                    <div><strong>{{ money($s['advancesHeld']['total']) }}</strong> in advances held for {{ $s['advancesHeld']['bookings'] }} upcoming {{ \Illuminate\Support\Str::plural('booking', $s['advancesHeld']['bookings']) }} (not counted above until the visit).</div></div>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card-header"><h2 class="card-title">Top services by revenue</h2></div>
        @if($s['topServices']->isEmpty())
            <div class="card-body"><p class="text-sm text-muted">No completed or paid bills in this period.</p></div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Service</th><th class="num">Bills</th><th class="num">Revenue</th></tr></thead>
                    <tbody>
                        @foreach($s['topServices'] as $row)
                            <tr>
                                <td>
                                    <div class="fw-600">{{ $row->name }}</div>
                                    <div class="meter"><span style="width: {{ round($row->revenue / max(1, $s['topServices']->max('revenue')) * 100) }}%"></span></div>
                                </td>
                                <td class="num">{{ $row->bookings }}</td>
                                <td class="num fw-600">{{ money($row->revenue) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
