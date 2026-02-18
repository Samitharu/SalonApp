@extends('layouts.app')

@section('content')
<div class="container py-5">

<h2>Create Employee</h2>

<div class="card p-4">

<form method="POST" action="{{ route('admin.employees.store') }}">
@csrf

@include('admin.employees.form')

<button class="btn btn-success">Create</button>
<a href="{{ route('admin.employees.index') }}" class="btn btn-secondary">Cancel</a>

</form>

</div>
</div>
@endsection