@extends('layouts.admin')
@section('title', 'Restock')
@section('crumb', 'Stock')
@php use App\Models\Product; $sel = old('product_id', $selected?->id); @endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Restock</h1>
        <p>Record what you bought. Stock goes up straight away.</p>
    </div>
</div>

@if($products->isEmpty())
    <div class="card">
        <div class="empty">
            <div class="empty-icon"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i></div>
            <h3>No products yet</h3>
            <p>Add a product first, then restock it here.</p>
            <a href="{{ route('admin.products.create') }}" class="btn btn-sm btn-primary">Add product</a>
        </div>
    </div>
@else
<form method="POST" action="{{ route('admin.products.restock.store') }}" class="card card-pad" style="max-width:760px;" novalidate>
    @csrf
    <div class="form-grid">
        <div class="field full">
            <label class="label" for="product_id">Product <span class="req">*</span></label>
            <select class="select" id="product_id" name="product_id" required @error('product_id') aria-invalid="true" @enderror>
                <option value="">Choose a product…</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" data-many="{{ $p->unitLabel() }}" data-stock="{{ $p->qty($p->stock) }}" data-status="{{ $p->stockStatus() }}" @selected((string) $sel === (string) $p->id)>
                        {{ $p->displayName() }} — {{ $p->qty($p->stock) }}{{ $p->stockStatus() === 'out' ? ' (out)' : ($p->stockStatus() === 'low' ? ' (low)' : '') }}
                    </option>
                @endforeach
            </select>
            @error('product_id')<div class="error-text">{{ $message }}</div>@else<div class="hint" id="stockNow"></div>@enderror
        </div>

        <div class="field">
            <label class="label" for="quantity">Quantity bought <span class="req">*</span></label>
            <div class="input-suffix">
                <input class="input tabular" type="number" id="quantity" name="quantity" min="0.001" step="0.001" value="{{ old('quantity') }}" required inputmode="decimal" @error('quantity') aria-invalid="true" @enderror>
                <span data-unit-many>units</span>
            </div>
            @error('quantity')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label class="label" for="total_cost">Total paid ({{ setting('currency', 'LKR') }}) <span class="optional">(optional)</span></label>
            <input class="input tabular" type="number" id="total_cost" name="total_cost" min="0" step="0.01" value="{{ old('total_cost') }}" inputmode="decimal" @error('total_cost') aria-invalid="true" @enderror>
            @error('total_cost')<div class="error-text">{{ $message }}</div>@else<div class="hint" id="unitCost">The bill amount for this product.</div>@enderror
        </div>

        <div class="field">
            <label class="label" for="supplier">Supplier <span class="optional">(optional)</span></label>
            <input class="input" id="supplier" name="supplier" value="{{ old('supplier') }}" maxlength="120" list="suppliers">
        </div>
        <div class="field">
            <label class="label" for="reference">Bill / invoice no. <span class="optional">(optional)</span></label>
            <input class="input" id="reference" name="reference" value="{{ old('reference') }}" maxlength="60">
        </div>

        <div class="field">
            <label class="label" for="occurred_on">Date bought</label>
            <input class="input" type="date" id="occurred_on" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" @error('occurred_on') aria-invalid="true" @enderror>
            @error('occurred_on')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label class="label" for="note">Note <span class="optional">(optional)</span></label>
            <input class="input" id="note" name="note" value="{{ old('note') }}" maxlength="255" placeholder="e.g. expiry March 2027">
        </div>
    </div>

    <div class="form-actions">
        <a href="{{ route('admin.products.index') }}" class="btn">Cancel</a>
        <button type="submit" name="next" value="another" class="btn">Save &amp; restock another</button>
        <button type="submit" class="btn btn-primary">Save</button>
    </div>
</form>

<datalist id="suppliers">
    @foreach(\App\Models\StockMovement::whereNotNull('supplier')->distinct()->orderBy('supplier')->limit(50)->pluck('supplier') as $s)
        <option value="{{ $s }}">
    @endforeach
</datalist>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var product = document.getElementById('product_id');
    if (!product) return;
    var qty = document.getElementById('quantity'), cost = document.getElementById('total_cost');
    var stockNow = document.getElementById('stockNow'), unitCost = document.getElementById('unitCost');
    var currency = @json(setting('currency', 'LKR'));

    function sync() {
        var o = product.options[product.selectedIndex];
        var many = o && o.dataset.many ? o.dataset.many : 'units';
        document.querySelectorAll('[data-unit-many]').forEach(function (el) { el.textContent = many; });
        if (stockNow) stockNow.textContent = o && o.dataset.stock ? 'In stock now: ' + o.dataset.stock : '';
        var q = parseFloat(qty.value), c = parseFloat(cost.value);
        if (unitCost) unitCost.textContent = q > 0 && c > 0
            ? currency + ' ' + (c / q).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' per ' + many.replace(/s$/, '')
            : 'The bill amount for this product.';
    }
    [product, qty, cost].forEach(function (el) { el.addEventListener('input', sync); el.addEventListener('change', sync); });
    sync();
    if (!product.value) product.focus(); else qty.focus();
});
</script>
@endpush
