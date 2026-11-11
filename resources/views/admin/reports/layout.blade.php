@extends('layouts.admin')
@section('title', \App\Http\Controllers\Admin\ReportController::TABS[$tab][0] . ' report')
@section('crumb', 'Reports')

@php
    $query = array_filter(['range' => $filters['range'] === 'custom' ? null : $filters['range'], 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null]);
    $period = $report->from->isSameDay($report->to)
        ? $report->from->format('l, j F Y')
        : $report->from->format('j M Y') . ' – ' . $report->to->format('j M Y');
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Reports</h1>
        <p>{{ $period }} · {{ $report->days() }} {{ \Illuminate\Support\Str::plural('day', $report->days()) }}. Figures use the appointment date; revenue counts completed or paid bills.</p>
    </div>
    <div class="row no-print">
        <button type="button" class="btn" onclick="window.print()"><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
        <a href="{{ route('admin.reports.export', ['tab' => $tab] + $query + ($tab === 'customers' ? ['min_visits' => $filters['min_visits']] : [])) }}" class="btn"><i class="fa-solid fa-download" aria-hidden="true"></i> Export CSV</a>
    </div>
</div>

<nav class="report-tabs no-print" aria-label="Report type">
    @foreach(\App\Http\Controllers\Admin\ReportController::TABS as $key => [$label, $icon])
        <a href="{{ route('admin.reports', ['tab' => $key] + $query) }}" @if($key === $tab) aria-current="page" @endif>
            <i class="fa-solid {{ $icon }}" aria-hidden="true"></i> {{ $label }}
        </a>
    @endforeach
</nav>

<form method="GET" class="card report-filter no-print" action="{{ route('admin.reports', $tab) }}">
    <div class="report-presets" role="group" aria-label="Date range">
        @foreach(\App\Services\ReportService::PRESETS as $key => $label)
            <a href="{{ route('admin.reports', ['tab' => $tab, 'range' => $key] + ($tab === 'customers' ? ['min_visits' => $filters['min_visits']] : [])) }}"
               class="{{ $filters['range'] === $key ? 'is-active' : '' }}" @if($filters['range'] === $key) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>
    <div class="report-custom">
        <div class="field">
            <label class="label" for="r-from">From</label>
            <input class="input input-sm" type="date" id="r-from" name="from" value="{{ $report->from->toDateString() }}" max="{{ now()->toDateString() }}">
        </div>
        <div class="field">
            <label class="label" for="r-to">To</label>
            <input class="input input-sm" type="date" id="r-to" name="to" value="{{ $report->to->toDateString() }}" max="{{ now()->toDateString() }}">
        </div>
        @yield('extra-filters')
        <button type="submit" class="btn btn-ink btn-sm">Apply</button>
    </div>
    @if($errors->any())
        <div class="error-text" style="flex-basis:100%;">{{ $errors->first() }}</div>
    @endif
</form>

<div class="print-only report-print-head">
    <strong>{{ setting('salon_name', 'Salon') }} — {{ \App\Http\Controllers\Admin\ReportController::TABS[$tab][0] }} report</strong><br>
    {{ $period }} · printed {{ now()->format('j M Y, g:i A') }}
</div>

@yield('report')
@endsection
