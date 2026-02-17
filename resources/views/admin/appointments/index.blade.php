@extends('layouts.app')

@section('content')

<h1>Appointments</h1>

<form method="GET" style="margin-bottom:20px;">
    <input type="date" name="date" value="{{ request('date') }}">

    <select name="status">
        <option value="">All Status</option>
        <option value="pending">Pending</option>
        <option value="confirmed">Confirmed</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
    </select>

    <button>Filter</button>
</form>

<table border="1" cellpadding="10" width="100%">
<tr>
    <th>No</th>
    <th>Customer</th>
    <th>Service</th>
    <th>Date</th>
    <th>Time</th>
    <th>Status</th>
    <th>Actions</th>
</tr>

@foreach($appointments as $a)
<tr>
    <td>{{ $a->appointment_no }}</td>
    <td>{{ $a->customer->name }}</td>
    <td>{{ $a->service->name }}</td>
    <td>{{ $a->appointment_date }}</td>
    <td>{{ $a->slot_start }} - {{ $a->slot_end }}</td>
    <td>{{ $a->status }}</td>
    <td>

        <form method="POST"
              action="{{ route('admin.appointments.status', $a) }}"
              style="display:inline;">
            @csrf
            <select name="status" onchange="this.form.submit()">
                <option disabled selected>Change</option>
                <option value="confirmed">Confirm</option>
                <option value="completed">Complete</option>
                <option value="cancelled">Cancel</option>
            </select>
        </form>

        <form method="POST"
              action="{{ route('admin.appointments.destroy', $a) }}"
              style="display:inline;">
            @csrf
            @method('DELETE')
            <button onclick="return confirm('Delete?')">
                Delete
            </button>
        </form>

    </td>
</tr>
@endforeach

</table>

<br>

{{ $appointments->links() }}

@endsection