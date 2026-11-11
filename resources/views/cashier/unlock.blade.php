@extends('layouts.auth')
@section('title', 'Cashier station')
@section('mark')<i class="fa-solid fa-cash-register" style="font-size:18px;"></i>@endsection
@section('heading', 'Cashier station')
@section('subheading', $hasPin ? 'Enter the station PIN to continue' : 'Station locked')

@section('content')
@if($hasPin)
    <form method="POST" action="{{ route('cashier.unlock.post') }}" class="stack" novalidate>
        @csrf
        <div class="field">
            <label class="label" for="pin">Station PIN</label>
            <input class="input input-mono" style="font-size:22px;text-align:center;letter-spacing:.4em;" type="password" id="pin" name="pin"
                   inputmode="numeric" pattern="[0-9]*" maxlength="8" required autofocus autocomplete="off">
        </div>
        <button type="submit" class="btn btn-primary btn-lg btn-block" data-loading="Unlocking…">Unlock</button>
    </form>
@else
    <div class="stack">
        <p>No station PIN has been set yet. An admin can set one in <strong>Settings → Cashier station</strong>, or open the cashier while signed in as admin.</p>
        <a href="{{ route('admin.login') }}" class="btn btn-primary btn-block">Admin sign in</a>
    </div>
@endif
@endsection
