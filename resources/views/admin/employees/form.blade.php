@extends('layouts.admin')
@php $editing = $employee->exists; @endphp
@section('title', $editing ? 'Edit staff' : 'Add staff')
@section('crumb', 'Staff')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $editing ? $employee->name : 'Add staff member' }}</h1>
        <p>Profile details are shown to customers when they choose a stylist.</p>
    </div>
</div>

<div style="max-width:820px;" class="stack-lg">
    <form method="POST" action="{{ $editing ? route('admin.employees.update', $employee) : route('admin.employees.store') }}" class="card card-pad" novalidate>
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="form-grid">
            <div class="field">
                <label class="label" for="name">Full name <span class="req">*</span></label>
                <input class="input" id="name" name="name" value="{{ old('name', $employee->name) }}" required maxlength="120" @error('name') aria-invalid="true" @enderror>
                @error('name')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label class="label" for="title">Role / title</label>
                <input class="input" id="title" name="title" value="{{ old('title', $employee->title) }}" maxlength="80" placeholder="e.g. Senior stylist">
                @error('title')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label class="label" for="phone">Phone</label>
                <input class="input" id="phone" name="phone" type="tel" value="{{ old('phone', $employee->phone) }}" maxlength="20" @error('phone') aria-invalid="true" @enderror>
                @error('phone')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label class="label" for="email">Email</label>
                <input class="input" id="email" name="email" type="email" value="{{ old('email', $employee->email) }}" maxlength="120" @error('email') aria-invalid="true" @enderror>
                @error('email')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field full">
                <label class="label" for="bio">Short bio</label>
                <textarea class="textarea" id="bio" name="bio" maxlength="500" rows="3" placeholder="Specialities and experience">{{ old('bio', $employee->bio) }}</textarea>
            </div>

            <fieldset class="fieldset full">
                <legend>Working hours</legend>
                <p class="hint mb-4">Leave blank to follow the salon's opening hours.</p>
                <div class="form-grid">
                    <div class="field">
                        <label class="label" for="work_start">Starts</label>
                        <input class="input" type="time" id="work_start" name="work_start" value="{{ old('work_start', $employee->timeInput($employee->work_start)) }}" @error('work_start') aria-invalid="true" @enderror>
                        @error('work_start')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label class="label" for="work_end">Ends</label>
                        <input class="input" type="time" id="work_end" name="work_end" value="{{ old('work_end', $employee->timeInput($employee->work_end)) }}" @error('work_end') aria-invalid="true" @enderror>
                        @error('work_end')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="fieldset full">
                <legend>Personal lunch break</legend>
                <p class="hint mb-4">Optional. No bookings are placed with this stylist during their break. The salon-wide break on Opening hours applies too.</p>
                <div class="form-grid">
                    <div class="field">
                        <label class="label" for="break_start">Break starts</label>
                        <input class="input" type="time" id="break_start" name="break_start" value="{{ old('break_start', $employee->timeInput($employee->break_start)) }}" @error('break_start') aria-invalid="true" @enderror>
                        @error('break_start')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label class="label" for="break_end">Break ends</label>
                        <input class="input" type="time" id="break_end" name="break_end" value="{{ old('break_end', $employee->timeInput($employee->break_end)) }}" @error('break_end') aria-invalid="true" @enderror>
                        @error('break_end')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                </div>
            </fieldset>

            <div class="field full">
                <label class="switch">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $employee->is_active ?? true))>
                    <span class="track" aria-hidden="true"></span>
                    Active — can be booked and sign in to the portal
                </label>
            </div>

            <fieldset class="fieldset full">
                <legend>Portal permissions</legend>
                <p class="hint mb-4">What this stylist can do in their portal. Everyone can see their own day and start and finish their jobs.</p>
                <input type="hidden" name="portal_form" value="1">
                @php
                    $portalKeys = array_keys(\App\Support\Permissions::PORTAL);
                    $portalHeld = old('portal_form') ? (array) old('portal_permissions', []) : ($employee->portal_permissions ?? $portalKeys);
                @endphp
                @foreach(\App\Support\Permissions::PORTAL as $permission => [$label, $description])
                    <label class="perm-item">
                        <input type="checkbox" name="portal_permissions[]" value="{{ $permission }}" @checked(in_array($permission, $portalHeld, true))>
                        <span>
                            <span class="perm-label">{{ $label }}</span>
                            <span class="perm-desc">{{ $description }}</span>
                        </span>
                    </label>
                @endforeach
            </fieldset>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.employees.index') }}" class="btn">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $editing ? 'Save changes' : 'Add staff member' }}</button>
        </div>
    </form>

    @if($editing)
        <div class="card card-pad">
            <h2 class="card-title">Remove staff member</h2>
            <p class="text-sm text-muted mt-1 mb-4">Staff with booking history are deactivated instead of deleted, so past records stay intact.</p>
            <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}"
                  data-confirm="Remove {{ $employee->name }}?" data-confirm-message="They will no longer be bookable or able to sign in." data-confirm-button="Remove">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger-soft"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Remove</button>
            </form>
        </div>
    @endif
</div>
@endsection
