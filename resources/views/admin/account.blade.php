@extends('layouts.admin')
@section('title', 'My account')

@section('content')
<div class="page-head">
    <div><h1>My account</h1><p>Update your sign-in details.</p></div>
</div>

<form method="POST" action="{{ route('admin.account.update') }}" class="card card-pad" style="max-width:640px;" novalidate>
    @csrf @method('PUT')
    <div class="stack">
        <div class="field">
            <label class="label" for="name">Name</label>
            <input class="input" id="name" name="name" value="{{ old('name', $admin->name) }}" required maxlength="120" autocomplete="name" @error('name') aria-invalid="true" @enderror>
            @error('name')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label class="label" for="email">Email</label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email', $admin->email) }}" required maxlength="120" autocomplete="username" @error('email') aria-invalid="true" @enderror>
            @error('email')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <hr>
        <div class="form-grid">
            <div class="field">
                <label class="label" for="password">New password <span class="optional">(optional)</span></label>
                <input class="input" id="password" name="password" type="password" autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
                @error('password')<div class="error-text">{{ $message }}</div>@else<div class="hint">At least 8 characters with letters and numbers.</div>@enderror
            </div>
            <div class="field">
                <label class="label" for="password_confirmation">Confirm new password</label>
                <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
            </div>
        </div>
        <hr>
        <div class="field">
            <label class="label" for="current_password">Current password <span class="req">*</span></label>
            <input class="input" id="current_password" name="current_password" type="password" required autocomplete="current-password" @error('current_password') aria-invalid="true" @enderror>
            @error('current_password')<div class="error-text">{{ $message }}</div>@else<div class="hint">Required to save any change.</div>@enderror
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save changes</button>
    </div>
</form>
@endsection
