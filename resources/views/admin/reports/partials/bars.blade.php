{{--
  Single-series vertical bar chart (no library). One hue, no legend: the card
  title names the series. Each bar has a hover/focus tooltip; the same numbers
  are available in the table or CSV export.

  @param array  $points  [['label' => 'Mon 3 Nov', 'short' => '3', 'value' => 1200.5], …]
  @param string $title   Accessible description, e.g. "Revenue per day"
  @param bool   $money   Format values as currency (default: counts)
--}}
@php
    $money = $money ?? false;
    $max = max(1, collect($points)->max('value'));
    $fmt = fn ($v) => $money ? money($v) : number_format($v);
    $every = max(1, (int) ceil(count($points) / 12)); // ~12 axis labels at most
    $total = collect($points)->sum('value');
@endphp
<div class="bar-chart" role="img" aria-label="{{ $title }}: total {{ $fmt($total) }}, highest {{ $fmt(collect($points)->max('value') ?? 0) }}">
    <div class="bar-chart-max tabular" aria-hidden="true">{{ $fmt($max) }}</div>
    <div class="bar-plot" style="--bars: {{ max(1, count($points)) }};">
        @foreach($points as $p)
            <div class="bar-col" tabindex="0" data-tip="{{ $p['label'] }}: {{ $fmt($p['value']) }}{{ isset($p['count']) && $money ? ' · ' . $p['count'] . ' ' . \Illuminate\Support\Str::plural('bill', $p['count']) : '' }}">
                <span class="bar {{ $p['value'] > 0 ? '' : 'is-zero' }}" style="height: {{ $p['value'] > 0 ? max(2, round($p['value'] / $max * 100, 1)) : 0 }}%"></span>
            </div>
        @endforeach
    </div>
    <div class="bar-axis" aria-hidden="true" style="--bars: {{ max(1, count($points)) }};">
        @foreach($points as $i => $p)
            <span>{{ $i % $every === 0 ? $p['short'] : '' }}</span>
        @endforeach
    </div>
</div>
