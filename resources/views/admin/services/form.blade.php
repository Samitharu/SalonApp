@extends('layouts.admin')
@php $editing = $service->exists; @endphp
@section('title', $editing ? 'Edit service' : 'Add service')
@section('crumb', 'Services')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $editing ? $service->name : 'Add service' }}</h1>
        <p>{{ $editing ? 'Update details shown to customers.' : 'New services appear on the booking page once saved as bookable.' }}</p>
    </div>
</div>

<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('admin.services.update', $service) : route('admin.services.store') }}" class="card card-pad" style="max-width:820px;" novalidate>
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="form-grid">
        <div class="field full">
            <label class="label" for="name">Service name <span class="req">*</span></label>
            <input class="input" id="name" name="name" value="{{ old('name', $service->name) }}" required maxlength="120" placeholder="e.g. Haircut & blow-dry" @error('name') aria-invalid="true" @enderror>
            @error('name')<div class="error-text">{{ $message }}</div>@enderror
        </div>

        <div class="field">
            <label class="label" for="service_category_id">Category</label>
            <select class="select" id="service_category_id" name="service_category_id">
                <option value="">No category</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected((string) old('service_category_id', $service->service_category_id) === (string) $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            @if($categories->isEmpty())<div class="hint"><a href="{{ route('admin.categories.create') }}">Create a category</a> to group services.</div>@endif
        </div>

        <div class="field">
            <label class="label" for="sort_order">Display order</label>
            <input class="input" type="number" id="sort_order" name="sort_order" min="0" max="9999" value="{{ old('sort_order', $service->sort_order ?? 0) }}">
            <div class="hint">Lower numbers appear first.</div>
        </div>

        <div class="field">
            <label class="label" for="price">Price ({{ setting('currency', 'LKR') }}) <span class="req">*</span></label>
            <input class="input tabular" type="number" step="0.01" min="0" id="price" name="price" value="{{ old('price', $service->price) }}" required inputmode="decimal" @error('price') aria-invalid="true" @enderror>
            @error('price')<div class="error-text">{{ $message }}</div>@enderror
        </div>

        <div class="field">
            <label class="label" for="duration_minutes">Duration (minutes) <span class="req">*</span></label>
            <input class="input tabular" type="number" min="5" max="720" step="5" id="duration_minutes" name="duration_minutes" value="{{ old('duration_minutes', $service->duration_minutes) }}" required inputmode="numeric" @error('duration_minutes') aria-invalid="true" @enderror>
            @error('duration_minutes')<div class="error-text">{{ $message }}</div>@enderror
            <div class="hint">How long the service (or package) takes.</div>
        </div>

        <div class="field">
            <label class="label" for="buffer_minutes">Cleanup after this service</label>
            @php $buf = old('buffer_minutes', $service->buffer_minutes); @endphp
            <select class="select" id="buffer_minutes" name="buffer_minutes">
                <option value="" @selected($buf === null || $buf === '')>Salon default ({{ (int) setting('buffer_minutes', 0) }} min)</option>
                @foreach([0, 5, 10, 15, 20, 30, 45, 60] as $m)
                    <option value="{{ $m }}" @selected($buf !== null && $buf !== '' && (int) $buf === $m)>{{ $m === 0 ? 'None' : $m . ' minutes' }}</option>
                @endforeach
            </select>
            <div class="hint">Gap kept free before the stylist's next appointment — e.g. longer for colour or packages.</div>
        </div>

        <div class="field">
            <label class="label" for="advance_type">Advance payment</label>
            @php $advType = old('advance_type', $service->advance_type ?? 'none'); @endphp
            <select class="select" id="advance_type" name="advance_type">
                <option value="none" @selected($advType === 'none')>Not required</option>
                <option value="fixed" @selected($advType === 'fixed')>Fixed amount</option>
                <option value="percent" @selected($advType === 'percent')>Percentage of the price</option>
            </select>
            <div class="hint">For special packages such as bridal dressing. Customers see how much to pay and your bank details; record it when it arrives.</div>
        </div>

        <div class="field" id="advanceValueField" @if($advType === 'none') hidden @endif>
            <label class="label" for="advance_value"><span id="advanceValueLabel">{{ $advType === 'percent' ? 'Advance (%)' : 'Advance amount (' . setting('currency', 'LKR') . ')' }}</span></label>
            <input class="input tabular" type="number" min="0" step="0.01" id="advance_value" name="advance_value" value="{{ old('advance_value', (float) ($service->advance_value ?? 0) ?: '') }}" inputmode="decimal" @error('advance_value') aria-invalid="true" @enderror>
            @error('advance_value')<div class="error-text">{{ $message }}</div>@enderror
        </div>

        <div class="field full">
            <label class="label" for="description">Description</label>
            <textarea class="textarea" id="description" name="description" maxlength="1000" rows="3" placeholder="What's included, who it's for">{{ old('description', $service->description) }}</textarea>
            @error('description')<div class="error-text">{{ $message }}</div>@enderror
        </div>

        @php
            $recipe = old('products', $service->exists
                ? $service->products->map(fn ($p) => ['id' => $p->id, 'qty' => \App\Models\Product::number((float) $p->pivot->quantity)])->values()->all()
                : []);
        @endphp
        <fieldset class="field full">
            <legend class="label">Products used per client <span class="optional">(for stock)</span></legend>
            <div class="hint mb-2">Each time this service is completed, stock goes down by these amounts. For a package, list everything it uses.</div>
            <div class="recipe" id="recipe">
                @foreach($recipe as $i => $row)
                    @include('admin.services.recipe-row', ['i' => $i, 'row' => $row])
                @endforeach
            </div>
            <div class="recipe-empty" id="recipeEmpty" @if($recipe) hidden @endif>No products linked — stock won't change when this service is done.</div>
            @error('products')<div class="error-text">{{ $message }}</div>@enderror
            <div class="row mt-2">
                <button type="button" class="btn btn-sm" id="addRecipe" @disabled($products->isEmpty())><i class="fa-solid fa-plus" aria-hidden="true"></i> Add product</button>
                @if($products->isEmpty())
                    <span class="hint">No products yet — @perm('stock.manage')<a href="{{ route('admin.products.create') }}">add them in Stock</a>@else add them in Stock @endperm first.</span>
                @endif
            </div>
            <template id="recipeTpl">@include('admin.services.recipe-row', ['i' => '__i__', 'row' => []])</template>
        </fieldset>

        <div class="field full">
            <span class="label">Photo <span class="optional">(optional, JPG/PNG/WebP up to 2 MB)</span></span>
            <div class="file-drop">
                <img id="imgPreview" src="{{ $service->imageUrl() }}" alt="" @if(!$service->imageUrl()) hidden @endif>
                <div class="stack-sm grow">
                    <label class="sr-only" for="image">Upload photo</label>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" data-preview="imgPreview">
                    @if($service->image)
                        <label class="check text-sm"><input type="checkbox" name="remove_image" value="1"> Remove current photo</label>
                    @endif
                </div>
            </div>
            @error('image')<div class="error-text">{{ $message }}</div>@enderror
        </div>

        <div class="field full">
            <label class="switch">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active ?? true))>
                <span class="track" aria-hidden="true"></span>
                Active
            </label>
            <div class="hint">Inactive services stay in booking history but can't be booked or added as extras.</div>
        </div>

        <div class="field full">
            <label class="switch">
                <input type="checkbox" name="is_addon" value="1" @checked(old('is_addon', $service->is_addon ?? false))>
                <span class="track" aria-hidden="true"></span>
                Add-on only
            </label>
            <div class="hint">For extra charges such as “Long hair”, “Deep conditioning” or “Nail art”. Add-ons can't be booked on their own; stylists, the cashier and admins add them to an appointment's bill. Duration is ignored for add-ons.</div>
        </div>
    </div>

    <div class="form-actions">
        <a href="{{ route('admin.services.index') }}" class="btn">Cancel</a>
        <button type="submit" class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create service' }}</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var type = document.getElementById('advance_type');
    var field = document.getElementById('advanceValueField');
    var label = document.getElementById('advanceValueLabel');
    var input = document.getElementById('advance_value');
    var currency = @json(setting('currency', 'LKR'));
    function sync() {
        field.hidden = type.value === 'none';
        label.textContent = type.value === 'percent' ? 'Advance (%)' : 'Advance amount (' + currency + ')';
        input.max = type.value === 'percent' ? 100 : '';
    }
    type.addEventListener('change', sync);
    sync();

    // Products used per client: add/remove rows, fill the default amount, keep units in step.
    var recipe = document.getElementById('recipe');
    var tpl = document.getElementById('recipeTpl');
    var empty = document.getElementById('recipeEmpty');
    var next = recipe.querySelectorAll('[data-row]').length;

    function describe(row, setDefault) {
        var sel = row.querySelector('[data-recipe-product]');
        var qty = row.querySelector('[data-recipe-qty]');
        var o = sel.options[sel.selectedIndex];
        row.querySelector('[data-recipe-unit]').textContent = o && o.dataset.many ? o.dataset.many : 'units';
        if (setDefault && o && o.dataset.default && !qty.value) qty.value = o.dataset.default;
        var hint = '';
        if (o && o.value && parseFloat(o.dataset.uses) > 1) hint = '1 ' + o.dataset.one + ' serves ' + o.dataset.uses + ' clients, so ' + o.dataset.default + ' per client.';
        row.querySelector('[data-recipe-hint]').textContent = hint;
        row.querySelector('[data-recipe-hint]').hidden = !hint;
    }
    // A product can only be listed once.
    function lockChosen() {
        var chosen = Array.prototype.map.call(recipe.querySelectorAll('[data-recipe-product]'), function (s) { return s.value; });
        recipe.querySelectorAll('[data-recipe-product]').forEach(function (s) {
            Array.prototype.forEach.call(s.options, function (opt) { opt.disabled = opt.value !== '' && opt.value !== s.value && chosen.indexOf(opt.value) !== -1; });
        });
        empty.hidden = recipe.children.length > 0;
    }
    recipe.querySelectorAll('[data-row]').forEach(function (row) { describe(row, false); });
    lockChosen();

    document.getElementById('addRecipe').addEventListener('click', function () {
        recipe.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, String(next++)));
        var row = recipe.lastElementChild;
        describe(row, false);
        lockChosen();
        row.querySelector('[data-recipe-product]').focus();
    });
    recipe.addEventListener('change', function (e) {
        if (e.target.matches('[data-recipe-product]')) { describe(e.target.closest('[data-row]'), true); lockChosen(); }
    });
    recipe.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-recipe-remove]');
        if (!btn) return;
        btn.closest('[data-row]').remove();
        lockChosen();
        document.getElementById('addRecipe').focus();
    });
});
</script>
@endpush
