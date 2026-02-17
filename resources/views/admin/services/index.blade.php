@extends('layouts.app')

@section('content')

<div class="container py-5">

<h2 class="mb-4">Manage Services</h2>

<a href="{{ route('admin.services.create') }}" class="btn btn-primary mb-3">
Add Service
</a>

<table class="table table-bordered">

<tr>
<th>Name</th>
<th>Price</th>
<th>Duration</th>
<th>Actions</th>
</tr>

@foreach($services as $service)
<tr>
<td>{{ $service->name }}</td>
<td>{{ $service->price }}</td>
<td>{{ $service->duration_minutes }} mins</td>
<td>
<a href="{{ route('admin.services.edit', $service) }}" class="btn btn-sm btn-warning">
Edit
</a>

<form method="POST"
      action="{{ route('admin.services.destroy', $service) }}"
      style="display:inline;">
@csrf
@method('DELETE')
<button class="btn btn-sm btn-danger">Delete</button>
</form>
</td>
</tr>
@endforeach

</table>

{{ $services->links() }}

</div>

@endsection