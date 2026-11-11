@extends('layouts.admin')
@section('title', 'Adjust stock')
@section('crumb', 'Stock')
@php use App\Models\Product; use App\Models\StockMovement; @endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Adjust stock</h1>
        <p>{{ $product->displayName() }} · after a shelf count, or for damaged, expired or unrecorded use.</p>
    </div>
</div>

<form method="POST" action="{{ route('admin.products.adjust.store', $product) }}" class="card card-pad" style="max-width:640px;" novalidate>
    @csrf
    <div class="form-grid">
        <div class="field">
            <span class="label">System stock</span>
            <div class="readonly-value tabular">{{ $product->qty($product->stock) }}</div>
        </div>
        <div class="field">
            <label class="label" for="counted">Actual stock now <span class="req">*</span></label>
            <div class="input-suffix">
                <input class="input tabular" type="number" id="counted" name="counted" min="0" step="0.001" value="{{ old('counted') }}" required inputmode="decimal" autofocus @error('counted') aria-invalid="true" @enderror>
                <span>{{ $product->unitLabel() }}</span>
            </div>
            @error('counted')<div class="error-text">{{ $message }}</div>@else<div class="hint" id="diff">What's actually on the shelf.</div>@enderror
        </div>
        <div class="field">
            <label class="label" for="reason">Reason <span class="req">*</span></label>
            <select class="select" id="reason" name="reason" required @error('reason') aria-invalid="true" @enderror>
                @foreach(StockMovement::REASONS as $key => $label)
                    <option value="{{ $key }}" @selected(old('reason', 'count') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('reason')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label class="label" for="note">Note <span class="optional" id="noteOptional">(optional)</span></label>
            <input class="input" id="note" name="note" value="{{ old('note') }}" maxlength="255" @error('note') aria-invalid="true" @enderror>
            @error('note')<div class="error-text">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="form-actions">
        <a href="{{ route('admin.products.show', $product) }}" class="btn">Cancel</a>
        <button type="submit" class="btn btn-primary">Save stock</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('counted'), diff = document.getElementById('diff');
    var reason = document.getElementById('reason'), opt = document.getElementById('noteOptional');
    var system = {{ (float) $product->stock }}, unit = @json($product->unitLabel());
    function sync() {
        if (diff && input.value !== '') {
            var d = Math.round((parseFloat(input.value) - system) * 1000) / 1000;
            diff.textContent = d === 0 ? 'Same as the system — nothing will change.' : (d > 0 ? '+' : '−') + Math.abs(d) + ' ' + unit + (d > 0 ? ' more than the system shows' : ' less than the system shows');
        }
        opt.textContent = reason.value === 'other' ? '(required)' : '(optional)';
    }
    input.addEventListener('input', sync); reason.addEventListener('change', sync); sync();
});
</script>
@endpush
