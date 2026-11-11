@extends('layouts.admin')
@section('title', 'Feedback')

@php $filtered = array_filter($filters ?? []); @endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Customer feedback</h1>
        <p>Ratings customers leave after a completed visit. Reviews can only be shown on the website when the customer agreed.</p>
    </div>
</div>

<div class="stats feedback-stats">
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-star" aria-hidden="true"></i> Average rating</div>
        <div class="stat-value">{{ $summary['count'] ? number_format($summary['average'], 1) : '—' }}<small style="margin-left:4px;">/ 5</small></div>
        <div class="stat-foot">{{ number_format($summary['count']) }} {{ \Illuminate\Support\Str::plural('review', $summary['count']) }}</div>
    </div>
    <a class="stat" href="{{ route('admin.feedback.index', ['show' => 'publishable']) }}" style="text-decoration:none;">
        <div class="stat-label"><i class="fa-solid fa-globe" aria-hidden="true"></i> Ready to publish</div>
        <div class="stat-value">{{ $summary['waiting'] }}</div>
        <div class="stat-foot">{{ $summary['waiting'] ? 'Customers agreed — review them →' : 'Nothing waiting' }}</div>
    </a>
    <div class="stat stat-wide">
        <div class="stat-label">Rating breakdown</div>
        <div class="rating-bars">
            @for($r = 5; $r >= 1; $r--)
                @php $n = (int) ($summary['dist'][$r] ?? 0); $pct = $summary['count'] ? round($n / $summary['count'] * 100) : 0; @endphp
                <a href="{{ route('admin.feedback.index', ['rating' => $r]) }}" class="rating-bar" aria-label="{{ $r }} stars: {{ $n }}">
                    <span class="tabular">{{ $r }}<i class="fa-solid fa-star" aria-hidden="true"></i></span>
                    <span class="meter"><span style="width:{{ $pct }}%"></span></span>
                    <span class="tabular text-muted">{{ $n }}</span>
                </a>
            @endfor
        </div>
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar">
        <div class="field">
            <label class="label" for="rating">Rating</label>
            <select class="select select-sm" id="rating" name="rating">
                <option value="">All ratings</option>
                @for($r = 5; $r >= 1; $r--)
                    <option value="{{ $r }}" @selected((string) ($filters['rating'] ?? '') === (string) $r)>{{ $r }} {{ \Illuminate\Support\Str::plural('star', $r) }}</option>
                @endfor
            </select>
        </div>
        <div class="field">
            <label class="label" for="staff_id">Stylist</label>
            <select class="select select-sm" id="staff_id" name="staff_id">
                <option value="">All stylists</option>
                @foreach($staffList as $s)
                    <option value="{{ $s->id }}" @selected((string) ($filters['staff_id'] ?? '') === (string) $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label class="label" for="show">Visibility</label>
            <select class="select select-sm" id="show" name="show">
                <option value="">All</option>
                <option value="published" @selected(($filters['show'] ?? '') === 'published')>On website</option>
                <option value="publishable" @selected(($filters['show'] ?? '') === 'publishable')>Can be published</option>
                <option value="private" @selected(($filters['show'] ?? '') === 'private')>Private</option>
            </select>
        </div>
        <div class="row">
            <button class="btn btn-ink btn-sm" type="submit">Filter</button>
            @if($filtered)<a href="{{ route('admin.feedback.index') }}" class="btn btn-ghost btn-sm">Clear</a>@endif
        </div>
    </form>

    @if($feedback->isEmpty())
        <div class="empty">
            <div class="empty-icon"><i class="fa-regular fa-star" aria-hidden="true"></i></div>
            <h3>{{ $filtered ? 'No feedback matches' : 'No feedback yet' }}</h3>
            <p>{{ $filtered ? 'Try clearing the filters.' : 'Customers can rate their visit from “Manage my booking” once it is completed.' }}</p>
        </div>
    @else
        <ul class="feedback-list">
            @foreach($feedback as $f)
                <li class="feedback-item">
                    <div class="feedback-main">
                        <div class="row row-wrap" style="gap:var(--s-3);">
                            <span class="stars" aria-label="{{ $f->rating }} out of 5 stars">
                                @for($i = 1; $i <= 5; $i++)<i class="fa-{{ $i <= $f->rating ? 'solid' : 'regular' }} fa-star" aria-hidden="true"></i>@endfor
                            </span>
                            @if($f->is_public && $f->allow_public)
                                <span class="badge badge-completed no-dot">On website</span>
                            @elseif($f->allow_public)
                                <span class="badge badge-pending no-dot">Can be published</span>
                            @else
                                <span class="badge no-dot">Private</span>
                            @endif
                        </div>
                        @if($f->comment)
                            <p class="feedback-comment">“{{ $f->comment }}”</p>
                        @else
                            <p class="text-sm text-muted">No comment.</p>
                        @endif
                        <div class="feedback-meta">
                            @if($f->customer)@perm('customers.view')<a href="{{ route('admin.customers.show', $f->customer) }}">{{ $f->customer->name }}</a>@else{{ $f->customer->name }}@endperm @else Deleted customer @endif
                            · {{ $f->service->name ?? 'Service' }}
                            @if($f->staff) · with {{ $f->staff->name }}@endif
                            · {{ $f->created_at->format('j M Y') }}
                            @if($f->appointment) · @perm('appointments.view')<a href="{{ route('admin.appointments.show', $f->appointment) }}" class="chip-code">{{ $f->appointment->appointment_no }}</a>@else<span class="chip-code">{{ $f->appointment->appointment_no }}</span>@endperm @endif
                        </div>
                    </div>
                    <div class="feedback-actions">
                        @if($f->allow_public)
                            <form method="POST" action="{{ route('admin.feedback.publish', $f) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $f->is_public ? '' : 'btn-soft' }}">
                                    <i class="fa-solid {{ $f->is_public ? 'fa-eye-slash' : 'fa-globe' }}" aria-hidden="true"></i> {{ $f->is_public ? 'Hide' : 'Publish' }}
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.feedback.destroy', $f) }}" data-confirm="Delete this feedback?" data-confirm-message="This cannot be undone." data-confirm-button="Delete">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Delete feedback"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
        @if($feedback->hasPages())
            <div class="card-footer">{{ $feedback->links() }}</div>
        @endif
    @endif
</div>
@endsection
