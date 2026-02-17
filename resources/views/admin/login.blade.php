@extends('layouts.app')

@section('content')

<div class="container py-5">
<div class="row justify-content-center">
<div class="col-md-4">

<div class="card p-4">

<h3 class="text-center mb-3">Admin Login</h3>

@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form method="POST">
@csrf

<input name="email" class="form-control mb-3" placeholder="Email">
<input type="password" name="password" class="form-control mb-3" placeholder="Password">

<button class="btn btn-primary w-100">
Login
</button>

</form>

</div>
</div>
</div>
</div>

@endsection