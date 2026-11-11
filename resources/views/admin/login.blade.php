@extends('layouts.auth')
@section('title', 'Admin sign in')
@section('heading', 'Admin sign in')
@section('subheading', setting('salon_name', 'Salon') . ' management')

@section('content')
<form method="POST" action="{{ url('/admin/login') }}" class="stack" novalidate>
    @csrf
    <div class="field">
        <label class="label" for="email">Email</label>
        <input class="input" type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" maxlength="120">
    </div>
    <div class="field">
        <label class="label" for="password">Password</label>
        <div class="input-icon">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            <input class="input" type="password" id="password" name="password" required autocomplete="current-password" style="padding-right:48px;">
            <button type="button" class="btn btn-ghost btn-icon btn-sm input-action" data-toggle-password="password" aria-label="Show password"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
        </div>
    </div>
    <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in on this device</label>
    <button type="submit" class="btn btn-primary btn-lg btn-block" data-loading="Signing in…">Sign in</button>
</form>
@endsection
