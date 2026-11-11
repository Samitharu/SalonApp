@extends('errors.layout')
@section('code', '403')
@section('heading', 'Access denied')
@section('message', (isset($exception) && $exception->getMessage()) ? $exception->getMessage() : 'You don’t have permission to view this page.')
@section('actions')
    @if(auth('admin')->check())
        <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Back to dashboard</a>
    @elseif(auth('staff')->check())
        <a href="{{ route('stylist.dashboard') }}" class="btn btn-primary">Back to my day</a>
    @else
        <a href="{{ url('/') }}" class="btn btn-primary">Go to homepage</a>
    @endif
@endsection
