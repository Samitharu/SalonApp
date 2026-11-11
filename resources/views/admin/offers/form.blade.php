@extends('layouts.admin')
@php
    $editing = $offer->exists;
    $appliesTo = old('applies_to', $offer->applies_to ?? 'all');
    $chosen = array_map('intval', (array) old('services', $editing ? $offer->services->pluck('id')->all() : []));
@endphp
@section('title', $editing ? 'Edit offer' : 'Add offer')
@section('crumb', 'Offers')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $editing ? $offer->name : 'Add offer' }}</h1>
        <p>Customers get the discount when their visit falls on these dates. If two offers cover the same service, they get the bigger one.</p>
    </div>
</div>

<form method="POST" action="{{ $editing ? route('admin.offers.update', $offer) : route('admin.offers.store') }}" class="stack-lg" style="max-width:900px;" novalidate>
    @csrf
    @if($editing) @method('PUT') @endif

    <section class="card card-pad">
        <h2 class="card-title mb-4">The offer</h2>
        <div class="form-grid">
            <div class="field full">
                <label class="label" for="name">Name <span class="req">*</span></label>
                <input class="input" id="name" name="name" value="{{ old('name', $offer->name) }}" required maxlength="120" placeholder="e.g. Festive glow offer" @error('name') aria-invalid="true" @enderror>
                @error('name')<div class="error-text">{{ $message }}</div>@else<div class="hint">Customers see this on the website, their booking and their receipt.</div>@enderror
            </div>
            <div class="field">
                <label class="label" for="discount_type">Discount type <span class="req">*</span></label>
                <select class="select" id="discount_type" name="discount_type">
                    <option value="percent" @selected(old('discount_type', $offer->discount_type ?? 'percent') === 'percent')>Percentage off</option>
                    <option value="fixed" @selected(old('discount_type', $offer->discount_type) === 'fixed')>Amount off ({{ setting('currency', 'LKR') }})</option>
                </select>
            </div>
            <div class="field">
                <label class="label" for="discount_value">Discount <span class="req">*</span></label>
                <input class="input tabular" type="number" step="0.01" min="0" id="discount_value" name="discount_value" value="{{ old('discount_value', $offer->discount_value !== null ? rtrim(rtrim($offer->discount_value, '0'), '.') : '') }}" required inputmode="decimal" @error('discount_value') aria-invalid="true" @enderror>
                @error('discount_value')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label class="label" for="starts_on">First day <span class="req">*</span></label>
                <input class="input" type="date" id="starts_on" name="starts_on" value="{{ old('starts_on', $offer->starts_on?->toDateString() ?? now()->toDateString()) }}" required @error('starts_on') aria-invalid="true" @enderror>
                @error('starts_on')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label class="label" for="ends_on">Last day <span class="req">*</span></label>
                <input class="input" type="date" id="ends_on" name="ends_on" value="{{ old('ends_on', $offer->ends_on?->toDateString()) }}" required @error('ends_on') aria-invalid="true" @enderror>
                @error('ends_on')<div class="error-text">{{ $message }}</div>@else<div class="hint">Visits on the first and last day are included.</div>@enderror
            </div>
            <div class="field full">
                <label class="label" for="description">Short description <span class="optional">(optional)</span></label>
                <textarea class="textarea" id="description" name="description" rows="2" maxlength="500" placeholder="Shown on the website, e.g. Treat yourself this season">{{ old('description', $offer->description) }}</textarea>
                @error('description')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field full">
                <label class="switch">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing ? $offer->is_active : !old('_token')))>
                    <span class="track" aria-hidden="true"></span>
                    Active — turn off to pause the offer without changing its dates
                </label>
            </div>
        </div>
    </section>

    <section class="card card-pad">
        <h2 class="card-title mb-4">What it applies to</h2>
        <div class="field">
            <label class="label" for="applies_to">Services</label>
            <select class="select" id="applies_to" name="applies_to" style="max-width:320px;">
                <option value="all" @selected($appliesTo === 'all')>All services</option>
                <option value="selected" @selected($appliesTo === 'selected')>Only the services I choose</option>
            </select>
            @error('services')<div class="error-text">{{ $message }}</div>@enderror
            <div class="hint">Add-ons and extras are never discounted.</div>
        </div>

        <div id="servicePicker" @if($appliesTo !== 'selected') hidden @endif>
            <div class="perm-groups">
                @foreach($groups as $group => $services)
                    <fieldset class="perm-group" data-offer-group>
                        <legend>{{ $group }}</legend>
                        <button type="button" class="link-btn text-sm" data-select-group>Select all</button>
                        @foreach($services as $s)
                            <label class="perm-item">
                                <input type="checkbox" name="services[]" value="{{ $s->id }}" @checked(in_array((int) $s->id, $chosen, true))>
                                <span>
                                    <span class="perm-label">{{ $s->name }}</span>
                                    <span class="perm-desc">{{ money($s->price) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>
                @endforeach
            </div>
        </div>
    </section>

    @if($editing)
        <p class="hint">Changes only affect new bookings. Bookings already made keep their price.</p>
    @endif

    <div class="row row-end">
        <a href="{{ route('admin.offers.index') }}" class="btn">Cancel</a>
        <button type="submit" class="btn btn-primary">{{ $editing ? 'Save changes' : 'Add offer' }}</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var applies = document.getElementById('applies_to');
    var picker = document.getElementById('servicePicker');
    applies.addEventListener('change', function () { picker.hidden = applies.value !== 'selected'; });

    document.querySelectorAll('[data-select-group]').forEach(function (btn) {
        var boxes = btn.closest('[data-offer-group]').querySelectorAll('input[type=checkbox]');
        btn.addEventListener('click', function () {
            var all = Array.prototype.every.call(boxes, function (b) { return b.checked; });
            boxes.forEach(function (b) { b.checked = !all; });
        });
    });
});
</script>
@endpush
