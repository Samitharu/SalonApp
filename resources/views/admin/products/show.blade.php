@extends('layouts.admin')
@section('title', $product->name)
@section('crumb', 'Stock')
@php use App\Models\Product; $st = $product->stockStatus(); @endphp

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $product->name }}</h1>
        <p>{{ $product->brand ?: 'No brand' }} · measured in {{ $product->unitLabel() }}@if($product->uses_per_unit > 1) · 1 {{ $product->unitLabel(1) }} serves {{ Product::number($product->uses_per_unit) }} clients @endif
            @unless($product->is_active) · <span class="badge badge-neutral">Archived</span>@endunless</p>
    </div>
    @perm('stock.manage')
    <div class="row">
        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-ghost"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i> Edit</a>
        @if($product->is_active)
            <a href="{{ route('admin.products.adjust', $product) }}" class="btn">Adjust</a>
            <a href="{{ route('admin.products.restock', ['product' => $product->id]) }}" class="btn btn-primary"><i class="fa-solid fa-truck-ramp-box" aria-hidden="true"></i> Restock</a>
        @endif
    </div>
    @endperm
</div>

<div class="stats">
    <div class="stat {{ $st === 'out' ? 'stat-danger' : ($st === 'low' ? 'stat-warn' : '') }}">
        <div class="stat-label"><i class="fa-solid fa-box" aria-hidden="true"></i> In stock</div>
        <div class="stat-value stat-value-sm">{{ $product->qty($product->stock) }}</div>
        <div class="stat-foot">{{ $st === 'out' ? 'Out of stock' : ($st === 'low' ? 'Running low' : 'OK') }}@if($product->clientsLeft() !== null) · ≈ {{ $product->clientsLeft() }} clients @endif</div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-bell" aria-hidden="true"></i> Alert level</div>
        <div class="stat-value stat-value-sm">{{ $product->low_stock_level !== null ? $product->qty($product->low_stock_level) : 'When out' }}</div>
        <div class="stat-foot">@perm('stock.manage')<a href="{{ route('admin.products.edit', $product) }}">Change</a>@else Set by the owner @endperm</div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Used, last 30 days</div>
        <div class="stat-value stat-value-sm">{{ $product->qty(max(0, $usedLast30)) }}</div>
        <div class="stat-foot">
            @if($usedLast30 > 0 && $product->stock > 0)
                Lasts about {{ max(1, (int) round($product->stock / ($usedLast30 / 30))) }} more days
            @else
                From completed visits
            @endif
        </div>
    </div>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-coins" aria-hidden="true"></i> Latest cost</div>
        <div class="stat-value stat-value-sm">
            @if($product->cost_per_unit)
                <small>{{ setting('currency', 'LKR') }}</small>{{ number_format($product->cost_per_unit, fmod($product->cost_per_unit, 1) ? 2 : 0) }}
            @else
                —
            @endif
        </div>
        <div class="stat-foot">per {{ $product->unitLabel(1) }}@if($product->stockValue()) · stock worth {{ money($product->stockValue()) }} @endif</div>
    </div>
</div>

<div class="dash-grid">
    <section class="card" aria-labelledby="hist-title">
        <div class="card-header"><div><h2 class="card-title" id="hist-title">Stock history</h2><div class="card-subtitle">Every change, newest first</div></div></div>
        @if($movements->isEmpty())
            <div class="card-body text-sm text-muted">No stock changes yet.</div>
        @else
            <div class="table-wrap">
                <table class="table table-stack">
                    <thead><tr><th>When</th><th>Change</th><th class="num">Qty</th><th class="num">Balance</th><th>Details</th></tr></thead>
                    <tbody>
                        @foreach($movements as $m)
                            <tr>
                                <td data-label="When" class="text-sm">
                                    <span class="nowrap">{{ ($m->occurred_on && $m->type === 'purchase' ? $m->occurred_on : $m->created_at)->format('j M Y') }}</span>
                                    <div class="text-xs text-muted">{{ $m->created_at->format('g:i A') }}@if($m->created_by_name) · {{ $m->created_by_name }}@endif</div>
                                </td>
                                <td data-label="Change">
                                    <span class="badge no-dot {{ ['purchase' => 'badge-completed', 'opening' => 'badge-info', 'usage' => 'badge-neutral', 'return' => 'badge-info', 'adjustment' => 'badge-pending'][$m->type] ?? 'badge-neutral' }}">{{ $m->typeLabel() }}</span>
                                </td>
                                <td data-label="Qty" class="num tabular fw-600 {{ $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $m->quantity > 0 ? '+' : '−' }}{{ Product::number(abs($m->quantity)) }}</td>
                                <td data-label="Balance" class="num tabular">{{ Product::number($m->balance_after) }}</td>
                                <td data-label="Details" class="text-sm">
                                    @if($m->appointment)
                                        Booking @perm('appointments.view')<a href="{{ route('admin.appointments.show', $m->appointment_id) }}" class="mono">{{ $m->appointment->appointment_no }}</a>@else<span class="mono">{{ $m->appointment->appointment_no }}</span>@endperm
                                    @elseif($m->appointment_id === null && $m->reference && in_array($m->type, ['usage', 'return'], true))
                                        Booking <span class="mono">{{ $m->reference }}</span> <span class="text-muted">(deleted)</span>
                                    @endif
                                    @if($m->type === 'purchase' || $m->type === 'opening')
                                        {{ collect([$m->supplier, $m->reference ? 'Bill ' . $m->reference : null, $m->total_cost ? money($m->total_cost) : null])->filter()->implode(' · ') ?: '—' }}
                                    @endif
                                    @if($m->reasonLabel())<div>{{ $m->reasonLabel() }}</div>@endif
                                    @if($m->note)<div class="text-muted">{{ $m->note }}</div>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($movements->hasPages())<div class="card-footer">{{ $movements->links() }}</div>@endif
        @endif
    </section>

    <div class="stack">
        <section class="card" aria-labelledby="used-title">
            <div class="card-header"><div><h2 class="card-title" id="used-title">Used in</h2><div class="card-subtitle">Per client</div></div></div>
            @if($product->services->isEmpty())
                <div class="card-body text-sm text-muted">Not linked to any service yet. Open a service and add this product under “Products used”.</div>
            @else
                <ul class="list-plain">
                    @foreach($product->services as $s)
                        <li class="row row-between">
                            @perm('services.manage')<a href="{{ route('admin.services.edit', $s) }}">{{ $s->name }}</a>@else<span>{{ $s->name }}</span>@endperm
                            <span class="tabular text-sm">{{ $product->qty((float) $s->pivot->quantity) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
        @if($product->notes)
            <section class="card card-pad">
                <h2 class="card-title mb-2">Notes</h2>
                <p class="text-sm" style="white-space:pre-line;">{{ $product->notes }}</p>
            </section>
        @endif
    </div>
</div>
@endsection
