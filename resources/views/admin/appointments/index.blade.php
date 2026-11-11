@extends('layouts.admin')
@section('title', 'Appointments')

@php
    $scope = $filters['scope'] ?? (empty($filters['date']) ? 'upcoming' : 'all');
    $qs = fn (array $over) => array_filter(array_merge(request()->only(['q', 'date', 'status', 'staff_id', 'scope']), $over), fn ($v) => $v !== null && $v !== '');
    $hasFilters = !empty($filters['q']) || !empty($filters['date']) || !empty($filters['status']) || !empty($filters['staff_id']);
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Appointments</h1>
        <p>{{ number_format($appointments->total()) }} {{ \Illuminate\Support\Str::plural('appointment', $appointments->total()) }} {{ $scope === 'upcoming' ? 'from today onwards' : ($scope === 'past' ? 'in the past' : '') }}</p>
    </div>
    <div class="row row-wrap">
        @perm('appointments.export')<a href="{{ route('admin.appointments.export', $qs([])) }}" class="btn"><i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i> Export CSV</a>@endperm
        @perm('appointments.manage')<a href="{{ route('admin.appointments.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> New booking</a>@endperm
    </div>
</div>

<div class="card">
    <div class="card-header" style="flex-wrap:wrap;">
        <nav class="segmented" aria-label="Time range">
            <a href="{{ route('admin.appointments.index', $qs(['scope' => 'upcoming', 'page' => null])) }}" class="{{ $scope === 'upcoming' ? 'is-active' : '' }}">Upcoming</a>
            <a href="{{ route('admin.appointments.index', $qs(['scope' => 'past', 'page' => null])) }}" class="{{ $scope === 'past' ? 'is-active' : '' }}">Past</a>
            <a href="{{ route('admin.appointments.index', $qs(['scope' => 'all', 'page' => null])) }}" class="{{ $scope === 'all' ? 'is-active' : '' }}">All</a>
        </nav>
    </div>

    <form method="GET" class="toolbar" role="search">
        <input type="hidden" name="scope" value="{{ $scope }}">
        <div class="field grow">
            <label class="label" for="q">Search</label>
            <div class="input-icon">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input class="input input-sm" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Reference, job #, name or phone">
            </div>
        </div>
        <div class="field">
            <label class="label" for="date">Date</label>
            <input class="input input-sm" type="date" id="date" name="date" value="{{ $filters['date'] ?? '' }}">
        </div>
        <div class="field">
            <label class="label" for="status">Status</label>
            <select class="select select-sm" id="status" name="status">
                <option value="">Any status</option>
                @foreach(\App\Models\Appointment::STATUSES as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ status_label($s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label class="label" for="staff_id">Stylist</label>
            <select class="select select-sm" id="staff_id" name="staff_id">
                <option value="">Any stylist</option>
                @foreach($staffList as $s)
                    <option value="{{ $s->id }}" @selected((string) ($filters['staff_id'] ?? '') === (string) $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="row">
            <button class="btn btn-ink btn-sm" type="submit">Apply</button>
            @if($hasFilters)
                <a href="{{ route('admin.appointments.index', ['scope' => $scope]) }}" class="btn btn-ghost btn-sm">Clear</a>
            @endif
        </div>
    </form>

    @if($appointments->isEmpty())
        <div class="empty">
            <div class="empty-icon"><i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i></div>
            <h3>{{ $hasFilters ? 'No appointments match these filters' : 'No appointments yet' }}</h3>
            <p>{{ $hasFilters ? 'Try widening the date or clearing a filter.' : 'Online bookings and bookings you add will show up here.' }}</p>
        </div>
    @else
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Stylist</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th class="actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($appointments as $a)
                        <tr>
                            <td class="cell-primary">
                                <div class="fw-600 nowrap">{{ \Carbon\Carbon::parse($a->appointment_date)->format('D j M') }}, {{ fmt_time($a->slot_start) }}</div>
                                <div class="table-sub"><a href="{{ route('admin.appointments.show', $a) }}" class="chip-code">{{ $a->appointment_no }}</a>@if($a->walk_in) · Walk-in @elseif($a->type === 'phone') · Phone @endif</div>
                            </td>
                            <td data-label="Customer">
                                <div class="fw-600">{{ $a->customer->name ?? '—' }}</div>
                                <div class="table-sub">{{ $a->customer->phone ?? '' }}</div>
                            </td>
                            <td data-label="Service">{{ $a->service->name ?? '—' }}</td>
                            <td data-label="Stylist">
                                @if($a->staff)
                                    {{ $a->staff->name }}
                                @else
                                    <span class="text-muted">Unassigned</span>
                                @endif
                            </td>
                            <td data-label="Status">@include('partials.status', ['status' => $a->status])</td>
                            <td data-label="Payment">
                                @if($a->payment_status === 'paid')
                                    <span class="badge badge-paid">Paid · {{ $a->paymentMethodLabel() }}</span>
                                @elseif($a->payment_status === 'partial')
                                    <span class="badge badge-confirmed no-dot">Advance · {{ money($a->amount_paid) }}</span>
                                @else
                                    <span class="badge badge-unpaid no-dot">Unpaid</span>
                                @endif
                            </td>
                            <td class="actions" data-label="">
                                <div class="row">
                                    @if($a->status === 'pending' && admin_can('appointments.manage'))
                                        <form method="POST" action="{{ route('admin.appointments.status', $a) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" class="btn btn-soft btn-sm" data-loading="…">Confirm</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('admin.appointments.show', $a) }}" class="btn btn-ghost btn-sm">Open</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($appointments->hasPages())
            <div class="card-footer">{{ $appointments->links() }}</div>
        @endif
    @endif
</div>
@endsection
