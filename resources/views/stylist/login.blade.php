@extends('layouts.auth')
@section('title', 'Stylist sign in')
@section('mark')<i class="fa-solid fa-scissors" style="font-size:18px;"></i>@endsection
@section('heading', 'Stylist portal')
@section('subheading', 'Sign in to see your schedule')

@section('content')
<form method="POST" action="{{ route('stylist.login.post') }}" class="stack" novalidate>
    @csrf
    <div class="field">
        <label class="label" for="username">Username</label>
        <div class="input-icon">
            <i class="fa-solid fa-user" aria-hidden="true"></i>
            <input class="input" id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" autocapitalize="none" maxlength="60">
        </div>
    </div>
    <div class="field">
        <label class="label" for="password">Password</label>
        <div class="input-icon">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            <input class="input" type="password" id="password" name="password" required autocomplete="current-password" style="padding-right:48px;">
            <button type="button" class="btn btn-ghost btn-icon btn-sm input-action" data-toggle-password="password" aria-label="Show password"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
        </div>
    </div>
    <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
    <button type="submit" class="btn btn-primary btn-lg btn-block" data-loading="Signing in…">Sign in</button>
    <p class="hint text-center">Forgot your login? Ask the salon manager to reset it.</p>
</form>
@endsection
