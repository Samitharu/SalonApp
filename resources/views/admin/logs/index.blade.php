@extends('layouts.admin')
@section('title', 'System log')

@php
    $filtered = array_filter(\Illuminate\Support\Arr::except($filters, ['subject', 'id']));
    $levelBadge = ['info' => 'badge-neutral', 'warning' => 'badge-pending', 'error' => 'badge-cancelled', 'critical' => 'badge-cancelled'];
    $fmt = function ($v) {
        if (is_bool($v)) return $v ? 'yes' : 'no';
        if ($v === null || $v === '') return '—';
        return is_array($v) ? json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $v;
    };
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>System log</h1>
        <p>Every sign-in, booking, payment and admin change, plus application errors. Entries are kept for {{ $summary['retention'] }} days and can’t be edited.</p>
    </div>
    <a href="{{ route('admin.logs.export', request()->query()) }}" class="btn"><i class="fa-solid fa-download" aria-hidden="true"></i> Export CSV</a>
</div>

<div class="stats">
    <div class="stat">
        <div class="stat-label"><i class="fa-regular fa-clock" aria-hidden="true"></i> Entries today</div>
        <div class="stat-value">{{ number_format($summary['today']) }}</div>
    </div>
    <a class="stat" href="{{ route('admin.logs.index', ['level' => 'error']) }}" style="text-decoration:none;">
        <div class="stat-label"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Errors (7 days)</div>
        <div class="stat-value">{{ number_format($summary['errors']) }}</div>
        <div class="stat-foot">{{ $summary['errors'] ? 'Review errors →' : 'No errors' }}</div>
    </a>
    <a class="stat" href="{{ route('admin.logs.index', ['category' => 'auth', 'level' => 'warning']) }}" style="text-decoration:none;">
        <div class="stat-label"><i class="fa-solid fa-user-lock" aria-hidden="true"></i> Failed sign-ins (24 h)</div>
        <div class="stat-value">{{ number_format($summary['failed_login']) }}</div>
        <div class="stat-foot">{{ $summary['failed_login'] >= 10 ? 'Unusually high — check the IPs' : 'Admin, stylist and cashier PIN' }}</div>
    </a>
    <div class="stat">
        <div class="stat-label"><i class="fa-solid fa-box-archive" aria-hidden="true"></i> Retention</div>
        <div class="stat-value">{{ $summary['retention'] }}<small style="margin-left:4px;">days</small></div>
        <div class="stat-foot">Change in Settings → Booking rules</div>
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar" role="search">
        <div class="field grow">
            <label class="label" for="q">Search</label>
            <div class="input-icon">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input class="input input-sm" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Reference, name, action or IP">
            </div>
        </div>
        <div class="field">
            <label class="label" for="category">Area</label>
            <select class="select select-sm" id="category" name="category">
                <option value="">All areas</option>
                @foreach(\App\Models\SystemLog::CATEGORIES as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label class="label" for="level">Level</label>
            <select class="select select-sm" id="level" name="level">
                <option value="">All levels</option>
                @foreach(\App\Models\SystemLog::LEVELS as $lvl)
                    <option value="{{ $lvl }}" @selected(($filters['level'] ?? '') === $lvl)>{{ ucfirst($lvl) }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label class="label" for="actor">Done by</label>
            <select class="select select-sm" id="actor" name="actor">
                <option value="">Anyone</option>
                @foreach(['admin' => 'Admins', 'staff' => 'Stylists', 'cashier' => 'Cashier station', 'customer' => 'Customers', 'guest' => 'Visitors', 'system' => 'System'] as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['actor'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label class="label" for="from">From</label>
            <input class="input input-sm" type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" max="{{ now()->toDateString() }}">
        </div>
        <div class="field">
            <label class="label" for="to">To</label>
            <input class="input input-sm" type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" max="{{ now()->toDateString() }}">
        </div>
        @if(!empty($filters['subject']) && !empty($filters['id']))
            <input type="hidden" name="subject" value="{{ $filters['subject'] }}">
            <input type="hidden" name="id" value="{{ $filters['id'] }}">
        @endif
        <div class="row">
            <button class="btn btn-ink btn-sm" type="submit">Filter</button>
            @if($filtered || !empty($filters['subject']))<a href="{{ route('admin.logs.index') }}" class="btn btn-ghost btn-sm">Clear</a>@endif
        </div>
    </form>

    @if(!empty($filters['subject']) && !empty($filters['id']))
        <div class="alert alert-info" style="margin:var(--s-4) var(--s-5) 0;"><i class="fa-solid fa-filter" aria-hidden="true"></i>
            <div>Showing history for {{ $filters['subject'] }} #{{ $filters['id'] }} only.</div></div>
    @endif

    @if($logs->isEmpty())
        <div class="empty">
            <div class="empty-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></div>
            <h3>{{ $filtered ? 'No entries match' : 'Nothing logged yet' }}</h3>
            <p>{{ $filtered ? 'Try a wider date range or clear the filters.' : 'Activity appears here as soon as people use the system.' }}</p>
        </div>
    @else
        <div class="table-wrap">
            <table class="table table-stack log-table">
                <thead>
                    <tr><th>When</th><th>Level</th><th>What happened</th><th>By</th><th>IP address</th></tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                        <tr>
                            <td data-label="When" class="nowrap">
                                <div class="fw-600">{{ $log->created_at->format('j M Y') }}</div>
                                <div class="table-sub tabular">{{ $log->created_at->format('g:i:s A') }}</div>
                            </td>
                            <td data-label="Level">
                                <span class="badge {{ $levelBadge[$log->level] ?? 'badge-neutral' }} no-dot">{{ ucfirst($log->level) }}</span>
                            </td>
                            <td class="cell-primary log-what">
                                <div class="table-sub">{{ $log->categoryLabel() }} · <code>{{ $log->action }}</code></div>
                                <div class="log-desc">{{ $log->description }}</div>
                                @if($log->properties || $log->user_agent || $log->subject_type)
                                    <details class="log-details">
                                        <summary>Details</summary>
                                        <dl class="log-props">
                                            @if($log->subject_type)
                                                <div><dt>Record</dt><dd>
                                                    @if($log->subject_type === 'Appointment' && $liveAppointments->has($log->subject_id))
                                                        @perm('appointments.view')<a href="{{ route('admin.appointments.show', $log->subject_id) }}">Appointment #{{ $log->subject_id }}</a>@else Appointment #{{ $log->subject_id }} @endperm
                                                    @else
                                                        {{ $log->subject_type }} #{{ $log->subject_id }}
                                                    @endif
                                                    · <a href="{{ route('admin.logs.index', ['subject' => $log->subject_type, 'id' => $log->subject_id]) }}">full history</a>
                                                </dd></div>
                                            @endif
                                            @foreach((array) $log->properties as $key => $value)
                                                <div><dt>{{ str_replace('_', ' ', $key) }}</dt><dd>
                                                    @if(is_array($value) && array_key_exists('from', $value) && array_key_exists('to', $value))
                                                        <span class="log-from">{{ $fmt($value['from']) }}</span> → <span class="log-to">{{ $fmt($value['to']) }}</span>
                                                    @elseif($key === 'trace')
                                                        <pre>{{ $value }}</pre>
                                                    @elseif(is_array($value))
                                                        @foreach($value as $k => $v)<div><span class="text-muted">{{ str_replace('_', ' ', $k) }}:</span> {{ $fmt($v) }}</div>@endforeach
                                                    @else
                                                        {{ $fmt($value) }}
                                                    @endif
                                                </dd></div>
                                            @endforeach
                                            @if($log->user_agent)
                                                <div><dt>Device</dt><dd class="text-muted">{{ $log->user_agent }}</dd></div>
                                            @endif
                                        </dl>
                                    </details>
                                @endif
                            </td>
                            <td data-label="By">{{ $log->actorLabel() }}</td>
                            <td data-label="IP" class="tabular">{{ $log->ip_address ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="card-footer">{{ $logs->links() }}</div>
        @endif
    @endif
</div>
@endsection
