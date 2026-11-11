{{-- Change vs the previous period of the same length. Icon + words, not colour alone. --}}
@php
    $pct = $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
@endphp
<div class="stat-foot">
    @if($pct === null)
        {{ $current > 0 ? 'No data for the previous period' : 'No activity' }}
    @elseif($pct == 0)
        <i class="fa-solid fa-minus" aria-hidden="true"></i> Same as previous period
    @else
        <span class="delta {{ $pct > 0 ? 'is-up' : 'is-down' }}">
            <i class="fa-solid {{ $pct > 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}" aria-hidden="true"></i>
            {{ $pct > 0 ? '+' : '' }}{{ $pct }}%
        </span> vs previous {{ $report->days() }} {{ \Illuminate\Support\Str::plural('day', $report->days()) }}
    @endif
</div>
