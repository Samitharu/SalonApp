@extends('layouts.app')

@section('content')

<div class="container py-5">

<h2 class="mb-4">Create Service</h2>

<div class="card p-4">

<form method="POST" action="{{ route('admin.services.store') }}">
@csrf

<div class="mb-3">
<label class="form-label">Name</label>
<input name="name"
       class="form-control"
       required>
</div>

<div class="mb-3">
<label class="form-label">Price</label>
<input name="price"
       class="form-control"
       required>
</div>

<div class="mb-3">
<label class="form-label">Duration (minutes)</label>
<input name="duration_minutes"
       class="form-control"
       required>
</div>

<div class="mb-3">
<label class="form-label">Description</label>
<textarea name="description"
          class="form-control"></textarea>
</div>

<button class="btn btn-success">
Create Service
</button>

<a href="{{ route('admin.services.index') }}"
   class="btn btn-secondary ms-2">
Cancel
</a>

</form>

</div>
</div>

@endsection