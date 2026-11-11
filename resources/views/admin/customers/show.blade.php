@extends('layouts.admin')
@section('title', $customer->name)
@section('crumb', 'Customers')

@section('content')
<div class="page-head">
    <div class="person">
        <span class="avatar avatar-lg" aria-hidden="true">{{ initials($customer->name) }}</span>
        <div>
            <h1>{{ $customer->name }}</h1>
            <p>
                <a href="tel:{{ $customer->phone }}">{{ $customer->phone }}</a>
                @if($customer->email) · <a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>@endif
                · Customer since {{ $customer->created_at->format('M Y') }}
            </p>
        </div>
    </div>
    <div class="row row-wrap">
        @perm('customers.manage')<a href="{{ route('admin.customers.edit', $customer) }}" class="btn"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>@endperm
        @perm('appointments.manage')<a href="{{ route('admin.appointments.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> New booking</a>@endperm
    </div>
</div>

<div class="stats">
    <div class="stat"><div class="stat-label">Completed visits</div><div class="stat-value">{{ $totals['visits'] }}</div></div>
    <div class="stat"><div class="stat-label">Total spent</div><div class="stat-value"><small>{{ setting('currency', 'LKR') }}</small>{{ number_format($totals['spent']) }}</div></div>
    <div class="stat"><div class="stat-label">Cancelled</div><div class="stat-value">{{ $totals['cancelled'] }}</div></div>
    <div class="stat"><div class="stat-label">No-shows</div><div class="stat-value">{{ $totals['no_shows'] }}</div></div>
</div>

@if($customer->notes)
    <div class="alert alert-info mb-6"><i class="fa-solid fa-note-sticky" aria-hidden="true"></i><div><strong>Notes:</strong> {{ $customer->notes }}</div></div>
@endif

<div class="card">
    <div class="card-header"><h2 class="card-title">Booking history</h2></div>
    @if($appointments->isEmpty())
        <div class="empty"><div class="empty-icon"><i class="fa-regular fa-calendar" aria-hidden="true"></i></div><h3>No bookings</h3></div>
    @else
        <div class="table-wrap">
            <table class="table table-stack">
                <thead><tr><th>Date</th><th>Service</th><th>Stylist</th><th>Status</th><th class="num">Total</th><th class="actions"><span class="sr-only">Open</span></th></tr></thead>
                <tbody>
                    @foreach($appointments as $a)
                        <tr>
                            <td class="cell-primary">
                                <div class="fw-600">{{ \Carbon\Carbon::parse($a->appointment_date)->format('D j M Y') }}</div>
                                <div class="table-sub">{{ fmt_time($a->slot_start) }} · <span class="mono">{{ $a->appointment_no }}</span></div>
                            </td>
                            <td data-label="Service">{{ $a->service->name ?? '—' }}</td>
                            <td data-label="Stylist">{{ $a->staff->name ?? '—' }}</td>
                            <td data-label="Status">@include('partials.status', ['status' => $a->status])</td>
                            <td data-label="Total" class="num">
                                {{ money($a->total()) }}
                                @if($a->items->isNotEmpty())<div class="table-sub">incl. {{ $a->items->count() }} {{ \Illuminate\Support\Str::plural('extra', $a->items->count()) }}</div>@endif
                            </td>
                            <td class="actions" data-label="">@perm('appointments.view')<a href="{{ route('admin.appointments.show', $a) }}" class="btn btn-ghost btn-sm">Open</a>@endperm</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($appointments->hasPages())<div class="card-footer">{{ $appointments->links() }}</div>@endif
    @endif
</div>
@endsection
