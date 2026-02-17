@extends('layouts.app')

@section('content')

<div class="container py-5">

<h2 class="mb-4">Edit Service</h2>

<div class="card p-4">

<form method="POST" action="{{ route('admin.services.update', $service) }}">
@csrf
@method('PUT')

<div class="mb-3">
<label class="form-label">Name</label>
<input name="name"
       class="form-control"
       value="{{ $service->name }}"
       required>
</div>

<div class="mb-3">
<label class="form-label">Price</label>
<input name="price"
       class="form-control"
       value="{{ $service->price }}"
       required>
</div>

<div class="mb-3">
<label class="form-label">Duration (minutes)</label>
<input name="duration_minutes"
       class="form-control"
       value="{{ $service->duration_minutes }}"
       required>
</div>

<div class="mb-3">
<label class="form-label">Description</label>
<textarea name="description"
          class="form-control">{{ $service->description }}</textarea>
</div>

<button class="btn btn-primary">
Update Service
</button>

<a href="{{ route('admin.services.index') }}"
   class="btn btn-secondary ms-2">
Cancel
</a>

</form>

</div>
</div>

@endsection