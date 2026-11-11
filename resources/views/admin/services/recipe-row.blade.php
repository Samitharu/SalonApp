{{-- One "product used per client" row. Pass $i (index or '__i__'), $row (['id','qty']) and $products. --}}
@php
    $sel = (string) ($row['id'] ?? '');
    $chosen = $sel !== '' ? $products->firstWhere('id', (int) $sel) : null;
@endphp
<div class="recipe-row" data-row>
    <div>
        <select class="select" name="products[{{ $i }}][id]" aria-label="Product" data-recipe-product @error("products.$i.id") aria-invalid="true" @enderror>
            <option value="">Choose a product…</option>
            @foreach($products as $p)
                <option value="{{ $p->id }}" data-many="{{ $p->unitLabel() }}" data-default="{{ \App\Models\Product::number($p->defaultPerClient()) }}"
                        data-uses="{{ \App\Models\Product::number($p->uses_per_unit) }}" data-one="{{ $p->unitLabel(1) }}" @selected($sel === (string) $p->id)>
                    {{ $p->displayName() }}{{ $p->is_active ? '' : ' (archived)' }}
                </option>
            @endforeach
        </select>
        @error("products.$i.id")<div class="error-text">{{ $message }}</div>@enderror
    </div>
    <div>
        <div class="input-suffix">
            <input class="input tabular" type="number" name="products[{{ $i }}][qty]" value="{{ $row['qty'] ?? '' }}" min="0.001" step="0.001" placeholder="Per client"
                   aria-label="Amount used per client" inputmode="decimal" data-recipe-qty @error("products.$i.qty") aria-invalid="true" @enderror>
            <span data-recipe-unit>{{ $chosen ? $chosen->unitLabel() : 'units' }}</span>
        </div>
        @error("products.$i.qty")<div class="error-text">{{ $message }}</div>@enderror
    </div>
    <button type="button" class="btn btn-ghost btn-icon recipe-remove" aria-label="Remove product" data-recipe-remove><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
    <div class="hint" data-recipe-hint></div>
</div>
