@extends('layouts.app')

@section('content')

<div class="container py-5">

<h2 class="mb-4">Manage Employees</h2>

<a href="{{ route('admin.employees.create') }}"
   class="btn btn-primary mb-3">
Add Employee
</a>
@if(session('success'))
<div class="alert alert-success">
{{ session('success') }}
</div>
@endif

<table class="table table-bordered bg-white">
<thead>
<tr>
<th>Name</th>
<th>Phone</th>
<th>Email</th>
<th width="160">Actions</th>
</tr>
</thead>

<tbody>
@foreach($employees as $e)
<tr>

<td>{{ $e->name }}</td>
<td>{{ $e->phone }}</td>
<td>{{ $e->email }}</td>

<td>

<a href="{{ route('admin.employees.edit', $e) }}"
   class="btn btn-warning btn-sm">
Edit
</a>

<form method="POST"
      action="{{ route('admin.employees.destroy', $e) }}"
      class="d-inline">
@csrf
@method('DELETE')

<button class="btn btn-danger btn-sm"
onclick="return confirm('Delete employee?')">
Delete
</button>

</form>

</td>
</tr>
@endforeach
</tbody>

</table>

{{ $employees->links() }}

</div>

@endsection