@extends('layouts.app')

@section('content')
<div class="container py-5">

<h2>Edit Employee</h2>

<div class="card p-4">

<form method="POST" action="{{ route('employees.update', $employee) }}">
@csrf
@method('PUT')

@include('admin.employees.form', ['employee' => $employee])

<button class="btn btn-primary">Update</button>
<a href="{{ route('employees.index') }}" class="btn btn-secondary">Cancel</a>

</form>

</div>
</div>
@endsection