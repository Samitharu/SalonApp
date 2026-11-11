@extends('layouts.admin')
@php
    use App\Models\Product;
    $editing = $product->exists;
    $unit = old('unit', $product->unit);
@endphp
@section('title', $editing ? 'Edit product' : 'Add product')
@section('crumb', 'Stock')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $editing ? $product->name : 'Add product' }}</h1>
        <p>{{ $editing ? 'Stock is changed with Restock or Adjust, not here.' : 'Something you use on clients — a cream, facial pack, colour, wax…' }}</p>
    </div>
</div>

<form method="POST" action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}" class="card card-pad" style="max-width:820px;" novalidate>
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="form-grid">
        <div class="field">
            <label class="label" for="name">Product name <span class="req">*</span></label>
            <input class="input" id="name" name="name" value="{{ old('name', $product->name) }}" required maxlength="120" placeholder="e.g. Gold facial pack" @error('name') aria-invalid="true" @enderror>
            @error('name')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label class="label" for="brand">Brand <span class="optional">(optional)</span></label>
            <input class="input" id="brand" name="brand" value="{{ old('brand', $product->brand) }}" maxlength="120" placeholder="e.g. Nature's Secrets" @error('brand') aria-invalid="true" @enderror>
            @error('brand')<div class="error-text">{{ $message }}</div>@enderror
        </div>

        <div class="field">
            <label class="label" for="unit">Measured in <span class="req">*</span></label>
            <select class="select" id="unit" name="unit" required>
                @foreach(Product::UNITS as $key => [$one, $many, $countable])
                    <option value="{{ $key }}" data-one="{{ $one }}" data-many="{{ $many }}" data-countable="{{ $countable ? 1 : 0 }}" @selected($unit === $key)>{{ ucfirst($many) }}</option>
                @endforeach
            </select>
            <div class="hint">Stock, restocks and usage are all counted in this unit.</div>
            @error('unit')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label class="label" for="uses_per_unit">Clients per <span data-unit-one>{{ Product::UNITS[$unit][0] ?? $unit }}</span> <span class="req">*</span></label>
            <input class="input tabular" type="number" id="uses_per_unit" name="uses_per_unit" min="1" max="1000" step="0.5" value="{{ old('uses_per_unit', Product::number((float) $product->uses_per_unit)) }}" required inputmode="decimal" @error('uses_per_unit') aria-invalid="true" @enderror>
            <div class="hint" id="usesHint">How many clients one <span data-unit-one>{{ Product::UNITS[$unit][0] ?? $unit }}</span> serves — e.g. one facial pack for 2 people. Use 1 for ml or g; set the exact amount on each service.</div>
            @error('uses_per_unit')<div class="error-text">{{ $message }}</div>@enderror
        </div>

        @unless($editing)
            <div class="field">
                <label class="label" for="opening_stock">Opening stock</label>
                <div class="input-suffix">
                    <input class="input tabular" type="number" id="opening_stock" name="opening_stock" min="0" step="0.001" value="{{ old('opening_stock') }}" placeholder="0" inputmode="decimal" @error('opening_stock') aria-invalid="true" @enderror>
                    <span data-unit-many>{{ Product::UNITS[$unit][1] ?? $unit }}</span>
                </div>
                <div class="hint" id="clientsPreview"></div>
                @error('opening_stock')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label class="label" for="opening_cost">Cost of opening stock ({{ setting('currency', 'LKR') }}) <span class="optional">(optional)</span></label>
                <input class="input tabular" type="number" id="opening_cost" name="opening_cost" min="0" step="0.01" value="{{ old('opening_cost') }}" inputmode="decimal" @error('opening_cost') aria-invalid="true" @enderror>
                <div class="hint">What you paid in total. Used to show stock value.</div>
                @error('opening_cost')<div class="error-text">{{ $message }}</div>@enderror
            </div>
        @else
            <div class="field">
                <span class="label">In stock now</span>
                <div class="readonly-value">{{ $product->qty($product->stock) }}</div>
                <div class="hint"><a href="{{ route('admin.products.restock', ['product' => $product->id]) }}">Restock</a> · <a href="{{ route('admin.products.adjust', $product) }}">Adjust after a stock count</a></div>
            </div>
        @endunless

        <div class="field">
            <label class="label" for="low_stock_level">Low-stock alert at</label>
            <div class="input-suffix">
                <input class="input tabular" type="number" id="low_stock_level" name="low_stock_level" min="0" step="0.001" value="{{ old('low_stock_level', $product->low_stock_level !== null ? Product::number($product->low_stock_level) : '') }}" inputmode="decimal" @error('low_stock_level') aria-invalid="true" @enderror>
                <span data-unit-many>{{ Product::UNITS[$unit][1] ?? $unit }}</span>
            </div>
            <div class="hint">You’ll be alerted when stock falls to this level. Leave empty to be alerted only when it runs out.</div>
            @error('low_stock_level')<div class="error-text">{{ $message }}</div>@enderror
        </div>

        <div class="field full">
            <label class="label" for="notes">Notes <span class="optional">(optional)</span></label>
            <textarea class="textarea" id="notes" name="notes" maxlength="1000" rows="2" placeholder="Where it's kept, usual supplier, shelf life…">{{ old('notes', $product->notes) }}</textarea>
        </div>

        @if($editing)
            <div class="field full">
                <input type="hidden" name="is_active" value="0">
                <label class="switch">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))>
                    <span class="track" aria-hidden="true"></span>
                    Active
                </label>
                <div class="hint">Archived products are hidden from lists and can't be added to services. Their history is kept.</div>
            </div>
        @endif
    </div>

    <div class="form-actions">
        @if($editing)
            <button type="submit" form="deleteProduct" class="btn btn-danger-soft" style="margin-right:auto;">{{ $product->movements()->where('type', '!=', 'opening')->exists() ? 'Archive' : 'Delete' }}</button>
        @endif
        <a href="{{ route('admin.products.index') }}" class="btn">Cancel</a>
        <button type="submit" class="btn btn-primary">{{ $editing ? 'Save changes' : 'Add product' }}</button>
    </div>
</form>

@if($editing)
    <form method="POST" action="{{ route('admin.products.destroy', $product) }}" id="deleteProduct"
          data-confirm="Remove {{ $product->name }}?" data-confirm-message="Products with stock history are archived so past usage still adds up." data-confirm-button="Remove">
        @csrf @method('DELETE')
    </form>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var unit = document.getElementById('unit');
    var uses = document.getElementById('uses_per_unit');
    var opening = document.getElementById('opening_stock');
    var preview = document.getElementById('clientsPreview');

    function sync() {
        var o = unit.options[unit.selectedIndex];
        document.querySelectorAll('[data-unit-one]').forEach(function (el) { el.textContent = o.dataset.one; });
        document.querySelectorAll('[data-unit-many]').forEach(function (el) { el.textContent = o.dataset.many; });
        if (preview) {
            var n = parseFloat(opening.value), u = parseFloat(uses.value) || 1;
            preview.textContent = n > 0 && u > 1 ? '≈ ' + Math.floor(n * u + 0.0001) + ' clients' : '';
        }
    }
    [unit, uses, opening].forEach(function (el) { if (el) el.addEventListener('input', sync); });
    unit.addEventListener('change', sync);
    sync();
});
</script>
@endpush
