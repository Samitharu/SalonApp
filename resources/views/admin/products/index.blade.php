@extends('layouts.admin')
@section('title', 'Stock')

@section('content')
<div class="page-head">
    <div>
        <h1>Stock</h1>
        <p>Products used on clients. Stock goes down automatically when a visit is completed.</p>
    </div>
    @perm('stock.manage')
    <div class="row">
        <a href="{{ route('admin.products.restock') }}" class="btn"><i class="fa-solid fa-truck-ramp-box" aria-hidden="true"></i> Restock</a>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add product</a>
    </div>
    @endperm
</div>

<div class="stats">
    <a class="stat" href="{{ route('admin.products.index') }}" style="text-decoration:none;">
        <div class="stat-label"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Products</div>
        <div class="stat-value">{{ $summary['products'] }}</div>
        <div class="stat-foot">{{ $summary['archived'] ? $summary['archived'] . ' archived' : 'In use' }}</div>
    </a>
    <a class="stat {{ $summary['low'] ? 'stat-warn' : '' }}" href="{{ route('admin.products.index', ['status' => 'low']) }}" style="text-decoration:none;">
        <div class="stat-label"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Running low</div>
        <div class="stat-value">{{ $summary['low'] }}</div>
        <div class="stat-foot">{{ $summary['low'] ? 'Restock soon →' : 'All above alert level' }}</div>
    </a>
    <a class="stat {{ $summary['out'] ? 'stat-danger' : '' }}" href="{{ route('admin.products.index', ['status' => 'out']) }}" style="text-decoration:none;">
        <div class="stat-label"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> Out of stock</div>
        <div class="stat-value">{{ $summary['out'] }}</div>
        <div class="stat-foot">{{ $summary['out'] ? 'Needs restocking →' : 'Nothing out' }}</div>
    </a>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-coins" aria-hidden="true"></i> Stock value</div>
        <div class="stat-value"><small>{{ setting('currency', 'LKR') }}</small>{{ number_format($summary['value']) }}</div>
        <div class="stat-foot">At latest purchase prices</div>
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar" role="search">
        <label class="search-box grow">
            <span class="sr-only">Search products</span>
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" class="input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by name or brand">
        </label>
        <select class="select" name="status" aria-label="Stock level" style="max-width:200px;">
            <option value="">All stock levels</option>
            <option value="low" @selected(($filters['status'] ?? '') === 'low')>Low or out</option>
            <option value="out" @selected(($filters['status'] ?? '') === 'out')>Out of stock</option>
        </select>
        @if($archived)<input type="hidden" name="archived" value="1">@endif
        <button type="submit" class="btn">Filter</button>
        <a href="{{ route('admin.products.index', $archived ? [] : ['archived' => 1]) }}" class="btn btn-ghost">{{ $archived ? 'Back to active' : 'Archived (' . $summary['archived'] . ')' }}</a>
    </form>

    @if($products->isEmpty())
        <div class="empty">
            <div class="empty-icon"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i></div>
            @if(!empty($filters['search']) || !empty($filters['status']) || $archived)
                <h3>No products match</h3>
                <p>Try a different search or filter.</p>
            @else
                <h3>No products yet</h3>
                <p>Add the creams, packs, colours and other items you use, then link them to your services.</p>
                @perm('stock.manage')<a href="{{ route('admin.products.create') }}" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add product</a>@endperm
            @endif
        </div>
    @else
        <div class="table-wrap">
            <table class="table table-stack">
                <thead><tr><th>Product</th><th class="num">In stock</th><th>Status</th><th class="num">Used in</th><th class="num">Value</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @foreach($products as $p)
                        @php $st = $p->stockStatus(); @endphp
                        <tr>
                            <td class="cell-primary">
                                <a href="{{ route('admin.products.show', $p) }}" class="row-link">{{ $p->name }}</a>
                                <div class="text-xs text-muted">{{ $p->brand ?: 'No brand' }}@if($p->uses_per_unit > 1) · 1 {{ $p->unitLabel(1) }} serves {{ \App\Models\Product::number($p->uses_per_unit) }}@endif</div>
                            </td>
                            <td data-label="In stock" class="num">
                                <span class="fw-600 tabular {{ $st === 'out' ? 'text-danger' : '' }}">{{ $p->qty($p->stock) }}</span>
                                @if($p->clientsLeft() !== null)<div class="text-xs text-muted">≈ {{ $p->clientsLeft() }} clients</div>@endif
                            </td>
                            <td data-label="Status">
                                @if($st === 'out')
                                    <span class="badge badge-cancelled">Out of stock</span>
                                @elseif($st === 'low')
                                    <span class="badge badge-pending">Low</span>
                                @else
                                    <span class="badge badge-completed">OK</span>
                                @endif
                                @if($p->low_stock_level !== null)<div class="text-xs text-muted mt-1">Alert at {{ \App\Models\Product::number($p->low_stock_level) }}</div>@endif
                            </td>
                            <td data-label="Used in" class="num">{{ $p->services_count }} {{ \Illuminate\Support\Str::plural('service', $p->services_count) }}</td>
                            <td data-label="Value" class="num tabular">{{ $p->stockValue() !== null ? money($p->stockValue()) : '—' }}</td>
                            <td class="actions" data-label="">
                                @perm('stock.manage')
                                <div class="row" style="flex-wrap:nowrap;">
                                    @if($p->is_active)
                                        <a href="{{ route('admin.products.restock', ['product' => $p->id]) }}" class="btn btn-sm {{ $st !== 'ok' ? 'btn-primary' : '' }}">Restock</a>
                                        <a href="{{ route('admin.products.adjust', $p) }}" class="btn btn-ghost btn-sm" title="Count stock or record damage">Adjust</a>
                                        <a href="{{ route('admin.products.edit', $p) }}" class="btn btn-ghost btn-icon btn-sm" aria-label="Edit {{ $p->name }}"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i></a>
                                    @else
                                        <form method="POST" action="{{ route('admin.products.restore', $p) }}">@csrf<button type="submit" class="btn btn-sm">Restore</button></form>
                                    @endif
                                </div>
                                @endperm
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($products->hasPages())<div class="card-footer">{{ $products->links() }}</div>@endif
    @endif
</div>
@endsection
