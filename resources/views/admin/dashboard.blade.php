@extends('layouts.app')

@section('content')

<div class="container py-5">

<h2 class="mb-4">Admin Dashboard</h2>

<div class="row g-4">

<div class="col-md-4">
<div class="card p-4 text-center">
<h5>Welcome Admin</h5>
<p>Salon management panel</p>
</div>
</div>

<div class="col-md-4">
<div class="card p-4 text-center">
<h5>Appointments</h5>
<a href="{{ route('admin.appointments.index') }}" class="btn btn-primary">
Manage
</a>
</div>
</div>

<div class="col-md-4">
<div class="card p-4 text-center">
<h5>Services</h5>
<a href="{{ route('admin.services.index') }}" class="btn btn-primary">
Manage
</a>
</div>
</div>

</div>

</div>

@endsection