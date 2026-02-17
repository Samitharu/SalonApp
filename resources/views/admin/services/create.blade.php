@extends('admin.layout')

@section('content')
<h2>Create Service</h2>

<form method="POST" action="{{ route('admin.services.store') }}">
    @csrf

    <div>
        <label>Name</label>
        <input type="text" name="name" required>
    </div>

    <div>
        <label>Price</label>
        <input type="number" step="0.01" name="price" required>
    </div>

    <button type="submit">Save</button>
</form>

@endsection