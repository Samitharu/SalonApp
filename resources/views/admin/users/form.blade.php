@extends('layouts.admin')
@php
    use App\Support\Permissions;
    $editing = $user->exists;
    $owner = (bool) old('is_owner', $user->is_owner);
    $held = old('permissions', $user->permissions ?? []);
    $mine = $actor->effectivePermissions();
@endphp
@section('title', $editing ? 'Edit access' : 'Add user')
@section('crumb', 'Admin users')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $editing ? $user->name : 'Add user' }}</h1>
        <p>{{ $editing ? 'Changes apply straight away, even if they’re signed in.' : 'They sign in at /admin/login with this email and password.' }}</p>
    </div>
</div>

<form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" class="stack-lg" style="max-width:900px;" novalidate>
    @csrf
    @if($editing) @method('PUT') @endif

    @if($errors->any())
        <div class="alert alert-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>{{ $errors->first() }}</div></div>
    @endif

    <section class="card card-pad">
        <h2 class="card-title mb-4">Sign-in details</h2>
        <div class="form-grid">
            <div class="field">
                <label class="label" for="name">Name <span class="req">*</span></label>
                <input class="input" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" autocomplete="off" @error('name') aria-invalid="true" @enderror>
                @error('name')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label class="label" for="email">Email <span class="req">*</span></label>
                <input class="input" type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="120" autocomplete="off" @error('email') aria-invalid="true" @enderror>
                @error('email')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label class="label" for="password">{{ $editing ? 'New password' : 'Password' }} @unless($editing)<span class="req">*</span>@else<span class="optional">(leave blank to keep)</span>@endunless</label>
                <input class="input" type="password" id="password" name="password" autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
                @error('password')<div class="error-text">{{ $message }}</div>@else<div class="hint">At least 8 characters, with letters and numbers.@if($editing) A new password signs them out everywhere.@endif</div>@enderror
            </div>
            <div class="field">
                <label class="label" for="password_confirmation">Confirm password</label>
                <input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
            </div>
            @if($editing)
                <div class="field full">
                    <label class="switch">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                        <span class="track" aria-hidden="true"></span>
                        Active — can sign in
                    </label>
                    <div class="hint">Turn this off when someone leaves. They’re signed out immediately and their history stays in the log.</div>
                </div>
            @else
                <input type="hidden" name="is_active" value="1">
            @endif
        </div>
    </section>

    <section class="card card-pad">
        <h2 class="card-title">What they can do</h2>

        @if($actor->is_owner)
            <div class="mt-4">
                <label class="switch">
                    <input type="checkbox" name="is_owner" value="1" id="isOwner" @checked($owner)>
                    <span class="track" aria-hidden="true"></span>
                    Owner — full access to everything
                </label>
                <div class="hint">Owners can do everything, including managing other owners. Keep this to one or two people.</div>
            </div>
        @endif

        <div id="permissionPanel" @if($owner) hidden @endif>
            <div class="role-presets" role="group" aria-label="Start from a role">
                <span class="label">Start from:</span>
                @foreach(Permissions::ROLES as $key => [$label, $preset])
                    <button type="button" class="chip" data-role='@json(Permissions::normalise($preset))'>{{ $label }}</button>
                @endforeach
                <button type="button" class="chip" data-role="[]">Clear all</button>
            </div>

            <div class="perm-groups">
                @foreach(Permissions::GROUPS as $group => $items)
                    <fieldset class="perm-group">
                        <legend>{{ $group }}</legend>
                        @foreach($items as $permission => [$label, $description])
                            @php $canGrant = in_array($permission, $mine, true); @endphp
                            <label class="perm-item {{ $canGrant ? '' : 'is-locked' }}" @unless($canGrant) title="You don’t have this permission, so you can’t change it" @endunless>
                                <input type="checkbox" name="permissions[]" value="{{ $permission }}" data-perm="{{ $permission }}"
                                       data-implies='@json(Permissions::IMPLIES[$permission] ?? [])' @checked(in_array($permission, $held, true)) @disabled(!$canGrant)>
                                <span>
                                    <span class="perm-label">{{ $label }}</span>
                                    <span class="perm-desc">{{ $description }}</span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>
                @endforeach
            </div>
            <p class="hint mt-4">Every user can see the dashboard and edit their own account. The dashboard only shows what they’re allowed to see.</p>
        </div>
    </section>

    <div class="row row-end">
        <a href="{{ route('admin.users.index') }}" class="btn">Cancel</a>
        <button type="submit" class="btn btn-primary">{{ $editing ? 'Save changes' : 'Add user' }}</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-perm]'));
    var owner = document.getElementById('isOwner');
    var panel = document.getElementById('permissionPanel');

    function byName(name) { return boxes.filter(function (b) { return b.dataset.perm === name; })[0]; }

    // Ticking "manage" also ticks "see"; unticking "see" unticks what depends on it.
    function applyImplied(box) {
        if (box.checked) {
            JSON.parse(box.dataset.implies).forEach(function (n) { var b = byName(n); if (b && !b.disabled) b.checked = true; });
        } else {
            boxes.forEach(function (b) {
                if (!b.disabled && JSON.parse(b.dataset.implies).indexOf(box.dataset.perm) !== -1) b.checked = false;
            });
        }
    }
    boxes.forEach(function (b) { b.addEventListener('change', function () { applyImplied(b); }); });

    document.querySelectorAll('[data-role]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wanted = JSON.parse(btn.dataset.role);
            boxes.forEach(function (b) { if (!b.disabled) b.checked = wanted.indexOf(b.dataset.perm) !== -1; });
        });
    });

    if (owner) owner.addEventListener('change', function () { panel.hidden = owner.checked; });
});
</script>
@endpush
