@extends('layouts.app')

@section('content')

<div class="container py-5">

<h2 class="mb-4">Services</h2>

@foreach($categories as $category)

<h4 class="mt-4">{{ $category->name }}</h4>

<div class="row g-3">

@foreach($category->services as $service)
<div class="col-12 col-md-6 col-lg-4">

<div class="card p-4 h-100 text-center">
<h5>{{ $service->name }}</h5>
<p>{{ $service->duration_minutes }} mins</p>
<strong>{{ $service->price }} {{ setting('currency') }}</strong>

<a href="/booking" class="btn btn-primary mt-3 spa-link">
Book
</a>
</div>

</div>
@endforeach

</div>

@endforeach

</div>

@endsection