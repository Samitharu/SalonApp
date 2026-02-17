@extends('layouts.app')

@section('content')

<div class="hero">
<h1>Luxury Salon Experience</h1>
<p>Book beauty services in seconds</p>
<a href="/booking" class="btn btn-primary spa-link mt-3">
Book Appointment
</a>
</div>

<div class="container mt-5">

<h2 class="mb-4">Featured Services</h2>

<div class="row g-4">

@foreach($services as $service)
<div class="col-md-4">
<div class="card p-4 text-center">
<h5>{{ $service->name }}</h5>
<p>{{ $service->duration_minutes }} mins</p>
<strong>{{ $service->price }} {{ setting('currency') }}</strong>
<a href="/booking?service={{ $service->id }}" class="btn btn-primary mt-3 spa-link">Book</a>
</div>
</div>
@endforeach

</div>
</div>

@endsection