@extends('layouts.admin')
@section('title', 'Customers')

@section('content')
<div class="page-head">
    <div>
        <h1>Customers</h1>
        <p>{{ number_format($customers->total()) }} {{ \Illuminate\Support\Str::plural('customer', $customers->total()) }}. Customers are created automatically when they book.</p>
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar" role="search">
        <div class="field grow">
            <label class="label" for="search">Search</label>
            <div class="input-icon">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input class="input input-sm" id="search" name="search" value="{{ request('search') }}" placeholder="Name, phone or email">
            </div>
        </div>
        <div class="row">
            <button class="btn btn-ink btn-sm" type="submit">Search</button>
            @if(request('search'))<a href="{{ route('admin.customers.index') }}" class="btn btn-ghost btn-sm">Clear</a>@endif
        </div>
    </form>

    @if($customers->isEmpty())
        <div class="empty">
            <div class="empty-icon"><i class="fa-solid fa-user-group" aria-hidden="true"></i></div>
            <h3>{{ request('search') ? 'No customers found' : 'No customers yet' }}</h3>
            <p>{{ request('search') ? 'Check the spelling or search by phone number.' : 'Customers appear here after their first booking.' }}</p>
        </div>
    @else
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th class="num">Bookings</th>
                        <th>Last visit</th>
                        <th>Customer since</th>
                        <th class="actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $c)
                        <tr>
                            <td class="cell-primary">
                                <a href="{{ route('admin.customers.show', $c) }}" class="person row-link" style="text-decoration:none;">
                                    <span class="avatar" aria-hidden="true">{{ initials($c->name) }}</span>
                                    <span>
                                        <span class="person-name" style="display:block;">{{ $c->name }}</span>
                                        @if($c->email)<span class="person-meta">{{ $c->email }}</span>@endif
                                    </span>
                                </a>
                            </td>
                            <td data-label="Phone" class="tabular">{{ $c->phone ?: '—' }}</td>
                            <td data-label="Bookings" class="num">{{ $c->appointments_count }}</td>
                            <td data-label="Last visit">{{ $c->appointments_max_appointment_date ? \Carbon\Carbon::parse($c->appointments_max_appointment_date)->format('j M Y') : '—' }}</td>
                            <td data-label="Since">{{ $c->created_at->format('j M Y') }}</td>
                            <td class="actions" data-label=""><a href="{{ route('admin.customers.show', $c) }}" class="btn btn-ghost btn-sm">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($customers->hasPages())
            <div class="card-footer">{{ $customers->links() }}</div>
        @endif
    @endif
</div>
@endsection
