@extends('errors.layout')
@section('code', '419')
@section('heading', 'Session expired')
@section('message', 'For your security, this page expired. Go back, refresh and try again.')
@section('actions')
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn btn-primary">Go back</a>
@endsection
