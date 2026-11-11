@extends('layouts.admin')
@section('title', 'Edit customer')
@section('crumb', 'Customers')

@section('content')
<div class="page-head">
    <div><h1>Edit customer</h1><p>{{ $customer->name }}</p></div>
</div>

<div style="max-width:760px;" class="stack-lg">
    <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="card card-pad" novalidate>
        @csrf @method('PUT')
        <div class="form-grid">
            <div class="field">
                <label class="label" for="name">Name <span class="req">*</span></label>
                <input class="input" id="name" name="name" value="{{ old('name', $customer->name) }}" required maxlength="120" @error('name') aria-invalid="true" @enderror>
                @error('name')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label class="label" for="phone">Phone <span class="req">*</span></label>
                <input class="input" id="phone" name="phone" type="tel" value="{{ old('phone', $customer->phone) }}" required maxlength="20" @error('phone') aria-invalid="true" @enderror>
                @error('phone')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field full">
                <label class="label" for="email">Email</label>
                <input class="input" id="email" name="email" type="email" value="{{ old('email', $customer->email) }}" maxlength="120" @error('email') aria-invalid="true" @enderror>
                @error('email')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field full">
                <label class="label" for="notes">Notes</label>
                <textarea class="textarea" id="notes" name="notes" maxlength="2000" rows="4" placeholder="Preferences, allergies, anything staff should know">{{ old('notes', $customer->notes) }}</textarea>
                <div class="hint">Visible to admins on every booking for this customer.</div>
            </div>
        </div>
        <div class="form-actions">
            <a href="{{ route('admin.customers.show', $customer) }}" class="btn">Cancel</a>
            <button type="submit" class="btn btn-primary">Save customer</button>
        </div>
    </form>

    <div class="card card-pad">
        <h2 class="card-title">Delete customer</h2>
        <p class="text-sm text-muted mt-1 mb-4">Permanently removes this customer and all of their booking history.</p>
        <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}"
              data-confirm="Delete {{ $customer->name }}?" data-confirm-message="All of their bookings will also be deleted. This cannot be undone." data-confirm-button="Delete customer">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger-soft"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete customer</button>
        </form>
    </div>
</div>
@endsection
